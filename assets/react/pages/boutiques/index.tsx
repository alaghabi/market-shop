import { useDeferredValue, useMemo, useState, type CSSProperties } from 'react';
import { PublicHeader } from '../../components/PublicHeader';
import { frontOfficeUrl } from '../../backoffice/utils/frontOfficeUrl';
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

export function ActiveBoutiquesPage({ boutiques }: { boutiques: PublicBoutique[] }) {
  const [query, setQuery] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('Tous');
  const deferredQuery = useDeferredValue(query);

  const activeBoutiques = useMemo(() => boutiques.filter((boutique) => boutique.status === 'active' && boutique.isPublished === true && boutique.isVisiblePublicly !== false), [boutiques]);
  const categories = useMemo(() => ['Tous', ...Array.from(new Set(activeBoutiques.map((boutique) => boutique.category).filter((category): category is string => Boolean(category)))).slice(0, 6)], [activeBoutiques]);
  const filteredBoutiques = useMemo(() => {
    const normalized = deferredQuery.trim().toLowerCase();
    const category = selectedCategory.toLowerCase();

    return activeBoutiques.filter((boutique) => {
      const matchesCategory = category === 'tous' || (boutique.category ?? '').toLowerCase() === category;
      const matchesQuery = [boutique.name, boutique.category ?? '', boutique.city ?? ''].join(' ').toLowerCase().includes(normalized);

      return matchesCategory && matchesQuery;
    });
  }, [activeBoutiques, deferredQuery, selectedCategory]);

  return (
    <main className="lovable-home lovable-directory">
      <PublicHeader />

      <section className="lovable-directory__hero">
        <div className="lovable-container">
          <div className="lovable-directory__hero-card">
            <span className="lovable-pill">Boutiques actives</span>
            <h1>Trouvez les marchands disponibles sur Hanooti.</h1>
            <p>Parcourez les boutiques actives, filtrez par catégorie et ouvrez directement la vitrine du marchand.</p>
            <div className="lovable-directory__search" role="search">
              <span className="material-symbols-outlined">search</span>
              <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Rechercher une boutique, ville ou catégorie..." aria-label="Rechercher une boutique" />
            </div>
            <div className="lovable-directory__stats">
              <div><strong>{activeBoutiques.length}</strong><span>Boutiques actives</span></div>
              <div><strong>{categories.length - 1}</strong><span>Catégories</span></div>
              <div><strong>{filteredBoutiques.length}</strong><span>Résultats</span></div>
            </div>
          </div>
        </div>
      </section>

      <section className="lovable-section">
        <div className="lovable-container">
          <div className="lovable-directory__filters" aria-label="Filtres catégories">
            {categories.map((category) => (
              <button className={category === selectedCategory ? 'is-active' : ''} key={category} type="button" onClick={() => setSelectedCategory(category)}>
                {category}
              </button>
            ))}
          </div>

          <div className="lovable-directory__grid">
            {filteredBoutiques.length > 0 ? filteredBoutiques.map((boutique) => (
              <a className="lovable-card lovable-directory__card" key={boutique.slug} href={frontOfficeUrl(boutique)} style={{ '--boutique-accent': boutique.accent || '#0369A1' } as CSSProperties}>
                <div className="lovable-directory__image">
                    <img src={boutique.image || boutique.logoUrl || '/img/hanooti-mark.svg'} alt={`Boutique ${boutique.name}`} onError={(event) => { event.currentTarget.src = '/img/hanooti-mark.svg'; }} />
                  <span><span className="material-symbols-outlined">verified</span> Active</span>
                </div>
                <div className="lovable-directory__body">
                  <div className="lovable-directory__avatar">{boutique.name.charAt(0).toUpperCase()}</div>
                  <div>
                    <h2>{boutique.name}</h2>
                    <p>{boutique.category || 'Boutique'} · {boutique.city || 'En ligne'}</p>
                  </div>
                </div>
                <div className="lovable-directory__footer">
                  <span>Voir la boutique</span>
                  <span className="material-symbols-outlined">arrow_forward</span>
                </div>
              </a>
            )) : (
              <div className="lovable-directory__empty">
                <span className="material-symbols-outlined">storefront</span>
                <strong>Aucune boutique active trouvée</strong>
                <p>Modifiez votre recherche ou choisissez une autre catégorie.</p>
                <button className="lovable-button lovable-button--secondary" type="button" onClick={() => { setQuery(''); setSelectedCategory('Tous'); }}>Réinitialiser</button>
              </div>
            )}
          </div>
        </div>
      </section>
      <footer className="lovable-footer">
        <div className="lovable-container">
          <div className="lovable-footer__brand">
            <a className="lovable-brand" href="/">Hanooti</a>
            <p>Découvrez les boutiques et les commerçants présents sur Hanooti.</p>
            <PlatformSocialLinks className="lovable-footer__socials" itemClassName="lovable-footer__social-link" />
          </div>
        </div>
      </footer>
    </main>
  );
}
