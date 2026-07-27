import { useEffect, useState } from 'react';
import { ChatBox } from '../../chat/ChatBox';
import { StorefrontTheme, type StoreBoutique, type StoreProduct } from './storefront/StorefrontTheme';
import { applyStorefrontTheme, resetStorefrontTheme } from '../../theme/storefrontThemeRoot';
import { getStorefrontThemePreset } from '../../theme/themes';
import { authHeaders, boutiqueQuery, isBoutiqueSubdomain, resolveBoutiqueSlug } from './boutiqueRouting';
import { frontOfficeUrl } from '../../backoffice/utils/frontOfficeUrl';
import type { StoreCategory, StoreFilter } from './storefront/catalogueTypes';

type StorefrontBoutiqueResponse = {
  id?: string;
  name?: string;
  slug?: string;
  status?: string;
  isPublished?: boolean;
  isVisiblePublicly?: boolean;
  customDomain?: string | null;
  logoUrl?: string | null;
  description?: string | null;
  coverImage?: string | null;
  backgroundColor?: string | null;
  contactEmail?: string | null;
  address?: string | null;
  primaryColor?: string | null;
  colorPalette?: Record<string, string> | null;
  iconSet?: Record<string, never>;
  featuredCategories?: Array<{ categoryId?: string; label?: string; position?: number }>;
  frontOfficePages?: Array<{ slug?: string; label?: string; enabled?: boolean; position?: number }>;
  navigationItems?: Array<{ label?: string; href?: string; position?: number; enabled?: boolean }>;
  homepageSections?: Array<{ type: string; enabled: boolean; position?: number; title?: string }>;
  banners?: Array<{ image?: string; mobile_image?: string; title?: string; subtitle?: string; button_text?: string; button_url?: string; active?: boolean; position?: number }>;
  catalogConfig?: Record<string, unknown>;
  moduleConfig?: Record<string, unknown>;
  orderMode?: string | null;
  socialLinks?: Record<string, string>;
  slogan?: string | null;
  favicon?: string | null;
  maintenanceMessage?: string | null;
  headerConfig?: Record<string, boolean>;
  footerConfig?: Record<string, string | boolean>;
  theme?: string | null;
  fontFamily?: string | null;
  fontSize?: string | null;
  borderRadius?: string | null;
  reviewsEnabled?: boolean;
  wishlistEnabled?: boolean;
  analyticsEnabled?: boolean;
  viewsEnabled?: boolean;
  customerAccountsEnabled?: boolean;
  customersWithAccount?: number;
  customersWithoutAccount?: number;
  publicOrdersCount?: number;
};

type ProductResponse = {
  id?: string;
  name?: string;
  slug?: string;
  sellingPrice?: number;
  priceCents?: number;
  comparePrice?: number | null;
  comparePriceCents?: number | null;
  currency?: string;
  shortDescription?: string | null;
  description?: string | null;
  images?: StoreProduct['images'];
  categoryName?: string | null;
  categorySlug?: string | null;
  categoryId?: string | null;
  categoryIds?: string[];
  brandName?: string | null;
  filterValues?: Array<{ filterId: string; filterName: string; filterSlug: string; value: string }>;
  variants?: StoreProduct['variants'];
  stockQuantity?: number;
  badge?: string | null;
  rating?: number;
  reviewsCount?: number;
  favoritesCount?: number;
  viewsCount?: number;
  createdAt?: string | null;
};

type CategoryResponse = {
  id?: string;
  name?: string;
  slug?: string;
  parentId?: string | null;
  productsCount?: number;
  image?: string | null;
  banner?: string | null;
  description?: string | null;
  children?: Array<{ id?: string; name?: string; slug?: string; productsCount?: number }>;
};

type FilterResponse = {
  id?: string;
  name?: string;
  slug?: string;
  type?: string;
  position?: number;
  values?: Array<{ id?: string; value?: string }>;
};

type CollectionResponse<T> = { member?: T[]; items?: T[] } | T[];

export function StorefrontPage({ title, description }: { title: string; description: string }) {
  const boutiqueSlug = resolveBoutiqueSlug(/^\/boutiques\/([^/]+)/);
  const [boutique, setBoutique] = useState<StoreBoutique | null>(null);
  const [products, setProducts] = useState<StoreProduct[]>([]);
  const [categories, setCategories] = useState<StoreCategory[]>([]);
  const [filters, setFilters] = useState<StoreFilter[]>([]);
  const [favoriteProductIds, setFavoriteProductIds] = useState<string[]>([]);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    if (!boutiqueSlug) return;
    resetStorefrontTheme();
    setLoaded(false);
    const headers = authHeaders();

    fetch(`/api/boutiques/${boutiqueSlug}`, { headers })
      .then((r) => r.ok ? r.json() : null)
      .then((data: StorefrontBoutiqueResponse | null) => {
        if (!data) return;

        if (!isBoutiqueSubdomain() && window.location.pathname.startsWith('/boutiques/')) {
          const pathSuffix = window.location.pathname.replace(/^\/boutiques\/[^/]+/, '') || '/';
          const canonicalUrl = frontOfficeUrl({
            slug: data.slug ?? boutiqueSlug,
            status: data.status,
            customDomain: data.customDomain,
           });
           const targetUrl = '/' === pathSuffix ? canonicalUrl : `${canonicalUrl}${pathSuffix}`;
           if (new URL(targetUrl, window.location.href).href !== window.location.href) {
             window.location.replace(targetUrl);
             return;
           }
        }

         const themeCode = data.theme ?? 'hanooti-marketplace';
         const preset = getStorefrontThemePreset(themeCode);
         const themeData = data.theme || !preset
           ? { ...data, theme: themeCode }
           : { ...data, theme: themeCode, colorPalette: preset.colorPalette };

         applyStorefrontTheme(themeData);
         setBoutique({
           id: data.id ?? '',
           name: data.name ?? '',
           slug: data.slug ?? boutiqueSlug,
            logoUrl: themeData.logoUrl ?? null,
            description: themeData.description ?? `${title} — ${description}`,
            coverImage: themeData.coverImage ?? null,
             backgroundColor: themeData.backgroundColor ?? themeData.colorPalette?.background ?? null,
            colorPalette: themeData.colorPalette ?? null,
            email: themeData.contactEmail ?? null,
           address: themeData.address ?? null,
           primaryColor: themeData.primaryColor ?? undefined,
             heroTitle: data.slogan ?? title,
             heroSubtitle: data.description ?? description,
             theme: themeCode,
             fontFamily: themeData.fontFamily ?? null,
             fontSize: themeData.fontSize ?? null,
             borderRadius: themeData.borderRadius ?? preset?.borderRadius ?? null,
             slogan: themeData.slogan ?? null,
             favicon: themeData.favicon ?? null,
             socialLinks: themeData.socialLinks ?? {},
             headerConfig: themeData.headerConfig ?? {},
             footerConfig: themeData.footerConfig ?? {},
             navigationItems: themeData.navigationItems ?? [],
             frontOfficePages: themeData.frontOfficePages ?? [],
             featuredCategories: themeData.featuredCategories ?? [],
             homepageSections: themeData.homepageSections ?? [],
             banners: themeData.banners ?? [],
             catalogConfig: themeData.catalogConfig ?? {},
             moduleConfig: themeData.moduleConfig ?? {},
             orderMode: themeData.orderMode ?? null,
             maintenanceMessage: themeData.maintenanceMessage ?? null,
            reviewsEnabled: data.reviewsEnabled === true,
            wishlistEnabled: data.wishlistEnabled === true,
            analyticsEnabled: data.analyticsEnabled === true,
            viewsEnabled: data.viewsEnabled === true,
           customerAccountsEnabled: data.customerAccountsEnabled !== false,
           customersWithAccount: data.customersWithAccount ?? 0,
           customersWithoutAccount: data.customersWithoutAccount ?? 0,
           publicOrdersCount: data.publicOrdersCount ?? 0,
         });
         if (data.wishlistEnabled === true) {
           fetch('/api/favorites/products', { headers, credentials: 'same-origin' })
             .then((response) => response.ok ? response.json() : [])
              .then((payload: Array<{ productId?: string }> | { member?: Array<{ productId?: string }>; items?: Array<{ productId?: string }>; 'hydra:member'?: Array<{ productId?: string }> }) => {
                const favorites = Array.isArray(payload) ? payload : payload.member ?? payload.items ?? payload['hydra:member'] ?? [];
               setFavoriteProductIds(favorites.map((favorite) => favorite.productId).filter((id): id is string => Boolean(id)));
             })
             .catch(() => setFavoriteProductIds([]));
         } else {
           setFavoriteProductIds([]);
         }
      })
      .catch(() => {})
      .finally(() => setLoaded(true));

    fetch(`/api/products${boutiqueQuery(boutiqueSlug)}`, { headers })
      .then((r) => r.ok ? r.json() : [])
      .then((data: CollectionResponse<ProductResponse>) => {
        const items = Array.isArray(data) ? data : data.member ?? [];
        setProducts(Array.isArray(items) ? items.map(mapProduct) : []);
      })
      .catch(() => {});

    fetch(`/api/categories${boutiqueQuery(boutiqueSlug)}`, { headers })
      .then((r) => r.ok ? r.json() : [])
      .then((data: CollectionResponse<CategoryResponse>) => {
        const items = collectionItems(data);
        setCategories(buildCategoryTree(items.map(mapCategory)));
      })
      .catch(() => {});

    fetch(`/api/filters${boutiqueQuery(boutiqueSlug)}`, { headers })
      .then((r) => r.ok ? r.json() : [])
      .then((data: CollectionResponse<FilterResponse>) => {
        const items = collectionItems(data);
        setFilters(deduplicateFilters(items.map(mapFilter).filter((filter): filter is StoreFilter => Boolean(filter))));
      })
      .catch(() => {});
    return resetStorefrontTheme;
  }, [boutiqueSlug]);

  if (!boutique && !loaded) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[color:var(--ds-surface)]">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-[color:var(--ds-primary)] border-t-transparent" />
      </div>
    );
  }

  if (!boutique) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[color:var(--ds-surface)] px-4 text-center">
        <div>
          <h1 className="text-2xl font-bold text-[color:var(--ds-on-surface)]">Boutique non publiée</h1>
          <p className="mt-2 text-sm text-[color:var(--ds-on-surface-variant)]">Cette boutique n'est pas encore accessible au public.</p>
        </div>
      </div>
    );
  }

  async function toggleFavorite(productId: string): Promise<void> {
    if (!boutique?.wishlistEnabled) return;

    const isFavorite = favoriteProductIds.includes(productId);
    const response = await fetch(`/api/favorites/products/${productId}`, {
      method: isFavorite ? 'DELETE' : 'POST',
      credentials: 'same-origin',
      headers: authHeaders(),
    });

    if (!response.ok) return;

     setFavoriteProductIds((current) => isFavorite ? current.filter((id) => id !== productId) : [...current, productId]);
     setProducts((current) => current.map((product) => product.id === productId
       ? { ...product, favoritesCount: Math.max(0, (product.favoritesCount ?? 0) + (isFavorite ? -1 : 1)) }
       : product));
  }

  return (
    <>
      <StorefrontTheme boutique={boutique} products={products} categories={categories} filters={filters} reviewsEnabled={boutique.reviewsEnabled === true} favoriteProductIds={favoriteProductIds} onToggleFavorite={(id) => { void toggleFavorite(id); }} />
      {boutique && localStorage.getItem(`hanooti_chat_enabled_${boutique.slug}`) !== 'false' && localStorage.getItem('hanooti_boutique_chat_enabled') !== 'false' && (
        <ChatBox boutiqueId={boutique.id} apiBaseUrl="/api" primaryColor={boutique.primaryColor} />
      )}
    </>
  );
}

function mapProduct(item: ProductResponse): StoreProduct {
  return {
    id: item.id ?? '',
    name: item.name ?? '',
    slug: item.slug ?? '',
    priceCents: item.sellingPrice ?? item.priceCents ?? 0,
    comparePriceCents: item.comparePrice ?? item.comparePriceCents ?? null,
    currency: item.currency ?? 'TND',
    description: item.shortDescription ?? item.description ?? null,
    images: item.images ?? [],
    categoryName: item.categoryName ?? null,
    categorySlug: item.categorySlug ?? null,
    categoryId: item.categoryId ?? null,
    categoryIds: item.categoryIds ?? [],
    brandName: item.brandName ?? null,
    filterValues: item.filterValues ?? [],
    variants: item.variants ?? [],
    stockQuantity: item.stockQuantity,
    badge: item.badge ?? null,
    rating: item.rating,
    reviewsCount: item.reviewsCount,
    favoritesCount: item.favoritesCount,
     createdAt: item.createdAt ?? null,
     viewsCount: item.viewsCount ?? 0,
   };
}

function collectionItems<T>(data: CollectionResponse<T>): T[] {
  if (Array.isArray(data)) return data;
  return data.member ?? data.items ?? [];
}

function mapCategory(item: CategoryResponse): StoreCategory | null {
  if (!item.id || !item.name || !item.slug) return null;

  return {
    id: item.id,
    name: item.name,
    slug: item.slug,
    parentId: item.parentId ?? null,
    count: item.productsCount ?? 0,
    image: item.image ?? '',
    banner: item.banner ?? null,
    description: item.description ?? null,
    children: (item.children ?? [])
      .filter((child) => child.id && child.name && child.slug)
      .map((child) => ({
        id: child.id as string,
        name: child.name as string,
        slug: child.slug as string,
        parentId: item.id as string,
        count: child.productsCount ?? 0,
        image: '',
        children: [],
      })),
  };
}

function buildCategoryTree(items: Array<StoreCategory | null>): StoreCategory[] {
  const byId = new Map<string, StoreCategory>();

  items.filter((item): item is StoreCategory => Boolean(item)).forEach((category) => {
    const current = byId.get(category.id);
    byId.set(category.id, {
      ...(current ?? category),
      ...category,
      children: [],
    });

    category.children.forEach((child) => {
      if (!byId.has(child.id)) byId.set(child.id, { ...child, children: [] });
    });
  });

  const roots: StoreCategory[] = [];
  byId.forEach((category) => {
    if (category.parentId && byId.has(category.parentId)) {
      byId.get(category.parentId)?.children.push(category);
    } else {
      roots.push(category);
    }
  });

  const sortTree = (categories: StoreCategory[]): void => {
    categories.sort((a, b) => a.name.localeCompare(b.name, 'fr'));
    categories.forEach((category) => sortTree(category.children));
  };

  sortTree(roots);
  return roots;
}

function mapFilter(item: FilterResponse): StoreFilter | null {
  if (!item.id || !item.name || !item.slug) return null;

  return {
    id: item.id,
    name: item.name,
    slug: item.slug,
    type: item.type ?? 'select',
    position: item.position ?? 0,
    values: Array.from(new Map(
      (item.values ?? [])
        .filter((value) => value.id && value.value)
        .map((value) => [value.value!.trim().toLocaleLowerCase('fr'), { id: value.id as string, value: value.value!.trim() }]),
    ).values()),
  };
}

function deduplicateFilters(filters: StoreFilter[]): StoreFilter[] {
  const unique = new Map<string, StoreFilter>();

  filters.forEach((filter) => {
    const key = filter.id || filter.slug;
    const current = unique.get(key);
    if (!current) {
      unique.set(key, filter);
      return;
    }

    const values = new Map(current.values.map((value) => [value.value.toLocaleLowerCase('fr'), value]));
    filter.values.forEach((value) => values.set(value.value.toLocaleLowerCase('fr'), value));
    unique.set(key, { ...current, values: Array.from(values.values()) });
  });

  return Array.from(unique.values()).sort((left, right) => left.position - right.position);
}
