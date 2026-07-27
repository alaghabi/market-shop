import type { StorefrontThemePreset } from '../types';

export const noirAtelierTheme: StorefrontThemePreset = {
  code: 'noir-atelier',
  name: 'Noir Atelier',
  description: 'Template editorial premium, contraste noir et accent rose couture.',
  layout: 'editorial',
  fontFamily: 'DM Sans, Inter, system-ui, sans-serif',
  borderRadius: '10px',
  colorPalette: {
    primary: '#18181B',
    primaryContainer: '#27272A',
    secondary: '#3F3F46',
    background: '#FAFAFA',
    surface: '#FFFFFF',
    surfaceContainer: '#F4F4F5',
    surfaceContainerHigh: '#E4E4E7',
    text: '#09090B',
    textMuted: '#52525B',
    outline: '#D4D4D8',
    accent: '#EC4899',
  },
};
