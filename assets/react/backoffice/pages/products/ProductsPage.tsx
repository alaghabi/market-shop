import { useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import type { Product, SubscriptionSummary } from '../../types';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardHeader, CardBody } from '../../components/Card';
import { Button } from '../../components/Button';
import { Table } from '../../components/Table';
import { Badge } from '../../components/Badge';
import { Pagination } from '../../components/Pagination';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { FiltersBar } from '../../components/FiltersBar';
import { LoadingState, EmptyState, ErrorState } from '../../components/States';
import { PageHeader } from '../../layout/Shell';
import { useNotification } from '../../hooks/useNotification';

const PAGE_SIZE = 20;

function productImageUrl(product: Product): string | null {
  const image = product.images?.find((candidate) => typeof candidate !== 'string' && candidate.isDefault) ?? product.images?.[0];
  if (!image) return null;
  if (typeof image === 'string') return image;
  return image.smallUrl ?? image.url ?? image.largeUrl ?? null;
}

export function ProductsPage({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const navigate = useNavigate();
  const { showNotice } = useNotification();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [sortField, setSortField] = useState('createdAt');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc');
  const [deleteTarget, setDeleteTarget] = useState<Product | null>(null);
  const [deleting, setDeleting] = useState(false);

  const fetchProducts = useCallback(async () => {
    const params = new URLSearchParams();
    params.set('page', String(page));
    params.set('itemsPerPage', String(PAGE_SIZE));
    params.set(`order[${sortField}]`, sortDir);
    if (search) params.set('name', search);
    if (status) params.set('isActive', status === 'active' ? 'true' : 'false');
    return api.getCollection<Product>('/products?' + params.toString());
  }, [api, page, search, sortField, sortDir, status]);
  const { data, isLoading, error, refresh } = useApiData(fetchProducts, [page, search, sortField, sortDir, status]);

  const fetchSummary = useCallback(() => api.get<SubscriptionSummary>('/subscription/summary'), [api]);
  const { data: summary, refresh: refreshSummary } = useApiData(fetchSummary);
  const productQuota = summary?.quotas?.find((quota) => quota.code === 'max_products');
  const products = data?.member ?? [];
  const totalItems = data?.totalItems ?? 0;
  const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));

  async function handleDelete() {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      await api.delete(`/products/${deleteTarget.id}`);
      showNotice('Produit supprimé.', 'success');
      setDeleteTarget(null);
      refresh();
      refreshSummary();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Erreur lors de la suppression.', 'error');
    } finally {
      setDeleting(false);
    }
  }

  const columns = [
    {
      key: 'name', label: 'Produit', sortable: true,
      render: (product: Product) => {
        const image = productImageUrl(product);

        return <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          {image ? <img src={image} alt="" style={{ width: 42, height: 42, borderRadius: 8, objectFit: 'cover', border: '1px solid var(--bo-border)', flexShrink: 0 }} /> : <div aria-hidden="true" style={{ width: 42, height: 42, borderRadius: 8, background: 'var(--bo-surface)', border: '1px solid var(--bo-border)', flexShrink: 0 }} />}
          <div><strong style={{ fontSize: 14 }}>{product.name}</strong><div style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>{product.sku ?? '-'}</div></div>
        </div>;
      },
    },
    { key: 'categoryName', label: 'Catégorie', render: (product: Product) => <span style={{ color: 'var(--bo-text-secondary)' }}>{product.categoryName ?? '-'}</span> },
    { key: 'priceCents', label: 'Prix', sortable: true, render: (product: Product) => <strong>{((product.sellingPrice ?? product.priceCents ?? 0) / 100).toFixed(2)} TND</strong> },
    { key: 'stockQuantity', label: 'Stock', sortable: true, render: (product: Product) => <span style={{ color: product.stockQuantity <= product.lowStockThreshold ? 'var(--bo-warning)' : 'inherit', fontWeight: product.stockQuantity <= product.lowStockThreshold ? 600 : 400 }}>{product.stockQuantity}</span> },
    { key: 'isActive', label: 'Statut', render: (product: Product) => <Badge tone={product.status === 'ACTIVE' ? 'success' : 'neutral'}>{product.status === 'ACTIVE' ? 'Actif' : product.status === 'DRAFT' ? 'Brouillon' : 'Inactif'}</Badge> },
  ];

  if (error) return <ErrorState message={error} onRetry={refresh} />;

  return (
    <div>
      <PageHeader title="Produits" description="Gérez votre catalogue de produits" actions={<Button onClick={() => navigate('/admin/products/new')}>+ Nouveau produit</Button>} />
      <Card>
        {productQuota && <div style={{ padding: '10px 20px', background: 'var(--bo-surface)', borderBottom: '1px solid var(--bo-border)', fontSize: 13, color: 'var(--bo-text-secondary)' }}>Produits actifs : <strong>{productQuota.usage}</strong> / {productQuota.limit ?? '∞'}</div>}
        <CardHeader>
          <FiltersBar search={search} onSearchChange={(value) => { setSearch(value); setPage(1); }} status={status} onStatusChange={(value) => { setStatus(value); setPage(1); }} statusOptions={[{ value: 'active', label: 'Actifs' }, { value: 'inactive', label: 'Inactifs' }]} />
          <span style={{ fontSize: 13, color: 'var(--bo-text-muted)' }}>{totalItems} produit{totalItems > 1 ? 's' : ''}</span>
        </CardHeader>
         <CardBody>
           {isLoading ? <LoadingState /> : products.length === 0 ? <EmptyState title="Aucun produit" message="Commencez par ajouter votre premier produit." action={{ label: '+ Nouveau produit', onClick: () => navigate('/admin/products/new') }} /> : <Table columns={columns} data={products} sort={{ field: sortField, direction: sortDir }} onSort={(field) => { if (sortField === field) setSortDir((direction) => direction === 'asc' ? 'desc' : 'asc'); else { setSortField(field); setSortDir('asc'); } }} onRowClick={(product) => navigate(`/admin/products/${product.id}`)} renderActions={(product) => <><Button size="sm" variant="secondary" onClick={(event) => { event.stopPropagation(); navigate(`/admin/products/${product.id}/edit`); }}>Modifier</Button><Button size="sm" variant="danger" onClick={(event) => { event.stopPropagation(); setDeleteTarget(product); }}>Supprimer</Button></>} />}
        </CardBody>
        <div style={{ padding: '0 20px 16px' }}><Pagination page={page} totalPages={totalPages} onPageChange={setPage} /></div>
      </Card>
      <ConfirmDialog isOpen={!!deleteTarget} onClose={() => setDeleteTarget(null)} onConfirm={handleDelete} title="Supprimer le produit" message={`Êtes-vous sûr de vouloir supprimer "${deleteTarget?.name}" ? Cette action est irréversible.`} confirmLabel="Supprimer" danger isLoading={deleting} />
    </div>
  );
}
