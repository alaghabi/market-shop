import { useCallback, useState } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { useNotification } from '../../hooks/useNotification';
import { PageHeader } from '../../layout/Shell';
import { Card, CardBody } from '../../components/Card';
import { Button } from '../../components/Button';
import { Badge } from '../../components/Badge';
import { Modal } from '../../components/Modal';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { FormField, Input } from '../../components/FormField';
import { MediaField } from '../../components/MediaField';
import { LoadingState, EmptyState, ErrorState } from '../../components/States';
import { Pagination } from '../../components/Pagination';
import { useBoutique } from '../../hooks/useBoutique';

type Theme = {
  id: string;
  name: string;
  code: string;
  previewImage?: string | null;
  isActive: boolean;
  isDefault: boolean;
};

type ThemeForm = { name: string; code: string; previewImage: string; isActive: boolean; isDefault: boolean };
const emptyForm: ThemeForm = { name: '', code: '', previewImage: '', isActive: true, isDefault: false };
const PAGE_SIZE = 20;

function themeIsActive(value: unknown): boolean {
  return value === true || value === 1 || value === '1' || value === 'true';
}

export function ThemesPage({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const { boutique, boutiques } = useBoutique();
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<Theme | null>(null);
  const [form, setForm] = useState<ThemeForm>(emptyForm);
  const [deleteId, setDeleteId] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [page, setPage] = useState(1);
  const [activeOverrides, setActiveOverrides] = useState<Record<string, boolean>>({});

  const fetchThemes = useCallback(() => api.getCollection<Theme>(`/admin/themes?page=${page}&itemsPerPage=${PAGE_SIZE}`), [api, page]);
  const { data, isLoading, error, refresh } = useApiData(fetchThemes, [page]);
  const themes = data?.member ?? [];
  const totalPages = Math.max(1, Math.ceil((data?.totalItems ?? 0) / PAGE_SIZE));

  const openCreate = () => { setEditing(null); setForm(emptyForm); setModalOpen(true); };
  const openEdit = (theme: Theme) => {
    setEditing(theme);
    setForm({ name: theme.name, code: theme.code, previewImage: theme.previewImage ?? '', isActive: activeOverrides[theme.id] ?? themeIsActive(theme.isActive), isDefault: theme.isDefault });
    setModalOpen(true);
  };

  const save = async () => {
    if (!form.name.trim() || !form.code.trim()) {
      showNotice('Le nom et le code sont obligatoires.', 'error');
      return;
    }
    setSaving(true);
    try {
      const payload = { ...form, name: form.name.trim(), code: form.code.trim(), previewImage: form.previewImage.trim() || null };
      if (editing) await api.patch(`/admin/themes/${editing.id}`, payload);
      else await api.post('/admin/themes', payload);
      showNotice(editing ? 'Thème modifié.' : 'Thème créé.', 'success');
      setModalOpen(false);
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Impossible de sauvegarder le thème.', 'error');
    } finally {
      setSaving(false);
    }
  };

  const update = async (theme: Theme, changes: Partial<Theme>) => {
    const previousOverride = activeOverrides[theme.id];
    const currentIsActive = previousOverride ?? themeIsActive(theme.isActive);
    const nextIsActive = typeof changes.isActive === 'boolean' ? changes.isActive : currentIsActive;

    if (typeof changes.isActive === 'boolean') {
      setActiveOverrides((current) => ({ ...current, [theme.id]: nextIsActive }));
    }

    try {
      await api.patch(`/admin/themes/${theme.id}`, {
        name: theme.name,
        code: theme.code,
        previewImage: theme.previewImage ?? null,
        isActive: currentIsActive,
        isDefault: theme.isDefault,
        ...changes,
      });
      showNotice('Thème mis à jour.', 'success');
      refresh();
    } catch (err) {
      if (typeof changes.isActive === 'boolean') {
        setActiveOverrides((current) => {
          const next = { ...current };
          if (undefined === previousOverride) delete next[theme.id];
          else next[theme.id] = previousOverride;
          return next;
        });
      }
      showNotice(err instanceof Error ? err.message : 'Impossible de mettre à jour le thème.', 'error');
    }
  };

  const remove = async () => {
    if (!deleteId) return;
    try {
      await api.delete(`/admin/themes/${deleteId}`);
      showNotice('Thème supprimé.', 'success');
      setDeleteId(null);
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Impossible de supprimer le thème.', 'error');
    }
  };

  return (
    <div>
      <PageHeader title="Thèmes plateforme" description="Gérez les thèmes disponibles pour les boutiques." actions={<Button variant="primary" onClick={openCreate}>Nouveau thème</Button>} />
      <Card>
        <CardBody>
          {isLoading ? <LoadingState /> : error ? <ErrorState message={error} onRetry={refresh} /> : themes.length === 0 ? <EmptyState title="Aucun thème" /> : (
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: 14 }}>
                {themes.map((theme) => (
                 (() => {
                  const isActive = activeOverrides[theme.id] ?? themeIsActive(theme.isActive);

                  return (
                 <div key={theme.id} style={{ border: '1px solid var(--bo-border)', borderRadius: 10, overflow: 'hidden' }}>
                  {theme.previewImage && <img src={theme.previewImage} alt="" style={{ width: '100%', height: 100, objectFit: 'cover' }} />}
                  <div style={{ padding: 14 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                      <div><strong>{theme.name}</strong><div style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>{theme.code}</div></div>
                      <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap', justifyContent: 'flex-end' }}>
                         <Badge tone={isActive ? 'success' : 'error'}>{isActive ? 'Actif' : 'Inactif'}</Badge>
                        {theme.isDefault && <Badge tone="info">Défaut</Badge>}
                      </div>
                    </div>
                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 14 }}>
                      <Button variant="secondary" size="sm" onClick={() => openEdit(theme)}>Modifier</Button>
                       <Button variant="ghost" size="sm" onClick={() => update(theme, { isActive: !isActive })}>{isActive ? 'Désactiver' : 'Activer'}</Button>
                      {!theme.isDefault && <Button variant="ghost" size="sm" onClick={() => update(theme, { isDefault: true })}>Définir défaut</Button>}
                      <Button variant="danger" size="sm" onClick={() => setDeleteId(theme.id)}>Supprimer</Button>
                    </div>
                  </div>
                  </div>
                  );
                 })()
                ))}
               <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
             </div>
          )}
        </CardBody>
      </Card>
      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Modifier le thème' : 'Créer un thème'} footer={<><Button variant="secondary" onClick={() => setModalOpen(false)}>Annuler</Button><Button variant="primary" onClick={save} disabled={saving}>{saving ? 'En cours...' : 'Enregistrer'}</Button></>}>
        <div style={{ display: 'grid', gap: 14 }}>
          <FormField label="Nom" required><Input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></FormField>
          <FormField label="Code" required><Input value={form.code} onChange={(event) => setForm({ ...form, code: event.target.value })} /></FormField>
           <MediaField
             label="Image aperçu"
             value={form.previewImage}
             onChange={(previewImage) => setForm({ ...form, previewImage })}
             boutiqueId={boutique?.id ?? boutiques[0]?.id}
             context="themes"
             hint="Collez une URL ou importez une image."
           />
          <div style={{ display: 'flex', gap: 16 }}><label><input type="checkbox" checked={form.isActive} onChange={(event) => setForm({ ...form, isActive: event.target.checked })} /> Actif</label><label><input type="checkbox" checked={form.isDefault} onChange={(event) => setForm({ ...form, isDefault: event.target.checked })} /> Défaut</label></div>
        </div>
      </Modal>
      <ConfirmDialog isOpen={deleteId !== null} title="Supprimer le thème" message="Ce thème sera supprimé du catalogue plateforme." confirmLabel="Supprimer" onConfirm={remove} onClose={() => setDeleteId(null)} danger />
    </div>
  );
}
