import { useCallback, useState } from "react";
import { useApiClient, useApiData } from "../../hooks/useApi";
import { useNotification } from "../../hooks/useNotification";
import { PageHeader } from "../../layout/Shell";
import { Card, CardBody } from "../../components/Card";
import { Button } from "../../components/Button";
import { Badge } from "../../components/Badge";
import { LoadingState, EmptyState, ErrorState } from "../../components/States";
import { FiltersBar } from "../../components/FiltersBar";
import { notificationLink } from "../../utils/notificationLink";
import { Pagination } from "../../components/Pagination";

type NotificationItem = {
  id: string;
  recipientIdentifier?: string | null;
  type: string;
  title: string;
  message: string;
  boutiqueId?: string | null;
  read: boolean;
  createdAt: string;
};

export function NotificationsPage({
  getAccessToken,
  userRoles = [],
}: {
  getAccessToken: () => string | null;
  userRoles?: string[];
}) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const isSuperAdmin = userRoles.includes("ROLE_SUPER_ADMIN");
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const pageSize = 20;

  const fetchNotifications = useCallback(
    () => api.getCollection<NotificationItem>(`/notifications?page=${page}&itemsPerPage=${pageSize}`),
    [api, page],
  );
  const { data, isLoading, error, refresh } = useApiData(
    fetchNotifications,
    [page],
  );
  const notifications = (data?.member ?? []).filter((item) => {
    const matchesSearch =
      !search ||
      `${item.title} ${item.message} ${item.type}`
        .toLowerCase()
        .includes(search.toLowerCase());
    const matchesStatus =
      !status || (status === "unread" ? !item.read : item.read);
    return matchesSearch && matchesStatus;
  });

  const markAsRead = async (item: NotificationItem) => {
    if (item.read) return;
    try {
      await api.patch(`/notifications/${item.id}/read`, {});
      refresh();
    } catch (err) {
      showNotice(
        err instanceof Error
          ? err.message
          : "Impossible de marquer la notification.",
        "error",
      );
    }
  };

  const openNotification = async (item: NotificationItem) => {
    await markAsRead(item);
    window.location.assign(notificationLink(item, isSuperAdmin));
  };

  return (
    <div>
      <PageHeader
        title="Notifications"
        description="Consultez les notifications générées par la plateforme."
      />
      <Card>
        <CardBody>
          <FiltersBar
            search={search}
            onSearchChange={(value) => { setSearch(value); setPage(1); }}
            status={status}
            onStatusChange={(value) => { setStatus(value); setPage(1); }}
            statusOptions={[
              { value: "unread", label: "Non lues" },
              { value: "read", label: "Lues" },
            ]}
          />
          {isLoading ? (
            <LoadingState />
          ) : error ? (
            <ErrorState message={error} onRetry={refresh} />
          ) : notifications.length === 0 ? (
            <EmptyState
              title="Aucune notification"
              message="Les notifications réelles apparaîtront ici."
            />
          ) : (
            <div style={{ display: "grid", gap: 8, marginTop: 16 }}>
              {notifications.map((item) => (
                <div
                  key={item.id}
                  role="link"
                  tabIndex={0}
                  onClick={() => void openNotification(item)}
                  onKeyDown={(event) => {
                    if (event.key === "Enter" || event.key === " ") {
                      event.preventDefault();
                      void openNotification(item);
                    }
                  }}
                  style={{
                    display: "grid",
                    gridTemplateColumns: "minmax(0, 1fr) auto",
                    gap: 16,
                    alignItems: "center",
                    padding: "12px 14px",
                    border: "1px solid var(--bo-border)",
                    borderRadius: 10,
                    cursor: "pointer",
                    background: item.read
                      ? undefined
                      : "color-mix(in srgb, var(--bo-primary) 5%, transparent)",
                  }}
                >
                  <div>
                    <div
                      style={{
                        display: "flex",
                        gap: 8,
                        alignItems: "center",
                        flexWrap: "wrap",
                      }}
                    >
                      <strong>{item.title}</strong>
                      <Badge tone={item.read ? "neutral" : "info"}>
                        {item.read ? "Lue" : "Non lue"}
                      </Badge>
                      <Badge tone="neutral">{item.type}</Badge>
                    </div>
                    <p
                      style={{
                        margin: "6px 0",
                        color: "var(--bo-text-secondary)",
                      }}
                    >
                      {item.message}
                    </p>
                    <div
                      style={{ fontSize: 12, color: "var(--bo-text-muted)" }}
                    >
                      {new Date(item.createdAt).toLocaleString("fr-FR")}
                      {item.boutiqueId
                        ? ` · Boutique ${item.boutiqueId}`
                        : " · Plateforme"}
                      {item.recipientIdentifier
                        ? ` · ${item.recipientIdentifier}`
                        : ""}
                    </div>
                  </div>
                  {!item.read && (
                    <Button
                      variant="secondary"
                      size="sm"
                      onClick={(event) => {
                        event.stopPropagation();
                        void markAsRead(item);
                      }}
                    >
                      Marquer lue
                    </Button>
                  )}
                </div>
              ))}
            </div>
          )}
          <Pagination page={page} totalPages={Math.max(1, Math.ceil((data?.totalItems ?? 0) / pageSize))} onPageChange={setPage} />
        </CardBody>
      </Card>
    </div>
  );
}
