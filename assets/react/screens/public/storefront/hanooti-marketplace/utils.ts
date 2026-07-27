import { getProductImageUrl, type StoreProduct } from '../ProductCard';
import type { PageKind, StoreCategory } from './types';

export function buildCategories(products: StoreProduct[]): StoreCategory[] {
  const categories = new Map<string, StoreCategory>();

  products.forEach((product) => {
    const name = product.categoryName || 'Collection';
    const slug = product.categorySlug || slugify(name);
    const image = getProductImageUrl(product);
    const current = categories.get(slug);

    if (current) {
      current.count += 1;
      if (!current.image && image) current.image = image;
      return;
    }

    categories.set(slug, { id: slug, name, slug, parentId: null, count: 1, image, children: [] });
  });

  return Array.from(categories.values());
}

export function findCategoryBySlug(categories: StoreCategory[], slug: string): StoreCategory | null {
  for (const category of categories) {
    if (category.slug === slug) return category;
    const nested = findCategoryBySlug(category.children, slug);
    if (nested) return nested;
  }

  return null;
}

export function withProductCounts(categories: StoreCategory[], products: StoreProduct[]): StoreCategory[] {
  return categories.map((category) => ({
    ...category,
    count: products.filter((product) => productMatchesCategory(product, category)).length,
    children: withProductCounts(category.children, products),
  }));
}

export function findCategoryParent(categories: StoreCategory[], childId: string): StoreCategory | null {
  for (const category of categories) {
    if (category.children.some((child) => child.id === childId)) return category;
    const parent = findCategoryParent(category.children, childId);
    if (parent) return parent;
  }

  return null;
}

export function categoryIds(category: StoreCategory): Set<string> {
  const ids = new Set<string>([category.id]);
  category.children.forEach((child) => {
    categoryIds(child).forEach((id) => ids.add(id));
  });
  return ids;
}

export function productMatchesCategory(product: StoreProduct, category: StoreCategory): boolean {
  const ids = categoryIds(category);
  const productCategoryIds = new Set([product.categoryId, ...(product.categoryIds ?? [])].filter(Boolean));

  if ([...productCategoryIds].some((id) => ids.has(id as string))) return true;

  return product.categorySlug === category.slug;
}

export function filterProducts(products: StoreProduct[], query: string): StoreProduct[] {
  const normalized = query.trim().toLowerCase();
  if (!normalized) return products;

  return products.filter((product) =>
    [product.name, product.categoryName, product.description]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(normalized)),
  );
}

export function isPromotion(product: StoreProduct): boolean {
  return product.isPromoted === true;
}

export function sortProducts(products: StoreProduct[], sort: string): StoreProduct[] {
  return [...products].sort((left, right) => {
    switch (sort) {
      case 'price-asc':
        return left.priceCents - right.priceCents;
      case 'price-desc':
        return right.priceCents - left.priceCents;
      case 'name':
        return left.name.localeCompare(right.name, 'fr');
      case 'rating':
        return (right.rating ?? 0) - (left.rating ?? 0);
      case 'newest':
        return String(right.createdAt ?? '').localeCompare(String(left.createdAt ?? ''));
      default:
        return 0;
    }
  });
}

export function formatPrice(product: StoreProduct): string {
  return formatMoney(product.priceCents, product.currency);
}

export function formatMoney(cents: number, currency: string): string {
  return `${(cents / 100).toFixed(2)} ${currency}`;
}

export function resolvePage(): PageKind {
  const path = window.location.pathname;
  if (path.includes('/categories/')) return 'category';
  if (path.endsWith('/catalogue')) return 'catalogue';
  if (path.endsWith('/promotions')) return 'promotions';
  if (path.endsWith('/avis')) return 'reviews';
  if (path.endsWith('/a-propos')) return 'about';
  if (path.endsWith('/contact')) return 'contact';
  if (path.includes('/pages/')) return 'cms';
  return 'home';
}

export function resolvePathParam(segment: string): string {
  const match = window.location.pathname.match(new RegExp(`/${segment}/([^/]+)`));
  return match?.[1] ?? '';
}

export function slugify(value: string): string {
  return value
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'collection';
}
