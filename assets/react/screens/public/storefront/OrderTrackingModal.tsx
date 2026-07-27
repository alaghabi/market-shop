import { useEffect, useState } from 'react';
import { CheckCircle2, Loader2, Search, X } from 'lucide-react';
import { boutiqueQuery } from '../boutiqueRouting';

type TrackingOrder = {
  reference: string;
  status: string;
  paymentStatus?: string;
  deliveryStatus?: string | null;
  deliveryTracking?: string | null;
  totalCents: number;
  currency: string;
  createdAt: string;
  items: Array<{ name: string; quantity: number; unitPriceCents: number }>;
};

const statusLabels: Record<string, string> = {
  draft: 'Brouillon',
  pending: 'En attente de confirmation',
  paid: 'Confirmée',
  completed: 'Terminée',
  shipped: 'Expédiée',
  delivered: 'Livrée',
  cancelled: 'Annulée',
  refunded: 'Remboursée',
};

export function OrderTrackingModal({ boutiqueSlug, onClose }: { boutiqueSlug: string; onClose: () => void }) {
  const [reference, setReference] = useState('');
  const [order, setOrder] = useState<TrackingOrder | null>(null);
  const [state, setState] = useState<'idle' | 'loading' | 'not-found' | 'error'>('idle');

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [onClose]);

  async function lookupOrder(event: React.FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    const value = reference.trim();
    if (!value) return;

    setState('loading');
    setOrder(null);
    try {
      const response = await fetch(`/api/public/order-tracking/${encodeURIComponent(value)}${boutiqueQuery(boutiqueSlug)}`);
      if (response.status === 404) {
        setState('not-found');
        return;
      }
      if (!response.ok) {
        setState('error');
        return;
      }
      setOrder(await response.json() as TrackingOrder);
      setState('idle');
    } catch {
      setState('error');
    }
  }

  return (
    <div className="fixed inset-0 z-[70] flex items-end justify-center bg-black/60 p-0 backdrop-blur-sm sm:items-center sm:p-5" role="dialog" aria-modal="true" aria-labelledby="order-tracking-title">
      <button type="button" className="absolute inset-0 cursor-default" aria-label="Fermer" onClick={onClose} />
      <div className="relative z-10 max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-t-[2rem] bg-[#f6f2eb] p-6 text-[#171717] shadow-2xl sm:rounded-[2rem] sm:p-8">
        <button type="button" onClick={onClose} className="absolute right-5 top-5 rounded-full border border-black/10 p-2 transition hover:bg-white" aria-label="Fermer le suivi de commande">
          <X className="h-4 w-4" />
        </button>
        <div className="pr-10">
          <div className="text-xs font-semibold uppercase tracking-[0.22em] text-black/45">Suivi boutique</div>
          <h2 id="order-tracking-title" className="mt-3 text-3xl font-semibold tracking-[-0.04em]">Consulter votre commande</h2>
          <p className="mt-3 text-sm leading-6 text-black/60">Saisissez la référence reçue après votre commande pour afficher son statut réel.</p>
        </div>

        <form className="mt-7 flex flex-col gap-3 sm:flex-row" onSubmit={(event) => { void lookupOrder(event); }}>
          <label className="sr-only" htmlFor="order-reference">Référence de commande</label>
          <input
            id="order-reference"
            value={reference}
            onChange={(event) => setReference(event.target.value)}
            placeholder="Ex. 0197f2d4-..."
            autoComplete="off"
            className="min-w-0 flex-1 rounded-full border border-black/10 bg-white px-5 py-3 text-sm outline-none transition focus:border-black/35 focus:ring-2 focus:ring-black/10"
          />
          <button type="submit" disabled={!reference.trim() || state === 'loading'} className="inline-flex items-center justify-center gap-2 rounded-full bg-[#111111] px-5 py-3 text-sm font-semibold text-white transition hover:bg-black disabled:cursor-not-allowed disabled:opacity-45">
            {state === 'loading' ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
            Rechercher
          </button>
        </form>

        {state === 'not-found' && <div className="mt-5 rounded-2xl border border-amber-900/10 bg-amber-50 px-4 py-4 text-sm text-amber-900">Aucune commande trouvable avec cette référence.</div>}
        {state === 'error' && <div className="mt-5 rounded-2xl border border-red-900/10 bg-red-50 px-4 py-4 text-sm text-red-900">Impossible de consulter la commande pour le moment. Réessayez.</div>}
        {order && (
          <div className="mt-6 rounded-[1.5rem] border border-black/10 bg-white p-5">
            <div className="flex flex-wrap items-start justify-between gap-4 border-b border-black/8 pb-4">
              <div>
                <div className="text-xs uppercase tracking-[0.18em] text-black/45">Référence</div>
                <div className="mt-1 break-all font-mono text-sm">#{order.reference}</div>
              </div>
              <div className="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800">
                <CheckCircle2 className="h-4 w-4" />
                {statusLabels[order.status] ?? order.status}
              </div>
            </div>
            <div className="mt-5 grid gap-4 text-sm sm:grid-cols-2">
              <div><div className="text-black/45">Date</div><div className="mt-1 font-medium">{new Date(order.createdAt).toLocaleString('fr-FR')}</div></div>
              <div><div className="text-black/45">Total</div><div className="mt-1 font-medium">{(order.totalCents / 100).toFixed(2)} {order.currency}</div></div>
              {order.deliveryStatus && <div><div className="text-black/45">Livraison</div><div className="mt-1 font-medium">{order.deliveryStatus}</div></div>}
              {order.deliveryTracking && <div><div className="text-black/45">Suivi colis</div><div className="mt-1 font-medium">{order.deliveryTracking}</div></div>}
            </div>
            <div className="mt-5 border-t border-black/8 pt-4">
              <div className="text-sm font-semibold">Articles</div>
              <ul className="mt-3 space-y-2 text-sm text-black/65">
                {order.items.map((item, index) => <li key={`${item.name}-${index}`} className="flex justify-between gap-4"><span>{item.name} x {item.quantity}</span><span>{(item.unitPriceCents * item.quantity / 100).toFixed(2)} {order.currency}</span></li>)}
              </ul>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
