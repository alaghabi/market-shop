import { useEffect, useMemo } from 'react';
import { motion } from 'framer-motion';
import { Badge, Button, Card } from '../../components/ui';
import { authHeaders, resolveBoutiqueSlug } from './boutiqueRouting';
import { applyStorefrontTheme, resetStorefrontTheme, type StorefrontThemeData } from '../../theme/storefrontThemeRoot';

type LastOrderSummary = {
  orderId: string;
  status: string;
  totalCents: number;
  currency: string;
  customerName?: string;
};

export function OrderConfirmationPage() {
  const orderId = useMemo(() => new URLSearchParams(window.location.search).get('orderId') || '', []);
  const boutiqueSlug = useMemo(() => resolveBoutiqueSlug(/^\/boutiques\/([^/]+)\/order-confirmation/), []);

  useEffect(() => {
    if (!boutiqueSlug) return undefined;

    let cancelled = false;
    resetStorefrontTheme();
    fetch(`/api/boutiques/${encodeURIComponent(boutiqueSlug)}`, { headers: authHeaders() })
      .then((response) => response.ok ? response.json() as Promise<StorefrontThemeData> : null)
      .then((payload) => {
        if (!cancelled && payload) applyStorefrontTheme(payload);
      })
      .catch(() => {});

    return () => {
      cancelled = true;
      resetStorefrontTheme();
    };
  }, [boutiqueSlug]);

  const summary = useMemo(() => {
    try {
      const raw = window.sessionStorage.getItem('market-shop:last-order');

      return raw ? JSON.parse(raw) as LastOrderSummary : null;
    } catch {
      return null;
    }
  }, []);
  const displayedOrderId = orderId || summary?.orderId || '';
  const total = (((summary?.totalCents ?? 0) / 100)).toFixed(2);

  return (
    <main className="ds-shell">
      <section className="ds-page py-8 md:py-12">
        <Card className="ds-hero text-center">
          <motion.div
            initial={{ scale: 0.4, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            transition={{ type: 'spring', stiffness: 260, damping: 18, delay: 0.1 }}
            className="mx-auto mb-2 flex h-16 w-16 items-center justify-center rounded-full bg-[color:var(--ds-success-container,#d1fae5)] text-[color:var(--ds-success,#047857)]"
          >
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
          </motion.div>
          <Badge tone="success" className="mx-auto">Succès</Badge>
          <h1 className="ds-hero__title mt-4">Commande confirmée</h1>
          <p className="ds-hero__subtitle mx-auto">Votre commande a bien été créée. Vous pouvez garder ce numéro pour le suivi.</p>
          <div className="mx-auto mt-6 max-w-2xl rounded-2xl border border-[color:var(--ds-outline-variant)] bg-white p-6 text-left">
             <p className="text-sm text-[color:var(--ds-on-surface-variant)]">N° commande</p>
             <strong className="text-2xl">{displayedOrderId ? `#${displayedOrderId}` : 'Commande créée'}</strong>
             <p className="mt-2 text-sm font-semibold text-red-600">Si vous le souhaitez, sauvegardez ce lien pour consulter le statut de cette commande.</p>
             <div className="mt-4 grid gap-4 md:grid-cols-2">
              <div>
                <p className="text-sm text-[color:var(--ds-on-surface-variant)]">Statut</p>
                <strong>{summary?.status || 'pending'}</strong>
              </div>
              <div>
                <p className="text-sm text-[color:var(--ds-on-surface-variant)]">Total</p>
                <strong>{total} {summary?.currency || 'TND'}</strong>
              </div>
            </div>
            {summary?.customerName ? (
              <p className="mt-4 text-sm text-[color:var(--ds-on-surface-variant)]">Client: <strong>{summary.customerName}</strong></p>
            ) : null}
            <div className="mt-4 grid gap-3 md:grid-cols-4">
              {['Confirmée', 'Préparée', 'Expédiée', 'Livrée'].map((step, index) => (
                <motion.div
                  key={step}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: 0.25 + index * 0.08, duration: 0.35 }}
                  className={`rounded-2xl p-3 text-center ${index === 0 ? 'bg-[color:var(--ds-secondary-container)]' : 'bg-[color:var(--ds-surface-container-low)]'}`}
                >
                  <strong className="block">{step}</strong>
                  <span className="text-xs text-[color:var(--ds-on-surface-variant)]">Étape {index + 1}</span>
                </motion.div>
              ))}
            </div>
          </div>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
             <Button variant="primary" style={{ backgroundColor: 'var(--ds-primary)' }} onClick={() => { window.location.href = '/'; }}>Retour à la boutique</Button>
            <Button variant="secondary" onClick={() => { window.history.back(); }}>Retour</Button>
          </div>
        </Card>
      </section>
    </main>
  );
}
