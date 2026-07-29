import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { useMemo, useState } from 'react';
import { BrandLogo } from '../../components/BrandLogo';
import { frontOfficeUrl } from '../../backoffice/utils/frontOfficeUrl';
import { Badge, Button, Card, Input } from '../../components/ui';
import { appIcons } from '../../icons/fontAwesome';
import { PlatformSocialLinks } from '../../components/PlatformSocialLinks';

type PublicBoutique = {
  name: string;
  category?: string | null;
  city?: string | null;
  image?: string | null;
  href?: string;
  accent?: string | null;
  slug: string;
  status?: string;
  logoUrl?: string | null;
  customDomain?: string | null;
  isPublished?: boolean;
  isVisiblePublicly?: boolean;
};

type MarketplacePageProps = {
  title: string;
  description: string;
  boutiques: PublicBoutique[];
};

const BENEFITS = [
  {
    icon: appIcons.store,
    title: 'Boutiques claires',
    description: 'Une vue simple pour trouver rapidement une boutique ou une collection.',
  },
  {
    icon: appIcons.products,
    title: 'Parcours guidé',
    description: 'Un front plus lisible avec des sections courtes et des actions visibles.',
  },
  {
    icon: appIcons.truck,
    title: 'Commande suivie',
    description: 'Accès plus direct aux boutiques, à la vitrine et aux prochaines étapes.',
  },
];

const DEFAULT_CATEGORIES = ['Tous', 'Mode', 'Beauté', 'Maison', 'Alimentation', 'Services', 'Artisanat'];

function getStoreCountLabel(count: number): string {
  return `${count} boutique${count > 1 ? 's' : ''}`;
}

export function MarketplacePage({ title, description, boutiques }: MarketplacePageProps) {
  const [query, setQuery] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('Tous');

  const categories = useMemo(() => {
    const values = boutiques
      .map((boutique) => boutique.category)
      .filter((category): category is string => Boolean(category));

    return ['Tous', ...Array.from(new Set(values)).slice(0, 5)];
  }, [boutiques]);

  const filteredBoutiques = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    const category = selectedCategory.toLowerCase();

    return boutiques.filter((boutique) => {
      const searchable = [boutique.name, boutique.category ?? '', boutique.city ?? '']
        .join(' ')
        .toLowerCase()
        .includes(normalized);

      if ('tous' !== category && (boutique.category ?? '').toLowerCase() !== category) {
        return false;
      }

      return searchable;
    });
  }, [boutiques, query, selectedCategory]);

  const cityCount = useMemo(() => new Set(boutiques.map((boutique) => boutique.city).filter(Boolean)).size, [boutiques]);
  const featuredBoutiques = filteredBoutiques.slice(0, 8);
  const hasFilter = query.trim().length > 0;

  return (
    <main className="ds-shell">
      <header className="sticky top-0 z-40 border-b border-[color:var(--ds-outline-variant)] bg-[color:var(--ds-surface-container-lowest)]/95 backdrop-blur">
        <div className="ds-page flex items-center justify-between gap-6 py-4">
          <a className="flex items-center gap-3 font-bold text-[color:var(--ds-primary)] no-underline" href="/">
            <BrandLogo className="hanooti-logo--marketplace" />
          </a>

          <nav className="hidden items-center gap-4 md:flex" aria-label="Navigation publique">
            <a className="rounded-full px-4 py-2 font-semibold text-[color:var(--ds-on-surface-variant)] no-underline transition-colors hover:bg-[color:var(--ds-surface-container-low)] hover:text-[color:var(--ds-on-surface)]" href="/boutiques">
              Boutiques
            </a>
            <Button type="button" variant="secondary" onClick={() => { window.location.href = '/admin'; }}>
              Back-office
            </Button>
          </nav>
        </div>
      </header>

      <section className="ds-page py-8 md:py-12">
        <Card className="ds-hero overflow-hidden relative">
          <div className="absolute inset-0 pointer-events-none opacity-70">
            <div className="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-[color:var(--ds-primary-container)] blur-3xl" />
            <div className="absolute -bottom-24 -left-24 h-64 w-64 rounded-full bg-[color:var(--ds-secondary-container)] blur-3xl" />
          </div>

          <div className="relative grid gap-10 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
            <div>
              <Badge tone="neutral">Professional Hub</Badge>
              <h1 className="ds-hero__title">{title || 'Découvrez les boutiques Hanooti'}</h1>
              <p className="ds-hero__subtitle">
                {description || 'Une vitrine publique plus lisible pour explorer les boutiques, comparer rapidement et accéder au bon parcours.'}
              </p>

              <div className="mt-6 flex flex-wrap gap-3">
                <Button type="button" variant="primary" onClick={() => { window.location.href = '/admin'; }}>
                  <FontAwesomeIcon icon={appIcons.store} /> Ouvrir ma boutique
                </Button>
                <Button type="button" variant="secondary" onClick={() => { window.location.href = '/boutiques'; }}>
                  <FontAwesomeIcon icon={appIcons.products} /> Parcourir le marketplace
                </Button>
              </div>

              <div className="mt-8 max-w-2xl">
                <div className="flex flex-col gap-3 sm:flex-row">
                  <div className="relative flex-1">
                    <FontAwesomeIcon
                      icon={appIcons.search}
                      className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[color:var(--ds-on-surface-variant)]"
                    />
                    <Input
                      value={query}
                      onChange={(event) => setQuery(event.target.value)}
                      placeholder="Find a boutique..."
                      className="pl-11"
                      aria-label="Rechercher une boutique"
                    />
                  </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-2">
                  {categories.map((category) => (
                    <button
                      key={category}
                      type="button"
                      onClick={() => setSelectedCategory(category)}
                      className={`rounded-full px-4 py-2 text-sm font-semibold transition-colors duration-200 cursor-pointer ${selectedCategory === category ? 'bg-[color:var(--ds-primary)] text-white' : 'bg-[color:var(--ds-surface-container-low)] text-[color:var(--ds-on-surface-variant)] hover:bg-[color:var(--ds-surface-container)] hover:text-[color:var(--ds-on-surface)]'}`}
                    >
                      {category}
                    </button>
                  ))}
                  <button
                    type="button"
                    onClick={() => { setQuery(''); setSelectedCategory('Tous'); }}
                    className="rounded-full px-4 py-2 text-sm font-semibold text-[color:var(--ds-on-surface-variant)] transition-colors duration-200 cursor-pointer hover:bg-[color:var(--ds-surface-container-low)]"
                    disabled={!hasFilter && 'Tous' === selectedCategory}
                  >
                    Reset
                  </button>
                </div>
              </div>

              <div className="mt-8 grid gap-4 sm:grid-cols-3">
                <Card className="bg-[color:var(--ds-surface-container-low)]">
                  <p className="text-sm text-[color:var(--ds-on-surface-variant)]">Boutiques</p>
                  <p className="mt-2 text-3xl font-bold">{boutiques.length}</p>
                </Card>
                <Card className="bg-[color:var(--ds-surface-container-low)]">
                  <p className="text-sm text-[color:var(--ds-on-surface-variant)]">Villes</p>
                  <p className="mt-2 text-3xl font-bold">{cityCount}</p>
                </Card>
                <Card className="bg-[color:var(--ds-surface-container-low)]">
                  <p className="text-sm text-[color:var(--ds-on-surface-variant)]">Résultats</p>
                  <p className="mt-2 text-3xl font-bold">{filteredBoutiques.length}</p>
                </Card>
              </div>
            </div>

            <Card className="bg-[color:var(--ds-surface-container-lowest)]/90 backdrop-blur">
              <p className="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--ds-on-surface-variant)]">Platform</p>
              <h2 className="mt-2 text-2xl font-bold">Boutique Directory</h2>
              <p className="mt-2 text-[color:var(--ds-on-surface-variant)]">
                Le front met l’accent sur la découverte, le tri rapide et des points d’entrée compréhensibles.
              </p>

              <div className="mt-6 space-y-3">
                {['Verified vendors', 'Smart discovery', 'Fast access'].map((item) => (
                  <div key={item} className="flex items-center justify-between gap-3 rounded-2xl border border-[color:var(--ds-outline-variant)] bg-[color:var(--ds-surface-container-low)] px-4 py-3">
                    <span className="font-medium">{item}</span>
                    <FontAwesomeIcon icon={appIcons.shop} className="text-[color:var(--ds-primary)]" />
                  </div>
                ))}
              </div>

              <div className="mt-6 flex flex-wrap gap-2">
                <Badge tone="success">Accessible</Badge>
                <Badge tone="neutral">Responsive</Badge>
                <Badge tone="neutral">Design system</Badge>
              </div>
            </Card>
          </div>
        </Card>
      </section>

      <section className="ds-page pb-8">
        <div className="mx-auto max-w-3xl text-center">
          <p className="ds-hero__eyebrow">Boutique Directory</p>
          <h2 className="mt-2 text-3xl font-bold">Filter through our network of verified professional vendors.</h2>
          <p className="mt-3 text-lg text-[color:var(--ds-on-surface-variant)]">
            Les pages publiques doivent guider l’utilisateur sans le noyer dans des blocs visuels trop agressifs.
          </p>
        </div>

        <div className="ds-grid ds-grid--cards mt-10">
          {BENEFITS.map((benefit) => (
            <Card key={benefit.title} className="transition-colors hover:bg-[color:var(--ds-surface-container-low)]">
              <span className="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-[color:var(--ds-secondary-container)] text-[color:var(--ds-primary)]">
                <FontAwesomeIcon icon={benefit.icon} />
              </span>
              <h3 className="mt-4 text-lg font-bold">{benefit.title}</h3>
              <p className="mt-2 text-[color:var(--ds-on-surface-variant)]">{benefit.description}</p>
            </Card>
          ))}
        </div>
      </section>

      <section className="ds-page pb-16">
        <div className="mb-6 flex items-end justify-between gap-4">
          <div>
            <p className="ds-hero__eyebrow">Boutique Grid</p>
            <h2 className="mt-2 text-2xl font-bold">{hasFilter ? 'Résultats filtrés' : 'Boutiques à la une'}</h2>
            <p className="mt-1 max-w-2xl text-[color:var(--ds-on-surface-variant)]">
              {hasFilter
                ? 'Affinez la recherche avec un autre mot-clé ou réinitialisez le filtre.'
                : 'Accès direct aux boutiques les plus visibles dans le marketplace public.'}
            </p>
          </div>

          {hasFilter ? (
            <Button type="button" variant="ghost" onClick={() => setQuery('')}>
              Effacer le filtre
            </Button>
          ) : (
            <Button type="button" variant="ghost" onClick={() => { window.location.href = '/boutiques'; }}>
              View all boutiques
            </Button>
          )}
        </div>

        <div className="ds-grid ds-grid--cards">
          {featuredBoutiques.length > 0 ? (
            featuredBoutiques.map((boutique) => (
              <a
                key={boutique.slug}
                href={frontOfficeUrl(boutique)}
                className="group ds-card cursor-pointer no-underline transition hover:-translate-y-1 hover:shadow-lg"
                style={{ borderTop: `4px solid ${boutique.accent || 'var(--ds-primary)'}` }}
              >
                <div className="h-48 relative overflow-hidden rounded-2xl bg-[color:var(--ds-surface-container-low)]">
                  <img
                    src={boutique.image || boutique.logoUrl || '/img/hanooti-mark.svg'}
                    alt={boutique.name}
                    className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                    onError={(event) => {
                      event.currentTarget.src = '/img/hanooti-mark.svg';
                    }}
                  />

                  <div className="absolute inset-x-4 top-4 flex items-start justify-between gap-3">
                    <Badge tone={boutique.status === 'closed' ? 'error' : 'success'}>
                      {boutique.status === 'closed' ? 'Closed' : 'Open'}
                    </Badge>
                    <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white/90 text-[color:var(--ds-primary)] shadow-sm backdrop-blur">
                      <FontAwesomeIcon icon={appIcons.shop} />
                    </span>
                  </div>
                </div>

                <div className="mt-5 flex items-center gap-3">
                  <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[color:var(--ds-secondary-container)] text-[color:var(--ds-primary)] font-bold">
                    {boutique.name.charAt(0).toUpperCase()}
                  </div>
                  <div className="min-w-0">
                    <h3 className="truncate text-xl font-bold text-[color:var(--ds-on-surface)]">{boutique.name}</h3>
                    <p className="truncate text-sm text-[color:var(--ds-on-surface-variant)]">{boutique.category || 'Boutique'} • {boutique.city || 'Boutique en ligne'}</p>
                  </div>
                </div>

                <div className="mt-5 flex items-center justify-between gap-3">
                  <p className="text-sm text-[color:var(--ds-on-surface-variant)] line-clamp-2">
                    Explore curated products, store updates and a faster checkout flow.
                  </p>
                  <FontAwesomeIcon icon={appIcons.search} className="text-[color:var(--ds-primary)]" />
                </div>
              </a>
            ))
          ) : (
            <div className="ds-empty-state ds-card">
              <FontAwesomeIcon icon={appIcons.store} size="2x" />
              <strong>Aucune boutique trouvée</strong>
              <span>Essayez un autre mot-clé ou réinitialisez la recherche.</span>
              <Button type="button" variant="secondary" onClick={() => setQuery('')}>
                Réinitialiser
              </Button>
            </div>
          )}
        </div>
      </section>

      <section className="ds-page pb-16">
        <div className="rounded-3xl bg-[color:var(--ds-primary)] px-6 py-10 text-white md:px-10 md:py-14">
          <div className="flex flex-col gap-8 md:flex-row md:items-center md:justify-between">
            <div className="max-w-2xl">
              <h2 className="text-3xl font-bold md:text-4xl">Ready to scale your business?</h2>
              <p className="mt-4 text-white/80">
                Join our network of boutique owners and use a clearer storefront, better discovery, and smoother selling tools.
              </p>
            </div>
            <Button type="button" variant="secondary" onClick={() => { window.location.href = '/admin'; }}>
              Get Started
            </Button>
          </div>
        </div>
      </section>

      <footer className="border-t border-[color:var(--ds-outline-variant)] bg-[color:var(--ds-surface-container-lowest)]">
        <div className="ds-page grid gap-8 py-10 md:grid-cols-[1.2fr_0.8fr_0.8fr]">
          <div>
            <a className="flex items-center gap-3 font-bold text-[color:var(--ds-primary)] no-underline" href="/">
              <BrandLogo className="hanooti-logo--marketplace" />
            </a>
            <p className="mt-4 max-w-sm text-sm text-[color:var(--ds-on-surface-variant)]">
              Marketplace public pour découvrir les boutiques, explorer les vitrines et accéder au back-office.
            </p>
            <PlatformSocialLinks
              className="mt-5 flex flex-wrap items-center gap-3"
              itemClassName="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[color:var(--ds-outline-variant)] text-[color:var(--ds-on-surface-variant)] transition hover:-translate-y-0.5 hover:text-[color:var(--ds-primary)]"
            />
          </div>

          <div>
            <h3 className="text-sm font-bold uppercase tracking-[0.12em] text-[color:var(--ds-on-surface-variant)]">Explorer</h3>
            <ul className="mt-4 space-y-3 text-sm">
              <li><a className="no-underline text-[color:var(--ds-on-surface-variant)] hover:text-[color:var(--ds-on-surface)]" href="/boutiques">Boutiques</a></li>
              <li><a className="no-underline text-[color:var(--ds-on-surface-variant)] hover:text-[color:var(--ds-on-surface)]" href="/admin">Back-office</a></li>
            </ul>
          </div>

          <div>
            <h3 className="text-sm font-bold uppercase tracking-[0.12em] text-[color:var(--ds-on-surface-variant)]">Support</h3>
            <ul className="mt-4 space-y-3 text-sm">
              <li><span className="text-[color:var(--ds-on-surface-variant)]">CGV</span></li>
              <li><span className="text-[color:var(--ds-on-surface-variant)]">Confidentialité</span></li>
              <li><span className="text-[color:var(--ds-on-surface-variant)]">Contact</span></li>
            </ul>
          </div>
        </div>
      </footer>
    </main>
  );
}
