import { useEffect, useMemo, useState } from 'react';
import { Badge, Button, Card, Input, Select } from '../../components/ui';
import { authHeaders, boutiqueQuery, resolveBoutiqueSlug } from './boutiqueRouting';
import { applyStorefrontTheme, resetStorefrontTheme, type StorefrontThemeData } from '../../theme/storefrontThemeRoot';

type Country = {
  id: string;
  name: string;
  code: string;
  phoneCode: string | null;
};

type Governorate = {
  id: string;
  countryId: string;
  name: string;
  code: string;
};

type Locality = {
  id: string;
  countryId: string;
  governorateId: string;
  name: string;
  postalCode: string | null;
};

type PaymentMethod = {
  id: string;
  code: string;
  name: string;
  type?: string;
};

type CartOutput = {
  totalCents: number;
  currency: string;
  itemsCount: number;
  customerEmail?: string | null;
  firstName?: string | null;
  lastName?: string | null;
  phone?: string | null;
  address?: string | null;
  city?: string | null;
  postalCode?: string | null;
  country?: string | null;
  countryId?: string | null;
  governorate?: string | null;
  governorateId?: string | null;
  locality?: string | null;
  localityId?: string | null;
};

type CheckoutBoutique = StorefrontThemeData & {
  id: string;
  name: string;
  slug: string;
};

export function CheckoutPage() {
  const boutiqueSlug = resolveBoutiqueSlug(/^\/boutiques\/([^/]+)\/checkout/);
  const [boutique, setBoutique] = useState<CheckoutBoutique | null>(null);
  const [countries, setCountries] = useState<Country[]>([]);
  const [governorates, setGovernorates] = useState<Governorate[]>([]);
  const [localities, setLocalities] = useState<Locality[]>([]);
  const [isLoadingCountries, setIsLoadingCountries] = useState(true);
  const [isLoadingGovernorates, setIsLoadingGovernorates] = useState(false);
  const [isLoadingLocalities, setIsLoadingLocalities] = useState(false);
  const [paymentMethods, setPaymentMethods] = useState<PaymentMethod[]>([]);
  const [cart, setCart] = useState<CartOutput | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitSuccess, setSubmitSuccess] = useState<string | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [form, setForm] = useState({
    firstName: '',
    lastName: '',
    email: '',
    phone: '',
    addressLine: '',
    countryId: '',
    governorateId: '',
    localityId: '',
    postalCode: '',
    paymentMethodCode: '',
  });

  useEffect(() => {
    if (!boutiqueSlug) return;
    const headers = authHeaders();
    resetStorefrontTheme();
    fetch(`/api/boutiques/${boutiqueSlug}`, { headers })
      .then((response) => response.ok ? response.json() : null)
      .then((payload: CheckoutBoutique | null) => {
        if (!payload) return;
        applyStorefrontTheme(payload);
        setBoutique(payload);
      })
      .catch(() => {});

    return resetStorefrontTheme;
  }, [boutiqueSlug]);

  useEffect(() => {
    let cancelled = false;

    async function loadCart() {
      try {
        const response = await fetch(`/api/cart${boutiqueQuery(boutiqueSlug)}`);
        if (!response.ok) {
          throw new Error('Impossible de charger le panier.');
        }

        const payload = await response.json() as CartOutput;
        if (cancelled) {
          return;
        }

        setCart(payload);
        setForm((current) => ({
          ...current,
          firstName: current.firstName || payload.firstName || '',
          lastName: current.lastName || payload.lastName || '',
          email: current.email || payload.customerEmail || '',
          phone: current.phone || payload.phone || '',
          addressLine: current.addressLine || payload.address || '',
          countryId: current.countryId || payload.countryId || '',
          governorateId: current.governorateId || payload.governorateId || '',
          localityId: current.localityId || payload.localityId || '',
          postalCode: current.postalCode || payload.postalCode || '',
        }));
      } catch (error) {
        if (!cancelled) {
          setLoadError(error instanceof Error ? error.message : 'Impossible de charger le panier.');
        }
      }
    }

    async function loadCountries() {
      setIsLoadingCountries(true);
      setLoadError(null);

      try {
        const response = await fetch('/api/reference/countries');
        if (!response.ok) {
          throw new Error('Impossible de charger les pays.');
        }

        const payload = await response.json() as { member?: Country[] } | Country[];
        const items = Array.isArray(payload) ? payload : (payload.member ?? []);

        if (cancelled) {
          return;
        }

        setCountries(items);
        setForm((current) => ({
          ...current,
          countryId: current.countryId || items[0]?.id || '',
        }));
      } catch (error) {
        if (!cancelled) {
          setLoadError(error instanceof Error ? error.message : 'Impossible de charger les pays.');
        }
      } finally {
        if (!cancelled) {
          setIsLoadingCountries(false);
        }
      }
    }

    void loadCart();
    void loadCountries();

    return () => {
      cancelled = true;
    };
  }, [boutiqueSlug]);

  useEffect(() => {
    let cancelled = false;

    async function loadPaymentMethods() {
      try {
        const response = await fetch(`/api/payment-methods${boutiqueQuery(boutiqueSlug)}`);
        if (!response.ok) {
          throw new Error('Impossible de charger les moyens de paiement.');
        }

        const payload = await response.json() as { member?: PaymentMethod[] } | PaymentMethod[];
        const items = Array.isArray(payload) ? payload : (payload.member ?? []);

        if (cancelled) {
          return;
        }

        setPaymentMethods(items);
        setForm((current) => ({
          ...current,
          paymentMethodCode: current.paymentMethodCode
            || items.find((method) => method.type === 'CASH_ON_DELIVERY' || method.code === 'CASH_ON_DELIVERY')?.code
            || items[0]?.code
            || '',
        }));
      } catch (error) {
        if (!cancelled) {
          setLoadError(error instanceof Error ? error.message : 'Impossible de charger les moyens de paiement.');
        }
      }
    }

    void loadPaymentMethods();

    return () => {
      cancelled = true;
    };
  }, [boutiqueSlug]);

  useEffect(() => {
    let cancelled = false;

    async function loadGovernorates() {
      if (!form.countryId) {
        setGovernorates([]);
        return;
      }

      setIsLoadingGovernorates(true);
      setLoadError(null);

      try {
        const response = await fetch(`/api/reference/governorates?countryId=${encodeURIComponent(form.countryId)}`);
        if (!response.ok) {
          throw new Error('Impossible de charger les gouvernorats.');
        }

        const payload = await response.json() as { member?: Governorate[] } | Governorate[];
        const items = Array.isArray(payload) ? payload : (payload.member ?? []);

        if (cancelled) {
          return;
        }

        setGovernorates(items);
        setForm((current) => ({
          ...current,
          governorateId: items.some((item) => item.id === current.governorateId) ? current.governorateId : (items[0]?.id || ''),
          localityId: '',
          postalCode: '',
        }));
      } catch (error) {
        if (!cancelled) {
          setLoadError(error instanceof Error ? error.message : 'Impossible de charger les gouvernorats.');
        }
      } finally {
        if (!cancelled) {
          setIsLoadingGovernorates(false);
        }
      }
    }

    void loadGovernorates();

    return () => {
      cancelled = true;
    };
  }, [form.countryId]);

  useEffect(() => {
    let cancelled = false;

    async function loadLocalities() {
      if (!form.governorateId) {
        setLocalities([]);
        return;
      }

      setIsLoadingLocalities(true);
      setLoadError(null);

      try {
        const response = await fetch(`/api/reference/localities?governorateId=${encodeURIComponent(form.governorateId)}`);
        if (!response.ok) {
          throw new Error('Impossible de charger les villes.');
        }

        const payload = await response.json() as { member?: Locality[] } | Locality[];
        const items = Array.isArray(payload) ? payload : (payload.member ?? []);

        if (cancelled) {
          return;
        }

        setLocalities(items);
        setForm((current) => {
          const nextLocalityId = items.some((item) => item.id === current.localityId) ? current.localityId : (items[0]?.id || '');
          const nextLocality = items.find((item) => item.id === nextLocalityId);

          return {
            ...current,
            localityId: nextLocalityId,
            postalCode: nextLocality?.postalCode || current.postalCode || '',
          };
        });
      } catch (error) {
        if (!cancelled) {
          setLoadError(error instanceof Error ? error.message : 'Impossible de charger les villes.');
        }
      } finally {
        if (!cancelled) {
          setIsLoadingLocalities(false);
        }
      }
    }

    void loadLocalities();

    return () => {
      cancelled = true;
    };
  }, [form.governorateId]);

  const selectedCountry = useMemo(
    () => countries.find((item) => item.id === form.countryId) ?? null,
    [countries, form.countryId],
  );
  const selectedGovernorate = useMemo(
    () => governorates.find((item) => item.id === form.governorateId) ?? null,
    [governorates, form.governorateId],
  );
  const selectedLocality = useMemo(
    () => localities.find((item) => item.id === form.localityId) ?? null,
    [localities, form.localityId],
  );
  const subtotal = ((cart?.totalCents ?? 0) / 100).toFixed(2);
  const currency = cart?.currency ?? 'TND';

  const isAddressReady = Boolean(form.addressLine && form.countryId && form.governorateId && form.localityId);

  async function submitCheckout() {
    if (!selectedCountry || !selectedGovernorate || !selectedLocality) {
      setSubmitError('Sélectionnez une adresse complète avant de confirmer.');

      return;
    }

    setIsSubmitting(true);
    setSubmitError(null);
    setSubmitSuccess(null);

    try {
      const response = await fetch(`/api/cart/checkout${boutiqueQuery(boutiqueSlug)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          paymentMethodCode: form.paymentMethodCode,
          customerEmail: form.email,
          firstName: form.firstName,
          lastName: form.lastName,
          phone: form.phone,
          shippingAddress: form.addressLine,
          shippingCity: selectedLocality.name,
          shippingPostalCode: form.postalCode,
          shippingCountry: selectedCountry.name,
          shippingCountryId: selectedCountry.id,
          shippingGovernorate: selectedGovernorate.name,
          shippingGovernorateId: selectedGovernorate.id,
          shippingLocality: selectedLocality.name,
          shippingLocalityId: selectedLocality.id,
        }),
      });

      const payload = await response.json().catch(() => null) as { orderId?: string; status?: string; totalCents?: number; currency?: string; detail?: string; description?: string } | null;
      if (!response.ok) {
        throw new Error(payload?.detail || payload?.description || 'Checkout impossible.');
      }

      setSubmitSuccess('Commande créée avec succès.');
      window.sessionStorage.setItem('market-shop:last-order', JSON.stringify({
        orderId: payload?.orderId || '',
        status: payload?.status || 'pending',
        totalCents: payload?.totalCents || cart?.totalCents || 0,
        currency: payload?.currency || cart?.currency || 'TND',
        customerName: `${form.firstName} ${form.lastName}`.trim(),
      }));
      window.location.href = `/order-confirmation?orderId=${encodeURIComponent(payload?.orderId || '')}`;
    } catch (error) {
      setSubmitError(error instanceof Error ? error.message : 'Checkout impossible.');
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <main className="ds-shell min-h-screen bg-[color:var(--sf-bg,#f6f2eb)] text-[color:var(--sf-text,#171717)]">
      <section className="ds-page py-8 md:py-12">
        <div className="mb-6">
          <p className="ds-hero__eyebrow">Checkout</p>
          <h1 className="ds-hero__title">Paiement sécurisé{boutique?.name ? ` chez ${boutique.name}` : ''}</h1>
          <p className="ds-hero__subtitle">Adresse guidée, données client préremplies et total réel du panier.</p>
        </div>

        <div className="ds-grid ds-grid--split">
          <div className="space-y-6">
            <Card>
              <h2 className="text-xl font-bold">1. Livraison</h2>
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                 <Input aria-label="Prénom" value={form.firstName} onChange={(event) => setForm((current) => ({ ...current, firstName: event.target.value }))} placeholder="Prénom" />
                 <Input aria-label="Nom" value={form.lastName} onChange={(event) => setForm((current) => ({ ...current, lastName: event.target.value }))} placeholder="Nom" />
                 <Input aria-label="Email" className="md:col-span-2" value={form.email} onChange={(event) => setForm((current) => ({ ...current, email: event.target.value }))} placeholder="Email" type="email" />
                 <Input aria-label="Téléphone" className="md:col-span-2" value={form.phone} onChange={(event) => setForm((current) => ({ ...current, phone: event.target.value }))} placeholder="Téléphone" />
                 <Input aria-label="Adresse" className="md:col-span-2" value={form.addressLine} onChange={(event) => setForm((current) => ({ ...current, addressLine: event.target.value }))} placeholder="Adresse" />
                 <Select aria-label="Pays" value={form.countryId} onChange={(event) => setForm((current) => ({ ...current, countryId: event.target.value, governorateId: '', localityId: '', postalCode: '' }))} disabled={isLoadingCountries || 0 === countries.length}>
                  <option value="">Choisir un pays</option>
                  {countries.map((country) => (
                    <option key={country.id} value={country.id}>{country.name}</option>
                  ))}
                </Select>
                 <Select aria-label="Gouvernorat" value={form.governorateId} onChange={(event) => setForm((current) => ({ ...current, governorateId: event.target.value, localityId: '', postalCode: '' }))} disabled={!form.countryId || isLoadingGovernorates || 0 === governorates.length}>
                  <option value="">Choisir un gouvernorat</option>
                  {governorates.map((governorate) => (
                    <option key={governorate.id} value={governorate.id}>{governorate.name}</option>
                  ))}
                </Select>
                 <Select aria-label="Ville" value={form.localityId} onChange={(event) => {
                  const localityId = event.target.value;
                  const locality = localities.find((item) => item.id === localityId) ?? null;
                  setForm((current) => ({
                    ...current,
                    localityId,
                    postalCode: locality?.postalCode || '',
                  }));
                }} disabled={!form.governorateId || isLoadingLocalities || 0 === localities.length}>
                  <option value="">Choisir une ville</option>
                  {localities.map((locality) => (
                    <option key={locality.id} value={locality.id}>{locality.name}</option>
                  ))}
                </Select>
                 <Input aria-label="Code postal" value={form.postalCode} onChange={(event) => setForm((current) => ({ ...current, postalCode: event.target.value }))} placeholder="Code postal" />
              </div>

              <div className="mt-4 flex flex-wrap items-center gap-3 text-sm text-[color:var(--ds-on-surface-variant)]">
                <Badge tone={isAddressReady ? 'success' : 'neutral'}>{isAddressReady ? 'Adresse prête' : 'Adresse incomplète'}</Badge>
                {selectedCountry ? <span>Pays: <strong>{selectedCountry.name}</strong></span> : null}
                {selectedGovernorate ? <span>Gouvernorat: <strong>{selectedGovernorate.name}</strong></span> : null}
                {selectedLocality ? <span>Ville: <strong>{selectedLocality.name}</strong></span> : null}
              </div>

              {loadError ? (
                <p className="mt-4 text-sm text-red-600">{loadError}</p>
              ) : null}
              {submitError ? (
                <p className="mt-2 text-sm text-red-600">{submitError}</p>
              ) : null}
              {submitSuccess ? (
                <p className="mt-2 text-sm text-green-600">{submitSuccess}</p>
              ) : null}
            </Card>

            <Card>
              <h2 className="text-xl font-bold">2. Mode de livraison</h2>
              <div className="mt-4 grid gap-3 md:grid-cols-2">
                <div className="rounded-2xl border border-[color:var(--ds-outline-variant)] bg-white p-4"><strong>Standard</strong><p className="text-sm text-[color:var(--ds-on-surface-variant)]">3 à 5 jours - Calcul final côté boutique</p></div>
                <div className="rounded-2xl border border-[color:var(--ds-outline-variant)] bg-white p-4"><strong>Adresse</strong><p className="text-sm text-[color:var(--ds-on-surface-variant)]">Pays, gouvernorat et ville issus des référentiels</p></div>
              </div>
            </Card>

            <Card>
              <h2 className="text-xl font-bold">3. Paiement</h2>
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                 <Select aria-label="Moyen de paiement" value={form.paymentMethodCode} onChange={(event) => setForm((current) => ({ ...current, paymentMethodCode: event.target.value }))}>
                  <option value="">Choisir un moyen de paiement</option>
                  {paymentMethods.map((method) => (
                    <option key={method.id} value={method.code}>{method.name}</option>
                  ))}
                </Select>
                 <Input aria-label="Titulaire de la carte" placeholder="Titulaire" />
                 <Input aria-label="Numéro de carte" placeholder="Numéro de carte" />
                 <Input aria-label="Date d'expiration" placeholder="MM/AA" />
              </div>
            </Card>
          </div>

          <Card>
            <h2 className="text-xl font-bold">Récapitulatif</h2>
            <div className="mt-4 space-y-3 text-sm">
              <div className="flex justify-between"><span>Articles</span><strong>{cart?.itemsCount ?? 0}</strong></div>
              <div className="flex justify-between"><span>Sous-total</span><strong>{subtotal} {currency}</strong></div>
              <div className="flex justify-between"><span>Livraison</span><strong>Calculée après validation</strong></div>
              <div className="flex justify-between border-t border-[color:var(--ds-outline-variant)] pt-3 text-base"><span>Total actuel</span><strong>{subtotal} {currency}</strong></div>
            </div>
            <div className="mt-6 flex items-center gap-3">
              <Badge tone="success">Sécurisé</Badge>
              <Badge tone="neutral">SSL</Badge>
            </div>
            <div className="mt-6 rounded-2xl border border-[color:var(--ds-outline-variant)] bg-white/80 p-4 text-sm text-[color:var(--ds-on-surface-variant)]">
              <p><strong>Adresse sélectionnée</strong></p>
              <p className="mt-2">{form.addressLine || 'Adresse non renseignée'}</p>
              <p>{selectedLocality?.name || 'Ville'}{selectedGovernorate ? `, ${selectedGovernorate.name}` : ''}</p>
              <p>{selectedCountry?.name || 'Pays'}{form.postalCode ? ` • ${form.postalCode}` : ''}</p>
            </div>
            <Button variant="primary" className="mt-6 w-full" disabled={!isAddressReady || !form.paymentMethodCode || isLoadingCountries || isLoadingGovernorates || isLoadingLocalities || isSubmitting} onClick={() => { void submitCheckout(); }}>{isSubmitting ? 'Confirmation...' : form.paymentMethodCode === 'CASH_ON_DELIVERY' ? 'Confirmer la commande' : 'Confirmer et payer'}</Button>
          </Card>
        </div>
      </section>
    </main>
  );
}
