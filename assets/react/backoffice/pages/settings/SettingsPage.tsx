import { useState, useCallback } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardHeader, CardBody } from '../../components/Card';
import { Button } from '../../components/Button';
import { Badge } from '../../components/Badge';
import { LoadingState, ErrorState } from '../../components/States';
import { FormField, Input, Textarea } from '../../components/FormField';
import { PageHeader } from '../../layout/Shell';
import { useNotification } from '../../hooks/useNotification';
import { useBoutique } from '../../hooks/useBoutique';
import type { SubscriptionSummary } from '../../types';
import { mergeThemeOptionWithPreset } from '../../../theme/themes';

type ThemeOption = {
  id: string;
  name: string;
  code: string;
  description?: string | null;
  isDefault?: boolean;
  colorPalette?: Record<string, string>;
};

type BoutiqueSettings = {
  name?: string; slogan?: string; description?: string; contactEmail?: string; contactPhone?: string;
  address?: string; city?: string; postalCode?: string; country?: string;
  primaryColor?: string; secondaryColor?: string; accentColor?: string; backgroundColor?: string; textColor?: string;
  colorPalette?: Record<string, string>;
  fontFamily?: string; fontSize?: string; borderRadius?: string;
  enableEmailVerification?: boolean; enableCustomerEmailVerification?: boolean;
  orderMode?: string; maintenance?: boolean;
  metaPixelId?: string;
   theme?: string; moduleConfig?: { enable_customer_auth?: boolean };
};

type BoutiqueSettingsResponse = {
  shopName?: string | null;
  slogan?: string | null;
  description?: string | null;
  contactEmail?: string | null;
  contactPhone?: string | null;
  address?: string | null;
  city?: string | null;
  postalCode?: string | null;
  country?: string | null;
  primaryColor?: string | null;
  secondaryColor?: string | null;
  accentColor?: string | null;
  backgroundColor?: string | null;
  textColor?: string | null;
  colorPalette?: Record<string, string> | null;
  fontFamily?: string | null;
  fontSize?: string | null;
  borderRadius?: string | null;
  enableEmailVerification?: boolean | null;
  enableCustomerEmailVerification?: boolean | null;
  orderMode?: string | null;
  maintenanceMode?: boolean | null;
  maintenance?: boolean | null;
  metaPixelId?: string | null;
   theme?: string | null; moduleConfig?: { enable_customer_auth?: boolean };
};

const FONT_OPTIONS = [
  { value: 'Inter, system-ui, sans-serif', label: 'Inter' },
  { value: '"Nunito Sans", Rubik, system-ui, sans-serif', label: 'Nunito Sans' },
  { value: '"DM Sans", Inter, sans-serif', label: 'DM Sans' },
  { value: '"Open Sans", system-ui, sans-serif', label: 'Open Sans' },
  { value: 'Lato, "Helvetica Neue", Arial, sans-serif', label: 'Lato' },
  { value: '"Source Sans 3", system-ui, sans-serif', label: 'Source Sans 3' },
  { value: '"IBM Plex Sans", system-ui, sans-serif', label: 'IBM Plex Sans' },
  { value: '"Work Sans", system-ui, sans-serif', label: 'Work Sans' },
  { value: '"Plus Jakarta Sans", system-ui, sans-serif', label: 'Plus Jakarta Sans' },
  { value: 'Montserrat, system-ui, sans-serif', label: 'Montserrat' },
  { value: 'Raleway, system-ui, sans-serif', label: 'Raleway' },
  { value: '"Playfair Display", Georgia, serif', label: 'Playfair Display' },
  { value: '"Libre Baskerville", Georgia, serif', label: 'Libre Baskerville' },
  { value: 'Rubik, system-ui, sans-serif', label: 'Rubik' },
  { value: '"Space Grotesk", system-ui, sans-serif', label: 'Space Grotesk' },
  { value: 'Manrope, system-ui, sans-serif', label: 'Manrope' },
  { value: 'Geist, system-ui, sans-serif', label: 'Geist' },
  { value: '"JetBrains Mono", "Fira Code", monospace', label: 'JetBrains Mono' },
];

const defaultColors = {
  primaryColor: '#3525cd',
  secondaryColor: '#505f76',
  accentColor: '#4f46e5',
  backgroundColor: '#fcf8ff',
  textColor: '#1b1b24',
};

function normalizeHexColor(value: unknown, fallback: string): string {
  return typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value) ? value : fallback;
}

function buildColorPalette(form: BoutiqueSettings): Record<string, string> {
  return {
    ...(form.colorPalette ?? {}),
    primary: normalizeHexColor(form.primaryColor, defaultColors.primaryColor),
    secondary: normalizeHexColor(form.secondaryColor, defaultColors.secondaryColor),
    accent: normalizeHexColor(form.accentColor, defaultColors.accentColor),
    background: normalizeHexColor(form.backgroundColor, defaultColors.backgroundColor),
    text: normalizeHexColor(form.textColor, defaultColors.textColor),
  };
}

function ThemePreviewCard({
  theme,
  selected,
  onSelect,
}: {
  theme: ThemeOption;
  selected: boolean;
  onSelect: () => void;
}) {
  const palette = theme.colorPalette ?? {};
  const swatches = [
    palette.primary ?? '#3525cd',
    palette.background ?? '#fcf8ff',
    palette.surface ?? '#ffffff',
    palette.accent ?? palette.primaryContainer ?? '#4f46e5',
  ];

  return (
    <button
      type="button"
      onClick={onSelect}
      className="bo-theme-card"
      style={{
        border: selected ? '2px solid var(--bo-primary)' : '1px solid var(--bo-border)',
        boxShadow: selected ? '0 0 0 3px color-mix(in srgb, var(--bo-primary) 18%, transparent)' : undefined,
      }}
    >
      <div className="bo-theme-card__preview">
        {swatches.map((color) => (
          <span key={color} style={{ backgroundColor: color }} />
        ))}
      </div>
      <div className="bo-theme-card__body">
        <div className="bo-theme-card__title">
          <span>{theme.name}</span>
          {theme.isDefault && <Badge tone="info">Défaut</Badge>}
          {selected && <Badge tone="success">Actif</Badge>}
        </div>
        {theme.description && <p className="bo-theme-card__desc">{theme.description}</p>}
      </div>
    </button>
  );
}

export function SettingsPage({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const { boutique } = useBoutique();
  const [saving, setSaving] = useState(false);
  const [savingTheme, setSavingTheme] = useState(false);
  const [form, setForm] = useState<BoutiqueSettings>({});
  const [themes, setThemes] = useState<ThemeOption[]>([]);

  const fetchSettings = useCallback(async () => {
    if (!boutique?.id) return null;
    const [data, themesData] = await Promise.all([
      api.get<BoutiqueSettingsResponse>('/settings'),
      api.get<ThemeOption[] | { member?: ThemeOption[] }>('/themes').catch(() => []),
    ]);
    const themeList = Array.isArray(themesData)
      ? themesData
      : (themesData as { member?: ThemeOption[] }).member ?? [];
    const enrichedThemes = themeList.map(mergeThemeOptionWithPreset);
    setThemes(enrichedThemes);
    setForm({
      name: data.shopName ?? '', slogan: data.slogan ?? '', description: data.description ?? '',
      contactEmail: data.contactEmail ?? '', contactPhone: data.contactPhone ?? '',
      address: data.address ?? '', city: data.city ?? '', postalCode: data.postalCode ?? '', country: data.country ?? '',
      primaryColor: normalizeHexColor(data.primaryColor ?? data.colorPalette?.primary, defaultColors.primaryColor),
      secondaryColor: normalizeHexColor(data.secondaryColor ?? data.colorPalette?.secondary, defaultColors.secondaryColor),
      accentColor: normalizeHexColor(data.accentColor ?? data.colorPalette?.accent, defaultColors.accentColor),
      backgroundColor: normalizeHexColor(data.backgroundColor ?? data.colorPalette?.background, defaultColors.backgroundColor),
      textColor: normalizeHexColor(data.textColor ?? data.colorPalette?.text, defaultColors.textColor),
      colorPalette: data.colorPalette ?? {},
      fontFamily: data.fontFamily ?? '', fontSize: data.fontSize ?? '', borderRadius: data.borderRadius ?? '',
      enableEmailVerification: !!data.enableEmailVerification,
       enableCustomerEmailVerification: !!data.enableCustomerEmailVerification,
       moduleConfig: { ...(data.moduleConfig ?? {}), enable_customer_auth: data.moduleConfig?.enable_customer_auth !== false },
      orderMode: data.orderMode ?? 'standard', maintenance: !!(data.maintenanceMode ?? data.maintenance),
      metaPixelId: data.metaPixelId ?? '',
      theme: data.theme ?? enrichedThemes.find((t) => t.isDefault)?.code ?? enrichedThemes[0]?.code ?? '',
    });
    return data;
  }, [api, boutique?.id]);

  const { isLoading, error, refresh } = useApiData(fetchSettings, [boutique?.id]);
  const fetchSummary = useCallback(async () => api.get<SubscriptionSummary>('/subscription/summary'), [api]);
  const { data: summary, isLoading: summaryLoading } = useApiData(fetchSummary, [boutique?.id]);
  const hasAnalytics = summary?.accessibleModules?.includes('analytics') ?? false;
  const hasMetaPixelExtension = summary?.activeExtensions?.some((extension) => extension.extensionCode === 'meta_pixel') ?? false;
  const canUseMetaPixel = hasAnalytics && hasMetaPixelExtension;

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      await api.patch('/settings', { ...form, shopName: form.name, maintenanceMode: form.maintenance, colorPalette: buildColorPalette(form) });
      showNotice('Paramètres mis à jour.', 'success');
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Erreur.', 'error');
    } finally {
      setSaving(false);
    }
  }

  async function handleThemeSelect(code: string) {
    if (form.theme === code) return;
    setSavingTheme(true);
    try {
      const customPalette = buildColorPalette(form);
      const updated = await api.patch<BoutiqueSettingsResponse>('/settings', {
        theme: code,
        primaryColor: form.primaryColor,
        secondaryColor: form.secondaryColor,
        colorPalette: customPalette,
      });
      const palette = updated.colorPalette ?? {};
      setForm((f) => ({
        ...f,
        theme: code,
        primaryColor: normalizeHexColor(updated.primaryColor ?? palette.primary, f.primaryColor ?? defaultColors.primaryColor),
        secondaryColor: normalizeHexColor(updated.secondaryColor ?? palette.secondary, f.secondaryColor ?? defaultColors.secondaryColor),
        accentColor: normalizeHexColor(updated.accentColor ?? palette.accent, f.accentColor ?? defaultColors.accentColor),
        backgroundColor: normalizeHexColor(updated.backgroundColor ?? palette.background, f.backgroundColor ?? defaultColors.backgroundColor),
        textColor: normalizeHexColor(updated.textColor ?? palette.text, f.textColor ?? defaultColors.textColor),
        colorPalette: palette,
      }));
      showNotice('Thème appliqué à la boutique.', 'success');
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Erreur lors du changement de thème.', 'error');
    } finally {
      setSavingTheme(false);
    }
  }

  if (error) return <ErrorState message={error} onRetry={refresh} />;

  return (
    <div>
      <PageHeader title="Paramètres" description="Configuration de la boutique" />

      <Card>
        <CardHeader><h3>Thème de la boutique</h3></CardHeader>
        <CardBody>
          {isLoading ? <LoadingState /> : (
            <>
              <p style={{ marginBottom: 16, color: 'var(--bo-text-muted)' }}>
                Choisissez l&apos;apparence de votre boutique en ligne. Le changement s&apos;applique immédiatement au storefront.
              </p>
              <div className="bo-theme-grid">
                {themes.map((theme) => (
                  <ThemePreviewCard
                    key={theme.id}
                    theme={theme}
                    selected={form.theme === theme.code}
                    onSelect={() => handleThemeSelect(theme.code)}
                  />
                ))}
              </div>
              {savingTheme && <p style={{ marginTop: 12, fontSize: 13, color: 'var(--bo-text-muted)' }}>Application du thème...</p>}
              {themes.length === 0 && (
                <p style={{ color: 'var(--bo-text-muted)' }}>Aucun thème disponible. Contactez l&apos;administrateur.</p>
              )}
            </>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardHeader><h3>Informations générales</h3></CardHeader>
        <CardBody>
          {isLoading ? <LoadingState /> : (
            <form className="bo-form" onSubmit={handleSubmit}>
              <div className="bo-form-row">
                <FormField label="Nom"><Input value={form.name ?? ''} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} /></FormField>
                <FormField label="Slogan"><Input value={form.slogan ?? ''} onChange={(e) => setForm((f) => ({ ...f, slogan: e.target.value }))} /></FormField>
              </div>
              <FormField label="Description"><Textarea rows={3} value={form.description ?? ''} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} /></FormField>
              <div className="bo-form-row">
                <FormField label="Email contact"><Input type="email" value={form.contactEmail ?? ''} onChange={(e) => setForm((f) => ({ ...f, contactEmail: e.target.value }))} /></FormField>
                <FormField label="Téléphone"><Input value={form.contactPhone ?? ''} onChange={(e) => setForm((f) => ({ ...f, contactPhone: e.target.value }))} /></FormField>
              </div>
              <div className="bo-form-row">
                <FormField label="Adresse"><Input value={form.address ?? ''} onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))} /></FormField>
                <FormField label="Ville"><Input value={form.city ?? ''} onChange={(e) => setForm((f) => ({ ...f, city: e.target.value }))} /></FormField>
              </div>
              <div className="bo-form-row">
                <FormField label="Code postal"><Input value={form.postalCode ?? ''} onChange={(e) => setForm((f) => ({ ...f, postalCode: e.target.value }))} /></FormField>
                <FormField label="Pays"><Input value={form.country ?? ''} onChange={(e) => setForm((f) => ({ ...f, country: e.target.value }))} /></FormField>
              </div>
              <h3 style={{ marginTop: 24 }}>Apparence</h3>
              <div className="bo-form-row">
                <FormField label="Couleur principale"><Input type="color" value={form.primaryColor ?? defaultColors.primaryColor} onChange={(e) => setForm((f) => ({ ...f, primaryColor: e.target.value }))} /></FormField>
                <FormField label="Couleur secondaire"><Input type="color" value={form.secondaryColor ?? defaultColors.secondaryColor} onChange={(e) => setForm((f) => ({ ...f, secondaryColor: e.target.value }))} /></FormField>
                <FormField label="Accent"><Input type="color" value={form.accentColor ?? defaultColors.accentColor} onChange={(e) => setForm((f) => ({ ...f, accentColor: e.target.value }))} /></FormField>
              </div>
              <div className="bo-form-row">
                <FormField label="Fond boutique"><Input type="color" value={form.backgroundColor ?? defaultColors.backgroundColor} onChange={(e) => setForm((f) => ({ ...f, backgroundColor: e.target.value }))} /></FormField>
                <FormField label="Texte"><Input type="color" value={form.textColor ?? defaultColors.textColor} onChange={(e) => setForm((f) => ({ ...f, textColor: e.target.value }))} /></FormField>
              </div>
              <div className="bo-form-row">
                <FormField label="Police">
                  <select className="bo-input" value={form.fontFamily ?? ''} onChange={(e) => setForm((f) => ({ ...f, fontFamily: e.target.value }))}>
                    <option value="">Sélectionner une police</option>
                    {FONT_OPTIONS.map((opt) => (
                      <option key={opt.value} value={opt.value} style={{ fontFamily: opt.value }}>{opt.label}</option>
                    ))}
                  </select>
                </FormField>
                <FormField label="Taille police"><Input value={form.fontSize ?? ''} placeholder="16px" onChange={(e) => setForm((f) => ({ ...f, fontSize: e.target.value }))} /></FormField>
                <FormField label="Border radius"><Input value={form.borderRadius ?? ''} placeholder="8px" onChange={(e) => setForm((f) => ({ ...f, borderRadius: e.target.value }))} /></FormField>
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
                  <select className="bo-input" value={form.enableEmailVerification ? 'yes' : 'no'} onChange={(e) => setForm((f) => ({ ...f, enableEmailVerification: e.target.value === 'yes' }))}>
                    <option value="yes">Activée</option><option value="no">Désactivée</option>
                  </select>
                </FormField>
              </div>
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
