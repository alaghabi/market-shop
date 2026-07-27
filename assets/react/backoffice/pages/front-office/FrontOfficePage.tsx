import { useState, useCallback, useMemo } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardHeader, CardBody } from '../../components/Card';
import { Button } from '../../components/Button';
import { Badge } from '../../components/Badge';
import { LoadingState, ErrorState } from '../../components/States';
import { FormField, Input, Textarea } from '../../components/FormField';
import { MediaField } from '../../components/MediaField';
import { PageHeader } from '../../layout/Shell';
import { useNotification } from '../../hooks/useNotification';
import { useBoutique } from '../../hooks/useBoutique';
import { mergeThemeOptionWithPreset } from '../../../theme/themes';
import { frontOfficeUrl } from '../../utils/frontOfficeUrl';

type ThemeOption = {
  id: string;
  name: string;
  code: string;
  description?: string | null;
  isDefault?: boolean;
  colorPalette?: Record<string, string>;
};

type FrontOfficeSettings = {
  name?: string;
  slogan?: string;
  description?: string;
  logoUrl?: string;
  coverImage?: string;
  theme?: string;
  fontFamily?: string;
  fontSize?: string;
  borderRadius?: string;
  primaryColor?: string;
  accentColor?: string;
  backgroundColor?: string;
  secondaryColor?: string;
  textColor?: string;
};

function buildColorPalette(form: FrontOfficeSettings): Record<string, string> {
  return {
    ...(form.primaryColor ? { primary: form.primaryColor } : {}),
    ...(form.secondaryColor ? { secondary: form.secondaryColor } : {}),
    ...(form.accentColor ? { accent: form.accentColor } : {}),
    ...(form.backgroundColor ? { background: form.backgroundColor } : {}),
    ...(form.textColor ? { text: form.textColor } : {}),
  };
}

function normalizeHexColor(value: unknown, fallback: string): string {
  return typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value) ? value : fallback;
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

export function FrontOfficePage({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const { boutique } = useBoutique();
  const [saving, setSaving] = useState(false);
  const [savingTheme, setSavingTheme] = useState(false);
  const [form, setForm] = useState<FrontOfficeSettings>({});
  const [themes, setThemes] = useState<ThemeOption[]>([]);

  const storefrontUrl = useMemo(
    () => (boutique?.slug ? frontOfficeUrl(boutique) : null),
    [boutique],
  );

  const fetchSettings = useCallback(async () => {
    if (!boutique?.id) return null;
    const [data, themesData] = await Promise.all([
      api.get<any>('/settings'),
      api.get<ThemeOption[] | { member?: ThemeOption[] }>('/themes').catch(() => []),
    ]);
    const themeList = Array.isArray(themesData)
      ? themesData
      : (themesData as { member?: ThemeOption[] }).member ?? [];
    const enrichedThemes = themeList.map(mergeThemeOptionWithPreset);
    setThemes(enrichedThemes);
    setForm({
      name: data.shopName ?? '',
      slogan: data.slogan ?? '',
      description: data.description ?? '',
      logoUrl: data.logoUrl ?? '',
      coverImage: data.coverImage ?? '',
      theme: data.theme ?? enrichedThemes.find((t) => t.isDefault)?.code ?? enrichedThemes[0]?.code ?? '',
      fontFamily: data.fontFamily ?? '',
      fontSize: data.fontSize ?? '',
      borderRadius: data.borderRadius ?? '',
       primaryColor: normalizeHexColor(data.primaryColor ?? data.colorPalette?.primary, '#3525cd'),
       secondaryColor: normalizeHexColor(data.secondaryColor ?? data.colorPalette?.secondary, '#505f76'),
       accentColor: normalizeHexColor(data.accentColor ?? data.colorPalette?.accent ?? data.colorPalette?.primaryContainer, '#4f46e5'),
       backgroundColor: normalizeHexColor(data.backgroundColor ?? data.colorPalette?.background, '#fcf8ff'),
       textColor: normalizeHexColor(data.textColor ?? data.colorPalette?.text, '#1b1b24'),
    });
    return data;
  }, [api, boutique?.id]);

  const { isLoading, error, refresh } = useApiData(fetchSettings, [boutique?.id]);

  async function handleThemeSelect(code: string) {
    if (form.theme === code) return;
    setSavingTheme(true);
    try {
      const customPalette = buildColorPalette(form);
      await api.patch('/settings', {
        theme: code,
        primaryColor: form.primaryColor || null,
        secondaryColor: form.secondaryColor || null,
        colorPalette: customPalette,
      });
      setForm((f) => ({
        ...f,
         theme: code,
         primaryColor: customPalette.primary ?? f.primaryColor,
         secondaryColor: customPalette.secondary ?? f.secondaryColor,
         accentColor: customPalette.accent ?? f.accentColor,
         backgroundColor: customPalette.background ?? f.backgroundColor,
         textColor: customPalette.text ?? f.textColor,
      }));
      showNotice('Thème appliqué à votre boutique en ligne.', 'success');
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Erreur lors du changement de thème.', 'error');
    } finally {
      setSavingTheme(false);
    }
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      await api.patch('/settings', {
        shopName: form.name,
        slogan: form.slogan,
        description: form.description,
        logoUrl: form.logoUrl || null,
        coverImage: form.coverImage || null,
        fontFamily: form.fontFamily || null,
        fontSize: form.fontSize || null,
        borderRadius: form.borderRadius || null,
        primaryColor: form.primaryColor || null,
        secondaryColor: form.secondaryColor || null,
        accentColor: form.accentColor || null,
        backgroundColor: form.backgroundColor || null,
        textColor: form.textColor || null,
        colorPalette: buildColorPalette(form),
      });
      showNotice('Boutique en ligne mise à jour.', 'success');
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Erreur.', 'error');
    } finally {
      setSaving(false);
    }
  }

  if (!boutique) {
    return (
      <Card>
        <CardBody>
          <p style={{ color: 'var(--bo-text-muted)' }}>Sélectionnez une boutique pour personnaliser le front office.</p>
        </CardBody>
      </Card>
    );
  }

  if (error) return <ErrorState message={error} onRetry={refresh} />;

  return (
    <div>
      <PageHeader
        title="Boutique en ligne"
        description="Personnalisez l'apparence de votre storefront visible par vos clients."
        actions={storefrontUrl ? (
          <Button variant="secondary" onClick={() => window.open(storefrontUrl, '_blank', 'noopener')}>
            Voir ma boutique
          </Button>
        ) : undefined}
      />

      <Card>
        <CardHeader><h3>Thème visuel</h3></CardHeader>
        <CardBody>
          {isLoading ? <LoadingState /> : (
            <>
              <p style={{ marginBottom: 16, color: 'var(--bo-text-muted)' }}>
                Choisissez un thème prédéfini. Les couleurs et la typographie sont appliquées immédiatement sur votre boutique.
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
                <p style={{ color: 'var(--bo-text-muted)' }}>Aucun thème disponible.</p>
              )}
            </>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardHeader><h3>Identité de la boutique</h3></CardHeader>
        <CardBody>
          {isLoading ? <LoadingState /> : (
            <form className="bo-form" onSubmit={handleSubmit}>
              <div className="bo-form-row">
                <FormField label="Nom affiché">
                  <Input value={form.name ?? ''} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
                </FormField>
                <FormField label="Slogan">
                  <Input value={form.slogan ?? ''} onChange={(e) => setForm((f) => ({ ...f, slogan: e.target.value }))} />
                </FormField>
              </div>
              <FormField label="Description">
                <Textarea rows={3} value={form.description ?? ''} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} />
              </FormField>
              <div className="bo-form-row">
                <MediaField label="Logo" value={form.logoUrl} boutiqueId={boutique.id} hint="Utilisez une URL publique ou importez une image." onChange={(logoUrl) => setForm((f) => ({ ...f, logoUrl }))} />
                <MediaField label="Image de couverture" value={form.coverImage} boutiqueId={boutique.id} maxSizeMb={10} hint="Utilisez une URL publique ou importez une image." onChange={(coverImage) => setForm((f) => ({ ...f, coverImage }))} />
              </div>

              <h3 style={{ marginTop: 24 }}>Couleurs & typographie</h3>
              <div className="bo-form-row">
                <FormField label="Couleur principale">
                  <Input type="color" value={normalizeHexColor(form.primaryColor, '#3525cd')} onChange={(e) => setForm((f) => ({ ...f, primaryColor: e.target.value }))} />
                </FormField>
                <FormField label="Couleur secondaire"><Input type="color" value={normalizeHexColor(form.secondaryColor, '#505f76')} onChange={(e) => setForm((f) => ({ ...f, secondaryColor: e.target.value }))} /></FormField>
                <FormField label="Couleur accent">
                  <Input type="color" value={normalizeHexColor(form.accentColor, '#4f46e5')} onChange={(e) => setForm((f) => ({ ...f, accentColor: e.target.value }))} />
                </FormField>
                <FormField label="Fond">
                  <Input type="color" value={normalizeHexColor(form.backgroundColor, '#fcf8ff')} onChange={(e) => setForm((f) => ({ ...f, backgroundColor: e.target.value }))} />
                </FormField>
                <FormField label="Couleur du texte"><Input type="color" value={normalizeHexColor(form.textColor, '#1b1b24')} onChange={(e) => setForm((f) => ({ ...f, textColor: e.target.value }))} /></FormField>
              </div>
              <div className="bo-form-row">
                <FormField label="Police">
                  <Input value={form.fontFamily ?? ''} placeholder="Inter, sans-serif" onChange={(e) => setForm((f) => ({ ...f, fontFamily: e.target.value }))} />
                </FormField>
                <FormField label="Taille police">
                  <Input value={form.fontSize ?? ''} placeholder="16px" onChange={(e) => setForm((f) => ({ ...f, fontSize: e.target.value }))} />
                </FormField>
                <FormField label="Arrondi">
                  <Input value={form.borderRadius ?? ''} placeholder="12px" onChange={(e) => setForm((f) => ({ ...f, borderRadius: e.target.value }))} />
                </FormField>
              </div>

              <div style={{ marginTop: 24, display: 'flex', gap: 12 }}>
                <Button type="submit" disabled={saving}>{saving ? 'Enregistrement...' : 'Enregistrer'}</Button>
                {storefrontUrl && (
                  <Button type="button" variant="secondary" onClick={() => window.open(storefrontUrl, '_blank', 'noopener')}>
                    Prévisualiser
                  </Button>
                )}
              </div>
            </form>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
