import { useCallback } from "react";
import { useApiClient, useApiData } from "../../hooks/useApi";
import { Badge, statusBadge } from "../../components/Badge";
import { Card, CardBody, CardHeader } from "../../components/Card";
import { EmptyState, ErrorState, LoadingState } from "../../components/States";
import { Button } from "../../components/Button";
import { PageHeader } from "../../layout/Shell";
import { useNotification } from "../../hooks/useNotification";

type BoutiqueTarget = {
  id: string;
  name: string;
  slug: string;
  status: string;
  contactEmail?: string;
  customDomain?: string | null;
  isPublished?: boolean;
  productsCount?: number;
  usersCount?: number;
  createdAt: string;
};

type BoutiqueSettings = {
  logoUrl?: string | null;
  coverImage?: string | null;
  shopName?: string | null;
  slogan?: string | null;
  description?: string | null;
  contactEmail?: string | null;
  contactPhone?: string | null;
  address?: string | null;
  domain?: string | null;
  theme?: string | null;
  fontFamily?: string | null;
  primaryColor?: string | null;
  secondaryColor?: string | null;
  maintenanceMode?: boolean;
  orderMode?: string | null;
  enableEmailVerification?: boolean;
  enableCustomerEmailVerification?: boolean;
  createAccountAfterOrder?: boolean;
};

type ModuleAccess = {
  code: string;
  name: string;
  globallyEnabled: boolean;
  allowedBySubscription: boolean;
  enabledInBoutique: boolean;
  accessible: boolean;
};

type SubscriptionDetail = {
  isActive: boolean;
  planName: string | null;
  priceTnd: number;
  currency: string;
  startDate: string | null;
  endDate: string | null;
  daysRemaining: number | null;
  quotas: Array<{
    code: string;
    name: string;
    unit: string | null;
    limit: number | null;
    usage: number;
    remaining: number | null;
  }>;
  activeExtensions: Array<{
    id: string;
    extensionCode: string;
    extensionName: string;
    type: string;
    expiresAt: string | null;
  }>;
  pendingRequests: Array<{
    id: string;
    extensionCode: string;
    extensionName: string;
    status: string;
    requestedAt: string;
  }>;
  expiredExtensionsCount?: number;
};

type DetailData = {
  settings: BoutiqueSettings;
  access: { modules: Record<string, ModuleAccess> };
  subscription: SubscriptionDetail;
  dashboard: BoutiqueDashboard;
};

type BoutiqueDashboard = {
  kpis: {
    ordersTotal: number;
    ordersConfirmed: number;
    ordersPending: number;
    ordersCancelled: number;
    ordersShipped: number;
    ordersDelivered: number;
    productsTotal: number;
    productsActive: number;
    categoriesTotal: number;
    productViewsTotal: number;
  };
};

function display(value: string | number | null | undefined): string {
  return value === null || value === undefined || value === ""
    ? "—"
    : String(value);
}

function formatDate(value: string | null | undefined): string {
  return value ? new Date(value).toLocaleDateString("fr-FR") : "—";
}

function QuotaProgress({
  quota,
}: {
  quota: SubscriptionDetail["quotas"][number];
}) {
  const limit = quota.limit;
  const hasLimit = limit !== null;
  const percentage =
    limit !== null && limit > 0
      ? Math.min(100, Math.round((quota.usage / limit) * 100))
      : 0;
  const isFull = limit !== null && quota.usage >= limit;

  return (
    <div style={{ display: "grid", gap: 6 }}>
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          gap: 12,
          fontSize: 13,
        }}
      >
        <span>{quota.name}</span>
        <strong
          style={{
            color: isFull ? "var(--bo-error)" : "var(--bo-text-secondary)",
            whiteSpace: "nowrap",
          }}
        >
          {quota.usage} / {hasLimit ? limit : "illimité"}
          {quota.unit ? ` ${quota.unit}` : ""}
        </strong>
      </div>
      {hasLimit && (
        <div
          style={{
            height: 7,
            borderRadius: 99,
            background: "var(--bo-border)",
            overflow: "hidden",
          }}
        >
          <div
            style={{
              height: "100%",
              width: `${percentage}%`,
              borderRadius: 99,
              background: isFull ? "var(--bo-error)" : "var(--bo-primary)",
            }}
          />
        </div>
      )}
    </div>
  );
}

function SettingRow({
  label,
  value,
}: {
  label: string;
  value: React.ReactNode;
}) {
  return (
    <div
      style={{
        display: "flex",
        justifyContent: "space-between",
        gap: 16,
        padding: "8px 0",
        borderBottom: "1px solid var(--bo-border)",
      }}
    >
      <span style={{ color: "var(--bo-text-muted)", fontSize: 13 }}>
        {label}
      </span>
      <strong
        style={{ fontSize: 13, textAlign: "right", overflowWrap: "anywhere" }}
      >
        {value}
      </strong>
    </div>
  );
}

function Metric({
  label,
  value,
  tone = "neutral",
}: {
  label: string;
  value: number;
  tone?: "success" | "warning" | "error" | "info" | "neutral";
}) {
  return (
    <div style={{ padding: "12px 14px", border: "1px solid var(--bo-border)", borderRadius: 10 }}>
      <div style={{ fontSize: 22, fontWeight: 700, color: tone === "neutral" ? "var(--bo-text)" : `var(--bo-${tone})` }}>{value.toLocaleString("fr-FR")}</div>
      <div style={{ marginTop: 3, fontSize: 12, color: "var(--bo-text-muted)" }}>{label}</div>
    </div>
  );
}

export function BoutiqueDetailsPage({
  boutiqueId,
  getAccessToken,
  userRoles = [],
}: {
  boutiqueId: string;
  getAccessToken: () => string | null;
  userRoles?: string[];
}) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const fetchBoutique = useCallback(
    () => api.get<BoutiqueTarget>(`/boutiques/${encodeURIComponent(boutiqueId)}`),
    [api, boutiqueId],
  );
  const {
    data: boutique,
    isLoading: boutiqueLoading,
    error: boutiqueError,
    refresh: refreshBoutique,
  } = useApiData(fetchBoutique, [boutiqueId]);
  const fetchDetails = useCallback(async (): Promise<DetailData | null> => {
    if (!boutique) return null;

    const id = encodeURIComponent(boutique.id);
    const [settings, access, subscription, dashboard] = await Promise.all([
      api.get<BoutiqueSettings>(`/admin/boutiques/${id}/settings`),
      api.get<{ modules: Record<string, ModuleAccess> }>(
        `/admin/boutiques/${id}/dashboard/access`,
      ),
      api.get<SubscriptionDetail>(`/subscription/summary?boutiqueId=${id}`),
      api.get<BoutiqueDashboard>(`/admin/boutiques/${id}/dashboard`),
    ]);

    return { settings, access, subscription, dashboard };
  }, [api, boutique]);

  const { data, isLoading, error, refresh } = useApiData(fetchDetails, [
    boutique?.id,
  ]);

  const modules = Object.values(data?.access.modules ?? {});
  const status = boutique ? statusBadge(boutique.status) : null;
  const isSuperAdmin = userRoles.includes("ROLE_SUPER_ADMIN");

  const runAction = async (action: "approve" | "reject" | "suspend" | "activate" | "publish" | "unpublish") => {
    if (!boutique) return;
    const needsReason = ["reject", "suspend", "activate", "unpublish"].includes(action);
    const reason = needsReason ? window.prompt("Motif de cette action :")?.trim() : undefined;
    if (needsReason && !reason) return;
    try {
      await api.patch(`/boutiques/${encodeURIComponent(boutique.id)}/${action}`, reason ? { reason } : {});
      showNotice("Boutique mise à jour", "success");
      refreshBoutique();
      refresh();
    } catch (actionError) {
      showNotice(actionError instanceof Error ? actionError.message : "Impossible de mettre à jour la boutique.", "error");
    }
  };

  const actionButtons = boutique && isSuperAdmin ? (
    <div style={{ display: "flex", gap: 8, flexWrap: "wrap", justifyContent: "flex-end" }}>
       {boutique.status === "pending" && <Badge tone="neutral">En attente de publication</Badge>}
      {boutique.status === "active" ? (
        <Button variant="danger" size="sm" onClick={() => runAction("suspend")}>Désactiver</Button>
      ) : boutique.status !== "pending" && (
        <Button variant="primary" size="sm" onClick={() => runAction("activate")}>Activer</Button>
      )}
      {boutique.isPublished ? (
        <Button variant="ghost" size="sm" onClick={() => runAction("unpublish")}>Dépublier</Button>
      ) : boutique.status === "active" && (
        <Button variant="secondary" size="sm" onClick={() => runAction("publish")}>Publier</Button>
      )}
    </div>
  ) : undefined;

  return (
    <div className="bo-page">
      <PageHeader
        title={boutique ? `Détails de ${boutique.name}` : "Détails de la boutique"}
        description="Consultez la configuration, l'abonnement, les quotas, les modules et les extensions."
        actions={<div style={{ display: "flex", gap: 10, alignItems: "center", flexWrap: "wrap", justifyContent: "flex-end" }}>{actionButtons}<Button variant="secondary" onClick={() => window.location.assign("/admin/boutiques")}>Retour aux boutiques</Button></div>}
      />
      {boutiqueLoading ? <LoadingState message="Chargement de la boutique..." /> : boutiqueError ? (
        <ErrorState message={boutiqueError} onRetry={refreshBoutique} />
      ) : !boutique ? <EmptyState title="Boutique introuvable" /> : isLoading ? (
        <LoadingState message="Chargement des informations de la boutique..." />
      ) : error ? (
        <ErrorState message={error} onRetry={refresh} />
      ) : !data ? (
        <EmptyState />
      ) : (
        <div style={{ display: "grid", gap: 18 }}>
          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              gap: 12,
              flexWrap: "wrap",
              padding: 16,
              borderRadius: 12,
              background: "var(--bo-primary-light)",
              border: "1px solid var(--bo-border)",
            }}
          >
            <div>
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  gap: 8,
                  flexWrap: "wrap",
                }}
              >
                <h3 style={{ margin: 0 }}>{boutique.name}</h3>
                {status && <Badge tone={status.tone}>{status.label}</Badge>}
                <Badge tone={boutique.isPublished ? "success" : "neutral"}>
                  {boutique.isPublished ? "Publiée" : "Non publiée"}
                </Badge>
              </div>
              <div
                style={{
                  color: "var(--bo-text-secondary)",
                  fontSize: 13,
                  marginTop: 5,
                }}
              >
                {boutique.slug} · créée le {formatDate(boutique.createdAt)}
              </div>
            </div>
            <div style={{ display: "flex", gap: 8 }}>
              <Badge tone="info">{boutique.productsCount ?? 0} produits</Badge>
              <Badge tone="neutral">
                {boutique.usersCount ?? 0} utilisateurs
              </Badge>
            </div>
          </div>

          {(data.settings.logoUrl || data.settings.coverImage) && (
            <Card>
              <CardHeader>Identité visuelle</CardHeader>
              <CardBody>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: 16 }}>
                  {data.settings.logoUrl && (
                    <div>
                      <div style={{ color: 'var(--bo-text-muted)', fontSize: 12, marginBottom: 6 }}>Logo</div>
                      <img src={data.settings.logoUrl} alt={`Logo ${boutique.name}`} style={{ width: 140, height: 140, borderRadius: 12, objectFit: 'contain', border: '1px solid var(--bo-border)', background: 'var(--bo-surface)' }} />
                    </div>
                  )}
                  {data.settings.coverImage && (
                    <div>
                      <div style={{ color: 'var(--bo-text-muted)', fontSize: 12, marginBottom: 6 }}>Image de couverture</div>
                      <img src={data.settings.coverImage} alt={`Couverture ${boutique.name}`} style={{ width: '100%', maxWidth: 420, height: 140, borderRadius: 12, objectFit: 'cover', border: '1px solid var(--bo-border)' }} />
                    </div>
                  )}
                </div>
              </CardBody>
            </Card>
          )}

          <Card>
            <CardHeader>Vue opérationnelle</CardHeader>
            <CardBody>
              <div style={{ display: "grid", gap: 18 }}>
                <div>
                  <div style={{ fontSize: 12, fontWeight: 700, color: "var(--bo-text-muted)", textTransform: "uppercase", marginBottom: 10 }}>Commandes</div>
                  <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(130px, 1fr))", gap: 10 }}>
                    <Metric label="Total" value={data.dashboard.kpis.ordersTotal} />
                    <Metric label="Validées" value={data.dashboard.kpis.ordersConfirmed} tone="success" />
                    <Metric label="En attente" value={data.dashboard.kpis.ordersPending} tone="warning" />
                    <Metric label="Rejetées / annulées" value={data.dashboard.kpis.ordersCancelled} tone="error" />
                    <Metric label="Expédiées" value={data.dashboard.kpis.ordersShipped} />
                    <Metric label="Livrées" value={data.dashboard.kpis.ordersDelivered} tone="success" />
                  </div>
                </div>
                <div>
                  <div style={{ fontSize: 12, fontWeight: 700, color: "var(--bo-text-muted)", textTransform: "uppercase", marginBottom: 10 }}>Catalogue et audience</div>
                  <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(160px, 1fr))", gap: 10 }}>
                    <Metric label="Produits total" value={data.dashboard.kpis.productsTotal} />
                    <Metric label="Produits actifs" value={data.dashboard.kpis.productsActive} tone="success" />
                    <Metric label="Catégories" value={data.dashboard.kpis.categoriesTotal} />
                    <Metric label="Vues produits" value={data.dashboard.kpis.productViewsTotal} tone="info" />
                  </div>
                </div>
              </div>
            </CardBody>
          </Card>

          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(300px, 1fr))",
              gap: 18,
            }}
          >
            <Card>
              <CardHeader>Informations et settings</CardHeader>
              <CardBody>
                <div style={{ display: "grid" }}>
                  <SettingRow
                    label="Nom affiché"
                    value={display(data.settings.shopName ?? boutique.name)}
                  />
                  <SettingRow
                    label="Slogan"
                    value={display(data.settings.slogan)}
                  />
                  <SettingRow
                    label="Email"
                    value={display(
                      data.settings.contactEmail ?? boutique.contactEmail,
                    )}
                  />
                  <SettingRow
                    label="Téléphone"
                    value={display(data.settings.contactPhone)}
                  />
                  <SettingRow
                    label="Adresse"
                    value={display(data.settings.address)}
                  />
                  <SettingRow
                    label="Domaine"
                    value={display(
                      data.settings.domain ?? boutique.customDomain,
                    )}
                  />
                  <SettingRow
                    label="Thème"
                    value={display(data.settings.theme)}
                  />
                  <SettingRow
                    label="Police"
                    value={display(data.settings.fontFamily)}
                  />
                  <SettingRow
                    label="Mode commande"
                    value={display(data.settings.orderMode)}
                  />
                  <SettingRow
                    label="Maintenance"
                    value={
                      <Badge
                        tone={
                          data.settings.maintenanceMode ? "warning" : "success"
                        }
                      >
                        {data.settings.maintenanceMode
                          ? "Activée"
                          : "Désactivée"}
                      </Badge>
                    }
                  />
                </div>
                {data.settings.description && (
                  <p
                    style={{
                      margin: "14px 0 0",
                      color: "var(--bo-text-secondary)",
                      fontSize: 13,
                    }}
                  >
                    {data.settings.description}
                  </p>
                )}
              </CardBody>
            </Card>

            <Card>
              <CardHeader>Options client</CardHeader>
              <CardBody>
                <div style={{ display: "grid" }}>
                  <SettingRow
                    label="Vérification email admin"
                    value={
                      data.settings.enableEmailVerification
                        ? "Activée"
                        : "Désactivée"
                    }
                  />
                  <SettingRow
                    label="Vérification email client"
                    value={
                      data.settings.enableCustomerEmailVerification
                        ? "Activée"
                        : "Désactivée"
                    }
                  />
                  <SettingRow
                    label="Compte après commande"
                    value={
                      data.settings.createAccountAfterOrder
                        ? "Activé"
                        : "Désactivé"
                    }
                  />
                  <SettingRow
                    label="Couleur principale"
                    value={display(data.settings.primaryColor)}
                  />
                  <SettingRow
                    label="Couleur secondaire"
                    value={display(data.settings.secondaryColor)}
                  />
                </div>
              </CardBody>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <div
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  gap: 12,
                  alignItems: "center",
                  flexWrap: "wrap",
                }}
              >
                <span>Abonnement et consommation</span>
                <Badge tone={data.subscription.isActive ? "success" : "error"}>
                  {data.subscription.isActive
                    ? "Abonnement actif"
                    : "Abonnement inactif"}
                </Badge>
              </div>
            </CardHeader>
            <CardBody>
              <div
                style={{
                  display: "grid",
                  gridTemplateColumns:
                    "minmax(220px, .8fr) minmax(280px, 1.2fr)",
                  gap: 24,
                }}
              >
                <div>
                  <div style={{ fontSize: 22, fontWeight: 700 }}>
                    {display(data.subscription.planName ?? "Aucun plan")}
                  </div>
                  <p
                    style={{
                      margin: "6px 0 16px",
                      color: "var(--bo-text-secondary)",
                      fontSize: 13,
                    }}
                  >
                    {data.subscription.priceTnd} {data.subscription.currency} ·
                    du {formatDate(data.subscription.startDate)} au{" "}
                    {formatDate(data.subscription.endDate)}
                  </p>
                  {data.subscription.daysRemaining !== null && (
                    <Badge
                      tone={
                        data.subscription.daysRemaining <= 7
                          ? "warning"
                          : "info"
                      }
                    >
                      {data.subscription.daysRemaining} jour(s) restant(s)
                    </Badge>
                  )}
                </div>
                <div style={{ display: "grid", gap: 13 }}>
                  <div
                    style={{
                      fontSize: 13,
                      fontWeight: 700,
                      color: "var(--bo-text-muted)",
                      textTransform: "uppercase",
                    }}
                  >
                    Quotas
                  </div>
                  {data.subscription.quotas.length === 0 ? (
                    <EmptyState
                      title="Aucun quota"
                      message="Aucune limite configurée pour ce plan."
                    />
                  ) : (
                    data.subscription.quotas.map((quota) => (
                      <QuotaProgress key={quota.code} quota={quota} />
                    ))
                  )}
                </div>
              </div>
            </CardBody>
          </Card>

          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(300px, 1fr))",
              gap: 18,
            }}
          >
            <Card>
              <CardHeader>
                Modules et état d'accès ({modules.length})
              </CardHeader>
              <CardBody>
                {modules.length === 0 ? (
                  <EmptyState />
                ) : (
                  <div style={{ display: "grid", gap: 8 }}>
                    {modules.map((module) => (
                      <div
                        key={module.code}
                        style={{
                          display: "flex",
                          justifyContent: "space-between",
                          alignItems: "center",
                          gap: 10,
                          padding: "9px 10px",
                          border: "1px solid var(--bo-border)",
                          borderRadius: 8,
                        }}
                      >
                        <div style={{ minWidth: 0 }}>
                          <strong style={{ fontSize: 13 }}>
                            {module.name}
                          </strong>
                          <div
                            style={{
                              color: "var(--bo-text-muted)",
                              fontSize: 11,
                            }}
                          >
                            {module.code}
                          </div>
                        </div>
                        <div
                          style={{
                            display: "flex",
                            gap: 4,
                            flexWrap: "wrap",
                            justifyContent: "flex-end",
                          }}
                        >
                          <Badge
                            tone={module.globallyEnabled ? "success" : "error"}
                          >
                            {module.globallyEnabled ? "Global" : "Global off"}
                          </Badge>
                          <Badge
                            tone={
                              module.allowedBySubscription
                                ? "success"
                                : "warning"
                            }
                          >
                            {module.allowedBySubscription
                              ? "Plan"
                              : "Hors plan"}
                          </Badge>
                          <Badge
                            tone={module.accessible ? "success" : "neutral"}
                          >
                            {module.accessible ? "Accessible" : "Bloqué"}
                          </Badge>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </CardBody>
            </Card>

            <Card>
              <CardHeader>Extensions</CardHeader>
              <CardBody>
                <div style={{ display: "grid", gap: 14 }}>
                  <div>
                    <div
                      style={{
                        fontSize: 12,
                        fontWeight: 700,
                        color: "var(--bo-text-muted)",
                        textTransform: "uppercase",
                        marginBottom: 7,
                      }}
                    >
                      Actives
                    </div>
                    {data.subscription.activeExtensions.length === 0 ? (
                      <span
                        style={{ color: "var(--bo-text-muted)", fontSize: 13 }}
                      >
                        Aucune extension active
                      </span>
                    ) : (
                      <div style={{ display: "grid", gap: 7 }}>
                        {data.subscription.activeExtensions.map((extension) => (
                          <div
                            key={extension.id}
                            style={{
                              display: "flex",
                              justifyContent: "space-between",
                              gap: 10,
                              fontSize: 13,
                            }}
                          >
                            <span>{extension.extensionName}</span>
                            <Badge tone="success">
                              {extension.expiresAt
                                ? `Jusqu'au ${formatDate(extension.expiresAt)}`
                                : "Permanent"}
                            </Badge>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                  <div>
                    <div
                      style={{
                        fontSize: 12,
                        fontWeight: 700,
                        color: "var(--bo-text-muted)",
                        textTransform: "uppercase",
                        marginBottom: 7,
                      }}
                    >
                      Demandes en cours
                    </div>
                    {data.subscription.pendingRequests.length === 0 ? (
                      <span
                        style={{ color: "var(--bo-text-muted)", fontSize: 13 }}
                      >
                        Aucune demande
                      </span>
                    ) : (
                      <div style={{ display: "grid", gap: 7 }}>
                        {data.subscription.pendingRequests.map((request) => {
                          const requestStatus = statusBadge(request.status);
                          return (
                            <div
                              key={request.id}
                              style={{
                                display: "flex",
                                justifyContent: "space-between",
                                gap: 10,
                                fontSize: 13,
                              }}
                            >
                              <span>{request.extensionName}</span>
                              <Badge tone={requestStatus.tone}>
                                {requestStatus.label}
                              </Badge>
                            </div>
                          );
                        })}
                      </div>
                    )}
                  </div>
                  {(data.subscription.expiredExtensionsCount ?? 0) > 0 && (
                    <Badge tone="warning">
                      {data.subscription.expiredExtensionsCount} extension(s)
                      expirée(s)
                    </Badge>
                  )}
                </div>
              </CardBody>
            </Card>
          </div>
        </div>
      )}
    </div>
  );
}
