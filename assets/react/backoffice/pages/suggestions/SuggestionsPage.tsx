import { useCallback, useState } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { useBoutique } from '../../hooks/useBoutique';
import { useNotification } from '../../hooks/useNotification';
import { Card, CardBody, CardHeader } from '../../components/Card';
import { Button } from '../../components/Button';
import { Badge } from '../../components/Badge';
import { FormField, Input, Select, Textarea } from '../../components/FormField';
import { FiltersBar } from '../../components/FiltersBar';
import { Modal } from '../../components/Modal';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { Pagination } from '../../components/Pagination';
import { Table } from '../../components/Table';
import { EmptyState, ErrorState, LoadingState } from '../../components/States';
import { PageHeader } from '../../layout/Shell';
import { SuggestionCommentForm, SuggestionCommentList } from '../../components/suggestions/SuggestionCommentList';
import { SuggestionHistory } from '../../components/suggestions/SuggestionHistory';
import { SuggestionReactionBar } from '../../components/suggestions/SuggestionReactionBar';
import { SuggestionStatusBadge } from '../../components/suggestions/SuggestionStatusBadge';
import {
  SUGGESTION_STATUS_OPTIONS,
  type SuggestionComment,
  type Suggestion,
  type SuggestionCategory,
} from '../../components/suggestions/SuggestionTypes';

const PAGE_SIZE = 20;

type SuggestionForm = {
  title: string;
  description: string;
  categoryId: string;
  showAuthorPublic: boolean;
  showBoutiquePublic: boolean;
};

type CategoryForm = {
  name: string;
  slug: string;
  description: string;
  position: string;
  isActive: boolean;
};

const emptyForm: SuggestionForm = {
  title: '',
  description: '',
  categoryId: '',
  showAuthorPublic: false,
  showBoutiquePublic: true,
};

const emptyCategoryForm: CategoryForm = {
  name: '',
  slug: '',
  description: '',
  position: '0',
  isActive: true,
};

function slugify(value: string): string {
  return value
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '');
}

function formatFilterDate(date: string): string {
  return date ? `${date}T00:00:00+00:00` : '';
}

function AccessState({ title, message }: { title: string; message: string }) {
  return (
    <Card>
      <CardBody>
        <div className="bo-state-message" role="status">
          <h2>{title}</h2>
          <p>{message}</p>
        </div>
      </CardBody>
    </Card>
  );
}

export function SuggestionsPage({
  getAccessToken,
  userRoles = [],
}: {
  getAccessToken: () => string | null;
  userRoles?: string[];
}) {
  const api = useApiClient(getAccessToken);
  const { boutique } = useBoutique();
  const { showNotice } = useNotification();
  const isSuperAdmin = userRoles.includes('ROLE_SUPER_ADMIN');
  const canManage = isSuperAdmin || userRoles.includes('ROLE_BOUTIQUE_ADMIN');
  const disabled = !isSuperAdmin && !boutique;
  const [page, setPage] = useState(1);
  const [categoryPage, setCategoryPage] = useState(1);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [category, setCategory] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [sort, setSort] = useState('newest');
  const [selected, setSelected] = useState<Suggestion | null>(null);
  const [detail, setDetail] = useState<Suggestion | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState<string | null>(null);
  const [editing, setEditing] = useState<Suggestion | null | undefined>(undefined);
  const [form, setForm] = useState<SuggestionForm>(emptyForm);
  const [saving, setSaving] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);
  const [commentLoading, setCommentLoading] = useState(false);
  const [officialResponse, setOfficialResponse] = useState('');
  const [suggestionToDelete, setSuggestionToDelete] = useState<Suggestion | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [categoryModalOpen, setCategoryModalOpen] = useState(false);
  const [categoryEditing, setCategoryEditing] = useState<SuggestionCategory | null>(null);
  const [categoryForm, setCategoryForm] = useState<CategoryForm>(emptyCategoryForm);
  const [categorySaving, setCategorySaving] = useState(false);
  const [categoryActionLoading, setCategoryActionLoading] = useState<string | null>(null);
  const [categoryToDelete, setCategoryToDelete] = useState<SuggestionCategory | null>(null);
  const [categoryDeleteLoading, setCategoryDeleteLoading] = useState(false);

  const fetchSuggestions = useCallback(async () => {
    const params = new URLSearchParams({
      page: String(page),
      limit: String(PAGE_SIZE),
      sort,
    });
    if (search) params.set('search', search);
    if (status) params.set('status', status);
    if (category) params.set('category', category);
    if (from) params.set('from', formatFilterDate(from));
    if (to) params.set('to', `${to}T23:59:59+00:00`);
    return api.getCollection<Suggestion>(`/suggestions?${params.toString()}`);
  }, [api, page, search, status, category, from, to, sort]);

  const fetchCategories = useCallback(
    () => api.getCollection<SuggestionCategory>('/public/suggestion-categories?itemsPerPage=100'),
    [api],
  );
  const fetchAdminCategories = useCallback(
    () => isSuperAdmin
      ? api.getCollection<SuggestionCategory>(`/admin/suggestion-categories?page=${categoryPage}&itemsPerPage=${PAGE_SIZE}`)
      : Promise.resolve({ member: [], totalItems: 0 }),
    [api, isSuperAdmin, categoryPage],
  );
  const { data, isLoading, error, refresh } = useApiData(fetchSuggestions, [page, search, status, category, from, to, sort]);
  const { data: categoryData, refresh: refreshCategories } = useApiData(fetchCategories, []);
  const {
    data: adminCategoryData,
    isLoading: adminCategoriesLoading,
    error: adminCategoriesError,
    refresh: refreshAdminCategories,
  } = useApiData(fetchAdminCategories, [isSuperAdmin, categoryPage]);
  const suggestions = data?.member ?? [];
  const categories = categoryData?.member ?? [];
  const adminCategories = adminCategoryData?.member ?? [];
  const totalPages = Math.max(1, Math.ceil((data?.totalItems ?? suggestions.length) / PAGE_SIZE));
  const categoryTotalPages = Math.max(1, Math.ceil((adminCategoryData?.totalItems ?? 0) / PAGE_SIZE));

  function resetFilters() {
    setSearch('');
    setStatus('');
    setCategory('');
    setFrom('');
    setTo('');
    setSort('newest');
    setPage(1);
  }

  async function openDetail(item: Suggestion) {
    setSelected(item);
    setDetail(item);
    setDetailError(null);
    setDetailLoading(true);
    try {
      setDetail(await api.get<Suggestion>(`/suggestions/${item.id}`));
    } catch (err) {
      setDetailError(err instanceof Error ? err.message : 'Impossible de charger le détail.');
    } finally {
      setDetailLoading(false);
    }
  }

  function openCreate() {
    setEditing(null);
    setForm(emptyForm);
  }

  function openCategoryCreate() {
    setCategoryEditing(null);
    setCategoryForm(emptyCategoryForm);
    setCategoryModalOpen(true);
  }

  function openCategoryEdit(item: SuggestionCategory) {
    setCategoryEditing(item);
    setCategoryForm({
      name: item.name,
      slug: item.slug,
      description: item.description ?? '',
      position: String(item.position),
      isActive: item.isActive,
    });
    setCategoryModalOpen(true);
  }

  async function saveCategory() {
    const name = categoryForm.name.trim();
    const slug = categoryForm.slug.trim() || slugify(name);
    if (!name || !slug) return;

    setCategorySaving(true);
    try {
      const payload = {
        name,
        slug,
        description: categoryForm.description.trim() || null,
        position: Number.parseInt(categoryForm.position, 10) || 0,
        isActive: categoryForm.isActive,
      };
      if (categoryEditing) {
        await api.patch<SuggestionCategory>(`/admin/suggestion-categories/${categoryEditing.id}`, payload);
        showNotice('Catégorie mise à jour.', 'success');
      } else {
        await api.post<SuggestionCategory>('/admin/suggestion-categories', payload);
        showNotice('Catégorie créée.', 'success');
      }
      setCategoryModalOpen(false);
      setCategoryEditing(null);
      refreshAdminCategories();
      refreshCategories();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Impossible d’enregistrer la catégorie.', 'error');
    } finally {
      setCategorySaving(false);
    }
  }

  async function updateCategoryStatus(item: SuggestionCategory, isActive: boolean) {
    setCategoryActionLoading(item.id);
    try {
      await api.patch<SuggestionCategory>(`/admin/suggestion-categories/${item.id}`, {
        name: item.name,
        slug: item.slug,
        description: item.description ?? null,
        position: item.position,
        isActive,
      });
      showNotice(isActive ? 'Catégorie activée.' : 'Catégorie désactivée.', 'success');
      refreshAdminCategories();
      refreshCategories();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Impossible de modifier le statut de la catégorie.', 'error');
    } finally {
      setCategoryActionLoading(null);
    }
  }

  async function deleteCategory() {
    if (!categoryToDelete) return;
    setCategoryDeleteLoading(true);
    try {
      await api.delete(`/admin/suggestion-categories/${categoryToDelete.id}`);
      showNotice('Catégorie supprimée.', 'success');
      setCategoryToDelete(null);
      refreshAdminCategories();
      refreshCategories();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Impossible de supprimer la catégorie.', 'error');
    } finally {
      setCategoryDeleteLoading(false);
    }
  }

  function openEdit(item: Suggestion) {
    setEditing(item);
    setForm({
      title: item.title,
      description: item.description,
      categoryId: item.categoryId ?? '',
      showAuthorPublic: item.showAuthorPublic,
      showBoutiquePublic: item.showBoutiquePublic,
    });
  }

  async function saveSuggestion() {
    if (!form.title.trim() || !form.description.trim()) return;
    setSaving(true);
    try {
      const payload = {
        title: form.title.trim(),
        description: form.description.trim(),
        categoryId: form.categoryId || null,
        showAuthorPublic: form.showAuthorPublic,
        showBoutiquePublic: form.showBoutiquePublic,
      };
      const saved = editing ? await api.patch<Suggestion>(`/suggestions/${editing.id}`, payload) : await api.post<Suggestion>('/suggestions', payload);
      showNotice(editing ? 'Suggestion mise à jour.' : 'Suggestion créée.', 'success');
      setEditing(undefined);
      if (selected?.id === saved.id) {
        setSelected(saved);
        setDetail(saved);
      }
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Impossible d’enregistrer la suggestion.', 'error');
    } finally {
      setSaving(false);
    }
  }

  async function updateDetail(next: Promise<Suggestion>, successMessage: string) {
    setActionLoading(true);
    try {
      const updated = await next;
      setDetail(updated);
      setSelected(updated);
      showNotice(successMessage, 'success');
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Action impossible.', 'error');
    } finally {
      setActionLoading(false);
    }
  }

  function changeStatus(nextStatus: string) {
    if (!detail) return;
    void updateDetail(api.patch<Suggestion>(`/admin/suggestions/${detail.id}/status`, { status: nextStatus }), 'Statut mis à jour.');
  }

  function changeVisibility(nextVisibility: string) {
    if (!detail) return;
    void updateDetail(api.patch<Suggestion>(`/admin/suggestions/${detail.id}/visibility`, { visibility: nextVisibility }), 'Visibilité mise à jour.');
  }

  function publish() {
    if (!detail) return;
    void updateDetail(api.post<Suggestion>(`/admin/suggestions/${detail.id}/publish`), 'Suggestion publiée.');
  }

  function archive() {
    if (!detail) return;
    void updateDetail(api.post<Suggestion>(`/admin/suggestions/${detail.id}/archive`), 'Suggestion archivée.');
  }

  async function submitOfficialResponse() {
    if (!detail || !officialResponse.trim()) return;
    setActionLoading(true);
    try {
      const updated = await api.post<Suggestion>(`/admin/suggestions/${detail.id}/official-response`, { response: officialResponse.trim() });
      setDetail(updated);
      setSelected(updated);
      setOfficialResponse('');
      showNotice('Réponse officielle publiée.', 'success');
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Impossible d’ajouter la réponse.', 'error');
    } finally {
      setActionLoading(false);
    }
  }

  async function submitComment(content: string) {
    if (!detail) return;
    setCommentLoading(true);
    try {
      const comment = await api.post<SuggestionComment>(`/suggestions/${detail.id}/comments`, { content, visibility: 'public' });
      setDetail((current) => current ? { ...current, comments: [...(current.comments ?? []), comment], commentCount: current.commentCount + 1 } : current);
      showNotice('Commentaire ajouté.', 'success');
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Impossible d’ajouter le commentaire.', 'error');
    } finally {
      setCommentLoading(false);
    }
  }

  async function react(type: string) {
    if (!detail) return;
    try {
      if (detail.currentUserReaction === type) {
        await api.delete(`/suggestions/${detail.id}/reactions`);
      } else {
        await api.post(`/suggestions/${detail.id}/reactions`, { type });
      }
      setDetail(await api.get<Suggestion>(`/suggestions/${detail.id}`));
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Réaction impossible.', 'error');
    }
  }

  async function deleteSuggestion() {
    if (!suggestionToDelete) return;
    setDeleteLoading(true);
    try {
      await api.delete(`/suggestions/${suggestionToDelete.id}`);
      showNotice('Suggestion supprimée.', 'success');
      setSuggestionToDelete(null);
      setSelected(null);
      setDetail(null);
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Suppression impossible.', 'error');
    } finally {
      setDeleteLoading(false);
    }
  }

  async function exportCsv() {
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (status) params.set('status', status);
    if (category) params.set('category', category);
    if (from) params.set('from', formatFilterDate(from));
    if (to) params.set('to', `${to}T23:59:59+00:00`);
    try {
      const blob = await api.download(`/admin/suggestions/export?${params.toString()}`);
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = 'suggestions.csv';
      anchor.click();
      URL.revokeObjectURL(url);
      showNotice('Export CSV téléchargé.', 'success');
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Export impossible.', 'error');
    }
  }

  const columns = [
    { key: 'title', label: 'Suggestion', sortable: true, render: (item: Suggestion) => <div><strong>{item.title}</strong><div className="bo-table-secondary">{item.description}</div></div> },
    { key: 'categoryName', label: 'Catégorie', render: (item: Suggestion) => item.categoryName || '—' },
    { key: 'status', label: 'Statut', render: (item: Suggestion) => <SuggestionStatusBadge status={item.status} /> },
    { key: 'reactionCount', label: 'Engagement', render: (item: Suggestion) => `${item.reactionCount} réaction${item.reactionCount > 1 ? 's' : ''} · ${item.commentCount} commentaire${item.commentCount > 1 ? 's' : ''}` },
    { key: 'createdAt', label: 'Date', sortable: true, render: (item: Suggestion) => new Date(item.createdAt).toLocaleDateString('fr-FR') },
  ];

  if (disabled) {
    return <AccessState title="Sélectionnez une boutique" message="Choisissez une boutique dans l’en-tête pour accéder aux suggestions." />;
  }

  if (error) {
    const isUnauthorized = /permission|accès|forbidden|403|unauthor/i.test(error);
    return isUnauthorized
      ? <AccessState title="Accès non autorisé" message="Votre rôle ou vos permissions ne permettent pas de consulter les suggestions." />
      : <ErrorState message={error} onRetry={refresh} />;
  }

  return (
    <div className="suggestions-admin-page">
      <PageHeader
        title="Boîte à suggestions"
        description="Centralisez les idées, priorisez les besoins et échangez avec votre communauté."
        actions={(
          <div className="bo-page-actions">
            {isSuperAdmin && <Button variant="secondary" onClick={() => { void exportCsv(); }}>Exporter CSV</Button>}
            {canManage && <Button onClick={openCreate}>Nouvelle suggestion</Button>}
          </div>
        )}
      />
      {isSuperAdmin && (
        <Card>
          <CardHeader>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16, width: '100%' }}>
              <div>
                <h2 style={{ margin: 0, fontSize: 16 }}>Catégories de suggestions</h2>
                <span className="bo-table-secondary">Gérez les catégories visibles dans la boîte à suggestions.</span>
              </div>
              <Button size="sm" onClick={openCategoryCreate}>Nouvelle catégorie</Button>
            </div>
          </CardHeader>
          <CardBody>
            {adminCategoriesLoading ? <LoadingState message="Chargement des catégories..." /> : adminCategoriesError ? (
              <ErrorState message={adminCategoriesError} onRetry={refreshAdminCategories} />
            ) : adminCategories.length === 0 ? (
              <EmptyState title="Aucune catégorie" message="Créez une catégorie pour organiser les suggestions." action={{ label: 'Créer une catégorie', onClick: openCategoryCreate }} />
            ) : (
              <>
                <Table
                  columns={[
                    { key: 'name', label: 'Nom', sortable: true, render: (item: SuggestionCategory) => <div><strong>{item.name}</strong><div className="bo-table-secondary">{item.slug}</div></div> },
                    { key: 'position', label: 'Position', render: (item: SuggestionCategory) => item.position },
                    { key: 'isActive', label: 'Statut', render: (item: SuggestionCategory) => <Badge tone={item.isActive ? 'success' : 'neutral'}>{item.isActive ? 'Actif' : 'Inactif'}</Badge> },
                  ]}
                  data={adminCategories}
                  renderActions={(item) => (
                    <>
                      <Button size="sm" variant="ghost" disabled={categoryActionLoading === item.id} onClick={() => openCategoryEdit(item)}>Modifier</Button>
                      <Button size="sm" variant="secondary" disabled={categoryActionLoading === item.id} onClick={() => { void updateCategoryStatus(item, !item.isActive); }}>
                        {categoryActionLoading === item.id ? '...' : item.isActive ? 'Désactiver' : 'Activer'}
                      </Button>
                      <Button size="sm" variant="danger" disabled={categoryActionLoading === item.id} onClick={() => setCategoryToDelete(item)}>Supprimer</Button>
                    </>
                  )}
                />
                <Pagination page={categoryPage} totalPages={categoryTotalPages} onPageChange={setCategoryPage} />
              </>
             )}
          </CardBody>
        </Card>
      )}
      <Card>
        <CardHeader>
          <FiltersBar
            search={search}
            onSearchChange={(value) => { setSearch(value); setPage(1); }}
            status={status}
            onStatusChange={(value) => { setStatus(value); setPage(1); }}
            statusOptions={SUGGESTION_STATUS_OPTIONS}
          >
            <Select aria-label="Catégorie" value={category} onChange={(event) => { setCategory(event.target.value); setPage(1); }}>
              <option value="">Toutes les catégories</option>
              {categories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </Select>
            <Input aria-label="Date de début" type="date" value={from} onChange={(event) => { setFrom(event.target.value); setPage(1); }} />
            <Input aria-label="Date de fin" type="date" value={to} onChange={(event) => { setTo(event.target.value); setPage(1); }} />
            <Select aria-label="Trier par" value={sort} onChange={(event) => { setSort(event.target.value); setPage(1); }}>
              <option value="newest">Plus récentes</option>
              <option value="oldest">Plus anciennes</option>
              <option value="updated">Modifiées récemment</option>
              <option value="title">Titre</option>
            </Select>
            {(search || status || category || from || to || sort !== 'newest') && <Button variant="ghost" size="sm" onClick={resetFilters}>Réinitialiser</Button>}
          </FiltersBar>
          <span className="bo-count">{data?.totalItems ?? suggestions.length} suggestion{(data?.totalItems ?? suggestions.length) > 1 ? 's' : ''}</span>
        </CardHeader>
        <CardBody>
          {isLoading ? <LoadingState message="Chargement des suggestions..." /> : suggestions.length === 0 ? (
            <EmptyState title="Aucune suggestion" message="Aucune suggestion ne correspond à vos filtres." action={canManage ? { label: 'Créer une suggestion', onClick: openCreate } : undefined} />
          ) : (
            <>
              <Table columns={columns} data={suggestions} onRowClick={(item) => { void openDetail(item); }} />
              <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
            </>
          )}
        </CardBody>
      </Card>

      <Modal isOpen={!!selected} onClose={() => { setSelected(null); setDetail(null); }} title={detail?.title ?? selected?.title ?? 'Suggestion'} width="760px" footer={selected && (
        <div className="bo-modal-actions">
          <Button variant="secondary" onClick={() => { setSelected(null); setDetail(null); }}>Fermer</Button>
          {canManage && detail && <Button variant="secondary" onClick={() => openEdit(detail)}>Modifier</Button>}
          {canManage && detail && <Button variant="danger" onClick={() => setSuggestionToDelete(detail)}>Supprimer</Button>}
        </div>
      )}>
        {detailLoading ? <LoadingState message="Chargement du détail..." /> : detailError ? <ErrorState message={detailError} onRetry={() => { if (selected) void openDetail(selected); }} /> : detail && (
          <div className="suggestion-detail">
            <div className="suggestion-detail-header">
              <div><SuggestionStatusBadge status={detail.status} /> <span className="suggestion-detail-visibility">{detail.visibility === 'public' ? 'Public' : detail.visibility === 'admins' ? 'Admins' : 'Privé'}</span></div>
              <p>{detail.categoryName || 'Sans catégorie'} · {new Date(detail.createdAt).toLocaleString('fr-FR')}</p>
            </div>
            <p className="suggestion-detail-description">{detail.description}</p>
            <SuggestionReactionBar counts={detail.reactionCounts} currentReaction={detail.currentUserReaction} onReact={react} isLoading={actionLoading} />
            {detail.officialResponse && <div className="suggestion-official-response"><strong>Réponse officielle{detail.officialResponseBy ? ` · ${detail.officialResponseBy}` : ''}</strong><p>{detail.officialResponse}</p></div>}
            {canManage && (
              <div className="suggestion-admin-controls">
                <FormField label="Statut"><Select value={detail.status} disabled={actionLoading} onChange={(event) => changeStatus(event.target.value)}>{SUGGESTION_STATUS_OPTIONS.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></FormField>
                <FormField label="Visibilité"><Select value={detail.visibility} disabled={actionLoading} onChange={(event) => changeVisibility(event.target.value)}><option value="private">Privée</option><option value="admins">Admins</option><option value="public">Publique</option></Select></FormField>
                <div className="bo-inline-actions">
                  {!detail.isPublished && <Button size="sm" disabled={actionLoading} onClick={publish}>Publier</Button>}
                  {detail.status !== 'archived' && <Button size="sm" variant="secondary" disabled={actionLoading} onClick={archive}>Archiver</Button>}
                </div>
              </div>
            )}
            {canManage && <div className="suggestion-response-form"><FormField label="Réponse officielle"><Textarea value={officialResponse} onChange={(event) => setOfficialResponse(event.target.value)} placeholder="Répondre officiellement à cette suggestion..." rows={3} /></FormField><Button size="sm" disabled={!officialResponse.trim() || actionLoading} onClick={() => { void submitOfficialResponse(); }}>Publier la réponse</Button></div>}
            <div className="suggestion-detail-grid">
              <section><h4>Commentaires</h4><SuggestionCommentList comments={detail.comments} /><SuggestionCommentForm onSubmit={submitComment} isLoading={commentLoading} /></section>
              <section><h4>Historique</h4><SuggestionHistory history={detail.history} /></section>
            </div>
          </div>
        )}
      </Modal>

      <Modal isOpen={editing !== undefined} onClose={() => { if (!saving) setEditing(undefined); }} title={editing ? 'Modifier la suggestion' : 'Nouvelle suggestion'} width="560px" footer={<div className="bo-modal-actions"><Button variant="secondary" disabled={saving} onClick={() => setEditing(undefined)}>Annuler</Button><Button disabled={saving || !form.title.trim() || !form.description.trim()} onClick={() => { void saveSuggestion(); }}>{saving ? 'Enregistrement...' : 'Enregistrer'}</Button></div>}>
        <div className="bo-form">
          <FormField label="Titre" required><Input value={form.title} maxLength={255} onChange={(event) => setForm((current) => ({ ...current, title: event.target.value }))} /></FormField>
          <FormField label="Description" required><Textarea value={form.description} rows={6} onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))} /></FormField>
          <FormField label="Catégorie"><Select value={form.categoryId} onChange={(event) => setForm((current) => ({ ...current, categoryId: event.target.value }))}><option value="">Sans catégorie</option>{categories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></FormField>
          <label className="bo-checkbox"><input type="checkbox" checked={form.showAuthorPublic} onChange={(event) => setForm((current) => ({ ...current, showAuthorPublic: event.target.checked }))} /> Afficher mon nom publiquement</label>
          <label className="bo-checkbox"><input type="checkbox" checked={form.showBoutiquePublic} onChange={(event) => setForm((current) => ({ ...current, showBoutiquePublic: event.target.checked }))} /> Afficher la boutique publiquement</label>
        </div>
      </Modal>

      {isSuperAdmin && (
        <Modal
          isOpen={categoryModalOpen}
          onClose={() => { if (!categorySaving) setCategoryModalOpen(false); }}
          title={categoryEditing ? 'Modifier la catégorie' : 'Nouvelle catégorie'}
          width="520px"
          footer={<div className="bo-modal-actions"><Button variant="secondary" disabled={categorySaving} onClick={() => setCategoryModalOpen(false)}>Annuler</Button><Button disabled={categorySaving || !categoryForm.name.trim() || !(categoryForm.slug.trim() || slugify(categoryForm.name))} onClick={() => { void saveCategory(); }}>{categorySaving ? 'Enregistrement...' : 'Enregistrer'}</Button></div>}
        >
          <div className="bo-form">
            <FormField label="Nom" required><Input value={categoryForm.name} maxLength={160} onChange={(event) => setCategoryForm((current) => ({ ...current, name: event.target.value }))} /></FormField>
            <FormField label="Slug" required hint="Utilisé dans les URLs et doit être unique."><Input value={categoryForm.slug} maxLength={180} onChange={(event) => setCategoryForm((current) => ({ ...current, slug: event.target.value }))} /></FormField>
            <FormField label="Description"><Textarea value={categoryForm.description} rows={3} onChange={(event) => setCategoryForm((current) => ({ ...current, description: event.target.value }))} /></FormField>
            <FormField label="Position"><Input type="number" min={0} value={categoryForm.position} onChange={(event) => setCategoryForm((current) => ({ ...current, position: event.target.value }))} /></FormField>
            <label className="bo-checkbox"><input type="checkbox" checked={categoryForm.isActive} onChange={(event) => setCategoryForm((current) => ({ ...current, isActive: event.target.checked }))} /> Active</label>
          </div>
        </Modal>
      )}

      <ConfirmDialog isOpen={!!suggestionToDelete} onClose={() => { if (!deleteLoading) setSuggestionToDelete(null); }} onConfirm={() => { void deleteSuggestion(); }} title="Supprimer la suggestion" message={suggestionToDelete ? `Supprimer définitivement « ${suggestionToDelete.title} » ?` : ''} confirmLabel="Supprimer définitivement" danger isLoading={deleteLoading} />
      {isSuperAdmin && <ConfirmDialog isOpen={!!categoryToDelete} onClose={() => { if (!categoryDeleteLoading) setCategoryToDelete(null); }} onConfirm={() => { void deleteCategory(); }} title="Supprimer la catégorie" message={categoryToDelete ? `Supprimer définitivement « ${categoryToDelete.name} » ?` : ''} confirmLabel="Supprimer définitivement" danger isLoading={categoryDeleteLoading} />}
    </div>
  );
}
