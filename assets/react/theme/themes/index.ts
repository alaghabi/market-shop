import { hanootiGlassTheme } from './hanooti-glass';
import { hanootiMarketplaceTheme } from './hanooti-marketplace';
import { noirAtelierTheme } from './noir-atelier';
import { nordicEditorialTheme } from './nordic-editorial';
import { oceanMinimalTheme } from './ocean-minimal';
import type { StorefrontThemePreset } from './types';

export type { StorefrontThemePreset } from './types';

export const STOREFRONT_THEME_PRESETS: StorefrontThemePreset[] = [
  hanootiGlassTheme,
  hanootiMarketplaceTheme,
  noirAtelierTheme,
  nordicEditorialTheme,
  oceanMinimalTheme,
];

export const STOREFRONT_THEME_PRESETS_BY_CODE = Object.fromEntries(
  STOREFRONT_THEME_PRESETS.map((theme) => [theme.code, theme]),
) as Record<string, StorefrontThemePreset>;

export function getStorefrontThemePreset(code?: string | null): StorefrontThemePreset | null {
  if (!code) return null;

  return STOREFRONT_THEME_PRESETS_BY_CODE[code] ?? null;
}

export function mergeThemeOptionWithPreset<T extends { code: string; colorPalette?: Record<string, string>; description?: string | null }>(theme: T): T {
  const preset = getStorefrontThemePreset(theme.code);
  if (!preset) return theme;

  return {
    ...theme,
    description: theme.description ?? preset.description,
    colorPalette: theme.colorPalette ?? preset.colorPalette,
  };
}
