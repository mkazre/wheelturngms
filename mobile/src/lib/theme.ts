import { useColorScheme } from 'react-native';

/** Wheelsturn brand palette (matches the web app's Tailwind config). */
export const brand = {
  navy950: '#06071c',
  navy900: '#0b0d2e',
  navy800: '#12153f',
  navy700: '#1c205c',
  navy600: '#262c78',
  navy500: '#353d96',
  navy100: '#d9dcf3',
  navy50: '#eef0fb',
  red: '#ef1c25',
  crimson: '#9b1631',
  crimson50: '#fdf2f4',
  green: '#10b981',
  amber: '#f59e0b',
  orange: '#f97316',
  blue: '#3b82f6',
  purple: '#8b5cf6',
};

export type Palette = {
  dark: boolean;
  bg: string;
  card: string;
  cardAlt: string;
  border: string;
  text: string;
  muted: string;
  faint: string;
  primary: string;
  primaryText: string;
  accent: string;
  success: string;
  warning: string;
  danger: string;
  tabBar: string;
  input: string;
};

const light: Palette = {
  dark: false,
  bg: '#f4f5fb',
  card: '#ffffff',
  cardAlt: '#f8fafc',
  border: '#e4e7f1',
  text: '#0b0d2e',
  muted: '#64748b',
  faint: '#94a3b8',
  primary: brand.navy900,
  primaryText: '#ffffff',
  accent: brand.crimson,
  success: '#059669',
  warning: '#d97706',
  danger: '#dc2626',
  tabBar: '#ffffff',
  input: '#ffffff',
};

const dark: Palette = {
  dark: true,
  bg: '#06071c',
  card: '#0f1238',
  cardAlt: '#141848',
  border: '#232861',
  text: '#f1f2fb',
  muted: '#a3a9cf',
  faint: '#6b72a8',
  primary: '#ffffff',
  primaryText: brand.navy900,
  accent: '#ef4466',
  success: '#34d399',
  warning: '#fbbf24',
  danger: '#f87171',
  tabBar: '#0b0d2e',
  input: '#12153f',
};

export function usePalette(): Palette {
  return useColorScheme() === 'dark' ? dark : light;
}

export const radius = { sm: 8, md: 12, lg: 16, xl: 22, pill: 999 };
export const space = { xs: 4, sm: 8, md: 12, lg: 16, xl: 24 };

/** Chart series colours: brand first, then distinct accessible hues. */
export const chartColors = [brand.navy700, brand.crimson, '#0ea5e9', brand.amber, brand.green, brand.purple, '#64748b', '#ec4899'];
