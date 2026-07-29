import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { useEffect, useMemo, useState } from 'react';
import { appIcons } from '../../icons/fontAwesome';
import { BrandLogo } from '../../components/BrandLogo';
import { Badge, Button, Card, Input } from '../../components/ui';
import { frontOfficeUrl } from '../../backoffice/utils/frontOfficeUrl';

type BoutiqueItem = {
  id: string;
  name: string;
  slug: string;
  status: string;
  isPublished?: boolean;
  isVisiblePublicly?: boolean;
  customDomain?: string | null;
  primaryColor: string;
  secondaryColor: string;
  logoUrl: string | null;
  contactEmail: string | null;
  address: string | null;
  productsCount: number;
  ordersCount: number;
  totalRevenue: number;
};

type ReviewItem = {
  id: string;
  boutiqueId: string;
  authorName: string;
  rating: number;
  comment: string | null;
  createdAt: string;
};

export function BoutiqueCentralPage({ title, description }: { title: string; description: string }) {
  const [email, setEmail] = useState('');
  const [boutiques, setBoutiques] = useState<BoutiqueItem[]>([]);
  const [reviewsMap, setReviewsMap] = useState<Record<string, ReviewItem[]>>({});
  const [isLoaded, setIsLoaded] = useState(false);

  useEffect(() => {
    fetch('/api/boutiques')
      .then((response) => response.ok ? response.json() : Promise.reject())
      .then((data) => {
        const items = data.member ?? data.items ?? [];
        setBoutiques(items);
        setIsLoaded(true);
        items.forEach((b: BoutiqueItem) => {
          fetch(`/api/boutiques/${b.slug}/reviews`)
            .then((res) => res.ok ? res.json() : [])
            .then((reviews: ReviewItem[]) => {
              const member = (reviews as { member?: ReviewItem[] }).member ?? reviews;
              setReviewsMap((prev) => ({ ...prev, [b.id]: Array.isArray(member) ? member : [] }));
            })
            .catch(() => {});
        });
      })
      .catch(() => setIsLoaded(true));
  }, []);

  const totalProducts = boutiques.reduce((sum, b) => sum + b.productsCount, 0);
  const totalOrders = boutiques.reduce((sum, b) => sum + b.ordersCount, 0);
  const featured = boutiques.filter((b) => b.status === 'active' && b.isPublished === true && b.isVisiblePublicly !== false).slice(0, 6);

  async function handleQuickRegister(event?: React.FormEvent) {
    if (event) event.preventDefault();
    if (!email) return;
    window.location.href = `/auth/register?email=${encodeURIComponent(email)}`;
  }

  return (
    <main className="ds-shell">
      <header className="sticky top-0 z-50 border-b border-[color:var(--ds-outline-variant)] bg-[color:var(--ds-surface-container-lowest)]/95 backdrop-blur">
        <div className="ds-page flex items-center justify-between gap-6 py-4">
          <a className="flex items-center gap-3 font-bold text-[color:var(--ds-primary)] no-underline" href="/">
            <BrandLogo className="hanooti-logo--marketplace" />
          </a>
          <nav className="hidden items-center gap-6 md:flex" aria-label="Navigation publique">
            <a className="font-semibold text-[color:var(--ds-on-surface-variant)] no-underline" href="/boutiques">Boutiques</a>
            <a className="rounded-full bg-[color:var(--ds-on-surface)] px-4 py-2 font-semibold text-white no-underline" href="/admin">Back-office</a>
          </nav>
        </div>
      </header>

      <section className="ds-page py-8 md:py-16">
        <div className="ds-hero overflow-hidden relative">
          <div className="absolute inset-0 opacity-50 pointer-events-none">
            <div className="absolute -right-20 -top-20 h-80 w-80 rounded-full bg-[color:var(--ds-primary-container)] blur-3xl" />
            <div className="absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-[color:var(--ds-secondary-container)] blur-3xl" />
          </div>

          <div className="relative grid gap-10 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
            <div>
              <p className="ds-hero__eyebrow">Hanooti</p>
              <h1 className="ds-hero__title">{title}</h1>
              <p className="ds-hero__subtitle">{description}</p>
              <div className="mt-8 flex flex-wrap gap-4">
                <div className="rounded-2xl border border-[color:var(--ds-outline-variant)] bg-[color:var(--ds-surface-container-lowest)]/85 p-5 max-w-sm backdrop-blur">
                  <p className="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--ds-on-surface-variant)]">Créer ta boutique</p>
                  <strong className="mt-1 block text-lg">Commence gratuitement, dès 99 DT / mois</strong>
                </div>
                <div className="rounded-2xl border border-[color:var(--ds-outline-variant)] bg-[color:var(--ds-surface-container-lowest)]/85 p-5 max-w-sm backdrop-blur">
                  <p className="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--ds-on-surface-variant)]">Aucune carte de crédit requise</p>
                  <strong className="mt-1 block text-lg">Période d'essai 14 jours</strong>
                </div>
              </div>
              <div className="mt-8 max-w-md">
                <form onSubmit={handleQuickRegister} className="flex gap-3">
                  <Input
                    type="email"
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    placeholder="admin@boutique.fr"
                    required
                    className="flex-1"
                  />
                  <Button variant="primary" type="submit">
                    Créer mon compte
                  </Button>
                </form>
                <p className="mt-2 text-xs text-[color:var(--ds-on-surface-variant)]">Commencez votre essai gratuit de 14 jours. Aucune carte de crédit requise.</p>
              </div>
            </div>

            <Card className="bg-[color:var(--ds-surface-container-lowest)]/90 backdrop-blur">
              <p className="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--ds-on-surface-variant)]">Plateforme</p>
              <h2 className="mt-2 text-5xl font-bold">{boutiques.length}</h2>
              <p className="mt-2 text-sm text-[color:var(--ds-on-surface-variant)]">Boutiques locales actives</p>
              <div className="mt-4 flex items-center gap-2">
                <Badge tone="success">Publié</Badge>
                <Badge tone="neutral">Multi-boutique</Badge>
              </div>

              <div className="mt-6 grid grid-cols-2 gap-4 border-t border-[color:var(--ds-outline-variant)] pt-6">
                <div>
                  <p className="text-2xl font-bold">{totalProducts.toLocaleString()}</p>
                  <p className="text-sm text-[color:var(--ds-on-surface-variant)]">Produits</p>
                </div>
                <div>
                  <p className="text-2xl font-bold">{totalOrders.toLocaleString()}</p>
                  <p className="text-sm text-[color:var(--ds-on-surface-variant)]">Commandes</p>
                </div>
              </div>
            </Card>
          </div>
        </div>
      </section>

      <section className="ds-page pb-8">
        <div className="mx-auto max-w-2xl text-center">
          <h2 className="text-3xl font-bold">Tout ce dont tu as besoin pour ta boutique</h2>
          <p className="mt-3 text-lg text-[color:var(--ds-on-surface-variant)]">Platform tout-en-un pour gérer ta boutique en ligne et développer ton activité.</p>
        </div>

        <div className="ds-grid ds-grid--cards mt-10">
          {[
            { icon: appIcons.store, title: 'Boutiques actives', value: String(boutiques.length) },
            { icon: appIcons.products, title: 'Produits', value: totalProducts.toLocaleString() },
            { icon: appIcons.pos, title: 'Commandes', value: totalOrders.toLocaleString() },
          ].map((item) => (
            <Card key={item.title}>
              <div className="flex items-center justify-between gap-4">
                <div>
                  <p className="text-sm text-[color:var(--ds-on-surface-variant)]">{item.title}</p>
                  <h3 className="mt-2 text-3xl font-bold">{item.value}</h3>
                </div>
                <span className="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-[color:var(--ds-secondary-container)] text-[color:var(--ds-primary)]">
                  <FontAwesomeIcon icon={item.icon} />
                </span>
              </div>
            </Card>
          ))}
        </div>
      </section>

      <section className="ds-page pb-16">
        <div className="mb-8 flex items-end justify-between gap-4">
          <div>
            <p className="ds-hero__eyebrow">Boutiques</p>
            <h2 className="mt-2 text-2xl font-bold">Boutiques à la une</h2>
            <p className="mt-1 max-w-2xl text-[color:var(--ds-on-surface-variant)]">Découvrez nos boutiques partenaires et leurs produits.</p>
          </div>
          <Button variant="ghost" onClick={() => window.location.href = '/boutiques'}>
            <FontAwesomeIcon icon={appIcons.products} /> Voir tout
          </Button>
        </div>

        <div className="ds-grid ds-grid--cards">
          {featured.length > 0 ? featured.map((boutique) => {
            const boutiqueReviews = reviewsMap[boutique.id] ?? [];
            const avgRating = boutiqueReviews.length > 0
              ? Math.round(boutiqueReviews.reduce((s, r) => s + r.rating, 0) / boutiqueReviews.length * 10) / 10
              : 0;

            return (
              <a key={boutique.id} href={frontOfficeUrl(boutique)} className="ds-card p-0 overflow-hidden no-underline transition hover:-translate-y-1" style={{ borderTop: `4px solid ${boutique.primaryColor}` }}>
                <div className="p-5">
                  <div className="flex items-center gap-3">
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-white text-lg font-bold" style={{ backgroundColor: boutique.primaryColor }}>
                      {boutique.name.charAt(0)}
                    </div>
                    <div className="min-w-0">
                      <h3 className="text-lg font-bold text-[color:var(--ds-on-surface)] truncate">{boutique.name}</h3>
                      <p className="text-sm text-[color:var(--ds-on-surface-variant)] truncate">{boutique.address ?? 'Boutique en ligne'}</p>
                    </div>
                  </div>

                  <div className="mt-4 flex items-center gap-4 text-sm text-[color:var(--ds-on-surface-variant)]">
                    <span><strong>{boutique.productsCount}</strong> produits</span>
                    {avgRating > 0 && <span>★ {avgRating}/5 ({boutiqueReviews.length} avis)</span>}
                  </div>

                  <div className="mt-4 flex gap-2">
                     <Badge tone="success">Publié</Badge>
                  </div>
                </div>
              </a>
            );
          }) : (
            <div className="ds-empty-state ds-card">
              <FontAwesomeIcon icon={appIcons.store} size="2x" />
              <strong>Aucune boutique publiée</strong>
              <span>Revenez bientôt pour découvrir nos boutiques.</span>
            </div>
          )}
        </div>
      </section>
    </main>
  );
}
