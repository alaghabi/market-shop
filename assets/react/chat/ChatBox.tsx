import { useCallback, useEffect, useRef, useState } from 'react';

type Message = {
  id: string;
  conversationId: string;
  senderType: string;
  content: string;
  fileUrl?: string | null;
  fileType?: string | null;
  createdAt: string;
};

type GuestChatSession = {
  conversationId: string;
  guestAccessToken: string;
  guestName?: string;
  guestEmail?: string;
  guestPhone?: string;
};

type ChatBoxProps = {
  boutiqueId: string;
  apiBaseUrl: string;
  token?: string | null;
  primaryColor?: string;
};

export function ChatBox({ boutiqueId, apiBaseUrl, token, primaryColor }: ChatBoxProps) {
  const accent = primaryColor ?? 'var(--sf-accent, var(--ds-primary, #111111))';
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState<Message[]>([]);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [guestAccessToken, setGuestAccessToken] = useState<string | null>(null);
  const [content, setContent] = useState('');
  const [guestName, setGuestName] = useState('');
  const [guestEmail, setGuestEmail] = useState('');
  const [guestPhone, setGuestPhone] = useState('');
  const [showForm, setShowForm] = useState(true);
  const [loading, setLoading] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [botTyping, setBotTyping] = useState(false);
  const eventSourceRef = useRef<EventSource | null>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const cookieName = `hanooti_chat_${boutiqueId}`;

  const scrollToBottom = useCallback(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, []);

  useEffect(() => {
    scrollToBottom();
  }, [messages, botTyping, scrollToBottom]);

  useEffect(() => {
    const session = readCookie<GuestChatSession>(cookieName);
    if (!session?.conversationId || !session.guestAccessToken) return;

    setConversationId(session.conversationId);
    setGuestAccessToken(session.guestAccessToken);
    setGuestName(session.guestName ?? '');
    setGuestEmail(session.guestEmail ?? '');
    setGuestPhone(session.guestPhone ?? '');
    setShowForm(false);
  }, [cookieName]);

  useEffect(() => {
    if (!conversationId) return;

    const mercureUrl = import.meta.env?.VITE_MERCURE_PUBLIC_URL || process.env.MERCURE_PUBLIC_URL || 'http://localhost:3000/.well-known/mercure';
    const url = new URL(mercureUrl);
    url.searchParams.append('topic', `chat/conversation/${conversationId}`);

    const es = new EventSource(url);
    eventSourceRef.current = es;

    es.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);
        if (data.type === 'typing') {
          setBotTyping(data.senderType === 'bot');
          return;
        }
        if (data.type === 'read') return;
        setMessages((prev) => {
          if (prev.some((m) => m.id === data.id)) return prev;
          return [...prev, data];
        });
        setBotTyping(false);
      } catch { /* ignore */ }
    };

    return () => {
      es.close();
      eventSourceRef.current = null;
    };
  }, [conversationId]);

  useEffect(() => {
    if (!conversationId) return;
    fetchMessages();
  }, [conversationId]);

  async function fetchMessages() {
    if (!conversationId) return;
    try {
      const headers: Record<string, string> = {};
      if (token) headers['Authorization'] = `Bearer ${token}`;
      if (guestAccessToken) headers['X-Guest-Chat-Token'] = guestAccessToken;

      const res = await fetch(
        `${apiBaseUrl}/conversations/${conversationId}/messages`,
        { headers },
      );
      if (res.ok) {
        const data = await res.json();
        setMessages(Array.isArray(data) ? data : data['hydra:member'] ?? []);
      }
    } catch { /* ignore */ }
  }

  async function startConversation() {
    if (!guestName.trim()) return;
    setLoading(true);

    try {
      const headers: Record<string, string> = { 'Content-Type': 'application/json' };
      if (token) headers['Authorization'] = `Bearer ${token}`;

      const res = await fetch(
        `${apiBaseUrl}/conversations`,
        {
          method: 'POST',
          headers,
          body: JSON.stringify({
            boutiqueId,
            guestName: guestName.trim(),
            guestEmail: guestEmail.trim() || undefined,
            guestPhone: guestPhone.trim() || undefined,
          }),
        },
      );

      if (res.ok) {
        const data = await res.json();
        setConversationId(data.id);
        if (data.guestAccessToken) {
          setGuestAccessToken(data.guestAccessToken);
          writeCookie(cookieName, {
            conversationId: data.id,
            guestAccessToken: data.guestAccessToken,
            guestName: guestName.trim(),
            guestEmail: guestEmail.trim() || undefined,
            guestPhone: guestPhone.trim() || undefined,
          });
        }
        setShowForm(false);
      }
    } catch { /* ignore */ } finally {
      setLoading(false);
    }
  }

  async function sendMessage(e: React.FormEvent) {
    e.preventDefault();
    if (!content.trim() || !conversationId) return;

    const headers: Record<string, string> = { 'Content-Type': 'application/json' };
    if (token) headers['Authorization'] = `Bearer ${token}`;
    if (guestAccessToken) headers['X-Guest-Chat-Token'] = guestAccessToken;

    try {
      await fetch(
        `${apiBaseUrl}/conversations/${conversationId}/messages`,
        {
          method: 'POST',
          headers,
          body: JSON.stringify({ content: content.trim(), senderType: 'user' }),
        },
      );
      setContent('');
    } catch { /* ignore */ }
  }

  async function uploadFile(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file || !conversationId) return;

    setUploading(true);
    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('conversationId', conversationId);

      const uploadHeaders: Record<string, string> = {};
      if (token) uploadHeaders['Authorization'] = `Bearer ${token}`;
      if (guestAccessToken) uploadHeaders['X-Guest-Chat-Token'] = guestAccessToken;

      const uploadRes = await fetch(`${apiBaseUrl}/chat/upload`, {
        method: 'POST',
        headers: uploadHeaders,
        body: formData,
      });

      if (!uploadRes.ok) return;
      const { url, type } = await uploadRes.json();

      const headers: Record<string, string> = { 'Content-Type': 'application/json' };
      if (token) headers['Authorization'] = `Bearer ${token}`;
      if (guestAccessToken) headers['X-Guest-Chat-Token'] = guestAccessToken;

      await fetch(
        `${apiBaseUrl}/conversations/${conversationId}/messages`,
        {
          method: 'POST',
          headers,
          body: JSON.stringify({
            content: '',
            senderType: 'user',
            fileUrl: url,
            fileType: type,
          }),
        },
      );
    } catch { /* ignore */ } finally {
      setUploading(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  }

  return (
    <div style={{
      position: 'fixed',
      bottom: '16px',
      right: '16px',
      zIndex: 1000,
      fontFamily: 'system-ui, sans-serif',
    }}>
      {!open && (
        <button
          onClick={() => setOpen(true)}
          style={{
            width: '56px',
            height: '56px',
            borderRadius: '50%',
            border: 'none',
             background: accent,
            color: '#fff',
            fontSize: '24px',
            cursor: 'pointer',
            boxShadow: '0 4px 12px rgba(0,0,0,0.25)',
          }}
          aria-label="Ouvrir le chat"
        >
          <span className="chat-launcher-emoji" aria-hidden="true">💬</span>
        </button>
      )}

      {open && (
        <div style={{
          width: '360px',
          maxHeight: '520px',
          background: '#fff',
          borderRadius: '12px',
          boxShadow: '0 8px 32px rgba(0,0,0,0.15)',
          display: 'flex',
          flexDirection: 'column',
          overflow: 'hidden',
        }}>
          <div style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            padding: '12px 16px',
             background: accent,
            color: '#fff',
          }}>
            <strong>Support</strong>
            <button
              onClick={() => setOpen(false)}
              style={{ background: 'none', border: 'none', color: '#fff', cursor: 'pointer', fontSize: '18px' }}
              aria-label="Fermer"
            >
              ✕
            </button>
          </div>

          {showForm && !conversationId ? (
            <div style={{ padding: '16px', display: 'flex', flexDirection: 'column', gap: '8px' }}>
              <p style={{ margin: '0 0 8px', fontSize: '14px', color: '#555' }}>Laissez-nous un message :</p>
              <input
                placeholder="Votre nom *"
                value={guestName}
                onChange={(e) => setGuestName(e.target.value)}
                style={inputStyle}
                required
              />
              <input
                placeholder="Votre email"
                type="email"
                value={guestEmail}
                onChange={(e) => setGuestEmail(e.target.value)}
                style={inputStyle}
              />
              <input
                placeholder="Votre téléphone"
                value={guestPhone}
                onChange={(e) => setGuestPhone(e.target.value)}
                style={inputStyle}
              />
              <button
                onClick={startConversation}
                disabled={loading || !guestName.trim()}
                style={sendBtnStyle(accent)}
              >
                {loading ? 'Envoi...' : 'Démarrer la conversation'}
              </button>
            </div>
          ) : (
            <>
              <div style={{
                flex: 1,
                overflowY: 'auto',
                padding: '12px',
                display: 'flex',
                flexDirection: 'column',
                gap: '8px',
                minHeight: '300px',
                maxHeight: '380px',
              }}>
                {messages.length === 0 && !botTyping && (
                  <p style={{ textAlign: 'center', color: '#999', fontSize: '14px', marginTop: '40px' }}>
                    Aucun message. Écrivez-nous !
                  </p>
                )}
                {messages.map((msg) => (
                  <div
                    key={msg.id}
                    style={{
                      alignSelf: msg.senderType === 'user' ? 'flex-end' : 'flex-start',
                       background: msg.senderType === 'user' ? accent : '#f0f0f0',
                      color: msg.senderType === 'user' ? '#fff' : '#333',
                      padding: '8px 12px',
                      borderRadius: '12px',
                      maxWidth: '80%',
                      fontSize: '14px',
                      wordBreak: 'break-word',
                    }}
                  >
                    {msg.fileUrl && (
                      <div style={{ marginBottom: msg.content ? '4px' : 0 }}>
                        {msg.fileType?.startsWith('image') ? (
                          <img
                            src={msg.fileUrl}
                            alt="Pièce jointe"
                            style={{ maxWidth: '100%', borderRadius: '8px' }}
                          />
                        ) : (
                           <a href={msg.fileUrl} target="_blank" rel="noopener noreferrer" style={{ color: msg.senderType === 'user' ? '#fff' : accent }}>
                            📎 Fichier joint
                          </a>
                        )}
                      </div>
                    )}
                    {msg.content}
                    <div style={{
                      fontSize: '10px',
                      opacity: 0.7,
                      marginTop: '4px',
                    }}>
                      {msg.senderType === 'bot' && <span style={{ marginRight: '4px' }}>🤖</span>}
                      {new Date(msg.createdAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
                    </div>
                  </div>
                ))}
                {botTyping && (
                  <div style={{
                    alignSelf: 'flex-start',
                    background: '#f0f0f0',
                    color: '#999',
                    padding: '8px 12px',
                    borderRadius: '12px',
                    fontSize: '14px',
                    fontStyle: 'italic',
                  }}>
                    L'assistant écrit...
                  </div>
                )}
                <div ref={messagesEndRef} />
              </div>

              <form onSubmit={sendMessage} style={{
                display: 'flex',
                gap: '8px',
                padding: '8px 12px',
                borderTop: '1px solid #eee',
              }}>
                <input
                  ref={fileInputRef}
                  type="file"
                  onChange={uploadFile}
                  style={{ display: 'none' }}
                  accept="image/*,.pdf,.doc,.docx,.txt,.zip"
                />
                <button
                  type="button"
                  onClick={() => fileInputRef.current?.click()}
                  disabled={uploading}
                  style={{
                    background: 'none',
                    border: 'none',
                    cursor: 'pointer',
                    fontSize: '20px',
                    padding: '4px',
                  }}
                  aria-label="Joindre un fichier"
                >
                  📎
                </button>
                <input
                  placeholder={uploading ? 'Upload...' : 'Votre message'}
                  value={content}
                  onChange={(e) => setContent(e.target.value)}
                  style={{
                    flex: 1,
                    border: '1px solid #ddd',
                    borderRadius: '20px',
                    padding: '8px 12px',
                    fontSize: '14px',
                    outline: 'none',
                  }}
                  disabled={uploading}
                />
                <button
                  type="submit"
                  disabled={!content.trim() || uploading}
                  style={{
            background: accent,
                    color: '#fff',
                    border: 'none',
                    borderRadius: '20px',
                    padding: '8px 16px',
                    cursor: 'pointer',
                    fontSize: '14px',
                  }}
                >
                  Envoyer
                </button>
              </form>
            </>
          )}
        </div>
      )}
    </div>
  );
}

const inputStyle: React.CSSProperties = {
  border: '1px solid #ddd',
  borderRadius: '8px',
  padding: '10px 12px',
  fontSize: '14px',
  outline: 'none',
  width: '100%',
  boxSizing: 'border-box',
};

function sendBtnStyle(accent: string): React.CSSProperties {
  return {
    background: accent,
    color: '#fff',
    border: 'none',
    borderRadius: '8px',
    padding: '10px 16px',
    cursor: 'pointer',
    fontSize: '14px',
    fontWeight: 600,
  };
}

function readCookie<T>(name: string): T | null {
  const cookie = document.cookie.split('; ').find((item) => item.startsWith(`${name}=`));
  if (!cookie) return null;

  try {
    return JSON.parse(decodeURIComponent(cookie.slice(name.length + 1))) as T;
  } catch {
    return null;
  }
}

function writeCookie(name: string, value: unknown) {
  const maxAge = 60 * 60 * 24 * 30;
  document.cookie = `${name}=${encodeURIComponent(JSON.stringify(value))}; Max-Age=${maxAge}; Path=/; SameSite=Lax`;
}
