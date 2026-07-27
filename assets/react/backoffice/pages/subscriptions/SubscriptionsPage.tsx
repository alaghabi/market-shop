import { useState, useCallback } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardBody } from '../../components/Card';
import { Button } from '../../components/Button';
import { Badge } from '../../components/Badge';
import { LoadingState, ErrorState, EmptyState } from '../../components/States';
import { PageHeader } from '../../layout/Shell';
import { useNotification } from '../../hooks/useNotification';
import { Modal } from '../../components/Modal';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { FormField, Input, Select, Textarea } from '../../components/FormField';
import { useBoutique } from '../../hooks/useBoutique';
import { BoutiqueFormSelect, resolveFormBoutiqueId } from '../../components/BoutiqueFormSelect';
import { ExtensionsBoutiquePanel } from './ExtensionsBoutiquePanel';
import { ExtensionsAdminPanel } from './ExtensionsAdminPanel';
import { Pagination } from '../../components/Pagination';

type SubscriptionPlan = {
  id: string;
  name: string;
  description?: string | null;
  priceTnd: number;
  durationMonths: number;
  isFree: boolean;
  modules?: string[] | null;
  isActive: boolean;
  isVisible: boolean;
};

type SubscriptionPlanForm = {
  name: string;
  description: string;
  priceTnd: string;
  durationMonths: string;
  isFree: boolean;
  isActive: boolean;
  isVisible: boolean;
  modules: string[];
};

type ModuleOption = {
  moduleCode: string;
  moduleName: string;
};

type Subscription = {
  id: string;
  plan?: string;
  status: string;
  startDate?: string;
  endDate?: string;
  createdAt: string;
};

const emptyForm: SubscriptionPlanForm = {
  name: '',
  description: '',
  priceTnd: '0',
  durationMonths: '1',
  isFree: false,
  isActive: true,
  isVisible: true,
  modules: [],
};

const PAGE_SIZE = 20;

export function SubscriptionsPage({ getAccessToken, userRoles = [] }: { getAccessToken: () => string | null; userRoles?: string[] }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const { boutique } = useBoutique();
  const isSuperAdmin = userRoles.includes('ROLE_SUPER_ADMIN');
  const [tab, setTab] = useState<'plans' | 'extensions'>('plans');
  const [page, setPage] = useState(1);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<SubscriptionPlan | null>(null);
  const [form, setForm] = useState<SubscriptionPlanForm>(emptyForm);
  const [saving, setSaving] = useState(false);
  const [deleteId, setDeleteId] = useState<string | null>(null);
  const [subscriptionModalOpen, setSubscriptionModalOpen] = useState(false);
  const [previewModalOpen, setPreviewModalOpen] = useState(false);
  const [subscriptionBoutiqueId, setSubscriptionBoutiqueId] = useState('');
  const [subscriptionPlanId, setSubscriptionPlanId] = useState('');
  const [previewLoading, setPreviewLoading] = useState(false);
  const [planPreview, setPlanPreview] = useState<{
    currentPlanName?: string | null;
    newPlanName: string;
    newPlanPriceTnd: number;
    currency: string;
    durationMonths: number;
    isRenewal: boolean;
    projectedEndDate?: string | null;
    quotaChanges: Array<{ code: string; name: string; currentLimit: number | null; newLimit: number | null; currentUsage: number }>;
    modulesGained: string[];
    modulesLost: string[];
    themesGained: Array<{ code: string; name: string }>;
    themesLost: Array<{ code: string; name: string }>;
    extensionCompatibility: Array<{ code: string; name: string; compatible: boolean }>;
  } | null>(null);
  const [savingSubscriptionRequest, setSavingSubscriptionRequest] = useState(false);

  const fetchPlans = useCallback(
    () => api.getCollection<SubscriptionPlan>(`${isSuperAdmin ? '/admin/subscription-plans' : '/boutique/subscription-plans'}?page=${page}&itemsPerPage=${PAGE_SIZE}`),
    [api, isSuperAdmin, page],
  );
  const fetchPlanOptions = useCallback(
    () => api.getCollection<SubscriptionPlan>(`${isSuperAdmin ? '/admin/subscription-plans' : '/boutique/subscription-plans'}?itemsPerPage=100`),
    [api, isSuperAdmin],
  );
  const fetchSub = useCallback(() => api.get<{ member: Subscription[] }>('/subscriptions').catch(() => ({ member: [] })), [api]);
  const fetchModules = useCallback(
    () => isSuperAdmin ? api.getCollection<ModuleOption>('/admin/platform-modules?itemsPerPage=100') : Promise.resolve({ member: [], totalItems: 0 }),
    [api, isSuperAdmin],
  );

  const { data: plans, isLoading: plansLoading, error: plansError, refresh: refreshPlans } = useApiData(fetchPlans, [page]);
  const { data: planOptionsData } = useApiData(fetchPlanOptions, [isSuperAdmin]);
  const { data: subsData } = useApiData(fetchSub, []);
  const { data: modulesData, isLoading: modulesLoading } = useApiData(fetchModules, [isSuperAdmin]);

  const plansList = plans?.member ?? [];
  const planOptions = planOptionsData?.member ?? plansList;
  const subscriptions = subsData?.member ?? [];
  const moduleOptions = modulesData?.member ?? [];
  const totalPages = Math.max(1, Math.ceil((plans?.totalItems ?? 0) / PAGE_SIZE));
  const moduleNames = new Map(moduleOptions.map((module) => [module.moduleCode, module.moduleName]));

  const openCreate = () => {
    setEditing(null);
    setForm(emptyForm);
    setModalOpen(true);
  };

  const openEdit = (plan: SubscriptionPlan) => {
    setEditing(plan);
    setForm({
      name: plan.name,
      description: plan.description ?? '',
      priceTnd: String(plan.priceTnd ?? 0),
      durationMonths: String(plan.durationMonths ?? 1),
      isFree: plan.isFree,
      isActive: plan.isActive,
      isVisible: plan.isVisible,
      modules: plan.modules ?? [],
    });
    setModalOpen(true);
  };

  const payloadFromForm = () => ({
    name: form.name.trim(),
    description: form.description.trim() || null,
    durationMonths: Math.max(1, Number.parseInt(form.durationMonths, 10) || 1),
    priceTnd: form.isFree ? 0 : Math.max(0, Number.parseInt(form.priceTnd, 10) || 0),
    isFree: form.isFree,
    isVisible: form.isVisible,
    isActive: form.isActive,
    modules: form.modules,
  });

  const savePlan = async () => {
    if (!form.name.trim()) {
      showNotice('Le nom du plan est obligatoire', 'error');
      return;
    }

    setSaving(true);
    try {
      const payload = payloadFromForm();
      if (editing) {
        await api.patch(`/admin/subscription-plans/${editing.id}`, payload);
        showNotice('Plan modifié', 'success');
      } else {
        await api.post('/admin/subscription-plans', payload);
        showNotice('Plan créé', 'success');
      }
      setModalOpen(false);
      refreshPlans();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la sauvegarde', 'error');
    } finally {
      setSaving(false);
    }
  };

  const updatePlan = async (plan: SubscriptionPlan, changes: Partial<SubscriptionPlan>) => {
    try {
      await api.patch(`/admin/subscription-plans/${plan.id}`, {
        name: plan.name,
        description: plan.description ?? null,
        durationMonths: plan.durationMonths,
        priceTnd: plan.priceTnd,
        isFree: plan.isFree,
        isVisible: plan.isVisible,
        isActive: plan.isActive,
        modules: plan.modules ?? [],
        ...changes,
      });
      showNotice('Plan mis à jour', 'success');
      refreshPlans();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la mise à jour', 'error');
    }
  };

  const duplicatePlan = async (plan: SubscriptionPlan) => {
    try {
      await api.post('/admin/subscription-plans', {
        name: `${plan.name} (copie)`,
        description: plan.description ?? null,
        durationMonths: plan.durationMonths,
        priceTnd: plan.priceTnd,
        isFree: plan.isFree,
        isVisible: plan.isVisible,
        isActive: false,
        modules: plan.modules ?? [],
      });
      showNotice('Plan dupliqué en brouillon inactif.', 'success');
      refreshPlans();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la duplication', 'error');
    }
  };

  const deletePlan = async () => {
    if (!deleteId) return;
    try {
      await api.delete(`/admin/subscription-plans/${deleteId}`);
      showNotice('Plan supprimé', 'success');
      setDeleteId(null);
      refreshPlans();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la suppression', 'error');
    }
  };

  const openSubscriptionChange = (planId = '') => {
    setSubscriptionBoutiqueId('');
    setSubscriptionPlanId(planId);
    if (planId) {
      void loadPlanPreview(planId);
    } else {
      setSubscriptionModalOpen(true);
    }
  };

  const loadPlanPreview = async (planId: string) => {
    setPreviewLoading(true);
    setPreviewModalOpen(true);
    try {
      const preview = await api.get<typeof planPreview>(`/subscription/plan-change/preview?planId=${encodeURIComponent(planId)}`);
      setPlanPreview(preview);
      setSubscriptionPlanId(planId);
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Impossible de charger l\'aperçu', 'error');
      setPreviewModalOpen(false);
    } finally {
      setPreviewLoading(false);
    }
  };

  const requestSubscriptionChange = async () => {
    const boutiqueId = resolveFormBoutiqueId(boutique?.id, subscriptionBoutiqueId);
    if (!boutiqueId) {
      showNotice('Sélectionnez une boutique.', 'error');
      return;
    }
    if (!subscriptionPlanId) {
      showNotice('Sélectionnez un plan.', 'error');
      return;
    }

    setSavingSubscriptionRequest(true);
    try {
      await api.post('/subscription-requests', { boutiqueId, subscriptionPlanId });
      showNotice('Demande de modification envoyée', 'success');
      setSubscriptionModalOpen(false);
      setPreviewModalOpen(false);
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la demande', 'error');
    } finally {
      setSavingSubscriptionRequest(false);
    }
  };

  return (
    <div>
      <PageHeader
        title="Abonnements"
        description={isSuperAdmin
          ? 'Gestion Super Admin des plans, extensions, quotas et demandes.'
          : 'Votre abonnement, vos quotas et le catalogue d\'extensions disponibles.'}
        actions={tab === 'plans' && isSuperAdmin ? <Button variant="primary" onClick={openCreate}>Nouveau plan</Button> : undefined}
      />

      <div style={{ display: 'flex', gap: 8, marginBottom: 20 }}>
        <Button variant={tab === 'plans' ? 'primary' : 'ghost'} size="sm" onClick={() => setTab('plans')}>
          {isSuperAdmin ? 'Plans' : 'Mon abonnement'}
        </Button>
        <Button variant={tab === 'extensions' ? 'primary' : 'ghost'} size="sm" onClick={() => setTab('extensions')}>
          Extensions{isSuperAdmin ? ' & Quotas' : ''}
        </Button>
      </div>

      {tab === 'extensions' ? (
        isSuperAdmin ? <ExtensionsAdminPanel getAccessToken={getAccessToken} /> : <ExtensionsBoutiquePanel getAccessToken={getAccessToken} />
      ) : (
      <>
      <Card>
        <CardBody>
          {plansLoading ? <LoadingState /> : plansError ? <ErrorState message={plansError} onRetry={refreshPlans} /> : (
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: 20 }}>
               {plansList.map((plan) => (
                <div key={plan.id} className="bo-card" style={{ padding: 24, border: '1px solid var(--bo-border)', borderRadius: 12 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, alignItems: 'flex-start' }}>
                    <div>
                      <h3 style={{ margin: '0 0 8px' }}>{plan.name}</h3>
                      {plan.description && <p style={{ fontSize: 14, color: 'var(--bo-text-secondary)', margin: '0 0 16px' }}>{plan.description}</p>}
                    </div>
                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', justifyContent: 'flex-end' }}>
                       <Badge tone={plan.isActive ? 'success' : 'error'}>{plan.isActive ? 'Actif' : 'Inactif'}</Badge>
                      <Badge tone={plan.isVisible ? 'success' : 'warning'}>{plan.isVisible ? 'Publié' : 'Masqué'}</Badge>
                    </div>
                  </div>
                  <div style={{ fontSize: 28, fontWeight: 700, marginBottom: 12 }}>
                    {plan.isFree ? 'Gratuit' : `${plan.priceTnd.toFixed(0)} TND`}
                    <span style={{ fontSize: 14, fontWeight: 400, color: 'var(--bo-text-muted)' }}>/{plan.durationMonths} mois</span>
                  </div>
                  {plan.modules && plan.modules.length > 0 && (
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginBottom: 16 }}>
                       {plan.modules.map((m) => <Badge key={m} tone="neutral">{moduleNames.get(m) ?? m}</Badge>)}
                    </div>
                  )}
                  {isSuperAdmin ? (
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 16 }}>
                      <Button variant="secondary" size="sm" onClick={() => openEdit(plan)}>Modifier</Button>
                      <Button variant="secondary" size="sm" onClick={() => duplicatePlan(plan)}>Dupliquer</Button>
                      <Button variant="ghost" size="sm" onClick={() => updatePlan(plan, { isActive: !plan.isActive })}>{plan.isActive ? 'Désactiver' : 'Activer'}</Button>
                      <Button variant="ghost" size="sm" onClick={() => updatePlan(plan, { isVisible: !plan.isVisible })}>{plan.isVisible ? 'Dépublier' : 'Publier'}</Button>
                      <Button variant="danger" size="sm" onClick={() => setDeleteId(plan.id)}>Supprimer</Button>
                    </div>
                  ) : (
                    <div style={{ marginTop: 16 }}>
                      <Button variant="secondary" size="sm" onClick={() => openSubscriptionChange(plan.id)}>Choisir ce plan</Button>
                    </div>
                  )}
                </div>
               ))}
               <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
             </div>
          )}
        </CardBody>
      </Card>

      {subscriptions.length > 0 && (
        <div style={{ marginTop: 24 }}>
          <h3 style={{ marginBottom: 12 }}>Mon abonnement</h3>
          <Card>
            <CardBody>
              {subscriptions.map((sub) => (
                <div key={sub.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 0', borderBottom: '1px solid var(--bo-border)' }}>
                  <div>
                    <strong>{sub.plan ?? 'N/A'}</strong>
                    <div style={{ fontSize: 13, color: 'var(--bo-text-secondary)' }}>
                      {sub.startDate ? `Du ${new Date(sub.startDate).toLocaleDateString('fr-FR')}` : ''}
                      {sub.endDate ? ` au ${new Date(sub.endDate).toLocaleDateString('fr-FR')}` : ''}
                    </div>
                  </div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <Badge tone={sub.status === 'active' ? 'success' : 'neutral'}>{sub.status}</Badge>
                    <Button variant="secondary" size="sm" onClick={() => openSubscriptionChange()}>Modifier abonnement</Button>
                  </div>
                </div>
              ))}
            </CardBody>
          </Card>
        </div>
      )}

      <Modal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editing ? 'Modifier le plan' : 'Créer un plan'}
        width="620px"
        footer={(
          <>
            <Button variant="secondary" onClick={() => setModalOpen(false)}>Annuler</Button>
            <Button variant="primary" onClick={savePlan} disabled={saving}>{saving ? 'En cours...' : editing ? 'Enregistrer' : 'Créer'}</Button>
          </>
        )}
      >
        <div style={{ display: 'grid', gap: 14 }}>
          <FormField label="Nom" required>
            <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Business 12 mois" />
          </FormField>
          <FormField label="Description">
            <Textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} rows={3} />
          </FormField>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
            <FormField label="Durée (mois)" required>
              <Input type="number" min={1} value={form.durationMonths} onChange={(e) => setForm({ ...form, durationMonths: e.target.value })} />
            </FormField>
            <FormField label="Prix TND" required>
              <Input type="number" min={0} disabled={form.isFree} value={form.priceTnd} onChange={(e) => setForm({ ...form, priceTnd: e.target.value })} />
            </FormField>
          </div>
          <FormField label="Modules inclus" hint="Sélectionnez les modules existants inclus dans ce plan.">
            {modulesLoading ? <LoadingState /> : moduleOptions.length === 0 ? (
              <p style={{ margin: 0, color: 'var(--bo-text-muted)', fontSize: 13 }}>Aucun module disponible.</p>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(210px, 1fr))', gap: 8, maxHeight: 220, overflowY: 'auto', padding: 4 }}>
                {moduleOptions.map((module) => {
                  const checked = form.modules.includes(module.moduleCode);
                  return (
                    <label key={module.moduleCode} style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '8px 10px', border: '1px solid var(--bo-border)', borderRadius: 8, cursor: 'pointer' }}>
                      <input
                        type="checkbox"
                        checked={checked}
                        onChange={() => setForm({ ...form, modules: checked ? form.modules.filter((code) => code !== module.moduleCode) : [...form.modules, module.moduleCode] })}
                      />
                      <span><strong>{module.moduleName}</strong><small style={{ display: 'block', color: 'var(--bo-text-muted)' }}>{module.moduleCode}</small></span>
                    </label>
                  );
                })}
              </div>
            )}
          </FormField>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 16 }}>
            <label><input type="checkbox" checked={form.isFree} onChange={(e) => setForm({ ...form, isFree: e.target.checked, priceTnd: e.target.checked ? '0' : form.priceTnd })} /> Gratuit</label>
            <label><input type="checkbox" checked={form.isActive} onChange={(e) => setForm({ ...form, isActive: e.target.checked })} /> Actif</label>
            <label><input type="checkbox" checked={form.isVisible} onChange={(e) => setForm({ ...form, isVisible: e.target.checked })} /> Publié</label>
          </div>
        </div>
      </Modal>

      <Modal
        isOpen={previewModalOpen}
        onClose={() => setPreviewModalOpen(false)}
        title="Aperçu du changement de plan"
        width="720px"
        footer={(
          <>
            <Button variant="secondary" onClick={() => setPreviewModalOpen(false)}>Annuler</Button>
            <Button variant="primary" onClick={requestSubscriptionChange} disabled={savingSubscriptionRequest || previewLoading || !subscriptionPlanId}>
              {savingSubscriptionRequest ? 'Envoi...' : 'Confirmer la demande'}
            </Button>
          </>
        )}
      >
        {previewLoading ? <LoadingState /> : planPreview ? (
          <div style={{ display: 'grid', gap: 16 }}>
            <p style={{ margin: 0, color: 'var(--bo-text-secondary)' }}>
              {planPreview.currentPlanName ? `Passage de "${planPreview.currentPlanName}" vers ` : ''}
              <strong>{planPreview.newPlanName}</strong>
              {planPreview.isRenewal ? ' (renouvellement)' : ''}
              {' — '}{planPreview.newPlanPriceTnd} {planPreview.currency} / {planPreview.durationMonths} mois
            </p>
            {planPreview.projectedEndDate && (
              <p style={{ margin: 0, fontSize: 13 }}>Date de fin projetée : {new Date(planPreview.projectedEndDate).toLocaleDateString('fr-FR')}</p>
            )}
            <div>
              <h4 style={{ margin: '0 0 8px', fontSize: 13, textTransform: 'uppercase', color: 'var(--bo-text-muted)' }}>Quotas</h4>
              <div style={{ display: 'grid', gap: 6 }}>
                {planPreview.quotaChanges.slice(0, 8).map((q) => (
                  <div key={q.code} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
                    <span>{q.name}</span>
                    <span>{q.currentLimit ?? '∞'} → {q.newLimit ?? '∞'} (utilisé: {q.currentUsage})</span>
                  </div>
                ))}
              </div>
            </div>
            {(planPreview.modulesGained.length > 0 || planPreview.modulesLost.length > 0) && (
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                <div>
                  <h4 style={{ margin: '0 0 8px', fontSize: 13 }}>Modules gagnés</h4>
                  {planPreview.modulesGained.length ? planPreview.modulesGained.map((m) => <Badge key={m} tone="success">{m}</Badge>) : <span style={{ fontSize: 13, color: 'var(--bo-text-muted)' }}>—</span>}
                </div>
                <div>
                  <h4 style={{ margin: '0 0 8px', fontSize: 13 }}>Modules perdus</h4>
                  {planPreview.modulesLost.length ? planPreview.modulesLost.map((m) => <Badge key={m} tone="warning">{m}</Badge>) : <span style={{ fontSize: 13, color: 'var(--bo-text-muted)' }}>—</span>}
                </div>
              </div>
            )}
            {planPreview.extensionCompatibility.length > 0 && (
              <div>
                <h4 style={{ margin: '0 0 8px', fontSize: 13 }}>Compatibilité extensions</h4>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                  {planPreview.extensionCompatibility.map((ext) => (
                    <Badge key={ext.code} tone={ext.compatible ? 'success' : 'warning'}>{ext.name}{ext.compatible ? '' : ' (incompatible)'}</Badge>
                  ))}
                </div>
              </div>
            )}
          </div>
        ) : <EmptyState />}
      </Modal>

      <Modal
        isOpen={subscriptionModalOpen}
        onClose={() => setSubscriptionModalOpen(false)}
        title="Modifier abonnement"
        width="520px"
        footer={(
          <>
            <Button variant="secondary" onClick={() => setSubscriptionModalOpen(false)}>Annuler</Button>
            <Button variant="primary" onClick={requestSubscriptionChange} disabled={savingSubscriptionRequest}>{savingSubscriptionRequest ? 'Envoi...' : 'Envoyer la demande'}</Button>
          </>
        )}
      >
        <div style={{ display: 'grid', gap: 14 }}>
          <BoutiqueFormSelect value={subscriptionBoutiqueId} onChange={setSubscriptionBoutiqueId} />
          <FormField label="Nouveau plan" required>
            <Select required value={subscriptionPlanId} onChange={(event) => setSubscriptionPlanId(event.target.value)}>
              <option value="">Sélectionner un plan</option>
               {planOptions.filter((plan) => plan.isActive && plan.isVisible).map((plan) => (
                <option key={plan.id} value={plan.id}>{plan.name} - {plan.isFree ? 'Gratuit' : `${plan.priceTnd} TND`}</option>
              ))}
            </Select>
          </FormField>
        </div>
      </Modal>

      <ConfirmDialog
        isOpen={deleteId !== null}
        title="Supprimer le plan"
        message="Cette action supprime le plan d'abonnement. Elle peut échouer si le plan est déjà lié à des abonnements."
        confirmLabel="Supprimer"
        onConfirm={deletePlan}
        onClose={() => setDeleteId(null)}
        danger
      />
      </>
      )}
    </div>
  );
}
