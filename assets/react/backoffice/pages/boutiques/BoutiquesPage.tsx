import { useState, useCallback } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { useNotification } from '../../hooks/useNotification';
import { Card, CardHeader, CardBody } from '../../components/Card';
import { Button } from '../../components/Button';
import { Badge } from '../../components/Badge';
import { LoadingState, ErrorState } from '../../components/States';
import { PageHeader } from '../../layout/Shell';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { Pagination } from '../../components/Pagination';
import { frontOfficeUrl } from '../../utils/frontOfficeUrl';

type BoutiqueSummary = {
  id: string; name: string; slug: string; status: string;
  contactEmail?: string; customDomain?: string | null;
  isVisiblePublicly?: boolean; isPublished?: boolean;
  productsCount?: number; usersCount?: number; createdAt: string;
};

type SubscriptionRequest = {
  id: string; boutiqueId: string; boutiqueName: string;
  subscriptionPlanName: string; status: string; requestedAt: string;
};
type PublicationRequest = {
  id: string; boutiqueId: string; boutiqueName: string; status: string; requestedAt: string; reason?: string | null;
};

const PAGE_SIZE = 20;

export function BoutiquesPage({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const [page, setPage] = useState(1);
  const [requestsPage, setRequestsPage] = useState(1);

  const fetchBoutiques = useCallback(() => api.getCollection<BoutiqueSummary>(`/boutiques?page=${page}&itemsPerPage=${PAGE_SIZE}`), [api, page]);
  const fetchRequests = useCallback(() => api.getCollection<SubscriptionRequest>(`/admin/subscription-requests?page=${requestsPage}&itemsPerPage=${PAGE_SIZE}`), [api, requestsPage]);
  const fetchPublicationRequests = useCallback(() => api.getCollection<PublicationRequest>(`/admin/boutique-publication-requests?page=1&itemsPerPage=${PAGE_SIZE}`), [api]);

  const { data: boutiquesRes, isLoading, error, refresh } = useApiData(fetchBoutiques);
  const { data: requestsRes, refresh: refreshRequests } = useApiData(fetchRequests);
  const { data: publicationRequestsRes, refresh: refreshPublicationRequests } = useApiData(fetchPublicationRequests);

  const boutiques = boutiquesRes?.member ?? [];
  const subscriptionRequests = requestsRes?.member ?? [];
  const pendingRequests = subscriptionRequests.filter((r) => r.status === 'pending');
  const publicationRequests = publicationRequestsRes?.member ?? [];
  const pendingPublicationRequests = publicationRequests.filter((r) => r.status === 'pending');
  const totalPages = Math.max(1, Math.ceil((boutiquesRes?.totalItems ?? 0) / PAGE_SIZE));
  const requestsTotalPages = Math.max(1, Math.ceil((requestsRes?.totalItems ?? 0) / PAGE_SIZE));

  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [sortBy, setSortBy] = useState<'name' | 'status' | 'createdAt' | 'products'>('createdAt');
  const [showDeleteConfirm, setShowDeleteConfirm] = useState<string | null>(null);
  const refreshAll = useCallback(() => { refresh(); refreshRequests(); refreshPublicationRequests(); }, [refresh, refreshRequests, refreshPublicationRequests]);

  const filteredBoutiques = boutiques
    .filter((b) => statusFilter === 'all' || b.status === statusFilter)
    .filter((b) =>
      b.name.toLowerCase().includes(search.toLowerCase()) ||
      b.slug.toLowerCase().includes(search.toLowerCase()) ||
      (b.contactEmail ?? '').toLowerCase().includes(search.toLowerCase())
    )
    .sort((a, b) => {
      if (sortBy === 'products') return (b.productsCount ?? 0) - (a.productsCount ?? 0);
      if (sortBy === 'createdAt') return new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime();
      return String(a[sortBy]).localeCompare(String(b[sortBy]));
    });

  const runAction = async (id: string, action: string) => {
    const needsReason = ['reject', 'suspend', 'activate', 'archive', 'unpublish'].includes(action);
    const reason = needsReason ? window.prompt('Motif de cette action :')?.trim() : undefined;
    if (needsReason && !reason) return;
    try {
      await api.patch(`/boutiques/${id}/${action}`, reason ? { reason } : {});
      showNotice(`Boutique ${action === 'approve' ? 'approuvée' : action === 'reject' ? 'rejetée' : action === 'suspend' ? 'désactivée' : action === 'activate' ? 'réactivée' : action === 'publish' ? 'publiée' : 'dépubliée'}`, 'success');
      refreshAll();
    } catch { showNotice('Erreur lors de la mise à jour', 'error'); }
  };

  const deleteBoutique = async (id: string) => {
    try {
      await api.delete(`/boutiques/${id}`);
      showNotice('Boutique supprimée', 'success');
      setShowDeleteConfirm(null);
      refreshAll();
    } catch { showNotice('Erreur lors de la suppression', 'error'); }
  };

  const processRequest = async (id: string, action: 'approve' | 'reject') => {
    try {
      await api.patch(`/admin/subscription-requests/${id}/${action}`, {});
      showNotice(action === 'approve' ? 'Abonnement accepté' : 'Abonnement refusé', 'success');
      refreshAll();
    } catch { showNotice('Erreur lors du traitement', 'error'); }
  };

  const processPublicationRequest = async (id: string, action: 'approve' | 'reject') => {
    const reason = action === 'reject' ? window.prompt('Raison du refus :')?.trim() : undefined;
    if (action === 'reject' && !reason) return;
    try {
      await api.patch(`/admin/boutique-publication-requests/${id}/${action}`, action === 'reject' ? { reason } : {});
      showNotice(action === 'approve' ? 'Boutique publiée' : 'Demande refusée', 'success');
      refreshAll();
    } catch (actionError) {
      showNotice(actionError instanceof Error ? actionError.message : 'Erreur lors du traitement', 'error');
    }
  };

  return (
    <div className="bo-page">
      <PageHeader
        title="Boutiques"
        description="Gestion des boutiques de la plateforme"
      />

      {isLoading ? <LoadingState /> : error ? <ErrorState message={error} onRetry={refreshAll} /> : (
        <div className="bo-page-content space-y-6">
          {/* Subscription requests */}
          <Card>
            <CardHeader>
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%' }}>
                <span>Demandes d'abonnement ({pendingRequests.length})</span>
                <Badge tone={pendingRequests.length > 0 ? 'warning' : 'success'}>
                  {pendingRequests.length > 0 ? 'À traiter' : 'À jour'}
                </Badge>
              </div>
            </CardHeader>
            <CardBody>
              {pendingRequests.length === 0 ? (
                <p style={{ padding: 16, textAlign: 'center', color: 'var(--bo-text-muted)', fontSize: 14 }}>Aucune demande en attente.</p>
              ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                   {pendingRequests.map((r) => (
                    <div key={r.id} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '10px 14px', borderRadius: 10, border: '1px solid var(--bo-border)', background: 'var(--bo-surface)' }}>
                      <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ fontWeight: 600, fontSize: 14 }}>{r.boutiqueName}</div>
                        <div style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>Plan {r.subscriptionPlanName} · {new Date(r.requestedAt).toLocaleDateString('fr-FR')}</div>
                      </div>
                      <Badge tone="warning">{r.status}</Badge>
                      <div style={{ display: 'flex', gap: 4 }}>
                        <Button variant="ghost" size="sm" onClick={() => processRequest(r.id, 'approve')}>Accepter</Button>
                        <Button variant="ghost" size="sm" style={{ color: 'var(--bo-error)' }} onClick={() => processRequest(r.id, 'reject')}>Refuser</Button>
                      </div>
                     </div>
                   ))}
                   <Pagination page={requestsPage} totalPages={requestsTotalPages} onPageChange={setRequestsPage} />
                 </div>
              )}
            </CardBody>
          </Card>

          <Card>
            <CardHeader>
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%' }}>
                <span>Demandes de publication ({pendingPublicationRequests.length})</span>
                <Badge tone={pendingPublicationRequests.length > 0 ? 'warning' : 'success'}>
                  {pendingPublicationRequests.length > 0 ? 'À traiter sous 24h' : 'À jour'}
                </Badge>
              </div>
            </CardHeader>
            <CardBody>
              {pendingPublicationRequests.length === 0 ? (
                <p style={{ padding: 16, textAlign: 'center', color: 'var(--bo-text-muted)', fontSize: 14 }}>Aucune demande de publication en attente.</p>
              ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                  {pendingPublicationRequests.map((request) => (
                    <div key={request.id} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '10px 14px', borderRadius: 10, border: '1px solid var(--bo-border)', background: 'var(--bo-surface)' }}>
                      <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ fontWeight: 600, fontSize: 14 }}>{request.boutiqueName}</div>
                        <div style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>Demandée le {new Date(request.requestedAt).toLocaleDateString('fr-FR')}</div>
                      </div>
                      <Badge tone="warning">En attente</Badge>
                      <div style={{ display: 'flex', gap: 4 }}>
                        <Button variant="ghost" size="sm" onClick={() => void processPublicationRequest(request.id, 'approve')}>Publier</Button>
                        <Button variant="ghost" size="sm" style={{ color: 'var(--bo-error)' }} onClick={() => void processPublicationRequest(request.id, 'reject')}>Refuser</Button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardBody>
          </Card>

          {/* Boutique list */}
          <Card>
            <CardHeader>
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, width: '100%', flexWrap: 'wrap' }}>
                <span>Toutes les boutiques ({filteredBoutiques.length})</span>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                  <input
                    type="text" placeholder="Nom, slug, email…" value={search}
                    onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                    className="bo-input"
                    style={{ maxWidth: 220, padding: '6px 12px', fontSize: 13, borderRadius: 8, border: '1px solid var(--bo-border)' }}
                  />
                   <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
                    className="bo-input"
                    style={{ padding: '6px 12px', fontSize: 13, borderRadius: 8, border: '1px solid var(--bo-border)' }}
                  >
                    <option value="all">Tous statuts</option>
                    <option value="pending">En attente</option>
                    <option value="active">Actives</option>
                    <option value="suspended">Suspendues</option>
                    <option value="rejected">Rejetées</option>
                    <option value="archived">Archivées</option>
                  </select>
                   <select value={sortBy} onChange={(e) => { setSortBy(e.target.value as typeof sortBy); setPage(1); }}
                    className="bo-input"
                    style={{ padding: '6px 12px', fontSize: 13, borderRadius: 8, border: '1px solid var(--bo-border)' }}
                  >
                    <option value="createdAt">Plus récentes</option>
                    <option value="name">Nom</option>
                    <option value="status">Statut</option>
                    <option value="products">Produits</option>
                  </select>
                </div>
              </div>
            </CardHeader>
            <CardBody>
              {filteredBoutiques.length === 0 ? (
                <p style={{ padding: 24, textAlign: 'center', color: 'var(--bo-text-muted)', fontSize: 14 }}>Aucune boutique trouvée.</p>
              ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                   {filteredBoutiques.map((b) => (
                    <div key={b.id} style={{
                      display: 'flex', alignItems: 'center', gap: 12,
                      padding: '10px 14px', borderRadius: 10,
                      border: '1px solid var(--bo-border)', background: 'var(--bo-surface)',
                    }}>
                      <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ fontWeight: 600, fontSize: 14 }}>{b.name}</div>
                        <div style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>{b.slug} · {b.contactEmail ?? '—'}</div>
                      </div>
                      <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                        <Badge tone={b.status === 'active' ? 'success' : b.status === 'suspended' ? 'error' : 'warning'}>{b.status}</Badge>
                        <Badge tone={b.isPublished ? 'success' : 'neutral'}>{b.isPublished ? 'Publiée' : 'Non publiée'}</Badge>
                      </div>
                      <a
                        href={frontOfficeUrl(b)} target="_blank" rel="noreferrer"
                        className="bo-btn bo-btn-secondary bo-btn-sm"
                        style={{ textDecoration: 'none', whiteSpace: 'nowrap' }}
                        title={b.status === 'active' ? 'Ouvrir le front sous-domaine' : 'Prévisualisation privée admin'}
                      >
                        {b.status === 'active' ? 'Accéder front' : 'Preview front'}
                      </a>
                      <Button variant="secondary" size="sm" onClick={() => window.location.assign(`/admin/boutiques/${encodeURIComponent(b.id)}`)}>Détails</Button>
                      <div style={{ fontSize: 12, color: 'var(--bo-text-muted)', whiteSpace: 'nowrap' }}>{b.productsCount ?? 0} produits</div>
                      <div className="bo-table-actions" style={{ display: 'flex', gap: 4 }}>
                        {b.status === 'pending' && <Badge tone="neutral">En attente de publication</Badge>}
                        {b.status === 'active' && (
                          <Button variant="ghost" size="sm" onClick={() => runAction(b.id, 'suspend')}>Désactiver</Button>
                        )}
                        {(b.status === 'suspended' || b.status === 'archived') && (
                          <Button variant="ghost" size="sm" onClick={() => runAction(b.id, 'activate')}>Réactiver</Button>
                        )}
                        {b.status === 'rejected' && (
                          <Button variant="ghost" size="sm" onClick={() => runAction(b.id, 'activate')}>Activer</Button>
                        )}
                        {b.status === 'active' && !b.isPublished && (
                          <Button variant="ghost" size="sm" onClick={() => runAction(b.id, 'publish')}>Publier</Button>
                        )}
                        {b.isPublished && (
                          <Button variant="ghost" size="sm" onClick={() => runAction(b.id, 'unpublish')}>Dépublier</Button>
                        )}
                        <Button variant="ghost" size="sm" style={{ color: 'var(--bo-error)' }} onClick={() => setShowDeleteConfirm(b.id)}>Supprimer</Button>
                      </div>
                     </div>
                   ))}
                   <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
                 </div>
              )}
            </CardBody>
          </Card>
        </div>
      )}

      <ConfirmDialog
        isOpen={showDeleteConfirm !== null}
        title="Supprimer la boutique"
        message="Cette action est irréversible. Toutes les données associées seront perdues."
        danger
        onConfirm={() => showDeleteConfirm && deleteBoutique(showDeleteConfirm)}
        onClose={() => setShowDeleteConfirm(null)}
      />

    </div>
  );
}
