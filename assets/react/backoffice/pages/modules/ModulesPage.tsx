import { useCallback, useState } from 'react';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { useBoutique } from '../../hooks/useBoutique';
import { useNotification } from '../../hooks/useNotification';
import { PageHeader } from '../../layout/Shell';
import { Card, CardBody } from '../../components/Card';
import { Button } from '../../components/Button';
import { Badge } from '../../components/Badge';
import { LoadingState, EmptyState, ErrorState } from '../../components/States';
import { Pagination } from '../../components/Pagination';

type PlatformModule = {
  id: string | null;
  moduleId: string;
  moduleCode: string;
  moduleName: string;
  isEnabled: boolean;
  reasonDisabled?: string | null;
};

type ShopModule = {
  id: string;
  moduleId: string;
  moduleCode: string;
  moduleName: string;
  isEnabled: boolean;
};

export function ModulesPage({ getAccessToken }: { getAccessToken: () => string | null }) {
  const api = useApiClient(getAccessToken);
  const { boutique } = useBoutique();
  const { showNotice } = useNotification();
  const [page, setPage] = useState(1);

  const fetchModules = useCallback(async () => {
    const platform = await api.getCollection<PlatformModule>(`/admin/platform-modules?page=${page}&itemsPerPage=20`);
    const shop = boutique?.id
      ? await api.getCollection<ShopModule>(`/boutiques/${boutique.id}/modules?itemsPerPage=100`)
      : { member: [], totalItems: 0 };

    return { platform: platform.member, platformTotalItems: platform.totalItems, shop: shop.member };
  }, [api, boutique?.id, page]);

  const { data, isLoading, error, refresh } = useApiData(fetchModules, [boutique?.id, page]);
  const shopByCode = new Map((data?.shop ?? []).map((module) => [module.moduleCode, module]));
  const totalPages = Math.max(1, Math.ceil((data?.platformTotalItems ?? 0) / 20));

  const togglePlatform = async (module: PlatformModule) => {
    try {
      const isEnabled = !module.isEnabled;
      if (module.id) {
        await api.patch(`/admin/platform-modules/${module.id}`, { moduleId: module.moduleId, isEnabled });
      } else {
        await api.post('/admin/platform-modules', { moduleId: module.moduleId, isEnabled });
      }
      showNotice(`Module ${isEnabled ? 'activé' : 'désactivé'} globalement.`, 'success');
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Impossible de modifier le module.', 'error');
    }
  };

  const toggleShop = async (module: PlatformModule, shopModule?: ShopModule) => {
    if (!boutique?.id) return;

    try {
      const isEnabled = !(shopModule?.isEnabled ?? true);
      if (shopModule) {
        await api.patch(`/boutiques/${boutique.id}/modules/${shopModule.id}`, { isEnabled });
      } else {
        await api.post(`/boutiques/${boutique.id}/modules`, { moduleId: module.moduleId, isEnabled });
      }
      showNotice(`Module ${isEnabled ? 'activé' : 'désactivé'} pour ${boutique.name}.`, 'success');
      refresh();
    } catch (err) {
      showNotice(err instanceof Error ? err.message : 'Impossible de modifier le module de la boutique.', 'error');
    }
  };

  return (
    <div>
      <PageHeader
        title="Modules"
        description={boutique ? `État global et configuration de ${boutique.name}.` : 'État global des modules de la plateforme.'}
      />
      <Card>
        <CardBody>
          {isLoading ? <LoadingState /> : error ? <ErrorState message={error} onRetry={refresh} /> : !data?.platform.length ? (
            <EmptyState title="Aucun module" message="Les modules disponibles apparaîtront ici." />
          ) : (
            <div style={{ display: 'grid', gap: 10 }}>
               {data.platform.map((module) => {
                const shopModule = shopByCode.get(module.moduleCode);
                const shopEnabled = shopModule?.isEnabled ?? true;
                return (
                  <div key={module.moduleCode} style={{ display: 'grid', gridTemplateColumns: 'minmax(220px, 1fr) auto auto', gap: 16, alignItems: 'center', padding: '14px 16px', border: '1px solid var(--bo-border)', borderRadius: 10 }}>
                    <div>
                      <strong>{module.moduleName}</strong>
                      <div style={{ fontSize: 12, color: 'var(--bo-text-muted)' }}>{module.moduleCode}</div>
                      {module.reasonDisabled && <div style={{ fontSize: 12, color: 'var(--bo-text-secondary)', marginTop: 4 }}>{module.reasonDisabled}</div>}
                    </div>
                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                      <Badge tone={module.isEnabled ? 'success' : 'error'}>{module.isEnabled ? 'Global activé' : 'Global désactivé'}</Badge>
                      {boutique && <Badge tone={shopEnabled ? 'success' : 'warning'}>{shopEnabled ? 'Boutique activée' : 'Boutique désactivée'}</Badge>}
                    </div>
                    <div style={{ display: 'flex', gap: 8 }}>
                      <Button variant={module.isEnabled ? 'ghost' : 'primary'} size="sm" onClick={() => togglePlatform(module)}>
                        {module.isEnabled ? 'Désactiver globalement' : 'Activer globalement'}
                      </Button>
                      {boutique && (
                        <Button variant={shopEnabled ? 'ghost' : 'secondary'} size="sm" onClick={() => toggleShop(module, shopModule)}>
                          {shopEnabled ? 'Désactiver boutique' : 'Activer boutique'}
                        </Button>
                      )}
                    </div>
                  </div>
                );
               })}
               <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
             </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
