import { useCallback, useState } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardBody, CardHeader } from '../../components/Card';
import { Button } from '../../components/Button';
import { Badge, statusBadge } from '../../components/Badge';
import { LoadingState, ErrorState, EmptyState } from '../../components/States';
import { Modal } from '../../components/Modal';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { FormField, Input, Select, Textarea } from '../../components/FormField';
import { useNotification } from '../../hooks/useNotification';
import { Pagination } from '../../components/Pagination';

type Extension = {
  id: string;
  code: string;
  name: string;
  description?: string | null;
  type: string;
  targetCode?: string | null;
  value?: number | null;
  priceTnd: number;
  durationMonths?: number | null;
  requiresValidation: boolean;
  isActive: boolean;
  icon?: string | null;
};

type ExtensionForm = {
  code: string;
  name: string;
  description: string;
  type: string;
  targetCode: string;
  value: string;
  priceTnd: string;
  durationMonths: string;
  requiresValidation: boolean;
  isActive: boolean;
};

type QuotaDefinition = { id: string; code: string; name: string; description?: string | null; unit?: string | null; category?: string | null; priceTnd: number; isActive: boolean };
type ModuleDefinition = { moduleCode: string; moduleName: string };
type ThemeDefinition = { id: string; code: string; name: string; isActive: boolean };
type QuotaForm = { code: string; name: string; description: string; unit: string; category: string; priceTnd: string; isActive: boolean };

type ExtensionRequestItem = {
  id: string;
  boutiqueName: string;
  extensionName: string;
  extensionCode: string;
  status: string;
  priceTnd: number;
  requestedAt: string;
  comment?: string | null;
};

type Stats = {
  activeSubscriptions: number;
  expiredSubscriptions: number;
  pendingSubscriptionRequests: number;
  revenueSubscriptionsTnd: number;
  revenueExtensionsTnd: number;
  extensionRequestsByStatus: Record<string, number>;
  mostRequestedExtensions: Array<{ code: string; name: string; count: number }>;
  activeExtensionGrants: number;
  expiredExtensionGrants: number;
};

const emptyExtensionForm: ExtensionForm = {
  code: '', name: '', description: '', type: 'service', targetCode: '', value: '', priceTnd: '0', durationMonths: '', requiresValidation: true, isActive: true,
};
const emptyQuotaForm: QuotaForm = { code: '', name: '', description: '', unit: '', category: '', priceTnd: '0', isActive: true };

type Tab = 'stats' | 'extensions' | 'quotas' | 'requests';
const PAGE_SIZE = 20;

export function ExtensionsAdminPanel({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const [tab, setTab] = useState<Tab>('stats');

  // Extensions catalog state
  const [extModalOpen, setExtModalOpen] = useState(false);
  const [editingExt, setEditingExt] = useState<Extension | null>(null);
  const [extForm, setExtForm] = useState<ExtensionForm>(emptyExtensionForm);
  const [savingExt, setSavingExt] = useState(false);
  const [deleteExtId, setDeleteExtId] = useState<string | null>(null);

  // Quota definitions state
  const [quotaModalOpen, setQuotaModalOpen] = useState(false);
  const [editingQuota, setEditingQuota] = useState<QuotaDefinition | null>(null);
  const [quotaForm, setQuotaForm] = useState<QuotaForm>(emptyQuotaForm);
  const [savingQuota, setSavingQuota] = useState(false);
  const [deleteQuotaId, setDeleteQuotaId] = useState<string | null>(null);

  // Requests moderation state
  const [decisionId, setDecisionId] = useState<string | null>(null);
  const [decisionAction, setDecisionAction] = useState<'approve' | 'reject' | 'suspend' | null>(null);
  const [decisionComment, setDecisionComment] = useState('');
  const [extensionPage, setExtensionPage] = useState(1);
  const [quotaPage, setQuotaPage] = useState(1);
  const [requestPage, setRequestPage] = useState(1);

  const fetchStats = useCallback(() => api.get<Stats>('/admin/subscription/stats'), [api]);
  const fetchExtensions = useCallback(() => api.getCollection<Extension>(`/admin/extensions?page=${extensionPage}&itemsPerPage=${PAGE_SIZE}`), [api, extensionPage]);
  const fetchQuotas = useCallback(() => api.getCollection<QuotaDefinition>(`/admin/quota-definitions?page=${quotaPage}&itemsPerPage=${PAGE_SIZE}`), [api, quotaPage]);
  const fetchQuotaOptions = useCallback(() => api.getCollection<QuotaDefinition>('/admin/quota-definitions?itemsPerPage=100'), [api]);
  const fetchRequests = useCallback(() => api.getCollection<ExtensionRequestItem>(`/admin/extension-requests?page=${requestPage}&itemsPerPage=${PAGE_SIZE}`), [api, requestPage]);
  const fetchModules = useCallback(() => api.getCollection<ModuleDefinition>('/admin/platform-modules?itemsPerPage=100'), [api]);
  const fetchThemes = useCallback(() => api.getCollection<ThemeDefinition>('/admin/themes?itemsPerPage=100'), [api]);

  const { data: stats, isLoading: statsLoading, error: statsError, refresh: refreshStats } = useApiData(fetchStats, [tab]);
  const { data: extensions, isLoading: extLoading, error: extError, refresh: refreshExtensions } = useApiData(fetchExtensions, [tab, extensionPage]);
  const { data: quotas, isLoading: quotaLoading, error: quotaError, refresh: refreshQuotas } = useApiData(fetchQuotas, [tab, quotaPage]);
  const { data: quotaOptionsData } = useApiData(fetchQuotaOptions, [tab]);
  const { data: requestsData, isLoading: reqLoading, error: reqError, refresh: refreshRequests } = useApiData(fetchRequests, [tab, requestPage]);
  const { data: modulesData } = useApiData(fetchModules, [tab]);
  const { data: themesData } = useApiData(fetchThemes, [tab]);

  const extensionsList = extensions?.member ?? [];
  const quotasList = quotas?.member ?? [];
  const quotaOptions = quotaOptionsData?.member ?? quotasList;
  const requestsList = requestsData?.member ?? [];
  const extensionTotalPages = Math.max(1, Math.ceil((extensions?.totalItems ?? 0) / PAGE_SIZE));
  const quotaTotalPages = Math.max(1, Math.ceil((quotas?.totalItems ?? 0) / PAGE_SIZE));
  const requestTotalPages = Math.max(1, Math.ceil((requestsData?.totalItems ?? 0) / PAGE_SIZE));
  const moduleOptions = modulesData?.member ?? [];
  const themeOptions = (themesData?.member ?? []).filter((theme) => theme.isActive);
  const targetOptions = extForm.type === 'module'
    ? moduleOptions.map((module) => ({ code: module.moduleCode, name: module.moduleName }))
    : extForm.type === 'theme'
      ? themeOptions.map((theme) => ({ code: theme.code, name: theme.name }))
      : extForm.type === 'quota_boost'
         ? quotaOptions.filter((quota) => quota.isActive).map((quota) => ({ code: quota.code, name: quota.name }))
        : moduleOptions.map((module) => ({ code: module.moduleCode, name: module.moduleName }));
  const targetName = new Map([
    ...moduleOptions.map((module) => [module.moduleCode, module.moduleName] as const),
    ...themeOptions.map((theme) => [theme.code, theme.name] as const),
     ...quotaOptions.map((quota) => [quota.code, quota.name] as const),
  ]);

  const openCreateExt = () => { setEditingExt(null); setExtForm(emptyExtensionForm); setExtModalOpen(true); };
  const openEditExt = (ext: Extension) => {
    setEditingExt(ext);
    setExtForm({
      code: ext.code, name: ext.name, description: ext.description ?? '', type: ext.type, targetCode: ext.targetCode ?? '',
      value: ext.value != null ? String(ext.value) : '', priceTnd: String(ext.priceTnd), durationMonths: ext.durationMonths != null ? String(ext.durationMonths) : '',
      requiresValidation: ext.requiresValidation, isActive: ext.isActive,
    });
    setExtModalOpen(true);
  };

  const saveExtension = async () => {
    if (!extForm.code.trim() || !extForm.name.trim()) {
      showNotice('Le code et le nom sont obligatoires', 'error');
      return;
    }
    if (extForm.type !== 'service' && !extForm.targetCode) {
      showNotice('Sélectionnez la cible de cette extension', 'error');
      return;
    }
    setSavingExt(true);
    try {
      const payload = {
        code: extForm.code.trim(),
        name: extForm.name.trim(),
        description: extForm.description.trim() || null,
        type: extForm.type,
        targetCode: extForm.targetCode.trim() || null,
        value: extForm.value.trim() ? Number.parseInt(extForm.value, 10) : null,
        priceTnd: Math.max(0, Number.parseInt(extForm.priceTnd, 10) || 0),
        durationMonths: extForm.durationMonths.trim() ? Number.parseInt(extForm.durationMonths, 10) : null,
        requiresValidation: extForm.requiresValidation,
        isActive: extForm.isActive,
      };
      if (editingExt) {
        await api.patch(`/admin/extensions/${editingExt.id}`, payload);
        showNotice('Extension modifiée', 'success');
      } else {
        await api.post('/admin/extensions', payload);
        showNotice('Extension créée', 'success');
      }
      setExtModalOpen(false);
      refreshExtensions();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la sauvegarde', 'error');
    } finally {
      setSavingExt(false);
    }
  };

  const deleteExtension = async () => {
    if (!deleteExtId) return;
    try {
      await api.delete(`/admin/extensions/${deleteExtId}`);
      showNotice('Extension supprimée', 'success');
      setDeleteExtId(null);
      refreshExtensions();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la suppression', 'error');
    }
  };

  const openCreateQuota = () => { setEditingQuota(null); setQuotaForm(emptyQuotaForm); setQuotaModalOpen(true); };
  const openEditQuota = (quota: QuotaDefinition) => {
    setEditingQuota(quota);
    setQuotaForm({ code: quota.code, name: quota.name, description: quota.description ?? '', unit: quota.unit ?? '', category: quota.category ?? '', priceTnd: String(quota.priceTnd ?? 0), isActive: quota.isActive });
    setQuotaModalOpen(true);
  };

  const saveQuota = async () => {
    if (!quotaForm.code.trim() || !quotaForm.name.trim()) {
      showNotice('Le code et le nom sont obligatoires', 'error');
      return;
    }
    setSavingQuota(true);
    try {
      const payload = {
        code: quotaForm.code.trim(),
        name: quotaForm.name.trim(),
        description: quotaForm.description.trim() || null,
        unit: quotaForm.unit.trim() || null,
        category: quotaForm.category.trim() || null,
        priceTnd: Math.max(0, Number.parseInt(quotaForm.priceTnd, 10) || 0),
        isActive: quotaForm.isActive,
      };
      if (editingQuota) {
        await api.patch(`/admin/quota-definitions/${editingQuota.id}`, payload);
        showNotice('Quota modifié', 'success');
      } else {
        await api.post('/admin/quota-definitions', payload);
        showNotice('Quota créé', 'success');
      }
      setQuotaModalOpen(false);
      refreshQuotas();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la sauvegarde', 'error');
    } finally {
      setSavingQuota(false);
    }
  };

  const deleteQuota = async () => {
    if (!deleteQuotaId) return;
    try {
      await api.delete(`/admin/quota-definitions/${deleteQuotaId}`);
      showNotice('Quota supprimé', 'success');
      setDeleteQuotaId(null);
      refreshQuotas();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la suppression', 'error');
    }
  };

  const openDecision = (id: string, action: 'approve' | 'reject' | 'suspend') => {
    setDecisionId(id);
    setDecisionAction(action);
    setDecisionComment('');
  };

  const confirmDecision = async () => {
    if (!decisionId || !decisionAction) return;
    try {
      await api.patch(`/admin/extension-requests/${decisionId}/${decisionAction}`, { adminComment: decisionComment.trim() || null });
      showNotice('Décision enregistrée', 'success');
      setDecisionId(null);
      setDecisionAction(null);
      refreshRequests();
      refreshStats();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la décision', 'error');
    }
  };

  const decisionLabels: Record<string, string> = { approve: 'Approuver', reject: 'Refuser', suspend: 'Suspendre' };

  return (
    <div style={{ display: 'grid', gap: 20 }}>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
        <Button variant={tab === 'stats' ? 'primary' : 'ghost'} size="sm" onClick={() => setTab('stats')}>Statistiques</Button>
        <Button variant={tab === 'extensions' ? 'primary' : 'ghost'} size="sm" onClick={() => setTab('extensions')}>Extensions</Button>
        <Button variant={tab === 'quotas' ? 'primary' : 'ghost'} size="sm" onClick={() => setTab('quotas')}>Quotas</Button>
        <Button variant={tab === 'requests' ? 'primary' : 'ghost'} size="sm" onClick={() => setTab('requests')}>Demandes</Button>
      </div>

      {tab === 'stats' && (
        <Card>
          <CardHeader>Statistiques abonnements &amp; extensions</CardHeader>
          <CardBody>
            {statsLoading ? <LoadingState /> : statsError ? <ErrorState message={statsError} onRetry={refreshStats} /> : stats ? (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 16 }}>
                {[
                  { label: 'Abonnements actifs', value: stats.activeSubscriptions },
                  { label: 'Abonnements expirés', value: stats.expiredSubscriptions },
                  { label: 'Demandes d\'abonnement en attente', value: stats.pendingSubscriptionRequests },
                  { label: 'Revenu abonnements (TND)', value: stats.revenueSubscriptionsTnd },
                  { label: 'Revenu extensions (TND)', value: stats.revenueExtensionsTnd },
                  { label: 'Extensions actives', value: stats.activeExtensionGrants },
                  { label: 'Extensions expirées', value: stats.expiredExtensionGrants },
                ].map((item) => (
                  <div key={item.label} style={{ padding: 16, border: '1px solid var(--bo-border)', borderRadius: 12 }}>
                    <div style={{ fontSize: 24, fontWeight: 700 }}>{item.value}</div>
                    <div style={{ fontSize: 13, color: 'var(--bo-text-secondary)' }}>{item.label}</div>
                  </div>
                ))}
                <div style={{ padding: 16, border: '1px solid var(--bo-border)', borderRadius: 12, gridColumn: '1 / -1' }}>
                  <h4 style={{ marginTop: 0, fontSize: 13, textTransform: 'uppercase', color: 'var(--bo-text-muted)' }}>Extensions les plus demandées</h4>
                  {stats.mostRequestedExtensions.length === 0 ? <EmptyState /> : stats.mostRequestedExtensions.map((e) => (
                    <div key={e.code} style={{ display: 'flex', justifyContent: 'space-between', padding: '6px 0' }}>
                      <span>{e.name}</span>
                      <Badge tone="info">{e.count}</Badge>
                    </div>
                  ))}
                </div>
              </div>
            ) : <EmptyState />}
          </CardBody>
        </Card>
      )}

      {tab === 'extensions' && (
        <Card>
          <CardHeader>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <span>Catalogue d'extensions</span>
              <Button variant="primary" size="sm" onClick={openCreateExt}>Nouvelle extension</Button>
            </div>
          </CardHeader>
          <CardBody>
            {extLoading ? <LoadingState /> : extError ? <ErrorState message={extError} onRetry={refreshExtensions} /> : extensionsList.length === 0 ? <EmptyState /> : (
              <div style={{ display: 'grid', gap: 8 }}>
                 {extensionsList.map((ext) => (
                  <div key={ext.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 12px', border: '1px solid var(--bo-border)', borderRadius: 8 }}>
                    <div>
                      <strong>{ext.name}</strong> <span style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>({ext.code})</span>
                      <div style={{ fontSize: 12, color: 'var(--bo-text-secondary)' }}>
                         {ext.type} {ext.targetCode ? `→ ${targetName.get(ext.targetCode) ?? ext.targetCode}` : ''} {ext.value ? `(+${ext.value})` : ''} — {ext.priceTnd} TND{ext.durationMonths ? ` / ${ext.durationMonths} mois` : ' (permanent)'}
                      </div>
                    </div>
                    <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                      <Badge tone={ext.isActive ? 'success' : 'neutral'}>{ext.isActive ? 'Actif' : 'Inactif'}</Badge>
                      {ext.requiresValidation && <Badge tone="warning">Validation requise</Badge>}
                      <Button variant="secondary" size="sm" onClick={() => openEditExt(ext)}>Modifier</Button>
                      <Button variant="danger" size="sm" onClick={() => setDeleteExtId(ext.id)}>Suppr.</Button>
                    </div>
                  </div>
                 ))}
                 <Pagination page={extensionPage} totalPages={extensionTotalPages} onPageChange={setExtensionPage} />
               </div>
            )}
          </CardBody>
        </Card>
      )}

      {tab === 'quotas' && (
        <Card>
          <CardHeader>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <span>Définitions de quotas</span>
              <Button variant="primary" size="sm" onClick={openCreateQuota}>Nouveau quota</Button>
            </div>
          </CardHeader>
          <CardBody>
            {quotaLoading ? <LoadingState /> : quotaError ? <ErrorState message={quotaError} onRetry={refreshQuotas} /> : quotasList.length === 0 ? <EmptyState /> : (
              <div style={{ display: 'grid', gap: 8 }}>
                 {quotasList.map((quota) => (
                  <div key={quota.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 12px', border: '1px solid var(--bo-border)', borderRadius: 8 }}>
                    <div>
                      <strong>{quota.name}</strong> <span style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>({quota.code})</span>
                         {quota.category && <div style={{ fontSize: 12, color: 'var(--bo-text-secondary)' }}>{quota.category} {quota.unit ? `— ${quota.unit}` : ''}</div>}
                         <div style={{ fontSize: 12, color: 'var(--bo-text-secondary)' }}>Prix : {quota.priceTnd} TND</div>
                    </div>
                    <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                      <Badge tone={quota.isActive ? 'success' : 'neutral'}>{quota.isActive ? 'Actif' : 'Inactif'}</Badge>
                      <Button variant="secondary" size="sm" onClick={() => openEditQuota(quota)}>Modifier</Button>
                      <Button variant="danger" size="sm" onClick={() => setDeleteQuotaId(quota.id)}>Suppr.</Button>
                    </div>
                  </div>
                 ))}
                 <Pagination page={quotaPage} totalPages={quotaTotalPages} onPageChange={setQuotaPage} />
               </div>
            )}
            <p style={{ fontSize: 12, color: 'var(--bo-text-muted)', marginTop: 12 }}>
              Les limites par plan (nombre de produits, employés, etc.) se configurent depuis la fiche de chaque plan d'abonnement.
            </p>
          </CardBody>
        </Card>
      )}

      {tab === 'requests' && (
        <Card>
          <CardHeader>Demandes d'extension</CardHeader>
          <CardBody>
            {reqLoading ? <LoadingState /> : reqError ? <ErrorState message={reqError} onRetry={refreshRequests} /> : requestsList.length === 0 ? <EmptyState /> : (
              <div style={{ display: 'grid', gap: 8 }}>
                 {requestsList.map((req) => {
                  const badge = statusBadge(req.status);
                  const actionable = ['awaiting_validation', 'paid', 'activated'].includes(req.status);
                  return (
                    <div key={req.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 12px', border: '1px solid var(--bo-border)', borderRadius: 8 }}>
                      <div>
                        <strong>{req.boutiqueName}</strong> — {req.extensionName}
                        <div style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>
                          {new Date(req.requestedAt).toLocaleDateString('fr-FR')} — {req.priceTnd} TND {req.comment ? `— "${req.comment}"` : ''}
                        </div>
                      </div>
                      <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                        <Badge tone={badge.tone}>{badge.label}</Badge>
                        {['awaiting_validation', 'paid'].includes(req.status) && (
                          <>
                            <Button variant="secondary" size="sm" onClick={() => openDecision(req.id, 'approve')}>Approuver</Button>
                            <Button variant="ghost" size="sm" onClick={() => openDecision(req.id, 'reject')}>Refuser</Button>
                          </>
                        )}
                        {req.status === 'activated' && (
                          <Button variant="ghost" size="sm" onClick={() => openDecision(req.id, 'suspend')}>Suspendre</Button>
                        )}
                        {!actionable && !['activated'].includes(req.status) && null}
                      </div>
                    </div>
                  );
                 })}
                 <Pagination page={requestPage} totalPages={requestTotalPages} onPageChange={setRequestPage} />
               </div>
            )}
          </CardBody>
        </Card>
      )}

      <Modal
        isOpen={extModalOpen}
        onClose={() => setExtModalOpen(false)}
        title={editingExt ? 'Modifier l\'extension' : 'Créer une extension'}
        width="620px"
        footer={(
          <>
            <Button variant="secondary" onClick={() => setExtModalOpen(false)}>Annuler</Button>
            <Button variant="primary" onClick={saveExtension} disabled={savingExt}>{savingExt ? 'En cours...' : editingExt ? 'Enregistrer' : 'Créer'}</Button>
          </>
        )}
      >
        <div style={{ display: 'grid', gap: 14 }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
            <FormField label="Code" required>
              <Input value={extForm.code} onChange={(e) => setExtForm({ ...extForm, code: e.target.value })} placeholder="boost_products_100" />
            </FormField>
            <FormField label="Nom" required>
              <Input value={extForm.name} onChange={(e) => setExtForm({ ...extForm, name: e.target.value })} placeholder="+100 produits" />
            </FormField>
          </div>
          <FormField label="Description">
            <Textarea value={extForm.description} onChange={(e) => setExtForm({ ...extForm, description: e.target.value })} rows={2} />
          </FormField>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
             <FormField label="Type" required>
               <Select value={extForm.type} onChange={(e) => setExtForm({ ...extForm, type: e.target.value, targetCode: '' })}>
                <option value="quota_boost">Boost de quota</option>
                <option value="module">Module</option>
                <option value="theme">Thème</option>
                <option value="service">Service</option>
              </Select>
            </FormField>
             <FormField label="Extension liée à" required={extForm.type !== 'service'} hint="Sélectionnez une cible existante.">
               <Select value={extForm.targetCode} onChange={(e) => setExtForm({ ...extForm, targetCode: e.target.value })}>
                 <option value="">{extForm.type === 'service' ? 'Aucune cible spécifique' : 'Sélectionner une cible'}</option>
                 {extForm.targetCode && !targetOptions.some((option) => option.code === extForm.targetCode) && (
                   <option value={extForm.targetCode}>{targetName.get(extForm.targetCode) ?? extForm.targetCode}</option>
                 )}
                 {targetOptions.map((option) => <option key={option.code} value={option.code}>{option.name} ({option.code})</option>)}
               </Select>
             </FormField>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
            <FormField label="Valeur" hint="Pour boost de quota">
              <Input type="number" value={extForm.value} onChange={(e) => setExtForm({ ...extForm, value: e.target.value })} />
            </FormField>
            <FormField label="Prix TND" required>
              <Input type="number" min={0} value={extForm.priceTnd} onChange={(e) => setExtForm({ ...extForm, priceTnd: e.target.value })} />
            </FormField>
            <FormField label="Durée (mois)" hint="Vide = permanent">
              <Input type="number" min={1} value={extForm.durationMonths} onChange={(e) => setExtForm({ ...extForm, durationMonths: e.target.value })} />
            </FormField>
          </div>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 16 }}>
            <label><input type="checkbox" checked={extForm.requiresValidation} onChange={(e) => setExtForm({ ...extForm, requiresValidation: e.target.checked })} /> Validation requise</label>
            <label><input type="checkbox" checked={extForm.isActive} onChange={(e) => setExtForm({ ...extForm, isActive: e.target.checked })} /> Actif</label>
          </div>
        </div>
      </Modal>

      <Modal
        isOpen={quotaModalOpen}
        onClose={() => setQuotaModalOpen(false)}
        title={editingQuota ? 'Modifier le quota' : 'Créer un quota'}
        width="520px"
        footer={(
          <>
            <Button variant="secondary" onClick={() => setQuotaModalOpen(false)}>Annuler</Button>
            <Button variant="primary" onClick={saveQuota} disabled={savingQuota}>{savingQuota ? 'En cours...' : editingQuota ? 'Enregistrer' : 'Créer'}</Button>
          </>
        )}
      >
        <div style={{ display: 'grid', gap: 14 }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
            <FormField label="Code" required>
              <Input value={quotaForm.code} onChange={(e) => setQuotaForm({ ...quotaForm, code: e.target.value })} placeholder="max_products" />
            </FormField>
            <FormField label="Nom" required>
              <Input value={quotaForm.name} onChange={(e) => setQuotaForm({ ...quotaForm, name: e.target.value })} placeholder="Produits" />
            </FormField>
          </div>
          <FormField label="Description">
            <Textarea value={quotaForm.description} onChange={(e) => setQuotaForm({ ...quotaForm, description: e.target.value })} rows={2} />
          </FormField>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
             <FormField label="Unité">
               <Input value={quotaForm.unit} onChange={(e) => setQuotaForm({ ...quotaForm, unit: e.target.value })} placeholder="produits" />
             </FormField>
            <FormField label="Catégorie">
              <Input value={quotaForm.category} onChange={(e) => setQuotaForm({ ...quotaForm, category: e.target.value })} placeholder="catalogue" />
             </FormField>
           </div>
           <FormField label="Prix TND" required hint="Prix de référence du quota/extension">
             <Input type="number" min={0} value={quotaForm.priceTnd} onChange={(e) => setQuotaForm({ ...quotaForm, priceTnd: e.target.value })} />
           </FormField>
           <label><input type="checkbox" checked={quotaForm.isActive} onChange={(e) => setQuotaForm({ ...quotaForm, isActive: e.target.checked })} /> Actif</label>
        </div>
      </Modal>

      <Modal
        isOpen={decisionId !== null}
        onClose={() => { setDecisionId(null); setDecisionAction(null); }}
        title={decisionAction ? decisionLabels[decisionAction] : ''}
        width="480px"
        footer={(
          <>
            <Button variant="secondary" onClick={() => { setDecisionId(null); setDecisionAction(null); }}>Annuler</Button>
            <Button variant={decisionAction === 'reject' || decisionAction === 'suspend' ? 'danger' : 'primary'} onClick={confirmDecision}>Confirmer</Button>
          </>
        )}
      >
        <FormField label="Commentaire (optionnel)">
          <Textarea value={decisionComment} onChange={(e) => setDecisionComment(e.target.value)} rows={3} />
        </FormField>
      </Modal>

      <ConfirmDialog
        isOpen={deleteExtId !== null}
        title="Supprimer l'extension"
        message="Cette extension sera définitivement supprimée du catalogue."
        confirmLabel="Supprimer"
        onConfirm={deleteExtension}
        onClose={() => setDeleteExtId(null)}
        danger
      />

      <ConfirmDialog
        isOpen={deleteQuotaId !== null}
        title="Supprimer le quota"
        message="Cette définition de quota sera supprimée. Les limites de plan associées seront aussi supprimées."
        confirmLabel="Supprimer"
        onConfirm={deleteQuota}
        onClose={() => setDeleteQuotaId(null)}
        danger
      />
    </div>
  );
}
