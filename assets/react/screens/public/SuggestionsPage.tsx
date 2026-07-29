import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Modal } from '../../backoffice/components/Modal';
import { SuggestionCommentForm, SuggestionCommentList } from '../../backoffice/components/suggestions/SuggestionCommentList';
import { SuggestionHistory } from '../../backoffice/components/suggestions/SuggestionHistory';
import { SuggestionReactionBar } from '../../backoffice/components/suggestions/SuggestionReactionBar';
import { SuggestionStatusBadge } from '../../backoffice/components/suggestions/SuggestionStatusBadge';
import { SUGGESTION_STATUS_OPTIONS, type Suggestion, type SuggestionCategory } from '../../backoffice/components/suggestions/SuggestionTypes';
import { Button, Card, Input, Select, Textarea } from '../../components/ui';
import { authHeaders, resolveBoutiqueSlug } from './boutiqueRouting';
import { useAuth } from '../../auth/useAuth';
import { PublicHeader } from '../../components/PublicHeader';

const PAGE_SIZE = 12;

async function requestJson<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`/api${path}`, {
    ...options,
    headers: { 'Content-Type': 'application/json', ...(authHeaders() ?? {}), ...(options.headers ?? {}) },
  });
  if (!response.ok) {
    const error = await response.json().catch(() => ({ detail: response.statusText })) as { detail?: string; message?: string };
    throw new Error(error.detail ?? error.message ?? `Erreur ${response.status}`);
  }
  if (response.status === 204) return undefined as T;
  return response.json() as Promise<T>;
}

function collection<T>(payload: { member?: T[]; items?: T[]; totalItems?: number }): { items: T[]; total: number } {
  const items = payload.member ?? payload.items ?? [];
  return { items, total: payload.totalItems ?? items.length };
}

export function SuggestionsPage() {
  const navigate = useNavigate();
  const { user, signOut } = useAuth();
  const boutiqueSlug = resolveBoutiqueSlug(/^\/boutiques\/([^/]+)/) || new URLSearchParams(window.location.search).get('boutique') || '';
  const boutiqueQuery = boutiqueSlug ? `?boutiqueSlug=${encodeURIComponent(boutiqueSlug)}` : '';
  const canInteract = Boolean(authHeaders());
  const roles = user?.profile.roles ?? [];
  const canAccessBackOffice = roles.includes('ROLE_BOUTIQUE_ADMIN') || roles.includes('ROLE_SUPER_ADMIN');
  const canCreate = canAccessBackOffice;
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('');
  const [status, setStatus] = useState('');
  const [sort, setSort] = useState('newest');
  const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [categories, setCategories] = useState<SuggestionCategory[]>([]);
  const [selected, setSelected] = useState<Suggestion | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [createLoading, setCreateLoading] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);
  const [createNotice, setCreateNotice] = useState<string | null>(null);
  const [createForm, setCreateForm] = useState({ title: '', description: '', categoryId: '' });

  const listPath = useMemo(() => {
    const params = new URLSearchParams({ page: String(page), limit: String(PAGE_SIZE), sort });
    if (search) params.set('search', search);
    if (category) params.set('category', category);
    if (status) params.set('status', status);
    if (boutiqueSlug) params.set('boutiqueSlug', boutiqueSlug);
    return `/public/suggestions?${params.toString()}`;
  }, [page, search, category, status, sort, boutiqueSlug]);

  const loadSuggestions = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [suggestionPayload, categoryPayload] = await Promise.all([
        requestJson<{ member?: Suggestion[]; items?: Suggestion[]; totalItems?: number }>(listPath),
        requestJson<{ member?: SuggestionCategory[]; items?: SuggestionCategory[] }>('/public/suggestion-categories'),
      ]);
      const result = collection(suggestionPayload);
      setSuggestions(result.items);
      setTotalItems(result.total);
      setCategories(collection(categoryPayload).items);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Impossible de charger les suggestions.');
    } finally {
      setLoading(false);
    }
  }, [listPath]);

  useEffect(() => { void loadSuggestions(); }, [loadSuggestions]);

  async function openDetail(item: Suggestion) {
    setSelected(item);
    setDetailLoading(true);
    try {
      setSelected(await requestJson<Suggestion>(`/public/suggestions/${item.id}${boutiqueQuery}`));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Impossible de charger le détail.');
    } finally {
      setDetailLoading(false);
    }
  }

  async function reloadDetail() {
    if (!selected) return;
    setSelected(await requestJson<Suggestion>(`/public/suggestions/${selected.id}${boutiqueQuery}`));
  }

  async function react(type: string) {
    if (!selected || !canInteract) return;
    setActionLoading(true);
    try {
      const query = boutiqueQuery;
      if (selected.currentUserReaction === type) {
        await requestJson<void>(`/suggestions/${selected.id}/reactions${query}`, { method: 'DELETE' });
      } else {
        await requestJson(`/suggestions/${selected.id}/reactions${query}`, { method: 'POST', body: JSON.stringify({ type }) });
      }
      await reloadDetail();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Réaction impossible.');
    } finally {
      setActionLoading(false);
    }
  }

  async function comment(content: string) {
    if (!selected || !canInteract) return;
    setActionLoading(true);
    try {
      const query = boutiqueQuery;
      await requestJson(`/suggestions/${selected.id}/comments${query}`, { method: 'POST', body: JSON.stringify({ content, visibility: 'public' }) });
      await reloadDetail();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Commentaire impossible.');
    } finally {
      setActionLoading(false);
    }
  }

  async function createSuggestion() {
    const title = createForm.title.trim();
    const description = createForm.description.trim();
    if (!title || !description) {
      setCreateError('Le titre et la description sont obligatoires.');

      return;
    }

    setCreateLoading(true);
    setCreateError(null);
    try {
      if (!canCreate) return;
      await requestJson<Suggestion>(`/suggestions${boutiqueQuery}`, {
        method: 'POST',
        body: JSON.stringify({
          title,
          description,
          categoryId: createForm.categoryId || null,
          showAuthorPublic: false,
          showBoutiquePublic: true,
        }),
      });
      setCreateForm({ title: '', description: '', categoryId: '' });
      setCreateOpen(false);
      setCreateNotice('Votre idée a été envoyée. Elle sera visible après validation.');
      await loadSuggestions();
    } catch (err) {
      setCreateError(err instanceof Error ? err.message : 'Impossible d’envoyer votre idée.');
    } finally {
      setCreateLoading(false);
    }
  }

  const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));

  return (
    <main className="suggestions-public-shell">
      <PublicHeader canAccessBackOffice={canAccessBackOffice} isAuthenticated={canInteract} onSignOut={signOut} />
      <section className="suggestions-public-page">
          <div className="suggestions-public-hero">
            <p className="suggestions-eyebrow">Communauté Hanooti</p>
            <h1>Boîte à suggestions</h1>
            <p>Partagez vos idées, votez pour les améliorations qui comptent et suivez leur évolution.</p>
            <div className="suggestions-public-hero-actions">
              <button className="lovable-button" type="button" onClick={() => { navigate('/boutiques'); }}>
                Explorer les Boutiques <span className="material-symbols-outlined">arrow_forward</span>
              </button>
              {canCreate && <Button className="lovable-button" onClick={() => { setCreateError(null); setCreateOpen(true); }}>Proposer une idée</Button>}
            </div>
            {createNotice && <p className="suggestions-public-notice" role="status">{createNotice}</p>}
          </div>
        <Card className="suggestions-public-filters">
          <Input aria-label="Rechercher une suggestion" placeholder="Rechercher une idée..." value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} />
          <Select aria-label="Filtrer par catégorie" value={category} onChange={(event) => { setCategory(event.target.value); setPage(1); }}><option value="">Toutes les catégories</option>{categories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select>
          <Select aria-label="Filtrer par statut" value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }}><option value="">Tous les statuts</option>{SUGGESTION_STATUS_OPTIONS.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select>
          <Select aria-label="Trier les suggestions" value={sort} onChange={(event) => { setSort(event.target.value); setPage(1); }}><option value="newest">Plus récentes</option><option value="oldest">Plus anciennes</option><option value="updated">Modifiées récemment</option><option value="title">Titre</option></Select>
        </Card>

        {loading ? <div className="suggestions-public-state" role="status">Chargement des suggestions...</div> : error ? <div className="suggestions-public-state suggestions-public-error" role="alert"><strong>Impossible de charger les suggestions</strong><p>{error}</p><Button className="lovable-button lovable-button--secondary" variant="secondary" onClick={() => { void loadSuggestions(); }}>Réessayer</Button></div> : suggestions.length === 0 ? <div className="suggestions-public-state"><strong>Aucune suggestion</strong><p>Essayez de modifier vos filtres ou revenez plus tard.</p></div> : (
          <div className="suggestions-public-grid">
            {suggestions.map((suggestion) => (
              <Card className="suggestion-public-card" key={suggestion.id}>
                <div className="suggestion-public-card-top"><SuggestionStatusBadge status={suggestion.status} />{suggestion.categoryName && <span>{suggestion.categoryName}</span>}</div>
                <h2>{suggestion.title}</h2>
                <p>{suggestion.description}</p>
                <div className="suggestion-public-card-meta"><span>{suggestion.reactionCount} réactions</span><span>{suggestion.commentCount} commentaires</span></div>
                <Button className="lovable-button lovable-button--secondary" variant="secondary" onClick={() => { void openDetail(suggestion); }}>Voir le détail</Button>
              </Card>
            ))}
          </div>
        )}
        {totalPages > 1 && <nav className="suggestions-public-pagination" aria-label="Pagination des suggestions"><Button className="lovable-button lovable-button--secondary" variant="ghost" disabled={page <= 1} onClick={() => setPage((current) => current - 1)}>Précédent</Button><span>Page {page} sur {totalPages}</span><Button className="lovable-button lovable-button--secondary" variant="ghost" disabled={page >= totalPages} onClick={() => setPage((current) => current + 1)}>Suivant</Button></nav>}
      </section>

      <Modal isOpen={createOpen} onClose={() => { if (!createLoading) setCreateOpen(false); }} title="Proposer une idée" width="620px">
        <div className="suggestion-create-form">
          <label>
            <span>Titre</span>
            <Input value={createForm.title} onChange={(event) => setCreateForm((current) => ({ ...current, title: event.target.value }))} placeholder="Ex. Ajouter un mode sombre" maxLength={255} />
          </label>
          <label>
            <span>Catégorie</span>
            <Select value={createForm.categoryId} onChange={(event) => setCreateForm((current) => ({ ...current, categoryId: event.target.value }))}>
              <option value="">Choisir une catégorie</option>
              {categories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </Select>
          </label>
          <label>
            <span>Description</span>
            <Textarea value={createForm.description} onChange={(event) => setCreateForm((current) => ({ ...current, description: event.target.value }))} placeholder="Expliquez le besoin et le bénéfice attendu..." rows={6} />
          </label>
          {createError && <p className="suggestion-create-error" role="alert">{createError}</p>}
          <div className="suggestion-create-actions">
            <Button className="lovable-button lovable-button--secondary" variant="secondary" disabled={createLoading} onClick={() => setCreateOpen(false)}>Annuler</Button>
            <Button className="lovable-button" disabled={createLoading} onClick={() => { void createSuggestion(); }}>{createLoading ? 'Envoi...' : 'Envoyer mon idée'}</Button>
          </div>
        </div>
      </Modal>

      <Modal isOpen={!!selected} onClose={() => setSelected(null)} title={selected?.title ?? 'Suggestion'} width="760px">
        {detailLoading ? <div className="suggestions-public-state">Chargement...</div> : selected && (
          <div className="suggestion-public-detail">
            <div className="suggestion-public-detail-meta"><SuggestionStatusBadge status={selected.status} />{selected.categoryName && <span>{selected.categoryName}</span>}<time dateTime={selected.createdAt}>{new Date(selected.createdAt).toLocaleDateString('fr-FR')}</time></div>
            <p className="suggestion-public-detail-description">{selected.description}</p>
            {selected.officialResponse && <div className="suggestion-official-response"><strong>Réponse officielle{selected.officialResponseBy ? ` · ${selected.officialResponseBy}` : ''}</strong><p>{selected.officialResponse}</p></div>}
            {canInteract ? <SuggestionReactionBar counts={selected.reactionCounts} currentReaction={selected.currentUserReaction} onReact={react} isLoading={actionLoading} /> : <p className="suggestion-login-hint">Connectez-vous pour réagir ou commenter cette suggestion.</p>}
            <section><h3>Commentaires publics</h3><SuggestionCommentList comments={selected.comments} />{canInteract && <SuggestionCommentForm onSubmit={comment} isLoading={actionLoading} />}</section>
            {selected.history && selected.history.length > 0 && <section><h3>Évolution</h3><SuggestionHistory history={selected.history} /></section>}
          </div>
        )}
      </Modal>
    </main>
  );
}
