import { motion, useReducedMotion } from 'framer-motion';
import { ArrowRight, Sparkles, TrendingUp, X } from 'lucide-react';
import { useEffect, useRef, useState, type CSSProperties } from 'react';
import { useLocation } from 'react-router-dom';
import { ImageWithFallback } from '../../../components/ImageWithFallback';
import { boutiqueLink, boutiqueQuery, isBoutiqueSubdomain, resolveBoutiqueSlug } from '../boutiqueRouting';

type WeeklyTopProduct = {
  id: string;
  name: string;
  slug: string;
  shortDescription?: string | null;
  priceCents: number;
  comparePriceCents?: number | null;
  currency: string;
  imageUrl?: string | null;
  quantitySold: number;
};

function isEligiblePath(pathname: string, subdomain: boolean): boolean {
  if (subdomain) {
    return pathname === '/'
      || /^\/categories\/[^/]+\/?$/.test(pathname)
      || pathname === '/promotions'
      || pathname === '/client/account';
  }

  return /^\/boutiques\/[^/]+\/?$/.test(pathname)
    || /^\/boutiques\/[^/]+\/categories\/[^/]+\/?$/.test(pathname)
    || /^\/boutiques\/[^/]+\/promotions\/?$/.test(pathname)
    || /^\/boutiques\/[^/]+\/client\/account\/?$/.test(pathname);
}

function isBoutiquePath(pathname: string, subdomain: boolean): boolean {
  return subdomain || pathname.startsWith('/boutiques/');
}

function formatPrice(cents: number, currency: string): string {
  return `${(cents / 100).toFixed(2)} ${currency}`;
}

function currentWeekKey(): string {
  const date = new Date();
  const day = (date.getUTCDay() + 6) % 7;
  date.setUTCDate(date.getUTCDate() - day);

  return date.toISOString().slice(0, 10);
}

function referrerBelongsToBoutique(referrer: string, boutiqueSlug: string): boolean {
  if (!referrer) return false;

  try {
    const url = new URL(referrer);
    const pathSlug = url.pathname.match(/^\/boutiques\/([^/]+)/)?.[1];
    if (pathSlug === boutiqueSlug) return true;

    const labels = url.hostname.split('.');
    return labels.length >= 2 && labels[0] === boutiqueSlug;
  } catch {
    return false;
  }
}

function hasSeenProduct(seenKey: string, boutiqueSlug: string): boolean {
  try {
    return window.sessionStorage.getItem(seenKey) === '1'
      && referrerBelongsToBoutique(document.referrer, boutiqueSlug);
  } catch {
    return false;
  }
}

function isPageReload(): boolean {
  const navigation = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming | undefined;

  return navigation?.type === 'reload';
}

function markProductAsSeen(seenKey: string): void {
  try {
    window.sessionStorage.setItem(seenKey, '1');
  } catch {
    // Private browsing modes may deny access to Web Storage.
  }
}

export function BoutiqueWeeklyTopProductModal() {
  const location = useLocation();
  const reduceMotion = useReducedMotion();
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const activeBoutiqueRef = useRef<string | null>(null);
  const openedForVisitRef = useRef(false);
  const [product, setProduct] = useState<WeeklyTopProduct | null>(null);
  const [isOpen, setIsOpen] = useState(false);

  const pathBasedBoutique = location.pathname.startsWith('/boutiques/');
  const subdomain = !pathBasedBoutique && isBoutiqueSubdomain();
  const boutiqueSlug = resolveBoutiqueSlug(/^\/boutiques\/([^/]+)/);
  const boutiqueContext = isBoutiquePath(location.pathname, subdomain) && Boolean(boutiqueSlug);
  const eligible = boutiqueContext && isEligiblePath(location.pathname, subdomain);

  useEffect(() => {
    if (!boutiqueContext || !boutiqueSlug) {
      activeBoutiqueRef.current = null;
      openedForVisitRef.current = false;
      setProduct(null);
      setIsOpen(false);
      return;
    }

    if (activeBoutiqueRef.current !== boutiqueSlug) {
      activeBoutiqueRef.current = boutiqueSlug;
      openedForVisitRef.current = false;
      setProduct(null);
      setIsOpen(false);
    }

    if (!eligible || openedForVisitRef.current) return;

    openedForVisitRef.current = true;
    const seenKey = `hanooti.weekly-top-product.seen.${boutiqueSlug}.${currentWeekKey()}`;
    if (hasSeenProduct(seenKey, boutiqueSlug) && !isPageReload()) return;

    try {
      window.sessionStorage.removeItem(seenKey);
    } catch {
      // Private browsing modes may deny access to Web Storage.
    }
    const controller = new AbortController();

    fetch(`/api/public/most-ordered-product${boutiqueQuery(boutiqueSlug)}`, { signal: controller.signal })
      .then((response) => response.ok ? response.json() as Promise<WeeklyTopProduct> : null)
      .then((data) => {
        if (!data?.id || !data.slug) return;
        markProductAsSeen(seenKey);
        setProduct(data);
        setIsOpen(true);
      })
      .catch(() => {})

    return () => controller.abort();
  }, [boutiqueContext, boutiqueSlug, eligible]);

  useEffect(() => {
    if (!isOpen) return;

    closeButtonRef.current?.focus();
    const handleKeyDown = (event: KeyboardEvent) => {
      if ('Escape' === event.key) setIsOpen(false);
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen]);

  function close(): void {
    setIsOpen(false);
  }

  const detailHref = product ? boutiqueLink(`/products/${product.slug}`) : '#';
  const modalTheme = {
    '--weekly-modal-background': 'var(--sf-surface, var(--ds-surface, #f7f2e9))',
    '--weekly-modal-text': 'var(--sf-text, var(--ds-on-surface, #171717))',
    '--weekly-modal-muted': 'var(--sf-text-muted, var(--ds-on-surface-variant, #464555))',
    '--weekly-modal-accent': 'var(--ds-primary, #111111)',
    '--weekly-modal-surface': 'var(--sf-surface-accent, var(--ds-surface-container, #e8dfd1))',
    '--weekly-modal-on-accent': 'var(--ds-on-primary, #ffffff)',
    '--weekly-modal-font-family': 'var(--ds-font-family, inherit)',
  } as CSSProperties;
  return (
    <>
      {isOpen && product && (
        <div
          className="fixed inset-0 z-[120] flex items-center justify-center overflow-hidden bg-black/55 px-3 py-3 backdrop-blur-md sm:px-6 sm:py-6"
          role="presentation"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) close();
          }}
        >
          <div
            className="relative w-full max-w-4xl overflow-hidden rounded-[1.25rem] border border-white/20 bg-[color:var(--weekly-modal-background)] text-[color:var(--weekly-modal-text)] shadow-[0_32px_100px_rgba(0,0,0,0.35)] sm:rounded-[2rem]"
            style={{ ...modalTheme, fontFamily: 'var(--weekly-modal-font-family)' }}
            role="dialog"
            aria-modal="true"
            aria-labelledby="weekly-top-product-title"
            aria-describedby="weekly-top-product-description"
            onMouseDown={(event) => event.stopPropagation()}
          >
            <div className="pointer-events-none absolute -right-24 -top-24 h-64 w-64 rounded-full bg-[color:var(--sf-accent,var(--ds-primary,#111111))]/15 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-28 -left-20 h-64 w-64 rounded-full bg-[color:var(--weekly-modal-accent)]/20 blur-3xl" />

            <div className="pointer-events-none absolute inset-0 z-0 overflow-hidden" aria-hidden="true">
              <motion.span
                className="absolute -right-12 top-16 h-32 w-32 rounded-full bg-[color:var(--weekly-modal-accent)]/25 blur-3xl sm:h-48 sm:w-48"
                animate={reduceMotion ? { opacity: 0.35 } : { opacity: [0.2, 0.55, 0.2], scale: [0.9, 1.12, 0.9], x: [0, -18, 0], y: [0, 12, 0] }}
                transition={{ duration: 5.5, repeat: Infinity, ease: 'easeInOut' }}
              />
              <motion.span
                className="absolute -left-10 top-1/2 h-24 w-24 rounded-full bg-[color:var(--weekly-modal-accent)]/20 blur-3xl sm:h-36 sm:w-36"
                animate={reduceMotion ? { opacity: 0.25 } : { opacity: [0.15, 0.45, 0.15], scale: [1, 0.82, 1], x: [0, 20, 0] }}
                transition={{ duration: 6.5, repeat: Infinity, ease: 'easeInOut', delay: 0.6 }}
              />
              <motion.span
                className="absolute right-[22%] top-[30%] text-[color:var(--weekly-modal-accent)]"
                animate={reduceMotion ? { opacity: 0.55 } : { opacity: [0.25, 1, 0.25], scale: [0.75, 1.15, 0.75], rotate: [0, 18, 0] }}
                transition={{ duration: 3.2, repeat: Infinity, ease: 'easeInOut' }}
              >
                <Sparkles className="h-4 w-4 sm:h-5 sm:w-5" />
              </motion.span>
              <motion.span
                className="absolute right-[10%] top-[18%] text-[color:var(--weekly-modal-accent)]"
                animate={reduceMotion ? { opacity: 0.4 } : { opacity: [0.15, 0.8, 0.15], scale: [0.8, 1.25, 0.8], rotate: [12, -12, 12] }}
                transition={{ duration: 4.4, repeat: Infinity, ease: 'easeInOut', delay: 1.1 }}
              >
                <Sparkles className="h-3 w-3" />
              </motion.span>
              <motion.span
                className="absolute left-[18%] top-[62%] text-[color:var(--weekly-modal-accent)]"
                animate={reduceMotion ? { opacity: 0.35 } : { opacity: [0.1, 0.65, 0.1], scale: [0.7, 1.1, 0.7], rotate: [0, 24, 0] }}
                transition={{ duration: 5, repeat: Infinity, ease: 'easeInOut', delay: 0.8 }}
              >
                <Sparkles className="h-3.5 w-3.5" />
              </motion.span>
            </div>

            <button
              ref={closeButtonRef}
              type="button"
              onClick={close}
              className="absolute right-5 top-5 z-10 rounded-full border border-black/10 bg-white/70 p-2.5 text-[color:var(--weekly-modal-muted)] backdrop-blur hover:bg-white hover:text-[color:var(--weekly-modal-text)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black"
              aria-label="Fermer la présentation du produit"
            >
              <X className="h-4 w-4" />
            </button>

            <div className="relative grid lg:grid-cols-[1.05fr_0.95fr]">
              <div className="relative z-10 order-2 flex min-w-0 flex-col justify-center px-5 pb-6 pt-7 sm:px-12 sm:py-14 lg:order-1 lg:px-14">
                <div
                  className="inline-flex w-fit items-center gap-2 rounded-full border border-black/10 bg-white/65 px-3.5 py-2 text-[10px] font-bold uppercase tracking-[0.22em] text-[color:var(--weekly-modal-muted)]"
                >
                  <Sparkles className="h-3.5 w-3.5 text-[color:var(--sf-accent,var(--ds-primary,#111111))]" />
                  Tendance de la semaine
                </div>

                <p
                  className="mt-8 text-xs font-semibold uppercase tracking-[0.3em] text-[color:var(--weekly-modal-muted)]"
                >
                  Le produit préféré de nos clients
                </p>

                <h2
                  id="weekly-top-product-title"
                  className="mt-3 max-w-xl text-3xl font-semibold leading-[1] tracking-[-0.045em] sm:text-6xl sm:leading-[0.95] sm:tracking-[-0.055em]"
                >
                  {product.name}
                </h2>

                <p
                  id="weekly-top-product-description"
                  className="mt-5 line-clamp-3 max-w-lg text-sm leading-7 text-[color:var(--weekly-modal-muted)] sm:line-clamp-4 sm:text-base"
                >
                  {product.shortDescription || 'Découvrez le produit qui séduit le plus les visiteurs de cette boutique cette semaine.'}
                </p>

                <div
                  className="mt-7 flex flex-wrap items-center gap-3"
                >
                  <span className="inline-flex items-center gap-2 rounded-full bg-[color:var(--weekly-modal-accent)] px-4 py-2 text-xs font-semibold text-[color:var(--weekly-modal-on-accent)]">
                    <TrendingUp className="h-3.5 w-3.5 text-[color:var(--weekly-modal-on-accent)]" />
                    {product.quantitySold} unités commandées
                  </span>
                  <span className="text-lg font-semibold tracking-tight">
                    {formatPrice(product.priceCents, product.currency)}
                  </span>
                </div>

                <a
                  href={detailHref}
                  onClick={close}
                  className="mt-7 inline-flex w-full items-center justify-center gap-2 rounded-full px-5 py-3.5 text-sm font-semibold text-[color:var(--weekly-modal-on-accent)] shadow-lg shadow-black/10 hover:shadow-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black sm:mt-8 sm:w-fit sm:px-6"
                  style={{ backgroundColor: 'var(--weekly-modal-accent)' }}
                >
                  Voir le détail du produit
                  <ArrowRight className="h-4 w-4" />
                </a>
              </div>

              <div className="relative z-10 order-1 min-h-[190px] overflow-hidden bg-[color:var(--weekly-modal-surface)] sm:min-h-[390px] lg:order-2 lg:min-h-[520px]">
                <div className="absolute inset-0">
                  {product.imageUrl ? (
                    <ImageWithFallback src={product.imageUrl} alt={product.name} className="h-full w-full object-cover" />
                  ) : (
                    <div className="flex h-full items-center justify-center bg-gradient-to-br from-[#d8ccb9] via-[#efe8dd] to-[#c8b69c] px-8 text-center text-sm font-medium text-black/40">
                      Image produit indisponible
                    </div>
                  )}
                  <div className="absolute inset-0 bg-gradient-to-t from-black/35 via-transparent to-white/10" />
                </div>
                <div className="absolute bottom-3 left-3 rounded-full border border-white/35 bg-black/40 px-3 py-1.5 text-[9px] font-bold uppercase tracking-[0.16em] text-white backdrop-blur sm:bottom-5 sm:left-5 sm:px-3.5 sm:py-2 sm:text-[10px] sm:tracking-[0.2em]">
                  Sélection boutique
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
