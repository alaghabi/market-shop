import { useState } from 'react';
import { authHeaders, boutiqueQuery } from '../boutiqueRouting';
import { hasCartCookieConsent, acceptCartCookieConsent } from './cartConsent';
import type { StoreProduct } from './ProductCard';

export function useCartAdd({ boutiqueSlug, onAdded }: { boutiqueSlug: string; onAdded: (product: StoreProduct) => void }) {
  const [pending, setPending] = useState<{ product: StoreProduct; quantity: number } | null>(null);
  const [consentOpen, setConsentOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(product: StoreProduct, quantity: number): Promise<void> {
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

    onAdded(product);
  }

  function add(product: StoreProduct, quantity = 1): void {
    const isGuest = !authHeaders();
    if (isGuest && !hasCartCookieConsent()) {
      setPending({ product, quantity });
      setConsentOpen(true);
      return;
    }

    void submit(product, quantity).catch((exception: unknown) => {
      setError(exception instanceof Error ? exception.message : 'Erreur lors de l’ajout au panier.');
    });
  }

  function acceptConsent(): void {
    acceptCartCookieConsent();
    setConsentOpen(false);
    const pendingAdd = pending;
    setPending(null);
    if (pendingAdd) {
      void submit(pendingAdd.product, pendingAdd.quantity).catch((exception: unknown) => {
        setError(exception instanceof Error ? exception.message : 'Erreur lors de l’ajout au panier.');
      });
    }
  }

  return { add, consentOpen, acceptConsent, error };
}
