import { useEffect, useMemo, useState } from 'react';
import { Menu, Search } from 'lucide-react';
import { CartSheet } from './CartSheet';
import { FavoritesPopover } from './FavoritesPopover';
import { BoutiqueAccountLink } from '../BoutiqueCustomerAccount';
import type { StoreProduct } from './ProductCard';
import type { StoreBoutique } from './StorefrontTheme';
import { authHeaders, boutiqueLink, boutiqueQuery } from '../boutiqueRouting';
import { BrandMark, Footer, MobileMenu, navItems, TopBar } from './hanooti-marketplace/components';
import { AboutPage, ContactPage, HomePage, ProductListing, ReviewsPage } from './hanooti-marketplace/pages';
import type { CartItem } from './hanooti-marketplace/types';
import type { StoreCategory, StoreFilter } from './catalogueTypes';
import { buildCategories, findCategoryBySlug, filterProducts, isPromotion, productMatchesCategory, resolvePage, resolvePathParam } from './hanooti-marketplace/utils';

const pageTitle = {
  category: 'Categorie',
};

export function HanootiMarketplaceTemplate({ boutique, products, categories: loadedCategories = [], filters, favoriteProductIds, onToggleFavorite }: { boutique: StoreBoutique; products: StoreProduct[]; categories?: StoreCategory[]; filters: StoreFilter[]; favoriteProductIds: string[]; onToggleFavorite: (productId: string) => void }) {
  const [cart, setCart] = useState<CartItem[]>([]);
  const [menuOpen, setMenuOpen] = useState(false);
  const [query, setQuery] = useState('');
  const page = resolvePage();
  const categorySlug = resolvePathParam('categories');
  window.__boutiqueSlug__ = boutique.slug;

  const categories = useMemo(() => loadedCategories.length > 0 ? loadedCategories : buildCategories(products), [loadedCategories, products]);
  const currentCategory = findCategoryBySlug(categories, categorySlug);
  const searchedProducts = useMemo(() => filterProducts(products, query), [products, query]);
  const pageProducts = useMemo(() => {
    if (page === 'category' && currentCategory) {
      return searchedProducts.filter((product) => productMatchesCategory(product, currentCategory));
    }

    if (page === 'promotions') {
      return searchedProducts.filter(isPromotion);
    }

    return searchedProducts;
  }, [currentCategory, page, searchedProducts]);

  const featured = products.find((product) => product.badge || product.rating) ?? products[0] ?? null;
  const promos = products.filter(isPromotion).slice(0, 4);
  const bestSellers = [...products].sort((a, b) => (b.rating ?? 0) - (a.rating ?? 0)).slice(0, 4);

  async function refreshCart(): Promise<void> {
    const response = await fetch(`/api/cart${boutiqueQuery(boutique.slug)}`, { credentials: 'same-origin', headers: authHeaders() });
    if (!response.ok) return;
    const payload = await response.json() as {
      currency: string;
      items: Array<{ id: string; productId: string | null; productName: string | null; quantity: number; unitPriceCents: number; variantId?: string | null; variantSku?: string | null; variantAttributes?: Array<{ name: string; value: string }> }>;
    };
    setCart(payload.items.filter((item) => item.productId !== null).map((item) => {
      const baseProduct = products.find((product) => product.id === item.productId);
      return {
        itemId: item.id,
        qty: item.quantity,
        product: {
          ...(baseProduct ?? { id: item.productId as string, name: item.productName ?? 'Produit', slug: '', currency: payload.currency, images: [] }),
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
  }, [boutique.slug, products]);

  const cartItemKey = (item: CartItem): string => item.itemId ?? `${item.product.id}:${item.product.variantId ?? ''}`;

  async function setQty(id: string, qty: number): Promise<void> {
    const item = cart.find((current) => cartItemKey(current) === id);
    if (!item) return;
    if (qty <= 0) {
      await removeItem(id);
      return;
    }
    await fetch(`/api/cart/items/${id}${boutiqueQuery(boutique.slug)}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/merge-patch+json', ...(authHeaders() ?? {}) },
      body: JSON.stringify({ quantity: qty, variantId: item.product.variantId ?? null }),
    });
    await refreshCart();
  }

  async function removeItem(id: string): Promise<void> {
    await fetch(`/api/cart/items/${id}${boutiqueQuery(boutique.slug)}`, { method: 'DELETE', headers: authHeaders() });
    await refreshCart();
  }

  return (
    <div
      className="min-h-screen bg-[color:var(--sf-bg,#FAF5FF)] text-[color:var(--sf-text,#0F172A)]"
      style={{
        '--sf-accent': boutique.primaryColor ?? '#7C3AED',
        fontFamily: 'var(--ds-font-family, Inter), system-ui, sans-serif',
        fontSize: 'var(--ds-font-size, 16px)',
      } as React.CSSProperties}
    >
      <TopBar boutique={boutique} />
      <header className="sticky top-0 z-40 border-b border-[color:var(--sf-accent,var(--sf-outline,#DDD6FE))] bg-white/85 backdrop-blur-xl">
        <div className="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
          <a href={boutiqueLink('/')} className="flex items-center gap-3 rounded-full focus:outline-none focus:ring-2 focus:ring-[color:var(--sf-accent,#22C55E)]">
            <BrandMark boutique={boutique} />
            <div>
              <div className="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--sf-accent,#22C55E)]">Marketplace</div>
              <div className="text-lg font-black tracking-tight">{boutique.name}</div>
            </div>
          </a>

           <nav className="sf-desktop-nav hidden items-center gap-1 rounded-full border border-[color:var(--sf-accent,var(--sf-outline,#DDD6FE))] bg-white/70 p-1 lg:flex" aria-label="Navigation boutique">
            {navItems().map((item) => <a key={item.href} href={item.href} className="rounded-full px-4 py-2 text-sm font-bold text-slate-600 transition-colors hover:bg-[color:var(--sf-surface-muted,#F3E8FF)] hover:text-[color:var(--sf-accent,#7C3AED)]">{item.label}</a>)}
          </nav>

          <div className="flex items-center gap-2">
            <label className="hidden h-11 items-center gap-2 rounded-full border border-[color:var(--sf-accent,var(--sf-outline,#DDD6FE))] bg-white px-4 md:flex">
              <Search className="h-4 w-4 text-slate-400" />
              <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Rechercher" className="w-36 bg-transparent text-sm outline-none placeholder:text-slate-400" />
            </label>
             {boutique.wishlistEnabled === true && <FavoritesPopover boutiqueSlug={boutique.slug} favoriteCount={favoriteProductIds.length} />}
             {boutique.customerAccountsEnabled !== false && <BoutiqueAccountLink boutiqueSlug={boutique.slug} />}
            <CartSheet items={cart} onSetQty={(id, qty) => { void setQty(id, qty); }} onRemove={(id) => { void removeItem(id); }} />
             <button type="button" onClick={() => setMenuOpen(true)} className="sf-menu-toggle inline-flex h-11 w-11 cursor-pointer items-center justify-center rounded-full border border-[color:var(--sf-accent,var(--sf-outline,#DDD6FE))] bg-white text-slate-700 transition-colors hover:bg-[color:var(--sf-surface-muted,#F3E8FF)] lg:hidden" aria-label="Ouvrir le menu">
              <Menu className="h-5 w-5" />
            </button>
          </div>
        </div>
      </header>

      {menuOpen && <MobileMenu onClose={() => setMenuOpen(false)} />}
       {page === 'home' && <HomePage boutique={boutique} categories={categories} featured={featured} promos={promos} bestSellers={bestSellers} wishlistEnabled={boutique.wishlistEnabled === true} reviewsEnabled={boutique.reviewsEnabled === true} viewsEnabled={boutique.viewsEnabled === true} favoriteProductIds={favoriteProductIds} onToggleFavorite={onToggleFavorite} />}
       {page === 'catalogue' && <ProductListing title="Catalogue" subtitle="Tous les produits disponibles dans cette boutique." products={pageProducts} categories={categories} filters={filters} query={query} onQuery={setQuery} wishlistEnabled={boutique.wishlistEnabled === true} reviewsEnabled={boutique.reviewsEnabled === true} viewsEnabled={boutique.viewsEnabled === true} favoriteProductIds={favoriteProductIds} onToggleFavorite={onToggleFavorite} />}
       {page === 'category' && <ProductListing title={currentCategory?.name ?? pageTitle.category} subtitle="Produits filtres par categorie et sous-categorie." products={pageProducts} categories={categories} filters={filters} category={currentCategory} query={query} onQuery={setQuery} wishlistEnabled={boutique.wishlistEnabled === true} reviewsEnabled={boutique.reviewsEnabled === true} viewsEnabled={boutique.viewsEnabled === true} favoriteProductIds={favoriteProductIds} onToggleFavorite={onToggleFavorite} />}
       {page === 'promotions' && <ProductListing title="Promotions" subtitle="Offres limitees, remises et coups de coeur." products={pageProducts} categories={categories} filters={filters} promotionsOnly query={query} onQuery={setQuery} wishlistEnabled={boutique.wishlistEnabled === true} reviewsEnabled={boutique.reviewsEnabled === true} viewsEnabled={boutique.viewsEnabled === true} favoriteProductIds={favoriteProductIds} onToggleFavorite={onToggleFavorite} />}
      {page === 'reviews' && <ReviewsPage products={products} reviewsEnabled={boutique.reviewsEnabled === true} boutique={boutique} />}
      {page === 'about' && <AboutPage boutique={boutique} categories={categories} />}
      {page === 'contact' && <ContactPage boutique={boutique} />}
      <Footer boutique={boutique} />
    </div>
  );
}
