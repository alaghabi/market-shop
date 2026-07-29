export type Boutique = {
  id: string;
  name: string;
  slug: string;
  status: string;
  contactEmail?: string | null;
  customDomain?: string | null;
  isPublished?: boolean;
  isVisiblePublicly?: boolean;
  productsCount?: number;
  usersCount?: number;
};

export type UserProfile = {
  email: string;
  displayName: string | null;
  roles: string[];
  boutiqueId: string | null;
  boutiqueName: string | null;
};

export type NavItem = {
  slug: string;
  title: string;
  path: string;
  section: string;
  description: string;
  icon: string;
  permissions: string[];
  module?: string;
  moduleAliases?: string[];
  requiredPermissions?: string[];
};

export type BoutiqueModuleAccess = {
  code: string;
  name: string;
  globallyEnabled: boolean;
  allowedBySubscription: boolean;
  enabledInBoutique: boolean;
  accessible: boolean;
};

export type BackOfficeAccess = {
  globalModules: Record<string, boolean>;
  boutiqueModules: Record<string, BoutiqueModuleAccess>;
  permissions: string[];
  roles: string[];
};

export type Product = {
  id: string;
  boutiqueId?: string;
  name: string;
  slug: string;
  sku?: string | null;
  description?: string | null;
  priceCents?: number;
  sellingPrice?: number;
  comparePriceCents?: number | null;
  comparePrice?: number | null;
  currency: string;
  stockQuantity: number;
  lowStockThreshold: number;
  categoryId?: string | null;
  categoryName?: string | null;
  status?: string;
  isActive?: boolean;
  isFeatured: boolean;
  viewsCount?: number;
  images?: Array<string | { url?: string | null; smallUrl?: string | null; largeUrl?: string | null; alt?: string | null; isDefault?: boolean }>;
  variants?: Array<{
    id?: string;
    sku?: string | null;
    sellingPrice?: number;
    comparePrice?: number;
    quantity?: number;
    image?: string | null;
    isDefault?: boolean;
    isActive?: boolean;
    attributes?: Array<{ name: string; value: string }>;
  }>;
  filterValues?: Array<{ filterId: string; filterName: string; filterSlug?: string; value: string }>;
  createdAt?: string;
  updatedAt: string;
};

export type Category = {
  id: string;
  name: string;
  slug: string;
  boutiqueId: string;
  isActive: boolean;
  isFeatured: boolean;
  productsCount: number;
  children: Category[];
  parentId?: string | null;
  image?: string | null;
  banner?: string | null;
  createdAt: string;
};

export type ProductFilter = {
  id: string;
  boutiqueId?: string;
  name: string;
  slug: string;
  type: string;
  position: number;
  active: boolean;
  values: Array<{ id: string; value: string }>;
};

export type Order = {
  id: string;
  boutiqueId?: string;
  customerId?: string;
  channel: string;
  status: string;
  subtotalCents: number;
  discountCents: number;
  totalCents: number;
  currency: string;
  customerName?: string;
  customerEmail?: string;
  customerPhone?: string | null;
  itemsCount?: number;
  items?: Array<{
    productId?: string | null;
    productName?: string | null;
    productImage?: string | null;
    sku?: string;
    quantity: number;
    unitPriceCents: number;
    totalCents?: number;
    variantId?: string | null;
    variantAttributes?: Array<{ name: string; value: string }>;
  }>;
  shippingAddress?: string | null;
  shippingCity?: string | null;
  shippingPostalCode?: string | null;
  shippingCountry?: string | null;
  shippingGovernorate?: string | null;
  shippingLocality?: string | null;
  deliveryStatus?: string | null;
  deliveryError?: string | null;
  paymentStatus?: string;
  paymentMethodCode?: string | null;
  deliveryTracking?: string | null;
  shipmentId?: string | null;
  shipmentStatus?: string | null;
  deliveryCompanyName?: string | null;
  deliveryLabelUrl?: string | null;
  deliveredAt?: string | null;
  createdAt: string;
};

export type Customer = {
  id: string;
  email: string;
  firstName?: string;
  lastName?: string;
  phone?: string;
  active?: boolean;
  ordersCount?: number;
  totalSpentCents?: number;
  createdAt: string;
};

export type PaginatedResponse<T> = {
  '@context'?: string;
  '@id'?: string;
  '@type'?: string;
  totalItems: number;
  member: T[];
  items?: T[];
};

export type NoticeType = 'success' | 'error' | 'warning' | 'info';

export type Notice = {
  message: string;
  type: NoticeType;
};

export type PageMeta = {
  title: string;
  description: string;
  section: string;
  icon: string;
};

export type SortConfig = {
  field: string;
  direction: 'asc' | 'desc';
};

export type FilterConfig = {
  search: string;
  status?: string;
  categoryId?: string;
  sort?: SortConfig;
};

export type TableColumn<T> = {
  key: string;
  label: string;
  sortable?: boolean;
  render: (item: T) => React.ReactNode;
  width?: string;
};

export type DashboardWidget = {
  title: string;
  value: string | number;
  change?: number;
  icon: string;
  color: string;
};

export type QuotaInfo = {
  code: string;
  name: string;
  unit: string | null;
  limit: number | null;
  usage: number;
  remaining: number | null;
};

export type SubscriptionSummary = {
  boutiqueId: string | null;
  isActive: boolean;
  planId: string | null;
  planName: string | null;
  priceTnd: number;
  currency: string;
  startDate: string | null;
  endDate: string | null;
  daysRemaining: number | null;
  quotas: QuotaInfo[];
  accessibleModules: string[] | Record<string, string>;
  activeExtensions: Array<{ extensionCode: string; extensionName: string; type: string }>;
};

export type BellNotification = {
  id: string;
  title: string;
  message: string;
  boutiqueId?: string | null;
  type: 'info' | 'success' | 'warning' | 'error';
  read: boolean;
  createdAt: string;
  link?: string;
};
