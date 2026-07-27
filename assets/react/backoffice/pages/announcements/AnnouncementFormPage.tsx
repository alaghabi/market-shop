import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import type { Category, Product } from '../../types';
import { useApiClient, useApiData } from '../../hooks/useApi';
import { useBoutique } from '../../hooks/useBoutique';
import { useNotification } from '../../hooks/useNotification';
import { BoutiqueFormSelect, resolveFormBoutiqueId } from '../../components/BoutiqueFormSelect';
import { Card, CardBody, CardHeader } from '../../components/Card';
import { Button } from '../../components/Button';
import { FormField, Input, Select, Textarea } from '../../components/FormField';
import { LoadingState, ErrorState } from '../../components/States';
import { PageHeader } from '../../layout/Shell';
import { announcementFormFromItem, emptyAnnouncementForm, type Announcement, type AnnouncementFormState } from './announcementTypes';

export function AnnouncementFormPage({
  getAccessToken,
  announcementId,
}: {
  getAccessToken: () => string | null;
  announcementId?: string;
}) {
  const api = useApiClient(getAccessToken);
  const navigate = useNavigate();
  const { boutique } = useBoutique();
  const { showNotice } = useNotification();
  const [form, setForm] = useState<AnnouncementFormState>(emptyAnnouncementForm);
  const [submitting, setSubmitting] = useState(false);
  const targetBoutiqueId = boutique?.id ?? form.boutiqueId;
  const query = targetBoutiqueId ? `?boutiqueId=${encodeURIComponent(targetBoutiqueId)}&itemsPerPage=100` : '';

  const fetchAnnouncement = useCallback(
    () => announcementId ? api.get<Announcement>(`/announcements/${announcementId}`) : Promise.resolve(null),
    [announcementId, api],
  );
  const fetchCategories = useCallback(
    () => targetBoutiqueId ? api.getCollection<Category>(`/categories${query}`) : Promise.resolve({ member: [], totalItems: 0 }),
    [api, query, targetBoutiqueId],
  );
  const fetchProducts = useCallback(
    () => targetBoutiqueId ? api.getCollection<Product>(`/products${query}`) : Promise.resolve({ member: [], totalItems: 0 }),
    [api, query, targetBoutiqueId],
  );
  const { data: announcement, isLoading: announcementLoading, error: announcementError } = useApiData(fetchAnnouncement, [announcementId]);
  const { data: categoriesData } = useApiData(fetchCategories, [targetBoutiqueId]);
  const { data: productsData } = useApiData(fetchProducts, [targetBoutiqueId]);
  const categories = categoriesData?.member ?? [];
  const products = productsData?.member ?? [];

  useEffect(() => {
    if (announcement) setForm(announcementFormFromItem(announcement));
  }, [announcement]);

  function togglePage(page: string): void {
    setForm((current) => ({
      ...current,
      displayPages: current.displayPages.includes(page)
        ? current.displayPages.filter((item) => item !== page)
        : [...current.displayPages.filter((item) => item !== 'all'), page],
    }));
  }

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
        title: form.title || null,
        subtitle: form.subtitle || null,
        content: form.content,
        description: form.content,
        displayType: form.displayType,
        type: form.displayType,
        backgroundColor: form.backgroundColor,
        textColor: form.textColor,
        borderColor: form.borderColor,
        linkUrl: form.linkUrl || null,
        displayMode: form.displayMode,
        position: form.position,
        displayPages: form.displayPages,
        categoryIds: form.target === 'category' && form.categoryId ? [form.categoryId] : [],
        productIds: form.target === 'product' && form.productId ? [form.productId] : [],
        priority: form.priority,
        active: form.active,
        isDismissible: form.isDismissible,
        isGlobal: false,
        startsAt: form.startsAt || null,
        endsAt: form.endsAt || null,
      };

      if (announcementId) await api.patch(`/announcements/${announcementId}`, body);
      else await api.post('/announcements', body);
      showNotice(announcementId ? 'Annonce mise à jour.' : 'Annonce créée.', 'success');
      navigate('/admin/announcements');
    } catch (error) {
      showNotice(error instanceof Error ? error.message : 'Erreur lors de la sauvegarde.', 'error');
    } finally {
      setSubmitting(false);
    }
  }

  if (announcementError) return <ErrorState message={announcementError} onRetry={() => window.location.reload()} />;
  if (announcementId && announcementLoading) return <LoadingState />;

  return (
    <div>
      <PageHeader
        title={announcementId ? 'Modifier l’annonce' : 'Nouvelle annonce'}
        description="Configurez le message, son affichage et son ciblage."
        actions={<Button variant="secondary" onClick={() => navigate('/admin/announcements')}>Retour aux annonces</Button>}
      />

      <form className="bo-form" onSubmit={handleSubmit}>
        <Card>
          <CardHeader><h3>Contenu de l’annonce</h3></CardHeader>
          <CardBody>
            <div className="bo-form">
              <BoutiqueFormSelect value={form.boutiqueId} onChange={(boutiqueId) => setForm((current) => ({ ...current, boutiqueId }))} />
              <div className="bo-form-row">
                <FormField label="Titre"><Input value={form.title} onChange={(event) => setForm((current) => ({ ...current, title: event.target.value }))} /></FormField>
                <FormField label="Sous-titre"><Input value={form.subtitle} onChange={(event) => setForm((current) => ({ ...current, subtitle: event.target.value }))} /></FormField>
              </div>
              <FormField label="Message" required><Textarea required value={form.content} onChange={(event) => setForm((current) => ({ ...current, content: event.target.value }))} /></FormField>
              <FormField label="Lien de l’annonce" hint="Optionnel. Le clic sur l’annonce ouvrira ce lien."><Input type="url" placeholder="https://... ou /promotions" value={form.linkUrl} onChange={(event) => setForm((current) => ({ ...current, linkUrl: event.target.value }))} /></FormField>
              <div className="bo-announcement-preview" style={{ backgroundColor: form.backgroundColor, color: form.textColor, borderColor: form.borderColor }}>
                <strong>{form.title || form.content || 'Aperçu de votre annonce'}</strong>
                {form.subtitle && <span>{form.subtitle}</span>}
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader><h3>Affichage</h3></CardHeader>
          <CardBody>
            <div className="bo-form">
              <FormField label="Type d’affichage"><Select value={form.displayType} onChange={(event) => setForm((current) => ({ ...current, displayType: event.target.value }))}><option value="HOME_SLIDER">Slider accueil</option><option value="TOP_BAR">Barre supérieure</option><option value="BANNER">Bannière</option><option value="ALERT">Alerte</option><option value="POPUP">Fenêtre popup</option></Select></FormField>
              <div className="bo-form-row"><FormField label="Position storefront"><Select value={form.position} onChange={(event) => setForm((current) => ({ ...current, position: event.target.value }))}><option value="HOME_TOP">Accueil - haut</option><option value="HOME_MIDDLE">Accueil - milieu</option><option value="HOME_BOTTOM">Accueil - bas</option><option value="HEADER_TOP">Header - haut</option><option value="HEADER_BOTTOM">Header - bas</option><option value="FOOTER">Footer</option></Select></FormField><FormField label="Mode d’affichage"><Select value={form.displayMode} onChange={(event) => setForm((current) => ({ ...current, displayMode: event.target.value }))}><option value="SLIDER">Slider défilant</option><option value="FIXED">Fixe</option><option value="SCROLLING">Défilement</option></Select></FormField></div>
              <div className="bo-form-row"><FormField label="Couleur fond"><Input type="color" value={form.backgroundColor} onChange={(event) => setForm((current) => ({ ...current, backgroundColor: event.target.value }))} /></FormField><FormField label="Couleur texte"><Input type="color" value={form.textColor} onChange={(event) => setForm((current) => ({ ...current, textColor: event.target.value }))} /></FormField><FormField label="Couleur bordure"><Input type="color" value={form.borderColor} onChange={(event) => setForm((current) => ({ ...current, borderColor: event.target.value }))} /></FormField></div>
              <FormField label="Pages d’affichage"><div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>{['all', 'home', 'product', 'category', 'cart'].map((page) => <label className="bo-checkbox" key={page}><input type="checkbox" checked={form.displayPages.includes(page)} onChange={() => togglePage(page)} /> {page === 'all' ? 'Toutes' : page === 'home' ? 'Accueil' : page === 'product' ? 'Produit' : page === 'category' ? 'Catégorie' : 'Panier'}</label>)}</div></FormField>
              <div className="bo-form-row"><FormField label="Début"><Input type="date" value={form.startsAt} onChange={(event) => setForm((current) => ({ ...current, startsAt: event.target.value }))} /></FormField><FormField label="Fin"><Input type="date" value={form.endsAt} onChange={(event) => setForm((current) => ({ ...current, endsAt: event.target.value }))} /></FormField></div>
              <div style={{ display: 'flex', gap: 20 }}><label className="bo-checkbox"><input type="checkbox" checked={form.active} onChange={(event) => setForm((current) => ({ ...current, active: event.target.checked }))} /> Active</label><label className="bo-checkbox"><input type="checkbox" checked={form.isDismissible} onChange={(event) => setForm((current) => ({ ...current, isDismissible: event.target.checked }))} /> Fermable par le client</label></div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader><h3>Ciblage</h3></CardHeader>
          <CardBody>
            <div className="bo-form">
              <FormField label="Ciblage"><Select value={form.target} onChange={(event) => setForm((current) => ({ ...current, target: event.target.value as AnnouncementFormState['target'], categoryId: '', productId: '' }))}><option value="all">Toute la boutique</option><option value="category">Une catégorie</option><option value="product">Un produit</option></Select></FormField>
              {form.target === 'category' && <FormField label="Catégorie" required><Select required value={form.categoryId} onChange={(event) => setForm((current) => ({ ...current, categoryId: event.target.value }))}><option value="">Sélectionner</option>{categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</Select></FormField>}
              {form.target === 'product' && <FormField label="Produit" required><Select required value={form.productId} onChange={(event) => setForm((current) => ({ ...current, productId: event.target.value }))}><option value="">Sélectionner</option>{products.map((product) => <option key={product.id} value={product.id}>{product.name}</option>)}</Select></FormField>}
            </div>
          </CardBody>
        </Card>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 12 }}>
          <Button type="button" variant="secondary" onClick={() => navigate('/admin/announcements')}>Annuler</Button>
          <Button type="submit" disabled={submitting}>{submitting ? 'Enregistrement...' : announcementId ? 'Mettre à jour' : 'Créer l’annonce'}</Button>
        </div>
      </form>
    </div>
  );
}
