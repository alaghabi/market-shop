import { useCallback, useState } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardBody, CardHeader } from '../../components/Card';
import { Button } from '../../components/Button';
import { Badge, statusBadge } from '../../components/Badge';
import { LoadingState, ErrorState, EmptyState } from '../../components/States';
import { Modal } from '../../components/Modal';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { FormField, Input, Select } from '../../components/FormField';
import { useNotification } from '../../hooks/useNotification';
import { Pagination } from '../../components/Pagination';

type CredentialField = {
  key: 'login' | 'password' | 'apiKey' | 'token' | 'secret' | 'customBaseUrl';
  label: string;
  type?: string;
  required?: boolean;
  hint?: string | null;
};

type DeliveryCompany = {
  id: string;
  name: string;
  slug: string;
  provider: string;
  authType?: string;
  authConfig?: {
    credentialFields?: CredentialField[];
  };
  credentialFields?: CredentialField[];
  logoUrl?: string | null;
  description?: string | null;
  isActive: boolean;
};

type DeliveryAccount = {
  id: string;
  deliveryCompanyId: string;
  deliveryCompanyName: string;
  isVerified: boolean;
  verifiedAt?: string | null;
  lastError?: string | null;
  isActive: boolean;
  isDefault: boolean;
  hasApiKey: boolean;
  hasToken: boolean;
  hasSecret: boolean;
  customBaseUrl?: string | null;
  createdAt?: string | null;
};

type AccountForm = {
  deliveryCompanyId: string;
  login: string;
  password: string;
  apiKey: string;
  token: string;
  secret: string;
  customBaseUrl: string;
};

type Shipment = {
  id: string;
  orderId: string;
  deliveryCompanyName: string;
  status: string;
  trackingNumber?: string | null;
  labelUrl?: string | null;
  costCents?: number | null;
  errorMessage?: string | null;
  createdAt: string;
  sentAt?: string | null;
};

const emptyAccountForm: AccountForm = {
  deliveryCompanyId: '', login: '', password: '', apiKey: '', token: '', secret: '', customBaseUrl: '',
};

const FALLBACK_FIELDS: CredentialField[] = [
  { key: 'login', label: 'Identifiant', type: 'text', required: false },
  { key: 'password', label: 'Mot de passe', type: 'password', required: false },
  { key: 'apiKey', label: 'Clé API', type: 'password', required: false },
  { key: 'token', label: 'Token', type: 'password', required: false },
  { key: 'secret', label: 'Secret', type: 'password', required: false },
  { key: 'customBaseUrl', label: 'URL personnalisée', type: 'text', required: false, hint: 'Optionnel' },
];

function resolveCredentialFields(company: DeliveryCompany | undefined): CredentialField[] {
  const configured = company?.credentialFields ?? company?.authConfig?.credentialFields;
  if (Array.isArray(configured) && configured.length > 0) {
    return configured.filter((field): field is CredentialField => Boolean(field?.key && field?.label));
  }

  switch (company?.authType) {
    case 'bearer':
      return [{ key: 'token', label: 'Jeton API', type: 'password', required: true }];
    case 'api_key':
      return [{ key: 'apiKey', label: 'Clé API', type: 'password', required: true }];
    case 'basic':
      return [
        { key: 'login', label: 'Identifiant', type: 'text', required: true },
        { key: 'password', label: 'Mot de passe', type: 'password', required: true },
      ];
    default:
      return FALLBACK_FIELDS;
  }
}

type Tab = 'accounts' | 'shipments';
const PAGE_SIZE = 20;

export function DeliveryBoutiquePanel({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const [tab, setTab] = useState<Tab>('accounts');

  const [accountModalOpen, setAccountModalOpen] = useState(false);
  const [accountForm, setAccountForm] = useState<AccountForm>(emptyAccountForm);
  const [savingAccount, setSavingAccount] = useState(false);
  const [deleteAccountId, setDeleteAccountId] = useState<string | null>(null);
  const [verifyingId, setVerifyingId] = useState<string | null>(null);

  const [shipmentModalOpen, setShipmentModalOpen] = useState(false);
  const [shipmentOrderId, setShipmentOrderId] = useState('');
  const [shipmentAccountId, setShipmentAccountId] = useState('');
  const [creatingShipment, setCreatingShipment] = useState(false);
  const [busyShipmentId, setBusyShipmentId] = useState<string | null>(null);
  const [accountPage, setAccountPage] = useState(1);
  const [shipmentPage, setShipmentPage] = useState(1);

  const fetchCompanies = useCallback(() => api.getCollection<DeliveryCompany>('/delivery/companies?itemsPerPage=100'), [api]);
  const fetchAccounts = useCallback(() => api.getCollection<DeliveryAccount>(`/delivery-accounts?page=${accountPage}&itemsPerPage=${PAGE_SIZE}`), [api, accountPage]);
  const fetchShipments = useCallback(() => api.getCollection<Shipment>(`/delivery/shipments?page=${shipmentPage}&itemsPerPage=${PAGE_SIZE}`), [api, shipmentPage]);

  const { data: companiesData, isLoading: companiesLoading } = useApiData(fetchCompanies, [tab]);
  const { data: accountsData, isLoading: accountsLoading, error: accountsError, refresh: refreshAccounts } = useApiData(fetchAccounts, [tab, accountPage]);
  const { data: shipmentsData, isLoading: shipmentsLoading, error: shipmentsError, refresh: refreshShipments } = useApiData(fetchShipments, [tab, shipmentPage]);

  const companies = companiesData?.member ?? [];
  const accounts = accountsData?.member ?? [];
  const shipments = shipmentsData?.member ?? [];
  const accountTotalPages = Math.max(1, Math.ceil((accountsData?.totalItems ?? 0) / PAGE_SIZE));
  const shipmentTotalPages = Math.max(1, Math.ceil((shipmentsData?.totalItems ?? 0) / PAGE_SIZE));

  const availableCompanies = companies.filter((c) => c.isActive && !accounts.some((a) => a.deliveryCompanyId === c.id));
  const selectedCompany = companies.find((company) => company.id === accountForm.deliveryCompanyId);
  const credentialFields = resolveCredentialFields(selectedCompany);

  const openAddAccount = () => {
    setAccountForm(emptyAccountForm);
    setAccountModalOpen(true);
  };

  const saveAccount = async () => {
    if (!accountForm.deliveryCompanyId) {
      showNotice('Sélectionnez une société de livraison.', 'error');
      return;
    }

    for (const field of credentialFields) {
      if (!field.required) continue;
      const value = accountForm[field.key]?.trim() ?? '';
      if (!value) {
        showNotice(`Le champ « ${field.label} » est obligatoire.`, 'error');
        return;
      }
    }

    setSavingAccount(true);
    try {
      const payload: Record<string, string | null> = {
        deliveryCompanyId: accountForm.deliveryCompanyId,
      };
      for (const field of credentialFields) {
        const value = accountForm[field.key].trim();
        payload[field.key] = value || null;
      }
      await api.post('/delivery-accounts', payload);
      showNotice('Compte transporteur ajouté', 'success');
      setAccountModalOpen(false);
      refreshAccounts();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de l\'ajout', 'error');
    } finally {
      setSavingAccount(false);
    }
  };

  const verifyAccount = async (id: string) => {
    setVerifyingId(id);
    try {
      await api.post(`/delivery-accounts/${id}/verify`);
      showNotice('Connexion testée avec succès', 'success');
      refreshAccounts();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Échec du test de connexion', 'error');
      refreshAccounts();
    } finally {
      setVerifyingId(null);
    }
  };

  const setDefaultAccount = async (id: string) => {
    try {
      await api.post(`/delivery-accounts/${id}/set-default`);
      showNotice('Transporteur par défaut mis à jour', 'success');
      refreshAccounts();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur', 'error');
    }
  };

  const toggleAccountActive = async (account: DeliveryAccount) => {
    try {
      await api.patch(`/delivery-accounts/${account.id}`, { isActive: !account.isActive });
      showNotice(account.isActive ? 'Compte désactivé' : 'Compte activé', 'success');
      refreshAccounts();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur', 'error');
    }
  };

  const deleteAccount = async () => {
    if (!deleteAccountId) return;
    try {
      await api.delete(`/delivery-accounts/${deleteAccountId}`);
      showNotice('Compte supprimé', 'success');
      setDeleteAccountId(null);
      refreshAccounts();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la suppression', 'error');
    }
  };

  const openCreateShipment = () => {
    setShipmentOrderId('');
    setShipmentAccountId('');
    setShipmentModalOpen(true);
  };

  const createShipment = async () => {
    if (!shipmentOrderId.trim()) {
      showNotice('L\'identifiant de la commande est obligatoire.', 'error');
      return;
    }
    setCreatingShipment(true);
    try {
      await api.post('/delivery/shipments/queue', { orderId: shipmentOrderId.trim(), accountId: shipmentAccountId || null });
      showNotice('Expédition mise en file de traitement', 'success');
      setShipmentModalOpen(false);
      refreshShipments();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la création de l\'expédition', 'error');
    } finally {
      setCreatingShipment(false);
    }
  };

  const trackShipment = async (id: string) => {
    setBusyShipmentId(id);
    try {
      await api.post(`/delivery/shipments/${id}/track`);
      showNotice('Statut mis à jour', 'success');
      refreshShipments();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors du suivi', 'error');
    } finally {
      setBusyShipmentId(null);
    }
  };

  const cancelShipment = async (id: string) => {
    setBusyShipmentId(id);
    try {
      await api.post(`/delivery/shipments/${id}/cancel`);
      showNotice('Expédition annulée', 'success');
      refreshShipments();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de l\'annulation', 'error');
    } finally {
      setBusyShipmentId(null);
    }
  };

  const fetchLabel = async (id: string) => {
    setBusyShipmentId(id);
    try {
      await api.post(`/delivery/shipments/${id}/label`);
      showNotice('Étiquette récupérée', 'success');
      refreshShipments();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la récupération de l\'étiquette', 'error');
    } finally {
      setBusyShipmentId(null);
    }
  };

  return (
    <div style={{ display: 'grid', gap: 20 }}>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
        <Button variant={tab === 'accounts' ? 'primary' : 'ghost'} size="sm" onClick={() => setTab('accounts')}>Transporteurs</Button>
        <Button variant={tab === 'shipments' ? 'primary' : 'ghost'} size="sm" onClick={() => setTab('shipments')}>Expéditions</Button>
      </div>

      {tab === 'accounts' && (
        <Card>
          <CardHeader>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <span>Mes comptes transporteurs</span>
              <Button variant="primary" size="sm" onClick={openAddAccount} disabled={companiesLoading}>Ajouter un compte</Button>
            </div>
          </CardHeader>
          <CardBody>
            {accountsLoading ? <LoadingState /> : accountsError ? <ErrorState message={accountsError} onRetry={refreshAccounts} /> : accounts.length === 0 ? (
              <EmptyState title="Aucun transporteur configuré" message="Ajoutez un compte pour commencer à expédier vos commandes." action={{ label: 'Ajouter un compte', onClick: openAddAccount }} />
            ) : (
              <div style={{ display: 'grid', gap: 8 }}>
                 {accounts.map((account) => (
                  <div key={account.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 12px', border: '1px solid var(--bo-border)', borderRadius: 8, flexWrap: 'wrap', gap: 8 }}>
                    <div>
                      <strong>{account.deliveryCompanyName}</strong>
                      {account.isDefault && <Badge tone="primary">Par défaut</Badge>}
                      <div style={{ fontSize: 12, color: 'var(--bo-text-secondary)', marginTop: 4 }}>
                        {account.hasApiKey && 'API Key · '}{account.hasToken && 'Token · '}{account.hasSecret && 'Secret · '}
                        {account.customBaseUrl ? `URL: ${account.customBaseUrl}` : ''}
                      </div>
                      {account.lastError && <div style={{ fontSize: 12, color: 'var(--bo-error)', marginTop: 4 }}>{account.lastError}</div>}
                    </div>
                    <div style={{ display: 'flex', gap: 6, alignItems: 'center', flexWrap: 'wrap' }}>
                      <Badge tone={account.isVerified ? 'success' : 'warning'}>{account.isVerified ? 'Vérifié' : 'Non vérifié'}</Badge>
                      <Badge tone={account.isActive ? 'success' : 'neutral'}>{account.isActive ? 'Actif' : 'Inactif'}</Badge>
                      <Button variant="secondary" size="sm" onClick={() => verifyAccount(account.id)} disabled={verifyingId === account.id}>
                        {verifyingId === account.id ? 'Test...' : 'Tester connexion'}
                      </Button>
                      {!account.isDefault && (
                        <Button variant="ghost" size="sm" onClick={() => setDefaultAccount(account.id)}>Définir par défaut</Button>
                      )}
                      <Button variant="ghost" size="sm" onClick={() => toggleAccountActive(account)}>{account.isActive ? 'Désactiver' : 'Activer'}</Button>
                      <Button variant="danger" size="sm" onClick={() => setDeleteAccountId(account.id)}>Suppr.</Button>
                    </div>
                  </div>
                 ))}
                 <Pagination page={accountPage} totalPages={accountTotalPages} onPageChange={setAccountPage} />
               </div>
            )}
          </CardBody>
        </Card>
      )}

      {tab === 'shipments' && (
        <Card>
          <CardHeader>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <span>Expéditions</span>
              <Button variant="primary" size="sm" onClick={openCreateShipment}>Créer une expédition</Button>
            </div>
          </CardHeader>
          <CardBody>
            {shipmentsLoading ? <LoadingState /> : shipmentsError ? <ErrorState message={shipmentsError} onRetry={refreshShipments} /> : shipments.length === 0 ? <EmptyState /> : (
              <div style={{ display: 'grid', gap: 8 }}>
                 {shipments.map((shipment) => {
                  const badge = statusBadge(shipment.status);
                  const busy = busyShipmentId === shipment.id;
                  const isFinal = ['delivered', 'cancelled', 'return'].includes(shipment.status);
                  return (
                    <div key={shipment.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 12px', border: '1px solid var(--bo-border)', borderRadius: 8, flexWrap: 'wrap', gap: 8 }}>
                      <div>
                        <strong>Commande #{shipment.orderId.slice(0, 8)}</strong> — {shipment.deliveryCompanyName}
                        <div style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>
                          {shipment.trackingNumber ? `Suivi: ${shipment.trackingNumber}` : 'Pas encore de numéro de suivi'}
                          {shipment.costCents != null ? ` — ${(shipment.costCents / 100).toFixed(2)}` : ''}
                          {' — '}{new Date(shipment.createdAt).toLocaleString('fr-FR')}
                        </div>
                        {shipment.errorMessage && <div style={{ fontSize: 12, color: 'var(--bo-error)' }}>{shipment.errorMessage}</div>}
                      </div>
                      <div style={{ display: 'flex', gap: 6, alignItems: 'center', flexWrap: 'wrap' }}>
                        <Badge tone={badge.tone}>{badge.label}</Badge>
                        {!isFinal && (
                          <>
                            <Button variant="secondary" size="sm" onClick={() => trackShipment(shipment.id)} disabled={busy}>Suivre</Button>
                            <Button variant="ghost" size="sm" onClick={() => fetchLabel(shipment.id)} disabled={busy}>Étiquette</Button>
                            <Button variant="danger" size="sm" onClick={() => cancelShipment(shipment.id)} disabled={busy}>Annuler</Button>
                          </>
                        )}
                        {shipment.labelUrl && (
                          <a href={shipment.labelUrl} target="_blank" rel="noreferrer" className="bo-btn bo-btn-ghost bo-btn-sm">Voir étiquette</a>
                        )}
                      </div>
                    </div>
                  );
                 })}
                 <Pagination page={shipmentPage} totalPages={shipmentTotalPages} onPageChange={setShipmentPage} />
               </div>
            )}
          </CardBody>
        </Card>
      )}

      <Modal
        isOpen={accountModalOpen}
        onClose={() => setAccountModalOpen(false)}
        title="Ajouter un compte transporteur"
        width="560px"
        footer={(
          <>
            <Button variant="secondary" onClick={() => setAccountModalOpen(false)}>Annuler</Button>
            <Button variant="primary" onClick={saveAccount} disabled={savingAccount}>{savingAccount ? 'En cours...' : 'Ajouter'}</Button>
          </>
        )}
      >
        <div style={{ display: 'grid', gap: 14 }}>
          <FormField label="Société de livraison" required>
            <Select
              value={accountForm.deliveryCompanyId}
              onChange={(e) => setAccountForm({ ...emptyAccountForm, deliveryCompanyId: e.target.value })}
            >
              <option value="">Sélectionner une société</option>
              {availableCompanies.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}{c.description ? ` — ${c.description}` : ''}
                </option>
              ))}
            </Select>
          </FormField>
          {selectedCompany?.description && (
            <p style={{ margin: 0, fontSize: 13, color: 'var(--bo-text-secondary)' }}>{selectedCompany.description}</p>
          )}
          {!accountForm.deliveryCompanyId ? (
            <p style={{ margin: 0, fontSize: 13, color: 'var(--bo-text-muted)' }}>
              Choisissez un transporteur pour afficher les champs d&apos;authentification requis.
            </p>
          ) : (
            credentialFields.map((field) => (
              <FormField key={field.key} label={field.label} required={field.required} hint={field.hint ?? undefined}>
                <Input
                  type={field.type === 'password' ? 'password' : 'text'}
                  value={accountForm[field.key]}
                  onChange={(e) => setAccountForm({ ...accountForm, [field.key]: e.target.value })}
                  autoComplete="off"
                />
              </FormField>
            ))
          )}
        </div>
      </Modal>

      <Modal
        isOpen={shipmentModalOpen}
        onClose={() => setShipmentModalOpen(false)}
        title="Créer une expédition"
        width="480px"
        footer={(
          <>
            <Button variant="secondary" onClick={() => setShipmentModalOpen(false)}>Annuler</Button>
            <Button variant="primary" onClick={createShipment} disabled={creatingShipment}>{creatingShipment ? 'En cours...' : 'Créer'}</Button>
          </>
        )}
      >
        <div style={{ display: 'grid', gap: 14 }}>
          <FormField label="Identifiant de la commande" required hint="UUID de la commande à expédier">
            <Input value={shipmentOrderId} onChange={(e) => setShipmentOrderId(e.target.value)} />
          </FormField>
          <FormField label="Compte transporteur" hint="Laisser vide pour utiliser le transporteur par défaut">
            <Select value={shipmentAccountId} onChange={(e) => setShipmentAccountId(e.target.value)}>
              <option value="">Transporteur par défaut</option>
              {accounts.filter((a) => a.isActive).map((a) => <option key={a.id} value={a.id}>{a.deliveryCompanyName}</option>)}
            </Select>
          </FormField>
        </div>
      </Modal>

      <ConfirmDialog
        isOpen={deleteAccountId !== null}
        title="Supprimer le compte"
        message="Ce compte transporteur sera définitivement supprimé."
        confirmLabel="Supprimer"
        onConfirm={deleteAccount}
        onClose={() => setDeleteAccountId(null)}
        danger
      />
    </div>
  );
}
