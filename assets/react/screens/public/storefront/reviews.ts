import { useEffect, useState } from 'react';
import { boutiqueQuery } from '../boutiqueRouting';

export type StoreReview = {
  id: string;
  boutiqueId?: string | null;
  productId?: string | null;
  authorName: string;
  rating: number;
  comment: string | null;
  isVerifiedPurchase: boolean;
  createdAt: string;
};

type CollectionPayload = StoreReview[] | {
  member?: StoreReview[];
  items?: StoreReview[];
  'hydra:member'?: StoreReview[];
};

export function useStorefrontReviews(enabled = true, boutiqueSlug = '') {
  const [reviews, setReviews] = useState<StoreReview[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    if (!enabled) {
      setReviews([]);
      setIsLoading(false);
      return undefined;
    }

    const controller = new AbortController();

    fetch(`/api/reviews${boutiqueQuery(boutiqueSlug)}`, { signal: controller.signal })
      .then(async (response) => {
        if (!response.ok) throw new Error('Impossible de charger les avis de la boutique.');

        return await response.json() as CollectionPayload;
      })
      .then((payload) => {
        const items = Array.isArray(payload) ? payload : payload.member ?? payload.items ?? payload['hydra:member'] ?? [];
        setReviews(items);
      })
      .catch((error: unknown) => {
        if (!(error instanceof DOMException && error.name === 'AbortError')) setReviews([]);
      })
      .finally(() => {
        if (!controller.signal.aborted) setIsLoading(false);
      });

    return () => controller.abort();
  }, [enabled, boutiqueSlug]);

  return { reviews, isLoading };
}

export function formatStoreReviewDate(value: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';

  return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}

export function storeReviewInitial(name: string): string {
  return name.trim().charAt(0).toUpperCase() || '?';
}
