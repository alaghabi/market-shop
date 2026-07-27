import { Heart, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { authHeaders, boutiqueLink, boutiqueQuery } from '../boutiqueRouting';
import { ImageWithFallback } from '../../../components/ImageWithFallback';

type FavoriteItem = {
  id: string;
  productId: string;
  productName: string;
  productSlug: string;
  image?: string | null;
};

type FavoritePayload = FavoriteItem[] | { member?: FavoriteItem[]; items?: FavoriteItem[]; 'hydra:member'?: FavoriteItem[] };

function unpack(payload: FavoritePayload): FavoriteItem[] {
  return Array.isArray(payload) ? payload : payload.member ?? payload.items ?? payload['hydra:member'] ?? [];
}

export function FavoritesPopover({ boutiqueSlug, favoriteCount: badgeCount = 0, onRefresh }: { boutiqueSlug: string; favoriteCount?: number; onRefresh?: () => void }) {
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState<FavoriteItem[]>([]);
  const [loading, setLoading] = useState(false);

  async function refresh(): Promise<void> {
    const response = await fetch(`/api/favorites/products${boutiqueQuery(boutiqueSlug)}`, { credentials: 'same-origin', headers: authHeaders() });
    if (response.ok) {
      const payload = await response.json() as FavoritePayload;
      setItems(unpack(payload));
      onRefresh?.();
    }
  }

  useEffect(() => {
    if (!open) return;
    void refresh();
  }, [boutiqueSlug, open]);

  async function removeFavorite(productId: string): Promise<void> {
    const response = await fetch(`/api/favorites/products/${productId}${boutiqueQuery(boutiqueSlug)}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: authHeaders(),
    });
    if (response.ok) setItems((current) => current.filter((item) => item.productId !== productId));
  }

  // Use actual items count for badge to stay in sync
  const displayCount = badgeCount > 0 ? badgeCount : items.length;

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        className="sf-favorites-trigger relative inline-flex h-11 w-11 cursor-pointer items-center justify-center rounded-full border border-[color:var(--ds-primary)] text-[color:var(--sf-text-muted,var(--ds-on-surface-variant))] transition-colors hover:bg-[color:var(--sf-surface-muted,var(--ds-surface-container))] hover:text-[color:var(--sf-text,var(--ds-on-surface))] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--sf-accent,var(--ds-primary))]"
        aria-label="Afficher mes favoris"
        title="Mes favoris"
      >
        <Heart className="h-5 w-5" aria-hidden="true" />
        {displayCount > 0 && (
          <span key={displayCount} className="absolute -right-1 -top-1 grid h-5 w-5 place-items-center rounded-full bg-[color:var(--ds-primary)] text-[10px] font-bold text-white ring-2 ring-[color:var(--sf-surface,var(--ds-surface))]">
            {displayCount}
          </span>
        )}
      </button>

      {open && createPortal(
        <div className="sf-favorites-overlay" role="presentation" onClick={() => setOpen(false)}>
          <section
            className="sf-favorites-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="favorites-dialog-title"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="sf-favorites-dialog__header">
              <div className="sf-favorites-dialog__title-wrap">
                <div className="sf-favorites-dialog__icon"><Heart className="h-5 w-5" fill="currentColor" aria-hidden="true" /></div>
                <div>
                  <h2 id="favorites-dialog-title" className="sf-favorites-dialog__title">Mes favoris</h2>
                  <p className="sf-favorites-dialog__subtitle">Votre sélection personnelle</p>
                </div>
              </div>
              <button type="button" onClick={() => setOpen(false)} className="sf-favorites-dialog__close" aria-label="Fermer les favoris">
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="sf-favorites-dialog__count">{items.length} produit{items.length === 1 ? '' : 's'} sauvegardé{items.length === 1 ? '' : 's'}</div>

            {loading ? <p className="sf-favorites-dialog__empty">Chargement...</p> : items.length === 0 ? (
              <div className="sf-favorites-dialog__empty">
                <div className="sf-favorites-dialog__empty-icon"><Heart className="h-7 w-7" aria-hidden="true" /></div>
                <strong>Votre liste est vide</strong>
                <span>Ajoutez un produit avec le coeur pour le retrouver ici.</span>
              </div>
            ) : (
              <div className="sf-favorites-dialog__list">
                {items.map((item) => (
                  <div key={item.id} className="sf-favorites-item">
                    <ImageWithFallback src={item.image} alt="" className="sf-favorites-item__image" />
                    <a href={boutiqueLink(`/products/${item.productSlug}`)} onClick={() => setOpen(false)} className="sf-favorites-item__name">
                      <span>{item.productName}</span>
                      <small>Voir le produit</small>
                    </a>
                    <button type="button" onClick={() => { void removeFavorite(item.productId); }} className="sf-favorites-item__remove" aria-label={`Retirer ${item.productName} des favoris`}>
                      <Heart className="h-4 w-4" fill="currentColor" />
                    </button>
                  </div>
                ))}
              </div>
            )}
          </section>
        </div>,
        document.body
      )}
    </>
  );
}
