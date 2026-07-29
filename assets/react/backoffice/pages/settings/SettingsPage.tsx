import { useState, useCallback } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardHeader, CardBody } from '../../components/Card';
import { Button } from '../../components/Button';
import { LoadingState, ErrorState } from '../../components/States';
import { FormField, Input } from '../../components/FormField';
import { PageHeader } from '../../layout/Shell';
import { useNotification } from '../../hooks/useNotification';
import { useBoutique } from '../../hooks/useBoutique';
import type { SubscriptionSummary } from '../../types';

type BoutiqueSettings = {
  contactEmail?: string; contactPhone?: string;
  address?: string; city?: string; postalCode?: string; country?: string;
  facebookUrl?: string; instagramUrl?: string; tiktokUrl?: string; youtubeUrl?: string;
  linkedinUrl?: string; xTwitterUrl?: string; whatsappNumber?: string;
  enableEmailVerification?: boolean; enableCustomerEmailVerification?: boolean;
  orderMode?: string; maintenance?: boolean;
  metaPixelId?: string;
  moduleConfig?: { enable_customer_auth?: boolean };
};

type BoutiqueSettingsResponse = {
  contactEmail?: string | null;
  contactPhone?: string | null;
  address?: string | null;
  city?: string | null;
  postalCode?: string | null;
  country?: string | null;
  enableEmailVerification?: boolean | null;
  enableCustomerEmailVerification?: boolean | null;
  orderMode?: string | null;
  maintenanceMode?: boolean | null;
  maintenance?: boolean | null;
  metaPixelId?: string | null;
  facebookUrl?: string | null;
  instagramUrl?: string | null;
  tiktokUrl?: string | null;
  youtubeUrl?: string | null;
  linkedinUrl?: string | null;
  xTwitterUrl?: string | null;
  whatsappNumber?: string | null;
  socialLinks?: Record<string, string>;
  moduleConfig?: { enable_customer_auth?: boolean };
};

function hasAccessibleModule(value: string[] | Record<string, string> | undefined, module: string): boolean {
  if (Array.isArray(value)) return value.includes(module);
  return Object.values(value ?? {}).includes(module);
}

export function SettingsPage({ getAccessToken, userRoles = [] }: { getAccessToken: () => string | null; userRoles?: string[] }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const { boutique } = useBoutique();
  const isSuperAdmin = userRoles.includes('ROLE_SUPER_ADMIN');
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState<BoutiqueSettings>({});

  const fetchSettings = useCallback(async () => {
    if (!boutique?.id) return null;
    const data = await api.get<BoutiqueSettingsResponse>('/settings');
    setForm({
      contactEmail: data.contactEmail ?? '', contactPhone: data.contactPhone ?? '',
      address: data.address ?? '', city: data.city ?? '', postalCode: data.postalCode ?? '', country: data.country ?? '',
      facebookUrl: data.facebookUrl ?? data.socialLinks?.facebook ?? '',
      instagramUrl: data.instagramUrl ?? data.socialLinks?.instagram ?? '',
      tiktokUrl: data.tiktokUrl ?? data.socialLinks?.tiktok ?? '',
      youtubeUrl: data.youtubeUrl ?? data.socialLinks?.youtube ?? '',
      linkedinUrl: data.linkedinUrl ?? data.socialLinks?.linkedin ?? '',
      xTwitterUrl: data.xTwitterUrl ?? data.socialLinks?.x_twitter ?? '',
      whatsappNumber: data.whatsappNumber ?? data.socialLinks?.whatsapp ?? '',
      enableEmailVerification: !!data.enableEmailVerification,
      enableCustomerEmailVerification: !!data.enableCustomerEmailVerification,
      moduleConfig: { ...(data.moduleConfig ?? {}), enable_customer_auth: data.moduleConfig?.enable_customer_auth !== false },
      orderMode: data.orderMode ?? 'standard', maintenance: !!(data.maintenanceMode ?? data.maintenance),
      metaPixelId: data.metaPixelId ?? '',
    });
    return data;
  }, [api, boutique?.id]);

  const { isLoading, error, refresh } = useApiData(fetchSettings, [boutique?.id]);
  const fetchSummary = useCallback(async () => api.get<SubscriptionSummary>('/subscription/summary'), [api]);
  const { data: summary, isLoading: summaryLoading } = useApiData(fetchSummary, [boutique?.id]);
  const hasAnalytics = hasAccessibleModule(summary?.accessibleModules, 'analytics');
  const hasMetaPixelExtension = summary?.activeExtensions?.some((extension) => extension.extensionCode === 'meta_pixel') ?? false;
  const canUseMetaPixel = hasAnalytics && hasMetaPixelExtension;

  if (!boutique) {
    return (
      <Card>
        <CardBody>
          <p style={{ color: 'var(--bo-text-muted)' }}>Sélectionnez une boutique pour personnaliser le front office.</p>
        </CardBody>
      </Card>
    );
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      await api.patch('/settings', { ...form, maintenanceMode: form.maintenance });
      showNotice('Paramètres mis à jour.', 'success');
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Erreur.', 'error');
    } finally {
      setSaving(false);
    }
  }

  if (error) return <ErrorState message={error} onRetry={refresh} />;

  return (
    <div>
      <PageHeader title="Paramètres" description="Configuration de la boutique" />

      <Card>
        <CardHeader><h3>Coordonnées de la boutique</h3></CardHeader>
        <CardBody>
          {isLoading ? <LoadingState /> : (
            <form className="bo-form" onSubmit={handleSubmit}>
              <div className="bo-form-row">
                <FormField label="Email contact"><Input type="email" value={form.contactEmail ?? ''} onChange={(e) => setForm((f) => ({ ...f, contactEmail: e.target.value }))} /></FormField>
                <FormField label="Téléphone"><Input value={form.contactPhone ?? ''} onChange={(e) => setForm((f) => ({ ...f, contactPhone: e.target.value }))} /></FormField>
              </div>
              <div className="bo-form-row">
                <FormField label="Adresse"><Input value={form.address ?? ''} onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))} /></FormField>
                <FormField label="Ville"><Input value={form.city ?? ''} onChange={(e) => setForm((f) => ({ ...f, city: e.target.value }))} /></FormField>
              </div>
              <h3 style={{ marginTop: 24 }}>Réseaux sociaux</h3>
              <p style={{ color: 'var(--bo-text-muted)', fontSize: 13, marginTop: 4 }}>
                Les icônes apparaissent dans le footer uniquement pour les réseaux renseignés.
              </p>
              <div className="bo-form-row">
                <FormField label="Facebook"><Input type="url" placeholder="https://facebook.com/..." value={form.facebookUrl ?? ''} onChange={(e) => setForm((f) => ({ ...f, facebookUrl: e.target.value }))} /></FormField>
                <FormField label="Instagram"><Input type="url" placeholder="https://instagram.com/..." value={form.instagramUrl ?? ''} onChange={(e) => setForm((f) => ({ ...f, instagramUrl: e.target.value }))} /></FormField>
              </div>
              <div className="bo-form-row">
                <FormField label="TikTok"><Input type="url" placeholder="https://tiktok.com/@..." value={form.tiktokUrl ?? ''} onChange={(e) => setForm((f) => ({ ...f, tiktokUrl: e.target.value }))} /></FormField>
                <FormField label="YouTube"><Input type="url" placeholder="https://youtube.com/@..." value={form.youtubeUrl ?? ''} onChange={(e) => setForm((f) => ({ ...f, youtubeUrl: e.target.value }))} /></FormField>
              </div>
              <div className="bo-form-row">
                <FormField label="LinkedIn"><Input type="url" placeholder="https://linkedin.com/company/..." value={form.linkedinUrl ?? ''} onChange={(e) => setForm((f) => ({ ...f, linkedinUrl: e.target.value }))} /></FormField>
                <FormField label="X / Twitter"><Input type="url" placeholder="https://x.com/..." value={form.xTwitterUrl ?? ''} onChange={(e) => setForm((f) => ({ ...f, xTwitterUrl: e.target.value }))} /></FormField>
              </div>
              <FormField label="WhatsApp" hint="Numéro international ou lien https://wa.me/...">
                <Input placeholder="216XXXXXXXX" value={form.whatsappNumber ?? ''} onChange={(e) => setForm((f) => ({ ...f, whatsappNumber: e.target.value }))} />
              </FormField>
              <div className="bo-form-row">
                <FormField label="Code postal"><Input value={form.postalCode ?? ''} onChange={(e) => setForm((f) => ({ ...f, postalCode: e.target.value }))} /></FormField>
                <FormField label="Pays"><Input value={form.country ?? ''} onChange={(e) => setForm((f) => ({ ...f, country: e.target.value }))} /></FormField>
              </div>
              <h3 style={{ marginTop: 24 }}>Fonctionnement</h3>
              <div className="bo-form-row">
                <FormField label="Mode commande">
                  <select className="bo-input" value={form.orderMode} onChange={(e) => setForm((f) => ({ ...f, orderMode: e.target.value }))}>
                    <option value="standard">Standard</option>
                    <option value="preorder">Pré-commande</option>
                    <option value="contact">Contact uniquement</option>
                  </select>
                </FormField>
                <FormField label="Vérification email">
                    <select className="bo-input" value="yes" disabled>
                    <option value="yes">Activée (obligatoire)</option>
                    </select>
                </FormField>
              </div>
              {isSuperAdmin && (
                <label className="bo-checkbox" style={{ marginTop: 12 }}>
                  <input type="checkbox" checked={!!form.enableCustomerEmailVerification} onChange={(e) => setForm((f) => ({ ...f, enableCustomerEmailVerification: e.target.checked }))} />
                  Exiger la vérification email des clients de cette boutique
                </label>
              )}
              <div className="bo-form-row">
                <label className="bo-checkbox"><input type="checkbox" checked={!!form.maintenance} onChange={(e) => setForm((f) => ({ ...f, maintenance: e.target.checked }))} /> Mode maintenance</label>
                <label className="bo-checkbox"><input type="checkbox" checked={form.moduleConfig?.enable_customer_auth !== false} onChange={(e) => setForm((f) => ({ ...f, moduleConfig: { ...(f.moduleConfig ?? {}), enable_customer_auth: e.target.checked } }))} /> Comptes clients activés</label>
              </div>
              <h3 style={{ marginTop: 24 }}>Suivi & Tracking</h3>
              <FormField label="Meta Pixel ID" hint="Nécessite le module Analytics et l'extension Meta Pixel">
                 <Input value={form.metaPixelId ?? ''} placeholder="1234567890" disabled={summaryLoading || (!canUseMetaPixel && !form.metaPixelId)} onChange={(e) => setForm((f) => ({ ...f, metaPixelId: e.target.value }))} />
              </FormField>
              {!summaryLoading && !canUseMetaPixel && (
                <p style={{ fontSize: 12, color: 'var(--bo-warning)', marginTop: 4 }}>
                  Activez Analytics et l&apos;extension Meta Pixel pour configurer ce suivi.
                </p>
              )}
              <p style={{ fontSize: 12, color: 'var(--bo-text-muted)', marginTop: 4 }}>
                {form.metaPixelId ? '✓ Le pixel Meta est actif sur le storefront' : 'Le suivi Meta Pixel est désactivé. Saisissez un identifiant pour l\'activer.'}
              </p>
              {form.metaPixelId && boutique?.slug && (
                <div style={{ marginTop: 12, padding: 12, background: 'var(--bo-bg-secondary, #f5f5f5)', borderRadius: 6 }}>
                  <p style={{ fontSize: 12, fontWeight: 600, marginBottom: 4 }}>Flux produits Facebook / Meta Catalog</p>
                  <code style={{ fontSize: 12, wordBreak: 'break-all' }}>
                    https://{boutique.slug}.hanooti.com/products/feed.xml
                  </code>
                  <p style={{ fontSize: 11, color: 'var(--bo-text-muted)', marginTop: 4 }}>
                    Utilisez cette URL dans votre catalogue Meta Commerce Manager.
                  </p>
                </div>
              )}
              <div style={{ marginTop: 24 }}>
                <Button onClick={handleSubmit} disabled={saving}>{saving ? 'Enregistrement...' : 'Enregistrer'}</Button>
              </div>
            </form>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
