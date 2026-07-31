import { useState, useCallback, useMemo } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardBody, CardHeader } from '../../components/Card';
import { Button } from '../../components/Button';
import { Badge } from '../../components/Badge';
import { LoadingState, ErrorState, EmptyState } from '../../components/States';
import { PageHeader } from '../../layout/Shell';
import { useNotification } from '../../hooks/useNotification';
import { Modal } from '../../components/Modal';
import { FormField, Input, Select } from '../../components/FormField';
import { Pagination } from '../../components/Pagination';

type BoutiqueAdmin = {
  id: string;
  userId: string;
  email: string;
  displayName?: string | null;
  boutiqueName: string;
  role: string;
  status: string;
};

type SubscriptionPlan = {
  id: string;
  name: string;
  priceTnd: number;
  renewalPriceTnd?: number | null;
  effectiveRenewalPriceTnd?: number;
  durationMonths: number;
  isFree: boolean;
  isActive: boolean;
};

type Extension = {
  id: string;
  code: string;
  name: string;
  targetCode?: string | null;
  value?: number | null;
  priceTnd: number;
  durationMonths?: number | null;
  requiresValidation: boolean;
};

type AccountSubscription = {
  id: string;
  userId: string;
  userEmail: string;
  userDisplayName?: string | null;
  planId?: string | null;
  planName?: string | null;
  status: string;
  startDate?: string | null;
  endDate?: string | null;
  extensions: Array<{ code: string; name: string; expiresAt?: string | null }>;
  createdAt: string;
};

const PAGE_SIZE = 20;

export function AdminAccountSubscriptionPanel({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const [page, setPage] = useState(1);
  const [modalOpen, setModalOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState<{
    userId: string;
    planId: string;
    extensionIds: string[];
    startDate: string;
    endDate: string;
  }>({
    userId: '',
    planId: '',
    extensionIds: [],
    startDate: '',
    endDate: '',
  });

  const fetchAdmins = useCallback(
    () => api.getCollection<BoutiqueAdmin>('/admin/boutique-admins?itemsPerPage=200'),
    [api],
  );
  const fetchPlans = useCallback(
    () => api.getCollection<SubscriptionPlan>('/admin/subscription-plans?itemsPerPage=100'),
    [api],
  );
  const fetchExtensions = useCallback(
    () => api.getCollection<Extension>('/admin/extensions?itemsPerPage=100'),
    [api],
  );
  const fetchSubscriptions = useCallback(
    () => api.getCollection<AccountSubscription>(`/admin/account-subscriptions?page=${page}&itemsPerPage=${PAGE_SIZE}`),
    [api, page],
  );

  const { data: adminsData } = useApiData(fetchAdmins, []);
  const { data: plansData } = useApiData(fetchPlans, []);
  const { data: extensionsData } = useApiData(fetchExtensions, []);
  const { data: subsData, isLoading: subsLoading, error: subsError, refresh: refreshSubs } = useApiData(fetchSubscriptions, [page]);

  const users = useMemo(() => {
    const byId = new Map<string, { id: string; email: string; displayName?: string | null; boutiqueName?: string }>();
    for (const admin of adminsData?.member ?? []) {
      if (admin.role !== 'ROLE_BOUTIQUE_ADMIN' || byId.has(admin.userId)) {
        continue;
      }
      byId.set(admin.userId, {
        id: admin.userId,
        email: admin.email,
        displayName: admin.displayName,
        boutiqueName: admin.boutiqueName,
      });
    }
    return Array.from(byId.values());
  }, [adminsData]);

  const plans = useMemo(
    () => (plansData?.member ?? []).filter((plan) => plan.isActive),
    [plansData],
  );
  const extensions = extensionsData?.member ?? [];
  const subscriptions = subsData?.member ?? [];

  const openCreate = () => {
    setForm({ userId: '', planId: '', extensionIds: [], startDate: '', endDate: '' });
    setModalOpen(true);
  };

  const handleSubmit = async () => {
    if (!form.userId || !form.planId) {
      showNotice('Utilisateur et plan sont obligatoires', 'error');
      return;
    }
    setSaving(true);
    try {
      await api.post('/admin/account-subscriptions', {
        userId: form.userId,
        planId: form.planId,
        extensionIds: form.extensionIds,
        startDate: form.startDate || null,
        endDate: form.endDate || null,
      });
      showNotice('Abonnement créé', 'success');
      setModalOpen(false);
      refreshSubs();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la création', 'error');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div>
      <PageHeader title="Abonnements par compte" description="Créer et gérer les abonnements des comptes boutique." actions={<Button variant="primary" onClick={openCreate}>Créer un abonnement</Button>} />

      <Card>
        <CardHeader>Abonnements</CardHeader>
        <CardBody>
          {subsLoading ? <LoadingState /> : subsError ? <ErrorState message={subsError} onRetry={refreshSubs} /> : subscriptions.length === 0 ? <EmptyState /> : (
            <>
              <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                  <thead>
                    <tr style={{ borderBottom: '1px solid var(--bo-border)' }}>
                      <th style={{ textAlign: 'left', padding: '12px 8px' }}>Utilisateur</th>
                      <th style={{ textAlign: 'left', padding: '12px 8px' }}>Plan</th>
                      <th style={{ textAlign: 'left', padding: '12px 8px' }}>Statut</th>
                      <th style={{ textAlign: 'left', padding: '12px 8px' }}>Début</th>
                      <th style={{ textAlign: 'left', padding: '12px 8px' }}>Fin</th>
                      <th style={{ textAlign: 'left', padding: '12px 8px' }}>Extensions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {subscriptions.map((sub) => (
                      <tr key={sub.id} style={{ borderBottom: '1px solid var(--bo-border)' }}>
                        <td style={{ padding: '12px 8px' }}>
                          <div><strong>{sub.userDisplayName ?? sub.userEmail}</strong></div>
                          <div style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>{sub.userEmail}</div>
                        </td>
                        <td style={{ padding: '12px 8px' }}>{sub.planName ?? '—'}</td>
                        <td style={{ padding: '12px 8px' }}><Badge tone={sub.status === 'active' ? 'success' : 'neutral'}>{sub.status}</Badge></td>
                        <td style={{ padding: '12px 8px' }}>{sub.startDate ? new Date(sub.startDate).toLocaleDateString('fr-FR') : '—'}</td>
                        <td style={{ padding: '12px 8px' }}>{sub.endDate ? new Date(sub.endDate).toLocaleDateString('fr-FR') : '—'}</td>
                        <td style={{ padding: '12px 8px' }}>
                          {sub.extensions.length ? (
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
                              {sub.extensions.map((ext) => (
                                <Badge key={ext.code} tone="neutral">{ext.name}{ext.expiresAt ? ` (jusqu'au ${new Date(ext.expiresAt).toLocaleDateString('fr-FR')})` : ''}</Badge>
                              ))}
                            </div>
                          ) : (
                            <span style={{ color: 'var(--bo-text-muted)', fontSize: 12 }}>—</span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <Pagination page={page} totalPages={Math.max(1, Math.ceil((subsData?.totalItems ?? 0) / PAGE_SIZE))} onPageChange={setPage} />
            </>
          )}
        </CardBody>
      </Card>

      <Modal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        title="Créer un abonnement"
        width="640px"
        footer={
          <>
            <Button variant="secondary" onClick={() => setModalOpen(false)}>Annuler</Button>
            <Button variant="primary" onClick={handleSubmit} disabled={saving}>{saving ? 'Création...' : 'Créer'}</Button>
          </>
        }
      >
        <div style={{ display: 'grid', gap: 14 }}>
          <FormField label="Utilisateur" required hint="Sélectionnez le compte boutique admin">
            <Select required value={form.userId} onChange={(e) => setForm({ ...form, userId: e.target.value })}>
              <option value="">Sélectionner un utilisateur</option>
              {users.map((user) => (
                <option key={user.id} value={user.id}>
                  {user.displayName ?? user.email} ({user.email}){user.boutiqueName ? ` — ${user.boutiqueName}` : ''}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Plan" required>
            <Select required value={form.planId} onChange={(e) => setForm({ ...form, planId: e.target.value })}>
              <option value="">Sélectionner un plan</option>
              {plans.map((plan) => (
                <option key={plan.id} value={plan.id}>
                  {plan.name} — {plan.isFree ? 'Gratuit' : `${plan.priceTnd.toLocaleString()} TND`}
                  {!plan.isFree && plan.renewalPriceTnd != null
                    ? ` (renouv. ${plan.effectiveRenewalPriceTnd ?? plan.renewalPriceTnd} TND)`
                    : ''}
                  {' / '}{plan.durationMonths} mois
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Extensions" hint="Extensions cumulatives (ex: +1, +5 boutiques). Sélectionnez celles à activer.">
            {extensions.length === 0 ? (
              <p style={{ margin: 0, color: 'var(--bo-text-muted)', fontSize: 13 }}>Aucune extension disponible.</p>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: 8, maxHeight: 200, overflowY: 'auto', padding: 4 }}>
                {extensions.map((ext) => {
                  const checked = form.extensionIds.includes(ext.id);
                  return (
                    <label key={ext.id} style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '8px 10px', border: '1px solid var(--bo-border)', borderRadius: 8, cursor: 'pointer' }}>
                      <input
                        type="checkbox"
                        checked={checked}
                        onChange={() => setForm({ ...form, extensionIds: checked ? form.extensionIds.filter((id) => id !== ext.id) : [...form.extensionIds, ext.id] })}
                      />
                      <div>
                        <strong>{ext.name}</strong>
                        <div style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>
                          {ext.code}{ext.targetCode ? ` (${ext.targetCode}: +${ext.value})` : ''}
                          {ext.priceTnd > 0 ? ` — ${ext.priceTnd.toLocaleString()} TND` : ''}
                          {ext.durationMonths ? ` / ${ext.durationMonths} mois` : ' / permanent'}
                          {ext.requiresValidation ? ' — validation requise' : ''}
                        </div>
                      </div>
                    </label>
                  );
                })}
              </div>
            )}
          </FormField>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
            <FormField label="Date de début (optionnel)" hint="Défaut: maintenant">
              <Input type="datetime-local" value={form.startDate} onChange={(e) => setForm({ ...form, startDate: e.target.value })} />
            </FormField>
            <FormField label="Date de fin (optionnel)" hint="Défaut: selon durée du plan">
              <Input type="datetime-local" value={form.endDate} onChange={(e) => setForm({ ...form, endDate: e.target.value })} />
            </FormField>
          </div>
          <p style={{ margin: 0, fontSize: 13, color: 'var(--bo-text-muted)' }}>
            L'abonnement sera créé en statut <strong>Actif</strong>. Les extensions sont cumulées (ex: +1 + +1 + +5 = +7 boutiques).
          </p>
        </div>
      </Modal>
    </div>
  );
}
