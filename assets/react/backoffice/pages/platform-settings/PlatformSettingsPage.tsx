import { useEffect, useState } from 'react';
import { Button } from '../../components/Button';
import { Card, CardBody, CardHeader } from '../../components/Card';
import { ErrorState, LoadingState } from '../../components/States';
import { FormField, Input } from '../../components/FormField';
import { PageHeader } from '../../layout/Shell';
import { useApiClient } from '../../hooks/useApi';
import { useNotification } from '../../hooks/useNotification';

type SocialLinks = {
  facebook: string;
  instagram: string;
  tiktok: string;
  youtube: string;
  linkedin: string;
  x_twitter: string;
  whatsapp: string;
};

type AppConfig = {
  platform_social_links?: Partial<SocialLinks>;
};

const EMPTY_LINKS: SocialLinks = {
  facebook: '', instagram: '', tiktok: '', youtube: '', linkedin: '', x_twitter: '', whatsapp: '',
};

const FIELDS: Array<{ key: keyof SocialLinks; label: string; placeholder: string; hint?: string }> = [
  { key: 'facebook', label: 'Facebook', placeholder: 'https://facebook.com/hanooti' },
  { key: 'instagram', label: 'Instagram', placeholder: 'https://instagram.com/hanooti' },
  { key: 'tiktok', label: 'TikTok', placeholder: 'https://tiktok.com/@hanooti' },
  { key: 'youtube', label: 'YouTube', placeholder: 'https://youtube.com/@hanooti' },
  { key: 'linkedin', label: 'LinkedIn', placeholder: 'https://linkedin.com/company/hanooti' },
  { key: 'x_twitter', label: 'X / Twitter', placeholder: 'https://x.com/hanooti' },
  { key: 'whatsapp', label: 'WhatsApp', placeholder: '216XXXXXXXX', hint: 'Numéro international ou lien https://wa.me/...' },
];

export function PlatformSettingsPage({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const [links, setLinks] = useState<SocialLinks>(EMPTY_LINKS);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let mounted = true;
    api.get<AppConfig>('/admin/app-config')
      .then((config) => {
        if (mounted) setLinks({ ...EMPTY_LINKS, ...(config.platform_social_links ?? {}) });
      })
      .catch((exception) => {
        if (mounted) setError(exception instanceof Error ? exception.message : 'Configuration indisponible.');
      })
      .finally(() => {
        if (mounted) setLoading(false);
      });

    return () => { mounted = false; };
  }, [api]);

  async function save(): Promise<void> {
    setSaving(true);
    try {
      await api.patch('/admin/app-config', { platform_social_links: links });
      showNotice('Réseaux sociaux de la plateforme mis à jour.', 'success');
    } catch (exception) {
      showNotice(exception instanceof Error ? exception.message : 'Impossible de sauvegarder.', 'error');
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <LoadingState message="Chargement de la configuration plateforme..." />;
  if (error) return <ErrorState message={error} onRetry={() => window.location.reload()} />;

  return (
    <div>
      <PageHeader title="Réseaux sociaux plateforme" description="Configurez les liens affichés dans les footers publics de Hanooti." />
      <Card>
        <CardHeader><h3>Comptes officiels Hanooti</h3></CardHeader>
        <CardBody>
          <div className="bo-form">
            <p style={{ color: 'var(--bo-text-muted)', fontSize: 13, margin: 0 }}>
              Les champs vides sont masqués automatiquement sur le frontoffice général.
            </p>
            {FIELDS.map((field) => (
              <FormField key={field.key} label={field.label} hint={field.hint}>
                <Input
                  type={field.key === 'whatsapp' ? 'text' : 'url'}
                  value={links[field.key]}
                  placeholder={field.placeholder}
                  onChange={(event) => setLinks((current) => ({ ...current, [field.key]: event.target.value }))}
                />
              </FormField>
            ))}
            <div style={{ marginTop: 8 }}>
              <Button onClick={() => { void save(); }} disabled={saving}>
                {saving ? 'Enregistrement...' : 'Enregistrer'}
              </Button>
            </div>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
