import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { useEffect, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { appIcons } from '../../icons/fontAwesome';
import { Badge, Button, Card } from '../../components/ui';
import { CookieConsentModal } from '../../components/CookieConsentModal';
import { ProductImageGallery } from './storefront/ProductImageGallery';
import { ReviewSection } from '../../components/ReviewSection';
import { authHeaders, boutiqueLink, boutiqueQuery, resolveBoutiqueSlug } from './boutiqueRouting';
import { useCartAdd } from './storefront/useCartAdd';
import { CartSheet, type CartItem as CartSheetItem } from './storefront/CartSheet';
import { StorefrontHeader } from './storefront/StorefrontHeader';
import { VariantSelector } from './storefront/VariantSelector';
import type { StoreProduct } from './storefront/ProductCard';
import type { StoreBoutique } from './storefront/StorefrontTheme';
import { FavoriteButton } from './storefront/FavoriteButton';
import { applyStorefrontTheme, resetStorefrontTheme, type StorefrontThemeData } from '../../theme/storefrontThemeRoot';

type ProductItem = {
  id: string;
  name: string;
  slug: string;
  sellingPrice: number;
  comparePrice?: number;
  currency: string;
  shortDescription: string | null;
  description: string | null;
  images: Array<{ url: string; smallUrl?: string; largeUrl?: string; alt: string | null }>;
  stockQuantity: number;
  lowStockThreshold: number;
  viewsCount?: number;
  reviewsCount?: number;
  favoritesCount?: number;
  rating?: number | null;
  categoryId: string | null;
  categoryName: string | null;
  variants: ProductVariantItem[];
};

type ProductVariantItem = {
  id: string;
  sku: string | null;
  sellingPrice: number;
  comparePrice: number;
  quantity: number;
  image: string | null;
  isDefault: boolean;
  isActive: boolean;
  attributes: Array<{ name: string; value: string }>;
};

type BoutiqueItem = StorefrontThemeData & {
  id: string;
  name: string;
  slug: string;
  primaryColor: string;
  reviewsEnabled?: boolean;
  wishlistEnabled?: boolean;
  viewsEnabled?: boolean;
  coverImage?: string | null;
};

type CartOutput = {
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

export function ProductDetailPage({ title }: { title: string }) {
  const pathMatch = window.location.pathname.match(/^\/boutiques\/([^/]+)\/(?:produit|products)\/([^/]+)/);
  const boutiqueSlug = resolveBoutiqueSlug(/^\/boutiques\/([^/]+)\/(?:produit|products)\/[^/]+/);
  const productSlug = pathMatch?.[2] ?? window.location.pathname.match(/^\/(?:produit|products)\/([^/]+)/)?.[1] ?? '';
  window.__boutiqueSlug__ = boutiqueSlug;
  const [product, setProduct] = useState<ProductItem | null>(null);
  const [boutique, setBoutique] = useState<BoutiqueItem | null>(null);
  const [cartItems, setCartItems] = useState<CartSheetItem[]>([]);
  const [cartSheetOpen, setCartSheetOpen] = useState(false);
  const [quantity, setQuantity] = useState(1);
  const [selectedVariantId, setSelectedVariantId] = useState<string | null>(null);
  const [selectedAttributes, setSelectedAttributes] = useState<Record<string, string>>({});
  const [isFavorite, setIsFavorite] = useState(false);
  const [favoritesCount, setFavoritesCount] = useState(0);
  const [totalFavorites, setTotalFavorites] = useState(0);
  const { add: addToCart, consentOpen, acceptConsent, error: cartError } = useCartAdd({
    boutiqueSlug,
    onAdded: () => { void refreshCart(); setCartSheetOpen(true); },
  });

  async function refreshCart(): Promise<void> {
    const response = await fetch(`/api/cart${boutiqueQuery(boutiqueSlug)}`, { headers: authHeaders() });
    if (!response.ok) return;

    const payload = await response.json() as CartOutput;
    setCartItems(payload.items
      .filter((item) => item.productId !== null)
       .map((item) => ({
         itemId: item.id,
         product: {
          id: item.productId as string,
          name: item.productName ?? 'Produit',
          slug: '',
          priceCents: item.unitPriceCents,
          currency: payload.currency,
           images: [],
           variantId: item.variantId ?? undefined,
           variantSku: item.variantSku ?? undefined,
           variantAttributes: item.variantAttributes ?? [],
         },
        qty: item.quantity,
      })));
  }

  async function setCartQuantity(itemId: string, nextQuantity: number): Promise<void> {
    if (nextQuantity < 1) return;
    const currentItem = cartItems.find((item) => item.itemId === itemId);
    const response = await fetch(`/api/cart/items/${itemId}${boutiqueQuery(boutiqueSlug)}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/merge-patch+json', ...(authHeaders() ?? {}) },
      body: JSON.stringify({ quantity: nextQuantity, variantId: currentItem?.product.variantId ?? null }),
    });
    if (response.ok) await refreshCart();
  }

  async function removeCartItem(itemId: string): Promise<void> {
    const response = await fetch(`/api/cart/items/${itemId}${boutiqueQuery(boutiqueSlug)}`, {
      method: 'DELETE',
      headers: authHeaders(),
    });
    if (response.ok) await refreshCart();
  }

  useEffect(() => {
    if (!boutiqueSlug || !productSlug) return;
    const headers = authHeaders();
      void refreshCart();
      fetch(`/api/boutiques/${boutiqueSlug}`, { headers })
      .then((response) => response.ok ? response.json() : null)
      .then((data) => {
        if (!data) return;

        applyStorefrontTheme(data);
        setBoutique({ ...data, reviewsEnabled: data.reviewsEnabled === true });
      })
      .catch(() => {});

     fetch(`/api/products/${productSlug}${boutiqueQuery(boutiqueSlug)}`, { headers })
      .then((response) => response.ok ? response.json() : null)
       .then((data) => {
         if (data) {
           const nextProduct = {
             ...data,
             shortDescription: data.shortDescription ?? null,
             description: data.description ?? null,
             variants: Array.isArray(data.variants) ? data.variants : [],
           } as ProductItem;
            setProduct(nextProduct);
            setFavoritesCount(nextProduct.favoritesCount ?? 0);
           const defaultVariant = nextProduct.variants.find((variant) => variant.isActive && variant.isDefault)
             ?? nextProduct.variants.find((variant) => variant.isActive);
           setSelectedVariantId(defaultVariant?.id ?? null);
           setSelectedAttributes(defaultVariant
             ? Object.fromEntries(defaultVariant.attributes.map((attribute) => [attribute.name, attribute.value]))
             : {});
         }
       })
      .catch(() => {});
     return resetStorefrontTheme;
   }, [boutiqueSlug, productSlug]);

  useEffect(() => {
    if (!product?.id || boutique?.viewsEnabled !== true || !boutiqueSlug) return;

    const viewedKey = `viewed_${product.id}`;
    if (sessionStorage.getItem(viewedKey)) return;

    fetch(`/api/products/${product.id}/view?boutiqueSlug=${encodeURIComponent(boutiqueSlug)}`, { method: 'POST' })
      .then(() => sessionStorage.setItem(viewedKey, '1'))
      .catch(() => {});
  }, [boutique?.viewsEnabled, boutiqueSlug, product?.id]);

  useEffect(() => {
    if (!product?.id || boutique?.wishlistEnabled !== true || !boutiqueSlug) return;

    fetch(`/api/favorites/products${boutiqueQuery(boutiqueSlug)}`, { credentials: 'same-origin', headers: authHeaders() })
      .then((response) => response.ok ? response.json() : [])
      .then((payload: Array<{ productId?: string }> | { member?: Array<{ productId?: string }>; items?: Array<{ productId?: string }>; 'hydra:member'?: Array<{ productId?: string }> }) => {
        const favorites = Array.isArray(payload) ? payload : payload.member ?? payload.items ?? payload['hydra:member'] ?? [];
        setIsFavorite(favorites.some((favorite) => favorite.productId === product.id));
        setTotalFavorites(favorites.length);
      })
      .catch(() => { setIsFavorite(false); setTotalFavorites(0); });
  }, [boutique?.wishlistEnabled, boutiqueSlug, product?.id]);

  async function refreshFavorites(): Promise<void> {
    if (!product?.id || boutique?.wishlistEnabled !== true || !boutiqueSlug) return;
    try {
      const response = await fetch(`/api/favorites/products${boutiqueQuery(boutiqueSlug)}`, { credentials: 'same-origin', headers: authHeaders() });
      if (!response.ok) return;
      const payload = await response.json() as Array<{ productId?: string }> | { member?: Array<{ productId?: string }>; items?: Array<{ productId?: string }>; 'hydra:member'?: Array<{ productId?: string }> };
      const favorites = Array.isArray(payload) ? payload : payload.member ?? payload.items ?? payload['hydra:member'] ?? [];
      setIsFavorite(favorites.some((favorite) => favorite.productId === product.id));
      setTotalFavorites(favorites.length);
    } catch {
      setIsFavorite(false);
      setTotalFavorites(0);
    }
  }

  async function toggleFavorite(): Promise<void> {
    if (!product || boutique?.wishlistEnabled !== true) return;

    const response = await fetch(`/api/favorites/products/${product.id}${boutiqueQuery(boutiqueSlug)}`, {
      method: isFavorite ? 'DELETE' : 'POST',
      credentials: 'same-origin',
      headers: authHeaders(),
    });
    if (response.ok) {
      setIsFavorite((current) => !current);
      setFavoritesCount((current) => Math.max(0, current + (isFavorite ? -1 : 1)));
    }
  }

  if (!product) {
    return (
      <main className="ds-shell">
        <section className="ds-page py-8 md:py-12">
          <Card className="text-center py-12">
            <FontAwesomeIcon icon={appIcons.products} size="2x" />
            <h2 className="mt-4 text-xl font-bold">Chargement...</h2>
          </Card>
        </section>
      </main>
    );
  }

  const activeVariants = product.variants.filter((variant) => variant.isActive);
  const selectedVariant = activeVariants.find((variant) => variant.id === selectedVariantId) ?? null;
  const displayPrice = selectedVariant?.sellingPrice ?? product.sellingPrice;
  const displayComparePrice = selectedVariant?.comparePrice || product.comparePrice;
  const displayStock = selectedVariant?.quantity ?? product.stockQuantity;
  const discount = displayComparePrice && displayComparePrice > displayPrice
    ? Math.round((1 - displayPrice / displayComparePrice) * 100)
    : 0;

  function selectAttribute(name: string, value: string): void {
    const nextAttributes = { ...selectedAttributes, [name]: value };
    const matchingVariant = activeVariants.find((variant) => variant.attributes.every((attribute) => nextAttributes[attribute.name] === attribute.value));
    setSelectedAttributes(matchingVariant
      ? Object.fromEntries(matchingVariant.attributes.map((attribute) => [attribute.name, attribute.value]))
      : nextAttributes);
    setSelectedVariantId(matchingVariant?.id ?? selectedVariantId);
  }

  function handleAddToCart(): void {
    if (!product || !boutique) return;
    const storeProduct: StoreProduct = {
      id: product.id,
      name: product.name,
      slug: product.slug,
       priceCents: displayPrice,
       comparePriceCents: displayComparePrice,
       currency: product.currency,
       images: product.images.map((image) => ({ url: image.largeUrl ?? image.url, alt: image.alt })),
       variantId: selectedVariant?.id,
       variantSku: selectedVariant?.sku,
       variantAttributes: selectedVariant?.attributes,
    };
    addToCart(storeProduct, quantity);
  }

  return (
    <main className="ds-shell">
      {boutique && (
        <StorefrontHeader
          boutique={boutique as StoreBoutique}
          cartItems={cartItems}
          onSetCartQty={(id, qty) => { void setCartQuantity(id, qty); }}
          onRemoveCartItem={(id) => { void removeCartItem(id); }}
          favoriteCount={totalFavorites}
          cartOpen={cartSheetOpen}
          onCartOpenChange={setCartSheetOpen}
          onFavoritesRefresh={refreshFavorites}
        />
      )}
      <CookieConsentModal open={consentOpen} onAccept={acceptConsent} />
      {cartError && <div role="alert" className="fixed bottom-5 left-1/2 z-[90] -translate-x-1/2 rounded-full bg-red-600 px-5 py-3 text-sm font-semibold text-white shadow-xl">{cartError}</div>}
      <section className="ds-page py-8 md:py-12">
        <Card className="overflow-hidden p-0">
          <div className="grid gap-0 lg:grid-cols-2">
            <div className="overflow-hidden bg-[color:var(--ds-surface-container)]">
              <ProductImageGallery images={product.images} productName={product.name} />
            </div>
            <div className="p-8">
              <div className="flex items-start justify-between gap-4">
                <div>
                  {product.categoryName && (
                    <p className="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--ds-on-surface-variant)]">{product.categoryName}</p>
                  )}
                  <h1 className="mt-2 text-3xl font-bold">{product.name}</h1>
                </div>
                {discount > 0 && <Badge tone="success">-{discount}%</Badge>}
              </div>

              <div className="mt-6 flex items-center justify-between gap-3 text-sm text-[color:var(--ds-on-surface-variant)]">
                <div className="flex items-center gap-3">
                   {boutique?.viewsEnabled === true && <span>{product.viewsCount ?? 0} vues</span>}
                  {boutique?.reviewsEnabled === true && <span>★ {product.reviewsCount ?? 0} avis</span>}
                  {boutique?.reviewsEnabled === true && product.rating != null && <span>Note {product.rating.toFixed(1)}/5</span>}
                  {boutique?.wishlistEnabled === true && <span>♡ {favoritesCount} favoris</span>}
                </div>
                {boutique?.wishlistEnabled === true && <FavoriteButton productId={product.id} active={isFavorite} onToggle={() => { void toggleFavorite(); }} />}
              </div>

              <div className="mt-2 flex items-baseline gap-3">
                <span className="text-4xl font-bold">{(displayPrice / 100).toFixed(2)} {product.currency}</span>
                {displayComparePrice && displayComparePrice > displayPrice && (
                  <span className="text-lg text-[color:var(--ds-on-surface-variant)] line-through">{(displayComparePrice / 100).toFixed(2)} {product.currency}</span>
                )}
              </div>

              <div className="mt-4 flex items-center gap-3">
                <Badge tone={displayStock > 0 ? 'success' : 'error'}>
                  {displayStock > 0 ? `En stock (${displayStock})` : 'Rupture de stock'}
                </Badge>
                {product.lowStockThreshold > 0 && displayStock > 0 && displayStock <= product.lowStockThreshold && (
                  <Badge tone="warning">Stock bas</Badge>
                )}
              </div>

              {activeVariants.length > 0 && (
                <div className="mt-6">
                  <VariantSelector
                    variants={activeVariants}
                    selectedAttributes={selectedAttributes}
                    onSelect={selectAttribute}
                    idPrefix={product.id}
                  />
                </div>
              )}

              {product.description && (
                <p className="mt-6 text-[color:var(--ds-on-surface-variant)] leading-relaxed">{product.description}</p>
              )}

              <div className="mt-8 flex items-center gap-4">
                <div className="flex items-center rounded-xl border border-[color:var(--ds-outline-variant)]">
                   <motion.button whileTap={{ scale: 0.9 }} type="button" className="sf-quantity-control cursor-pointer" aria-label="Diminuer la quantité" onClick={() => setQuantity(Math.max(1, quantity - 1))}>
                     <ChevronLeft className="h-4 w-4" strokeWidth={2.5} aria-hidden="true" />
                   </motion.button>
                  <span className="min-w-[3rem] text-center font-semibold overflow-hidden">
                    <AnimatePresence mode="wait" initial={false}>
                      <motion.span
                        key={quantity}
                        initial={{ y: 8, opacity: 0 }}
                        animate={{ y: 0, opacity: 1 }}
                        exit={{ y: -8, opacity: 0 }}
                        transition={{ duration: 0.15 }}
                        className="inline-block"
                      >
                        {quantity}
                      </motion.span>
                    </AnimatePresence>
                  </span>
                   <motion.button whileTap={{ scale: 0.9 }} type="button" className="sf-quantity-control cursor-pointer" aria-label="Augmenter la quantité" onClick={() => setQuantity(quantity + 1)}>
                     <ChevronRight className="h-4 w-4" strokeWidth={2.5} aria-hidden="true" />
                   </motion.button>
                </div>
                 <Button variant="primary" className="flex-1" onClick={handleAddToCart} disabled={(activeVariants.length > 0 && !selectedVariant) || displayStock < 1}>
                  <FontAwesomeIcon icon={appIcons.products} /> Ajouter au panier
                </Button>
              </div>

              <div className="mt-6 flex gap-2">
                <Badge tone="neutral">Paiement sécurisé</Badge>
                <Badge tone="neutral">Livraison rapide</Badge>
              </div>
            </div>
          </div>
        </Card>
      </section>

      {boutique?.reviewsEnabled === true && (
        <section className="ds-page pb-16">
          <ReviewSection boutiqueSlug={boutiqueSlug} productId={product.id} />
        </section>
      )}
    </main>
  );
}
