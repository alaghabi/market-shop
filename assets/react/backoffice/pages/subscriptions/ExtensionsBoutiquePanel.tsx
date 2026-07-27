import { useCallback, useState } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardBody, CardHeader } from '../../components/Card';
import { Button } from '../../components/Button';
import { Badge, statusBadge } from '../../components/Badge';
import { LoadingState, ErrorState, EmptyState } from '../../components/States';
import { useNotification } from '../../hooks/useNotification';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { Pagination } from '../../components/Pagination';

type QuotaRow = { code: string; name: string; unit: string | null; limit: number | null; usage: number; remaining: number | null };
type ActiveExtension = { id: string; extensionCode: string; extensionName: string; type: string; activatedAt: string; expiresAt: string | null };
type PendingRequest = { id: string; extensionCode: string; extensionName: string; status: string; priceTnd: number; requestedAt: string };

type SubscriptionSummary = {
  isActive: boolean;
  planName: string | null;
  priceTnd: number;
  currency: string;
  endDate: string | null;
  daysRemaining: number | null;
  quotas: QuotaRow[];
  accessibleModules: string[] | Record<string, string>;
  accessibleThemes: Array<{ id: string; code: string; name: string }>;
  activeExtensions: ActiveExtension[];
  pendingRequests: PendingRequest[];
};

type ExtensionCatalogItem = {
  id: string;
  code: string;
  name: string;
  description?: string | null;
  type: string;
  priceTnd: number;
  durationMonths: number | null;
  isFree: boolean;
  isPermanent: boolean;
  requiresValidation: boolean;
  alreadyActive: boolean;
  pendingRequestId?: string | null;
  pendingRequestStatus?: string | null;
};

type ExtensionRequestItem = {
  id: string;
  extensionCode: string;
  extensionName: string;
  status: string;
  priceTnd: number;
  requestedAt: string;
  comment?: string | null;
  adminComment?: string | null;
};

const PAGE_SIZE = 20;

const typeLabels: Record<string, string> = {
  quota_boost: 'Quota',
  module: 'Module',
  theme: 'Thème',
  service: 'Service',
};

function QuotaBar({ quota }: { quota: QuotaRow }) {
  const pct = quota.limit ? Math.min(100, Math.round((quota.usage / quota.limit) * 100)) : 0;
  const danger = quota.limit !== null && quota.usage >= quota.limit;
  return (
    <div style={{ marginBottom: 12 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 4 }}>
        <span>{quota.name}</span>
        <span style={{ color: danger ? 'var(--bo-error)' : 'var(--bo-text-secondary)' }}>
          {quota.usage}{quota.limit !== null ? ` / ${quota.limit}` : ' / illimité'} {quota.unit ?? ''}
        </span>
      </div>
      {quota.limit !== null && (
        <div style={{ height: 6, background: 'var(--bo-border)', borderRadius: 4, overflow: 'hidden' }}>
          <div style={{ height: '100%', width: `${pct}%`, background: danger ? 'var(--bo-error)' : 'var(--bo-primary)' }} />
        </div>
      )}
    </div>
  );
}

export function ExtensionsBoutiquePanel({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const [confirmRequestId, setConfirmRequestId] = useState<string | null>(null);
  const [pendingCancelId, setPendingCancelId] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [catalogPage, setCatalogPage] = useState(1);
  const [requestsPage, setRequestsPage] = useState(1);

  const fetchSummary = useCallback(() => api.get<SubscriptionSummary>('/subscription/summary'), [api]);
  const fetchCatalog = useCallback(() => api.getCollection<ExtensionCatalogItem>(`/extensions/available?page=${catalogPage}&itemsPerPage=${PAGE_SIZE}`), [api, catalogPage]);
  const fetchRequests = useCallback(() => api.getCollection<ExtensionRequestItem>(`/extension-requests?page=${requestsPage}&itemsPerPage=${PAGE_SIZE}`), [api, requestsPage]);

  const { data: summary, isLoading: summaryLoading, error: summaryError, refresh: refreshSummary } = useApiData(fetchSummary, []);
  const { data: catalog, isLoading: catalogLoading, error: catalogError, refresh: refreshCatalog } = useApiData(fetchCatalog, [catalogPage]);
  const { data: requestsData, refresh: refreshRequests } = useApiData(fetchRequests, [requestsPage]);

  const catalogList = catalog?.member ?? [];
  const requestsList = requestsData?.member ?? [];
  const catalogTotalPages = Math.max(1, Math.ceil((catalog?.totalItems ?? 0) / PAGE_SIZE));
  const requestsTotalPages = Math.max(1, Math.ceil((requestsData?.totalItems ?? 0) / PAGE_SIZE));

  const refreshAll = () => {
    refreshSummary();
    refreshCatalog();
    refreshRequests();
  };

  const requestExtension = async (extensionId: string) => {
    setBusyId(extensionId);
    try {
      await api.post('/extension-requests', { extensionId });
      showNotice('Demande envoyée', 'success');
      setConfirmRequestId(null);
      refreshAll();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la demande', 'error');
    } finally {
      setBusyId(null);
    }
  };

  const payRequest = async (id: string) => {
    setBusyId(id);
    try {
      await api.patch(`/extension-requests/${id}/pay`, {});
      showNotice('Paiement confirmé', 'success');
      refreshAll();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors du paiement', 'error');
    } finally {
      setBusyId(null);
    }
  };

  const cancelRequest = async () => {
    if (!pendingCancelId) return;
    try {
      await api.patch(`/extension-requests/${pendingCancelId}/cancel`, {});
      showNotice('Demande annulée', 'success');
      setPendingCancelId(null);
      refreshAll();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de l\'annulation', 'error');
    }
  };

  const renewSubscription = async () => {
    setBusyId('renew');
    try {
      await api.post('/subscription/renew', {});
      showNotice('Demande de renouvellement envoyée', 'success');
      refreshAll();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors du renouvellement', 'error');
    } finally {
      setBusyId(null);
    }
  };

  const renewExtension = async (extensionCode: string) => {
    const item = catalogList.find((ext) => ext.code === extensionCode);
    if (!item) return;
    setBusyId(extensionCode);
    try {
      await api.post('/extension-requests', { extensionId: item.id, comment: 'Renouvellement' });
      showNotice('Demande de renouvellement envoyée', 'success');
      refreshAll();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors du renouvellement', 'error');
    } finally {
      setBusyId(null);
    }
  };

  return (
    <div style={{ display: 'grid', gap: 20 }}>
      <Card>
        <CardHeader>Mon abonnement</CardHeader>
        <CardBody>
          {summaryLoading ? <LoadingState /> : summaryError ? <ErrorState message={summaryError} onRetry={refreshSummary} /> : summary ? (
            <div style={{ display: 'grid', gridTemplateColumns: '1.2fr 1fr', gap: 32 }}>
              <div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                  <h3 style={{ margin: 0 }}>{summary.planName ?? 'Aucun plan'}</h3>
                  <Badge tone={summary.isActive ? 'success' : 'error'}>{summary.isActive ? 'Actif' : 'Inactif'}</Badge>
                  {summary.isActive && (
                    <Button variant="secondary" size="sm" onClick={renewSubscription} disabled={busyId === 'renew'}>
                      {busyId === 'renew' ? 'Envoi...' : 'Renouveler'}
                    </Button>
                  )}
                </div>
                {summary.endDate && (
                  <p style={{ fontSize: 13, color: 'var(--bo-text-secondary)', marginBottom: 16 }}>
                    Expire le {new Date(summary.endDate).toLocaleDateString('fr-FR')}
                    {typeof summary.daysRemaining === 'number' ? ` (${summary.daysRemaining} jour(s) restant(s))` : ''}
                  </p>
                )}
                <h4 style={{ fontSize: 13, textTransform: 'uppercase', color: 'var(--bo-text-muted)', marginBottom: 8 }}>Quotas</h4>
                {summary.quotas.length === 0 ? <EmptyState /> : summary.quotas.map((q) => <QuotaBar key={q.code} quota={q} />)}
              </div>
              <div>
                <h4 style={{ fontSize: 13, textTransform: 'uppercase', color: 'var(--bo-text-muted)', marginBottom: 8 }}>Extensions actives</h4>
                {summary.activeExtensions.length === 0 ? <EmptyState /> : (
                  <div style={{ display: 'grid', gap: 8, marginBottom: 20 }}>
                    {summary.activeExtensions.map((ext) => (
                      <div key={ext.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8, fontSize: 13, padding: '8px 10px', border: '1px solid var(--bo-border)', borderRadius: 8 }}>
                        <span>{ext.extensionName}</span>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                          <span style={{ color: 'var(--bo-text-muted)' }}>{ext.expiresAt ? `jusqu'au ${new Date(ext.expiresAt).toLocaleDateString('fr-FR')}` : 'permanent'}</span>
                          {ext.expiresAt && (
                            <Button variant="ghost" size="sm" onClick={() => renewExtension(ext.extensionCode)} disabled={busyId === ext.extensionCode}>
                              Renouveler
                            </Button>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
                <h4 style={{ fontSize: 13, textTransform: 'uppercase', color: 'var(--bo-text-muted)', marginBottom: 8 }}>Modules accessibles</h4>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                  {(Array.isArray(summary.accessibleModules) ? summary.accessibleModules : Object.values(summary.accessibleModules ?? {})).map((m) => <Badge key={m} tone="neutral">{m}</Badge>)}
                </div>
              </div>
            </div>
          ) : <EmptyState />}
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <span>Catalogue d'extensions</span>
          <p style={{ margin: '4px 0 0', fontSize: 13, fontWeight: 400, color: 'var(--bo-text-secondary)' }}>Boostez vos quotas ou débloquez des modules et thèmes supplémentaires.</p>
        </CardHeader>
        <CardBody>
          {catalogLoading ? <LoadingState /> : catalogError ? <ErrorState message={catalogError} onRetry={refreshCatalog} /> : catalogList.length === 0 ? <EmptyState /> : (
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: 16 }}>
               {catalogList.map((ext) => (
                <div key={ext.id} style={{ padding: 18, border: '1px solid var(--bo-border)', borderRadius: 12 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8, marginBottom: 8 }}>
                    <strong>{ext.name}</strong>
                    <Badge tone="info">{typeLabels[ext.type] ?? ext.type}</Badge>
                  </div>
                  {ext.description && <p style={{ fontSize: 13, color: 'var(--bo-text-secondary)', marginBottom: 12 }}>{ext.description}</p>}
                  <div style={{ fontWeight: 700, marginBottom: 12 }}>
                    {ext.isFree ? 'Gratuit' : `${ext.priceTnd} TND`}
                    {!ext.isPermanent && ext.durationMonths ? <span style={{ fontWeight: 400, fontSize: 13, color: 'var(--bo-text-muted)' }}> / {ext.durationMonths} mois</span> : null}
                  </div>
                  {ext.alreadyActive ? (
                    <Badge tone="success">Déjà active</Badge>
                  ) : ext.pendingRequestStatus ? (
                    <Badge tone={statusBadge(ext.pendingRequestStatus).tone}>{statusBadge(ext.pendingRequestStatus).label}</Badge>
                  ) : (
                    <Button variant="primary" size="sm" disabled={busyId === ext.id} onClick={() => setConfirmRequestId(ext.id)}>
                      {ext.isFree && !ext.requiresValidation ? 'Activer' : 'Demander'}
                    </Button>
                  )}
                </div>
               ))}
               <Pagination page={catalogPage} totalPages={catalogTotalPages} onPageChange={setCatalogPage} />
             </div>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardHeader>Mes demandes d'extension</CardHeader>
        <CardBody>
          {requestsList.length === 0 ? <EmptyState /> : (
            <div style={{ display: 'grid', gap: 8 }}>
               {requestsList.map((req) => {
                const badge = statusBadge(req.status);
                return (
                  <div key={req.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 12px', border: '1px solid var(--bo-border)', borderRadius: 8 }}>
                    <div>
                      <strong>{req.extensionName}</strong>
                      <div style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>
                        {new Date(req.requestedAt).toLocaleDateString('fr-FR')} — {req.priceTnd} TND
                        {req.adminComment ? ` — ${req.adminComment}` : ''}
                      </div>
                    </div>
                    <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                      <Badge tone={badge.tone}>{badge.label}</Badge>
                      {req.status === 'awaiting_payment' && (
                        <Button variant="secondary" size="sm" disabled={busyId === req.id} onClick={() => payRequest(req.id)}>Payer</Button>
                      )}
                      {['awaiting_payment', 'awaiting_validation'].includes(req.status) && (
                        <Button variant="ghost" size="sm" onClick={() => setPendingCancelId(req.id)}>Annuler</Button>
                      )}
                    </div>
                  </div>
                );
               })}
               <Pagination page={requestsPage} totalPages={requestsTotalPages} onPageChange={setRequestsPage} />
             </div>
          )}
        </CardBody>
      </Card>

      <ConfirmDialog
        isOpen={confirmRequestId !== null}
        title="Demander cette extension"
        message="Votre demande sera envoyée pour traitement. Si elle est payante, vous devrez confirmer le paiement ensuite."
        confirmLabel="Confirmer"
        onConfirm={() => confirmRequestId && requestExtension(confirmRequestId)}
        onClose={() => setConfirmRequestId(null)}
      />

      <ConfirmDialog
        isOpen={pendingCancelId !== null}
        title="Annuler la demande"
        message="Cette demande d'extension sera annulée."
        confirmLabel="Annuler la demande"
        onConfirm={cancelRequest}
        onClose={() => setPendingCancelId(null)}
        danger
      />
    </div>
  );
}
