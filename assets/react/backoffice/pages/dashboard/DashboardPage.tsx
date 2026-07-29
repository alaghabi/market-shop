import { useCallback } from 'react';
import { motion } from 'framer-motion';
import { Badge } from '../../components/Badge';
import { Button } from '../../components/Button';
import { Card, CardBody, CardHeader } from '../../components/Card';
import { ErrorState, LoadingState } from '../../components/States';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { useBoutique } from '../../hooks/useBoutique';
import { useNotification } from '../../hooks/useNotification';
import { PageHeader } from '../../layout/Shell';

const gridStagger = {
  hidden: {},
  show: { transition: { staggerChildren: 0.05 } },
};

const gridItem = {
  hidden: { opacity: 0, y: 12 },
  show: { opacity: 1, y: 0 },
};

function WidgetIcon({ tone }: { tone?: 'success' | 'warning' | 'error' | 'neutral' }) {
  return (
    <div className={`bo-widget-icon ${tone && tone !== 'neutral' ? `tone-${tone}` : ''}`}>
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
      </svg>
    </div>
  );
}

type BoutiqueDashboardData = {
  boutiqueId: string;
  kpis: {
    salesTodayCents: number;
    salesWeekCents: number;
    salesMonthCents: number;
    salesYearCents: number;
    ordersToday: number;
    ordersPending: number;
    ordersConfirmed: number;
    ordersShipped: number;
    ordersDelivered: number;
    ordersCancelled: number;
    customersTotal: number;
    customersNew: number;
    productsActive: number;
    productsOutOfStock: number;
    productsLowStock: number;
    averageRating: number | null;
    reviewsCount: number;
  };
  topProducts: Array<{ id: string; name: string; salesCount: number; revenueCents: number }>;
  viewStats: ViewStats;
};

type DashboardModules = { modules: Record<string, boolean> };
type PlatformDashboardData = {
  kpis: {
    totalBoutiques: number;
    activeBoutiques: number;
    pendingBoutiques: number;
    newBoutiques: number;
    totalCustomers: number;
    totalProducts: number;
    totalOrders: number;
    ordersToday: number;
    platformRevenueCents: number;
    monthlyGrowthPercent: number | null;
    totalBoutiqueAdmins: number;
    totalEmployees: number;
  };
  subscriptions: { active: number; expired: number; expiringSoon: number };
  topBoutiques: Array<{ id: string; name: string; revenueCents: number; orders: number }>;
  viewStats: ViewStats;
};
type ViewStats = {
  days: Array<{ date: string; views: number }>;
  products: Array<{ date: string; productId: string; productName: string; views: number }>;
  boutiques: Array<{ date: string; boutiqueId: string; boutiqueName: string; views: number }>;
};
type BoutiqueAccess = {
  modules: Record<string, { accessible: boolean; globallyEnabled: boolean; allowedBySubscription: boolean; enabledInBoutique: boolean }>;
  permissions: string[];
  roles: string[];
};
type PublicationRequest = { id: string; status: string; requestedAt: string; reason?: string | null };

type FeatureKey =
  | 'orders' | 'products' | 'inventory' | 'customers' | 'cart' | 'promotions' | 'coupons'
  | 'announcements' | 'reviews' | 'cms' | 'blog' | 'seo' | 'payments' | 'delivery'
  | 'loyalty' | 'wallet' | 'employees' | 'analytics';

type FeatureDefinition = {
  key: FeatureKey;
  title: string;
  moduleAliases: string[];
  subscriptionAliases?: string[];
  permissions: string[];
  rows: Array<{ label: string; value: (data: BoutiqueDashboardData) => string | number; tone?: 'success' | 'warning' | 'error' | 'neutral' }>;
  actions?: string[];
};

const FEATURES: FeatureDefinition[] = [
  {
    key: 'orders',
    title: 'Commandes',
    moduleAliases: ['orders', 'commandes'],
    permissions: ['order.read', 'view_orders'],
    rows: [
      { label: 'Nouvelles commandes', value: (d) => d.kpis.ordersToday },
      { label: 'En attente', value: (d) => d.kpis.ordersPending, tone: 'warning' },
      { label: 'Préparées', value: (d) => d.kpis.ordersConfirmed },
      { label: 'Expédiées', value: (d) => d.kpis.ordersShipped },
      { label: 'Annulées', value: (d) => d.kpis.ordersCancelled, tone: 'error' },
      { label: 'Remboursées', value: () => '—' },
    ],
    actions: ['Voir', 'Modifier statut', 'Imprimer facture', 'Bon livraison'],
  },
  {
    key: 'products',
    title: 'Produits',
    moduleAliases: ['products', 'produits'],
    permissions: ['product.read', 'view_products'],
    rows: [
      { label: 'Produits actifs', value: (d) => d.kpis.productsActive, tone: 'success' },
      { label: 'Produits inactifs', value: () => '—' },
      { label: 'Produits épuisés', value: (d) => d.kpis.productsOutOfStock, tone: 'error' },
      { label: 'Produits les plus vendus', value: (d) => d.topProducts.length },
      { label: 'Produits les plus consultés', value: () => '—' },
    ],
    actions: ['Créer produit', 'Importer produits', 'Exporter produits'],
  },
  {
    key: 'inventory',
    title: 'Stock',
    moduleAliases: ['inventory'],
    permissions: ['product.inventory.manage', 'view_inventory'],
    rows: [
      { label: 'Stock faible', value: (d) => d.kpis.productsLowStock, tone: 'warning' },
      { label: 'Rupture stock', value: (d) => d.kpis.productsOutOfStock, tone: 'error' },
      { label: 'Mouvements stock', value: () => '—' },
    ],
  },
  {
    key: 'customers',
    title: 'Clients',
    moduleAliases: ['customers', 'clients'],
    permissions: ['customer.read'],
    rows: [
      { label: 'Nouveaux clients', value: (d) => d.kpis.customersNew },
      { label: 'Clients actifs', value: (d) => d.kpis.customersTotal, tone: 'success' },
      { label: 'Clients fidèles', value: () => '—' },
      { label: 'Clients inactifs', value: () => '—' },
    ],
    actions: ['Profil', 'Historique commandes'],
  },
  {
    key: 'cart',
    title: 'Paniers abandonnés',
    moduleAliases: ['cart', 'abandoned_cart'],
    subscriptionAliases: ['abandoned_cart'],
    permissions: ['marketing.abandoned_cart.manage'],
    rows: [
      { label: 'Paniers abandonnés', value: () => '—', tone: 'warning' },
      { label: 'Valeur estimée perdue', value: () => '—' },
    ],
    actions: ['Relance email', 'Relance notification'],
  },
  {
    key: 'promotions',
    title: 'Promotions',
    moduleAliases: ['promotions'],
    subscriptionAliases: ['promotions'],
    permissions: ['marketing.promotion.manage', 'promotions'],
    rows: [
      { label: 'Actives', value: () => '—', tone: 'success' },
      { label: 'Programmées', value: () => '—' },
      { label: 'Expirées', value: () => '—', tone: 'warning' },
    ],
  },
  {
    key: 'coupons',
    title: 'Coupons',
    moduleAliases: ['coupons'],
    subscriptionAliases: ['coupons'],
    permissions: ['marketing.coupon.manage', 'coupons'],
    rows: [
      { label: 'Actifs', value: () => '—', tone: 'success' },
      { label: 'Utilisés', value: () => '—' },
      { label: 'Expirés', value: () => '—', tone: 'warning' },
    ],
  },
  {
    key: 'announcements',
    title: 'Annonces',
    moduleAliases: ['announcements'],
    permissions: ['cms.banner.manage', 'annonces', 'announcements'],
    rows: [
      { label: 'Actives', value: () => '—', tone: 'success' },
      { label: 'Programmées', value: () => '—' },
      { label: 'Clics', value: () => '—' },
      { label: 'Vues', value: () => '—' },
    ],
  },
  {
    key: 'reviews',
    title: 'Avis',
    moduleAliases: ['reviews', 'avis'],
    subscriptionAliases: ['reviews'],
    permissions: ['customer.reviews.manage', 'reviews'],
    rows: [
      { label: 'Avis produits', value: (d) => d.kpis.reviewsCount },
      { label: 'Avis boutique', value: () => '—' },
      { label: 'Note moyenne', value: (d) => d.kpis.averageRating?.toFixed(1) ?? '—' },
    ],
    actions: ['Approuver', 'Masquer', 'Répondre'],
  },
  {
    key: 'cms',
    title: 'CMS',
    moduleAliases: ['cms'],
    permissions: ['cms.page.read', 'cms', 'cms_access'],
    rows: [
      { label: 'Pages publiées', value: () => '—' },
      { label: 'Brouillons', value: () => '—' },
      { label: 'Planifiées', value: () => '—' },
    ],
  },
  {
    key: 'blog',
    title: 'Blog',
    moduleAliases: ['blog'],
    subscriptionAliases: ['blog'],
    permissions: ['cms.blog.manage', 'blog'],
    rows: [
      { label: 'Articles publiés', value: () => '—' },
      { label: 'Brouillons', value: () => '—' },
      { label: 'Vues', value: () => '—' },
    ],
  },
  {
    key: 'seo',
    title: 'SEO',
    moduleAliases: ['seo', 'seo_advanced'],
    subscriptionAliases: ['seo_advanced'],
    permissions: ['marketing.seo.manage'],
    rows: [
      { label: 'Sans meta title', value: () => '—', tone: 'warning' },
      { label: 'Sans meta description', value: () => '—', tone: 'warning' },
      { label: 'Erreurs SEO', value: () => '—', tone: 'error' },
    ],
  },
  {
    key: 'payments',
    title: 'Paiements',
    moduleAliases: ['payments', 'paiements'],
    permissions: ['invoice.payment.receive'],
    rows: [
      { label: 'Validés', value: () => '—', tone: 'success' },
      { label: 'Refusés', value: () => '—', tone: 'error' },
      { label: 'En attente', value: () => '—', tone: 'warning' },
      { label: 'Par moyen de paiement', value: () => '—' },
    ],
  },
  {
    key: 'delivery',
    title: 'Livraison',
    moduleAliases: ['delivery', 'livraison', 'delivery_tracking'],
    subscriptionAliases: ['delivery_tracking'],
    permissions: ['shop.shipping.manage', 'order.delivery.manage'],
    rows: [
      { label: 'En attente', value: () => '—', tone: 'warning' },
      { label: 'Expédiées', value: (d) => d.kpis.ordersShipped },
      { label: 'Réussies', value: (d) => d.kpis.ordersDelivered, tone: 'success' },
      { label: 'En retard', value: () => '—', tone: 'error' },
      { label: 'Par transporteur', value: () => '—' },
    ],
  },
  {
    key: 'loyalty',
    title: 'Fidélité',
    moduleAliases: ['loyalty', 'fidelite'],
    subscriptionAliases: ['loyalty'],
    permissions: ['marketing.loyalty.manage'],
    rows: [
      { label: 'Points distribués', value: () => '—' },
      { label: 'Points utilisés', value: () => '—' },
      { label: 'Meilleurs clients', value: () => '—' },
    ],
  },
  {
    key: 'wallet',
    title: 'Wallet',
    moduleAliases: ['wallet'],
    permissions: ['invoice.payment.receive'],
    rows: [
      { label: 'Crédits utilisés', value: () => '—' },
      { label: 'Remboursements wallet', value: () => '—' },
    ],
  },
  {
    key: 'employees',
    title: 'Employés',
    moduleAliases: ['employees', 'employes', 'employés'],
    permissions: ['employee.read'],
    rows: [
      { label: 'Nombre employés', value: () => '—' },
      { label: 'Dernières connexions', value: () => '—' },
      { label: 'Rôles', value: () => '—' },
    ],
  },
];

export function DashboardPage({ getAccessToken, userRoles = [] }: { getAccessToken: () => string | null; userRoles?: string[] }) {
  const api = useApiClient(getAccessToken);
  const { boutique } = useBoutique();
  const { showNotice } = useNotification();
  const canRequestPublication = userRoles.includes('ROLE_SUPER_ADMIN') || userRoles.includes('ROLE_BOUTIQUE_ADMIN');

  const fetchDashboard = useCallback(async () => {
    if (!boutique) {
      const [platform, globalModules] = await Promise.all([
        api.get<PlatformDashboardData>('/admin/dashboard/platform'),
        api.get<DashboardModules>('/admin/dashboard/modules'),
      ]);

      return { mode: 'platform' as const, platform, globalModules: globalModules.modules };
    }

    const [dashboard, globalModules, access] = await Promise.all([
      api.get<BoutiqueDashboardData>(`/admin/boutiques/${boutique.id}/dashboard`),
      api.get<DashboardModules>('/admin/dashboard/modules'),
      api.get<BoutiqueAccess>(`/admin/boutiques/${boutique.id}/dashboard/access`),
    ]);

      const publicationRequests = canRequestPublication
        ? await api.getCollection<PublicationRequest>('/boutique/publication-requests')
        : { member: [] };

      return {
        mode: 'boutique' as const,
        dashboard,
        globalModules: globalModules.modules,
        access,
        publicationRequest: publicationRequests.member.find((request) => request.status === 'pending') ?? null,
      };
  }, [api, boutique?.id, canRequestPublication]);

  const { data, isLoading, error, refresh } = useApiData(fetchDashboard, [boutique?.id]);

  if (error) return <ErrorState message={error} onRetry={refresh} />;
  if (isLoading || !data) return <LoadingState />;

  if (data.mode === 'platform') {
    return <PlatformDashboard data={data.platform} modules={data.globalModules} onRefresh={refresh} />;
  }
  if (!boutique) return <ErrorState message="Sélection boutique invalide." onRetry={refresh} />;

  const { dashboard, globalModules, access } = data;
  const publicationRequest = data.publicationRequest;
  const permissions = new Set(access.permissions);
  const hasAdminRole = access.roles.includes('ROLE_SUPER_ADMIN') || access.roles.includes('ROLE_BOUTIQUE_ADMIN');

  const hasPermission = (required: string[]) =>
    hasAdminRole || required.length === 0 || required.some((permission) => permissions.has(permission));

  const isGlobalModuleOn = (aliases: string[]) => aliases.some((alias) => globalModules[alias] === true);
  const isSubscriptionModuleOn = (aliases: string[] = []) =>
    aliases.length === 0 || aliases.some((alias) => access.modules[alias]?.accessible === true);

  const canShow = (feature: FeatureDefinition) =>
    isGlobalModuleOn(feature.moduleAliases) &&
    isSubscriptionModuleOn(feature.subscriptionAliases) &&
    hasPermission(feature.permissions);

  const visibleFeatures = FEATURES.filter(canShow);
  const kpis = dashboard.kpis;
  const averageBasket = kpis.ordersToday > 0 ? kpis.salesTodayCents / kpis.ordersToday : null;

  const featureIsVisible = (key: FeatureKey) => visibleFeatures.some((feature) => feature.key === key);
  const requestPublication = async () => {
    try {
      await api.post('/boutique/publication-requests', {});
      showNotice('Votre demande sera traitée sous 24h.', 'success');
      refresh();
    } catch (requestError) {
      showNotice(requestError instanceof Error ? requestError.message : 'Impossible d’envoyer la demande.', 'error');
    }
  };
  const mainCards = [
    { label: 'CA jour', value: money(kpis.salesTodayCents), visible: hasAdminRole && featureIsVisible('orders') },
    { label: 'CA mois', value: money(kpis.salesMonthCents), visible: hasAdminRole && featureIsVisible('orders') },
    { label: 'Commandes jour', value: kpis.ordersToday, visible: featureIsVisible('orders') },
    { label: 'Nouveaux clients', value: kpis.customersNew, visible: featureIsVisible('customers') },
    { label: 'Produits actifs', value: kpis.productsActive, visible: featureIsVisible('products') },
    { label: 'Produits en rupture', value: kpis.productsOutOfStock, visible: featureIsVisible('products'), tone: 'error' },
    { label: 'Panier moyen', value: averageBasket === null ? '—' : money(averageBasket), visible: hasAdminRole && featureIsVisible('orders') },
    { label: 'Taux conversion', value: '—', visible: hasAdminRole && featureIsVisible('analytics') },
  ];

  const priorities = [
    { label: 'Commandes à traiter', value: kpis.ordersPending, visible: featureIsVisible('orders'), tone: 'warning' as const },
    { label: 'Produits en rupture', value: kpis.productsOutOfStock, visible: featureIsVisible('products'), tone: 'error' as const },
    { label: 'Avis à modérer', value: '—', visible: featureIsVisible('reviews'), tone: 'warning' as const },
    { label: 'Promotions expirées', value: '—', visible: featureIsVisible('promotions'), tone: 'neutral' as const },
    { label: 'Abonnement proche expiration', value: '—', visible: true, tone: 'warning' as const },
    { label: 'Erreurs de paiement', value: '—', visible: featureIsVisible('payments'), tone: 'error' as const },
  ].filter((item) => item.visible);

  return (
    <div className="dashboard-page">
      <PageHeader
        title="Tableau de bord boutique"
        description={`Pilotage de ${boutique.name} avec modules, abonnement et permissions appliqués.`}
        actions={<Button variant="secondary" onClick={refresh}>Actualiser</Button>}
      />

      {canRequestPublication && !boutique.isPublished && (
        <Card style={{ marginTop: 18, borderColor: 'var(--bo-primary)' }}>
          <CardBody>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16, flexWrap: 'wrap' }}>
              <div>
                <strong>Boutique non publiée</strong>
                <div style={{ color: 'var(--bo-text-muted)', fontSize: 13, marginTop: 4 }}>
                  Demandez au Super Admin de publier votre boutique. Les demandes sont traitées sous 24h.
                </div>
              </div>
              {publicationRequest ? (
                <Badge tone="warning">Demande en cours de traitement</Badge>
              ) : (
                <Button variant="primary" onClick={() => void requestPublication()}>Demander la publication</Button>
              )}
            </div>
          </CardBody>
        </Card>
      )}

      <motion.section
        className="bo-widget-grid"
        variants={gridStagger}
        initial="hidden"
        animate="show"
      >
        {mainCards.filter((card) => card.visible).map((card) => (
          <motion.div key={card.label} variants={gridItem}>
            <Card>
              <CardBody>
                <div className="bo-widget">
                  <div className="bo-widget-header">
                    <WidgetIcon tone={card.tone as 'error' | 'neutral' | undefined} />
                  </div>
                  <div className="bo-widget-info">
                    <span className="bo-widget-label">{card.label}</span>
                    <strong className="bo-widget-value" style={{ color: card.tone === 'error' ? 'var(--bo-error)' : undefined }}>{card.value}</strong>
                  </div>
                </div>
              </CardBody>
            </Card>
          </motion.div>
        ))}
      </motion.section>

      <section style={{ marginTop: 24 }}>
        <Card>
          <CardHeader>Actions prioritaires</CardHeader>
          <CardBody>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 12 }}>
              {priorities.map((item) => (
                <div key={item.label} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: 12, border: '1px solid var(--bo-border)', borderRadius: 10 }}>
                  <span style={{ fontSize: 13, color: 'var(--bo-text-muted)' }}>{item.label}</span>
                  <Badge tone={item.tone}>{item.value}</Badge>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>
      </section>

      <section style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(290px, 1fr))', gap: 16, marginTop: 24 }}>
        {visibleFeatures.map((feature) => (
          <Card key={feature.key}>
            <CardHeader>{feature.title}</CardHeader>
            <CardBody>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                {feature.rows.map((row) => (
                  <StatRow key={row.label} label={row.label} value={row.value(dashboard)} tone={row.tone} />
                ))}
              </div>
              {feature.actions && (
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 14 }}>
                  {feature.actions.map((action) => <Button key={action} variant="ghost" size="sm">{action}</Button>)}
                </div>
              )}
            </CardBody>
          </Card>
        ))}
      </section>

      <section style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 16, marginTop: 24 }}>
        <Card>
          <CardHeader>Abonnement</CardHeader>
          <CardBody>
            <StatRow label="Abonnement actuel" value="—" />
            <StatRow label="Date expiration" value="—" tone="warning" />
            <StatRow label="Modules inclus" value={Object.values(access.modules).filter((m) => m.accessible).length} />
            <StatRow label="Modules désactivés" value={Object.values(access.modules).filter((m) => !m.accessible).length} tone="error" />
          </CardBody>
        </Card>

        {hasAdminRole && featureIsVisible('analytics') && (
          <Card>
            <CardHeader>Analytics</CardHeader>
            <CardBody>
              <StatRow label="Évolution ventes" value={money(kpis.salesMonthCents)} />
              <StatRow label="Évolution commandes" value="—" />
              <StatRow label="Évolution clients" value={kpis.customersNew} />
              <StatRow label="Évolution panier moyen" value={averageBasket === null ? '—' : money(averageBasket)} />
              <StatRow label="Top produits" value={dashboard.topProducts.length} />
            </CardBody>
          </Card>
        )}

        <Card>
          <CardHeader>Exports</CardHeader>
          <CardBody>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
              {['Commandes CSV', 'Produits XLSX', 'Clients CSV', 'Statistiques PDF'].map((label) => <Button key={label} variant="secondary" size="sm">{label}</Button>)}
            </div>
          </CardBody>
        </Card>
      </section>

      {dashboard.topProducts.length > 0 && featureIsVisible('products') && (
        <section style={{ marginTop: 24 }}>
          <Card>
            <CardHeader>Top produits</CardHeader>
            <CardBody>
              {dashboard.topProducts.map((product, index) => (
                <div key={product.id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid var(--bo-border-light)' }}>
                  <span style={{ color: 'var(--bo-text-muted)', width: 28 }}>#{index + 1}</span>
                  <strong style={{ flex: 1 }}>{product.name}</strong>
                  <span style={{ color: 'var(--bo-text-muted)', fontSize: 13 }}>{product.salesCount} ventes</span>
                  <strong style={{ marginLeft: 16 }}>{money(product.revenueCents)}</strong>
                </div>
              ))}
            </CardBody>
          </Card>
        </section>
      )}

      <ViewStatsSection data={dashboard.viewStats} />
    </div>
  );
}

function PlatformDashboard({ data, modules, onRefresh }: { data: PlatformDashboardData; modules: Record<string, boolean>; onRefresh: () => void }) {
  const kpis = data.kpis;
  const enabledModules = Object.entries(modules).filter(([, enabled]) => enabled);
  const disabledModules = Object.entries(modules).filter(([, enabled]) => !enabled);
  const growthTone: 'success' | 'error' = kpis.monthlyGrowthPercent === null || kpis.monthlyGrowthPercent >= 0 ? 'success' : 'error';

  const cards = [
    { label: 'Boutiques', value: kpis.totalBoutiques, hint: `${kpis.activeBoutiques} actives`, tone: 'success' as const },
    { label: 'En attente', value: kpis.pendingBoutiques, hint: `${kpis.newBoutiques} nouvelles ce mois`, tone: 'warning' as const },
    { label: 'Revenu plateforme', value: money(kpis.platformRevenueCents), hint: kpis.monthlyGrowthPercent === null ? 'Croissance non disponible' : `${kpis.monthlyGrowthPercent >= 0 ? '+' : ''}${kpis.monthlyGrowthPercent}%`, tone: growthTone },
    { label: 'Commandes', value: kpis.totalOrders, hint: `${kpis.ordersToday} aujourd'hui`, tone: 'neutral' as const },
    { label: 'Clients', value: kpis.totalCustomers, hint: 'Tous les clients', tone: 'neutral' as const },
    { label: 'Produits actifs', value: kpis.totalProducts, hint: 'Toutes boutiques', tone: 'neutral' as const },
    { label: 'Abonnements actifs', value: data.subscriptions.active, hint: `${data.subscriptions.expiringSoon} expirent bientôt`, tone: 'warning' as const },
    { label: 'Admins boutique', value: kpis.totalBoutiqueAdmins, hint: `${kpis.totalEmployees} employés`, tone: 'neutral' as const },
  ];

  return (
    <div className="dashboard-page">
      <PageHeader
        title="Tableau de bord général"
        description="Vue Super Admin de toute l'application: boutiques, ventes, modules, abonnements et activité globale."
        actions={<Button variant="secondary" onClick={onRefresh}>Actualiser</Button>}
      />

      <motion.section
        className="bo-widget-grid"
        variants={gridStagger}
        initial="hidden"
        animate="show"
      >
        {cards.map((card) => (
          <motion.div key={card.label} variants={gridItem}>
            <Card>
              <CardBody>
                <div className="bo-widget">
                  <div className="bo-widget-header">
                    <WidgetIcon tone={card.tone} />
                  </div>
                  <div className="bo-widget-info">
                    <span className="bo-widget-label">{card.label}</span>
                    <strong className="bo-widget-value" style={{ color: card.tone === 'error' ? 'var(--bo-error)' : card.tone === 'warning' ? 'var(--bo-warning)' : card.tone === 'success' ? 'var(--bo-success)' : undefined }}>{card.value}</strong>
                    <span style={{ color: 'var(--bo-text-muted)', fontSize: 12 }}>{card.hint}</span>
                  </div>
                </div>
              </CardBody>
            </Card>
          </motion.div>
        ))}
      </motion.section>

      <section style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 16, marginTop: 24 }}>
        <Card>
          <CardHeader>Boutiques</CardHeader>
          <CardBody>
            <StatRow label="Total" value={kpis.totalBoutiques} />
            <StatRow label="Actives" value={kpis.activeBoutiques} tone="success" />
            <StatRow label="En attente" value={kpis.pendingBoutiques} tone="warning" />
            <StatRow label="Nouvelles ce mois" value={kpis.newBoutiques} />
          </CardBody>
        </Card>

        <Card>
          <CardHeader>Abonnements</CardHeader>
          <CardBody>
            <StatRow label="Actifs" value={data.subscriptions.active} tone="success" />
            <StatRow label="Expirent bientôt" value={data.subscriptions.expiringSoon} tone="warning" />
            <StatRow label="Expirés" value={data.subscriptions.expired} tone="error" />
          </CardBody>
        </Card>

        <Card>
          <CardHeader>Modules application</CardHeader>
          <CardBody>
            <StatRow label="Modules activés" value={enabledModules.length} tone="success" />
            <StatRow label="Modules désactivés" value={disabledModules.length} tone={disabledModules.length > 0 ? 'warning' : 'neutral'} />
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 12 }}>
              {enabledModules.slice(0, 12).map(([code]) => <Badge key={code} tone="success">{code}</Badge>)}
              {enabledModules.length > 12 && <Badge tone="neutral">+{enabledModules.length - 12}</Badge>}
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>Utilisateurs internes</CardHeader>
          <CardBody>
            <StatRow label="Admins boutique" value={kpis.totalBoutiqueAdmins} />
            <StatRow label="Employés" value={kpis.totalEmployees} />
          </CardBody>
        </Card>
      </section>

      {data.topBoutiques.length > 0 && (
        <section style={{ marginTop: 24 }}>
          <Card>
            <CardHeader>Top boutiques</CardHeader>
            <CardBody>
              {data.topBoutiques.map((boutique, index) => (
                <div key={boutique.id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16, padding: '10px 0', borderBottom: '1px solid var(--bo-border-light)' }}>
                  <span style={{ color: 'var(--bo-text-muted)', width: 28 }}>#{index + 1}</span>
                  <strong style={{ flex: 1 }}>{boutique.name}</strong>
                  <span style={{ color: 'var(--bo-text-muted)', fontSize: 13 }}>{boutique.orders} commandes</span>
                  <strong>{money(boutique.revenueCents)}</strong>
                </div>
              ))}
            </CardBody>
          </Card>
        </section>
      )}

      <ViewStatsSection data={data.viewStats} />
    </div>
  );
}

function ViewStatsSection({ data }: { data: ViewStats }) {
  return (
    <section style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(290px, 1fr))', gap: 16, marginTop: 24 }}>
      <Card>
        <CardHeader>Vues produits · 7 derniers jours</CardHeader>
        <CardBody>
          <ViewsBarChart data={data.days} label="Nombre de vues produits par jour" />
        </CardBody>
      </Card>
      <Card>
        <CardHeader>Vues par boutique · 7 derniers jours</CardHeader>
        <CardBody>
          <ViewsByBoutiqueChart data={data.boutiques} />
        </CardBody>
      </Card>
    </section>
  );
}

function ViewsBarChart({ data, label }: { data: Array<{ date: string; views: number }>; label: string }) {
  const days = lastSevenDays();
  const values = days.map((date) => data.find((item) => item.date === date)?.views ?? 0);
  const max = Math.max(1, ...values);
  const chartHeight = 170;
  const chartWidth = 700;
  const barWidth = 54;
  const gap = (chartWidth - days.length * barWidth) / (days.length + 1);

  return (
    <div>
      <svg viewBox="0 0 700 220" role="img" aria-label={label} style={{ display: 'block', width: '100%', height: 'auto' }}>
        <line x1="0" y1={chartHeight} x2={chartWidth} y2={chartHeight} stroke="var(--bo-border)" />
        {values.map((value, index) => {
          const height = value > 0 ? Math.max(4, (value / max) * chartHeight) : 0;
          const x = gap + index * (barWidth + gap);
          const y = chartHeight - height;

          return (
            <g key={days[index]}>
              <rect x={x} y={y} width={barWidth} height={height} rx="6" fill="var(--bo-primary)" opacity="0.9" />
              <text x={x + barWidth / 2} y={y - 8} textAnchor="middle" fontSize="12" fill="var(--bo-text)">{value}</text>
              <text x={x + barWidth / 2} y="198" textAnchor="middle" fontSize="11" fill="var(--bo-text-muted)">{formatDate(days[index])}</text>
            </g>
          );
        })}
      </svg>
      <div style={chartHintStyle}>Total sur la période : <strong>{values.reduce((total, value) => total + value, 0)}</strong> vues</div>
    </div>
  );
}

function ViewsByBoutiqueChart({ data }: { data: ViewStats['boutiques'] }) {
  const days = lastSevenDays();
  const boutiqueNames = [...new Map(data.map((item) => [item.boutiqueId, item.boutiqueName])).entries()];
  const colors = ['var(--bo-primary)', 'var(--bo-success)', 'var(--bo-warning)', 'var(--bo-error)', '#8b5cf6'];
  const valuesByDay = days.map((date) => boutiqueNames.map(([boutiqueId]) => data.find((item) => item.date === date && item.boutiqueId === boutiqueId)?.views ?? 0));
  const totals = valuesByDay.map((values) => values.reduce((total, value) => total + value, 0));
  const max = Math.max(1, ...totals);
  const chartHeight = 170;
  const chartWidth = 700;
  const barWidth = 54;
  const gap = (chartWidth - days.length * barWidth) / (days.length + 1);

  return (
    <div>
      <svg viewBox="0 0 700 220" role="img" aria-label="Nombre de vues par boutique et par jour" style={{ display: 'block', width: '100%', height: 'auto' }}>
        <line x1="0" y1={chartHeight} x2={chartWidth} y2={chartHeight} stroke="var(--bo-border)" />
        {valuesByDay.map((values, dayIndex) => {
          const x = gap + dayIndex * (barWidth + gap);
          let offset = 0;

          return (
            <g key={days[dayIndex]}>
              {values.map((value, boutiqueIndex) => {
                const height = value > 0 ? Math.max(3, (value / max) * chartHeight) : 0;
                const y = chartHeight - offset - height;
                offset += height;

                return <rect key={boutiqueNames[boutiqueIndex]?.[0] ?? boutiqueIndex} x={x} y={y} width={barWidth} height={height} fill={colors[boutiqueIndex % colors.length]} opacity="0.9" />;
              })}
              <text x={x + barWidth / 2} y={chartHeight - offset - 8} textAnchor="middle" fontSize="12" fill="var(--bo-text)">{totals[dayIndex]}</text>
              <text x={x + barWidth / 2} y="198" textAnchor="middle" fontSize="11" fill="var(--bo-text-muted)">{formatDate(days[dayIndex])}</text>
            </g>
          );
        })}
      </svg>
      {boutiqueNames.length > 0 ? (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, fontSize: 12, color: 'var(--bo-text-muted)' }}>
          {boutiqueNames.map(([boutiqueId, name], index) => (
            <span key={boutiqueId}><i style={{ display: 'inline-block', width: 8, height: 8, borderRadius: 2, background: colors[index % colors.length], marginRight: 5 }} />{name}</span>
          ))}
        </div>
      ) : <div style={chartHintStyle}>Aucune vue enregistrée sur la période.</div>}
    </div>
  );
}

function lastSevenDays() {
  const today = new Date();

  return Array.from({ length: 7 }, (_, index) => {
    const date = new Date(today);
    date.setHours(0, 0, 0, 0);
    date.setDate(today.getDate() - (6 - index));

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  });
}

const chartHintStyle = { marginTop: 4, fontSize: 12, color: 'var(--bo-text-muted)' } as const;

function formatDate(value: string) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
  return new Date(`${value}T00:00:00`).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
}

function StatRow({ label, value, tone = 'neutral' }: { label: string; value: string | number; tone?: 'success' | 'warning' | 'error' | 'neutral' }) {
  const color = tone === 'success' ? 'var(--bo-success)' : tone === 'warning' ? 'var(--bo-warning)' : tone === 'error' ? 'var(--bo-error)' : 'var(--bo-text)';

  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, alignItems: 'center', fontSize: 13 }}>
      <span style={{ color: 'var(--bo-text-muted)' }}>{label}</span>
      <strong style={{ color }}>{value}</strong>
    </div>
  );
}

function money(cents: number) {
  return (cents / 100).toLocaleString('fr-TN', { style: 'currency', currency: 'TND' });
}
