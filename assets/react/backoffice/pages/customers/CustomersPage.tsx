import { useState, useCallback } from 'react';
import type { Customer, SubscriptionSummary } from '../../types';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardHeader, CardBody } from '../../components/Card';
import { Badge } from '../../components/Badge';
import { Button } from '../../components/Button';
import { Table } from '../../components/Table';
import { Pagination } from '../../components/Pagination';
import { Modal } from '../../components/Modal';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { FormField } from '../../components/FormField';
import { FiltersBar } from '../../components/FiltersBar';
import { LoadingState, EmptyState, ErrorState } from '../../components/States';
import { PageHeader } from '../../layout/Shell';
import { useBoutique } from '../../hooks/useBoutique';
import { useNotification } from '../../hooks/useNotification';

const PAGE_SIZE = 20;

export function CustomersPage({ getAccessToken, userRoles = [] }: { getAccessToken: () => string | null; userRoles?: string[] }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const { boutique } = useBoutique();
  const canManageCustomers = userRoles.includes('ROLE_SUPER_ADMIN') || userRoles.includes('ROLE_BOUTIQUE_ADMIN');
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [detailCustomer, setDetailCustomer] = useState<Customer | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Customer | null>(null);
  const [processingId, setProcessingId] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    const params = new URLSearchParams();
    params.set('page', String(page)); params.set('itemsPerPage', String(PAGE_SIZE));
    if (search) params.set('email', search);
    return api.getCollection<Customer>('/customers?' + params.toString());
  }, [api, page, search]);

  const { data, isLoading, error, refresh } = useApiData(fetchData, [page, search]);
  const fetchSummary = useCallback(async () => api.get<SubscriptionSummary>('/subscription/summary'), [api]);
  const { data: summary } = useApiData(fetchSummary, [boutique?.id]);
  const customerQuota = summary?.quotas?.find((q) => q.code === 'max_customers');
  const customers = data?.member ?? [];
  const totalItems = data?.totalItems ?? 0;
  const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));

  async function updateCustomer(customer: Customer, active: boolean) {
    setProcessingId(customer.id);
    try {
      await api.patch(`/customers/${customer.id}`, { active });
      showNotice(active ? 'Client activé.' : 'Client désactivé.', 'success');
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Erreur lors de la mise à jour du client.', 'error');
    } finally {
      setProcessingId(null);
    }
  }

  async function deleteCustomer() {
    if (!deleteTarget) return;
    setProcessingId(deleteTarget.id);
    try {
      await api.delete(`/customers/${deleteTarget.id}`);
      showNotice('Client supprimé.', 'success');
      setDeleteTarget(null);
      setDetailCustomer(null);
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Erreur lors de la suppression du client.', 'error');
    } finally {
      setProcessingId(null);
    }
  }

  const columns = [
    {
      key: 'email', label: 'Client',
      render: (c: Customer) => (
        <div>
          <strong style={{ fontSize: 14 }}>{c.firstName || c.lastName ? `${c.firstName ?? ''} ${c.lastName ?? ''}` : c.email}</strong>
          <div style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>{c.email}</div>
        </div>
      ),
    },
    { key: 'phone', label: 'Téléphone', render: (c: Customer) => <span style={{ color: 'var(--bo-text-secondary)' }}>{c.phone ?? '—'}</span> },
    { key: 'ordersCount', label: 'Commandes', render: (c: Customer) => <Badge tone="neutral">{c.ordersCount ?? 0}</Badge> },
    {
      key: 'totalSpentCents', label: 'Total dépensé',
      render: (c: Customer) => <strong>{((c.totalSpentCents ?? 0) / 100).toFixed(2)} TND</strong>,
    },
    { key: 'active', label: 'Statut', render: (c: Customer) => <Badge tone={c.active === false ? 'error' : 'success'}>{c.active === false ? 'Inactif' : 'Actif'}</Badge> },
    { key: 'createdAt', label: 'Inscrit le', render: (c: Customer) => <span style={{ fontSize: 13, color: 'var(--bo-text-secondary)' }}>{c.createdAt ? new Date(c.createdAt).toLocaleDateString('fr-FR') : '—'}</span> },
  ];

  if (error) return <ErrorState message={error} onRetry={refresh} />;

  return (
    <div>
      <PageHeader title="Clients" description="Gérez votre base clients" />
      <Card>
        {customerQuota && (
          <div style={{
            padding: '10px 20px', background: 'var(--bo-surface)', borderBottom: '1px solid var(--bo-border)',
            fontSize: 13, color: 'var(--bo-text-secondary)', display: 'flex', gap: 16, alignItems: 'center',
          }}>
            <span>Clients : <strong>{customerQuota.usage}</strong> / {customerQuota.limit ?? '∞'}</span>
            {customerQuota.remaining !== null && (
              <span style={{ color: customerQuota.remaining <= 0 ? 'var(--bo-warning)' : 'var(--bo-success)' }}>
                ({customerQuota.remaining <= 0 ? 'quota atteint' : customerQuota.remaining + ' restant' + (customerQuota.remaining > 1 ? 's' : '')})
              </span>
            )}
            {customerQuota.limit === null && <span style={{ color: 'var(--bo-success)' }}>Illimité</span>}
          </div>
        )}
        <CardHeader>
          <input className="bo-input" style={{ maxWidth: 320 }} placeholder="Rechercher par email..." value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
          <span style={{ fontSize: 13, color: 'var(--bo-text-muted)' }}>{totalItems} client{totalItems > 1 ? 's' : ''}</span>
        </CardHeader>
        <CardBody>
          {isLoading ? <LoadingState /> : customers.length === 0 ? (
            <EmptyState title="Aucun client" message="Les clients apparaîtront ici." />
          ) : (
            <><Table columns={columns} data={customers} onRowClick={setDetailCustomer} renderActions={canManageCustomers ? (customer) => <div style={{ display: 'flex', gap: 4 }}>
              <Button size="sm" variant={customer.active === false ? 'secondary' : 'ghost'} disabled={processingId === customer.id} onClick={(event) => { event.stopPropagation(); updateCustomer(customer, customer.active === false); }}>{customer.active === false ? 'Activer' : 'Désactiver'}</Button>
              <Button size="sm" variant="danger" disabled={processingId === customer.id} onClick={(event) => { event.stopPropagation(); setDeleteTarget(customer); }}>Supprimer</Button>
            </div> : undefined} /><Pagination page={page} totalPages={totalPages} onPageChange={setPage} /></>
          )}
        </CardBody>
      </Card>

      <Modal isOpen={!!detailCustomer} onClose={() => setDetailCustomer(null)} title="Détails client" width="500px">
        {detailCustomer && (
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
            <FormField label="Email"><div>{detailCustomer.email}</div></FormField>
            <FormField label="Téléphone"><div>{detailCustomer.phone ?? '—'}</div></FormField>
            <FormField label="Prénom"><div>{detailCustomer.firstName ?? '—'}</div></FormField>
            <FormField label="Nom"><div>{detailCustomer.lastName ?? '—'}</div></FormField>
            <FormField label="Commandes"><div>{detailCustomer.ordersCount ?? 0}</div></FormField>
            <FormField label="Total dépensé"><div>{((detailCustomer.totalSpentCents ?? 0) / 100).toFixed(2)} TND</div></FormField>
          </div>
        )}
      </Modal>
      <ConfirmDialog isOpen={!!deleteTarget} onClose={() => { if (!processingId) setDeleteTarget(null); }} onConfirm={deleteCustomer} title="Supprimer le client" message={deleteTarget ? `Supprimer définitivement ${deleteTarget.email} ?` : ''} confirmLabel="Supprimer" danger isLoading={!!processingId} />
    </div>
  );
}
