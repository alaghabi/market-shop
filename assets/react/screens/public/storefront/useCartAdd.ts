import { useState } from 'react';
import { authHeaders, boutiqueLink, boutiqueQuery } from '../boutiqueRouting';
import { hasCartCookieConsent, acceptCartCookieConsent } from './cartConsent';
import type { StoreProduct } from './ProductCard';

export function useCartAdd({ boutiqueSlug, onAdded }: { boutiqueSlug: string; onAdded: (product: StoreProduct) => void }) {
  const [pending, setPending] = useState<{ product: StoreProduct; quantity: number; redirectToCart: boolean } | null>(null);
  const [consentOpen, setConsentOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(product: StoreProduct, quantity: number, redirectToCart = false): Promise<void> {
    setError(null);
    const response = await fetch(`/api/cart/items${boutiqueQuery(boutiqueSlug)}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', ...(authHeaders() ?? {}) },
      body: JSON.stringify({ productId: product.id, quantity, variantId: product.variantId ?? null }),
    });

    if (!response.ok) {
      throw new Error('Impossible d’ajouter cet article au panier.');
    }

    if (redirectToCart) {
      window.location.href = boutiqueLink('/cart');
      return;
    }

    onAdded(product);
  }

  function add(product: StoreProduct, quantity = 1, redirectToCart = false): void {
    const isGuest = !authHeaders();
    if (isGuest && !hasCartCookieConsent()) {
      setPending({ product, quantity, redirectToCart });
      setConsentOpen(true);
      return;
    }

    void submit(product, quantity, redirectToCart).catch((exception: unknown) => {
      setError(exception instanceof Error ? exception.message : 'Erreur lors de l’ajout au panier.');
    });
  }

  function acceptConsent(): void {
    acceptCartCookieConsent();
    setConsentOpen(false);
    const pendingAdd = pending;
    setPending(null);
    if (pendingAdd) {
      void submit(pendingAdd.product, pendingAdd.quantity, pendingAdd.redirectToCart).catch((exception: unknown) => {
        setError(exception instanceof Error ? exception.message : 'Erreur lors de l’ajout au panier.');
      });
    }
  }

  return { add, consentOpen, acceptConsent, error };
}
