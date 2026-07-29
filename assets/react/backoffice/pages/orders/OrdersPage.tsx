import { useState, useCallback } from 'react';
import type { Order } from '../../types';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardHeader, CardBody } from '../../components/Card';
import { Button } from '../../components/Button';
import { Table } from '../../components/Table';
import { Badge } from '../../components/Badge';
import { Pagination } from '../../components/Pagination';
import { Modal } from '../../components/Modal';
import { FormField } from '../../components/FormField';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { FiltersBar } from '../../components/FiltersBar';
import { LoadingState, EmptyState, ErrorState } from '../../components/States';
import { PageHeader } from '../../layout/Shell';
import { useNotification } from '../../hooks/useNotification';

const PAGE_SIZE = 20;

const statusStyle: Record<string, string> = {
  draft: 'neutral', pending: 'warning', paid: 'info', completed: 'info', shipped: 'success', delivered: 'success', cancelled: 'neutral', refunded: 'neutral',
};

const statusLabels: Record<string, string> = {
  draft: 'Brouillon', pending: 'En attente', paid: 'Confirmée', completed: 'Confirmée', shipped: 'Expédiée', delivered: 'Livrée', cancelled: 'Annulée', refunded: 'Remboursée',
};

export function OrdersPage({ getAccessToken, userRoles = [] }: { getAccessToken: () => string | null; userRoles?: string[] }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [sortField, setSortField] = useState('createdAt');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc');
  const [detailOrder, setDetailOrder] = useState<Order | null>(null);
  const [orderToReject, setOrderToReject] = useState<Order | null>(null);
  const [orderToDelete, setOrderToDelete] = useState<Order | null>(null);
  const [actionOrderId, setActionOrderId] = useState<string | null>(null);
  const [deliveryPollOrderId, setDeliveryPollOrderId] = useState<string | null>(null);
  const isAdmin = userRoles.includes('ROLE_BOUTIQUE_ADMIN') || userRoles.includes('ROLE_SUPER_ADMIN');

  const fetchData = useCallback(async () => {
    const params = new URLSearchParams();
    params.set('page', String(page));
    params.set('itemsPerPage', String(PAGE_SIZE));
    params.set('order[' + sortField + ']', sortDir);
    if (search) params.set('customerName', search);
    if (status) params.set('status', status);
    return api.getCollection<Order>('/orders?' + params.toString());
  }, [api, page, search, status, sortField, sortDir]);

  const { data, isLoading, error, refresh } = useApiData(fetchData, [page, search, status, sortField, sortDir]);

  const orders = data?.member ?? [];
  const totalItems = data?.totalItems ?? 0;
  const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));

  function handleSort(field: string) {
    if (sortField === field) setSortDir((d) => d === 'asc' ? 'desc' : 'asc');
    else { setSortField(field); setSortDir('asc'); }
  }

  async function updateOrderStatus(order: Order, nextStatus: 'paid' | 'cancelled') {
    setActionOrderId(order.id);
    try {
      await api.patch(`/orders/${order.id}`, { status: nextStatus });
      showNotice(nextStatus === 'paid' ? 'Commande confirmée.' : 'Commande refusée.', 'success');
      setOrderToReject(null);
      setDetailOrder(null);
      await refresh();
      if ('paid' === nextStatus && (order.paymentMethodCode === 'CASH_ON_DELIVERY' || order.paymentStatus === 'paid')) {
        void waitForDelivery(order.id);
      }
    } catch (exception) {
      showNotice(exception instanceof Error ? exception.message : 'Impossible de mettre à jour la commande.', 'error');
    } finally {
      setActionOrderId(null);
    }
  }

  async function waitForDelivery(orderId: string) {
    setDeliveryPollOrderId(orderId);
    try {
      for (let attempt = 0; attempt < 30; attempt += 1) {
        await new Promise((resolve) => window.setTimeout(resolve, 1000));
        const updated = await api.get<Order>(`/orders/${orderId}`);
        if (updated.shipmentId || updated.deliveryError || ['shipped', 'delivered', 'cancelled'].includes(updated.status)) {
          setDetailOrder((current) => current?.id === orderId ? updated : current);
          await refresh();
          if (updated.deliveryLabelUrl) {
            showNotice('Le bon du transporteur est disponible.', 'success');
          } else if (updated.deliveryError) {
            showNotice(updated.deliveryError, 'error');
          }
          return;
        }
      }
      showNotice('La livraison est toujours en cours de traitement.', 'info');
    } catch (exception) {
      showNotice(exception instanceof Error ? exception.message : 'Impossible de suivre la livraison.', 'error');
    } finally {
      setDeliveryPollOrderId(null);
    }
  }

  async function deleteOrder(order: Order) {
    setActionOrderId(order.id);
    try {
      await api.delete(`/orders/${order.id}`);
      showNotice('Commande supprimée.', 'success');
      setOrderToDelete(null);
      setDetailOrder(null);
      refresh();
    } catch (exception) {
      showNotice(exception instanceof Error ? exception.message : 'Impossible de supprimer la commande.', 'error');
    } finally {
      setActionOrderId(null);
    }
  }

  const columns = [
    {
      key: 'items', label: 'Produit(s)',
      render: (o: Order) => {
        const item = o.items?.[0];
        return item ? <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          {item.productImage ? <img src={item.productImage} alt="" style={{ width: 36, height: 36, borderRadius: 6, objectFit: 'cover', border: '1px solid var(--bo-border)' }} /> : <div aria-hidden="true" style={{ width: 36, height: 36, borderRadius: 6, background: 'var(--bo-surface)', border: '1px solid var(--bo-border)' }} />}
          <span>{item.productName ?? item.productId ?? 'Produit'}{(o.items?.length ?? 0) > 1 ? ` +${(o.items?.length ?? 0) - 1}` : ''}</span>
        </div> : <span style={{ color: 'var(--bo-text-muted)' }}>—</span>;
      },
    },
    {
      key: 'customerName', label: 'Client', sortable: true,
      render: (o: Order) => (
        <div><div>{o.customerName ?? '—'}</div><div style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>{o.customerEmail ?? ''}</div></div>
      ),
    },
    {
      key: 'customerPhone', label: 'Téléphone',
      render: (o: Order) => <span>{o.customerPhone ?? '—'}</span>,
    },
    {
      key: 'status', label: 'Statut',
      render: (o: Order) => <Badge tone={statusStyle[o.status] as any ?? 'neutral'}>{statusLabels[o.status] ?? o.status}</Badge>,
    },
    {
      key: 'totalCents', label: 'Total', sortable: true,
      render: (o: Order) => <strong>{(o.totalCents / 100).toFixed(2)} {o.currency}</strong>,
    },
    {
      key: 'createdAt', label: 'Date', sortable: true,
      render: (o: Order) => <span style={{ color: 'var(--bo-text-secondary)', fontSize: 13 }}>{new Date(o.createdAt).toLocaleDateString('fr-FR')}</span>,
    },
  ];

  if (error) return <ErrorState message={error} onRetry={refresh} />;

  return (
    <div>
      <PageHeader title="Commandes" description="Suivez et gérez les commandes" />
      <Card>
        <CardHeader>
          <FiltersBar search={search} onSearchChange={(v) => { setSearch(v); setPage(1); }} status={status} onStatusChange={(v) => { setStatus(v); setPage(1); }} statusOptions={[
            { value: 'pending', label: 'En attente' }, { value: 'paid', label: 'Confirmée' },
            { value: 'completed', label: 'Terminée' }, { value: 'shipped', label: 'Expédiée' },
            { value: 'delivered', label: 'Livrée' }, { value: 'cancelled', label: 'Annulée' },
          ]} />
          <span style={{ fontSize: 13, color: 'var(--bo-text-muted)' }}>{totalItems} commande{totalItems > 1 ? 's' : ''}</span>
        </CardHeader>
        <CardBody>
          {isLoading ? <LoadingState /> : orders.length === 0 ? (
            <EmptyState title="Aucune commande" message="Les commandes apparaîtront ici." />
          ) : (
            <>
              <Table
                columns={columns}
                data={orders}
                sort={{ field: sortField, direction: sortDir }}
                onSort={handleSort}
                onRowClick={setDetailOrder}
                renderActions={(order) => (
                  <>
                    {isAdmin && order.status === 'pending' && (
                      <>
                        <Button size="sm" onClick={(event) => { event.stopPropagation(); void updateOrderStatus(order, 'paid'); }} disabled={actionOrderId === order.id}>
                          {deliveryPollOrderId === order.id ? 'Livraison...' : 'Confirmer'}
                        </Button>
                        <Button size="sm" variant="danger" onClick={(event) => { event.stopPropagation(); setOrderToReject(order); }} disabled={actionOrderId === order.id}>
                          Refuser
                        </Button>
                      </>
                    )}
                    {order.deliveryLabelUrl && (
                      <a
                        href={order.deliveryLabelUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="bo-btn bo-btn-ghost bo-btn-sm"
                        onClick={(event) => event.stopPropagation()}
                      >
                        Bon transporteur
                      </a>
                    )}
                    {isAdmin && order.status === 'cancelled' && (
                      <Button size="sm" variant="danger" onClick={(event) => { event.stopPropagation(); setOrderToDelete(order); }} disabled={actionOrderId === order.id}>
                        Supprimer
                      </Button>
                    )}
                  </>
                )}
              />
              <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
            </>
          )}
        </CardBody>
      </Card>

       <Modal isOpen={!!detailOrder} onClose={() => setDetailOrder(null)} title={`Commande #${detailOrder?.id ?? ''}`} width="560px">
        {detailOrder && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            <div style={{ borderBottom: '1px solid var(--bo-border)', paddingBottom: 16 }}>
              <strong>Informations de la commande</strong>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginTop: 12 }}>
                <FormField label="Numéro"><div>#{detailOrder.id}</div></FormField>
                <FormField label="Boutique"><div>{detailOrder.boutiqueId ?? '—'}</div></FormField>
                <FormField label="Canal"><div>{detailOrder.channel}</div></FormField>
                <FormField label="Date de création"><span>{new Date(detailOrder.createdAt).toLocaleString('fr-FR')}</span></FormField>
                <FormField label="Sous-total"><strong>{(detailOrder.subtotalCents / 100).toFixed(2)} {detailOrder.currency}</strong></FormField>
                <FormField label="Remise"><strong>{(detailOrder.discountCents / 100).toFixed(2)} {detailOrder.currency}</strong></FormField>
                <FormField label="Total"><strong>{(detailOrder.totalCents / 100).toFixed(2)} {detailOrder.currency}</strong></FormField>
                <FormField label="Devise"><div>{detailOrder.currency}</div></FormField>
              </div>
            </div>
            <div>
              <strong>Informations du client</strong>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginTop: 12 }}>
                <FormField label="Nom complet"><div>{detailOrder.customerName ?? '—'}</div></FormField>
                <FormField label="Identifiant client"><div>{detailOrder.customerId ?? '—'}</div></FormField>
                <FormField label="Email"><div>{detailOrder.customerEmail ?? '—'}</div></FormField>
                <FormField label="Téléphone"><div>{detailOrder.customerPhone ?? '—'}</div></FormField>
              </div>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
              <FormField label="Statut"><Badge tone={statusStyle[detailOrder.status] as any ?? 'neutral'}>{statusLabels[detailOrder.status] ?? detailOrder.status}</Badge></FormField>
              <FormField label="Paiement"><div>{detailOrder.paymentMethodCode ?? '—'} ({detailOrder.paymentStatus ?? 'pending'})</div></FormField>
            </div>
            <div style={{ borderTop: '1px solid var(--bo-border)', paddingTop: 16 }}>
              <FormField label="Adresse de livraison">
                <div>{[detailOrder.shippingAddress, detailOrder.shippingLocality, detailOrder.shippingCity, detailOrder.shippingGovernorate, detailOrder.shippingPostalCode, detailOrder.shippingCountry].filter(Boolean).join(', ') || '—'}</div>
              </FormField>
              <FormField label="Suivi livraison">
                <div>
                  {detailOrder.deliveryCompanyName ? `${detailOrder.deliveryCompanyName} — ` : ''}
                  {detailOrder.deliveryTracking ?? detailOrder.deliveryStatus ?? '—'}
                </div>
                {detailOrder.deliveryError && <div style={{ color: 'var(--bo-error)', marginTop: 6 }}>{detailOrder.deliveryError}</div>}
                {detailOrder.deliveryLabelUrl && (
                  <a href={detailOrder.deliveryLabelUrl} target="_blank" rel="noreferrer" className="bo-btn bo-btn-ghost bo-btn-sm" style={{ display: 'inline-flex', marginTop: 8 }}>
                    Télécharger / imprimer le bon
                  </a>
                )}
              </FormField>
            </div>
            <div style={{ borderTop: '1px solid var(--bo-border)', paddingTop: 16 }}>
              <strong>Détails des produits</strong>
              {detailOrder.items?.length ? (
                <div className="bo-table-wrapper" style={{ marginTop: 10 }}>
                  <table className="bo-table">
                    <thead><tr><th>Image</th><th>Produit</th><th>Référence</th><th>Qté</th><th>Prix unitaire</th><th>Total</th></tr></thead>
                    <tbody>{detailOrder.items.map((item, index) => (
                      <tr key={`${item.productId ?? item.productName ?? 'item'}-${index}`}>
                        <td>{item.productImage ? <img src={item.productImage} alt={item.productName ?? 'Produit'} style={{ width: 48, height: 48, borderRadius: 8, objectFit: 'cover', border: '1px solid var(--bo-border)' }} /> : <div aria-hidden="true" style={{ width: 48, height: 48, borderRadius: 8, background: 'var(--bo-surface)', border: '1px solid var(--bo-border)' }} />}</td>
                        <td>{item.productName ?? item.productId ?? 'Produit'}</td>
                        <td>{item.sku ?? '—'}</td>
                        <td>{item.quantity}</td>
                        <td>{(item.unitPriceCents / 100).toFixed(2)} {detailOrder.currency}</td>
                        <td>{((item.totalCents ?? item.unitPriceCents * item.quantity) / 100).toFixed(2)} {detailOrder.currency}</td>
                      </tr>
                    ))}</tbody>
                  </table>
                </div>
              ) : <div style={{ color: 'var(--bo-text-muted)', marginTop: 8 }}>Aucun produit détaillé.</div>}
            </div>
            {isAdmin && detailOrder.status === 'pending' && (
              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                <Button onClick={() => void updateOrderStatus(detailOrder, 'paid')} disabled={actionOrderId === detailOrder.id}>
                  {deliveryPollOrderId === detailOrder.id ? 'Livraison en cours...' : 'Confirmer et lancer la livraison'}
                </Button>
                <Button variant="danger" onClick={() => setOrderToReject(detailOrder)} disabled={actionOrderId === detailOrder.id}>
                  Refuser la commande
                </Button>
              </div>
            )}
            {isAdmin && detailOrder.status === 'cancelled' && (
              <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                <Button variant="danger" onClick={() => setOrderToDelete(detailOrder)} disabled={actionOrderId === detailOrder.id}>Supprimer la commande</Button>
              </div>
            )}
          </div>
        )}
      </Modal>
      <ConfirmDialog
        isOpen={!!orderToReject}
        onClose={() => { if (!actionOrderId) setOrderToReject(null); }}
        onConfirm={() => { if (orderToReject) void updateOrderStatus(orderToReject, 'cancelled'); }}
        title="Refuser la commande"
         message={orderToReject ? `Refuser la commande #${orderToReject.id} ?` : ''}
        confirmLabel="Refuser"
        danger
        isLoading={!!actionOrderId}
      />
      <ConfirmDialog
        isOpen={!!orderToDelete}
        onClose={() => { if (!actionOrderId) setOrderToDelete(null); }}
        onConfirm={() => { if (orderToDelete) void deleteOrder(orderToDelete); }}
        title="Supprimer la commande"
        message={orderToDelete ? `Supprimer définitivement la commande #${orderToDelete.id.slice(0, 8)} ?` : ''}
        confirmLabel="Supprimer"
        danger
        isLoading={!!actionOrderId}
      />
    </div>
  );
}
