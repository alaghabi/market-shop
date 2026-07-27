export type Announcement = {
  id: string;
  boutiqueId?: string | null;
  title?: string | null;
  subtitle?: string | null;
  content: string;
  displayType: string;
  backgroundColor?: string | null;
  textColor?: string | null;
  borderColor?: string | null;
  linkUrl?: string | null;
  displayMode: string;
  position: string;
  displayPages: string[];
  categoryIds: string[];
  productIds: string[];
  priority: number;
  active: boolean;
  isDismissible: boolean;
  startsAt?: string | null;
  endsAt?: string | null;
};

export type AnnouncementFormState = {
  boutiqueId: string;
  title: string;
  subtitle: string;
  content: string;
  displayType: string;
  backgroundColor: string;
  textColor: string;
  borderColor: string;
  linkUrl: string;
  displayMode: string;
  position: string;
  displayPages: string[];
  target: 'all' | 'category' | 'product';
  categoryId: string;
  productId: string;
  priority: number;
  active: boolean;
  isDismissible: boolean;
  startsAt: string;
  endsAt: string;
};

export const emptyAnnouncementForm = (): AnnouncementFormState => ({
  boutiqueId: '',
  title: '',
  subtitle: '',
  content: '',
  displayType: 'HOME_SLIDER',
  backgroundColor: '#3525cd',
  textColor: '#ffffff',
  borderColor: '#3525cd',
  linkUrl: '',
  displayMode: 'SLIDER',
  position: 'HOME_TOP',
  displayPages: ['all'],
  target: 'all',
  categoryId: '',
  productId: '',
  priority: 0,
  active: true,
  isDismissible: true,
  startsAt: '',
  endsAt: '',
});

export function announcementFormFromItem(item: Announcement): AnnouncementFormState {
  const target = item.productIds?.length ? 'product' : item.categoryIds?.length ? 'category' : 'all';

  return {
    boutiqueId: item.boutiqueId ?? '',
    title: item.title ?? '',
    subtitle: item.subtitle ?? '',
    content: item.content,
    displayType: item.displayType,
    backgroundColor: item.backgroundColor ?? '#3525cd',
    textColor: item.textColor ?? '#ffffff',
    borderColor: item.borderColor ?? '#3525cd',
    linkUrl: item.linkUrl ?? '',
    displayMode: item.displayMode,
    position: item.position,
    displayPages: item.displayPages ?? ['all'],
    target,
    categoryId: item.categoryIds?.[0] ?? '',
    productId: item.productIds?.[0] ?? '',
    priority: item.priority,
    active: item.active,
    isDismissible: item.isDismissible,
    startsAt: item.startsAt?.slice(0, 10) ?? '',
    endsAt: item.endsAt?.slice(0, 10) ?? '',
  };
}
