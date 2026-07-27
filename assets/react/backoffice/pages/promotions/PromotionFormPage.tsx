import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { useBoutique } from '../../hooks/useBoutique';
import { useNotification } from '../../hooks/useNotification';
import { BoutiqueFormSelect, resolveFormBoutiqueId } from '../../components/BoutiqueFormSelect';
import { Card, CardBody, CardHeader } from '../../components/Card';
import { Button } from '../../components/Button';
import { FormField, Input, Select } from '../../components/FormField';
import { LoadingState, ErrorState } from '../../components/States';
import { PageHeader } from '../../layout/Shell';
import { emptyPromotionForm, promotionFormFromItem, type Promotion, type PromotionFormState } from './promotionTypes';

type CategoryOption = { id: string; name: string };
type ProductOption = { id: string; name: string };

export function PromotionFormPage({ getAccessToken, promotionId }: { getAccessToken: () => string | null; promotionId?: string }) {
  const api = useApiClient(getAccessToken);
  const navigate = useNavigate();
  const { boutique } = useBoutique();
  const { showNotice } = useNotification();
  const [form, setForm] = useState<PromotionFormState>(emptyPromotionForm);
  const [submitting, setSubmitting] = useState(false);
  const targetBoutiqueId = boutique?.id ?? form.boutiqueId;

  const fetchPromotion = useCallback(
    () => promotionId ? api.get<Promotion>(`/promotions/${promotionId}`) : Promise.resolve(null),
    [api, promotionId],
  );
  const fetchCategories = useCallback(
    () => targetBoutiqueId ? api.getCollection<CategoryOption>(`/categories?boutiqueId=${encodeURIComponent(targetBoutiqueId)}&itemsPerPage=100`) : Promise.resolve({ member: [], totalItems: 0 }),
    [api, targetBoutiqueId],
  );
  const fetchProducts = useCallback(
    () => targetBoutiqueId ? api.getCollection<ProductOption>(`/products?boutiqueId=${encodeURIComponent(targetBoutiqueId)}&itemsPerPage=100`) : Promise.resolve({ member: [], totalItems: 0 }),
    [api, targetBoutiqueId],
  );
  const { data: promotion, isLoading: promotionLoading, error: promotionError } = useApiData(fetchPromotion, [promotionId]);
  const { data: categoriesData, isLoading: categoriesLoading } = useApiData(fetchCategories, [targetBoutiqueId]);
  const { data: productsData, isLoading: productsLoading } = useApiData(fetchProducts, [targetBoutiqueId]);
  const categories = categoriesData?.member ?? [];
  const products = productsData?.member ?? [];

  useEffect(() => {
    if (promotion) setForm(promotionFormFromItem(promotion));
  }, [promotion]);

  async function handleSubmit(event: React.FormEvent): Promise<void> {
    event.preventDefault();
    const boutiqueId = resolveFormBoutiqueId(boutique?.id, form.boutiqueId);
    if (!boutiqueId) {
      showNotice('Sélectionnez une boutique.', 'error');
      return;
    }

    setSubmitting(true);
    try {
      const body = {
        boutiqueId,
        name: form.name,
        scope: form.scope,
        categoryIds: form.scope === 'category' && form.categoryId ? [form.categoryId] : [],
        productIds: form.scope === 'product' && form.productId ? [form.productId] : [],
        type: form.type,
        value: form.value,
        startsAt: form.startDate || null,
        endsAt: form.endDate || null,
        active: form.status === 'active',
      };
      if (promotionId) await api.patch(`/promotions/${promotionId}`, body);
      else await api.post('/promotions', body);
      showNotice(promotionId ? 'Promotion mise à jour.' : 'Promotion créée.', 'success');
      navigate('/admin/promotions');
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la sauvegarde.', 'error');
    } finally {
      setSubmitting(false);
    }
  }

  if (promotionError) return <ErrorState message={promotionError} onRetry={() => window.location.reload()} />;
  if (promotionId && promotionLoading) return <LoadingState />;

  return (
    <div>
      <PageHeader
        title={promotionId ? 'Modifier la promotion' : 'Nouvelle promotion'}
        description="Configurez une réduction pour toute la boutique, une catégorie ou un produit."
        actions={<Button variant="secondary" onClick={() => navigate('/admin/promotions')}>Retour aux promotions</Button>}
      />
      <form className="bo-form" onSubmit={handleSubmit}>
        <Card>
          <CardHeader><h3>Paramètres de la promotion</h3></CardHeader>
          <CardBody>
            <div className="bo-form">
              <BoutiqueFormSelect value={form.boutiqueId} onChange={(boutiqueId) => setForm((current) => ({ ...current, boutiqueId, categoryId: '', productId: '' }))} />
              <FormField label="Nom" required><Input required value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} /></FormField>
              <FormField label="Portée de la promotion" required><Select value={form.scope} onChange={(event) => setForm((current) => ({ ...current, scope: event.target.value, categoryId: '', productId: '' }))}><option value="global">Toute la boutique</option><option value="category">Une catégorie</option><option value="product">Un produit</option></Select></FormField>
              {form.scope === 'category' && <FormField label="Catégorie" required><Select required value={form.categoryId} onChange={(event) => setForm((current) => ({ ...current, categoryId: event.target.value }))}><option value="">{categoriesLoading ? 'Chargement...' : 'Sélectionner une catégorie'}</option>{categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</Select></FormField>}
              {form.scope === 'product' && <FormField label="Produit" required><Select required value={form.productId} onChange={(event) => setForm((current) => ({ ...current, productId: event.target.value }))}><option value="">{productsLoading ? 'Chargement...' : 'Sélectionner un produit'}</option>{products.map((product) => <option key={product.id} value={product.id}>{product.name}</option>)}</Select></FormField>}
              <div className="bo-form-row"><FormField label="Type"><Select value={form.type} onChange={(event) => setForm((current) => ({ ...current, type: event.target.value }))}><option value="percentage">Pourcentage</option><option value="fixed_amount">Montant fixe</option></Select></FormField><FormField label="Valeur" required hint={form.type === 'percentage' ? '%' : 'centimes'}><Input type="number" min={0} required value={form.value} onChange={(event) => setForm((current) => ({ ...current, value: Number(event.target.value) }))} /></FormField></div>
              <div className="bo-form-row"><FormField label="Début"><Input type="date" value={form.startDate} onChange={(event) => setForm((current) => ({ ...current, startDate: event.target.value }))} /></FormField><FormField label="Fin"><Input type="date" value={form.endDate} onChange={(event) => setForm((current) => ({ ...current, endDate: event.target.value }))} /></FormField></div>
              <label className="bo-checkbox"><input type="checkbox" checked={form.status === 'active'} onChange={(event) => setForm((current) => ({ ...current, status: event.target.checked ? 'active' : 'inactive' }))} /> Promotion active</label>
            </div>
          </CardBody>
        </Card>
        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 12 }}><Button type="button" variant="secondary" onClick={() => navigate('/admin/promotions')}>Annuler</Button><Button type="submit" disabled={submitting}>{submitting ? 'Enregistrement...' : promotionId ? 'Mettre à jour' : 'Créer la promotion'}</Button></div>
      </form>
    </div>
  );
}
