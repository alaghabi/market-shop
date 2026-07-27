import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { ImageWithFallback } from '../../components/ImageWithFallback';
import { ChevronLeft, ChevronRight, Trash2 } from 'lucide-react';
import { appIcons } from '../../icons/fontAwesome';
import { Badge, Button, Card } from '../../components/ui';
import { authHeaders, boutiqueLink, boutiqueQuery, resolveBoutiqueSlug } from './boutiqueRouting';
import { applyStorefrontTheme, resetStorefrontTheme, type StorefrontThemeData } from '../../theme/storefrontThemeRoot';
import { StorefrontHeader } from './storefront/StorefrontHeader';
import type { StoreBoutique } from './storefront/StorefrontTheme';
import { VariantSelector } from './storefront/VariantSelector';

type BoutiqueItem = StorefrontThemeData & {
  id: string;
  name: string;
  slug: string;
  logoUrl?: string | null;
  slogan?: string | null;
  primaryColor: string;
  reviewsEnabled?: boolean;
  wishlistEnabled?: boolean;
  viewsEnabled?: boolean;
  coverImage?: string | null;
};

type CartItem = {
  id: string;
  productName: string | null;
  quantity: number;
  unitPriceCents: number;
  totalCents: number;
  variantId?: string | null;
  variantSku?: string | null;
  variantAttributes?: Array<{ name: string; value: string }>;
  availableVariants?: Array<{
    id: string;
    sku?: string | null;
    sellingPrice: number;
    quantity: number;
    attributes: Array<{ name: string; value: string }>;
  }>;
};

function findVariantIdForAttribute(item: CartItem, attributeName: string, value: string): string | null {
  if (!value || !item.availableVariants) return null;

  const selectedAttributes = new Map((item.variantAttributes ?? []).map((attribute) => [attribute.name, attribute.value]));
  selectedAttributes.set(attributeName, value);

  const exactMatch = item.availableVariants.find((variant) => (
    variant.quantity > 0
    && variant.attributes.length === selectedAttributes.size
    && variant.attributes.every((attribute) => selectedAttributes.get(attribute.name) === attribute.value)
  ));

  return exactMatch?.id ?? item.availableVariants.find((variant) => (
    variant.quantity > 0
    && variant.attributes.some((attribute) => attribute.name === attributeName && attribute.value === value)
  ))?.id ?? null;
}

type CartOutput = {
  id: string;
  boutiqueId: string;
  currency: string;
  itemsCount: number;
  totalCents: number;
  items: CartItem[];
};

export function CartPage() {
  const boutiqueSlug = resolveBoutiqueSlug(/^\/boutiques\/([^/]+)\/cart/);
  const [boutique, setBoutique] = useState<BoutiqueItem | null>(null);
  const [cart, setCart] = useState<CartOutput | null>(null);
  const [favoritesCount, setFavoritesCount] = useState(0);
  const [removingItemId, setRemovingItemId] = useState<string | null>(null);
  const [updatingItemId, setUpdatingItemId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!boutiqueSlug) return;
    window.__boutiqueSlug__ = boutiqueSlug;
    resetStorefrontTheme();
    const headers = authHeaders();
    fetch(`/api/boutiques/${boutiqueSlug}`, { headers })
      .then((response) => response.ok ? response.json() : null)
      .then((payload: BoutiqueItem | null) => {
        if (payload) applyStorefrontTheme(payload);
        setBoutique(payload);
        if (!payload) return null;

        return fetch(`/api/cart${boutiqueQuery(boutiqueSlug)}`, { headers });
      })
      .then((response) => response && response.ok ? response.json() : null)
      .then((payload: CartOutput | null) => {
        if (payload) setCart(payload);
      })
      .catch(() => {});

    // Fetch favorites count
    fetch(`/api/favorites/products${boutiqueQuery(boutiqueSlug)}`, { credentials: 'same-origin', headers })
      .then((response) => response.ok ? response.json() : [])
      .then((payload: any) => {
        const items = Array.isArray(payload) ? payload : payload.member ?? payload.items ?? payload['hydra:member'] ?? [];
        setFavoritesCount(items.length);
      })
      .catch(() => {});

    return resetStorefrontTheme;
  }, [boutiqueSlug]);

  const removeItem = async (itemId: string) => {
    setRemovingItemId(itemId);
    setError(null);
    try {
      const response = await fetch(`/api/cart/items/${itemId}${boutiqueQuery(boutiqueSlug)}`, {
        method: 'DELETE',
        headers: authHeaders(),
      });
      if (!response.ok) throw new Error('Impossible de supprimer cet article.');

      const refreshedCart = await fetch(`/api/cart${boutiqueQuery(boutiqueSlug)}`, {
        headers: authHeaders(),
      });
      if (!refreshedCart.ok) throw new Error('Impossible de recharger le panier.');
      setCart(await refreshedCart.json() as CartOutput);
    } catch (removeError) {
      setError(removeError instanceof Error ? removeError.message : 'Impossible de supprimer cet article.');
    } finally {
      setRemovingItemId(null);
    }
  };

  const updateQuantity = async (itemId: string, quantity: number, variantId?: string | null) => {
    if (quantity < 1) {
      await removeItem(itemId);
      return;
    }

    setUpdatingItemId(itemId);
    setError(null);
    try {
      const response = await fetch(`/api/cart/items/${itemId}${boutiqueQuery(boutiqueSlug)}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/merge-patch+json', ...authHeaders() },
        body: JSON.stringify({ quantity, variantId: variantId ?? null }),
      });
      if (!response.ok) throw new Error('Impossible de modifier la quantité.');

      const refreshedCart = await fetch(`/api/cart${boutiqueQuery(boutiqueSlug)}`, {
        headers: authHeaders(),
      });
      if (!refreshedCart.ok) throw new Error('Impossible de recharger le panier.');
      setCart(await refreshedCart.json() as CartOutput);
    } catch (updateError) {
      setError(updateError instanceof Error ? updateError.message : 'Impossible de modifier la quantité.');
    } finally {
      setUpdatingItemId(null);
    }
  };

  const items = cart?.items ?? [];
  const currency = cart?.currency ?? 'TND';
  const total = ((cart?.totalCents ?? 0) / 100).toFixed(2);

  return (
    <main className="ds-shell min-h-screen bg-[color:var(--sf-bg,#f6f2eb)] text-[color:var(--sf-text,#171717)]">
      {boutique && <StorefrontHeader boutique={boutique as StoreBoutique} showCart={false} cartItems={cart?.items.map(i => ({ itemId: i.id, product: { id: i.id, name: i.productName ?? 'Produit', slug: '', priceCents: i.unitPriceCents, currency: currency, images: [] }, qty: i.quantity })) ?? []} favoriteCount={favoritesCount} />}
      <section className="ds-page py-8 md:py-12">
        <div className="ds-grid ds-grid--split">
          <Card>
            <div className="mb-5 flex items-center justify-between">
              <div>
                <p className="ds-hero__eyebrow">Panier</p>
                <div className="mt-2 flex items-center gap-3">
                  {boutique && <ImageWithFallback src={boutique.logoUrl} alt={boutique.logoUrl ? boutique.name : 'Hanooti'} className="h-10 w-10 rounded-full object-cover" />}
                  <h1 className="text-3xl font-bold">{boutique?.name ?? 'Boutique'}</h1>
                </div>
              </div>
              <Badge tone="neutral">{cart?.itemsCount ?? 0} article{(cart?.itemsCount ?? 0) > 1 ? 's' : ''}</Badge>
            </div>

            {error && <p role="alert" className="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{error}</p>}
            <div className="space-y-4">
              {items.length > 0 ? items.map((item, index) => (
                <motion.div
                  key={item.id}
                  initial={{ opacity: 0, x: -12 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: index * 0.05, duration: 0.3 }}
                  className="flex items-center gap-4 rounded-2xl border border-[color:var(--sf-outline,#d8d0c4)] bg-[color:var(--sf-surface,#ffffff)] p-4"
                >
                  <div className="flex h-20 w-20 items-center justify-center rounded-xl bg-[color:var(--sf-surface-muted,#ece5d9)] text-[color:var(--sf-accent,#111111)]">
                    <FontAwesomeIcon icon={appIcons.products} />
                  </div>
                   <div className="flex-1">
                     <strong>{item.productName ?? 'Produit'}</strong>
                    {item.variantAttributes && item.variantAttributes.length > 0 && (
                      <div className="mt-1 flex flex-wrap gap-1.5">
                        {item.variantAttributes.map((attribute) => (
                          <span key={`${attribute.name}-${attribute.value}`} className="rounded-full bg-[color:var(--sf-surface-muted,#ece5d9)] px-2 py-0.5 text-xs text-[color:var(--sf-text-muted,#6b6560)]">
                            {attribute.name}: {attribute.value}
                          </span>
                        ))}
                      </div>
                    )}
                    {item.variantSku && <div className="mt-1 text-xs text-[color:var(--sf-text-muted,#6b6560)]">Réf. {item.variantSku}</div>}
                    {item.availableVariants && item.availableVariants.length > 0 && (
                      <div className="mt-3">
                        <VariantSelector
                          variants={item.availableVariants}
                          selectedAttributes={Object.fromEntries((item.variantAttributes ?? []).map((attribute) => [attribute.name, attribute.value]))}
                          onSelect={(name, value) => { void updateQuantity(item.id, item.quantity, findVariantIdForAttribute(item, name, value)); }}
                          idPrefix={item.id}
                          disabled={updatingItemId === item.id || removingItemId === item.id}
                        />
                      </div>
                    )}
                    <div className="mt-2 flex items-center justify-center gap-2 text-sm text-[color:var(--sf-text-muted,#6b6560)]">
                      <span>Qté:</span>
                      <button
                        type="button"
                         className="sf-quantity-control cursor-pointer transition-colors hover:bg-[color:var(--sf-surface-muted,#F3E8FF)] disabled:cursor-not-allowed disabled:opacity-50"
                        aria-label={`Diminuer la quantité de ${item.productName ?? 'cet article'}`}
                        disabled={removingItemId === item.id || updatingItemId === item.id}
                        onClick={() => { void updateQuantity(item.id, item.quantity - 1, item.variantId); }}
                      >
                         <ChevronLeft className="h-4 w-4" strokeWidth={2.5} aria-hidden="true" />
                      </button>
                      <strong className="min-w-6 text-center text-[color:var(--sf-text,var(--ds-on-surface))]">{item.quantity}</strong>
                      <button
                        type="button"
                         className="sf-quantity-control cursor-pointer transition-colors hover:bg-[color:var(--sf-surface-muted,#F3E8FF)] disabled:cursor-not-allowed disabled:opacity-50"
                        aria-label={`Augmenter la quantité de ${item.productName ?? 'cet article'}`}
                        disabled={removingItemId === item.id || updatingItemId === item.id}
                        onClick={() => { void updateQuantity(item.id, item.quantity + 1, item.variantId); }}
                      >
                         <ChevronRight className="h-4 w-4" strokeWidth={2.5} aria-hidden="true" />
                      </button>
                    </div>
                  </div>
                  <div className="text-right">
                    <strong>{(item.totalCents / 100).toFixed(2)} {currency}</strong>
                    <p className="text-sm text-[color:var(--sf-text-muted,#6b6560)]">Sous-total</p>
                    <Button
                      type="button"
                      variant="ghost"
                      className="mt-2 min-h-0 px-2 py-1 text-xs"
                      disabled={removingItemId === item.id}
                      onClick={() => { void removeItem(item.id); }}
                      aria-label={`Supprimer ${item.productName ?? 'cet article'}`}
                    >
                      <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                      {removingItemId === item.id ? 'Suppression...' : 'Supprimer'}
                    </Button>
                  </div>
                </motion.div>
              )) : (
                <div className="rounded-2xl border border-dashed border-[color:var(--sf-outline,#d8d0c4)] bg-[color:var(--sf-surface,#ffffff)] p-8 text-center text-[color:var(--sf-text-muted,#6b6560)]">
                  Votre panier boutique est vide.
                </div>
              )}
            </div>
          </Card>

          <Card>
            <h2 className="text-xl font-bold">Récapitulatif</h2>
            <div className="mt-4 space-y-3 text-sm">
              <div className="flex justify-between"><span>Sous-total</span><strong>{total} {currency}</strong></div>
              <div className="flex justify-between"><span>Livraison</span><strong>Calculée au checkout</strong></div>
              <div className="flex justify-between border-t border-[color:var(--ds-outline-variant)] pt-3 text-base"><span>Total</span><strong>{total} {currency}</strong></div>
            </div>
            <div className="mt-6 space-y-3">
               <Button variant="primary" className="w-full" onClick={() => { window.location.href = boutiqueLink('/checkout'); }}><FontAwesomeIcon icon={appIcons.security} /> Commander</Button>
               <Button variant="secondary" className="w-full" onClick={() => { window.location.href = boutiqueLink('/quote'); }}>Demander un devis</Button>
            </div>
          </Card>
        </div>
      </section>
    </main>
  );
}
