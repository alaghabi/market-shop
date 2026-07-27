export type Promotion = {
  id: string;
  boutiqueId?: string;
  name: string;
  scope: string;
  type: string;
  value: number;
  active: boolean;
  categoryIds?: string[];
  productIds?: string[];
  startsAt?: string;
  endsAt?: string;
  createdAt: string;
};

export type PromotionFormState = {
  boutiqueId: string;
  name: string;
  scope: string;
  categoryId: string;
  productId: string;
  type: string;
  value: number;
  startDate: string;
  endDate: string;
  status: 'active' | 'inactive';
};

export const emptyPromotionForm = (): PromotionFormState => ({
  boutiqueId: '',
  name: '',
  scope: 'global',
  categoryId: '',
  productId: '',
  type: 'percentage',
  value: 0,
  startDate: '',
  endDate: '',
  status: 'active',
});

export function promotionFormFromItem(item: Promotion): PromotionFormState {
  return {
    boutiqueId: item.boutiqueId ?? '',
    name: item.name,
    scope: item.scope ?? 'global',
    categoryId: item.categoryIds?.[0] ?? '',
    productId: item.productIds?.[0] ?? '',
    type: item.type,
    value: item.value,
    startDate: item.startsAt?.slice(0, 10) ?? '',
    endDate: item.endsAt?.slice(0, 10) ?? '',
    status: item.active ? 'active' : 'inactive',
  };
}
