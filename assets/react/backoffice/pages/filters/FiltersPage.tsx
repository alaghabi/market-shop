import { useState, useCallback } from 'react';
import type { ProductFilter } from '../../types';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardHeader, CardBody } from '../../components/Card';
import { Button } from '../../components/Button';
import { Table } from '../../components/Table';
import { Badge } from '../../components/Badge';
import { Pagination } from '../../components/Pagination';
import { Modal } from '../../components/Modal';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { FormField, Input, Select } from '../../components/FormField';
import { LoadingState, EmptyState, ErrorState } from '../../components/States';
import { PageHeader } from '../../layout/Shell';
import { useNotification } from '../../hooks/useNotification';
import { useBoutique } from '../../hooks/useBoutique';
import { BoutiqueFormSelect, resolveFormBoutiqueId } from '../../components/BoutiqueFormSelect';

const PAGE_SIZE = 20;

const FILTER_PRESETS = [
  { name: 'Couleur', type: 'color', example: '#2563EB', hint: 'Exemple : #2563EB.' },
  { name: 'Taille', type: 'select', example: 'M', hint: 'Exemple : M.' },
  { name: 'Matière', type: 'select', example: 'Coton', hint: 'Exemple : Coton.' },
  { name: 'Marque', type: 'select', example: 'Nike', hint: 'Exemple : Nike.' },
  { name: 'Style', type: 'select', example: 'Casual', hint: 'Exemple : Casual.' },
  { name: 'Format', type: 'select', example: 'Grand', hint: 'Exemple : Grand.' },
  { name: 'Pointure', type: 'select', example: '42', hint: 'Exemple : 42.' },
  { name: 'Prix', type: 'range', example: '0-100', hint: 'Exemple : 0-100.' },
];

type FilterForm = {
  boutiqueId: string;
  name: string;
  type: string;
  values: string[];
};

const emptyForm = (): FilterForm => ({ boutiqueId: '', name: '', type: 'select', values: [''] });

function isHexColor(value: string): boolean {
  return /^#[0-9a-f]{6}$/i.test(value);
}

export function FiltersPage({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const { boutique } = useBoutique();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<ProductFilter | null>(null);
  const [editing, setEditing] = useState<ProductFilter | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const [form, setForm] = useState<FilterForm>(emptyForm);

  const fetchData = useCallback(async () => {
    const params = new URLSearchParams();
    params.set('page', String(page));
    params.set('itemsPerPage', String(PAGE_SIZE));
    if (search) params.set('name', search);
    return api.getCollection<ProductFilter>('/filters?' + params.toString());
  }, [api, page, search]);

  const { data, isLoading, error, refresh } = useApiData(fetchData, [page, search]);

  const filters = data?.member ?? [];
  const totalItems = data?.totalItems ?? 0;
  const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));

  function openCreate() {
    setEditing(null);
    setForm(emptyForm());
    setModalOpen(true);
  }

  function openEdit(f: ProductFilter) {
    setEditing(f);
    setForm({
      boutiqueId: f.boutiqueId ?? '',
      name: f.name,
      type: f.type,
      values: (f.values ?? []).map((v) => v.value).length > 0 ? (f.values ?? []).map((v) => v.value) : [''],
    });
    setModalOpen(true);
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    const boutiqueId = resolveFormBoutiqueId(boutique?.id, form.boutiqueId);
    if (!boutiqueId) {
      showNotice('Sélectionnez une boutique.', 'error');
      return;
    }
    setSubmitting(true);
    try {
      const values = form.values.map((value) => value.trim()).filter(Boolean).map((value) => ({ value }));
      const body = { boutiqueId, name: form.name, type: form.type, active: true, values };
      if (editing) {
        await api.patch('/filters/' + editing.id, body);
        showNotice('Filtre mis à jour.', 'success');
      } else {
        await api.post('/filters', body);
        showNotice('Filtre créé.', 'success');
      }
      setModalOpen(false);
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Erreur.', 'error');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete() {
    if (!deleteTarget) return;
    try {
      await api.delete('/filters/' + deleteTarget.id);
      showNotice('Filtre supprimé.', 'success');
      setDeleteTarget(null);
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Erreur.', 'error');
    }
  }

  const columns = [
    { key: 'name', label: 'Nom', render: (f: ProductFilter) => <strong>{f.name}</strong> },
    { key: 'type', label: 'Type', render: (f: ProductFilter) => <Badge tone="neutral">{f.type}</Badge> },
    { key: 'active', label: 'Statut', render: (f: ProductFilter) => <Badge tone={f.active ? 'success' : 'neutral'}>{f.active ? 'Actif' : 'Inactif'}</Badge> },
    { key: 'values', label: 'Valeurs', render: (f: ProductFilter) => <span style={{ fontSize: 13, color: 'var(--bo-text-secondary)' }}>{(f.values ?? []).length} valeur(s)</span> },
  ];

  const nameOptions = FILTER_PRESETS.some((preset) => preset.name === form.name)
    ? FILTER_PRESETS
    : form.name
      ? [{ name: form.name, type: form.type, hint: 'Filtre existant.' }, ...FILTER_PRESETS]
      : FILTER_PRESETS;
  const selectedPreset = FILTER_PRESETS.find((preset) => preset.name === form.name);

  if (error) return <ErrorState message={error} onRetry={refresh} />;

  return (
    <div>
      <PageHeader title="Filtres produits" description="Configurez les filtres de recherche" actions={<Button onClick={openCreate}>+ Nouveau filtre</Button>} />
      <Card>
        <CardHeader>
          <input className="bo-input" style={{ maxWidth: 320 }} placeholder="Rechercher un filtre..." value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
        </CardHeader>
        <CardBody>
          {isLoading ? <LoadingState /> : filters.length === 0 ? (
            <EmptyState title="Aucun filtre" message="Créez votre premier filtre." action={{ label: '+ Nouveau filtre', onClick: openCreate }} />
          ) : (
            <>
              <Table columns={columns} data={filters} onRowClick={openEdit} renderActions={(filter) => <Button size="sm" variant="danger" onClick={(event) => { event.stopPropagation(); setDeleteTarget(filter); }}>Supprimer</Button>} />
              <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
            </>
          )}
        </CardBody>
      </Card>

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editing ? 'Modifier' : 'Nouveau filtre'} footer={
        <><Button variant="secondary" onClick={() => setModalOpen(false)}>Annuler</Button><Button onClick={handleSubmit} disabled={submitting}>{submitting ? '...' : editing ? 'Mettre à jour' : 'Créer'}</Button></>
      }>
        <form className="bo-form" onSubmit={handleSubmit}>
          <BoutiqueFormSelect value={form.boutiqueId} onChange={(boutiqueId) => setForm((f) => ({ ...f, boutiqueId }))} />
          <FormField label="Filtre" required hint="Choisissez un filtre standard pour votre boutique.">
            <Select required value={form.name} onChange={(e) => {
              const preset = FILTER_PRESETS.find((item) => item.name === e.target.value);
              setForm((f) => ({
                ...f,
                name: e.target.value,
                type: preset?.type ?? f.type,
                values: preset && f.values.every((value) => !value.trim()) ? [preset.example] : f.values,
              }));
            }}>
              <option value="">Sélectionner un filtre</option>
              {nameOptions.map((preset) => <option key={preset.name} value={preset.name}>{preset.name}</option>)}
            </Select>
          </FormField>
          <FormField label="Type">
            <Select value={form.type} onChange={(e) => setForm((f) => ({ ...f, type: e.target.value }))}>
              <option value="color">Couleur</option>
              <option value="select">Sélection</option>
              <option value="range">Plage</option>
            </Select>
          </FormField>
          <FormField label="Valeurs" hint={selectedPreset?.hint ?? 'Ajoutez une valeur par ligne.'}>
            <div className="bo-filter-values">
              {form.values.map((value, index) => {
                const colorValue = isHexColor(value) ? value : '#000000';
                return (
                  <div className="bo-filter-value-row" key={index}>
                    {form.type === 'color' ? (
                      <input className="bo-color-input" type="color" value={colorValue} aria-label={`Couleur ${index + 1}`} onChange={(e) => setForm((f) => ({ ...f, values: f.values.map((item, itemIndex) => itemIndex === index ? e.target.value : item) }))} />
                    ) : (
                      <Input required={index === 0} value={value} placeholder={`Valeur ${index + 1}`} onChange={(e) => setForm((f) => ({ ...f, values: f.values.map((item, itemIndex) => itemIndex === index ? e.target.value : item) }))} />
                    )}
                    {form.values.length > 1 && <Button type="button" variant="ghost" size="sm" onClick={() => setForm((f) => ({ ...f, values: f.values.filter((_, itemIndex) => itemIndex !== index) }))} aria-label={`Supprimer la valeur ${index + 1}`}>×</Button>}
                  </div>
                );
              })}
              <Button type="button" variant="secondary" size="sm" onClick={() => setForm((f) => ({ ...f, values: [...f.values, ''] }))}>+ Ajouter une valeur</Button>
            </div>
          </FormField>
        </form>
      </Modal>

      <ConfirmDialog isOpen={!!deleteTarget} onClose={() => setDeleteTarget(null)} onConfirm={handleDelete} title="Supprimer" message={`Supprimer "${deleteTarget?.name}" ?`} confirmLabel="Supprimer" danger />
    </div>
  );
}
