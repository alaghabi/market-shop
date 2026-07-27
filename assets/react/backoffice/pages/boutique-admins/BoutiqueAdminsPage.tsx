import { useCallback, useMemo, useState } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { Badge } from '../../components/Badge';
import { Button } from '../../components/Button';
import { Card, CardBody, CardHeader } from '../../components/Card';
import { EmptyState, ErrorState, LoadingState } from '../../components/States';
import { FiltersBar } from '../../components/FiltersBar';
import { Table } from '../../components/Table';
import { Pagination } from '../../components/Pagination';
import { PageHeader } from '../../layout/Shell';
import { useNotification } from '../../hooks/useNotification';
import { useBoutique } from '../../hooks/useBoutique';

type BoutiqueAdmin = {
  id: string;
  userId: string;
  email: string;
  displayName?: string | null;
  boutiqueId: string;
  boutiqueName: string;
  role: string;
  status: string;
  createdAt: string;
};

const PAGE_SIZE = 20;

const statusLabels: Record<string, string> = {
  PENDING: 'En attente',
  ACTIVE: 'Actif',
  INACTIVE: 'Inactif',
  SUSPENDED: 'Suspendu',
  REJECTED: 'Rejeté',
};

function statusTone(status: string): 'success' | 'warning' | 'error' | 'neutral' {
  if (status === 'ACTIVE') return 'success';
  if (status === 'PENDING') return 'warning';
  if (status === 'INACTIVE' || status === 'SUSPENDED' || status === 'REJECTED') return 'error';

  return 'neutral';
}

export function BoutiqueAdminsPage({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const { showNotice } = useNotification();
  const { boutique } = useBoutique();
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const [processingId, setProcessingId] = useState<string | null>(null);

  const fetchAdmins = useCallback(() => api.getCollection<BoutiqueAdmin>(`/admin/boutique-admins?page=${page}&itemsPerPage=${PAGE_SIZE}`), [api, page]);
  const { data, isLoading, error, refresh } = useApiData(fetchAdmins, [boutique?.id, page]);
  const admins = data?.member ?? [];
  const totalPages = Math.max(1, Math.ceil((data?.totalItems ?? 0) / PAGE_SIZE));

  async function updateAdminStatus(admin: BoutiqueAdmin, action: 'activate' | 'suspend') {
    setProcessingId(admin.id);
    try {
      await api.post(`/admin/users/${admin.userId}/${action}`, {});
      showNotice(action === 'activate' ? 'Administrateur activé.' : 'Administrateur désactivé.', 'success');
      refresh();
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la mise à jour.', 'error');
    } finally {
      setProcessingId(null);
    }
  }

  const filteredAdmins = useMemo(() => {
    const normalizedSearch = search.trim().toLowerCase();

    return admins.filter((admin) => {
      const matchesBoutique = !boutique || admin.boutiqueId === boutique.id;
      const matchesStatus = !status || admin.status === status;
      const matchesSearch = !normalizedSearch
        || admin.email.toLowerCase().includes(normalizedSearch)
        || (admin.displayName ?? '').toLowerCase().includes(normalizedSearch)
        || admin.boutiqueName.toLowerCase().includes(normalizedSearch);

      return matchesBoutique && matchesStatus && matchesSearch;
    });
  }, [admins, boutique?.id, search, status]);

  const columns = [
    {
      key: 'email',
      label: 'Administrateur',
      render: (admin: BoutiqueAdmin) => (
        <div>
          <strong>{admin.displayName || admin.email}</strong>
          {admin.displayName && <div style={{ color: 'var(--bo-text-muted)', fontSize: 13 }}>{admin.email}</div>}
        </div>
      ),
    },
    { key: 'boutiqueName', label: 'Boutique', render: (admin: BoutiqueAdmin) => <span>{admin.boutiqueName}</span> },
    { key: 'role', label: 'Rôle', render: (admin: BoutiqueAdmin) => <Badge tone="primary">{admin.role}</Badge> },
    { key: 'status', label: 'Statut', render: (admin: BoutiqueAdmin) => <Badge tone={statusTone(admin.status)}>{statusLabels[admin.status] ?? admin.status}</Badge> },
    { key: 'createdAt', label: 'Créé le', render: (admin: BoutiqueAdmin) => <span>{new Date(admin.createdAt).toLocaleDateString('fr-FR')}</span> },
  ];

  if (error) return <ErrorState message={error} onRetry={refresh} />;

  return (
    <div>
      <PageHeader
        title="Admins boutique"
        description={boutique ? `Administrateurs de ${boutique.name}.` : 'Consultez les administrateurs liés aux boutiques de la plateforme.'}
      />

      <Card>
        <CardHeader>
          <FiltersBar
            search={search}
            onSearchChange={(value) => { setSearch(value); setPage(1); }}
            status={status}
            onStatusChange={(value) => { setStatus(value); setPage(1); }}
            statusOptions={Object.entries(statusLabels).map(([value, label]) => ({ value, label }))}
          />
        </CardHeader>
        <CardBody>
          {isLoading ? <LoadingState /> : filteredAdmins.length === 0 ? (
            <EmptyState title="Aucun admin boutique" message="Aucun administrateur ne correspond aux filtres." />
          ) : (
            <>
              <Table
                columns={columns}
                data={filteredAdmins}
                renderActions={(admin) => admin.status === 'ACTIVE' ? (
                <Button
                  variant="ghost"
                  size="sm"
                  disabled={processingId === admin.id}
                  style={{ color: 'var(--bo-error)' }}
                  onClick={() => updateAdminStatus(admin, 'suspend')}
                >
                  Désactiver
                </Button>
              ) : (
                <Button
                  variant="ghost"
                  size="sm"
                  disabled={processingId === admin.id}
                  onClick={() => updateAdminStatus(admin, 'activate')}
                >
                  Activer
                </Button>
              )}
                />
              <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
            </>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
