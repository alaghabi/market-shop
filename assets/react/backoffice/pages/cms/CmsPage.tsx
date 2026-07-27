import { useState, useCallback } from "react";
import { useApiClient, useApiData } from "../../hooks/useApi";
import { Card, CardHeader, CardBody } from "../../components/Card";
import { Button } from "../../components/Button";
import { Table } from "../../components/Table";
import { Badge } from "../../components/Badge";
import { Pagination } from "../../components/Pagination";
import { Modal } from "../../components/Modal";
import { ConfirmDialog } from "../../components/ConfirmDialog";
import { FormField, Input, Select, Textarea } from "../../components/FormField";
import { LoadingState, EmptyState, ErrorState } from "../../components/States";
import { PageHeader } from "../../layout/Shell";
import { useNotification } from "../../hooks/useNotification";
import { useBoutique } from "../../hooks/useBoutique";
import {
  BoutiqueFormSelect,
  resolveFormBoutiqueId,
} from "../../components/BoutiqueFormSelect";

type CmsPageRecord = {
  id: string;
  boutiqueId?: string;
  title: string;
  slug: string;
  status: string;
  showInHeader?: boolean;
  showInFooter?: boolean;
  createdAt: string;
  content?: string;
};

const PAGE_SIZE = 20;

export function CmsManagementPage({
  getAccessToken,
}: {
  getAccessToken: () => string | null;
}) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const { boutique } = useBoutique();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [modalOpen, setModalOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<CmsPageRecord | null>(null);
  const [editing, setEditing] = useState<CmsPageRecord | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [form, setForm] = useState({
    boutiqueId: "",
    title: "",
    slug: "",
    content: "",
    isActive: true,
    showInHeader: false,
    showInFooter: false,
  });

  const fetchData = useCallback(async () => {
    const params = new URLSearchParams();
    params.set("page", String(page));
    params.set("itemsPerPage", String(PAGE_SIZE));
    if (search) params.set("title", search);
    return api.getCollection<CmsPageRecord>("/cms/pages?" + params.toString());
  }, [api, page, search]);

  const { data, isLoading, error, refresh } = useApiData(fetchData, [
    page,
    search,
  ]);
  const pages = data?.member ?? [];
  const totalItems = data?.totalItems ?? 0;
  const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));

  function openCreate() {
    setEditing(null);
    setForm({ boutiqueId: "", title: "", slug: "", content: "", isActive: true, showInHeader: false, showInFooter: false });
    setModalOpen(true);
  }
  function openEdit(p: CmsPageRecord) {
    setEditing(p);
    setForm({
      boutiqueId: p.boutiqueId ?? "",
      title: p.title,
      slug: p.slug,
      content: p.content ?? "",
      isActive: p.status === "PUBLISHED",
      showInHeader: p.showInHeader === true,
      showInFooter: p.showInFooter === true,
    });
    setModalOpen(true);
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    const boutiqueId = resolveFormBoutiqueId(boutique?.id, form.boutiqueId);
    if (!boutiqueId) {
      showNotice("Sélectionnez une boutique.", "error");
      return;
    }
    setSubmitting(true);
    try {
      const body = {
        boutiqueId,
        title: form.title.trim(),
        slug: form.slug.trim() || null,
        content: form.content,
        description: form.content || null,
        type: "CUSTOM",
        status: form.isActive ? "PUBLISHED" : "DRAFT",
        isHomepage: false,
        showInHeader: form.showInHeader,
        showInFooter: form.showInFooter,
        sortOrder: 0,
        blocks: [],
      };
      if (editing) {
        await api.patch("/cms/pages/" + editing.id, body);
        showNotice("Page mise à jour.", "success");
      } else {
        await api.post("/cms/pages", body);
        showNotice("Page créée.", "success");
      }
      setModalOpen(false);
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : "Erreur.", "error");
    } finally {
      setSubmitting(false);
    }
  }

  async function handlePublication(page: CmsPageRecord) {
    try {
      const action = page.status === "PUBLISHED" ? "unpublish" : "publish";
      await api.post(`/cms/pages/${page.id}/${action}`);
      showNotice(action === "publish" ? "Page publiée." : "Page dépubliée.", "success");
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : "Erreur.", "error");
    }
  }

  async function handleDelete() {
    if (!deleteTarget) return;
    try {
      await api.delete("/cms/pages/" + deleteTarget.id);
      showNotice("Page supprimée.", "success");
      setDeleteTarget(null);
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : "Erreur.", "error");
    }
  }

  const columns = [
    {
      key: "title",
      label: "Titre",
      render: (p: CmsPageRecord) => <strong>{p.title}</strong>,
    },
    {
      key: "slug",
      label: "Slug",
      render: (p: CmsPageRecord) => (
        <code style={{ fontSize: 12 }}>{p.slug}</code>
      ),
    },
    {
      key: "status",
      label: "Statut",
      render: (p: CmsPageRecord) => (
        <Badge tone={p.status === "PUBLISHED" ? "success" : "neutral"}>
          {p.status === "PUBLISHED" ? "Publiée" : "Brouillon"}
        </Badge>
      ),
    },
  ];

  if (error) return <ErrorState message={error} onRetry={refresh} />;

  return (
    <div>
      <PageHeader
        title="CMS"
        description="Pages et contenu"
        actions={<Button onClick={openCreate}>+ Nouvelle page</Button>}
      />
      <Card>
        <CardHeader>
          <input
            className="bo-input"
            style={{ maxWidth: 320 }}
            placeholder="Rechercher..."
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
          />
        </CardHeader>
        <CardBody>
          {isLoading ? (
            <LoadingState />
          ) : pages.length === 0 ? (
            <EmptyState
              title="Aucune page"
              message="Créez votre première page."
              action={{ label: "+ Nouvelle page", onClick: openCreate }}
            />
          ) : (
            <>
            <Table
              columns={columns}
              data={pages}
              onRowClick={openEdit}
              renderActions={(page) => (
                <div style={{ display: "flex", gap: 6 }}>
                  <Button
                    size="sm"
                    variant="secondary"
                    onClick={(event) => {
                      event.stopPropagation();
                      void handlePublication(page);
                    }}
                  >
                    {page.status === "PUBLISHED" ? "Dépublier" : "Publier"}
                  </Button>
                  <Button
                    size="sm"
                    variant="danger"
                    onClick={(event) => {
                      event.stopPropagation();
                      setDeleteTarget(page);
                    }}
                  >
                    Supprimer
                  </Button>
                </div>
              )}
            />
              <Pagination
                page={page}
                totalPages={totalPages}
                onPageChange={setPage}
              />
            </>
          )}
        </CardBody>
      </Card>

      <Modal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editing ? "Modifier" : "Nouvelle page"}
        width="640px"
        footer={
          <>
            <Button variant="secondary" onClick={() => setModalOpen(false)}>
              Annuler
            </Button>
            <Button onClick={handleSubmit} disabled={submitting}>
              {submitting ? "..." : editing ? "Mettre à jour" : "Créer"}
            </Button>
          </>
        }
      >
        <form className="bo-form" onSubmit={handleSubmit}>
          <BoutiqueFormSelect
            value={form.boutiqueId}
            onChange={(boutiqueId) => setForm((f) => ({ ...f, boutiqueId }))}
          />
          <FormField label="Titre" required>
            <Input
              required
              value={form.title}
              onChange={(e) =>
                setForm((f) => ({ ...f, title: e.target.value }))
              }
            />
          </FormField>
          <FormField label="Slug" hint="Exemple : offers. Laissez vide pour le générer depuis le titre.">
            <Input
              value={form.slug}
              onChange={(e) => setForm((f) => ({ ...f, slug: e.target.value }))}
              placeholder="offers"
            />
          </FormField>
          <FormField label="Contenu (HTML)">
            <Textarea
              rows={12}
              value={form.content}
              onChange={(e) =>
                setForm((f) => ({ ...f, content: e.target.value }))
              }
            />
          </FormField>
          <label className="bo-checkbox">
            <input
              type="checkbox"
              checked={form.isActive}
              onChange={(e) =>
                setForm((f) => ({ ...f, isActive: e.target.checked }))
              }
            />{" "}
            Publiée
          </label>
          <label className="bo-checkbox">
            <input
              type="checkbox"
              checked={form.showInHeader}
              onChange={(e) => setForm((f) => ({ ...f, showInHeader: e.target.checked }))}
            />{" "}
            Afficher dans le menu principal
          </label>
          <label className="bo-checkbox">
            <input
              type="checkbox"
              checked={form.showInFooter}
              onChange={(e) => setForm((f) => ({ ...f, showInFooter: e.target.checked }))}
            />{" "}
            Afficher dans le pied de page
          </label>
        </form>
      </Modal>

      <ConfirmDialog
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Supprimer"
        message={`Supprimer "${deleteTarget?.title}" ?`}
        confirmLabel="Supprimer"
        danger
      />
    </div>
  );
}
