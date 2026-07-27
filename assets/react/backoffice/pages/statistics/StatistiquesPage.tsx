import { useState, useCallback } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardHeader, CardBody } from '../../components/Card';
import { Button } from '../../components/Button';
import { Badge } from '../../components/Badge';
import { LoadingState, ErrorState } from '../../components/States';
import { PageHeader } from '../../layout/Shell';
import { Modal } from '../../components/Modal';
import { useBoutique } from '../../hooks/useBoutique';

type PlatformData = {
  kpis: {
    totalBoutiques: number; activeBoutiques: number; pendingBoutiques: number; newBoutiques: number;
    totalCustomers: number; totalProducts: number; totalOrders: number; ordersToday: number;
    platformRevenueCents: number; monthlyGrowthPercent: number | null;
    totalBoutiqueAdmins: number; totalEmployees: number;
  };
  subscriptions: { active: number; expired: number; expiringSoon: number };
  topBoutiques: Array<{ id: string; name: string; revenueCents: number; orders: number }>;
};

type AppConfig = {
  modules: Record<string, boolean>;
};

type BoutiqueDashboardData = {
  kpis: {
    salesMonthCents: number;
    ordersToday: number;
    ordersTotal: number;
    customersTotal: number;
    productsTotal: number;
  };
};

const MODULE_INFO: Record<string, { label: string; icon: string; color: string; section: string }> = {
  boutiques:   { label: 'Boutiques',  icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', color: '#6366f1', section: 'Plateforme' },
  produits:    { label: 'Produits',   icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', color: '#f59e0b', section: 'Catalogue' },
  categories:  { label: 'Catégories', icon: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z', color: '#10b981', section: 'Catalogue' },
  commandes:   { label: 'Commandes', icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z', color: '#3b82f6', section: 'Ventes' },
  paiements:   { label: 'Paiements', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', color: '#8b5cf6', section: 'Ventes' },
  livraison:   { label: 'Livraison', icon: 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0', color: '#ef4444', section: 'Ventes' },
  promotions:  { label: 'Promotions', icon: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z', color: '#f97316', section: 'Marketing' },
  coupons:     { label: 'Coupons',    icon: 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z', color: '#ec4899', section: 'Marketing' },
  cms:         { label: 'CMS',        icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z', color: '#06b6d4', section: 'Contenu' },
  blog:        { label: 'Blog',       icon: 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25', color: '#84cc16', section: 'Contenu' },
  avis:        { label: 'Avis',       icon: 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z', color: '#eab308', section: 'Contenu' },
  fidelite:    { label: 'Fidélité',   icon: 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z', color: '#ec4899', section: 'Clients' },
  wallet:      { label: 'Wallet',     icon: 'M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3', color: '#10b981', section: 'Clients' },
  seo:         { label: 'SEO',        icon: 'M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5m.75-9l3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605', color: '#14b8a6', section: 'Configuration' },
  facturation: { label: 'Facturation',icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z', color: '#a855f7', section: 'Configuration' },
  notifications: { label: 'Notifications', icon: 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0', color: '#f43f5e', section: 'Configuration' },
};

function SvgIcon({ path, color }: { path: string; color: string }) {
  return (
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} className="h-5 w-5" style={{ color }}>
      <path strokeLinecap="round" strokeLinejoin="round" d={path} />
    </svg>
  );
}

function StatRow({ label, value, color }: { label: string; value: string | number; color?: string }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '4px 0', fontSize: 13 }}>
      <span style={{ color: 'var(--bo-text-muted)' }}>{label}</span>
      <strong style={{ color: color ?? 'var(--bo-text)' }}>{value}</strong>
    </div>
  );
}

function StatBox({ label, value }: { label: string; value: string | number }) {
  return (
    <div style={{ padding: '12px 16px', borderRadius: 8, background: 'var(--bo-bg-subtle, rgba(0,0,0,0.02))', border: '1px solid var(--bo-border)' }}>
      <div style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>{label}</div>
      <div style={{ fontSize: 20, fontWeight: 700, marginTop: 4 }}>{value}</div>
    </div>
  );
}

export function StatistiquesPage({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const { boutique } = useBoutique();
  const fetchPlatform = useCallback(async (): Promise<PlatformData> => {
    if (!boutique) {
      return api.get<PlatformData>('/admin/dashboard/platform');
    }

    const dashboard = await api.get<BoutiqueDashboardData>(`/admin/boutiques/${boutique.id}/dashboard`);
    const status = boutique.status.toUpperCase();
    const kpis = dashboard.kpis;

    return {
      kpis: {
        totalBoutiques: 1,
        activeBoutiques: status === 'ACTIVE' ? 1 : 0,
        pendingBoutiques: status === 'PENDING' ? 1 : 0,
        newBoutiques: 0,
        totalCustomers: kpis.customersTotal,
        totalProducts: kpis.productsTotal,
        totalOrders: kpis.ordersTotal,
        ordersToday: kpis.ordersToday,
        platformRevenueCents: kpis.salesMonthCents,
        monthlyGrowthPercent: null,
        totalBoutiqueAdmins: 0,
        totalEmployees: 0,
      },
      subscriptions: { active: 0, expired: 0, expiringSoon: 0 },
      topBoutiques: [{
        id: boutique.id,
        name: boutique.name,
        revenueCents: kpis.salesMonthCents,
        orders: kpis.ordersTotal,
      }],
    };
  }, [api, boutique]);
  const fetchConfig = useCallback(() => api.get<AppConfig>('/admin/dashboard/modules'), [api]);

  const { data: plat, isLoading, error, refresh } = useApiData(fetchPlatform);
  const { data: config } = useApiData(fetchConfig);

  const modules = config?.modules ?? {};
  const kpis = plat?.kpis;

  const [showModulesModal, setShowModulesModal] = useState(false);

  const moduleAliases: Record<string, string[]> = {
    produits: ['products', 'produits'], categories: ['categories'],
    customers: ['customers', 'clients'], commandes: ['orders', 'commandes'],
    paiements: ['payments', 'paiements'], livraison: ['delivery', 'livraison'],
    promotions: ['promotions'], coupons: ['coupons'], cms: ['cms'], blog: ['blog'],
    avis: ['reviews', 'avis'], fidelite: ['loyalty', 'fidelite'], wallet: ['wallet'],
    analytics: ['analytics'], themes: ['themes'], audit: ['audit'], monitoring: ['monitoring'],
    seo: ['seo', 'seo_advanced'], facturation: ['facturation'], notifications: ['notifications'],
  };

  const isOn = (k: string) => {
    const aliases = moduleAliases[k] ?? [k];
    return aliases.some((alias) => modules[alias] === true);
  };

  const centsToTnd = (c: number) => (c / 100).toLocaleString('fr-TN', { style: 'currency', currency: 'TND' });

  return (
    <div className="bo-page">
      <PageHeader
        title="Statistiques"
        description={boutique ? `Indicateurs clés de ${boutique.name}` : 'Indicateurs clés de la plateforme'}
        actions={
          <Button variant="primary" onClick={() => setShowModulesModal(true)}>Modules</Button>
        }
      />

      {isLoading ? <LoadingState /> : error ? <ErrorState message={error} onRetry={refresh} /> : (
        <div className="bo-page-content space-y-6">
          {/* KPI row */}
          {kpis && (
            <div className="bo-kpi-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(190px, 1fr))', gap: 16 }}>
              <Card><CardBody>
                <div className="bo-widget"><SvgIcon path={MODULE_INFO.boutiques.icon} color={MODULE_INFO.boutiques.color} />
                  <div className="bo-widget-info"><span className="bo-widget-label">Boutiques</span><strong className="bo-widget-value">{kpis.totalBoutiques}</strong></div>
                  <div className="bo-widget-change"><span style={{ color: 'var(--bo-success)' }}>{kpis.newBoutiques} nouveau{kpis.newBoutiques > 1 ? 'x' : ''}</span></div>
                </div>
              </CardBody></Card>

              <Card><CardBody>
                <div className="bo-widget"><SvgIcon path={MODULE_INFO.facturation.icon} color={MODULE_INFO.facturation.color} />
                  <div className="bo-widget-info"><span className="bo-widget-label">Abonnements</span><strong className="bo-widget-value">{plat.subscriptions.active}</strong></div>
                  <div className="bo-widget-change"><span style={{ color: 'var(--bo-warning)' }}>{plat.subscriptions.expiringSoon} expirent bientôt</span></div>
                </div>
              </CardBody></Card>

              <Card><CardBody>
                <div className="bo-widget"><SvgIcon path={MODULE_INFO.boutiques.icon} color="#22c55e" />
                  <div className="bo-widget-info"><span className="bo-widget-label">Actives</span><strong className="bo-widget-value" style={{ color: 'var(--bo-success)' }}>{kpis.activeBoutiques}</strong></div>
                </div>
              </CardBody></Card>

              <Card><CardBody>
                <div className="bo-widget"><SvgIcon path={MODULE_INFO.boutiques.icon} color="#eab308" />
                  <div className="bo-widget-info"><span className="bo-widget-label">En attente</span><strong className="bo-widget-value" style={{ color: 'var(--bo-warning)' }}>{kpis.pendingBoutiques}</strong></div>
                </div>
              </CardBody></Card>

              <Card><CardBody>
                <div className="bo-widget"><SvgIcon path={MODULE_INFO.facturation.icon} color={MODULE_INFO.facturation.color} />
                  <div className="bo-widget-info"><span className="bo-widget-label">Revenu mois</span>
                    <strong className="bo-widget-value" style={{ fontSize: 15 }}>{centsToTnd(kpis.platformRevenueCents)}</strong>
                  </div>
                  {kpis.monthlyGrowthPercent !== null && (
                    <div className="bo-widget-change">
                      <span style={{ color: kpis.monthlyGrowthPercent >= 0 ? 'var(--bo-success)' : 'var(--bo-error)' }}>
                        {kpis.monthlyGrowthPercent >= 0 ? '+' : ''}{kpis.monthlyGrowthPercent}%
                      </span>
                    </div>
                  )}
                </div>
              </CardBody></Card>

              {isOn('commandes') && (
                <Card><CardBody>
                  <div className="bo-widget"><SvgIcon path={MODULE_INFO.commandes.icon} color={MODULE_INFO.commandes.color} />
                    <div className="bo-widget-info"><span className="bo-widget-label">Commandes</span><strong className="bo-widget-value">{kpis.totalOrders}</strong></div>
                    <div className="bo-widget-change"><span style={{ color: 'var(--bo-text-muted)' }}>{kpis.ordersToday} aujourd'hui</span></div>
                  </div>
                </CardBody></Card>
              )}

              {isOn('customers') && (
                <Card><CardBody>
                  <div className="bo-widget"><SvgIcon path={MODULE_INFO.facturation.icon} color="#6366f1" />
                    <div className="bo-widget-info"><span className="bo-widget-label">Clients</span><strong className="bo-widget-value">{kpis.totalCustomers}</strong></div>
                  </div>
                </CardBody></Card>
              )}

              {isOn('produits') && (
                <Card><CardBody>
                  <div className="bo-widget"><SvgIcon path={MODULE_INFO.produits.icon} color={MODULE_INFO.produits.color} />
                    <div className="bo-widget-info"><span className="bo-widget-label">Produits</span><strong className="bo-widget-value">{kpis.totalProducts}</strong></div>
                  </div>
                </CardBody></Card>
              )}

              <Card><CardBody>
                <div className="bo-widget"><SvgIcon path={MODULE_INFO.notifications.icon} color={MODULE_INFO.notifications.color} />
                  <div className="bo-widget-info"><span className="bo-widget-label">Administrateurs</span><strong className="bo-widget-value">{kpis.totalBoutiqueAdmins}</strong></div>
                  <div className="bo-widget-change"><span style={{ color: 'var(--bo-text-muted)' }}>{kpis.totalEmployees} employés</span></div>
                </div>
              </CardBody></Card>
            </div>
          )}

          {/* Stats grid */}
          <div className="bo-stats-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: 16 }}>
            <Card>
              <CardHeader><span>Boutiques</span></CardHeader>
              <CardBody>
                <StatRow label="Total" value={kpis?.totalBoutiques ?? '-'} />
                <StatRow label="Actives" value={kpis?.activeBoutiques ?? '-'} color="var(--bo-success)" />
                <StatRow label="Suspendues" value={kpis ? kpis.totalBoutiques - kpis.activeBoutiques - kpis.pendingBoutiques : '-'} color="var(--bo-error)" />
                <StatRow label="En attente" value={kpis?.pendingBoutiques ?? '-'} color="var(--bo-warning)" />
              </CardBody>
            </Card>

            <Card>
              <CardHeader><span>Abonnements</span></CardHeader>
              <CardBody>
                <StatRow label="Actifs" value={plat?.subscriptions.active ?? '-'} color="var(--bo-success)" />
                <StatRow label="Expirent bientôt" value={plat?.subscriptions.expiringSoon ?? '-'} color="var(--bo-warning)" />
                <StatRow label="Expirés" value={plat?.subscriptions.expired ?? '-'} color="var(--bo-error)" />
                <StatRow label="Revenu total" value={centsToTnd(kpis?.platformRevenueCents ?? 0)} />
              </CardBody>
            </Card>

            {isOn('produits') && (
              <Card>
                <CardHeader><span>Produits</span></CardHeader>
                <CardBody>
                  <StatRow label="Total produits" value={kpis?.totalProducts ?? '-'} />
                  <StatRow label="Actifs" value={kpis?.totalProducts ?? '-'} color="var(--bo-success)" />
                  <StatRow label="En rupture" value={0} color="var(--bo-error)" />
                </CardBody>
              </Card>
            )}

            {isOn('commandes') && (
              <Card>
                <CardHeader><span>Commandes</span></CardHeader>
                <CardBody>
                  <StatRow label="Total" value={kpis?.totalOrders ?? '-'} />
                  <StatRow label="Aujourd'hui" value={kpis?.ordersToday ?? '-'} color="var(--bo-primary)" />
                  <StatRow label="En attente" value={'-'} color="var(--bo-warning)" />
                  <StatRow label="Annulées" value={0} color="var(--bo-error)" />
                </CardBody>
              </Card>
            )}

            {isOn('paiements') && (
              <Card>
                <CardHeader><span>Paiements</span></CardHeader>
                <CardBody>
                  <StatRow label="Validés" value={'-'} color="var(--bo-success)" />
                  <StatRow label="Refusés" value={0} color="var(--bo-error)" />
                  <StatRow label="Revenus" value={centsToTnd(kpis?.platformRevenueCents ?? 0)} />
                </CardBody>
              </Card>
            )}

            {isOn('livraison') && (
              <Card>
                <CardHeader><span>Livraison</span></CardHeader>
                <CardBody>
                  <StatRow label="Livraisons" value={'-'} />
                  <StatRow label="En retard" value={0} color="var(--bo-error)" />
                  <StatRow label="Transporteurs" value={0} />
                </CardBody>
              </Card>
            )}

            {isOn('promotions') && (
              <Card>
                <CardHeader><span>Promotions</span></CardHeader>
                <CardBody>
                  <StatRow label="Promos actives" value={'-'} />
                  <StatRow label="Coupons utilisés" value={0} />
                </CardBody>
              </Card>
            )}

            {isOn('cms') && (
              <Card>
                <CardHeader><span>CMS</span></CardHeader>
                <CardBody>
                  <StatRow label="Pages" value={'-'} />
                  <StatRow label="Trafic" value={0} />
                </CardBody>
              </Card>
            )}

            {isOn('blog') && (
              <Card>
                <CardHeader><span>Blog</span></CardHeader>
                <CardBody>
                  <StatRow label="Articles" value={'-'} />
                  <StatRow label="Vues" value={0} />
                  <StatRow label="Publications" value={'-'} />
                </CardBody>
              </Card>
            )}

            {isOn('avis') && (
              <Card>
                <CardHeader><span>Avis</span></CardHeader>
                <CardBody>
                  <StatRow label="Avis produits" value={'-'} />
                  <StatRow label="Avis boutiques" value={0} />
                  <StatRow label="En modération" value={0} color="var(--bo-warning)" />
                </CardBody>
              </Card>
            )}

            {isOn('fidelite') && (
              <Card>
                <CardHeader><span>Fidélité</span></CardHeader>
                <CardBody>
                  <StatRow label="Points distribués" value={'-'} />
                  <StatRow label="Points utilisés" value={0} />
                </CardBody>
              </Card>
            )}

            {isOn('wallet') && (
              <Card>
                <CardHeader><span>Wallet</span></CardHeader>
                <CardBody>
                  <StatRow label="Soldes wallet" value={'-'} />
                  <StatRow label="Remboursements" value={0} />
                </CardBody>
              </Card>
            )}

            {isOn('seo') && (
              <Card>
                <CardHeader><span>SEO</span></CardHeader>
                <CardBody>
                  <StatRow label="Pages optimisées" value={'-'} />
                  <StatRow label="Sitemaps" value={0} />
                </CardBody>
              </Card>
            )}

            {isOn('facturation') && (
              <Card>
                <CardHeader><span>Facturation</span></CardHeader>
                <CardBody>
                  <StatRow label="Factures émises" value={'-'} />
                  <StatRow label="En attente" value={0} color="var(--bo-warning)" />
                </CardBody>
              </Card>
            )}

            {isOn('notifications') && (
              <Card>
                <CardHeader><span>Notifications</span></CardHeader>
                <CardBody>
                  <StatRow label="Envoyées (mois)" value={'-'} />
                  <StatRow label="Taux d'ouverture" value={'—'} />
                </CardBody>
              </Card>
            )}
          </div>

          {/* Top boutiques */}
          {plat && plat.topBoutiques.length > 0 && (
            <Card>
              <CardHeader><span>Top boutiques (CA)</span></CardHeader>
              <CardBody>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                  {plat.topBoutiques.map((tb, i) => (
                    <div key={tb.id} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '8px 12px', borderRadius: 8, background: 'var(--bo-bg-subtle, rgba(0,0,0,0.02))' }}>
                      <span className="bo-table-cell-text" style={{ fontWeight: 700, color: 'var(--bo-text-muted)', width: 24 }}>#{i + 1}</span>
                      <div style={{ flex: 1 }}><strong>{tb.name}</strong></div>
                      <span style={{ color: 'var(--bo-text-muted)', fontSize: 13 }}>{tb.orders} commandes</span>
                      <strong style={{ fontSize: 14 }}>{centsToTnd(tb.revenueCents)}</strong>
                    </div>
                  ))}
                </div>
              </CardBody>
            </Card>
          )}
        </div>
      )}

      <Modal isOpen={showModulesModal} onClose={() => setShowModulesModal(false)} title="Gestion des modules">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
          {Object.entries(MODULE_INFO).map(([key, info]) => {
            const enabled = modules[key] !== false;
            return (
              <div key={key} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '10px 12px', borderRadius: 8, border: '1px solid var(--bo-border)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <SvgIcon path={info.icon} color={info.color} />
                  <div>
                    <div style={{ fontWeight: 600, fontSize: 14 }}>{info.label}</div>
                    <div style={{ fontSize: 11, color: 'var(--bo-text-muted)' }}>{info.section}</div>
                  </div>
                </div>
                <Badge tone={enabled ? 'success' : 'error'}>{enabled ? 'Actif' : 'Inactif'}</Badge>
              </div>
            );
          })}
        </div>
      </Modal>
    </div>
  );
}
