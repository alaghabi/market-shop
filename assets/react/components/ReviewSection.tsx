import { useEffect, useState } from 'react';
import { Badge, Button, Card, Textarea } from './ui';

type ReviewOutput = {
  id: string;
  productId?: string | null;
  authorName: string;
  rating: number;
  comment: string | null;
  createdAt: string;
};

type ReviewSectionProps = {
  boutiqueSlug: string;
  productId?: string;
  scope?: 'boutique' | 'platform';
};

export function ReviewSection({ boutiqueSlug: _boutiqueSlug, productId, scope = 'boutique', onSubmitted }: ReviewSectionProps & { onSubmitted?: () => void }) {
  const [reviews, setReviews] = useState<ReviewOutput[]>([]);
  const [authorName, setAuthorName] = useState('');
  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState('');
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState('');

  const endpoint = scope === 'platform' ? '/api/platform/reviews' : '/api/reviews';
  const productEndpoint = productId ? `/api/products/${encodeURIComponent(productId)}/reviews` : endpoint;
  const listEndpoint = productEndpoint;

  useEffect(() => {
    fetch(listEndpoint)
      .then((response) => response.ok ? response.json() : [])
      .then((data) => {
        const payload = data as { member?: ReviewOutput[]; items?: ReviewOutput[]; 'hydra:member'?: ReviewOutput[] } | ReviewOutput[];
        const items = Array.isArray(payload) ? payload : payload.member ?? payload.items ?? payload['hydra:member'] ?? [];
        const allReviews = Array.isArray(items) ? items : [];
        setReviews(productId ? allReviews.filter((review) => review.productId === productId) : allReviews);
      })
      .catch(() => {});
  }, [listEndpoint, productId]);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError('');

    if (rating < 1) {
      setError('Veuillez sélectionner une note.');
      return;
    }

    try {
      const response = await fetch(productEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ productId: productId ?? null, authorName, rating, comment: comment || null }),
      });

      if (!response.ok) {
        setError('Erreur lors de l\'envoi. Veuillez réessayer.');
        return;
      }

      setSubmitted(true);
      onSubmitted?.();
      setAuthorName('');
      setRating(0);
      setComment('');

    } catch {
      setError('Erreur réseau. Veuillez réessayer.');
    }
  }

  const avgRating = reviews.length > 0
    ? Math.round(reviews.reduce((s, r) => s + r.rating, 0) / reviews.length * 10) / 10
    : 0;

  return (
    <div className="lovable-review-form">
      <div className="mb-6 flex items-end justify-between gap-4">
        <div>
          <h3 className="text-xl font-bold">Avis clients</h3>
          {reviews.length > 0 && (
            <p className="mt-1 text-sm text-[color:var(--ds-on-surface-variant)]">
              {avgRating}/5 - {reviews.length} avis
            </p>
          )}
        </div>
        <Badge tone="neutral">{reviews.length} avis</Badge>
      </div>

      {reviews.length === 0 && (
        <Card className="mb-6 text-center py-8">
          <p className="text-[color:var(--ds-on-surface-variant)]">Aucun avis pour le moment. Soyez le premier !</p>
        </Card>
      )}

      <div className="mb-8 grid gap-4">
        {reviews.map((review) => (
          <Card key={review.id} className="bg-[color:var(--ds-surface-container-lowest)]">
            <div className="flex items-start justify-between gap-3">
              <div>
                <strong>{review.authorName}</strong>
                <div className="mt-1 text-sm text-[color:var(--ds-primary)]">{'★'.repeat(review.rating)}{'☆'.repeat(5 - review.rating)}</div>
              </div>
              <span className="text-xs text-[color:var(--ds-on-surface-variant)]">
                {new Date(review.createdAt).toLocaleDateString('fr-FR')}
              </span>
            </div>
            {review.comment && <p className="mt-3 text-sm text-[color:var(--ds-on-surface-variant)]">{review.comment}</p>}
          </Card>
        ))}
      </div>

      {submitted ? (
        <Card className="bg-[color:var(--ds-success)]/10 border border-[color:var(--ds-success)]/20">
          <p className="text-sm font-semibold text-[color:var(--ds-success)]">Merci ! Votre avis a été soumis et sera visible après modération.</p>
        </Card>
      ) : (
        <Card>
          <h4 className="mb-4 font-bold">Laisser un avis</h4>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="mb-1 block text-sm font-semibold text-[color:var(--ds-on-surface-variant)]">Votre nom</label>
              <input
                className="ds-input w-full"
                value={authorName}
                onChange={(e) => setAuthorName(e.target.value)}
                placeholder="Jean Dupont"
                required
              />
            </div>
            <div>
              <label className="mb-1 block text-sm font-semibold text-[color:var(--ds-on-surface-variant)]">Note</label>
              <div className="flex gap-2">
                {[1, 2, 3, 4, 5].map((star) => (
                   <button
                     key={star}
                     type="button"
                     onClick={() => setRating(star)}
                     aria-label={`${star} étoile${star > 1 ? 's' : ''}`}
                     aria-pressed={star === rating}
                      className={`sf-rating-button${star <= rating ? ' sf-rating-button--active' : ''}`}
                   >
                     ★
                   </button>
                ))}
              </div>
            </div>
            <div>
              <label className="mb-1 block text-sm font-semibold text-[color:var(--ds-on-surface-variant)]">Commentaire (optionnel)</label>
              <Textarea
                value={comment}
                onChange={(e) => setComment(e.target.value)}
                placeholder="Partagez votre expérience..."
                rows={3}
              />
            </div>
            {error && <p className="text-sm text-[color:var(--ds-error)]">{error}</p>}
             <Button className="lovable-button" variant="primary" type="submit">Publier mon avis</Button>
          </form>
        </Card>
      )}
    </div>
  );
}
