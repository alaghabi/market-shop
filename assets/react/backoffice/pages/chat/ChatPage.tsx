import { useCallback, useEffect, useState } from "react";
import { useApiClient, useApiData } from "../../hooks/useApi";
import { Card, CardBody, CardHeader } from "../../components/Card";
import { Button } from "../../components/Button";
import { Badge } from "../../components/Badge";
import { LoadingState, EmptyState, ErrorState } from "../../components/States";
import { PageHeader } from "../../layout/Shell";
import { useNotification } from "../../hooks/useNotification";
import { Pagination } from "../../components/Pagination";
import { ConfirmDialog } from "../../components/ConfirmDialog";
import { useBoutique } from "../../hooks/useBoutique";

type Conversation = {
  id: string;
  boutiqueId: string;
  boutiqueName: string;
  userDisplayName?: string | null;
  guestName?: string | null;
  guestEmail?: string | null;
  lastMessage?: string | null;
  lastMessageAt?: string | null;
  unreadCount: number;
  active: boolean;
  createdAt: string;
};

type ChatMessage = {
  id: string;
  senderType: string;
  content: string;
  fileUrl?: string | null;
  fileType?: string | null;
  createdAt: string;
};

type ChatbotConfig = {
  mode: "MANUAL" | "AI";
  isEnabled: boolean;
  aiAllowed: boolean;
  chatbotEnabled: boolean;
};

export function ChatPage({
  getAccessToken,
  userRoles = [],
}: {
  getAccessToken: () => string | null;
  userRoles?: string[];
}) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const { boutique } = useBoutique();
  const [chatbotConfig, setChatbotConfig] = useState<ChatbotConfig | null>(null);
  const [chatbotLoading, setChatbotLoading] = useState(false);
  const canManageChatbot = userRoles.includes("ROLE_SUPER_ADMIN") || userRoles.includes("ROLE_BOUTIQUE_ADMIN");
  const [selected, setSelected] = useState<Conversation | null>(null);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [messageLoading, setMessageLoading] = useState(false);
  const [reply, setReply] = useState("");
  const [page, setPage] = useState(1);
  const [deleteTarget, setDeleteTarget] = useState<Conversation | null>(null);
  const [deleting, setDeleting] = useState(false);
  const pageSize = 20;

  const fetchConversations = useCallback(
    () => api.getCollection<Conversation>(`/admin/conversations?page=${page}&itemsPerPage=${pageSize}`),
    [api, page],
  );
  const { data, isLoading, error, refresh } = useApiData(
    fetchConversations,
    [page],
  );
  const conversations = data?.member ?? [];
  const totalPages = Math.max(1, Math.ceil((data?.totalItems ?? 0) / pageSize));

  const loadMessages = useCallback(
    async (conversation: Conversation) => {
      setMessageLoading(true);
      try {
        const res = await api.getCollection<ChatMessage>(
          `/conversations/${conversation.id}/messages`,
        );
        setMessages(res.member);
        await api.post(`/conversations/${conversation.id}/messages/read`, {
          senderType: "admin",
        });
        refresh();
      } catch (err) {
        showNotice(
          err instanceof Error
            ? err.message
            : "Impossible de charger la conversation.",
          "error",
        );
      } finally {
        setMessageLoading(false);
      }
    },
    [api, refresh, showNotice],
  );

  useEffect(() => {
    if (!selected) return;
    loadMessages(selected);
  }, [selected?.id]);

  useEffect(() => {
    if (!canManageChatbot || !boutique?.id) {
      setChatbotConfig(null);
      return;
    }

    setChatbotLoading(true);
    api
      .get<ChatbotConfig>('/chatbot')
      .then(setChatbotConfig)
      .catch(() => setChatbotConfig(null))
      .finally(() => setChatbotLoading(false));
  }, [api, boutique?.id, canManageChatbot]);

  async function updateChatbot(mode: "MANUAL" | "AI", isEnabled: boolean): Promise<void> {
    if (!boutique?.id) return;

    setChatbotLoading(true);
    try {
      const updated = await api.patch<ChatbotConfig>('/chatbot', {
        mode,
        isEnabled,
      });
      setChatbotConfig(updated);
      showNotice(isEnabled ? "Chatbot activé." : "Chatbot désactivé.", "success");
    } catch (err) {
      showNotice(err instanceof Error ? err.message : "Impossible de modifier le chatbot.", "error");
    } finally {
      setChatbotLoading(false);
    }
  }

  async function sendReply(event: React.FormEvent) {
    event.preventDefault();
    if (!selected || !reply.trim()) return;

    try {
      await api.post(`/conversations/${selected.id}/messages`, {
        content: reply.trim(),
      });
      setReply("");
      await loadMessages(selected);
      showNotice("Réponse envoyée.", "success");
    } catch (err) {
      showNotice(
        err instanceof Error ? err.message : "Impossible d envoyer la réponse.",
        "error",
      );
    }
  }

  async function deleteConversation() {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      await api.delete(`/conversations/${deleteTarget.id}`);
      if (selected?.id === deleteTarget.id) {
        setSelected(null);
        setMessages([]);
      }
      setDeleteTarget(null);
      showNotice("Conversation supprimée.", "success");
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : "Impossible de supprimer la conversation.", "error");
    } finally {
      setDeleting(false);
    }
  }

  if (error) return <ErrorState message={error} onRetry={refresh} />;

  return (
    <div>
      <PageHeader
        title="Messagerie"
        description="Questions chatBox des visiteurs et clients"
        actions={
          <Button type="button" variant="secondary" size="sm" onClick={refresh}>
            Actualiser
          </Button>
        }
      />
      {canManageChatbot && (
        <Card style={{ marginBottom: 16 }}>
          <CardHeader>
            <strong>Activation du chatbot</strong>
          </CardHeader>
          <CardBody>
            {!boutique ? (
              <p style={{ margin: 0 }}>Sélectionnez une boutique pour gérer son chatbot.</p>
            ) : chatbotLoading && !chatbotConfig ? (
              <LoadingState message="Chargement de la configuration chatbot..." />
            ) : (
              <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", gap: 10 }}>
                <span>
                  Boutique : <strong>{boutique.name}</strong>
                </span>
                <Badge tone={chatbotConfig?.chatbotEnabled ? "success" : "neutral"}>
                  {chatbotConfig?.chatbotEnabled ? `Actif (${chatbotConfig.mode})` : "Désactivé"}
                </Badge>
                <Button
                  type="button"
                  variant={chatbotConfig?.mode === "MANUAL" && chatbotConfig.isEnabled ? "primary" : "secondary"}
                  size="sm"
                  disabled={chatbotLoading}
                  onClick={() => { void updateChatbot("MANUAL", true); }}
                >
                  Manuel
                </Button>
                <Button
                  type="button"
                  variant={chatbotConfig?.mode === "AI" && chatbotConfig.isEnabled ? "primary" : "secondary"}
                  size="sm"
                  disabled={chatbotLoading || chatbotConfig?.aiAllowed !== true}
                  onClick={() => { void updateChatbot("AI", true); }}
                >
                  IA
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  disabled={chatbotLoading || chatbotConfig?.isEnabled !== true}
                  onClick={() => { void updateChatbot(chatbotConfig?.mode ?? "MANUAL", false); }}
                >
                  Désactiver
                </Button>
                {chatbotConfig?.aiAllowed !== true && (
                  <small style={{ color: "var(--bo-text-muted)" }}>
                    Le mode IA nécessite le module chatbot dans l’abonnement ou une extension active.
                  </small>
                )}
              </div>
            )}
          </CardBody>
        </Card>
      )}
      <div
        style={{
          display: "grid",
          gridTemplateColumns: "minmax(280px, 380px) 1fr",
          gap: 16,
        }}
      >
        <Card>
          <CardHeader>
            <strong>Conversations</strong>
          </CardHeader>
          <CardBody>
            {isLoading ? (
              <LoadingState />
            ) : conversations.length === 0 ? (
              <EmptyState
                title="Aucune conversation"
                message="Les messages chatBox apparaîtront ici."
              />
            ) : (
              <>
                <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                   {conversations.map((conversation) => {
                   const active = selected?.id === conversation.id;
                   return (
                     <div key={conversation.id} style={{ display: "flex", gap: 6, alignItems: "stretch" }}>
                     <button
                       type="button"
                       onClick={() => setSelected(conversation)}
                       className="bo-input"
                       style={{ flex: 1, minWidth: 0, display: "flex", flexDirection: "column", alignItems: "stretch", textAlign: "left", cursor: "pointer", background: active ? "var(--bo-primary-soft)" : "var(--bo-surface)", borderColor: active ? "var(--bo-primary)" : "var(--bo-border)" }}
                     >
                      <div
                        style={{
                          display: "flex",
                          justifyContent: "space-between",
                          gap: 8,
                        }}
                      >
                        <strong>
                          {conversation.guestName ||
                            conversation.userDisplayName ||
                            "Visiteur"}
                        </strong>
                        {conversation.unreadCount > 0 && (
                          <Badge tone="warning">
                            {conversation.unreadCount}
                          </Badge>
                        )}
                      </div>
                      <div
                         style={{ marginTop: 6, fontSize: 12, color: "var(--bo-text-muted)" }}
                      >
                        {conversation.boutiqueName}
                      </div>
                      {conversation.lastMessage && (
                        <div
                          style={{
                             marginTop: 8,
                            overflow: "hidden",
                            textOverflow: "ellipsis",
                            whiteSpace: "nowrap",
                            fontSize: 13,
                          }}
                        >
                          {conversation.lastMessage}
                        </div>
                      )}
                     </button>
                     <button type="button" aria-label={`Supprimer la conversation de ${conversation.guestName || conversation.userDisplayName || "Visiteur"}`} title="Supprimer la conversation" onClick={(event) => { event.stopPropagation(); setDeleteTarget(conversation); }} style={{ flex: "0 0 30px", alignSelf: "flex-start", width: 30, height: 30, marginTop: 2, padding: 0, border: 0, borderRadius: "50%", background: "transparent", color: "var(--bo-error)", display: "grid", placeItems: "center", cursor: "pointer" }}>
                       <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" d="M6 7h12m-9 0v10m6-10v10M9 7l1-3h4l1 3m-8 0 1 14h8l1-14" /></svg>
                     </button>
                     </div>
                   );
                  })}
                </div>
                <Pagination page={page} totalPages={totalPages} onPageChange={(nextPage) => { setPage(nextPage); setSelected(null); }} />
              </>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <strong>
              {selected
                ? selected.guestName ||
                  selected.userDisplayName ||
                  "Conversation visiteur"
                : "Conversation"}
            </strong>
          </CardHeader>
          <CardBody>
            {!selected ? (
              <EmptyState
                title="Sélectionnez une conversation"
                message="Choisissez un échange pour lire les messages et répondre."
              />
            ) : messageLoading ? (
              <LoadingState />
            ) : (
              <>
                <div
                  style={{
                    minHeight: 360,
                    maxHeight: 480,
                    overflowY: "auto",
                    display: "flex",
                    flexDirection: "column",
                    gap: 10,
                    paddingBottom: 12,
                  }}
                >
                  {messages.length === 0 ? (
                    <EmptyState
                      title="Aucun message"
                      message="Cette conversation est vide."
                    />
                  ) : (
                    messages.map((message) => {
                      const fromAdmin =
                        message.senderType === "admin" ||
                        message.senderType === "bot";
                      return (
                        <div
                          key={message.id}
                          style={{
                            alignSelf: fromAdmin ? "flex-end" : "flex-start",
                            maxWidth: "78%",
                          }}
                        >
                          <div
                            style={{
                              borderRadius: 14,
                              padding: "10px 12px",
                              background: fromAdmin
                                ? "var(--bo-primary)"
                                : "var(--bo-surface-muted)",
                              color: fromAdmin ? "#fff" : "var(--bo-text)",
                            }}
                          >
                            {message.fileUrl && (
                              <a
                                href={message.fileUrl}
                                target="_blank"
                                rel="noreferrer"
                                style={{
                                  color: fromAdmin
                                    ? "#fff"
                                    : "var(--bo-primary)",
                                }}
                              >
                                Pièce jointe
                              </a>
                            )}
                            {message.content && <div>{message.content}</div>}
                          </div>
                          <div
                            style={{
                              marginTop: 4,
                              fontSize: 11,
                              color: "var(--bo-text-muted)",
                              textAlign: fromAdmin ? "right" : "left",
                            }}
                          >
                            {new Date(message.createdAt).toLocaleString(
                              "fr-FR",
                            )}
                          </div>
                        </div>
                      );
                    })
                  )}
                </div>

                <form
                  onSubmit={sendReply}
                  style={{
                    display: "flex",
                    gap: 8,
                    borderTop: "1px solid var(--bo-border)",
                    paddingTop: 12,
                  }}
                >
                  <input
                    className="bo-input"
                    value={reply}
                    onChange={(e) => setReply(e.target.value)}
                    placeholder="Répondre au client..."
                    style={{ flex: 1 }}
                  />
                  <Button type="submit" disabled={!reply.trim()}>
                    Envoyer
                  </Button>
                </form>
              </>
            )}
          </CardBody>
        </Card>
      </div>
      <ConfirmDialog isOpen={!!deleteTarget} onClose={() => { if (!deleting) setDeleteTarget(null); }} onConfirm={deleteConversation} title="Supprimer la conversation" message="Cette conversation et ses messages seront supprimés définitivement." confirmLabel="Supprimer" danger isLoading={deleting} />
    </div>
  );
}
