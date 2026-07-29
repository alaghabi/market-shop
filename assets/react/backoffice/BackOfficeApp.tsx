import { useState, useEffect, type JSX } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { Shell } from './layout/Shell';
import { BoutiqueCtx } from './hooks/useBoutique';
import { NotificationProvider } from './hooks/useNotification';
import { ToastContainer } from './components/Toast';
import { DashboardPage } from './pages/dashboard/DashboardPage';
import { ProductsPage } from './pages/products/ProductsPage';
import { ProductFormPage } from './pages/products/ProductFormPage';
import { CategoriesPage } from './pages/categories/CategoriesPage';
import { FiltersPage } from './pages/filters/FiltersPage';
import { OrdersPage } from './pages/orders/OrdersPage';
import { CustomersPage } from './pages/customers/CustomersPage';
import { PromotionsPage } from './pages/promotions/PromotionsPage';
import { PromotionFormPage } from './pages/promotions/PromotionFormPage';
import { CmsManagementPage } from './pages/cms/CmsPage';
import { SettingsPage } from './pages/settings/SettingsPage';
import { FrontOfficePage } from './pages/front-office/FrontOfficePage';
import { ReviewsPage } from './pages/reviews/ReviewsPage';
import { ChatPage } from './pages/chat/ChatPage';
import { EmployeesPage } from './pages/employees/EmployeesPage';
import { SubscriptionsPage } from './pages/subscriptions/SubscriptionsPage';
import { DeliveryPage } from './pages/delivery/DeliveryPage';
import { SuperAdminPage } from './pages/super-admin/SuperAdminPage';
import { BoutiquesPage } from './pages/boutiques/BoutiquesPage';
import { BoutiqueDetailsPage } from './pages/boutiques/BoutiqueDetailsPage';
import { StatistiquesPage } from './pages/statistics/StatistiquesPage';
import { AnalyticsPage } from './pages/analytics/AnalyticsPage';
import { BoutiqueAdminsPage } from './pages/boutique-admins/BoutiqueAdminsPage';
import { ModulesPage } from './pages/modules/ModulesPage';
import { NotificationsPage } from './pages/notifications/NotificationsPage';
import { ThemesPage } from './pages/themes/ThemesPage';
import { SuggestionsPage } from './pages/suggestions/SuggestionsPage';
import { PlatformSettingsPage } from './pages/platform-settings/PlatformSettingsPage';
import { AnnouncementsPage } from './pages/announcements/AnnouncementsPage';
import { AnnouncementFormPage } from './pages/announcements/AnnouncementFormPage';
import { Card, CardBody } from './components/Card';
import { LoadingState } from './components/States';
import type { BackOfficeAccess, Boutique } from './types';

type PageProps = { getAccessToken: () => string | null; userRoles?: string[]; boutiqueId?: string; productId?: string };
type RouteGate = { moduleAliases?: string[]; permissions?: string[]; roles?: string[]; sensitive?: boolean };
function handleUnauthorized(response: Response): Response {
  if (response.status === 401) {
    window.location.assign('/auth/login');
  }

  return response;
}

const routeGates: Record<string, RouteGate> = {
  dashboard: { roles: ['ROLE_SUPER_ADMIN', 'ROLE_BOUTIQUE_ADMIN', 'ROLE_CAISSIER'] },
  products: { permissions: ['product.read', 'view_products'] },
  'product-new': { permissions: ['product.update', 'edit_products'] },
  'product-edit': { permissions: ['product.update', 'edit_products'] },
  'product-detail': { permissions: ['product.read', 'view_products'] },
  categories: { permissions: ['product.category.manage'] },
  filters: { permissions: ['product.update', 'edit_products'] },
  orders: { permissions: ['order.read', 'view_orders'] },
  customers: { permissions: ['customer.read'] },
  promotions: { moduleAliases: ['promotions', 'coupons'], permissions: ['marketing.promotion.manage', 'marketing.coupon.manage', 'promotions', 'coupons'] },
  'promotion-new': { moduleAliases: ['promotions', 'coupons'], permissions: ['marketing.promotion.manage', 'marketing.coupon.manage', 'promotions', 'coupons'] },
  'promotion-edit': { moduleAliases: ['promotions', 'coupons'], permissions: ['marketing.promotion.manage', 'marketing.coupon.manage', 'promotions', 'coupons'] },
  announcements: { permissions: ['cms.banner.manage', 'annonces', 'announcements'] },
  'announcement-new': { permissions: ['cms.banner.manage', 'annonces', 'announcements'] },
  'announcement-edit': { permissions: ['cms.banner.manage', 'annonces', 'announcements'] },
  reviews: { moduleAliases: ['reviews'], permissions: ['review.read', 'view_reviews'] },
  chat: { roles: ['ROLE_SUPER_ADMIN', 'ROLE_BOUTIQUE_ADMIN', 'ROLE_CAISSIER'] },
  'chatbot-config': { roles: ['ROLE_SUPER_ADMIN', 'ROLE_BOUTIQUE_ADMIN'] },
  cms: { moduleAliases: ['cms', 'blog'], permissions: ['cms.page.read', 'cms_access', 'cms', 'blog'] },
  appearance: { permissions: ['shop.appearance.manage', 'shop.settings.manage'], sensitive: true },
  theme: { permissions: ['shop.appearance.manage', 'shop.settings.manage'], sensitive: true },
  settings: { permissions: ['shop.settings.manage'], sensitive: true },
  employees: { moduleAliases: ['employees'], permissions: ['employee.read'], sensitive: true },
  subscriptions: { permissions: ['subscription.plan.read'], sensitive: true },
  delivery: { permissions: ['shop.delivery_account.manage', 'order.delivery.manage'], sensitive: true },
  boutiques: { roles: ['ROLE_SUPER_ADMIN'] },
  'boutique-detail': { roles: ['ROLE_SUPER_ADMIN'] },
  statistics: { roles: ['ROLE_SUPER_ADMIN'] },
  analytics: { roles: ['ROLE_SUPER_ADMIN'] },
  'boutique-admins': { roles: ['ROLE_SUPER_ADMIN'] },
  'super-admin': { roles: ['ROLE_SUPER_ADMIN'] },
  modules: { roles: ['ROLE_SUPER_ADMIN'] },
  notifications: { roles: ['ROLE_SUPER_ADMIN', 'ROLE_BOUTIQUE_ADMIN', 'ROLE_CAISSIER'] },
  themes: { roles: ['ROLE_SUPER_ADMIN'] },
  'platform-settings': { roles: ['ROLE_SUPER_ADMIN'] },
  suggestions: { permissions: ['suggestion.read'] },
};

function resolvePage(slug: string, props: PageProps) {
  const pages: Record<string, (p: PageProps) => JSX.Element> = {
    dashboard: (p) => <DashboardPage {...p} />,
    products: (p) => <ProductsPage {...p} />,
    'product-new': (p) => <ProductFormPage getAccessToken={p.getAccessToken} mode="create" />,
    'product-edit': (p) => <ProductFormPage {...p} mode="edit" productId={p.productId} />,
    'product-detail': (p) => <ProductFormPage {...p} mode="detail" productId={p.productId} />,
    categories: (p) => <CategoriesPage {...p} />,
    filters: (p) => <FiltersPage {...p} />,
    orders: (p) => <OrdersPage {...p} />,
    customers: (p) => <CustomersPage {...p} />,
    promotions: (p) => <PromotionsPage {...p} />,
    'promotion-new': (p) => <PromotionFormPage getAccessToken={p.getAccessToken} />,
    'promotion-edit': (p) => <PromotionFormPage getAccessToken={p.getAccessToken} promotionId={p.productId} />,
    announcements: (p) => <AnnouncementsPage {...p} />,
    'announcement-new': (p) => <AnnouncementFormPage getAccessToken={p.getAccessToken} />,
    'announcement-edit': (p) => <AnnouncementFormPage getAccessToken={p.getAccessToken} announcementId={p.productId} />,
    reviews: (p) => <ReviewsPage {...p} />,
    chat: (p) => <ChatPage {...p} />,
    'chatbot-config': () => <Navigate to="/admin/chat" replace />,
    cms: (p) => <CmsManagementPage {...p} />,
    appearance: (p) => <FrontOfficePage {...p} />,
    theme: (p) => <FrontOfficePage {...p} />,
    settings: (p) => <SettingsPage {...p} />,
    employees: (p) => <EmployeesPage {...p} />,
    subscriptions: (p) => <SubscriptionsPage {...p} />,
    delivery: (p) => <DeliveryPage {...p} />,
    boutiques: (p) => <BoutiquesPage {...p} />,
    'boutique-detail': (p) => <BoutiqueDetailsPage {...p} boutiqueId={p.boutiqueId ?? ''} />,
    statistics: (p) => <StatistiquesPage {...p} />,
    analytics: (p) => <AnalyticsPage {...p} />,
    'boutique-admins': (p) => <BoutiqueAdminsPage {...p} />,
    'super-admin': (p) => <SuperAdminPage {...p} />,
    modules: (p) => <ModulesPage {...p} />,
    notifications: (p) => <NotificationsPage {...p} />,
    themes: (p) => <ThemesPage {...p} />,
    'platform-settings': (p) => <PlatformSettingsPage {...p} />,
    suggestions: (p) => <SuggestionsPage {...p} />,
  };
  return pages[slug]?.(props) ?? <DashboardPage {...props} />;
}

function canOpenRoute(slug: string, userRoles: string[], access: BackOfficeAccess | null) {
  const gate = routeGates[slug];
  if (!gate) return false;
  if (userRoles.includes('ROLE_SUPER_ADMIN')) return true;
  if (gate.roles?.some((role) => userRoles.includes(role))) return true;
  if (gate.sensitive && !userRoles.includes('ROLE_BOUTIQUE_ADMIN')) return false;
  if (!access) return slug === 'dashboard';

  const moduleOk = !gate.moduleAliases || gate.moduleAliases.some((module) =>
    access.globalModules[module] === true && access.boutiqueModules[module]?.accessible === true,
  );
  if (!moduleOk) return false;

  return (gate.permissions ?? []).some((permission) => access.permissions.includes(permission));
}

function AccessDeniedPage() {
  return (
    <Card>
      <CardBody>
        <div style={{ textAlign: 'center', padding: 32 }}>
          <h2 style={{ margin: 0 }}>Accès refusé</h2>
          <p style={{ color: 'var(--bo-text-muted)', marginTop: 8 }}>Cette page est masquée par les permissions, les modules ou l'abonnement de la boutique.</p>
        </div>
      </CardBody>
    </Card>
  );
}

function slugFromPath(path: string): string {
  const segments = path.replace(/^\/admin\/?/, '').split('/');
  if (segments[0] === 'boutiques' && segments[1]) return 'boutique-detail';
  if (segments[0] === 'products' && segments[1] === 'new') return 'product-new';
  if (segments[0] === 'products' && segments[1] && segments[2] === 'edit') return 'product-edit';
  if (segments[0] === 'products' && segments[1]) return 'product-detail';
  if (segments[0] === 'announcements' && segments[1] === 'new') return 'announcement-new';
  if (segments[0] === 'announcements' && segments[1] && segments[2] === 'edit') return 'announcement-edit';
  if (segments[0] === 'promotions' && segments[1] === 'new') return 'promotion-new';
  if (segments[0] === 'promotions' && segments[1] && segments[2] === 'edit') return 'promotion-edit';
  return segments[0] || 'dashboard';
}

const selectedBoutiqueStorageKey = 'market-shop.backoffice.selected-boutique';

export function BackOfficeApp({
  userEmail,
  userRoles,
  userBoutiques,
  getAccessToken,
  onSignOut,
}: {
  userEmail: string;
  userRoles: string[];
  userBoutiques: Array<{ id: string; name: string; slug: string; status: string; customDomain?: string | null; isVisiblePublicly?: boolean }>;
  getAccessToken: () => string | null;
  onSignOut: () => Promise<void>;
}) {
  const location = useLocation();
  const currentPath = location.pathname;
  const pageSlug = slugFromPath(currentPath);

  const isSuperAdmin = userRoles.includes('ROLE_SUPER_ADMIN');
  const defaultBoutique: Boutique | null = !isSuperAdmin && userBoutiques.length > 0
    ? { id: userBoutiques[0].id, name: userBoutiques[0].name, slug: userBoutiques[0].slug, status: userBoutiques[0].status, customDomain: userBoutiques[0].customDomain, isVisiblePublicly: userBoutiques[0].isVisiblePublicly }
    : null;

  const boutiqueListFromProps = userBoutiques.map((b) => ({ id: b.id, name: b.name, slug: b.slug, status: b.status, customDomain: b.customDomain, isVisiblePublicly: b.isVisiblePublicly }));
  const storedBoutiqueId = typeof window !== 'undefined' ? window.localStorage.getItem(selectedBoutiqueStorageKey) : null;
  const storedBoutique = isSuperAdmin && storedBoutiqueId
    ? boutiqueListFromProps.find((item) => item.id === storedBoutiqueId) ?? null
    : null;
  const [boutique, setBoutique] = useState<Boutique | null>(storedBoutique ?? defaultBoutique);
  const [boutiques, setBoutiques] = useState<Boutique[]>(
    boutiqueListFromProps,
  );
  const [access, setAccess] = useState<BackOfficeAccess | null>(null);
  const [accessLoading, setAccessLoading] = useState(true);

  useEffect(() => {
    const token = getAccessToken();
    if (token) {
      fetch('/api/boutiques', { headers: { Authorization: `Bearer ${token}` } })
        .then(handleUnauthorized)
        .then((r) => r.json())
        .then((data) => {
          const list: Boutique[] = data.member ?? data.items ?? [];
          if (list.length > 0) {
            setBoutiques(list);
            setBoutique((prev) => {
              if (prev) return prev;
              const saved = storedBoutiqueId ? list.find((item) => item.id === storedBoutiqueId) : null;
              return saved ?? (isSuperAdmin ? null : list[0]);
            });
          }
        })
        .catch(() => {});
    }
  }, [getAccessToken, isSuperAdmin]);

  const handleBoutiqueChange = (nextBoutique: Boutique | null) => {
    setBoutique(nextBoutique);
    if (typeof window === 'undefined') return;
    if (nextBoutique) {
      window.localStorage.setItem(selectedBoutiqueStorageKey, nextBoutique.id);
    } else {
      window.localStorage.removeItem(selectedBoutiqueStorageKey);
    }
  };

  useEffect(() => {
    const token = getAccessToken();
    if (!token) {
      setAccess(null);
      setAccessLoading(false);
      return;
    }

    setAccessLoading(true);

    if (!boutique) {
      fetch('/api/admin/dashboard/modules', { headers: { Authorization: `Bearer ${token}` } })
        .then(handleUnauthorized)
        .then((r) => r.ok ? r.json() : { modules: {} })
        .then((global) => {
          setAccess({
            globalModules: global.modules ?? {},
            boutiqueModules: {},
            permissions: [],
            roles: userRoles,
          });
        })
        .catch(() => setAccess(null))
        .finally(() => setAccessLoading(false));
      return;
    }

    Promise.all([
      fetch('/api/admin/dashboard/modules', { headers: { Authorization: `Bearer ${token}` } }).then(handleUnauthorized).then((r) => r.ok ? r.json() : { modules: {} }),
      fetch(`/api/admin/boutiques/${boutique.id}/dashboard/access`, { headers: { Authorization: `Bearer ${token}` } }).then(handleUnauthorized).then((r) => r.ok ? r.json() : { modules: {}, permissions: [], roles: userRoles }),
    ])
      .then(([global, boutiqueAccess]) => {
        setAccess({
          globalModules: global.modules ?? {},
          boutiqueModules: boutiqueAccess.modules ?? {},
          permissions: boutiqueAccess.permissions ?? [],
          roles: boutiqueAccess.roles ?? userRoles,
        });
      })
      .catch(() => setAccess(null))
      .finally(() => setAccessLoading(false));
  }, [boutique?.id, getAccessToken, userRoles.join('|')]);

  return (
    <BoutiqueCtx.Provider value={{ boutique, boutiques, setBoutique: handleBoutiqueChange }}>
      <NotificationProvider>
        <InnerApp
          currentPath={currentPath}
          pageSlug={pageSlug}
          userEmail={userEmail}
          userRoles={userRoles}
          boutique={boutique}
          boutiques={boutiques}
           onBoutiqueChange={handleBoutiqueChange}
          access={access}
          accessLoading={accessLoading}
          getAccessToken={getAccessToken}
          onSignOut={onSignOut}
        />
      </NotificationProvider>
    </BoutiqueCtx.Provider>
  );
}

function InnerApp({
  currentPath,
  pageSlug,
  userEmail,
  userRoles,
  boutique,
  boutiques,
  onBoutiqueChange,
  access,
  accessLoading,
  getAccessToken,
  onSignOut,
}: {
  currentPath: string;
  pageSlug: string;
  userEmail: string;
  userRoles: string[];
  boutique: Boutique | null;
  boutiques: Boutique[];
  onBoutiqueChange: (b: Boutique | null) => void;
  access: BackOfficeAccess | null;
  accessLoading: boolean;
  getAccessToken: () => string | null;
  onSignOut: () => Promise<void>;
}) {
  const waitingForAccess = accessLoading && !userRoles.includes('ROLE_SUPER_ADMIN');

  return (
    <>
      <Shell
        currentPath={currentPath}
        userEmail={userEmail}
        userRoles={userRoles}
        boutique={boutique}
        boutiques={boutiques}
        onBoutiqueChange={onBoutiqueChange}
        onSignOut={onSignOut}
        access={access}
        getAccessToken={getAccessToken}
      >
        {waitingForAccess ? (
          <Card><CardBody><LoadingState message="Vérification des accès..." /></CardBody></Card>
        ) : canOpenRoute(pageSlug, userRoles, access) ? (
          resolvePage(pageSlug, {
            getAccessToken,
            userRoles,
            boutiqueId: currentPath.split('/')[3],
            productId: currentPath.replace(/^\/admin\/?/, '').split('/')[1],
          })
        ) : (
          <AccessDeniedPage />
        )}
      </Shell>
      <ToastContainer />
    </>
  );
}
