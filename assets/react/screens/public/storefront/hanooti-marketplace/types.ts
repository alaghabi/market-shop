import type { StoreProduct } from '../ProductCard';

export type CartItem = { product: StoreProduct; qty: number; itemId?: string };
export type PageKind = 'home' | 'catalogue' | 'category' | 'promotions' | 'reviews' | 'about' | 'contact';
export type { StoreCategory, StoreFilter } from '../catalogueTypes';
