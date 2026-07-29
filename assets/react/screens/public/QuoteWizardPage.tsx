import { useEffect, useState } from 'react';
import { Badge, Button, Card, Input, Textarea } from '../../components/ui';
import { authHeaders, boutiqueQuery, resolveBoutiqueSlug } from './boutiqueRouting';
import { applyStorefrontTheme, resetStorefrontTheme, type StorefrontThemeData } from '../../theme/storefrontThemeRoot';
import { StorefrontHeader } from './storefront/StorefrontHeader';
import type { StoreBoutique } from './storefront/StorefrontTheme';

type QuoteBoutique = StorefrontThemeData & {
  id: string;
  name: string;
  slug: string;
  logoUrl?: string | null;
  slogan?: string | null;
  primaryColor?: string | null;
  reviewsEnabled?: boolean;
  wishlistEnabled?: boolean;
  customerAccountsEnabled?: boolean;
};

type QuoteCartItem = {
  id: string;
  productId: string | null;
  productName: string | null;
  quantity: number;
  unitPriceCents: number;
  totalCents: number;
  variantSku?: string | null;
  variantAttributes?: Array<{ name: string; value: string }>;
};

type QuoteCart = {
  currency: string;
  itemsCount: number;
  totalCents: number;
  items: QuoteCartItem[];
};

export function QuoteWizardPage() {
  const boutiqueSlug = resolveBoutiqueSlug(/^\/boutiques\/([^/]+)\/quote/);
  const [boutique, setBoutique] = useState<QuoteBoutique | null>(null);
  const [cart, setCart] = useState<QuoteCart | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  useEffect(() => {
    if (!boutiqueSlug) {
      setIsLoading(false);
      setLoadError('Boutique introuvable.');
      return undefined;
    }

    let cancelled = false;
    const headers = authHeaders();
    window.__boutiqueSlug__ = boutiqueSlug;
    resetStorefrontTheme();

    async function loadQuoteData(): Promise<void> {
      try {
        const [boutiqueResponse, cartResponse] = await Promise.all([
          fetch(`/api/boutiques/${boutiqueSlug}`, { headers }),
          fetch(`/api/cart${boutiqueQuery(boutiqueSlug)}`, { headers }),
        ]);

        if (!boutiqueResponse.ok || !cartResponse.ok) {
          throw new Error('Impossible de charger les données du devis.');
        }

        const [boutiquePayload, cartPayload] = await Promise.all([
          boutiqueResponse.json() as Promise<QuoteBoutique>,
          cartResponse.json() as Promise<QuoteCart>,
        ]);

        if (cancelled) return;

        applyStorefrontTheme(boutiquePayload);
        setBoutique(boutiquePayload);
        setCart(cartPayload);
      } catch (error) {
        if (!cancelled) {
          setLoadError(error instanceof Error ? error.message : 'Impossible de charger les données du devis.');
        }
      } finally {
        if (!cancelled) setIsLoading(false);
      }
    }

    void loadQuoteData();

    return () => {
      cancelled = true;
      resetStorefrontTheme();
    };
  }, [boutiqueSlug]);

  const items = cart?.items ?? [];
  const currency = cart?.currency ?? 'TND';
  const total = ((cart?.totalCents ?? 0) / 100).toFixed(2);

  if (isLoading) {
    return <main className="ds-shell flex min-h-screen items-center justify-center">Chargement du panier...</main>;
  }

  if (loadError || !boutique || !cart) {
    return (
      <main className="ds-shell flex min-h-screen items-center justify-center px-6 text-center">
        <p className="text-sm text-red-600">{loadError ?? 'Impossible de charger le panier.'}</p>
      </main>
    );
  }

  return (
    <main className="ds-shell min-h-screen bg-[color:var(--sf-bg,#f6f2eb)] text-[color:var(--sf-text,#171717)]">
      <StorefrontHeader boutique={boutique as StoreBoutique} showCart={false} />
      <section className="ds-page py-8 md:py-12">
        <div className="mb-6">
          <p className="ds-hero__eyebrow">Devis</p>
          <h1 className="ds-hero__title">Demander un devis chez {boutique.name}</h1>
          <p className="ds-hero__subtitle">Les produits et quantités sont repris directement depuis votre panier.</p>
        </div>

        <div className="ds-stepper mb-6">
          {['Produits du panier', 'Coordonnées client', 'Confirmation'].map((step, index) => (
            <div className="ds-stepper__item" key={step}>
              <span className="ds-stepper__index">{index + 1}</span>
              <div>
                <strong>{step}</strong>
                <p className="text-sm text-[color:var(--ds-on-surface-variant)]">Étape {index + 1}</p>
              </div>
            </div>
          ))}
        </div>

        {items.length === 0 ? (
          <Card>
            <h2 className="text-xl font-bold">Votre panier est vide</h2>
            <p className="mt-2 text-sm text-[color:var(--ds-on-surface-variant)]">Ajoutez des produits avant de demander un devis.</p>
          </Card>
        ) : (
          <div className="ds-grid ds-grid--split">
            <Card>
              <div className="flex items-center justify-between gap-4">
                <h2 className="text-xl font-bold">Produits sélectionnés</h2>
                <Badge tone="neutral">{cart.itemsCount} article{cart.itemsCount > 1 ? 's' : ''}</Badge>
              </div>
              <div className="mt-4 space-y-4">
                {items.map((item) => (
                  <div key={item.id} className="flex items-center justify-between gap-4 rounded-2xl border border-[color:var(--ds-outline-variant)] bg-white p-4">
                    <div>
                      <strong>{item.productName ?? 'Produit'}</strong>
                      {item.variantAttributes?.map((attribute) => (
                        <p className="text-sm text-[color:var(--ds-on-surface-variant)]" key={`${item.id}-${attribute.name}`}>
                          {attribute.name}: {attribute.value}
                        </p>
                      ))}
                      {item.variantSku && <p className="text-xs text-[color:var(--ds-on-surface-variant)]">Réf. {item.variantSku}</p>}
                      <p className="text-sm text-[color:var(--ds-on-surface-variant)]">Quantité : {item.quantity}</p>
                    </div>
                    <div className="text-right">
                      <strong>{(item.totalCents / 100).toFixed(2)} {currency}</strong>
                      <p className="text-sm text-[color:var(--ds-on-surface-variant)]">{(item.unitPriceCents / 100).toFixed(2)} {currency} / unité</p>
                    </div>
                  </div>
                ))}
              </div>
            </Card>

            <div className="space-y-6">
              <Card>
                <h2 className="text-xl font-bold">Informations client</h2>
                <div className="mt-4 grid gap-4">
                  <Input aria-label="Nom" placeholder="Nom" />
                  <Input aria-label="Email" placeholder="Email" type="email" />
                  <Input aria-label="Téléphone" placeholder="Téléphone" />
                  <Textarea aria-label="Message" placeholder="Message ou précision sur votre demande" />
                </div>
              </Card>
              <Card>
                <h2 className="text-xl font-bold">Récapitulatif</h2>
                <div className="mt-4 space-y-3 text-sm">
                  <div className="flex justify-between"><span>Articles</span><strong>{cart.itemsCount}</strong></div>
                  <div className="flex justify-between"><span>Sous-total du panier</span><strong>{total} {currency}</strong></div>
                  <div className="flex justify-between"><span>Livraison</span><strong>À confirmer</strong></div>
                  <div className="flex justify-between border-t border-[color:var(--ds-outline-variant)] pt-3 text-base"><span>Valeur du panier</span><strong>{total} {currency}</strong></div>
                </div>
                <Button variant="primary" className="mt-5 w-full" type="button">Envoyer la demande</Button>
              </Card>
            </div>
          </div>
        )}
      </section>
    </main>
  );
}
