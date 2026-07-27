import { useCallback, useState } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Card, CardBody, CardHeader } from '../../components/Card';
import { Button } from '../../components/Button';
import { Badge } from '../../components/Badge';
import { LoadingState, ErrorState, EmptyState } from '../../components/States';
import { Modal } from '../../components/Modal';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { FormField, Input, Select, Textarea } from '../../components/FormField';
import { useNotification } from '../../hooks/useNotification';
import { Pagination } from '../../components/Pagination';

type DeliveryEndpoint = {
  id: string;
  companyId: string;
  type: string;
  name: string;
  url: string;
  httpMethod: string;
  headers: Record<string, string>;
  responseType: string;
  isActive: boolean;
};

type DeliveryCompany = {
  id: string;
  name: string;
  slug: string;
  baseUrl: string;
  provider: string;
  authType: string;
  authConfig: Record<string, unknown>;
  mappingConfig: Record<string, unknown>;
  parametersConfig: Record<string, unknown>;
  logoUrl?: string | null;
  description?: string | null;
  isActive: boolean;
  endpoints: DeliveryEndpoint[];
};

type CompanyForm = {
  name: string;
  slug: string;
  baseUrl: string;
  provider: string;
  authType: string;
  authConfigJson: string;
  mappingConfigJson: string;
  parametersConfigJson: string;
  logoUrl: string;
  description: string;
  isActive: boolean;
};

type EndpointForm = {
  type: string;
  name: string;
  url: string;
  httpMethod: string;
  headersJson: string;
  responseType: string;
  isActive: boolean;
};

type DeliveryVariable = { code: string; label: string; category: string };

type DeliveryApiLog = {
  id: string;
  deliveryCompanyId: string;
  deliveryCompanyName: string;
  boutiqueId?: string | null;
  endpointType?: string | null;
  requestMethod: string;
  requestUrl: string;
  responseStatus?: number | null;
  success: boolean;
  errorMessage?: string | null;
  durationMs?: number | null;
  createdAt: string;
};

const emptyCompanyForm: CompanyForm = {
  name: '', slug: '', baseUrl: '', provider: 'generic_http', authType: 'bearer',
  authConfigJson: '{}', mappingConfigJson: '{}', parametersConfigJson: '{}',
  logoUrl: '', description: '', isActive: true,
};

const emptyEndpointForm: EndpointForm = {
  type: 'create_shipment', name: '', url: '', httpMethod: 'POST', headersJson: '{}', responseType: 'json', isActive: true,
};

const endpointTypes = [
  { value: 'create_shipment', label: 'Création colis' },
  { value: 'cancel_shipment', label: 'Annulation' },
  { value: 'track_shipment', label: 'Suivi' },
  { value: 'get_label', label: 'Étiquette' },
  { value: 'calculate_cost', label: 'Calcul du coût' },
  { value: 'get_cities', label: 'Liste des villes' },
  { value: 'auth', label: 'Authentification' },
];

function parseJsonOrNull(value: string): Record<string, unknown> | null {
  try {
    const parsed = JSON.parse(value);
    return typeof parsed === 'object' && parsed !== null ? parsed as Record<string, unknown> : null;
  } catch {
    return null;
  }
}

type Tab = 'companies' | 'logs' | 'variables';
const PAGE_SIZE = 20;

export function DeliveryAdminPanel({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const [tab, setTab] = useState<Tab>('companies');

  const [companyModalOpen, setCompanyModalOpen] = useState(false);
  const [editingCompany, setEditingCompany] = useState<DeliveryCompany | null>(null);
  const [companyForm, setCompanyForm] = useState<CompanyForm>(emptyCompanyForm);
  const [savingCompany, setSavingCompany] = useState(false);
  const [deleteCompanyId, setDeleteCompanyId] = useState<string | null>(null);

  const [endpointsCompany, setEndpointsCompany] = useState<DeliveryCompany | null>(null);
  const [endpointModalOpen, setEndpointModalOpen] = useState(false);
  const [editingEndpoint, setEditingEndpoint] = useState<DeliveryEndpoint | null>(null);
  const [endpointForm, setEndpointForm] = useState<EndpointForm>(emptyEndpointForm);
  const [savingEndpoint, setSavingEndpoint] = useState(false);
  const [deleteEndpointId, setDeleteEndpointId] = useState<string | null>(null);

  const [importModalOpen, setImportModalOpen] = useState(false);
  const [importJson, setImportJson] = useState('');
  const [importErrors, setImportErrors] = useState<string[]>([]);
  const [importActivate, setImportActivate] = useState(false);
  const [validating, setValidating] = useState(false);
  const [importing, setImporting] = useState(false);

  const [exportModalOpen, setExportModalOpen] = useState(false);
  const [exportJson, setExportJson] = useState('');

  const [previewModalOpen, setPreviewModalOpen] = useState(false);
  const [previewJson, setPreviewJson] = useState('');

  const [logFilterCompany, setLogFilterCompany] = useState('');
  const [companyPage, setCompanyPage] = useState(1);
  const [logPage, setLogPage] = useState(1);

  const fetchCompanies = useCallback(() => api.getCollection<DeliveryCompany>(`/admin/delivery-companies?page=${companyPage}&itemsPerPage=${PAGE_SIZE}`), [api, companyPage]);
  const fetchCompanyOptions = useCallback(() => api.getCollection<DeliveryCompany>('/admin/delivery-companies?itemsPerPage=100'), [api]);
  const fetchVariables = useCallback(() => api.getCollection<DeliveryVariable>('/admin/delivery/variables?itemsPerPage=100'), [api]);
  const fetchLogs = useCallback(
    () => api.getCollection<DeliveryApiLog>(`/admin/delivery/api-logs?page=${logPage}&itemsPerPage=${PAGE_SIZE}${logFilterCompany ? `&companyId=${encodeURIComponent(logFilterCompany)}` : ''}`),
    [api, logFilterCompany, logPage],
  );

  const { data: companiesData, isLoading: companiesLoading, error: companiesError, refresh: refreshCompanies } = useApiData(fetchCompanies, [tab, companyPage]);
  const { data: companyOptionsData } = useApiData(fetchCompanyOptions, [tab]);
  const { data: variablesData, isLoading: variablesLoading } = useApiData(fetchVariables, [tab]);
  const { data: logsData, isLoading: logsLoading, error: logsError, refresh: refreshLogs } = useApiData(fetchLogs, [tab, logFilterCompany, logPage]);

  const companies = companiesData?.member ?? [];
  const companyOptions = companyOptionsData?.member ?? companies;
  const variables = variablesData?.member ?? [];
  const logs = logsData?.member ?? [];
  const companyTotalPages = Math.max(1, Math.ceil((companiesData?.totalItems ?? 0) / PAGE_SIZE));
  const logTotalPages = Math.max(1, Math.ceil((logsData?.totalItems ?? 0) / PAGE_SIZE));

  const variablesByCategory = variables.reduce((acc, v) => {
    (acc[v.category] ??= []).push(v);
    return acc;
  }, {} as Record<string, DeliveryVariable[]>);

  const openCreateCompany = () => { setEditingCompany(null); setCompanyForm(emptyCompanyForm); setCompanyModalOpen(true); };
  const openEditCompany = (company: DeliveryCompany) => {
    setEditingCompany(company);
    setCompanyForm({
      name: company.name, slug: company.slug, baseUrl: company.baseUrl, provider: company.provider, authType: company.authType,
      authConfigJson: JSON.stringify(company.authConfig ?? {}, null, 2),
      mappingConfigJson: JSON.stringify(company.mappingConfig ?? {}, null, 2),
      parametersConfigJson: JSON.stringify(company.parametersConfig ?? {}, null, 2),
      logoUrl: company.logoUrl ?? '', description: company.description ?? '', isActive: company.isActive,
    });
    setCompanyModalOpen(true);
  };

  const saveCompany = async () => {
    if (!companyForm.name.trim() || !companyForm.slug.trim() || !companyForm.baseUrl.trim()) {
      showNotice('Nom, slug et URL de base sont obligatoires.', 'error');
      return;
    }
    const authConfig = parseJsonOrNull(companyForm.authConfigJson);
    const mappingConfig = parseJsonOrNull(companyForm.mappingConfigJson);
    const parametersConfig = parseJsonOrNull(companyForm.parametersConfigJson);
    if (authConfig === null || mappingConfig === null || parametersConfig === null) {
      showNotice('Les blocs de configuration doivent être des objets JSON valides.', 'error');
      return;
    }
    setSavingCompany(true);
    try {
      const payload = {
        name: companyForm.name.trim(),
        slug: companyForm.slug.trim(),
        baseUrl: companyForm.baseUrl.trim(),
        provider: companyForm.provider.trim(),
        authType: companyForm.authType,
        authConfig, mappingConfig, parametersConfig,
        logoUrl: companyForm.logoUrl.trim() || null,
        description: companyForm.description.trim() || null,
        isActive: companyForm.isActive,
      };
      if (editingCompany) {
        await api.patch(`/admin/delivery-companies/${editingCompany.id}`, payload);
        showNotice('Société modifiée', 'success');
      } else {
        await api.post('/admin/delivery-companies', payload);
        showNotice('Société créée', 'success');
      }
      setCompanyModalOpen(false);
      refreshCompanies();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la sauvegarde', 'error');
    } finally {
      setSavingCompany(false);
    }
  };

  const toggleCompanyActive = async (company: DeliveryCompany) => {
    try {
      await api.patch(`/admin/delivery-companies/${company.id}`, {
        name: company.name, slug: company.slug, baseUrl: company.baseUrl, provider: company.provider, authType: company.authType,
        authConfig: company.authConfig, mappingConfig: company.mappingConfig, parametersConfig: company.parametersConfig,
        logoUrl: company.logoUrl, description: company.description, isActive: !company.isActive,
      });
      showNotice(company.isActive ? 'Société désactivée' : 'Société activée', 'success');
      refreshCompanies();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur', 'error');
    }
  };

  const deleteCompany = async () => {
    if (!deleteCompanyId) return;
    try {
      await api.delete(`/admin/delivery-companies/${deleteCompanyId}`);
      showNotice('Société supprimée', 'success');
      setDeleteCompanyId(null);
      refreshCompanies();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la suppression', 'error');
    }
  };

  const openEndpoints = (company: DeliveryCompany) => setEndpointsCompany(company);

  const openCreateEndpoint = () => { setEditingEndpoint(null); setEndpointForm(emptyEndpointForm); setEndpointModalOpen(true); };
  const openEditEndpoint = (endpoint: DeliveryEndpoint) => {
    setEditingEndpoint(endpoint);
    setEndpointForm({
      type: endpoint.type, name: endpoint.name, url: endpoint.url, httpMethod: endpoint.httpMethod,
      headersJson: JSON.stringify(endpoint.headers ?? {}, null, 2), responseType: endpoint.responseType, isActive: endpoint.isActive,
    });
    setEndpointModalOpen(true);
  };

  const saveEndpoint = async () => {
    if (!endpointsCompany) return;
    if (!endpointForm.name.trim() || !endpointForm.url.trim()) {
      showNotice('Nom et URL sont obligatoires.', 'error');
      return;
    }
    const headers = parseJsonOrNull(endpointForm.headersJson);
    if (headers === null) {
      showNotice('Les en-têtes doivent être un objet JSON valide.', 'error');
      return;
    }
    setSavingEndpoint(true);
    try {
      const payload = {
        type: endpointForm.type, name: endpointForm.name.trim(), url: endpointForm.url.trim(),
        httpMethod: endpointForm.httpMethod, headers, responseType: endpointForm.responseType, isActive: endpointForm.isActive,
      };
      if (editingEndpoint) {
        await api.patch(`/admin/delivery-endpoints/${editingEndpoint.id}`, payload);
        showNotice('Endpoint modifié', 'success');
      } else {
        await api.post(`/admin/delivery-companies/${endpointsCompany.id}/endpoints`, payload);
        showNotice('Endpoint créé', 'success');
      }
      setEndpointModalOpen(false);
      refreshCompanies();
      const updated = await api.get<DeliveryCompany>(`/delivery/companies/${endpointsCompany.id}`).catch(() => null);
      if (updated) setEndpointsCompany(updated);
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la sauvegarde', 'error');
    } finally {
      setSavingEndpoint(false);
    }
  };

  const deleteEndpoint = async () => {
    if (!deleteEndpointId || !endpointsCompany) return;
    try {
      await api.delete(`/admin/delivery-endpoints/${deleteEndpointId}`);
      showNotice('Endpoint supprimé', 'success');
      setDeleteEndpointId(null);
      refreshCompanies();
      const updated = await api.get<DeliveryCompany>(`/delivery/companies/${endpointsCompany.id}`).catch(() => null);
      if (updated) setEndpointsCompany(updated);
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la suppression', 'error');
    }
  };

  const openImport = () => { setImportJson(''); setImportErrors([]); setImportActivate(false); setImportModalOpen(true); };

  const validateImport = async () => {
    setValidating(true);
    setImportErrors([]);
    try {
      const config = JSON.parse(importJson);
      const result = await api.post<{ valid: boolean; errors: string[] }>('/admin/delivery-companies/validate-import', config);
      if (result.valid) {
        showNotice('Configuration valide.', 'success');
      } else {
        setImportErrors(result.errors);
      }
    } catch (error) {
      setImportErrors([error instanceof Error ? error.message : 'JSON invalide.']);
    } finally {
      setValidating(false);
    }
  };

  const runImport = async () => {
    setImporting(true);
    setImportErrors([]);
    try {
      const config = JSON.parse(importJson);
      config.activate = importActivate;
      await api.post('/admin/delivery-companies/import', config);
      showNotice('Société importée avec succès.', 'success');
      setImportModalOpen(false);
      refreshCompanies();
    } catch (error) {
      setImportErrors([error instanceof Error ? error.message : 'Erreur lors de l\'import.']);
    } finally {
      setImporting(false);
    }
  };

  const exportCompany = async (company: DeliveryCompany) => {
    try {
      const config = await api.get<Record<string, unknown>>(`/admin/delivery-companies/${company.id}/export`);
      setExportJson(JSON.stringify(config, null, 2));
      setExportModalOpen(true);
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de l\'export', 'error');
    }
  };

  const previewCompanyMapping = async (company: DeliveryCompany) => {
    try {
      const result = await api.get<{ preview: Record<string, unknown> }>(`/admin/delivery-companies/${company.id}/preview-mapping`);
      setPreviewJson(JSON.stringify(result.preview, null, 2));
      setPreviewModalOpen(true);
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de l\'aperçu du mapping', 'error');
    }
  };

  const copyToClipboard = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text);
      showNotice('Copié dans le presse-papier.', 'success');
    } catch {
      showNotice('Impossible de copier automatiquement.', 'warning');
    }
  };

  return (
    <div style={{ display: 'grid', gap: 20 }}>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
        <Button variant={tab === 'companies' ? 'primary' : 'ghost'} size="sm" onClick={() => setTab('companies')}>Sociétés de livraison</Button>
        <Button variant={tab === 'logs' ? 'primary' : 'ghost'} size="sm" onClick={() => setTab('logs')}>Logs API</Button>
        <Button variant={tab === 'variables' ? 'primary' : 'ghost'} size="sm" onClick={() => setTab('variables')}>Variables</Button>
      </div>

      {tab === 'companies' && (
        <Card>
          <CardHeader>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
              <span>Sociétés de livraison</span>
              <div style={{ display: 'flex', gap: 8 }}>
                <Button variant="secondary" size="sm" onClick={openImport}>Importer JSON</Button>
                <Button variant="primary" size="sm" onClick={openCreateCompany}>Nouvelle société</Button>
              </div>
            </div>
          </CardHeader>
          <CardBody>
            {companiesLoading ? <LoadingState /> : companiesError ? <ErrorState message={companiesError} onRetry={refreshCompanies} /> : companies.length === 0 ? <EmptyState /> : (
              <div style={{ display: 'grid', gap: 8 }}>
                 {companies.map((company) => (
                  <div key={company.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 12px', border: '1px solid var(--bo-border)', borderRadius: 8, flexWrap: 'wrap', gap: 8 }}>
                    <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                      {company.logoUrl && <img src={company.logoUrl} alt={company.name} style={{ width: 32, height: 32, borderRadius: 6, objectFit: 'contain' }} />}
                      <div>
                        <strong>{company.name}</strong> <span style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>({company.slug})</span>
                        <div style={{ fontSize: 12, color: 'var(--bo-text-secondary)' }}>
                          {company.provider} — auth: {company.authType} — {company.endpoints?.length ?? 0} endpoint(s)
                        </div>
                      </div>
                    </div>
                    <div style={{ display: 'flex', gap: 6, alignItems: 'center', flexWrap: 'wrap' }}>
                      <Badge tone={company.isActive ? 'success' : 'neutral'}>{company.isActive ? 'Actif' : 'Inactif'}</Badge>
                      <Button variant="secondary" size="sm" onClick={() => openEndpoints(company)}>Endpoints</Button>
                      <Button variant="ghost" size="sm" onClick={() => previewCompanyMapping(company)}>Aperçu mapping</Button>
                      <Button variant="ghost" size="sm" onClick={() => exportCompany(company)}>Exporter</Button>
                      <Button variant="secondary" size="sm" onClick={() => openEditCompany(company)}>Modifier</Button>
                      <Button variant="ghost" size="sm" onClick={() => toggleCompanyActive(company)}>{company.isActive ? 'Désactiver' : 'Activer'}</Button>
                      <Button variant="danger" size="sm" onClick={() => setDeleteCompanyId(company.id)}>Suppr.</Button>
                    </div>
                  </div>
                 ))}
                 <Pagination page={companyPage} totalPages={companyTotalPages} onPageChange={setCompanyPage} />
               </div>
            )}
          </CardBody>
        </Card>
      )}

      {tab === 'logs' && (
        <Card>
          <CardHeader>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
              <span>Logs des appels API transporteurs</span>
               <Select style={{ maxWidth: 240 }} value={logFilterCompany} onChange={(e) => { setLogFilterCompany(e.target.value); setLogPage(1); }}>
                <option value="">Toutes les sociétés</option>
                 {companyOptions.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </Select>
            </div>
          </CardHeader>
          <CardBody>
            {logsLoading ? <LoadingState /> : logsError ? <ErrorState message={logsError} onRetry={refreshLogs} /> : logs.length === 0 ? <EmptyState /> : (
              <div style={{ display: 'grid', gap: 6 }}>
                 {logs.map((log) => (
                  <div key={log.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 12px', border: '1px solid var(--bo-border)', borderRadius: 8, flexWrap: 'wrap', gap: 8 }}>
                    <div>
                      <strong>{log.deliveryCompanyName}</strong>{log.endpointType ? ` — ${log.endpointType}` : ''}
                      <div style={{ fontSize: 12, color: 'var(--bo-text-muted)', wordBreak: 'break-all' }}>
                        {log.requestMethod} {log.requestUrl}
                      </div>
                      {log.errorMessage && <div style={{ fontSize: 12, color: 'var(--bo-error)' }}>{log.errorMessage}</div>}
                    </div>
                    <div style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 12, color: 'var(--bo-text-muted)' }}>
                      {log.responseStatus != null && <Badge tone={log.success ? 'success' : 'error'}>{log.responseStatus}</Badge>}
                      {log.durationMs != null && <span>{log.durationMs}ms</span>}
                      <span>{new Date(log.createdAt).toLocaleString('fr-FR')}</span>
                    </div>
                  </div>
                 ))}
                 <Pagination page={logPage} totalPages={logTotalPages} onPageChange={setLogPage} />
               </div>
            )}
          </CardBody>
        </Card>
      )}

      {tab === 'variables' && (
        <Card>
          <CardHeader>Catalogue de variables internes</CardHeader>
          <CardBody>
            {variablesLoading ? <LoadingState /> : (
              <div style={{ display: 'grid', gap: 20 }}>
                {Object.entries(variablesByCategory).map(([category, vars]) => (
                  <div key={category}>
                    <h4 style={{ marginTop: 0, marginBottom: 8, fontSize: 13, textTransform: 'uppercase', color: 'var(--bo-text-muted)' }}>{category}</h4>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 8 }}>
                      {vars.map((v) => (
                        <div
                          key={v.code}
                          style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '6px 10px', border: '1px solid var(--bo-border)', borderRadius: 6, cursor: 'pointer' }}
                          onClick={() => copyToClipboard(`{{${v.code}}}`)}
                          title="Cliquer pour copier"
                        >
                          <code style={{ fontSize: 12 }}>{`{{${v.code}}}`}</code>
                          <span style={{ fontSize: 12, color: 'var(--bo-text-secondary)' }}>{v.label}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardBody>
        </Card>
      )}

      <Modal
        isOpen={companyModalOpen}
        onClose={() => setCompanyModalOpen(false)}
        title={editingCompany ? 'Modifier la société' : 'Créer une société de livraison'}
        width="680px"
        footer={(
          <>
            <Button variant="secondary" onClick={() => setCompanyModalOpen(false)}>Annuler</Button>
            <Button variant="primary" onClick={saveCompany} disabled={savingCompany}>{savingCompany ? 'En cours...' : editingCompany ? 'Enregistrer' : 'Créer'}</Button>
          </>
        )}
      >
        <div style={{ display: 'grid', gap: 14 }}>
          <h4 style={{ margin: 0 }}>Informations générales</h4>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
            <FormField label="Nom" required><Input value={companyForm.name} onChange={(e) => setCompanyForm({ ...companyForm, name: e.target.value })} placeholder="Aramex" /></FormField>
            <FormField label="Slug" required hint="minuscules, chiffres, tirets"><Input value={companyForm.slug} onChange={(e) => setCompanyForm({ ...companyForm, slug: e.target.value })} placeholder="aramex" /></FormField>
          </div>
          <FormField label="URL de base" required><Input value={companyForm.baseUrl} onChange={(e) => setCompanyForm({ ...companyForm, baseUrl: e.target.value })} placeholder="https://api.aramex.com" /></FormField>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
            <FormField label="Logo (URL)"><Input value={companyForm.logoUrl} onChange={(e) => setCompanyForm({ ...companyForm, logoUrl: e.target.value })} /></FormField>
            <FormField label="Provider" required hint="Code du connector (ex: aramex, generic_http)"><Input value={companyForm.provider} onChange={(e) => setCompanyForm({ ...companyForm, provider: e.target.value })} /></FormField>
          </div>
          <FormField label="Description"><Textarea rows={2} value={companyForm.description} onChange={(e) => setCompanyForm({ ...companyForm, description: e.target.value })} /></FormField>

          <h4 style={{ margin: '8px 0 0' }}>Authentification</h4>
          <FormField label="Type d'authentification">
            <Select value={companyForm.authType} onChange={(e) => setCompanyForm({ ...companyForm, authType: e.target.value })}>
              <option value="none">Aucune</option>
              <option value="basic">Basic</option>
              <option value="bearer">Bearer</option>
              <option value="api_key">Clé API</option>
              <option value="custom">Personnalisée (connector dédié)</option>
            </Select>
          </FormField>
          <FormField label="Configuration auth (JSON)" hint='ex: {"header": "Authorization", "prefix": "Bearer "}'>
            <Textarea rows={3} value={companyForm.authConfigJson} onChange={(e) => setCompanyForm({ ...companyForm, authConfigJson: e.target.value })} style={{ fontFamily: 'monospace', fontSize: 12 }} />
          </FormField>

          <h4 style={{ margin: '8px 0 0' }}>Mapping des champs</h4>
          <FormField label="Configuration mapping (JSON)" hint='ex: {"receiver": "{{customer.full_name}}", "amount": "{{order.total}}"}'>
            <Textarea rows={5} value={companyForm.mappingConfigJson} onChange={(e) => setCompanyForm({ ...companyForm, mappingConfigJson: e.target.value })} style={{ fontFamily: 'monospace', fontSize: 12 }} />
          </FormField>

          <h4 style={{ margin: '8px 0 0' }}>Paramètres additionnels</h4>
          <FormField label="Paramètres (JSON)">
            <Textarea rows={3} value={companyForm.parametersConfigJson} onChange={(e) => setCompanyForm({ ...companyForm, parametersConfigJson: e.target.value })} style={{ fontFamily: 'monospace', fontSize: 12 }} />
          </FormField>

          <label><input type="checkbox" checked={companyForm.isActive} onChange={(e) => setCompanyForm({ ...companyForm, isActive: e.target.checked })} /> Actif</label>
        </div>
      </Modal>

      <Modal
        isOpen={endpointsCompany !== null}
        onClose={() => setEndpointsCompany(null)}
        title={endpointsCompany ? `Endpoints — ${endpointsCompany.name}` : ''}
        width="640px"
        footer={<Button variant="secondary" onClick={() => setEndpointsCompany(null)}>Fermer</Button>}
      >
        {endpointsCompany && (
          <div style={{ display: 'grid', gap: 10 }}>
            <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
              <Button variant="primary" size="sm" onClick={openCreateEndpoint}>Nouvel endpoint</Button>
            </div>
            {(endpointsCompany.endpoints ?? []).length === 0 ? <EmptyState /> : (endpointsCompany.endpoints ?? []).map((endpoint) => (
              <div key={endpoint.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 10px', border: '1px solid var(--bo-border)', borderRadius: 8, flexWrap: 'wrap', gap: 8 }}>
                <div>
                  <strong>{endpoint.name}</strong> <span style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>({endpointTypes.find((t) => t.value === endpoint.type)?.label ?? endpoint.type})</span>
                  <div style={{ fontSize: 12, color: 'var(--bo-text-secondary)', wordBreak: 'break-all' }}>{endpoint.httpMethod} {endpoint.url}</div>
                </div>
                <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                  <Badge tone={endpoint.isActive ? 'success' : 'neutral'}>{endpoint.isActive ? 'Actif' : 'Inactif'}</Badge>
                  <Button variant="secondary" size="sm" onClick={() => openEditEndpoint(endpoint)}>Modifier</Button>
                  <Button variant="danger" size="sm" onClick={() => setDeleteEndpointId(endpoint.id)}>Suppr.</Button>
                </div>
              </div>
            ))}
          </div>
        )}
      </Modal>

      <Modal
        isOpen={endpointModalOpen}
        onClose={() => setEndpointModalOpen(false)}
        title={editingEndpoint ? 'Modifier l\'endpoint' : 'Nouvel endpoint'}
        width="560px"
        footer={(
          <>
            <Button variant="secondary" onClick={() => setEndpointModalOpen(false)}>Annuler</Button>
            <Button variant="primary" onClick={saveEndpoint} disabled={savingEndpoint}>{savingEndpoint ? 'En cours...' : editingEndpoint ? 'Enregistrer' : 'Créer'}</Button>
          </>
        )}
      >
        <div style={{ display: 'grid', gap: 14 }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
            <FormField label="Type" required>
              <Select value={endpointForm.type} onChange={(e) => setEndpointForm({ ...endpointForm, type: e.target.value })}>
                {endpointTypes.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
              </Select>
            </FormField>
            <FormField label="Méthode HTTP" required>
              <Select value={endpointForm.httpMethod} onChange={(e) => setEndpointForm({ ...endpointForm, httpMethod: e.target.value })}>
                <option value="GET">GET</option><option value="POST">POST</option><option value="PUT">PUT</option><option value="PATCH">PATCH</option><option value="DELETE">DELETE</option>
              </Select>
            </FormField>
          </div>
          <FormField label="Nom" required><Input value={endpointForm.name} onChange={(e) => setEndpointForm({ ...endpointForm, name: e.target.value })} /></FormField>
          <FormField label="URL" required><Input value={endpointForm.url} onChange={(e) => setEndpointForm({ ...endpointForm, url: e.target.value })} placeholder="/shipments/create" /></FormField>
          <FormField label="En-têtes (JSON)" hint='ex: {"Content-Type": "application/json"}'>
            <Textarea rows={3} value={endpointForm.headersJson} onChange={(e) => setEndpointForm({ ...endpointForm, headersJson: e.target.value })} style={{ fontFamily: 'monospace', fontSize: 12 }} />
          </FormField>
          <FormField label="Type de réponse">
            <Select value={endpointForm.responseType} onChange={(e) => setEndpointForm({ ...endpointForm, responseType: e.target.value })}>
              <option value="json">JSON</option><option value="xml">XML</option><option value="text">Texte</option>
            </Select>
          </FormField>
          <label><input type="checkbox" checked={endpointForm.isActive} onChange={(e) => setEndpointForm({ ...endpointForm, isActive: e.target.checked })} /> Actif</label>
        </div>
      </Modal>

      <Modal
        isOpen={importModalOpen}
        onClose={() => setImportModalOpen(false)}
        title="Importer une société via JSON"
        width="640px"
        footer={(
          <>
            <Button variant="secondary" onClick={() => setImportModalOpen(false)}>Annuler</Button>
            <Button variant="secondary" onClick={validateImport} disabled={validating}>{validating ? 'Validation...' : 'Valider'}</Button>
            <Button variant="primary" onClick={runImport} disabled={importing}>{importing ? 'Import...' : 'Importer'}</Button>
          </>
        )}
      >
        <div style={{ display: 'grid', gap: 12 }}>
          <FormField label="Configuration JSON" required hint='{"name": "...", "provider": "...", "auth": {...}, "endpoints": [...], "mapping": {...}}'>
            <Textarea rows={12} value={importJson} onChange={(e) => setImportJson(e.target.value)} style={{ fontFamily: 'monospace', fontSize: 12 }} />
          </FormField>
          <label><input type="checkbox" checked={importActivate} onChange={(e) => setImportActivate(e.target.checked)} /> Activer immédiatement après import</label>
          {importErrors.length > 0 && (
            <div style={{ padding: 10, borderRadius: 8, background: 'var(--bo-error-bg, #fee)', color: 'var(--bo-error)' }}>
              <strong>Erreurs :</strong>
              <ul style={{ margin: '6px 0 0', paddingLeft: 18 }}>
                {importErrors.map((err, i) => <li key={i}>{err}</li>)}
              </ul>
            </div>
          )}
        </div>
      </Modal>

      <Modal
        isOpen={exportModalOpen}
        onClose={() => setExportModalOpen(false)}
        title="Export JSON"
        width="640px"
        footer={(
          <>
            <Button variant="secondary" onClick={() => setExportModalOpen(false)}>Fermer</Button>
            <Button variant="primary" onClick={() => copyToClipboard(exportJson)}>Copier</Button>
          </>
        )}
      >
        <Textarea rows={16} value={exportJson} readOnly style={{ fontFamily: 'monospace', fontSize: 12 }} />
      </Modal>

      <Modal
        isOpen={previewModalOpen}
        onClose={() => setPreviewModalOpen(false)}
        title="Aperçu du mapping (données d'exemple)"
        width="600px"
        footer={<Button variant="secondary" onClick={() => setPreviewModalOpen(false)}>Fermer</Button>}
      >
        <Textarea rows={14} value={previewJson} readOnly style={{ fontFamily: 'monospace', fontSize: 12 }} />
      </Modal>

      <ConfirmDialog
        isOpen={deleteCompanyId !== null}
        title="Supprimer la société"
        message="Cette société de livraison et ses endpoints seront définitivement supprimés."
        confirmLabel="Supprimer"
        onConfirm={deleteCompany}
        onClose={() => setDeleteCompanyId(null)}
        danger
      />

      <ConfirmDialog
        isOpen={deleteEndpointId !== null}
        title="Supprimer l'endpoint"
        message="Cet endpoint sera définitivement supprimé."
        confirmLabel="Supprimer"
        onConfirm={deleteEndpoint}
        onClose={() => setDeleteEndpointId(null)}
        danger
      />
    </div>
  );
}
