import { useCallback, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useApiClient, useApiData } from "../../hooks/useApi";
import { useNotification } from "../../hooks/useNotification";
import { useBoutique } from "../../hooks/useBoutique";
import { Card, CardBody, CardHeader } from "../../components/Card";
import { Button } from "../../components/Button";
import { Badge } from "../../components/Badge";
import { ConfirmDialog } from "../../components/ConfirmDialog";
import { LoadingState, EmptyState, ErrorState } from "../../components/States";
import { Table } from "../../components/Table";
import { PageHeader } from "../../layout/Shell";
import { Pagination } from "../../components/Pagination";
import type { Announcement } from "./announcementTypes";

const PAGE_SIZE = 20;

export function AnnouncementsPage({
  getAccessToken,
  userRoles = [],
}: {
  getAccessToken: () => string | null;
  userRoles?: string[];
}) {
  const api = useApiClient(getAccessToken);
  const navigate = useNavigate();
  const { showNotice } = useNotification();
  const { boutique } = useBoutique();
  const isSuperAdmin = userRoles.includes("ROLE_SUPER_ADMIN");
  const [deleteTarget, setDeleteTarget] = useState<Announcement | null>(null);
  const [page, setPage] = useState(1);

  const fetchAnnouncements = useCallback(
    () =>
        api.getCollection<Announcement>(
          `${isSuperAdmin ? "/admin/announcements" : "/announcements"}?page=${page}&itemsPerPage=${PAGE_SIZE}`,
        ),
    [api, boutique, isSuperAdmin, page],
  );
  const { data, isLoading, error, refresh } = useApiData(fetchAnnouncements, [page]);
  const announcements = data?.member ?? [];
  const totalPages = Math.max(1, Math.ceil((data?.totalItems ?? 0) / PAGE_SIZE));

  async function handleDelete(): Promise<void> {
    if (!deleteTarget) return;
    try {
      await api.delete(`/announcements/${deleteTarget.id}`);
      showNotice("Annonce supprimée.", "success");
      setDeleteTarget(null);
      refresh();
    } catch (err) {
      showNotice(
        err instanceof Error ? err.message : "Erreur lors de la suppression.",
        "error",
      );
    }
  }

  const columns = [
    {
      key: "title",
      label: "Annonce",
      render: (item: Announcement) => (
        <div>
          <strong>{item.title || item.content.slice(0, 60)}</strong>
          <div style={{ fontSize: 12, color: "var(--bo-text-muted)" }}>
            {item.position}
          </div>
        </div>
      ),
    },
    {
      key: "target",
      label: "Cible",
      render: (item: Announcement) => (
        <Badge tone="neutral">
          {item.productIds?.length
            ? "Produit"
            : item.categoryIds?.length
              ? "Catégorie"
              : "Boutique"}
        </Badge>
      ),
    },
    {
      key: "displayType",
      label: "Type",
      render: (item: Announcement) => (
        <Badge tone="info">{item.displayType}</Badge>
      ),
    },
    {
      key: "active",
      label: "Statut",
      render: (item: Announcement) => (
        <Badge tone={item.active ? "success" : "neutral"}>
          {item.active ? "Active" : "Inactive"}
        </Badge>
      ),
    },
  ];

  if (error) return <ErrorState message={error} onRetry={refresh} />;

  return (
    <div>
      <PageHeader
        title="Annonces"
        description="Affichez des messages promotionnels sur votre boutique, vos produits ou vos catégories."
        actions={
          <Button onClick={() => navigate("/admin/announcements/new")}>
            + Nouvelle annonce
          </Button>
        }
      />
      <Card>
        <CardHeader>
          <span style={{ color: "var(--bo-text-muted)", fontSize: 13 }}>
            {announcements.length} annonce{announcements.length > 1 ? "s" : ""}
          </span>
        </CardHeader>
        <CardBody>
          {isLoading ? (
            <LoadingState />
          ) : announcements.length === 0 ? (
            <EmptyState
              title="Aucune annonce"
              message="Créez une annonce pour votre boutique."
              action={{
                label: "+ Nouvelle annonce",
                onClick: () => navigate("/admin/announcements/new"),
              }}
            />
          ) : (
            <>
              <Table
                columns={columns}
                data={announcements}
                onRowClick={(item) =>
                  navigate(`/admin/announcements/${item.id}/edit`)
                }
                renderActions={(item) => (
                  <Button
                    size="sm"
                    variant="danger"
                    onClick={(event) => {
                      event.stopPropagation();
                      setDeleteTarget(item);
                    }}
                  >
                    Supprimer
                  </Button>
                )}
              />
              <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
            </>
          )}
        </CardBody>
      </Card>
      <ConfirmDialog
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Supprimer l’annonce"
        message={`Supprimer « ${deleteTarget?.title || deleteTarget?.content.slice(0, 40)} » ?`}
        confirmLabel="Supprimer"
        danger
      />
    </div>
  );
}
