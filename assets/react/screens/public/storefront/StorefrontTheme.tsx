import { type ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import {
  ArrowRight,
  Mail,
  Menu,
  Search,
  ShieldCheck,
  Star,
  Sparkles,
  Truck,
  X,
} from 'lucide-react';
import { CartSheet } from './CartSheet';
import { authHeaders, boutiqueLink, boutiqueQuery } from '../boutiqueRouting';
import { type StoreProduct } from './ProductCard';
import type { StoreCategory, StoreFilter } from './catalogueTypes';
import { AboutPage, ContactPage, ProductListing, ReviewsPage } from './hanooti-marketplace/pages';
import { buildCategories, findCategoryBySlug, isPromotion, productMatchesCategory, resolvePage, resolvePathParam, withProductCounts } from './hanooti-marketplace/utils';
import { ImageWithFallback } from '../../../components/ImageWithFallback';
import { BoutiqueAccountLink } from '../BoutiqueCustomerAccount';
import { formatStoreReviewDate, storeReviewInitial, useStorefrontReviews, type StoreReview } from './reviews';
import { getStorefrontThemePreset } from '../../../theme/themes';
import { FavoriteButton } from './FavoriteButton';
import { BoutiqueMetrics } from './BoutiqueMetrics';
import { FavoritesPopover } from './FavoritesPopover';
import { OrderTrackingModal } from './OrderTrackingModal';
import { resolveSectionTitle, resolveStorefrontHero, resolveStorefrontNavigation, type StorefrontContent } from './content';

/** Brand accent used for CTAs/badges — falls back to the editorial black when a boutique has no custom color. */
const BRAND = 'var(--sf-accent, var(--ds-primary, #111111))';
const DEFAULT_HERO_BACKGROUND = '#111111';
const DEFAULT_HERO_TEXT = '#ffffff';

function resolveHeroBackground(boutique: StoreBoutique): string {
  const configuredBackground = boutique.backgroundColor?.trim() || boutique.colorPalette?.background?.trim();
  if (!configuredBackground) return DEFAULT_HERO_BACKGROUND;

  const presetBackground = getStorefrontThemePreset(boutique.theme)?.colorPalette.background;
  if (presetBackground?.toLowerCase() === configuredBackground.toLowerCase()) {
    return DEFAULT_HERO_BACKGROUND;
  }

  return configuredBackground;
}

function resolveHeroTextColor(boutique: StoreBoutique): string {
  const configuredText = boutique.colorPalette?.text?.trim();
  if (!configuredText) return DEFAULT_HERO_TEXT;

  const presetText = getStorefrontThemePreset(boutique.theme)?.colorPalette.text;
  if (presetText?.toLowerCase() === configuredText.toLowerCase()) {
    return DEFAULT_HERO_TEXT;
  }

  return configuredText;
}
const easeBrand = [0.16, 1, 0.3, 1] as const;
const fadeUp = {
  hidden: { opacity: 0, y: 18 },
  show: { opacity: 1, y: 0, transition: { duration: 0.5, ease: easeBrand } },
};
const staggerContainer = {
  hidden: {},
  show: { transition: { staggerChildren: 0.08 } },
};

export type { StoreProduct } from './ProductCard';

export type StoreBoutique = StorefrontContent & {
  id: string;
  name: string;
  slug: string;
  logoUrl?: string | null;
  description?: string | null;
  coverImage?: string | null;
  backgroundColor?: string | null;
  colorPalette?: Record<string, string> | null;
  email?: string | null;
  address?: string | null;
  primaryColor?: string;
  heroTitle?: string;
  heroSubtitle?: string;
  theme?: string | null;
  fontFamily?: string | null;
  fontSize?: string | null;
  borderRadius?: string | null;
  reviewsEnabled?: boolean;
  wishlistEnabled?: boolean;
  analyticsEnabled?: boolean;
  viewsEnabled?: boolean;
  customerAccountsEnabled?: boolean;
  chatbotEnabled?: boolean;
  customersWithAccount?: number;
  customersWithoutAccount?: number;
  publicOrdersCount?: number;
  productsCount?: number;
};

export type StoreAnnouncement = {
  id: string;
  content: string;
  priority?: number;
  active?: boolean;
  visible?: boolean;
  displayType?: string;
  title?: string | null;
  subtitle?: string | null;
  backgroundColor?: string | null;
  textColor?: string | null;
  borderColor?: string | null;
  linkUrl?: string | null;
  displayMode?: string;
  position?: string;
  displayPages?: string[];
  categoryIds?: string[];
  productIds?: string[];
};

type StoreCmsPage = {
  title: string;
  description?: string | null;
  content?: string | null;
  publishedAt?: string | null;
};

function sanitizeCmsHtml(value: string): string {
  if (typeof document === 'undefined') return value;

  const template = document.createElement('template');
  template.innerHTML = value;
  template.content.querySelectorAll('script, iframe, object, embed, style').forEach((node) => node.remove());
  template.content.querySelectorAll<HTMLElement>('*').forEach((element) => {
    [...element.attributes].forEach((attribute) => {
      if (attribute.name.toLowerCase().startsWith('on')) {
        element.removeAttribute(attribute.name);
      }
      if (['href', 'src', 'action'].includes(attribute.name.toLowerCase()) && /^(javascript|data):/i.test(attribute.value.trim())) {
        element.removeAttribute(attribute.name);
      }
    });
  });

  return template.innerHTML;
}

function CmsPageView({ page, loading }: { page: StoreCmsPage | null; loading: boolean }) {
  if (loading) return <div className="mx-auto max-w-4xl px-4 py-20 text-center text-black/60">Chargement de la page...</div>;
  if (!page) return <div className="mx-auto max-w-4xl px-4 py-20 text-center text-black/60">Page introuvable.</div>;

  return (
    <article className="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
      <h1 className="mb-6 text-4xl font-semibold tracking-tight">{page.title}</h1>
      {page.description && <p className="mb-8 text-lg text-black/60">{page.description}</p>}
      <div className="prose max-w-none text-black/75" dangerouslySetInnerHTML={{ __html: sanitizeCmsHtml(page.content ?? '') }} />
    </article>
  );
}

type CartItem = { product: StoreProduct; qty: number; itemId?: string };

type CartResponse = {
  currency: string;
  items: Array<{
    id: string;
    productId: string | null;
    productName: string | null;
    quantity: number;
    unitPriceCents: number;
    variantId?: string | null;
    variantSku?: string | null;
    variantAttributes?: Array<{ name: string; value: string }>;
  }>;
};

function cartItemKey(item: CartItem): string {
  return item.itemId ?? `${item.product.id}:${item.product.variantId ?? ''}`;
}

function AutoSlider({ itemCount, children, className = '' }: { itemCount: number; children: ReactNode; className?: string }) {
  const sliderRef = useRef<HTMLDivElement>(null);
  const [activeIndex, setActiveIndex] = useState(0);
  const [paused, setPaused] = useState(false);
  const reduceMotion = useReducedMotion();

  useEffect(() => {
    if (itemCount < 2 || paused) return undefined;

    const timer = window.setInterval(() => {
      setActiveIndex((current) => (current + 1) % itemCount);
    }, 4500);

    return () => window.clearInterval(timer);
  }, [itemCount, paused]);

  useEffect(() => {
    const slider = sliderRef.current;
    const item = slider?.children[activeIndex] as HTMLElement | undefined;
    if (!slider || !item) return;

    slider.scrollTo({
      left: item.offsetLeft - slider.offsetLeft,
      behavior: reduceMotion ? 'auto' : 'smooth',
    });
  }, [activeIndex, reduceMotion]);

  return (
    <div
      ref={sliderRef}
      className={`flex snap-x snap-mandatory gap-5 overflow-x-auto pb-3 scrollbar-none ${className}`}
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      onFocusCapture={() => setPaused(true)}
      onBlurCapture={() => setPaused(false)}
    >
      {children}
    </div>
  );
}

export function StorefrontTheme({
  boutique,
  products: initial,
  categories: loadedCategories = [],
  filters,
  announcements = [],
  promotedProductIds = [],
  reviewsEnabled = false,
  favoriteProductIds,
  onToggleFavorite,
  onFavoritesRefresh,
}: {
  boutique: StoreBoutique;
  products: StoreProduct[];
  categories?: StoreCategory[];
  filters: StoreFilter[];
  announcements?: StoreAnnouncement[];
  promotedProductIds?: string[];
  reviewsEnabled?: boolean;
  favoriteProductIds: string[];
  onToggleFavorite: (productId: string) => void;
  onFavoritesRefresh?: () => void;
}) {
  const [cart, setCart] = useState<CartItem[]>([]);
  const [mobileMenu, setMobileMenu] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [coverImageFailed, setCoverImageFailed] = useState(false);
  const [cmsPage, setCmsPage] = useState<StoreCmsPage | null>(null);
  const [cmsPageLoading, setCmsPageLoading] = useState(false);
  const [orderTrackingOpen, setOrderTrackingOpen] = useState(false);

  const heroColor = resolveHeroBackground(boutique);
  const heroTextColor = resolveHeroTextColor(boutique);

  const displayProducts = useMemo(
    () => initial.map((product) => ({ ...product, isPromoted: promotedProductIds.includes(product.id) })),
    [initial, promotedProductIds],
  );

  useEffect(() => {
    setCoverImageFailed(false);
  }, [boutique.coverImage]);

  window.__boutiqueSlug__ = boutique.slug;

  const categories = useMemo<StoreCategory[]>(() => loadedCategories.length > 0 ? withProductCounts(loadedCategories, displayProducts) : buildFallbackCategories(displayProducts), [displayProducts, loadedCategories]);

  const filteredProducts = useMemo(
    () =>
      searchQuery
        ? displayProducts.filter(
            (product) =>
              product.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
              product.description?.toLowerCase().includes(searchQuery.toLowerCase())
          )
        : displayProducts,
    [displayProducts, searchQuery]
  );

  const featuredProduct = filteredProducts[0] ?? displayProducts[0] ?? null;
  const newArrivals = filteredProducts.slice(0, 4);
  const spotlightPromos = filteredProducts.filter(isPromotion).slice(0, 4);
  const spotlightProducts = spotlightPromos.length > 0 ? spotlightPromos : filteredProducts.slice(0, 4);
  const bestSellers = [...filteredProducts]
    .sort((left, right) => (right.rating ?? 0) - (left.rating ?? 0))
    .slice(0, 2);
  const heroCategories = categories.slice(0, 2);
  const collectionCategories = categories.length > 0 ? categories : buildFallbackCategories(displayProducts);
  const routePage = resolvePage();
  const cmsSlug = resolvePathParam('pages');
  const categorySlug = resolvePathParam('categories');
  const currentCategory = findCategoryBySlug(categories, categorySlug);
  const routeProducts = routePage === 'category' && currentCategory
    ? displayProducts.filter((product) => productMatchesCategory(product, currentCategory))
    : routePage === 'promotions'
      ? displayProducts.filter(isPromotion)
      : displayProducts;

  async function refreshCart(): Promise<void> {
    const response = await fetch(`/api/cart${boutiqueQuery(boutique.slug)}`, { credentials: 'same-origin', headers: authHeaders() });
    if (!response.ok) return;
    const payload = await response.json() as CartResponse;
    setCart(payload.items.filter((item) => item.productId !== null).map((item) => {
      const baseProduct = displayProducts.find((product) => product.id === item.productId);
      return {
        itemId: item.id,
        qty: item.quantity,
        product: {
          ...(baseProduct ?? {
            id: item.productId as string,
            name: item.productName ?? 'Produit',
            slug: '',
            currency: payload.currency,
            images: [],
          }),
          priceCents: item.unitPriceCents,
          variantId: item.variantId ?? undefined,
          variantSku: item.variantSku ?? undefined,
          variantAttributes: item.variantAttributes ?? [],
        },
      };
    }));
  }

  useEffect(() => {
    void refreshCart();
  }, [boutique.slug, displayProducts]);

  useEffect(() => {
    if (routePage !== 'cms' || !cmsSlug) {
      setCmsPage(null);
      setCmsPageLoading(false);
      return;
    }

    let cancelled = false;
    setCmsPageLoading(true);
    fetch(`/api/pages/${encodeURIComponent(cmsSlug)}${boutiqueQuery(boutique.slug)}`, { headers: authHeaders() })
      .then((response) => response.ok ? response.json() as Promise<StoreCmsPage> : null)
      .then((page) => {
        if (!cancelled) setCmsPage(page);
      })
      .catch(() => {
        if (!cancelled) setCmsPage(null);
      })
      .finally(() => {
        if (!cancelled) setCmsPageLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [boutique.slug, cmsSlug, routePage]);

  async function handleSetQty(id: string, qty: number): Promise<void> {
    const item = cart.find((current) => cartItemKey(current) === id);
    if (!item?.itemId) return;
    if (qty <= 0) {
      await handleRemove(id);
      return;
    }
    const response = await fetch(`/api/cart/items/${item.itemId}${boutiqueQuery(boutique.slug)}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/merge-patch+json', ...(authHeaders() ?? {}) },
      body: JSON.stringify({ quantity: qty, variantId: item.product.variantId ?? null }),
    });
    if (response.ok) await refreshCart();
  }

  async function handleRemove(id: string): Promise<void> {
    const item = cart.find((current) => cartItemKey(current) === id);
    if (!item?.itemId) return;
    const response = await fetch(`/api/cart/items/${item.itemId}${boutiqueQuery(boutique.slug)}`, { method: 'DELETE', headers: authHeaders() });
    if (response.ok) await refreshCart();
  }

  const { reviews, isLoading: reviewsLoading } = useStorefrontReviews(reviewsEnabled, boutique.slug);
  const featuredReviews = reviews.slice(0, 3);
  const productNames = new Map(displayProducts.map((product) => [product.id, product.name]));

  const resolvedNavItems = resolveStorefrontNavigation(boutique, reviewsEnabled);
  const headerConfig = boutique.headerConfig ?? {};
  const footerConfig = boutique.footerConfig ?? {};
  const { title: heroTitle, subtitle: heroSubtitle, banner: activeBanner } = resolveStorefrontHero(boutique, boutique.name);
  const sectionTitle = (type: string, fallback: string): string => resolveSectionTitle(boutique, type, fallback);
  const footerText = typeof footerConfig.footer_text === 'string' ? footerConfig.footer_text : boutique.description ?? 'Retrouvez toute notre sélection et commandez en ligne.';
  const copyrightText = typeof footerConfig.copyright_text === 'string' ? footerConfig.copyright_text : `© ${new Date().getFullYear()} ${boutique.name}. Tous droits réservés.`;
  const showNewsletter = footerConfig.show_newsletter === true;

  return (
    <div
      className="sf-storefront min-h-screen bg-[color:var(--sf-bg,#f6f2eb)] text-[color:var(--sf-text,#171717)]"
      style={{
        '--sf-accent': boutique.primaryColor ?? '#111111',
        fontFamily: 'var(--ds-font-family, Inter), system-ui, sans-serif',
        fontSize: 'var(--ds-font-size, 16px)',
      } as React.CSSProperties}
    >
      <TopRibbon boutique={boutique} announcements={announcements} />

      <header className="sticky top-0 z-30 border-b border-black/10 bg-[color:var(--sf-bg,#f6f2eb)]/90 backdrop-blur-xl">
        <div className="mx-auto flex h-20 max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
          <button
            className="sf-menu-toggle rounded-full p-2 text-[#171717] lg:hidden"
            onClick={() => setMobileMenu(true)}
            aria-label="Menu"
          >
            <Menu className="h-5 w-5" />
          </button>

          <a href={boutiqueLink('/')} className="flex items-center gap-3">
            <ImageWithFallback src={boutique.logoUrl} alt={boutique.logoUrl ? boutique.name : 'Hanooti'} className="h-10 w-10 rounded-full object-cover" />
            <div className="hidden lg:block">
                <div className="text-xs uppercase tracking-[0.28em] text-black/50">{boutique.slogan || 'Boutique'}</div>
              <div className="text-lg font-semibold tracking-tight">{boutique.name}</div>
            </div>
          </a>

          <nav className="sf-desktop-nav hidden items-center gap-8 lg:ml-auto lg:flex">
             {resolvedNavItems.map((item) => (
              <a key={item.label} href={item.href} className="text-sm font-medium text-black/70 transition hover:text-black">
                {item.label}
              </a>
            ))}
          </nav>

          <div className="ml-auto flex items-center gap-2 lg:ml-auto">
             {headerConfig.show_search !== false && <button
              onClick={() => setSearchOpen(true)}
              className="rounded-full border border-black/10 p-2 text-black transition hover:bg-white"
              aria-label="Rechercher"
            >
              <Search className="h-4 w-4" />
             </button>}
             {boutique.wishlistEnabled === true && <FavoritesPopover boutiqueSlug={boutique.slug} favoriteCount={favoriteProductIds.length} onRefresh={onFavoritesRefresh} />}
             {headerConfig.show_account !== false && boutique.customerAccountsEnabled !== false && <BoutiqueAccountLink boutiqueSlug={boutique.slug} />}
             {headerConfig.show_cart !== false && <CartSheet items={cart} onSetQty={handleSetQty} onRemove={handleRemove} />}
          </div>
        </div>
      </header>

      <AnimatePresence>
        {mobileMenu && (
          <>
            <motion.div
              className="fixed inset-0 z-40 bg-black/30 backdrop-blur-sm"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setMobileMenu(false)}
            />
            <motion.div
              className="fixed inset-y-0 left-0 z-50 w-80 bg-[#f6f2eb] px-6 py-8 shadow-2xl"
              initial={{ x: '-100%' }}
              animate={{ x: 0 }}
              exit={{ x: '-100%' }}
              transition={{ type: 'spring', stiffness: 340, damping: 32 }}
            >
              <div className="mb-10 flex items-center justify-between">
                <div>
               <div className="text-xs uppercase tracking-[0.28em] text-black/50">{boutique.slogan || 'Navigation'}</div>
                  <div className="text-xl font-semibold">{boutique.name}</div>
                </div>
                <button className="rounded-full border border-black/10 p-2" onClick={() => setMobileMenu(false)}>
                  <X className="h-4 w-4" />
                </button>
              </div>
              <nav className="space-y-4">
                 {resolvedNavItems.map((item) => (
                  <a key={item.label} href={item.href} className="block text-lg font-medium text-black/80">
                    {item.label}
                  </a>
                ))}
              </nav>
            </motion.div>
          </>
        )}
      </AnimatePresence>

      <AnimatePresence>
        {searchOpen && (
          <SearchOverlay query={searchQuery} onQuery={setSearchQuery} onClose={() => setSearchOpen(false)} products={displayProducts} />
        )}
      </AnimatePresence>

        <main>
          {routePage === 'catalogue' || routePage === 'category' || routePage === 'promotions' ? (
            <ProductListing
              title={routePage === 'category' ? (currentCategory?.name ?? 'Catégorie') : routePage === 'promotions' ? 'Promotions' : 'Catalogue'}
              subtitle={routePage === 'category' ? 'Produits filtrés par catégorie et sous-catégorie.' : routePage === 'promotions' ? 'Offres limitées, remises et coups de coeur.' : 'Tous les produits disponibles dans cette boutique.'}
              products={routeProducts}
              categories={categories}
              filters={filters}
              category={currentCategory}
              promotionsOnly={routePage === 'promotions'}
              query={searchQuery}
               onQuery={setSearchQuery}
               wishlistEnabled={boutique.wishlistEnabled === true}
               reviewsEnabled={reviewsEnabled}
               viewsEnabled={boutique.viewsEnabled === true}
               favoriteProductIds={favoriteProductIds}
               onToggleFavorite={onToggleFavorite}
             />
          ) : routePage === 'about' ? (
            <AboutPage boutique={boutique} categories={categories} />
          ) : routePage === 'contact' ? (
            <ContactPage boutique={boutique} />
          ) : routePage === 'reviews' ? (
            <ReviewsPage
               products={displayProducts}
              reviewsEnabled={reviewsEnabled}
              boutique={boutique}
              sharedReviews={reviews}
               sharedReviewsLoading={reviewsLoading}
             />
          ) : routePage === 'cms' ? (
            <CmsPageView page={cmsPage} loading={cmsPageLoading} />
          ) : (
          <>
        <section className="px-4 pb-10 pt-6 sm:px-6 lg:px-8 lg:pb-16 lg:pt-8">
          <motion.div
            className="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[minmax(0,1.35fr)_430px]"
            variants={staggerContainer}
            initial="hidden"
            animate="show"
          >
             <motion.div
               variants={fadeUp}
               className="relative overflow-hidden rounded-[2rem] bg-[#111111] px-8 py-8 text-white sm:px-10 sm:py-10 lg:min-h-[620px] lg:px-14 lg:py-16"
               style={{ backgroundColor: heroColor }}
             >
               {boutique.coverImage && !coverImageFailed ? (
                 <>
                   <ImageWithFallback src={boutique.coverImage} alt="" aria-hidden="true" onError={() => setCoverImageFailed(true)} className="absolute inset-0 h-full w-full object-cover opacity-65" />
                   <div className="absolute inset-0 bg-gradient-to-br from-[#111111]/95 via-[#111111]/75 to-[#111111]/35" aria-hidden="true" />
                 </>
               ) : <div className="absolute inset-0 bg-black/20" aria-hidden="true" />}

               <div className="relative z-10">
                 <div className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-xs uppercase tracking-[0.22em] text-white/70">
                    <Sparkles className="h-3.5 w-3.5" style={{ color: BRAND }} /> {boutique.slogan || 'Boutique verifiee'}
                 </div>

                  <h1
                    className="mt-8 max-w-3xl text-4xl font-semibold uppercase leading-[0.92] tracking-[-0.05em] sm:text-6xl lg:text-7xl"
                    style={{ color: heroTextColor }}
                  >
                    {heroTitle}
                 </h1>

                 <p className="mt-6 max-w-xl text-sm leading-7 text-white/72 sm:text-base">
                    {heroSubtitle}
                 </p>

                 <div className="mt-9 flex flex-wrap gap-3">
                   <motion.a
                     whileHover={{ y: -2 }}
                     whileTap={{ scale: 0.97 }}
                     href={boutiqueLink('/catalogue')}
                      className="inline-flex w-full items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-semibold text-white transition sm:w-auto"
                     style={{ backgroundColor: BRAND }}
                   >
                     Explorer la boutique
                     <ArrowRight className="h-4 w-4" />
                   </motion.a>
                   <motion.a
                     whileHover={{ y: -2 }}
                     whileTap={{ scale: 0.97 }}
                      href={activeBanner?.button_url ? (activeBanner.button_url.startsWith('/') ? boutiqueLink(activeBanner.button_url) : activeBanner.button_url) : boutiqueLink('/promotions')}
                       className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-[color:var(--sf-accent,var(--ds-primary,#111111))] px-6 py-3 text-sm font-semibold text-[color:var(--sf-accent,var(--ds-primary,#111111))] transition hover:bg-[color:var(--sf-accent,var(--ds-primary,#111111))] hover:text-white sm:w-auto"
                   >
                      {activeBanner?.button_text ?? 'Voir les promotions'}
                   </motion.a>
                 </div>
               </div>
            </motion.div>

            <div className="grid gap-4 lg:grid-rows-[1fr_1fr_auto_auto]">
              {heroCategories.map((category, i) => (
                <motion.div key={category.name} variants={fadeUp} custom={i}>
                  <CategoryAccentCard category={category} />
                </motion.div>
              ))}

              {featuredProduct && (
                <motion.div variants={fadeUp}>
                    <FeaturedProductPanel product={featuredProduct} reviewsEnabled={reviewsEnabled} viewsEnabled={boutique.viewsEnabled === true} wishlistEnabled={boutique.wishlistEnabled === true} activeFavorite={favoriteProductIds.includes(featuredProduct.id)} onToggleFavorite={onToggleFavorite} />
                </motion.div>
              )}

              <div className="rounded-[1.6rem] border border-black/10 bg-white px-6 py-5">
                 <div className="text-3xl font-semibold tracking-[-0.05em]">{boutique.orderMode === 'CATALOG' ? 'Catalogue' : 'En ligne'}</div>
                 <div className="mt-1 text-sm text-black/60">Commande disponible</div>
              </div>

              <div className="rounded-[1.6rem] border border-black/10 bg-white px-6 py-6">
                 <div className="text-lg font-semibold">Restez informé</div>
                 <p className="mt-2 text-sm leading-6 text-black/60">Recevez les nouveautés et actualités de la boutique.</p>
                 <form className="mt-4 flex flex-col gap-2 sm:flex-row" onSubmit={(event) => event.preventDefault()}>
                  <input
                    type="email"
                    placeholder="Votre email"
                     className="min-w-0 w-full flex-1 rounded-full border border-black/10 bg-[#f8f5ef] px-4 py-3 text-sm outline-none"
                  />
                   <button type="submit" className="w-full rounded-full px-5 py-3 text-sm font-semibold text-white sm:w-auto" style={{ backgroundColor: BRAND }}>
                    Rejoindre
                  </button>
                </form>
              </div>
            </div>
          </motion.div>
        </section>

        <FeatureTicker announcements={announcements} />

        {boutique.analyticsEnabled && (
          <BoutiqueMetrics
            productsCount={boutique.productsCount}
            customersWithAccount={boutique.customersWithAccount}
            customersWithoutAccount={boutique.customersWithoutAccount}
            ordersCount={boutique.publicOrdersCount}
            customerAccountsEnabled={boutique.customerAccountsEnabled}
          />
        )}

        <section id="story" className="px-4 py-14 sm:px-6 lg:px-8">
          <div className="mx-auto max-w-7xl">
            <SectionHeading eyebrow="Explorer" title={sectionTitle('categories', 'Nos univers')} href={boutiqueLink('/catalogue')} />
            <AutoSlider itemCount={collectionCategories.length} className="mt-8">
              {collectionCategories.map((category) => (
                <motion.a
                  key={category.name}
                  whileHover={{ y: -4 }}
                  href={boutiqueLink(`/categories/${category.slug}`)}
                  className="group w-[82%] shrink-0 snap-start overflow-hidden rounded-[1.7rem] border border-black/10 bg-white sm:w-[calc((100%-1.25rem)/2)] xl:w-[calc((100%-3.75rem)/4)]"
                >
                  <div className="aspect-[0.95] overflow-hidden bg-[#e7e0d6]">
                    {category.image ? (
                      <ImageWithFallback src={category.image} alt={category.name} className="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
                    ) : (
                      <div className="flex h-full items-center justify-center text-sm text-black/45">{category.name}</div>
                    )}
                  </div>
                  <div className="flex items-center justify-between px-5 py-5">
                    <div>
                      <div className="text-sm font-semibold">{category.name}</div>
                      <div className="mt-1 text-xs text-black/50">{category.count} items</div>
                    </div>
                    <ArrowRight className="h-4 w-4 text-black/40 transition group-hover:translate-x-1 group-hover:text-black" />
                  </div>
                </motion.a>
              ))}
            </AutoSlider>
          </div>
        </section>

        <section id="catalogue" className="px-4 py-14 sm:px-6 lg:px-8">
          <div className="mx-auto max-w-7xl">
             <SectionHeading eyebrow="Catalogue" title={sectionTitle('products', 'Nouveautes')} href={boutiqueLink('/catalogue')} />
             <AutoSlider itemCount={newArrivals.length} className="mt-8">
               {newArrivals.map((product) => (
                 <div key={product.id} className="w-[82%] shrink-0 snap-start sm:w-[calc((100%-1.5rem)/2)] xl:w-[calc((100%-4.5rem)/4)]">
                   <ProductEditorialCard product={product} reviewsEnabled={reviewsEnabled} viewsEnabled={boutique.viewsEnabled === true} wishlistEnabled={boutique.wishlistEnabled === true} activeFavorite={favoriteProductIds.includes(product.id)} onToggleFavorite={onToggleFavorite} />
                 </div>
               ))}
             </AutoSlider>
          </div>
        </section>

        <section id="drops" className="px-4 pb-14 sm:px-6 lg:px-8" />

        {spotlightProducts.length > 0 && (
          <section id="spotlight" className="px-4 py-14 sm:px-6 lg:px-8">
            <div className="mx-auto max-w-7xl">
              <SectionHeading eyebrow="Offres du moment" title="Découvrez notre sélection" href={boutiqueLink('/promotions')} />
              <AutoSlider itemCount={spotlightProducts.length} className="mt-8">
                {spotlightProducts.map((product) => (
                  <div key={product.id} className="w-[82%] shrink-0 snap-start sm:w-[calc((100%-1.5rem)/2)] xl:w-[calc((100%-4.5rem)/4)]">
                    <ProductEditorialCard product={product} reviewsEnabled={reviewsEnabled} viewsEnabled={boutique.viewsEnabled === true} wishlistEnabled={boutique.wishlistEnabled === true} activeFavorite={favoriteProductIds.includes(product.id)} onToggleFavorite={onToggleFavorite} />
                  </div>
                ))}
              </AutoSlider>
            </div>
          </section>
        )}

        <section className="px-4 py-14 sm:px-6 lg:px-8">
          <div className="mx-auto max-w-7xl">
             <SectionHeading eyebrow="Selection" title={sectionTitle('best_sellers', 'Best-sellers')} href={boutiqueLink('/catalogue')} />
            <motion.div
              className="mt-8 grid gap-6 md:grid-cols-2"
              variants={staggerContainer}
              initial="hidden"
              whileInView="show"
              viewport={{ once: true, margin: '-80px' }}
            >
              {bestSellers.map((product) => (
                <motion.div key={product.id} variants={fadeUp}>
                    <ProductEditorialCard product={product} compact reviewsEnabled={reviewsEnabled} viewsEnabled={boutique.viewsEnabled === true} wishlistEnabled={boutique.wishlistEnabled === true} activeFavorite={favoriteProductIds.includes(product.id)} onToggleFavorite={onToggleFavorite} />
                </motion.div>
              ))}
            </motion.div>
          </div>
        </section>

        {reviewsEnabled && <section id="avis" className="px-4 py-14 sm:px-6 lg:px-8">
          <div className="mx-auto max-w-7xl">
            <div className="flex flex-wrap items-end justify-between gap-4">
               <div>
                <div className="text-xs uppercase tracking-[0.24em] text-black/45">Avis clients</div>
                <h2 className="mt-3 text-3xl font-semibold tracking-[-0.04em]">{sectionTitle('reviews', 'Ce que disent nos clients')}</h2>
              </div>
              <a href={boutiqueLink('/avis')} className="inline-flex items-center gap-2 text-sm font-semibold text-black/70 transition hover:text-black">
                Voir tous les avis <ArrowRight className="h-4 w-4" />
              </a>
            </div>
            <div className="mt-8 grid gap-5 md:grid-cols-3">
              {reviewsLoading ? <ReviewLoadingCard /> : featuredReviews.length > 0 ? featuredReviews.map((review) => <StorefrontReviewCard key={review.id} review={review} productName={review.productId ? productNames.get(review.productId) : undefined} />) : <EmptyReviewCard />}
            </div>
          </div>
        </section>}

        <section id="contact" className="px-4 py-10 sm:px-6 lg:px-8">
          <div className="mx-auto grid max-w-7xl gap-4 md:grid-cols-2 xl:grid-cols-4">
             <ServiceCard icon={<Truck className="h-5 w-5" />} title="Livraison" description="Des options adaptées à votre commande" />
             <ServiceCard icon={<ArrowRight className="h-5 w-5" />} title="Retours" description="Une politique claire pour vos achats" />
             <ServiceCard icon={<ShieldCheck className="h-5 w-5" />} title="Paiement sécurisé" description="Des moyens de paiement protégés" />
             <ServiceCard icon={<Mail className="h-5 w-5" />} title="Support" description={boutique.email || 'Une équipe dédiée'} />
          </div>
        </section>
          </>
          )}
        </main>

      <footer className="mt-10 bg-[#111111] text-white">
        <div className="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1.3fr_repeat(3,minmax(0,1fr))] lg:px-8">
          <div>
            <div className="flex items-center gap-3">
              <ImageWithFallback src={boutique.logoUrl} alt={boutique.logoUrl ? boutique.name : 'Hanooti'} className="h-11 w-11 rounded-full object-cover" />
              <div>
                <div className="text-lg font-semibold">{boutique.name}</div>
                <div className="text-sm text-white/45">Boutique editoriale</div>
              </div>
            </div>
             <p className="mt-5 max-w-sm text-sm leading-7 text-white/62">
               {footerText}
             </p>
          </div>

          <FooterLinks title="Boutique" links={[
            { label: 'Catalogue', href: boutiqueLink('/catalogue') },
            { label: 'Nouveautes', href: boutiqueLink('/catalogue') },
            { label: 'Promotions', href: boutiqueLink('/promotions') },
          ]} />

           <FooterLinks title="Aide" links={[
             { label: 'Contact', href: boutiqueLink('/contact') },
             { label: 'CGV', href: boutiqueLink('') },
             { label: 'Livraison & retours', href: boutiqueLink('/contact') },
           ]} />

           <div>
             <div className="text-sm font-semibold">Votre commande</div>
             <p className="mt-3 text-sm leading-6 text-white/55">Retrouvez le statut réel de votre commande avec sa référence.</p>
               <a
                 href="#order-tracking"
                 onClick={(event) => { event.preventDefault(); setOrderTrackingOpen(true); }}
                 className="mt-4 inline-flex w-full items-center justify-center rounded-full border px-5 py-3 text-sm font-semibold transition hover:brightness-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80 focus-visible:ring-offset-2 focus-visible:ring-offset-[#111111]"
                 style={{ backgroundColor: BRAND, borderColor: BRAND, color: 'var(--ds-on-primary, #ffffff)' }}
               >
                Consulter votre commande
              </a>
           </div>

            {showNewsletter && <div>
             <div className="text-sm font-semibold">Newsletter</div>
             <p className="mt-3 text-sm text-white/55">Recevez nos nouveautés et offres.</p>
            <form className="mt-4 flex gap-2" onSubmit={(event) => event.preventDefault()}>
              <input
                type="email"
                placeholder="Votre email"
                className="min-w-0 flex-1 rounded-full border border-white/12 bg-white/7 px-4 py-3 text-sm text-white outline-none placeholder:text-white/35"
              />
              <button type="submit" className="rounded-full bg-white px-5 py-3 text-sm font-semibold text-[#111111]">
                OK
              </button>
            </form>
           </div>}
        </div>
        <div className="border-t border-white/10">
          <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-xs text-white/45 sm:px-6 lg:px-8">
             <span>{copyrightText}</span>
            <span>{boutique.address || 'Tunisie'}</span>
         </div>
       </div>
      </footer>
      {orderTrackingOpen && <OrderTrackingModal boutiqueSlug={boutique.slug} onClose={() => setOrderTrackingOpen(false)} />}
    </div>
  );
}

function TopRibbon({ boutique, announcements }: { boutique: StoreBoutique; announcements: StoreAnnouncement[] }) {
  const announcement = announcements
    .filter((item) => item.active !== false && item.visible !== false)
    .filter((item) => (item.categoryIds ?? []).length === 0 && (item.productIds ?? []).length === 0)
    .filter((item) => {
      const pages = item.displayPages ?? [];
      return pages.length === 0 || pages.includes('all') || pages.includes('home');
    })
    .filter((item) => item.displayType === 'TOP_BAR' || ['HEADER_TOP', 'TOP_PAGE'].includes(item.position ?? ''))
    .sort((left, right) => (right.priority ?? 0) - (left.priority ?? 0))[0];

  if (!announcement) return null;

  const label = announcement.title?.trim() || announcement.content.trim();
  const link = announcement.linkUrl?.trim();

  return (
    <div className="border-b border-black/8 text-[#171717]" style={{ backgroundColor: announcement.backgroundColor ?? '#ece5d9', color: announcement.textColor ?? '#171717', borderColor: announcement.borderColor ?? undefined }}>
      <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-2 text-xs sm:px-6 lg:px-8">
        <div className="font-medium">
          {link ? <a href={link.startsWith('/') ? boutiqueLink(link) : link} className="hover:underline">{label}</a> : label}
        </div>
        <div className="flex items-center gap-4 opacity-70">
          {boutique.email && (
            <a href={`mailto:${boutique.email}`} className="inline-flex items-center gap-2 hover:text-black">
              <Mail className="h-3.5 w-3.5" />
              {boutique.email}
            </a>
          )}
          {announcement.subtitle && <span className="hidden sm:inline">{announcement.subtitle}</span>}
        </div>
      </div>
    </div>
  );
}

function ReviewLoadingCard() {
  return <div className="h-48 animate-pulse rounded-[1.5rem] border border-black/10 bg-white/70 md:col-span-3" aria-label="Chargement des avis" />;
}

function EmptyReviewCard() {
  return <div className="rounded-[1.5rem] border border-black/10 bg-white p-8 text-center text-sm text-black/55 md:col-span-3">Aucun avis boutique publié pour le moment.</div>;
}

function StorefrontReviewCard({ review, productName }: { review: StoreReview; productName?: string }) {
  return (
    <article className="rounded-[1.5rem] border border-black/10 bg-white p-6 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-3"><span className="grid h-10 w-10 place-items-center rounded-full bg-black/5 text-sm font-bold">{storeReviewInitial(review.authorName)}</span><div><div className="text-sm font-semibold">{review.authorName}</div><div className="text-xs text-black/45">{productName ? `Avis produit · ${productName}` : 'Avis boutique'}</div></div></div>
        <time className="text-xs text-black/45" dateTime={review.createdAt}>{formatStoreReviewDate(review.createdAt)}</time>
      </div>
      <div className="mt-4 flex items-center gap-1 text-amber-500" aria-label={`${review.rating} étoiles sur 5`}>{[1, 2, 3, 4, 5].map((star) => <Star key={star} className={`h-4 w-4 ${star <= review.rating ? 'fill-current' : ''}`} />)}</div>
      <p className="mt-4 text-sm leading-7 text-black/60">{review.comment ?? 'Avis noté par un client.'}</p>
    </article>
  );
}

function CategoryAccentCard({ category }: { category: StoreCategory }) {
  return (
    <div className="overflow-hidden rounded-[1.6rem] border border-black/10 bg-white">
      <div className="grid grid-cols-[120px_minmax(0,1fr)] items-stretch">
        <div className="aspect-square overflow-hidden bg-[#ddd4c6]">
          {category.image ? (
            <ImageWithFallback src={category.image} alt={category.name} className="h-full w-full object-cover" />
          ) : (
            <div className="flex h-full items-center justify-center text-sm text-black/45">{category.name}</div>
          )}
        </div>
        <div className="flex flex-col justify-center px-5 py-4">
          <div className="text-lg font-semibold">{category.name}</div>
          <div className="mt-1 text-sm text-black/55">{category.count} items</div>
        </div>
      </div>
    </div>
  );
}

function FeaturedProductPanel({ product, reviewsEnabled, viewsEnabled, wishlistEnabled, activeFavorite, onToggleFavorite }: { product: StoreProduct; reviewsEnabled: boolean; viewsEnabled: boolean; wishlistEnabled: boolean; activeFavorite: boolean; onToggleFavorite: (productId: string) => void }) {
  return (
    <div className="overflow-hidden rounded-[1.6rem] border border-black/10 bg-white">
      <div className="aspect-[1.15] overflow-hidden bg-[#e6ddcf]">
        {getProductImage(product) ? (
                 <ImageWithFallback src={getProductImage(product)} alt={product.name} className="h-full w-full object-cover" />
        ) : (
          <div className="flex h-full items-center justify-center text-sm text-black/45">Produit</div>
        )}
      </div>
      <div className="px-6 py-5">
        <div className="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-white" style={{ backgroundColor: BRAND }}>
          Featured
        </div>
        <div className="mt-3 text-xl font-semibold tracking-tight">{product.name}</div>
        <p className="mt-2 text-sm leading-6 text-black/58">{product.description || 'Piece phare de la collection, selectionnee pour incarner le template de reference.'}</p>
        <div className="mt-4 space-y-3">
          <div className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
             {viewsEnabled && <span className="text-xs text-black/50">{product.viewsCount ?? 0} vues</span>}
            <div className="text-lg font-semibold">{formatPrice(product)}</div>
            {reviewsEnabled && <span className="text-xs text-black/50">{product.reviewsCount ?? 0} avis</span>}
            {reviewsEnabled && product.rating != null && <span className="text-xs text-black/50">Note {product.rating.toFixed(1)}/5</span>}
            {wishlistEnabled && <span className="text-xs text-black/50">{product.favoritesCount ?? 0} favoris</span>}
          </div>
          <div className="flex items-center gap-2">
              <a
                href={boutiqueLink(`/products/${product.slug}`)}
                className="sf-primary-action inline-flex min-h-10 flex-1 items-center justify-center rounded-full px-4 py-2 text-sm font-semibold transition-opacity hover:opacity-90"
                style={{ backgroundColor: 'var(--ds-primary)' }}
            >
              Voir le produit
            </a>
            {wishlistEnabled && <FavoriteButton productId={product.id} active={activeFavorite} onToggle={onToggleFavorite} />}
          </div>
        </div>
      </div>
    </div>
  );
}

function FeatureTicker({ announcements }: { announcements: StoreAnnouncement[] }) {
  const announcementItems = announcements
    .filter((announcement) => announcement.displayType === 'HOME_SLIDER' || ['HOME_TOP', 'HOME_MIDDLE', 'HOME_BOTTOM'].includes(announcement.position ?? ''))
    .map((announcement) => ({
    id: announcement.id,
    label: announcement.title?.trim() || announcement.content.trim(),
    subtitle: announcement.subtitle?.trim(),
    backgroundColor: announcement.backgroundColor ?? undefined,
    textColor: announcement.textColor ?? undefined,
    borderColor: announcement.borderColor ?? undefined,
    linkUrl: announcement.linkUrl ?? undefined,
    displayMode: announcement.displayMode ?? 'SLIDER',
  })).filter((announcement) => announcement.label);
  const groups = Array.from({ length: Math.ceil(announcementItems.length / 3) }, (_, index) => announcementItems.slice(index * 3, index * 3 + 3));
  const shouldRotate = announcementItems.some((item) => item.displayMode !== 'FIXED') && groups.length > 1;
  const [groupIndex, setGroupIndex] = useState(0);

  useEffect(() => {
    if (!shouldRotate) return undefined;
    const interval = window.setInterval(() => setGroupIndex((current) => (current + 1) % groups.length), 6000);

    return () => window.clearInterval(interval);
  }, [groups.length, shouldRotate]);

  if (groups.length === 0) return null;

  const visibleItems = groups[groupIndex % groups.length];

  return (
    <section className="border-y border-black/10 bg-white py-4" aria-label="Annonces de la boutique">
      <motion.div
        key={groupIndex}
        className="mx-auto flex max-w-7xl flex-wrap justify-center gap-3 px-4 text-sm text-black/55"
        initial={{ opacity: 0, y: 6 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.25 }}
      >
        {visibleItems.map((item) => {
          const content = (
            <span
              className="inline-flex max-w-full items-center rounded-full border border-transparent px-4 py-2 text-center"
              style={{
                backgroundColor: item.backgroundColor,
                color: item.textColor,
                borderColor: item.borderColor,
              }}
            >
              {item.label}
              {item.subtitle && <span className="ml-2 opacity-70">{item.subtitle}</span>}
            </span>
          );

          return item.linkUrl ? (
            <a key={item.id} href={item.linkUrl} className="inline-flex max-w-full" target={item.linkUrl.startsWith('http') ? '_blank' : undefined} rel={item.linkUrl.startsWith('http') ? 'noreferrer' : undefined}>
              {content}
            </a>
          ) : (
            <span key={item.id} className="inline-flex max-w-full">{content}</span>
          );
        })}
      </motion.div>
    </section>
  );
}

function SectionHeading({ eyebrow, title, href }: { eyebrow: string; title: string; href: string }) {
  return (
    <div className="flex flex-wrap items-end justify-between gap-4">
      <div>
        <div className="text-xs uppercase tracking-[0.24em] text-black/45">{eyebrow}</div>
        <h2 className="mt-3 text-3xl font-semibold tracking-[-0.04em]">{title}</h2>
      </div>
      <a href={href} className="inline-flex items-center gap-2 text-sm font-semibold text-black/70 transition hover:text-black">
        Tout voir
        <ArrowRight className="h-4 w-4" />
      </a>
    </div>
  );
}

function ProductEditorialCard({
  product,
  compact = false,
  reviewsEnabled,
  viewsEnabled,
  wishlistEnabled,
  activeFavorite,
  onToggleFavorite,
}: {
  product: StoreProduct;
  compact?: boolean;
  reviewsEnabled: boolean;
  viewsEnabled: boolean;
  wishlistEnabled: boolean;
  activeFavorite: boolean;
  onToggleFavorite: (productId: string) => void;
}) {
  const badge = resolveBadge(product);
  const image = getProductImage(product);

  return (
    <motion.div
      whileHover={{ y: -6 }}
      transition={{ type: 'spring', stiffness: 300, damping: 24 }}
      className="group overflow-hidden rounded-[1.7rem] border border-black/10 bg-white"
    >
      <div className={`relative overflow-hidden bg-[#e7e0d6] ${compact ? 'aspect-[1.2]' : 'aspect-[0.95]'}`}>
        <a href={boutiqueLink(`/products/${product.slug}`)} className="block h-full">
          {image ? (
             <ImageWithFallback src={image} alt={product.name} className="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
          ) : (
            <div className="flex h-full items-center justify-center text-sm text-black/45">Produit</div>
          )}
          <div
            className="absolute left-4 top-4 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] shadow-sm"
            style={badge === 'Promo' ? { backgroundColor: '#dc2626', color: '#fff' } : { backgroundColor: BRAND, color: '#fff' }}
          >
            {badge}
          </div>
        </a>
          <a
            href={boutiqueLink(`/products/${product.slug}`)}
            aria-label={`Voir le produit ${product.name}`}
            className="sf-primary-action absolute bottom-4 right-4 rounded-full px-4 py-2 text-sm font-semibold opacity-100 transition hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
            style={{ backgroundColor: 'var(--ds-primary)' }}
        >
          Voir le produit
        </a>
      </div>
      <div className="px-5 py-5">
        <div className="text-xs uppercase tracking-[0.2em] text-black/45">{product.categoryName || 'Collection'}</div>
        <a href={boutiqueLink(`/products/${product.slug}`)} className="mt-2 block text-lg font-semibold tracking-tight text-[#171717]">
          {product.name}
        </a>
        <div className="mt-3 flex items-center justify-between gap-3 text-sm text-black/55">
          <div className="flex items-center gap-3 text-xs">
             {viewsEnabled && <span>{product.viewsCount ?? 0} vues</span>}
            {reviewsEnabled && <span>★ {product.reviewsCount ?? 0} avis</span>}
            {reviewsEnabled && product.rating != null && <span>Note {product.rating.toFixed(1)}/5</span>}
            {wishlistEnabled && <span>♡ {product.favoritesCount ?? 0} favoris</span>}
          </div>
          {wishlistEnabled && <FavoriteButton productId={product.id} active={activeFavorite} onToggle={onToggleFavorite} />}
        </div>
        <div className="mt-2 flex flex-wrap items-center gap-3 text-sm">
          <span className="font-semibold text-[#171717]">{formatPrice(product)}</span>
          {product.comparePriceCents && product.comparePriceCents > product.priceCents && (
            <span className="text-black/55 line-through">{(product.comparePriceCents / 100).toFixed(2)} {product.currency}</span>
          )}
        </div>
      </div>
    </motion.div>
  );
}

function ServiceCard({ icon, title, description }: { icon: ReactNode; title: string; description: string }) {
  return (
    <div className="rounded-[1.5rem] border border-black/10 bg-white px-6 py-5">
      <div className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-[#f1ebe1] text-[#111111]">{icon}</div>
      <div className="mt-4 text-base font-semibold">{title}</div>
      <div className="mt-1 text-sm text-black/55">{description}</div>
    </div>
  );
}

function FooterLinks({ title, links }: { title: string; links: Array<{ label: string; href: string }> }) {
  return (
    <div>
      <div className="text-sm font-semibold">{title}</div>
      <ul className="mt-4 space-y-3 text-sm text-white/55">
        {links.map((link) => (
          <li key={link.label}>
            <a href={link.href} className="transition hover:text-white">
              {link.label}
            </a>
          </li>
        ))}
      </ul>
    </div>
  );
}

function SearchOverlay({
  query,
  onQuery,
  onClose,
  products = [],
}: {
  query: string;
  onQuery: (value: string) => void;
  onClose: () => void;
  products?: StoreProduct[];
}) {
  const normalized = query.trim().toLowerCase();
  const results = useMemo(
    () => normalized
      ? products.filter((product) =>
          [product.name, product.categoryName, product.description]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(normalized))
        )
      : [],
    [normalized, products],
  );

  return (
    <motion.div
      className="fixed inset-0 z-50 overflow-y-auto bg-[#111111]/40 backdrop-blur-md"
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      onClick={onClose}
    >
      <motion.div
        className="mx-auto mt-12 max-w-3xl rounded-[2rem] bg-[#f6f2eb] p-6 shadow-2xl sm:mt-20"
        initial={{ opacity: 0, y: -16, scale: 0.98 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        exit={{ opacity: 0, y: -10, scale: 0.98 }}
        transition={{ type: 'spring', stiffness: 360, damping: 30 }}
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-center gap-4 rounded-full border border-black/10 bg-white px-5 py-4">
          <Search className="h-5 w-5 text-black/45" />
          <input
            autoFocus
            value={query}
            onChange={(event) => onQuery(event.target.value)}
            placeholder="Rechercher un produit..."
            className="min-w-0 flex-1 bg-transparent text-base outline-none placeholder:text-black/35"
          />
          <button onClick={onClose} className="rounded-full border border-black/10 p-2 text-black/60">
            <X className="h-4 w-4" />
          </button>
        </div>

        {normalized && (
          <div className="mt-4 max-h-[50vh] overflow-y-auto">
            {results.length === 0 ? (
              <p className="py-8 text-center text-sm text-black/45">Aucun produit trouvé pour "{query}"</p>
            ) : (
              <div className="grid gap-3">
                {results.slice(0, 12).map((product) => (
                  <a
                    key={product.id}
                    href={boutiqueLink(`/products/${product.slug}`)}
                    onClick={onClose}
                    className="flex items-center gap-4 rounded-2xl bg-white p-3 transition hover:bg-black/5"
                  >
                    <ImageWithFallback
                      src={getProductImage(product)}
                      alt=""
                      className="h-14 w-14 shrink-0 rounded-xl object-cover"
                    />
                    <div className="min-w-0 flex-1">
                      <div className="text-sm font-bold text-black truncate">{product.name}</div>
                      <div className="mt-1 text-xs text-black/45">{product.categoryName}</div>
                      <div className="mt-1 text-sm font-bold text-black">
                        {(product.priceCents / 100).toFixed(2)} {product.currency}
                      </div>
                    </div>
                  </a>
                ))}
                {results.length > 12 && (
                  <p className="text-center text-xs text-black/45">{results.length - 12} autres résultats...</p>
                )}
              </div>
            )}
          </div>
        )}
      </motion.div>
    </motion.div>
  );
}

function getProductImage(product: StoreProduct): string {
  const firstImage = product.images?.find((image) => typeof image !== 'string' && image.isDefault) ?? product.images?.[0];
  if (!firstImage) {
    return '';
  }

  return typeof firstImage === 'string' ? firstImage : firstImage.largeUrl ?? firstImage.url ?? '';
}

function resolveBadge(product: StoreProduct): string {
  if (product.isPromoted) {
    return 'Promo';
  }

  if (product.badge) {
    return product.badge;
  }

  if ((product.rating ?? 0) >= 4.7) {
    return 'Best-seller';
  }

  return 'Nouveau';
}

function formatPrice(product: StoreProduct): string {
  return `${(product.priceCents / 100).toFixed(2)} ${product.currency}`;
}

function buildFallbackCategories(products: StoreProduct[]): StoreCategory[] {
  return buildCategories(products).slice(0, 5);
}
