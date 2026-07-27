import { useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardHeader, CardBody } from '../../components/Card';
import { Button } from '../../components/Button';
import { Table } from '../../components/Table';
import { Badge } from '../../components/Badge';
import { Pagination } from '../../components/Pagination';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { LoadingState, EmptyState, ErrorState } from '../../components/States';
import { PageHeader } from '../../layout/Shell';
import { useNotification } from '../../hooks/useNotification';
import type { Promotion } from './promotionTypes';

const PAGE_SIZE = 20;

export function PromotionsPage({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const navigate = useNavigate();
  const { showNotice } = useNotification();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [deleteTarget, setDeleteTarget] = useState<Promotion | null>(null);

  const fetchData = useCallback(async () => {
    const params = new URLSearchParams();
    params.set('page', String(page));
    params.set('itemsPerPage', String(PAGE_SIZE));
    if (search) params.set('name', search);
    if (status) params.set('status', status);
    return api.getCollection<Promotion>('/promotions?' + params.toString());
  }, [api, page, search, status]);

  const { data, isLoading, error, refresh } = useApiData(fetchData, [page, search, status]);
  const items = data?.member ?? [];
  const totalItems = data?.totalItems ?? 0;
  const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));

  async function handleDelete(): Promise<void> {
    if (!deleteTarget) return;
    try {
      await api.delete(`/promotions/${deleteTarget.id}`);
      showNotice('Promotion supprimée.', 'success');
      setDeleteTarget(null);
      refresh();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la suppression.', 'error');
    }
  }

  const columns = [
    { key: 'name', label: 'Nom', render: (promotion: Promotion) => <strong>{promotion.name}</strong> },
    { key: 'scope', label: 'Portée', render: (promotion: Promotion) => <Badge tone="neutral">{promotion.scope === 'category' ? 'Catégorie' : promotion.scope === 'product' ? 'Produit' : 'Toute la boutique'}</Badge> },
    { key: 'type', label: 'Type', render: (promotion: Promotion) => <Badge tone="neutral">{promotion.type === 'percentage' ? '%' : 'Montant fixe'}</Badge> },
    { key: 'value', label: 'Valeur', render: (promotion: Promotion) => <span>{promotion.type === 'percentage' ? `${promotion.value}%` : `${(promotion.value / 100).toFixed(2)} TND`}</span> },
    { key: 'status', label: 'Statut', render: (promotion: Promotion) => <Badge tone={promotion.active ? 'success' : 'neutral'}>{promotion.active ? 'Actif' : 'Inactif'}</Badge> },
  ];

  if (error) return <ErrorState message={error} onRetry={refresh} />;

  return (
    <div>
      <PageHeader title="Promotions" description="Codes promo et réductions" actions={<Button onClick={() => navigate('/admin/promotions/new')}>+ Nouvelle promotion</Button>} />
      <Card>
        <CardHeader><input className="bo-input" style={{ maxWidth: 320 }} placeholder="Rechercher..." value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} /></CardHeader>
        <CardBody>
          {isLoading ? <LoadingState /> : items.length === 0 ? (
            <EmptyState title="Aucune promotion" message="Créez votre première promotion." action={{ label: '+ Nouvelle promotion', onClick: () => navigate('/admin/promotions/new') }} />
          ) : (
            <><Table columns={columns} data={items} onRowClick={(promotion) => navigate(`/admin/promotions/${promotion.id}/edit`)} renderActions={(promotion) => <Button size="sm" variant="danger" onClick={(event) => { event.stopPropagation(); setDeleteTarget(promotion); }}>Supprimer</Button>} /><Pagination page={page} totalPages={totalPages} onPageChange={setPage} /></>
          )}
        </CardBody>
      </Card>
      <ConfirmDialog isOpen={!!deleteTarget} onClose={() => setDeleteTarget(null)} onConfirm={handleDelete} title="Supprimer" message={`Supprimer "${deleteTarget?.name}" ?`} confirmLabel="Supprimer" danger />
    </div>
  );
}
