import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { useEffect, useState } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { LoginPage } from './auth/LoginPage';
import { PasswordResetConfirmPage, PasswordResetRequestPage } from './auth/PasswordResetPages';
import { EmailVerificationPage } from './auth/EmailVerificationPage';
import { useAuth } from './auth/useAuth';
import { appIcons } from './icons/fontAwesome';
import { Badge, Button, Card, Select } from './components/ui';
import { BackOfficeApp } from './backoffice/BackOfficeApp';
import { ApplicationReviewsPage } from './pages/application-reviews';
import { BoutiqueCentralRoutePage } from './pages/boutique-central';
import { ActiveBoutiquesPage } from './pages/boutiques';
import { CartRoutePage } from './pages/cart';
import { CheckoutRoutePage } from './pages/checkout';
import { HomePage } from './pages/home';
import { MarketplaceRoutePage } from './pages/marketplace';
import { OrderConfirmationRoutePage } from './pages/order-confirmation';
import { ProductDetailRoutePage } from './pages/product-detail';
import { QuoteWizardRoutePage } from './pages/quote-wizard';
import { StorefrontRoutePage } from './pages/storefront';
import { SuggestionsPage } from './screens/public/SuggestionsPage';
import { BoutiqueCustomerAccountPage, BoutiqueCustomerAuthPage } from './screens/public/BoutiqueCustomerAccount';
import { isBoutiqueSubdomain, resolveBoutiqueSlug } from './screens/public/boutiqueRouting';
import { BoutiqueWeeklyTopProductModal } from './screens/public/storefront/BoutiqueWeeklyTopProductModal';
import { useBoutiqueTheme } from './theme/useBoutiqueTheme';

type AppIcon = keyof typeof appIcons;

type RouteConfig = {
  slug: string;
  title: string;
  path: string;
  section: string;
  description: string;
  icon: AppIcon;
  access: 'public' | 'admin';
};

type PublicBoutique = {
  name: string;
  slug: string;
  category?: string | null;
  city?: string | null;
  image?: string | null;
  href?: string;
  accent?: string | null;
  status?: string;
  logoUrl?: string | null;
  customDomain?: string | null;
  productsCount?: number;
  isPublished?: boolean;
  isVisiblePublicly?: boolean;
};

function useApiData<T>(url: string | null): { data: T | null; isLoaded: boolean } {
  const [data, setData] = useState<T | null>(null);
  const [isLoaded, setIsLoaded] = useState(false);

  useEffect(() => {
    if (!url) {
      setIsLoaded(true);
      return;
    }

    let isMounted = true;

    fetch(url)
      .then((response) => response.ok ? response.json() as Promise<T> : Promise.reject(new Error(`Unable to load ${url}.`)))
      .then((payload) => {
        if (isMounted) {
          setData(payload);
          setIsLoaded(true);
        }
      })
      .catch(() => {
        if (isMounted) {
          setIsLoaded(true);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [url]);

  return { data, isLoaded };
}

function resolveRoute(pathname: string, publicRoutes: RouteConfig[], adminRoutes: RouteConfig[]): RouteConfig | null {
  if (pathname === '/suggestions') {
    return { slug: 'suggestions-public', title: 'Boîte à suggestions', path: '/suggestions', section: 'Communauté', description: 'Partagez vos idées', icon: 'dashboard', access: 'public' };
  }

  if (pathname === '/admin/suggestions') {
    return { slug: 'suggestions-admin', title: 'Boîte à suggestions', path: '/admin/suggestions', section: 'Admin', description: 'Gestion des suggestions', icon: 'dashboard', access: 'admin' };
  }

  if (publicRoutes.length === 0 && adminRoutes.length === 0) {
    return null;
  }

  if (pathname === '/' || pathname === '') {
    return publicRoutes[0] ?? adminRoutes[0] ?? null;
  }

  const publicRoute = publicRoutes.find((route) => route.path === pathname);
  if (publicRoute) {
    return publicRoute;
  }

  if (pathname === '/admin') {
    return adminRoutes[0] ?? null;
  }

  if (pathname.startsWith('/admin/')) {
    return adminRoutes.find((route) => route.path === pathname)
      ?? { slug: 'dashboard', title: 'Dashboard Back-office', path: pathname, section: 'Admin', description: '', icon: 'dashboard', access: 'admin' };
  }

  return publicRoutes[0] ?? null;
}

export function App() {
  return (
    <BrowserRouter>
      <AppRoutes />
    </BrowserRouter>
  );
}

function AppRoutes() {
  const { user, isLoading, signIn, signInWithProvider, signUp, signOut, getAccessToken } = useAuth();
  const { theme } = useBoutiqueTheme();
  const location = useLocation();
  const { data: routesData } = useApiData<{ publicRoutes: RouteConfig[]; adminRoutes: RouteConfig[] }>('/api/routes');
  const publicRoutes = routesData?.publicRoutes ?? [];
  const adminRoutes = routesData?.adminRoutes ?? [];
  const userRoles = user?.profile.roles ?? [];
  const canAccessBackOffice = userRoles.some((role) => ['ROLE_SUPER_ADMIN', 'ROLE_BOUTIQUE_ADMIN', 'ROLE_CAISSIER', 'ROLE_EMPLOYEE'].includes(role));
  const subdomainSlug = isBoutiqueSubdomain();
  const route = resolveRoute(location.pathname, publicRoutes, adminRoutes);

  if (!routesData || !route) {
    return <main className="auth-shell">Chargement...</main>;
  }

  const renderAdminRoute = (adminRoute: RouteConfig) => {
    if (isLoading) {
      return <main className="auth-shell">Chargement de la session...</main>;
    }

    if (!user) {
      return <LoginPage onSignIn={signIn} onSignInWithProvider={signInWithProvider} onSignUp={signUp} />;
    }

    const allowedRoles = ['ROLE_SUPER_ADMIN', 'ROLE_BOUTIQUE_ADMIN', 'ROLE_CAISSIER', 'ROLE_EMPLOYEE'];
    const hasBackOfficeAccess = user.profile.roles.some((role) => allowedRoles.includes(role));

    if (!hasBackOfficeAccess) {
      return <RoleDeniedPage onSignOut={signOut} userEmail={user.profile.email ?? user.profile.sub} />;
    }

    return (
      <BackOfficeApp
        userEmail={user.profile.email ?? user.profile.sub}
        userRoles={user.profile.roles}
        userBoutiques={user.profile.boutiques ?? []}
        getAccessToken={getAccessToken}
        onSignOut={signOut}
      />
    );
  };

  return (
    <>
    <Routes>
      <Route path="/login" element={<Navigate to="/auth/login" replace />} />
      <Route path="/register" element={<Navigate to="/auth/register" replace />} />
      <Route path="/auth/login" element={subdomainSlug ? <Navigate to="/client/login" replace /> : <LoginPage onSignIn={signIn} onSignInWithProvider={signInWithProvider} onSignUp={signUp} initialMode="login" />} />
      <Route path="/auth/register" element={subdomainSlug ? <Navigate to="/client/register" replace /> : <LoginPage onSignIn={signIn} onSignInWithProvider={signInWithProvider} onSignUp={signUp} initialMode="register" />} />
      <Route path="/auth/forgot-password" element={subdomainSlug ? <Navigate to="/client/forgot-password" replace /> : <PasswordResetRequestPage />} />
      <Route path="/auth/reset-password" element={subdomainSlug ? <Navigate to="/client/reset-password" replace /> : <PasswordResetConfirmPage />} />
      <Route path="/auth/verify-email" element={<EmailVerificationPage />} />
      {!subdomainSlug && <Route path="/avis" element={<ApplicationReviewsPage />} />}
      <Route path="/suggestions" element={<SuggestionsPage />} />
      <Route path="/admin/suggestions" element={renderAdminRoute({ slug: 'suggestions', title: 'Boîte à suggestions', path: '/admin/suggestions', section: 'Admin', description: 'Gestion des suggestions', icon: 'dashboard', access: 'admin' })} />
      <Route path="/boutiques/:boutiqueSlug/cart" element={<CartRoutePage />} />
      <Route path="/boutiques/:boutiqueSlug/client/login" element={<BoutiqueCustomerAuthPage mode="login" />} />
      <Route path="/boutiques/:boutiqueSlug/client/register" element={<BoutiqueCustomerAuthPage mode="register" />} />
      <Route path="/boutiques/:boutiqueSlug/client/forgot-password" element={<PasswordResetRequestPage customer />} />
      <Route path="/boutiques/:boutiqueSlug/client/reset-password" element={<PasswordResetConfirmPage customer />} />
      <Route path="/boutiques/:boutiqueSlug/client/account" element={<BoutiqueCustomerAccountPage />} />
      <Route path="/boutiques/:boutiqueSlug/catalogue" element={<StorefrontRoutePage title="Catalogue" description="Découvrez nos produits" />} />
      <Route path="/boutiques/:boutiqueSlug/categories/:categorySlug" element={<StorefrontRoutePage title="Catégorie" description="Découvrez nos produits" />} />
      <Route path="/boutiques/:boutiqueSlug/promotions" element={<StorefrontRoutePage title="Promotions" description="Découvrez nos offres" />} />
      <Route path="/boutiques/:boutiqueSlug/avis" element={<StorefrontRoutePage title="Avis" description="Avis clients" />} />
      <Route path="/boutiques/:boutiqueSlug/a-propos" element={<StorefrontRoutePage title="À propos" description="Découvrez notre boutique" />} />
      <Route path="/boutiques/:boutiqueSlug/contact" element={<StorefrontRoutePage title="Contact" description="Contactez la boutique" />} />
      <Route path="/boutiques/:boutiqueSlug/pages/:pageSlug" element={<StorefrontRoutePage title="Page" description="Découvrez cette page" />} />
      <Route path="/boutiques/:boutiqueSlug/produit/:productSlug" element={<ProductDetailRoutePage title="Produit" />} />
      <Route path="/boutiques/:boutiqueSlug/products/:productSlug" element={<ProductDetailRoutePage title="Produit" />} />
      {subdomainSlug ? (
        <>
          <Route path="/catalogue" element={<StorefrontRoutePage title="Catalogue" description="Découvrez nos produits" />} />
          <Route path="/categories/:categorySlug" element={<StorefrontRoutePage title="Catégorie" description="Découvrez nos produits" />} />
          <Route path="/produit/:productSlug" element={<ProductDetailRoutePage title="Produit" />} />
          <Route path="/products/:productSlug" element={<ProductDetailRoutePage title="Produit" />} />
          <Route path="/promotions" element={<StorefrontRoutePage title="Promotions" description="Découvrez nos offres" />} />
          <Route path="/avis" element={<StorefrontRoutePage title="Avis" description="Avis clients" />} />
          <Route path="/a-propos" element={<StorefrontRoutePage title="À propos" description="Découvrez notre boutique" />} />
           <Route path="/contact" element={<StorefrontRoutePage title="Contact" description="Contactez la boutique" />} />
           <Route path="/pages/:pageSlug" element={<StorefrontRoutePage title="Page" description="Découvrez cette page" />} />
           <Route path="/cart" element={<CartRoutePage />} />
          <Route path="/checkout" element={<CheckoutRoutePage />} />
           <Route path="/client/login" element={<BoutiqueCustomerAuthPage mode="login" />} />
           <Route path="/client/register" element={<BoutiqueCustomerAuthPage mode="register" />} />
           <Route path="/client/forgot-password" element={<PasswordResetRequestPage customer />} />
           <Route path="/client/reset-password" element={<PasswordResetConfirmPage customer />} />
          <Route path="/client/account" element={<BoutiqueCustomerAccountPage />} />
        </>
      ) : null}
      {publicRoutes.map((publicRoute) => (
        <Route key={`public-${publicRoute.slug}-${publicRoute.path}`} path={toReactRouterPath(publicRoute)} element={<PublicPage route={publicRoute} canAccessBackOffice={canAccessBackOffice} />} />
      ))}
      {adminRoutes.map((adminRoute) => (
        <Route key={`admin-${adminRoute.slug}-${adminRoute.path}`} path={adminRoute.path} element={renderAdminRoute(adminRoute)} />
      ))}
      <Route path="/admin/*" element={renderAdminRoute(adminRoutes[0] ?? { slug: 'dashboard', title: 'Dashboard', path: '/admin/dashboard', section: 'Admin', description: '', icon: 'dashboard', access: 'admin' })} />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
    <BoutiqueWeeklyTopProductModal />
    </>
  );
}

function toReactRouterPath(route: RouteConfig): string {
  if (route.slug === 'boutique-storefront') {
    return '/boutiques/:boutiqueSlug';
  }

  if (route.slug === 'product-detail') {
    return '/boutiques/:boutiqueSlug/products/:productSlug';
  }

  return route.path;
}

function PublicPage({ route, canAccessBackOffice }: { route: RouteConfig; canAccessBackOffice: boolean }) {
  const location = useLocation();
  const needsBoutiques = ['home', 'boutiques', 'boutique-storefront', 'product-detail'].includes(route.slug);
  const { data: boutiquesData } = useApiData<{ member?: PublicBoutique[]; items?: PublicBoutique[] }>(needsBoutiques ? '/api/boutiques' : null);
  const boutiques = boutiquesData?.member ?? boutiquesData?.items ?? [];

  const boutiqueSlug = location.pathname.match(/^\/boutiques\/([^/]+)/)?.[1] ?? '';
  const currentBoutique = boutiques.find((b) => b.slug === boutiqueSlug);

  if (route.slug === 'home') {
    const subdomainSlug = resolveBoutiqueSlug(/^\/boutiques\/([^/]+)/);
    if (subdomainSlug) {
      return <StorefrontRoutePage title="Boutique" description="Découvrez nos produits" />;
    }

    return <HomePage canAccessBackOffice={canAccessBackOffice} boutiques={boutiques} />;
  }

  if (route.slug === 'boutiques') {
    return <ActiveBoutiquesPage boutiques={boutiques} />;
  }

  if (route.slug === 'admin-home') {
    return <BoutiqueCentralRoutePage title={route.title} description={route.description} />;
  }

  if (route.slug === 'boutique-storefront') {
    return <StorefrontRoutePage title={currentBoutique ? currentBoutique.name : route.title} description={currentBoutique ? `Boutique ${currentBoutique.name}` : route.description} />;
  }

  if (route.slug === 'product-detail') {
    return <ProductDetailRoutePage title={route.title} />;
  }

  if (route.slug === 'cart') {
    return <CartRoutePage />;
  }

  if (route.slug === 'checkout') {
    return <CheckoutRoutePage />;
  }

  if (route.slug === 'quote') {
    return <QuoteWizardRoutePage />;
  }

  if (route.slug === 'confirmation') {
    return <OrderConfirmationRoutePage />;
  }

  if (route.slug === 'suggestions-public') {
    return <SuggestionsPage />;
  }

  return <MarketplaceRoutePage title={route.title} description={route.description} boutiques={boutiques} />;
}


function RoleDeniedPage({ onSignOut, userEmail }: { onSignOut: () => Promise<void>; userEmail: string }) {
  return (
    <main className="auth-shell">
      <section className="auth-card">
        <div className="brand-mark" aria-hidden="true">
          <FontAwesomeIcon icon={appIcons.security} />
        </div>
        <p className="auth-eyebrow">Hanooti</p>
        <h1>Accès restreint</h1>
        <p>
          Votre rôle ne permet pas d&apos;accéder au back-office.
          Seuls les Super Admins, Admins boutique et Caissiers peuvent y accéder.
        </p>
        <div className="auth-info">
          Connecté en tant que <strong>{userEmail}</strong>
        </div>
        <button type="button" onClick={onSignOut}>
          <FontAwesomeIcon icon={appIcons.logout} /> Se déconnecter
        </button>
      </section>
    </main>
  );
}
