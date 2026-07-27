import { Heart } from 'lucide-react';

export function FavoriteButton({
  productId,
  active,
  onToggle,
}: {
  productId: string;
  active: boolean;
  onToggle: (productId: string) => void;
}) {
  return (
    <button
      type="button"
      className={`sf-favorite-button${active ? ' sf-favorite-button--active' : ''}`}
      aria-label={active ? 'Retirer des favoris' : 'Ajouter aux favoris'}
      aria-pressed={active}
      onClick={(event) => {
        event.preventDefault();
        event.stopPropagation();
        onToggle(productId);
      }}
    >
      <Heart className="h-4 w-4" fill={active ? 'currentColor' : 'none'} aria-hidden="true" />
    </button>
  );
}
