import { Link, useNavigate } from 'react-router-dom';
import { useEffect, useState, type FormEvent } from 'react';
import { useAuth } from '../../auth/useAuth';
import { BrandLogo } from '../../components/BrandLogo';
import { FloatingInfoChat } from '../../components/FloatingInfoChat';
import { PublicHeader } from '../../components/PublicHeader';
import { frontOfficeUrl } from '../../backoffice/utils/frontOfficeUrl';
import { formatReviewDate, reviewInitial, usePlatformReviews } from '../application-reviews/platformReviews';
import { PlatformSocialLinks } from '../../components/PlatformSocialLinks';
import { SubscriptionPlansSlider } from '../../components/SubscriptionPlansSlider';

type HomeBoutique = {
  name: string;
  slug: string;
  category?: string | null;
  city?: string | null;
  status?: string;
  customDomain?: string | null;
  isPublished?: boolean;
  isVisiblePublicly?: boolean;
  productsCount?: number;
};

export function HomePage({ canAccessBackOffice, boutiques }: { canAccessBackOffice: boolean; boutiques: HomeBoutique[] }) {
  const navigate = useNavigate();
  const { user, signOut } = useAuth();
  const isAuthenticated = Boolean(user);
  const { reviews: platformReviews, isLoading: platformReviewsLoading } = usePlatformReviews();
  const publishedBoutiques = boutiques.filter((boutique) => boutique.status === 'active' && boutique.isPublished === true && boutique.isVisiblePublicly !== false);
  const featuredBoutiques = publishedBoutiques.slice(0, 6);
  const [activeBoutiqueIndex, setActiveBoutiqueIndex] = useState(0);
  const [newsletterEmail, setNewsletterEmail] = useState('');

  useEffect(() => {
    if (featuredBoutiques.length <= 3 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return undefined;

    const interval = window.setInterval(() => {
      setActiveBoutiqueIndex((current) => (current + 1) % featuredBoutiques.length);
    }, 4500);

    return () => window.clearInterval(interval);
  }, [featuredBoutiques.length]);

  const visibleBoutiques = featuredBoutiques.length <= 3
    ? featuredBoutiques
    : Array.from({ length: 3 }, (_, index) => featuredBoutiques[(activeBoutiqueIndex + index) % featuredBoutiques.length]);
  const totalProducts = publishedBoutiques.reduce((total, boutique) => total + (boutique.productsCount ?? 0), 0);

  function handleNewsletterSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const email = newsletterEmail.trim();
    if (!email) return;

    window.location.href = `mailto:contact@hanooti.com?subject=Inscription%20newsletter%20Hanooti&body=${encodeURIComponent(`Je souhaite recevoir les conseils Hanooti avec l'adresse ${email}.`)}`;
  }
  const products = [
    { shop: "L'Atelier Cuir", name: 'Sac en cuir tannage végétal', price: '189,00 DT', icon: 'shopping_bag', badge: 'Nouveau' },
    { shop: 'TechDirect B2B', name: 'Casque sans fil Pro X2', price: '249,00 DT', icon: 'headphones', badge: 'Nouveau' },
    { shop: 'Gourmet Select', name: "Coffret huile d'olive AOP", price: '62,00 DT', icon: 'restaurant', badge: 'Édition limitée' },
    { shop: 'Design & Co', name: 'Lampe sculpturale chêne', price: '320,00 DT', icon: 'lightbulb', badge: 'Nouveau' },
  ];
  const steps = [
    { icon: 'person_add', title: 'Créez votre compte', text: 'Inscrivez-vous en quelques clics et complétez votre profil professionnel pour inspirer confiance.' },
    { icon: 'settings_suggest', title: 'Personnalisez votre boutique', text: 'Ajoutez votre logo, vos couleurs et listez vos produits avec descriptions précises et photos HD.' },
    { icon: 'payments', title: 'Commencez à vendre', text: 'Recevez des commandes, gérez vos expéditions et soyez payé directement de manière sécurisée.' },
  ];
  const benefits = [
    { icon: 'trending_up', title: 'Visibilité accrue', text: "Accédez à un réseau de milliers d'acheteurs B2B en France et à l'international." },
    { icon: 'dashboard_customize', title: 'Outils de gestion pro', text: 'Une console admin puissante pour gérer stocks, factures et statistiques en temps réel.' },
    { icon: 'verified_user', title: 'Paiement sécurisé', text: 'Toutes les transactions sont cryptées et protégées par nos protocoles bancaires certifiés.' },
  ];

  return (
    <main className="lovable-home">
      <PublicHeader canAccessBackOffice={canAccessBackOffice} isAuthenticated={isAuthenticated} onSignOut={signOut} />

      <section className="lovable-hero">
        <div className="lovable-container lovable-hero__inner">
          <span className="lovable-pill">Solution Professionnelle</span>
          <h1>La Marketplace B2B pour les Indépendants</h1>
          <p>Propulsez votre activité commerciale avec une plateforme dédiée. Hanooti simplifie la gestion de votre boutique, de l&apos;inventaire à la vente sécurisée.</p>
          <div className="lovable-hero__actions">
            <button className="lovable-button" type="button" onClick={() => { navigate('/boutiques'); }}>Explorer les Boutiques <span className="material-symbols-outlined">arrow_forward</span></button>
            <button className="lovable-button lovable-button--secondary" type="button" onClick={() => { navigate('/admin'); }}>Créer ma Boutique</button>
            <button className="lovable-button lovable-button--secondary" type="button" onClick={() => { navigate('/suggestions'); }}>Boîte à suggestions</button>
          </div>
          <div className="lovable-stats">
            <div><strong>{publishedBoutiques.length.toLocaleString('fr-FR')}</strong><span>Boutiques actives</span></div>
            <div><strong>{platformReviewsLoading ? '—' : platformReviews.length.toLocaleString('fr-FR')}</strong><span>Avis approuvés</span></div>
            <div><strong>{totalProducts.toLocaleString('fr-FR')}</strong><span>Produits référencés</span></div>
          </div>
        </div>
      </section>

      <SubscriptionPlansSlider canAccessBackOffice={canAccessBackOffice} />

      <section className="lovable-section lovable-section--muted" id="Boutiques">
        <div className="lovable-container">
          <SectionHeader title="Boutiques à la Une" text="Découvrez les marchands qui font la différence sur Hanooti." align="split" />
          <div className="lovable-boutique-grid" role="region" aria-roledescription="carrousel" aria-label="Boutiques publiées">
            {featuredBoutiques.length > 0 ? visibleBoutiques.map((boutique) => (
              <article className="lovable-card lovable-boutique-card" key={boutique.slug}>
                <div className="lovable-avatar lovable-avatar--lg">{boutique.name.charAt(0).toUpperCase()}</div>
                <h3>{boutique.name}</h3>
                <p>{boutique.category || boutique.city || 'Boutique en ligne'}</p>
                <div className="lovable-rating"><span>Boutique publiée</span></div>
                <div className="lovable-badges"><span><span className="material-symbols-outlined">verified</span> Vérifié</span></div>
                <button type="button" onClick={() => { window.location.assign(frontOfficeUrl(boutique)); }}>Voir la boutique <span className="material-symbols-outlined">arrow_forward</span></button>
              </article>
            )) : (
              <div className="lovable-card lovable-boutique-card">
                <h3>Aucune boutique publiée</h3>
                <p>Les boutiques apparaîtront ici après leur publication.</p>
              </div>
            )}
          </div>
        </div>
      </section>

      <section className="lovable-section" id="nouveautes">
        <div className="lovable-container">
          <SectionHeader title="Nouveaux Produits" text="Les dernières nouveautés ajoutées par nos marchands." align="split" />
          <div className="lovable-product-grid">
            {products.map((product) => (
              <article className="lovable-card lovable-product-card" key={product.name}>
                <div className="lovable-product-card__media">
                  <span className="lovable-product-badge">{product.badge}</span>
                  <span className="material-symbols-outlined lovable-product-icon">{product.icon}</span>
                </div>
                <div className="lovable-product-card__body">
                  <p>{product.shop}</p>
                  <h3>{product.name}</h3>
                  <strong>{product.price}</strong>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="lovable-section lovable-section--reviews" id="avis">
        <div className="lovable-container">
          <SectionHeader eyebrow="reviews" eyebrowText="Témoignages" title="Avis Hanooti" text="Découvrez ce que les utilisateurs disent de l’application Hanooti." />
          <div className="lovable-review-grid">
            {platformReviewsLoading ? <div className="lovable-card lovable-review-card"><p>Chargement des avis...</p></div> : platformReviews.length > 0 ? platformReviews.slice(0, 6).map((review) => (
              <article className="lovable-card lovable-review-card" key={review.id}>
                <div className="lovable-review-card__header">
                  <div className="lovable-review-card__author"><span className="lovable-avatar">{reviewInitial(review.authorName)}</span><div><strong>{review.authorName}</strong><small>Utilisateur Hanooti</small></div></div>
                  <time dateTime={review.createdAt}>{formatReviewDate(review.createdAt)}</time>
                </div>
                <StarRating rating={review.rating} />
                <p>{review.comment ?? 'Avis noté par un utilisateur Hanooti.'}</p>
                {review.isVerifiedPurchase && <span className="lovable-verified"><span className="material-symbols-outlined">verified</span> Achat vérifié</span>}
              </article>
            )) : <div className="lovable-card lovable-review-card"><h3>Aucun avis publié</h3><p>Les premiers retours apparaîtront ici après validation.</p></div>}
          </div>
          <button className="lovable-button lovable-button--secondary lovable-centered-button" type="button" onClick={() => { navigate('/avis'); }}>Voir tous les avis <span className="material-symbols-outlined">arrow_forward</span></button>
        </div>
      </section>

      <section className="lovable-section" id="fonctionnement">
        <div className="lovable-container">
          <SectionHeader eyebrow="rocket_launch" eyebrowText="Démarrage rapide" title="Comment ça marche" text="Trois étapes simples pour transformer votre commerce." />
          <div className="lovable-step-grid">
            {steps.map((step, index) => (
              <article className="lovable-card lovable-step-card" key={step.title}>
                <span className="lovable-step-card__number">{index + 1}</span>
                <span className="material-symbols-outlined lovable-step-card__icon">{step.icon}</span>
                <h3>{step.title}</h3>
                <p>{step.text}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="lovable-section lovable-benefits">
        <div className="lovable-container">
          <SectionHeader eyebrow="stars" eyebrowText="Avantages" title="Pourquoi Hanooti ?" />
          <div className="lovable-benefit-grid">
            {benefits.map((benefit) => (
              <article className="lovable-card lovable-benefit-card" key={benefit.title}>
                <span className="material-symbols-outlined">{benefit.icon}</span>
                <h3>{benefit.title}</h3>
                <p>{benefit.text}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="lovable-newsletter">
        <div className="lovable-container">
          <div className="lovable-newsletter__card">
            <span className="material-symbols-outlined">mail</span>
            <h2>Restez informé</h2>
            <p>Recevez nos conseils pour booster votre boutique et les dernières tendances du marché.</p>
             <form onSubmit={handleNewsletterSubmit}>
               <input type="email" value={newsletterEmail} onChange={(event) => setNewsletterEmail(event.target.value)} placeholder="vous@email.com" aria-label="Adresse email" required />
               <button type="submit">Recevoir les conseils</button>
             </form>
          </div>
        </div>
      </section>

      <footer className="lovable-footer">
        <div className="lovable-container">
          <div className="lovable-footer__top">
             <div className="lovable-footer__brand"><Link className="lovable-brand" to="/"><BrandLogo /></Link><p>La plateforme de référence pour les commerçants indépendants cherchant excellence et efficacité.</p><PlatformSocialLinks className="lovable-footer__socials" itemClassName="lovable-footer__social-link" /></div>
             <FooterColumn title="Explorer" links={[{ label: 'Boutiques', href: '/boutiques' }, { label: 'Vendeurs', href: '/auth/register' }, { label: 'Catégories', href: '/#Boutiques' }]} />
             <FooterColumn title="Société" links={[{ label: 'À propos', href: '/#fonctionnement' }, { label: 'Contact', href: 'mailto:contact@hanooti.com' }, { label: 'Nouveautés', href: '/#nouveautes' }]} />
             <FooterColumn title="Légal" links={[{ label: 'CGV', href: 'mailto:contact@hanooti.com?subject=Demande%20CGV' }, { label: 'Confidentialité', href: 'mailto:contact@hanooti.com?subject=Question%20confidentialite' }, { label: 'Cookies', href: 'mailto:contact@hanooti.com?subject=Question%20cookies' }]} />
          </div>
          <p className="lovable-footer__bottom">© 2026 Market Shop - Hanooti. Tous droits réservés.</p>
        </div>
      </footer>
      {localStorage.getItem('hanooti_global_chat_enabled') !== 'false' && (
        <FloatingInfoChat title="Assistant Hanooti" welcomeMessage="Bonjour, je suis l’assistant général Hanooti. Comment puis-je vous aider ?" />
      )}
    </main>
  );
}

function StarRating({ rating }: { rating: number }) {
  return (
    <span className="lovable-stars" aria-label={`${rating} étoiles sur 5`}>
      {Array.from({ length: 5 }, (_, index) => (
        <span className="material-symbols-outlined" aria-hidden="true" key={index}>{index < rating ? 'star' : 'star_outline'}</span>
      ))}
    </span>
  );
}

function SectionHeader({ eyebrow, eyebrowText, title, text, align = 'center' }: { eyebrow?: string; eyebrowText?: string; title: string; text?: string; align?: 'center' | 'split' }) {
  return (
    <div className={`lovable-section-header lovable-section-header--${align}`}>
      <div>
        {eyebrow && <span className="lovable-pill"><span className="material-symbols-outlined">{eyebrow}</span>{eyebrowText}</span>}
        <h2>{title}</h2>
        {text && <p>{text}</p>}
      </div>
      {align === 'split' && <Link to="/boutiques">Tout voir <span className="material-symbols-outlined">arrow_forward</span></Link>}
    </div>
  );
}

function FooterColumn({ title, links }: { title: string; links: Array<{ label: string; href: string }> }) {
  return (
    <div className="lovable-footer__column">
      <h4>{title}</h4>
      {links.map((link) => <a href={link.href} key={link.label}>{link.label}</a>)}
    </div>
  );
}
