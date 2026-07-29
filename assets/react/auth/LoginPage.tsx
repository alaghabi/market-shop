import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import type { RegisterPayload, RegistrationResult } from './AuthProvider';
import { appIcons } from '../icons/fontAwesome';
import { BrandLogo } from '../components/BrandLogo';
import { SOCIAL_PROVIDERS, type SocialProvider } from './socialProviders';

type LoginPageProps = {
  onSignIn: (email: string, password: string) => Promise<void>;
  onSignInWithProvider?: (provider?: SocialProvider) => Promise<void>;
  onSignUp: (payload: RegisterPayload) => Promise<RegistrationResult>;
  initialMode?: 'login' | 'register';
};

type PublicBoutique = {
  status?: string;
  isPublished?: boolean;
  isVisiblePublicly?: boolean;
};

type PublicReview = {
  rating: number;
};

type CollectionPayload<T> = T[] | { member?: T[]; items?: T[] };

function getCollection<T>(payload: CollectionPayload<T>): T[] {
  return Array.isArray(payload) ? payload : payload.member ?? payload.items ?? [];
}

export function LoginPage({ onSignIn, onSignInWithProvider, onSignUp, initialMode = 'login' }: LoginPageProps) {
  const [mode, setMode] = useState<'login' | 'register'>(initialMode);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [displayName, setDisplayName] = useState('');
  const [boutiqueName, setBoutiqueName] = useState('');
  const [boutiqueSlug, setBoutiqueSlug] = useState('');
  const [rememberMe, setRememberMe] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [publicBoutiquesCount, setPublicBoutiquesCount] = useState<number | null>(null);
  const [publicReviewsCount, setPublicReviewsCount] = useState<number | null>(null);
  const [isLoadingPublicMetrics, setIsLoadingPublicMetrics] = useState(true);

  useEffect(() => {
    const controller = new AbortController();

    Promise.all([
      fetch('/api/boutiques', { signal: controller.signal }).then((response) => response.ok ? response.json() as Promise<CollectionPayload<PublicBoutique>> : Promise.reject(new Error('Boutiques indisponibles.'))),
      fetch('/api/platform/reviews', { signal: controller.signal }).then((response) => response.ok ? response.json() as Promise<CollectionPayload<PublicReview>> : Promise.reject(new Error('Avis indisponibles.'))),
    ])
      .then(([boutiquesPayload, reviewsPayload]) => {
        const boutiques = getCollection(boutiquesPayload).filter((boutique) => boutique.status === 'active' && boutique.isPublished === true && boutique.isVisiblePublicly !== false);
        const reviews = getCollection(reviewsPayload);

        setPublicBoutiquesCount(boutiques.length);
        setPublicReviewsCount(reviews.length);
      })
      .catch((exception: unknown) => {
        if (!(exception instanceof DOMException && exception.name === 'AbortError')) {
          setPublicBoutiquesCount(null);
          setPublicReviewsCount(null);
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setIsLoadingPublicMetrics(false);
      });

    return () => controller.abort();
  }, []);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setSuccess(null);
    setIsSubmitting(true);

    try {
      if ('login' === mode) {
        await onSignIn(email, password);
      } else {
        const result = await onSignUp({ email, password, displayName, boutiqueName, boutiqueSlug });
        setSuccess(result.message);
        setMode('login');
      }
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Action impossible.');
    } finally {
      setIsSubmitting(false);
    }
  }

  const isRegister = mode === 'register';

  return (
    <main className="lovable-auth">
      <div className="lovable-auth__shell">
          <section className="lovable-auth__panel" aria-label="Présentation Hanooti">
          <Link className="lovable-brand" to="/">
            <BrandLogo />
          </Link>
          <span className="lovable-pill">Marketplace B2B</span>
          <h1>{isRegister ? 'Lancez votre boutique professionnelle en quelques minutes.' : 'Reconnectez-vous à votre espace boutique.'}</h1>
           <p>Gérez vos produits, vos commandes, vos transporteurs et votre présence marketplace depuis une interface pensée pour les indépendants.</p>
           <div className="lovable-auth__proof-grid" aria-live="polite">
             <div><strong>{isLoadingPublicMetrics ? '—' : publicBoutiquesCount?.toLocaleString('fr-FR') ?? '—'}</strong><span>Boutiques actives</span></div>
             <div><strong>{isLoadingPublicMetrics ? '—' : publicReviewsCount?.toLocaleString('fr-FR') ?? '0'}</strong><span>Avis approuvés</span></div>
           </div>
          <div className="lovable-auth__feature-card">
            <span className="lovable-auth__icon"><FontAwesomeIcon icon={appIcons.dashboard} /></span>
            <div>
              <strong>Console complète</strong>
              <span>Inventaire, commandes, livraison et statistiques en temps réel.</span>
            </div>
          </div>
        </section>

        <section className="lovable-auth__form-panel" aria-label={isRegister ? 'Inscription' : 'Connexion'}>
          <div className="lovable-auth__form-card">
            <header>
              <Link className="lovable-auth__mobile-brand" to="/">
                <BrandLogo />
              </Link>
              <p className="lovable-auth__eyebrow">{isRegister ? 'Inscription boutique' : 'Connexion sécurisée'}</p>
              <h2>{isRegister ? 'Créer votre compte' : 'Bienvenue'}</h2>
              <p>{isRegister ? 'Renseignez les informations de base pour demander votre espace boutique.' : 'Connectez-vous pour accéder au back-office Hanooti.'}</p>
            </header>

            <div className="lovable-auth__tabs" role="tablist" aria-label="Mode authentification">
              <button type="button" className={!isRegister ? 'is-active' : ''} onClick={() => setMode('login')}>Connexion</button>
              <button type="button" className={isRegister ? 'is-active' : ''} onClick={() => setMode('register')}>Inscription</button>
            </div>

            {onSignInWithProvider && !isRegister && (
              <div className="lovable-auth__social-login">
                <button
                  type="button"
                  className="lovable-auth__keycloak"
                  onClick={() => {
                    setError(null);
                    void onSignInWithProvider().catch((exception: unknown) => {
                      setError(exception instanceof Error ? exception.message : 'Connexion SSO impossible.');
                    });
                  }}
                >
                  Continuer avec Hanooti SSO
                </button>
                <div className="lovable-auth__social-divider" aria-hidden="true"><span>ou continuer avec</span></div>
                <div className="lovable-auth__social-grid" aria-label="Connexion avec un fournisseur externe">
                  {SOCIAL_PROVIDERS.map((provider) => (
                    <button
                      key={provider.id}
                      type="button"
                      className="lovable-auth__social-button"
                      onClick={() => {
                        setError(null);
                        void onSignInWithProvider(provider.id).catch((exception: unknown) => {
                          setError(exception instanceof Error ? exception.message : `Connexion ${provider.label} impossible.`);
                        });
                      }}
                    >
                      <FontAwesomeIcon icon={appIcons[provider.icon as keyof typeof appIcons]} aria-hidden="true" />
                      <span>{provider.label}</span>
                    </button>
                  ))}
                </div>
              </div>
            )}

            <form className="lovable-auth__form" onSubmit={submit}>
              <label>
                <span>Email professionnel</span>
                <div className="lovable-auth__field">
                    <input
                      id="email"
                      type="email"
                      value={email}
                      onChange={(event) => setEmail(event.target.value)}
                      required
                      placeholder="vous@entreprise.com"
                    />
                    <FontAwesomeIcon icon={appIcons.mail} />
                </div>
              </label>

              <label>
                <span>Mot de passe</span>
                <div className="lovable-auth__field lovable-auth__field--password">
                  <FontAwesomeIcon icon={appIcons.lock} />
                    <input
                      id="password"
                      type={showPassword ? 'text' : 'password'}
                      value={password}
                      onChange={(event) => setPassword(event.target.value)}
                      required
                      placeholder="••••••••"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword((current) => !current)}
                      aria-label={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                    >
                      <FontAwesomeIcon icon={showPassword ? appIcons.eyeSlash : appIcons.eye} />
                    </button>
                </div>
              </label>

              {isRegister && (
                <div className="lovable-auth__register-fields">
                  <label>
                    <span>Nom complet</span>
                    <input value={displayName} onChange={(event) => setDisplayName(event.target.value)} placeholder="Admin Boutique" />
                  </label>
                  <label>
                    <span>Nom de la boutique</span>
                    <input value={boutiqueName} onChange={(event) => setBoutiqueName(event.target.value)} required placeholder="Luxe Paris" />
                  </label>
                  <label>
                    <span>Slug boutique</span>
                    <input value={boutiqueSlug} onChange={(event) => setBoutiqueSlug(event.target.value)} required pattern="[a-z0-9-]+" placeholder="luxe-paris" />
                  </label>
                  </div>
                )}

              <div className="lovable-auth__meta-row">
                <label className="lovable-auth__checkbox">
                  <input type="checkbox" checked={rememberMe} onChange={(event) => setRememberMe(event.target.checked)} />
                  <span>Se souvenir de moi</span>
                </label>
                <Link to="/auth/forgot-password">Mot de passe oublié ?</Link>
              </div>

              {error && <div className="lovable-auth__error">{error}</div>}
              {success && <div className="lovable-auth__success">{success}</div>}

              <button type="submit" disabled={isSubmitting} className="lovable-button lovable-auth__submit">
                <FontAwesomeIcon icon={isRegister ? appIcons.plus : appIcons.login} />
                {isSubmitting ? 'Traitement...' : isRegister ? 'Créer mon espace boutique' : 'Se connecter'}
              </button>
            </form>

            <footer className="lovable-auth__footer">
              <button type="button" onClick={() => setMode(isRegister ? 'login' : 'register')}>
                {isRegister ? 'J’ai déjà un compte' : 'Créer une boutique'}
              </button>
              <div>
                 <a href="mailto:contact@hanooti.com?subject=Demande%20d%27aide">Aide</a>
                 <a href="mailto:contact@hanooti.com?subject=Question%20confidentialite">Confidentialité</a>
              </div>
            </footer>
          </div>
        </section>
      </div>
    </main>
  );
}
