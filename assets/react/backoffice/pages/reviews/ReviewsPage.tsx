import { useState, useCallback } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardHeader, CardBody } from '../../components/Card';
import { Button } from '../../components/Button';
import { Table } from '../../components/Table';
import { Badge, statusBadge } from '../../components/Badge';
import { Pagination } from '../../components/Pagination';
import { Modal } from '../../components/Modal';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { LoadingState, EmptyState, ErrorState } from '../../components/States';
import { PageHeader } from '../../layout/Shell';
import { useNotification } from '../../hooks/useNotification';

type Review = {
  id: string;
  boutiqueId?: string;
  boutiqueName?: string | null;
  productId?: string;
  productName?: string | null;
  categoryId?: string | null;
  categoryName?: string | null;
  targetType?: 'product' | 'category' | 'boutique' | 'general';
  userId?: string;
  authorName: string;
  authorEmail?: string;
  rating: number;
  title?: string;
  comment?: string;
  images: string[];
  isVerifiedPurchase: boolean;
  status: string;
  createdAt: string;
};

const PAGE_SIZE = 20;

function targetLabel(review: Review): string {
  if (review.targetType === 'product' || review.productId) {
    return `Produit : ${review.productName ?? review.productId ?? 'inconnu'}`;
  }
  if (review.targetType === 'category' || review.categoryId) {
    return `Catégorie : ${review.categoryName ?? review.categoryId ?? 'inconnue'}`;
  }
  if (review.targetType === 'boutique' || review.boutiqueId) {
    return `Boutique : ${review.boutiqueName ?? review.boutiqueId ?? 'inconnue'}`;
  }

  return 'Application Hanooti';
}

function StarRating({ rating }: { rating: number }) {
  return (
    <span style={{ color: 'var(--bo-warning)', whiteSpace: 'nowrap' }}>
      {'★'.repeat(rating)}{'☆'.repeat(5 - rating)}
    </span>
  );
}

export function ReviewsPage({ getAccessToken, userRoles = [] }: { getAccessToken: () => string | null; userRoles?: string[] }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const isSuperAdmin = userRoles.includes('ROLE_SUPER_ADMIN');
  const isBoutiqueAdmin = userRoles.includes('ROLE_BOUTIQUE_ADMIN');
  const canDeleteReviews = isSuperAdmin || isBoutiqueAdmin;
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');
  const [reviewScope, setReviewScope] = useState<'all' | 'application'>('all');
  const [viewReview, setViewReview] = useState<Review | null>(null);
  const [reviewToDelete, setReviewToDelete] = useState<Review | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);

  const fetchData = useCallback(async () => {
    const params = new URLSearchParams();
    params.set('page', String(page));
    params.set('itemsPerPage', String(PAGE_SIZE));
    params.set('order[createdAt]', 'desc');
    if (statusFilter) params.set('status', statusFilter);
    const endpoint = reviewScope === 'application' && isSuperAdmin ? '/platform/reviews?' : '/reviews?';
    return api.getCollection<Review>(endpoint + params.toString());
  }, [api, isSuperAdmin, page, reviewScope, statusFilter]);

  const { data, isLoading, error, refresh } = useApiData(fetchData, [page, reviewScope, statusFilter]);
  const items = data?.member ?? [];
  const totalItems = data?.totalItems ?? 0;
  const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));

  async function handleApprove(review: Review) {
    try {
      await api.patch('/reviews/' + review.id + '/approve', {});
      showNotice('Avis approuvé.', 'success');
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Erreur.', 'error');
    }
  }

  async function handleReject(review: Review) {
    try {
      await api.patch('/reviews/' + review.id + '/reject', {});
      showNotice('Avis rejeté.', 'success');
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Erreur.', 'error');
    }
  }

  async function handleDelete() {
    if (!reviewToDelete) return;

    setIsDeleting(true);
    try {
      await api.delete('/reviews/' + reviewToDelete.id);
      showNotice('Avis supprimé.', 'success');
      if (viewReview?.id === reviewToDelete.id) setViewReview(null);
      setReviewToDelete(null);
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Erreur lors de la suppression.', 'error');
    } finally {
      setIsDeleting(false);
    }
  }

  const columns = [
    {
      key: 'authorName', label: 'Auteur',
      render: (r: Review) => (
        <div>
          <strong>{r.authorName}</strong>
          {r.isVerifiedPurchase && <span style={{ color: 'var(--bo-success)', marginLeft: 6, fontSize: 12 }} title="Achat vérifié">✓</span>}
          {r.authorEmail && <div style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>{r.authorEmail}</div>}
        </div>
      ),
    },
    { key: 'rating', label: 'Note', render: (r: Review) => <StarRating rating={r.rating} /> },
    {
      key: 'boutique', label: 'Boutique',
      render: (r: Review) => <span style={{ color: 'var(--bo-text-secondary)' }}>{r.boutiqueName ?? 'Application Hanooti'}</span>,
    },
    {
      key: 'comment', label: 'Avis',
      render: (r: Review) => (
        <div style={{ maxWidth: 300 }}>
          {r.title && <strong>{r.title}</strong>}
          {r.comment && <div style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', color: 'var(--bo-text-muted)' }}>{r.comment}</div>}
        </div>
      ),
    },
    {
      key: 'status', label: 'Statut',
      render: (r: Review) => {
        const s = r.status === 'approved' ? statusBadge('approved') : r.status === 'rejected' ? statusBadge('rejected') : statusBadge('pending');
        return <Badge tone={s.tone}>{s.label}</Badge>;
      },
    },
    {
      key: 'target', label: 'Cible',
      render: (r: Review) => <Badge tone="neutral">{targetLabel(r)}</Badge>,
    },
    {
      key: 'createdAt', label: 'Date',
      render: (r: Review) => new Date(r.createdAt).toLocaleDateString('fr-FR'),
    },
  ];

  if (error) return <ErrorState message={error} onRetry={refresh} />;

  return (
    <div>
      <PageHeader title="Avis" description="Gestion des avis clients" />
      <Card>
        <CardHeader>
          {isSuperAdmin && <select className="bo-input" style={{ maxWidth: 220 }} value={reviewScope} onChange={(event) => { setReviewScope(event.target.value as 'all' | 'application'); setPage(1); }}>
            <option value="all">Tous les avis</option>
            <option value="application">Application Hanooti</option>
          </select>}
          <select
            className="bo-input"
            style={{ maxWidth: 200 }}
            value={statusFilter}
            onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
          >
            <option value="">Tous les statuts</option>
            <option value="pending">En attente</option>
            <option value="approved">Approuvés</option>
            <option value="rejected">Rejetés</option>
          </select>
        </CardHeader>
        <CardBody>
          {isLoading ? <LoadingState /> : items.length === 0 ? (
            <EmptyState title="Aucun avis" message="Aucun avis client pour le moment." />
          ) : (
            <>
              <Table
                columns={columns}
                data={items}
                onRowClick={setViewReview}
                renderActions={(review) => (
                  <div style={{ display: 'flex', gap: 4 }}>
                    <Button size="sm" variant="secondary" onClick={(event) => { event.stopPropagation(); setViewReview(review); }}>Voir</Button>
                    {review.status === 'pending' && <>
                      <Button size="sm" variant="secondary" onClick={(event) => { event.stopPropagation(); handleApprove(review); }}>✓</Button>
                      <Button size="sm" variant="secondary" style={{ color: 'var(--bo-error)' }} onClick={(event) => { event.stopPropagation(); handleReject(review); }}>✗</Button>
                    </>}
                    {canDeleteReviews && <Button size="sm" variant="danger" onClick={(event) => { event.stopPropagation(); setReviewToDelete(review); }}>Supprimer</Button>}
                  </div>
                )}
              />
              <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
            </>
          )}
        </CardBody>
      </Card>

      <Modal
        isOpen={!!viewReview}
        onClose={() => setViewReview(null)}
        title="Détail de l'avis"
        footer={
          viewReview && isSuperAdmin ? (
            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
              <Button variant="secondary" onClick={() => setViewReview(null)}>Fermer</Button>
              {viewReview.status === 'pending' && (
                <>
                  <Button onClick={() => { handleApprove(viewReview); setViewReview(null); }}>Approuver</Button>
                  <Button variant="secondary" style={{ color: 'var(--bo-error)' }} onClick={() => { handleReject(viewReview); setViewReview(null); }}>Rejeter</Button>
                </>
              )}
              <Button variant="danger" onClick={() => { setReviewToDelete(viewReview); setViewReview(null); }}>Supprimer</Button>
            </div>
          ) : viewReview ? (
            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
              <Button variant="secondary" onClick={() => setViewReview(null)}>Fermer</Button>
              {viewReview.status === 'pending' && <>
                <Button onClick={() => { handleApprove(viewReview); setViewReview(null); }}>Approuver</Button>
                <Button variant="secondary" style={{ color: 'var(--bo-error)' }} onClick={() => { handleReject(viewReview); setViewReview(null); }}>Rejeter</Button>
              </>}
              {canDeleteReviews && <Button variant="danger" onClick={() => { setReviewToDelete(viewReview); setViewReview(null); }}>Supprimer</Button>}
            </div>
          ) : (
            <Button variant="secondary" onClick={() => setViewReview(null)}>Fermer</Button>
          )
        }
      >
        {viewReview && (
          <div className="bo-form">
            <div style={{ marginBottom: 16 }}>
              <StarRating rating={viewReview.rating} />
              <Badge tone={statusBadge(viewReview.status).tone}>{statusBadge(viewReview.status).label}</Badge>
              {viewReview.isVerifiedPurchase && <Badge tone="success">Achat vérifié</Badge>}
            </div>
            {viewReview.title && <h3 style={{ margin: '0 0 8px' }}>{viewReview.title}</h3>}
            {viewReview.comment && <p style={{ whiteSpace: 'pre-wrap', color: 'var(--bo-text-muted)' }}>{viewReview.comment}</p>}
            <hr style={{ border: 'none', borderTop: '1px solid var(--bo-border)', margin: '16px 0' }} />
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, fontSize: 14 }}>
              <div><strong>Auteur</strong><br /><span style={{ color: 'var(--bo-text-muted)' }}>{viewReview.authorName}</span></div>
              {viewReview.authorEmail && <div><strong>Email</strong><br /><span style={{ color: 'var(--bo-text-muted)' }}>{viewReview.authorEmail}</span></div>}
              <div><strong>Date</strong><br /><span style={{ color: 'var(--bo-text-muted)' }}>{new Date(viewReview.createdAt).toLocaleDateString('fr-FR')}</span></div>
              <div><strong>Cible</strong><br /><span style={{ color: 'var(--bo-text-muted)' }}>{targetLabel(viewReview)}</span></div>
              {viewReview.boutiqueName && <div><strong>Boutique</strong><br /><span style={{ color: 'var(--bo-text-muted)' }}>{viewReview.boutiqueName}</span></div>}
              {viewReview.productName && <div><strong>Produit</strong><br /><span style={{ color: 'var(--bo-text-muted)' }}>{viewReview.productName}</span></div>}
              {viewReview.categoryName && <div><strong>Catégorie</strong><br /><span style={{ color: 'var(--bo-text-muted)' }}>{viewReview.categoryName}</span></div>}
            </div>
          </div>
        )}
      </Modal>

      <ConfirmDialog
        isOpen={!!reviewToDelete}
        onClose={() => { if (!isDeleting) setReviewToDelete(null); }}
        onConfirm={handleDelete}
        title="Supprimer l'avis"
        message={reviewToDelete ? `Voulez-vous supprimer définitivement l'avis de ${reviewToDelete.authorName} ? Cette action est irréversible.` : ''}
        confirmLabel="Supprimer définitivement"
        danger
        isLoading={isDeleting}
      />
    </div>
  );
}
