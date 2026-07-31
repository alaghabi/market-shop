import { useEffect, useRef, useState, useCallback } from 'react';
import { motion, useReducedMotion } from 'framer-motion';
import { useApiData } from '../backoffice/hooks/useApi';
import { Button } from '../backoffice/components/Button';
import { appIcons } from '../icons/fontAwesome';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { useAuth } from '../auth/useAuth';

type Quota = {
  quotaCode: string;
  quotaName: string;
  limitValue: number | null;
};

type SubscriptionPlan = {
  id: string;
  name: string;
  description?: string | null;
  priceTnd: number;
  renewalPriceTnd?: number | null;
  effectiveRenewalPriceTnd?: number;
  isFree: boolean;
  isActive: boolean;
  isVisible: boolean;
  durationMonths: number;
  currency: string;
  quotas: Quota[];
};

const QUOTA_ICONS: Record<string, keyof typeof appIcons> = {
  max_products: 'products',
  max_customers: 'users',
  max_boutiques: 'store',
};

const QUOTA_LABELS: Record<string, string> = {
  max_products: 'Produits',
  max_customers: 'Clients',
  max_boutiques: 'Boutiques',
};

const CARD_VARIANTS = {
  hidden: { opacity: 0, y: 30, scale: 0.95 },
  visible: (index: number) => ({
    opacity: 1,
    y: 0,
    scale: 1,
    transition: {
      type: 'spring' as const,
      stiffness: 300,
      damping: 30,
      delay: index * 0.08,
      duration: 0.6,
    },
  }),
  hover: {
    y: -8,
    scale: 1.02,
    boxShadow: '0 25px 50px -12px rgba(217, 70, 239, 0.25), 0 0 0 1px rgba(217, 70, 239, 0.1)',
    transition: { type: 'spring' as const, stiffness: 400, damping: 25 },
  },
} as const;

const QUOTA_VARIANTS = {
  hidden: { opacity: 0, y: 10 },
  visible: (i: number) => ({
    opacity: 1,
    y: 0,
    transition: { delay: 0.2 + i * 0.05, type: 'spring' as const, stiffness: 500, damping: 30 },
  }),
} as const;

async function fetchPublicPlans(): Promise<{ member?: SubscriptionPlan[]; items?: SubscriptionPlan[] }> {
  const response = await fetch('/api/public/subscription-plans?itemsPerPage=100', {
    headers: { Accept: 'application/json' },
  });
  if (!response.ok) {
    throw new Error(`Impossible de charger les plans (${response.status})`);
  }

  return response.json();
}

export function SubscriptionPlansSlider({ canAccessBackOffice }: { canAccessBackOffice: boolean }) {
  const { user } = useAuth();
  const isAuthenticated = Boolean(user);
  const prefersReducedMotion = useReducedMotion();
  const [activeIndex, setActiveIndex] = useState(0);
  const [isHovering, setIsHovering] = useState(false);
  const [touchStart, setTouchStart] = useState<number | null>(null);
  const scrollRef = useRef<HTMLDivElement>(null);
  const cardsRef = useRef<(HTMLDivElement | null)[]>([]);

  const { data: plansData, isLoading, error } = useApiData(fetchPublicPlans, []);

  const plans = plansData?.member ?? plansData?.items ?? [];
  const activePlans = plans.filter((p: SubscriptionPlan) => p.isActive && p.isVisible);

  const cardsPerView = typeof window !== 'undefined'
    ? window.innerWidth >= 1024 ? 4 : window.innerWidth >= 640 ? 2 : 1
    : 4;

  const shouldAutoSlide = activePlans.length > cardsPerView && !prefersReducedMotion;
  const maxIndex = Math.max(0, activePlans.length - cardsPerView);

  const scrollToIndex = useCallback((index: number) => {
    if (!scrollRef.current) return;
    const clamped = Math.max(0, Math.min(index, maxIndex));
    setActiveIndex(clamped);
    const card = cardsRef.current[clamped];
    if (card) {
      card.scrollIntoView({ behavior: prefersReducedMotion ? 'instant' : 'smooth', inline: 'start' });
    }
  }, [maxIndex, prefersReducedMotion]);

  const handleNext = () => scrollToIndex(activeIndex + 1);
  const handlePrev = () => scrollToIndex(activeIndex - 1);

  useEffect(() => {
    if (!shouldAutoSlide) return;
    const interval = window.setInterval(() => {
      if (!isHovering) {
        scrollToIndex(activeIndex >= maxIndex ? 0 : activeIndex + 1);
      }
    }, 5000);
    return () => window.clearInterval(interval);
  }, [shouldAutoSlide, isHovering, activeIndex, maxIndex, scrollToIndex]);

  useEffect(() => {
    const handleResize = () => {
      const newCardsPerView = window.innerWidth >= 1024 ? 4 : window.innerWidth >= 640 ? 2 : 1;
      if (newCardsPerView !== cardsPerView) {
        setActiveIndex(0);
      }
    };
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  const handleTouchStart = (e: React.TouchEvent) => {
    setTouchStart(e.touches[0].clientX);
  };

  const handleTouchEnd = (e: React.TouchEvent) => {
    if (touchStart === null) return;
    const diff = touchStart - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 50) {
      diff > 0 ? handleNext() : handlePrev();
    }
    setTouchStart(null);
  };

  const formatPrice = (plan: SubscriptionPlan) => {
    if (plan.isFree) return 'Gratuit';
    const main = `${plan.priceTnd.toLocaleString('fr-FR')} ${plan.currency}`;
    const renewal = plan.renewalPriceTnd != null && plan.renewalPriceTnd !== plan.priceTnd
      ? ` · renouv. ${(plan.effectiveRenewalPriceTnd ?? plan.renewalPriceTnd).toLocaleString('fr-FR')} ${plan.currency}`
      : '';
    return `${main}${renewal}/${plan.durationMonths === 1 ? 'mois' : plan.durationMonths + ' mois'}`;
  };

  const getQuotaValue = (plan: SubscriptionPlan, code: string) => {
    const quota = plan.quotas.find((q: Quota) => q.quotaCode === code);
    return quota?.limitValue ?? null;
  };

  const handleCtaClick = () => {
    window.location.href = isAuthenticated || canAccessBackOffice
      ? '/admin/abonnements'
      : '/auth/register';
  };

  if (isLoading || activePlans.length === 0) {
    return null;
  }

  if (error) {
    console.error('Failed to load subscription plans:', error);
    return null;
  }

  return (
    <section className="ds-page py-16" id="plans" aria-labelledby="plans-title">
      <div className="ds-page__container">
        <div className="lovable-section-header lovable-section-header--split mb-10">
          <div>
            <span className="lovable-pill">
              <FontAwesomeIcon icon={appIcons.promotions} className="mr-2" aria-hidden="true" />
              Nos Abonnements
            </span>
            <h2 id="plans-title" className="ds-hero__title mt-2">Choisissez le plan qui vous convient</h2>
            <p className="ds-hero__subtitle mt-3 max-w-2xl">
              Tous nos plans incluent l'accès complet aux fonctionnalités essentielles. Changez ou annulez à tout moment.
            </p>
          </div>
          <div className="flex items-center gap-2" role="group" aria-label="Navigation des plans">
            <button
              type="button"
              onClick={handlePrev}
              disabled={activeIndex === 0}
              className="p-2 rounded-full bg-[color:var(--ds-surface-container-low)] text-[color:var(--ds-on-surface)] hover:bg-[color:var(--ds-primary)] hover:text-white transition-colors duration-200 disabled:opacity-30 disabled:cursor-not-allowed"
              aria-label="Plans précédents"
            >
              <FontAwesomeIcon icon={appIcons.arrowRight} flip="horizontal" size="sm" />
            </button>
            <button
              type="button"
              onClick={handleNext}
              disabled={activeIndex >= maxIndex}
              className="p-2 rounded-full bg-[color:var(--ds-surface-container-low)] text-[color:var(--ds-on-surface)] hover:bg-[color:var(--ds-primary)] hover:text-white transition-colors duration-200 disabled:opacity-30 disabled:cursor-not-allowed"
              aria-label="Plans suivants"
            >
              <FontAwesomeIcon icon={appIcons.arrowRight} size="sm" />
            </button>
          </div>
        </div>

        <div
          ref={scrollRef}
          className="flex gap-6 overflow-x-auto snap-x pb-4 scrollbar-hide"
          style={{
            scrollSnapType: 'x mandatory',
            scrollPadding: '0 24px',
            WebkitOverflowScrolling: 'touch',
          }}
          onMouseEnter={() => setIsHovering(true)}
          onMouseLeave={() => setIsHovering(false)}
          onTouchStart={handleTouchStart}
          onTouchEnd={handleTouchEnd}
          role="region"
          aria-label="Carousel des plans d'abonnement"
        >
          {activePlans.map((plan: SubscriptionPlan, index: number) => (
            <motion.div
              key={plan.id}
              ref={(el) => { cardsRef.current[index] = el; }}
              variants={CARD_VARIANTS}
              initial="hidden"
              animate="visible"
              custom={index}
              whileHover={prefersReducedMotion ? {} : { scale: 1.02, y: -8 }}
              className="flex-shrink-0 snap-start w-full sm:w-1/2 lg:w-1/4"
              style={{
                minWidth: '280px',
                maxWidth: '340px',
                scrollSnapAlign: 'start',
              }}
            >
              <div className="relative h-full">
                <div className="absolute inset-0 bg-gradient-to-br from-[color:var(--ds-primary-container)]/10 to-[color:var(--ds-secondary-container)]/10 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300" aria-hidden="true" />
                <div className="relative h-full rounded-2xl border border-[color:var(--ds-outline-variant)] bg-[color:var(--ds-surface-container-low)]/80 backdrop-blur-xl p-6 flex flex-col transition-all duration-300 hover:border-[color:var(--ds-primary)]/30 hover:shadow-[0_25px_50px_-12px_rgba(217,70,239,0.15)]">
                  {plan.isFree && (
                    <span className="absolute -top-3 left-6 z-10 px-3 py-1 rounded-full bg-[color:var(--ds-primary)] text-white text-xs font-bold tracking-wide">
                      Gratuit
                    </span>
                  )}

                  <div className="mb-4">
                    <h3 className="text-xl font-bold text-[color:var(--ds-on-surface)]">{plan.name}</h3>
                    {plan.description && (
                      <p className="mt-2 text-sm text-[color:var(--ds-on-surface-variant)] line-clamp-2">{plan.description}</p>
                    )}
                  </div>

                  <div className="mb-6 flex items-baseline gap-2">
                    <span className="text-4xl font-bold text-[color:var(--ds-on-surface)]">{plan.isFree ? '0' : plan.priceTnd.toLocaleString('fr-FR')}</span>
                    <span className="text-[color:var(--ds-on-surface-variant)]">
                      {plan.isFree ? '' : `${plan.currency}/${plan.durationMonths === 1 ? 'mois' : plan.durationMonths + ' mois'}`}
                    </span>
                  </div>

                  <ul className="flex-1 space-y-3 mb-6" role="list" aria-label="Quotas du plan">
                    {['max_products', 'max_customers', 'max_boutiques'].map((code, i) => {
                      const value = getQuotaValue(plan, code);
                      const icon = QUOTA_ICONS[code];
                      const label = QUOTA_LABELS[code];
                      return (
                        <motion.li
                          key={code}
                          variants={QUOTA_VARIANTS}
                          custom={i}
                          className="flex items-center gap-3 p-3 rounded-xl bg-[color:var(--ds-surface-container-lowest)]/60 border border-[color:var(--ds-outline-variant)]/50"
                        >
                          <span className="flex-shrink-0 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[color:var(--ds-primary-container)] text-[color:var(--ds-primary)]">
                            <FontAwesomeIcon icon={appIcons[icon]} size="lg" />
                          </span>
                          <div className="min-w-0 flex-1">
                            <p className="text-xs font-medium text-[color:var(--ds-on-surface-variant)] uppercase tracking-wide">{label}</p>
                            <p className="text-lg font-bold text-[color:var(--ds-on-surface)]">
                              {value === null ? 'Illimité' : value.toLocaleString('fr-FR')}
                            </p>
                          </div>
                        </motion.li>
                      );
                    })}
                  </ul>

                  <Button
                    variant={plan.isFree ? 'secondary' : 'primary'}
                    className="w-full"
                    onClick={handleCtaClick}
                    size="md"
                  >
                    {isAuthenticated ? 'Gérer mon abonnement' : 'Démarrer maintenant'}
                    <FontAwesomeIcon icon={appIcons.arrowRight} className="ml-2" size="sm" />
                  </Button>
                </div>
              </div>
            </motion.div>
          ))}
        </div>

        {activePlans.length > cardsPerView && (
          <div className="mt-8 flex items-center justify-center gap-2" role="tablist" aria-label="Indicateurs de plan">
            {Array.from({ length: Math.max(0, activePlans.length - cardsPerView + 1) }, (_, i) => (
              <button
                key={i}
                type="button"
                onClick={() => scrollToIndex(i)}
                className={`w-2.5 h-2.5 rounded-full transition-all duration-300 ${activeIndex === i ? 'bg-[color:var(--ds-primary)] w-8' : 'bg-[color:var(--ds-on-surface-variant)]/40 hover:bg-[color:var(--ds-primary)]/60'}`}
                role="tab"
                aria-selected={activeIndex === i}
                aria-label={`Aller au plan ${i + 1}`}
              />
            ))}
          </div>
        )}
      </div>
    </section>
  );
}