import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import type { Category, Product, ProductFilter } from '../../types';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { useBoutique } from '../../hooks/useBoutique';
import { useNotification } from '../../hooks/useNotification';
import { BoutiqueFormSelect, resolveFormBoutiqueId } from '../../components/BoutiqueFormSelect';
import { MediaField } from '../../components/MediaField';
import { MultiSelect } from '../../components/MultiSelect';
import { Card, CardBody, CardHeader } from '../../components/Card';
import { Button } from '../../components/Button';
import { Badge } from '../../components/Badge';
import { FormField, Input, Select, Textarea } from '../../components/FormField';
import { LoadingState, EmptyState, ErrorState } from '../../components/States';
import { PageHeader } from '../../layout/Shell';

type ProductFormMode = 'create' | 'edit' | 'detail';

type ProductFormPageProps = {
  getAccessToken: () => string | null;
  productId?: string;
  mode: ProductFormMode;
};

type ProductForm = {
  boutiqueId: string;
  name: string;
  sku: string;
  description: string;
  priceCents: number;
  comparePriceCents: number;
  stockQuantity: number;
  lowStockThreshold: number;
  parentCategoryId: string;
  subCategoryId: string;
  isActive: boolean;
  isFeatured: boolean;
};

type PromotionForm = {
  enabled: boolean;
  name: string;
  type: 'percentage' | 'fixed_amount';
  value: number;
  startsAt: string;
  endsAt: string;
};

type VariantRow = {
  key: string;
  attributes: Array<{ name: string; value: string }>;
};

const emptyForm = (): ProductForm => ({
  boutiqueId: '',
  name: '',
  sku: '',
  description: '',
  priceCents: 0,
  comparePriceCents: 0,
  stockQuantity: 0,
  lowStockThreshold: 5,
  parentCategoryId: '',
  subCategoryId: '',
  isActive: true,
  isFeatured: false,
});

const emptyPromotion = (): PromotionForm => ({
  enabled: false,
  name: '',
  type: 'percentage',
  value: 0,
  startsAt: '',
  endsAt: '',
});

function slugify(value: string): string {
  return value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
}

type ProductImage = NonNullable<Product['images']>[number];

function imageUrl(image: ProductImage): string {
  return typeof image === 'string' ? image : image?.url ?? image?.largeUrl ?? image?.smallUrl ?? '';
}

function variantKey(attributes: Array<{ name: string; value: string }>): string {
  return attributes.map((attribute) => `${attribute.name}:${attribute.value}`).sort().join('|');
}

function combinations(filters: ProductFilter[], selectedFilterIds: string[], selectedValues: Record<string, string[]>): VariantRow[] {
  const selectedFilters = filters.filter((filter) => selectedFilterIds.includes(filter.id) && (selectedValues[filter.id] ?? []).length > 0);
  if (selectedFilters.length === 0) return [];

  return selectedFilters.reduce<VariantRow[]>((rows, filter) => {
    const values = selectedValues[filter.id] ?? [];
    if (rows.length === 0) {
      return values.map((value) => ({
        key: variantKey([{ name: filter.name, value }]),
        attributes: [{ name: filter.name, value }],
      }));
    }

    return rows.flatMap((row) => values.map((value) => {
      const attributes = [...row.attributes, { name: filter.name, value }];
      return { key: variantKey(attributes), attributes };
    }));
  }, []);
}

export function ProductFormPage({ getAccessToken, productId, mode }: ProductFormPageProps) {
  const api = useApiClient(getAccessToken);
  const navigate = useNavigate();
  const { boutique } = useBoutique();
  const { showNotice } = useNotification();
  const readOnly = mode === 'detail';
  const [form, setForm] = useState<ProductForm>(emptyForm);
  const [images, setImages] = useState<string[]>([]);
  const [defaultImageIndex, setDefaultImageIndex] = useState(0);
  const [selectedFilterIds, setSelectedFilterIds] = useState<string[]>([]);
  const [selectedFilterValues, setSelectedFilterValues] = useState<Record<string, string[]>>({});
  const [variantStocks, setVariantStocks] = useState<Record<string, number>>({});
  const [promotion, setPromotion] = useState<PromotionForm>(emptyPromotion);
  const [submitting, setSubmitting] = useState(false);

  const targetBoutiqueId = boutique?.id ?? form.boutiqueId;
  const catalogQuery = targetBoutiqueId ? `?boutiqueId=${encodeURIComponent(targetBoutiqueId)}&itemsPerPage=100` : '';

  const fetchCategories = useCallback(
    () => targetBoutiqueId ? api.getCollection<Category>(`/categories${catalogQuery}`) : Promise.resolve({ member: [], totalItems: 0 }),
    [api, catalogQuery, targetBoutiqueId],
  );
  const fetchFilters = useCallback(
    () => targetBoutiqueId ? api.getCollection<ProductFilter>(`/filters${catalogQuery}`) : Promise.resolve({ member: [], totalItems: 0 }),
    [api, catalogQuery, targetBoutiqueId],
  );
  const fetchProduct = useCallback(
    () => productId ? api.get<Product>(`/products/${productId}`) : Promise.resolve(null),
    [api, productId],
  );
  const { data: categoriesData } = useApiData(fetchCategories, [targetBoutiqueId]);
  const { data: filtersData } = useApiData(fetchFilters, [targetBoutiqueId]);
  const { data: product, isLoading: productLoading, error: productError } = useApiData(fetchProduct, [productId]);

  const categories = categoriesData?.member ?? [];
  const filters = filtersData?.member ?? [];
  const rootCategories = categories.filter((category) => !category.parentId);
  const subCategories = categories.filter((category) => category.parentId === form.parentCategoryId);
  const variantRows = useMemo(() => combinations(filters, selectedFilterIds, selectedFilterValues), [filters, selectedFilterIds, selectedFilterValues]);
  const filterValuesPayload = useMemo(
    () => Object.fromEntries(selectedFilterIds
      .map((filterId) => [filterId, selectedFilterValues[filterId] ?? []])
      .filter(([, values]) => values.length > 0)),
    [selectedFilterIds, selectedFilterValues],
  );
  const totalVariantStock = variantRows.reduce((sum, row) => sum + (variantStocks[row.key] ?? 0), 0);

  useEffect(() => {
    if (!product) return;
    const productCategory = categories.find((category) => category.id === product.categoryId);
    const selectedValues: Record<string, string[]> = {};
    (product.filterValues ?? []).forEach((filterValue) => {
      selectedValues[filterValue.filterId] = [...(selectedValues[filterValue.filterId] ?? []), filterValue.value];
    });

    const stocks: Record<string, number> = {};
    (product.variants ?? []).forEach((variant) => {
      const attributes = variant.attributes ?? [];
      stocks[variantKey(attributes)] = variant.quantity ?? 0;
    });

    setForm({
      boutiqueId: product.boutiqueId ?? '',
      name: product.name,
      sku: product.sku ?? '',
      description: product.description ?? '',
      priceCents: product.sellingPrice ?? product.priceCents ?? 0,
      comparePriceCents: product.comparePrice ?? product.comparePriceCents ?? 0,
      stockQuantity: product.stockQuantity,
      lowStockThreshold: product.lowStockThreshold,
      parentCategoryId: productCategory?.parentId ?? productCategory?.id ?? '',
      subCategoryId: productCategory?.parentId ? productCategory.id : '',
      isActive: product.status === 'ACTIVE',
      isFeatured: product.isFeatured,
    });
    const productImages = (product.images ?? []).map(imageUrl).filter(Boolean);
    const selectedImageIndex = (product.images ?? []).findIndex((image) => typeof image !== 'string' && image.isDefault === true);
    setImages(productImages);
    setDefaultImageIndex(selectedImageIndex >= 0 ? selectedImageIndex : 0);
    setSelectedFilterIds(Object.keys(selectedValues).filter((filterId) => selectedValues[filterId].length > 0));
    setSelectedFilterValues(selectedValues);
    setVariantStocks(stocks);
  }, [categories, product]);

  function selectFilterIds(nextIds: string[]): void {
    setSelectedFilterIds(nextIds);
    setSelectedFilterValues((current) => Object.fromEntries(
      Object.entries(current).filter(([filterId]) => nextIds.includes(filterId)),
    ));
  }

  function updateImage(index: number, value: string) {
    setImages((current) => current.map((image, imageIndex) => imageIndex === index ? value : image));
  }

  function removeImage(index: number) {
    setImages((current) => current.filter((_, imageIndex) => imageIndex !== index));
    setDefaultImageIndex((current) => current === index ? 0 : current > index ? current - 1 : current);
  }

  function categoryChanged(parentCategoryId: string) {
    setForm((current) => ({ ...current, parentCategoryId, subCategoryId: '' }));
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (readOnly) return;
    const boutiqueId = resolveFormBoutiqueId(boutique?.id, form.boutiqueId);
    if (!boutiqueId) {
      showNotice('Sélectionnez une boutique.', 'error');
      return;
    }

    setSubmitting(true);
    try {
      const categoryId = form.subCategoryId || form.parentCategoryId || null;
      const savedProduct = productId
        ? await api.patch<Product>(`/products/${productId}`, {
          boutiqueId,
          name: form.name,
          slug: slugify(form.name),
          sku: form.sku || slugify(form.name),
          description: form.description || null,
          sellingPrice: form.priceCents,
          comparePrice: form.comparePriceCents,
          currency: 'TND',
          status: form.isActive ? 'ACTIVE' : 'INACTIVE',
          stockQuantity: variantRows.length > 0 ? totalVariantStock : form.stockQuantity,
          lowStockThreshold: form.lowStockThreshold,
          categoryId,
          categoryIds: categoryId ? [categoryId] : [],
          isFeatured: form.isFeatured,
           images: images.filter(Boolean),
           defaultImageUrl: images[defaultImageIndex] ?? images[0] ?? null,
            filterValues: filterValuesPayload,
          variants: variantRows.map((row, index) => ({
            sku: `${form.sku || slugify(form.name)}-${index + 1}`,
            sellingPrice: form.priceCents,
            comparePrice: form.comparePriceCents,
            quantity: variantStocks[row.key] ?? 0,
            image: images[index] || null,
            isDefault: index === 0,
            attributes: row.attributes,
          })),
        })
        : await api.post<Product>('/products', {
          boutiqueId,
          name: form.name,
          slug: slugify(form.name),
          sku: form.sku || slugify(form.name),
          description: form.description || null,
          sellingPrice: form.priceCents,
          comparePrice: form.comparePriceCents,
          currency: 'TND',
          status: form.isActive ? 'ACTIVE' : 'INACTIVE',
          stockQuantity: variantRows.length > 0 ? totalVariantStock : form.stockQuantity,
          lowStockThreshold: form.lowStockThreshold,
          categoryId,
          categoryIds: categoryId ? [categoryId] : [],
          isFeatured: form.isFeatured,
           images: images.filter(Boolean),
           defaultImageUrl: images[defaultImageIndex] ?? images[0] ?? null,
            filterValues: filterValuesPayload,
          variants: variantRows.map((row, index) => ({
            sku: `${form.sku || slugify(form.name)}-${index + 1}`,
            sellingPrice: form.priceCents,
            comparePrice: form.comparePriceCents,
            quantity: variantStocks[row.key] ?? 0,
            image: images[index] || null,
            isDefault: index === 0,
            attributes: row.attributes,
          })),
        });

      const savedProductId = savedProduct?.id ?? productId;
      if (promotion.enabled && savedProductId) {
        await api.post('/promotions', {
          boutiqueId,
          name: promotion.name || `Promotion ${form.name}`,
          scope: 'product',
          productIds: [savedProductId],
          categoryIds: [],
          type: promotion.type,
          value: promotion.value,
          startsAt: promotion.startsAt || null,
          endsAt: promotion.endsAt || null,
          active: true,
        });
      }

      showNotice(productId ? 'Produit mis à jour.' : 'Produit créé.', 'success');
      navigate('/admin/products');
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la sauvegarde.', 'error');
    } finally {
      setSubmitting(false);
    }
  }

  if (productId && productLoading) return <LoadingState />;
  if (productError) return <ErrorState message={productError} onRetry={() => window.location.reload()} />;

  return (
    <div>
      <PageHeader
        title={readOnly ? 'Détail du produit' : productId ? 'Modifier le produit' : 'Nouveau produit'}
        description={readOnly ? 'Consultez les informations du produit.' : 'Configurez le produit, ses variantes, son stock et ses médias.'}
        actions={readOnly ? <Button onClick={() => navigate(`/admin/products/${productId}/edit`)}>Modifier</Button> : undefined}
      />

      <form className="bo-form" onSubmit={handleSubmit}>
        <Card>
          <CardHeader><h3>Informations générales</h3></CardHeader>
          <CardBody>
            <div className="bo-form">
              {!readOnly && <BoutiqueFormSelect value={form.boutiqueId} onChange={(boutiqueId) => setForm((current) => ({ ...current, boutiqueId }))} />}
              <div className="bo-form-row">
                <FormField label="Nom" required><Input disabled={readOnly} required value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} /></FormField>
                <FormField label="SKU"><Input disabled={readOnly} value={form.sku} onChange={(event) => setForm((current) => ({ ...current, sku: event.target.value }))} /></FormField>
              </div>
              <FormField label="Description"><Textarea disabled={readOnly} value={form.description} onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))} /></FormField>
              <div className="bo-form-row">
                <FormField label="Prix (centimes)" required><Input disabled={readOnly} type="number" min={0} required value={form.priceCents} onChange={(event) => setForm((current) => ({ ...current, priceCents: Number(event.target.value) }))} /></FormField>
                <FormField label="Prix comparatif"><Input disabled={readOnly} type="number" min={0} value={form.comparePriceCents} onChange={(event) => setForm((current) => ({ ...current, comparePriceCents: Number(event.target.value) }))} /></FormField>
              </div>
              <div className="bo-form-row">
                <FormField label="Catégorie"><Select disabled={readOnly} value={form.parentCategoryId} onChange={(event) => categoryChanged(event.target.value)}><option value="">Sans catégorie</option>{rootCategories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</Select></FormField>
                <FormField label="Sous-catégorie (optionnel)"><Select disabled={readOnly || !form.parentCategoryId} value={form.subCategoryId} onChange={(event) => setForm((current) => ({ ...current, subCategoryId: event.target.value }))}><option value="">Aucune sous-catégorie</option>{subCategories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</Select></FormField>
              </div>
              <div style={{ display: 'flex', gap: 20 }}>
                <label className="bo-checkbox"><input disabled={readOnly} type="checkbox" checked={form.isActive} onChange={(event) => setForm((current) => ({ ...current, isActive: event.target.checked }))} /> Actif</label>
                <label className="bo-checkbox"><input disabled={readOnly} type="checkbox" checked={form.isFeatured} onChange={(event) => setForm((current) => ({ ...current, isFeatured: event.target.checked }))} /> Mis en avant</label>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader><h3>Images du produit</h3></CardHeader>
          <CardBody>
            {readOnly ? (
              images.length > 0 ? (
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(150px, 1fr))', gap: 12 }}>
                  {images.map((image, index) => <div key={`${image}-${index}`} style={{ position: 'relative' }}>
                    <img src={image} alt={`${form.name} ${index + 1}`} style={{ width: '100%', height: 150, borderRadius: 10, objectFit: 'cover', border: index === defaultImageIndex ? '2px solid var(--bo-primary)' : '1px solid var(--bo-border)' }} />
                    {index === defaultImageIndex && <Badge tone="success">Image par défaut</Badge>}
                  </div>)}
                </div>
              ) : <EmptyState title="Aucune image" message="Ce produit ne possède pas encore d’image." />
            ) : (
              <>
                <div className="bo-product-media-grid">
                  {images.map((image, index) => <div key={`${image}-${index}`} style={{ border: index === defaultImageIndex ? '2px solid var(--bo-primary)' : '1px solid var(--bo-border)', borderRadius: 12, padding: 10 }}>
                    <MediaField label={`Image ${index + 1}`} value={image} boutiqueId={targetBoutiqueId} context="products" onChange={(value) => updateImage(index, value)} />
                    <label className="bo-checkbox" style={{ margin: '10px 0' }}>
                      <input type="radio" name="defaultProductImage" checked={index === defaultImageIndex} onChange={() => setDefaultImageIndex(index)} />
                      Image par défaut
                    </label>
                    <Button type="button" variant="danger" size="sm" onClick={() => removeImage(index)}>Supprimer cette image</Button>
                  </div>)}
                </div>
                <Button type="button" variant="secondary" onClick={() => setImages((current) => [...current, ''])}>+ Ajouter une image</Button>
              </>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardHeader><h3>Collections de filtres et variantes</h3></CardHeader>
          <CardBody>
            {filters.length === 0 ? <p style={{ color: 'var(--bo-text-muted)' }}>Aucun filtre configuré pour cette boutique.</p> : (
              <>
                <FormField label="Filtres de variantes" hint="Cliquez sur les filtres pour les sélectionner ou les désélectionner.">
                  <MultiSelect
                    options={filters.map((filter) => ({ value: filter.id, label: filter.name }))}
                    value={selectedFilterIds}
                    onChange={setSelectedFilterIds}
                    disabled={readOnly}
                    ariaLabel="Filtres de variantes sélectionnés"
                  />
                </FormField>
                {selectedFilterIds.length === 0 && <p style={{ marginTop: 12, color: 'var(--bo-text-muted)', fontSize: 13 }}>Sélectionnez les filtres qui définissent les variantes du produit.</p>}
              </>
            )}
            {selectedFilterIds.length > 0 && (
              <div className="bo-product-filter-grid">
                {filters.filter((filter) => selectedFilterIds.includes(filter.id)).map((filter) => <fieldset className="bo-product-filter-card" key={filter.id}>
                  <legend>{filter.name}</legend>
                  {filter.values.length === 0 ? <small>Aucune valeur disponible</small> : (
                    <>
                       <MultiSelect
                         options={filter.values.map((value) => ({ value: value.value, label: value.value }))}
                         value={selectedFilterValues[filter.id] ?? []}
                         onChange={(values) => setSelectedFilterValues((current) => ({ ...current, [filter.id]: values }))}
                         disabled={readOnly}
                         ariaLabel={`Valeurs du filtre ${filter.name}`}
                       />
                      {(selectedFilterValues[filter.id] ?? []).length > 0 && (
                        <div className="bo-selected-filter-values" aria-label={`Valeurs sélectionnées pour ${filter.name}`}>
                          {(selectedFilterValues[filter.id] ?? []).map((value) => <span className="bo-variant-chip" key={`${filter.id}-${value}`}>{value}</span>)}
                        </div>
                      )}
                    </>
                  )}
                </fieldset>)}
              </div>
            )}
            {variantRows.length > 0 && <div style={{ marginTop: 20 }}>
              <h4>Stock par combinaison</h4>
              <div className="bo-variant-list">
                {variantRows.map((row) => <div className="bo-variant-row" key={row.key}><div>{row.attributes.map((attribute) => <span className="bo-variant-chip" key={`${attribute.name}-${attribute.value}`}>{attribute.name}: {attribute.value}</span>)}</div><Input disabled={readOnly} type="number" min={0} aria-label={`Stock ${row.key}`} value={variantStocks[row.key] ?? 0} onChange={(event) => setVariantStocks((current) => ({ ...current, [row.key]: Number(event.target.value) }))} /></div>)}
              </div>
              <p style={{ color: 'var(--bo-text-muted)', fontSize: 13 }}>Stock total des variantes : <strong>{totalVariantStock}</strong></p>
            </div>}
          </CardBody>
        </Card>

        {!readOnly && <Card>
          <CardHeader><h3>Promotion optionnelle</h3></CardHeader>
          <CardBody>
            <label className="bo-checkbox"><input type="checkbox" checked={promotion.enabled} onChange={(event) => setPromotion((current) => ({ ...current, enabled: event.target.checked }))} /> Créer une promotion pour ce produit</label>
            {promotion.enabled && <div className="bo-form" style={{ marginTop: 16 }}>
              <FormField label="Nom de la promotion"><Input value={promotion.name} placeholder={`Promotion ${form.name || 'produit'}`} onChange={(event) => setPromotion((current) => ({ ...current, name: event.target.value }))} /></FormField>
              <div className="bo-form-row"><FormField label="Type"><Select value={promotion.type} onChange={(event) => setPromotion((current) => ({ ...current, type: event.target.value as PromotionForm['type'] }))}><option value="percentage">Pourcentage</option><option value="fixed_amount">Montant fixe</option></Select></FormField><FormField label="Valeur" hint={promotion.type === 'percentage' ? '%' : 'centimes'}><Input type="number" min={0} value={promotion.value} onChange={(event) => setPromotion((current) => ({ ...current, value: Number(event.target.value) }))} /></FormField></div>
              <div className="bo-form-row"><FormField label="Début"><Input type="date" value={promotion.startsAt} onChange={(event) => setPromotion((current) => ({ ...current, startsAt: event.target.value }))} /></FormField><FormField label="Fin"><Input type="date" value={promotion.endsAt} onChange={(event) => setPromotion((current) => ({ ...current, endsAt: event.target.value }))} /></FormField></div>
            </div>}
          </CardBody>
        </Card>}

        {!readOnly && <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 12 }}><Button type="button" variant="secondary" onClick={() => navigate('/admin/products')}>Annuler</Button><Button type="submit" disabled={submitting}>{submitting ? 'Enregistrement...' : productId ? 'Mettre à jour' : 'Créer le produit'}</Button></div>}
      </form>
    </div>
  );
}
