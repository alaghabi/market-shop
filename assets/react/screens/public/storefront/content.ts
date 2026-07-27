import { boutiqueLink } from '../boutiqueRouting';

export type StorefrontNavigationItem = {
  label?: string;
  href?: string;
  position?: number;
  enabled?: boolean;
};

export type StorefrontBanner = {
  image?: string;
  mobile_image?: string;
  title?: string;
  subtitle?: string;
  button_text?: string;
  button_url?: string;
  active?: boolean;
  position?: number;
};

export type StorefrontSection = {
  type: string;
  enabled: boolean;
  position?: number;
  title?: string;
};

export type StorefrontContent = {
  slogan?: string | null;
  favicon?: string | null;
  heroTitle?: string;
  heroSubtitle?: string;
  socialLinks?: Record<string, string>;
  headerConfig?: Record<string, boolean>;
  footerConfig?: Record<string, string | boolean>;
  navigationItems?: StorefrontNavigationItem[];
  frontOfficePages?: Array<{ slug?: string; label?: string; enabled?: boolean; position?: number }>;
  featuredCategories?: Array<{ categoryId?: string; label?: string; position?: number }>;
  homepageSections?: StorefrontSection[];
  banners?: StorefrontBanner[];
  catalogConfig?: Record<string, unknown>;
  moduleConfig?: Record<string, unknown>;
  orderMode?: string | null;
  maintenanceMessage?: string | null;
};

export type ResolvedStorefrontNavigationItem = {
  label: string;
  href: string;
};

const defaultNavigation = (reviewsEnabled: boolean): ResolvedStorefrontNavigationItem[] => [
  { label: 'Accueil', href: boutiqueLink('/') },
  { label: 'Catalogue', href: boutiqueLink('/catalogue') },
  { label: 'Promotions', href: boutiqueLink('/promotions') },
  ...(reviewsEnabled ? [{ label: 'Avis', href: boutiqueLink('/avis') }] : []),
  { label: 'A propos', href: boutiqueLink('/a-propos') },
  { label: 'Contact', href: boutiqueLink('/contact') },
];

export function resolveStorefrontNavigation(content: StorefrontContent, reviewsEnabled: boolean): ResolvedStorefrontNavigationItem[] {
  const configured = (content.navigationItems ?? [])
    .filter((item) => item.enabled !== false && item.label && item.href)
    .sort((left, right) => (left.position ?? 0) - (right.position ?? 0))
    .map((item) => {
      const href = item.href as string;
      return {
        label: item.label as string,
        href: href.startsWith('/') ? boutiqueLink(href) : href,
      };
    });

  return configured.length > 0 ? configured : defaultNavigation(reviewsEnabled);
}

export function resolveStorefrontHero(content: StorefrontContent, boutiqueName: string): { title: string; subtitle: string; banner?: StorefrontBanner } {
  const banner = (content.banners ?? [])
    .filter((item) => item.active !== false)
    .sort((left, right) => (left.position ?? 0) - (right.position ?? 0))[0];

  return {
    title: banner?.title ?? content.heroTitle ?? content.slogan ?? boutiqueName,
    subtitle: banner?.subtitle ?? content.heroSubtitle ?? 'Une selection soignee de produits pour une experience simple et fiable.',
    banner,
  };
}

export function resolveSectionTitle(content: StorefrontContent, type: string, fallback: string): string {
  return content.homepageSections?.find((section) => section.type === type && section.enabled !== false)?.title ?? fallback;
}
