import { Menu, ShoppingCart, X } from 'lucide-react';
import { useState } from 'react';
import { BoutiqueAccountLink } from '../BoutiqueCustomerAccount';
import { boutiqueLink } from '../boutiqueRouting';
import { ImageWithFallback } from '../../../components/ImageWithFallback';
import { CartSheet, type CartItem } from './CartSheet';
import { FavoritesPopover } from './FavoritesPopover';
import type { StoreBoutique } from './StorefrontTheme';

const navigation = [
  { label: 'Accueil', path: '/' },
  { label: 'Catalogue', path: '/catalogue' },
  { label: 'Promotions', path: '/promotions' },
  { label: 'Avis', path: '/avis' },
  { label: 'A propos', path: '/a-propos' },
  { label: 'Contact', path: '/contact' },
];

type StorefrontHeaderProps = {
  boutique: StoreBoutique;
  showCart?: boolean;
  cartItems?: CartItem[];
  onSetCartQty?: (id: string, qty: number) => void;
  onRemoveCartItem?: (id: string) => void;
  favoriteCount?: number;
  onFavoritesRefresh?: () => void;
  cartOpen?: boolean;
  onCartOpenChange?: (open: boolean) => void;
};

export function StorefrontHeader({ boutique, showCart = true, cartItems, onSetCartQty, onRemoveCartItem, favoriteCount = 0, onFavoritesRefresh, cartOpen, onCartOpenChange }: StorefrontHeaderProps) {
  const [menuOpen, setMenuOpen] = useState(false);

  return (
    <>
      <div className="border-b border-black/8 bg-[#ece5d9] text-[#171717]">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-2 text-xs sm:px-6 lg:px-8">
          <div className="font-medium">Livraison offerte des 60 DT · Retours 30 jours</div>
          <div className="flex items-center gap-4 text-black/65">
            {boutique.email && <a href={`mailto:${boutique.email}`} className="hover:text-black">{boutique.email}</a>}
            <span className="hidden sm:inline">Support 7j/7</span>
          </div>
        </div>
      </div>
      <header className="sticky top-0 z-30 border-b border-black/10 bg-[color:var(--sf-bg,#f6f2eb)]/90 backdrop-blur-xl">
        <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
          <button type="button" onClick={() => setMenuOpen(true)} className="sf-menu-toggle rounded-full p-2 text-[#171717] lg:hidden" aria-label="Menu">
            <Menu className="h-5 w-5" />
          </button>
          <a href={boutiqueLink('/')} className="flex items-center gap-3">
            <ImageWithFallback src={boutique.logoUrl} alt={boutique.logoUrl ? boutique.name : 'Hanooti'} className="h-10 w-10 rounded-full object-cover" />
            <div>
              <div className="text-xs uppercase tracking-[0.28em] text-black/50">{boutique.slogan || 'Boutique'}</div>
              <div className="text-lg font-semibold tracking-tight">{boutique.name}</div>
            </div>
          </a>

          <nav className="sf-desktop-nav hidden items-center gap-8 lg:flex" aria-label="Navigation boutique">
            {navigation.map((item) => <a key={item.path} href={boutiqueLink(item.path)} className="text-sm font-medium text-black/70 transition hover:text-black">{item.label}</a>)}
          </nav>

          <div className="flex items-center gap-2">
            {boutique.wishlistEnabled === true && <FavoritesPopover boutiqueSlug={boutique.slug} favoriteCount={favoriteCount} onRefresh={onFavoritesRefresh} />}
              {showCart && cartItems && onSetCartQty && onRemoveCartItem ? (
                 <CartSheet items={cartItems} onSetQty={onSetCartQty} onRemove={onRemoveCartItem} open={cartOpen} onOpenChange={onCartOpenChange} />
              ) : showCart ? (
                 <a href={boutiqueLink('/cart')} aria-label="Panier" className="relative flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center rounded-full border border-[color:var(--ds-primary)] p-0 text-[color:var(--sf-text-muted,var(--ds-on-surface-variant))] transition-colors hover:bg-[color:var(--sf-surface-muted,var(--ds-surface-container))] hover:text-[color:var(--sf-text,var(--ds-on-surface))] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--sf-accent,var(--ds-primary,#111111))]">
                  <ShoppingCart className="h-5 w-5" />
                </a>
               ) : null}
              {boutique.customerAccountsEnabled !== false && <BoutiqueAccountLink boutiqueSlug={boutique.slug} />}
          </div>
        </div>
      </header>
      {menuOpen && (
        <div className="fixed inset-0 z-50 bg-black/30 backdrop-blur-sm lg:hidden">
          <div className="h-full w-80 max-w-[86vw] bg-[#f6f2eb] px-6 py-8 shadow-2xl">
            <div className="mb-10 flex items-center justify-between">
              <div>
                <div className="text-xs uppercase tracking-[0.28em] text-black/50">Navigation</div>
                <div className="text-xl font-semibold">{boutique.name}</div>
              </div>
              <button type="button" className="rounded-full border border-black/10 p-2" onClick={() => setMenuOpen(false)} aria-label="Fermer le menu">
                <X className="h-4 w-4" />
              </button>
            </div>
            <nav className="space-y-4" aria-label="Navigation mobile">
              {navigation.map((item) => <a key={item.path} href={boutiqueLink(item.path)} className="block text-lg font-medium text-black/80">{item.label}</a>)}
            </nav>
          </div>
        </div>
      )}
    </>
  );
}
