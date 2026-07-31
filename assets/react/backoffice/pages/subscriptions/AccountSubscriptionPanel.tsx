import { useCallback, useMemo, useState } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardBody, CardHeader } from '../../components/Card';
import { Button } from '../../components/Button';
import { Badge } from '../../components/Badge';
import { LoadingState, ErrorState, EmptyState } from '../../components/States';
import { useNotification } from '../../hooks/useNotification';
import { Modal } from '../../components/Modal';
import { FormField, Select } from '../../components/FormField';

type AccountSubscription = {
  isActive: boolean;
  id?: string | null;
  planId?: string | null;
  planName?: string | null;
  planDescription?: string | null;
  planDurationMonths: number;
  planPriceTnd: number;
  planRenewalPriceTnd?: number | null;
  effectivePlanRenewalPriceTnd?: number;
  extensionsRenewalPriceTnd?: number;
  currency: string;
  startDate?: string | null;
  endDate?: string | null;
  daysRemaining?: number | null;
  status?: string | null;
  renewalPriceTnd: number;
  extensions: Array<{
    id: string;
    code: string;
    name: string;
    targetCode?: string | null;
    value?: number | null;
    priceTnd: number;
    durationMonths?: number | null;
    expiresAt?: string | null;
    isActive: boolean;
  }>;
  maxBoutiques?: number | null;
  publishedBoutiques: number;
  isUnlimited: boolean;
};

type ChangePreview = {
  currentPlanId?: string | null;
  currentPlanName?: string | null;
  newPlanId: string;
  newPlanName: string;
  newPlanPriceTnd: number;
  newPlanRenewalPriceTnd?: number | null;
  effectivePlanRenewalPriceTnd?: number;
  extensionsRenewalPriceTnd?: number;
  renewalPriceTnd: number;
  currency: string;
  durationMonths: number;
  isRenewal: boolean;
  projectedEndDate?: string | null;
  newMaxBoutiques?: number | null;
  newIsUnlimited: boolean;
  publishedBoutiques: number;
  wouldExceedQuota?: boolean;
};

type PlanOption = {
  id: string;
  name: string;
  priceTnd: number;
  renewalPriceTnd?: number | null;
  effectiveRenewalPriceTnd?: number;
  durationMonths: number;
  isFree: boolean;
  isActive: boolean;
  isVisible: boolean;
};

type ExtensionOption = {
  id: string;
  code: string;
  name: string;
  description?: string | null;
  priceTnd: number;
  durationMonths?: number | null;
  isFree: boolean;
  isPermanent: boolean;
  requiresValidation: boolean;
  isActive: boolean;
  alreadyActive: boolean;
};

function formatPrice(millimes: number): string {
  return `${(millimes / 1000).toFixed(millimes % 1000 === 0 ? 0 : 2)} TND`;
}

export function AccountSubscriptionPanel({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();

  const [modalOpen, setModalOpen] = useState(false);
  const [previewModalOpen, setPreviewModalOpen] = useState(false);
  const [selectedPlanId, setSelectedPlanId] = useState('');
  const [selectedExtensionIds, setSelectedExtensionIds] = useState<string[]>([]);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [preview, setPreview] = useState<ChangePreview | null>(null);

  const fetchSubscription = useCallback(() => api.get<AccountSubscription>('/account/subscription'), [api]);
  const fetchPlans = useCallback(() => api.getCollection<PlanOption>('/boutique/subscription-plans?itemsPerPage=100'), [api]);
  const fetchExtensions = useCallback(() => api.getCollection<ExtensionOption>('/extensions/available?itemsPerPage=100'), [api]);

  const { data: subscription, isLoading: subscriptionLoading, error: subscriptionError, refresh: refreshSubscription } = useApiData(fetchSubscription, []);
  const { data: plansData, isLoading: plansLoading } = useApiData(fetchPlans, []);
  const { data: extensionsData } = useApiData(fetchExtensions, []);

  const plans = useMemo(() => (plansData?.member ?? []).filter((p) => p.isActive && p.isVisible), [plansData]);
  const extensions = useMemo(() => (extensionsData?.member ?? []).filter((e) => !e.requiresValidation && e.isActive && !e.alreadyActive), [extensionsData]);

  const openChangeModal = () => {
    setSelectedPlanId(subscription?.planId ?? '');
    setSelectedExtensionIds([]);
    setPreview(null);
    setModalOpen(true);
  };

  const loadPreview = async (planId: string) => {
    setPreviewLoading(true);
    setPreviewModalOpen(true);
    setPreview(null);
    try {
      const result = await api.get<ChangePreview>(`/account/subscription/change-preview?planId=${encodeURIComponent(planId)}`);
      setPreview(result);
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Impossible de charger l\'aperçu', 'error');
      setPreviewModalOpen(false);
    } finally {
      setPreviewLoading(false);
    }
  };

  const toggleExtension = (id: string) => {
    setSelectedExtensionIds((current) => (current.includes(id) ? current.filter((ext) => ext !== id) : [...current, id]));
  };

  const confirmPreview = async () => {
    setPreviewLoading(true);
    try {
      const result = await api.get<ChangePreview>(`/account/subscription/change-preview?planId=${encodeURIComponent(selectedPlanId)}`);
      setPreview(result);
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Impossible de charger l\'aperçu', 'error');
    } finally {
      setPreviewLoading(false);
    }
  };

  const saveSubscription = async () => {
    if (!selectedPlanId) {
      showNotice('Sélectionnez un plan.', 'error');
      return;
    }
    setSaving(true);
    try {
      await api.post('/account/subscription', { planId: selectedPlanId, extensionIds: selectedExtensionIds });
      showNotice('Abonnement mis à jour', 'success');
      setModalOpen(false);
      setPreviewModalOpen(false);
      refreshSubscription();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la mise à jour', 'error');
    } finally {
      setSaving(false);
    }
  };

  const renewSubscription = async () => {
    setSaving(true);
    try {
      await api.post('/account/subscription/renew', {});
      showNotice('Abonnement renouvelé', 'success');
      refreshSubscription();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors du renouvellement', 'error');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div style={{ display: 'grid', gap: 20 }}>
      <Card>
        <CardHeader>
          <span>Mon abonnement compte</span>
          <p style={{ margin: '4px 0 0', fontSize: 13, fontWeight: 400, color: 'var(--bo-text-secondary)' }}>
            Abonnement lié à votre compte administrateur : détermine le nombre de boutiques que vous pouvez gérer.
          </p>
        </CardHeader>
        <CardBody>
          {subscriptionLoading ? <LoadingState /> : subscriptionError ? <ErrorState message={subscriptionError} onRetry={refreshSubscription} /> : subscription ? (
            <div style={{ display: 'grid', gridTemplateColumns: '1.2fr 1fr', gap: 32 }}>
              <div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                  <h3 style={{ margin: 0 }}>{subscription.planName ?? 'Aucun plan actif'}</h3>
                  <Badge tone={subscription.isActive ? 'success' : 'warning'}>{subscription.isActive ? 'Actif' : 'Sans abonnement'}</Badge>
                  <Button variant="secondary" size="sm" onClick={openChangeModal}>Changer de plan</Button>
                  {subscription.isActive && (
                    <Button variant="secondary" size="sm" onClick={renewSubscription} disabled={saving}>
                      {saving ? 'En cours...' : 'Renouveler'}
                    </Button>
                  )}
                </div>
                {subscription.planDescription && (
                  <p style={{ fontSize: 13, color: 'var(--bo-text-secondary)', margin: '0 0 8px' }}>{subscription.planDescription}</p>
                )}
                <p style={{ fontSize: 13, color: 'var(--bo-text-secondary)', margin: '0 0 16px' }}>
                  {subscription.planDurationMonths > 0
                    ? `${formatPrice(subscription.planPriceTnd)} / ${subscription.planDurationMonths} mois`
                    : formatPrice(subscription.planPriceTnd)}
                  {subscription.endDate ? ` — expire le ${new Date(subscription.endDate).toLocaleDateString('fr-FR')}` : ''}
                  {typeof subscription.daysRemaining === 'number' ? ` (${subscription.daysRemaining} jour(s) restant(s))` : ''}
                </p>
                <h4 style={{ fontSize: 13, textTransform: 'uppercase', color: 'var(--bo-text-muted)', marginBottom: 8 }}>Limite de boutiques publiées</h4>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 12 }}>
                  <span>Boutiques publiées</span>
                  <span style={{ color: subscription.maxBoutiques !== null && subscription.maxBoutiques !== undefined && subscription.publishedBoutiques >= subscription.maxBoutiques ? 'var(--bo-error)' : 'var(--bo-text-secondary)' }}>
                    {subscription.publishedBoutiques}{subscription.isUnlimited ? ' / illimité' : ` / ${subscription.maxBoutiques}`}
                  </span>
                </div>
                {subscription.maxBoutiques !== null && subscription.maxBoutiques !== undefined && subscription.maxBoutiques > 0 && (
                  <div style={{ height: 6, background: 'var(--bo-border)', borderRadius: 4, overflow: 'hidden', marginBottom: 16 }}>
                    <div
                      style={{
                        height: '100%',
                        width: `${Math.min(100, Math.round((subscription.publishedBoutiques / subscription.maxBoutiques) * 100))}%`,
                        background: subscription.publishedBoutiques >= subscription.maxBoutiques ? 'var(--bo-error)' : 'var(--bo-primary)',
                      }}
                    />
                  </div>
                )}
                <h4 style={{ fontSize: 13, textTransform: 'uppercase', color: 'var(--bo-text-muted)', marginBottom: 8 }}>Extensions incluses</h4>
                {subscription.extensions.length === 0 ? <EmptyState /> : (
                  <div style={{ display: 'grid', gap: 8 }}>
                    {subscription.extensions.map((ext) => (
                      <div key={ext.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8, fontSize: 13, padding: '8px 10px', border: '1px solid var(--bo-border)', borderRadius: 8 }}>
                        <span><strong>{ext.name}</strong>{ext.targetCode ? ` (+${ext.value ?? ''} ${ext.targetCode})` : ''}</span>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                          <span style={{ color: 'var(--bo-text-muted)' }}>{ext.expiresAt ? `jusqu'au ${new Date(ext.expiresAt).toLocaleDateString('fr-FR')}` : 'permanent'}</span>
                          <Badge tone={ext.isActive ? 'success' : 'error'}>{ext.isActive ? 'Active' : 'Expirée'}</Badge>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
                {subscription.isActive && (
                  <div style={{ fontSize: 13, color: 'var(--bo-text-secondary)', marginTop: 16, display: 'grid', gap: 4 }}>
                    <div>Prix plan (souscription) : <strong>{formatPrice(subscription.planPriceTnd)}</strong></div>
                    <div>
                      Prix plan (renouvellement) :{' '}
                      <strong>{formatPrice(subscription.effectivePlanRenewalPriceTnd ?? subscription.planPriceTnd)}</strong>
                      {subscription.planRenewalPriceTnd === null || subscription.planRenewalPriceTnd === undefined
                        ? ' (prix principal)'
                        : ''}
                    </div>
                    <div>Extensions actives : <strong>{formatPrice(subscription.extensionsRenewalPriceTnd ?? 0)}</strong></div>
                    <div>Total renouvellement : <strong>{formatPrice(subscription.renewalPriceTnd)}</strong></div>
                  </div>
                )}
              </div>
              <div>
                <h4 style={{ fontSize: 13, textTransform: 'uppercase', color: 'var(--bo-text-muted)', marginBottom: 8 }}>Plan actuel</h4>
                {subscription.planId ? <p style={{ fontSize: 13 }}>{subscription.planName ?? 'Plan'}</p> : <p style={{ fontSize: 13, color: 'var(--bo-text-muted)' }}>Aucun plan souscrit.</p>}
                <div style={{ marginTop: 20 }}>
                  <h4 style={{ fontSize: 13, textTransform: 'uppercase', color: 'var(--bo-text-muted)', marginBottom: 8 }}>Plans disponibles</h4>
                  {plansLoading ? <LoadingState /> : (
                    <div style={{ display: 'grid', gap: 8 }}>
                      {plans.map((plan) => (
                        <div key={plan.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8, fontSize: 13, padding: '8px 10px', border: '1px solid var(--bo-border)', borderRadius: 8 }}>
                          <span><strong>{plan.name}</strong></span>
                          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span style={{ color: 'var(--bo-text-secondary)' }}>
                              {plan.isFree ? 'Gratuit' : formatPrice(plan.priceTnd)}
                              {!plan.isFree && (plan.renewalPriceTnd !== null && plan.renewalPriceTnd !== undefined)
                                ? ` → renouv. ${formatPrice(plan.effectiveRenewalPriceTnd ?? plan.renewalPriceTnd)}`
                                : ''}
                              {plan.durationMonths > 0 ? ` / ${plan.durationMonths} mois` : ''}
                            </span>
                            {plan.id === subscription.planId ? <Badge tone="success">Actuel</Badge> : (
                              <Button variant="ghost" size="sm" onClick={() => loadPreview(plan.id)}>Voir</Button>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            </div>
          ) : <EmptyState />}
        </CardBody>
      </Card>

      <Modal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        title="Changer de plan compte"
        width="640px"
        footer={(
          <>
            <Button variant="secondary" onClick={() => setModalOpen(false)}>Annuler</Button>
            <Button variant="primary" onClick={confirmPreview} disabled={!selectedPlanId || previewLoading}>
              {previewLoading ? 'En cours...' : 'Aperçu'}
            </Button>
          </>
        )}
      >
        <div style={{ display: 'grid', gap: 14 }}>
          <FormField label="Nouveau plan" required>
            <Select required value={selectedPlanId} onChange={(event) => setSelectedPlanId(event.target.value)}>
              <option value="">Sélectionner un plan</option>
              {plans.map((plan) => (
                <option key={plan.id} value={plan.id}>
                  {plan.name} — {plan.isFree ? 'Gratuit' : formatPrice(plan.priceTnd)}
                  {!plan.isFree && plan.renewalPriceTnd != null
                    ? ` → renouv. ${formatPrice(plan.effectiveRenewalPriceTnd ?? plan.renewalPriceTnd)}`
                    : ''}
                  {plan.durationMonths > 0 ? ` / ${plan.durationMonths} mois` : ''}
                </option>
              ))}
            </Select>
          </FormField>
          {extensions.length > 0 && (
            <FormField label="Extensions auto-activables" hint="Extensions activées immédiatement avec votre abonnement (comptent dans le prix de renouvellement).">
              <div style={{ display: 'grid', gap: 8 }}>
                {extensions.map((ext) => {
                  const checked = selectedExtensionIds.includes(ext.id);
                  return (
                    <label key={ext.id} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '8px 10px', border: '1px solid var(--bo-border)', borderRadius: 8, cursor: 'pointer' }}>
                      <input type="checkbox" checked={checked} onChange={() => toggleExtension(ext.id)} />
                      <span style={{ fontSize: 13 }}><strong>{ext.name}</strong>
                        <small style={{ display: 'block', color: 'var(--bo-text-muted)' }}>
                          {ext.isFree ? 'Gratuit' : formatPrice(ext.priceTnd)}
                          {ext.durationMonths ? ` / ${ext.durationMonths} mois` : ''}
                          {ext.description ? ` — ${ext.description}` : ''}
                        </small>
                      </span>
                    </label>
                  );
                })}
              </div>
            </FormField>
          )}
        </div>
      </Modal>

      <Modal
        isOpen={previewModalOpen}
        onClose={() => setPreviewModalOpen(false)}
        title="Aperçu du changement de plan"
        width="640px"
        footer={(
          <>
            <Button variant="secondary" onClick={() => setPreviewModalOpen(false)}>Annuler</Button>
            <Button variant="primary" onClick={saveSubscription} disabled={saving || previewLoading || !selectedPlanId}>
              {saving ? 'En cours...' : 'Confirmer'}
            </Button>
          </>
        )}
      >
        {previewLoading ? <LoadingState /> : preview ? (
          <div style={{ display: 'grid', gap: 14 }}>
            <p style={{ margin: 0, fontSize: 14 }}>
              {preview.currentPlanName ? `Passage de "${preview.currentPlanName}" vers ` : 'Souscription à '}
              <strong>{preview.newPlanName}</strong>
              {preview.isRenewal ? ' (renouvellement)' : ''}
              {' — '}{formatPrice(preview.newPlanPriceTnd)} / {preview.durationMonths} mois
            </p>
            {preview.projectedEndDate && (
              <p style={{ margin: 0, fontSize: 13 }}>Date de fin projetée : {new Date(preview.projectedEndDate).toLocaleDateString('fr-FR')}</p>
            )}
            <p style={{ margin: 0, fontSize: 13 }}>
              Boutiques autorisées : <strong>{preview.newIsUnlimited ? 'illimité' : preview.newMaxBoutiques}</strong>
              {' — '}publiées : <strong>{preview.publishedBoutiques}</strong>
              {preview.wouldExceedQuota ? (
                <span style={{ color: 'var(--bo-error)', display: 'block', marginTop: 8 }}>
                  Ce plan est insuffisant pour vos boutiques déjà publiées. Dépubliez-en avant de changer.
                </span>
              ) : null}
            </p>
            <p style={{ margin: 0, fontSize: 13 }}>
              Prix souscription : <strong>{formatPrice(preview.newPlanPriceTnd)}</strong>
              {' — '}renouvellement plan :{' '}
              <strong>{formatPrice(preview.effectivePlanRenewalPriceTnd ?? preview.newPlanPriceTnd)}</strong>
              {preview.newPlanRenewalPriceTnd === null || preview.newPlanRenewalPriceTnd === undefined
                ? ' (prix principal)'
                : ''}
            </p>
            <p style={{ margin: 0, fontSize: 13 }}>
              Extensions : <strong>{formatPrice(preview.extensionsRenewalPriceTnd ?? 0)}</strong>
              {' — '}total renouvellement : <strong>{formatPrice(preview.renewalPriceTnd)}</strong>
            </p>
          </div>
        ) : <EmptyState />}
      </Modal>
    </div>
  );
}
