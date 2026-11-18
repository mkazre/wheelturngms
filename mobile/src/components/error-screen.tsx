import type { ErrorBoundaryProps } from 'expo-router';
import { router } from 'expo-router';
import { View } from 'react-native';
import { usePalette } from '@/lib/theme';
import { Button, Icon, T } from './ui';

/**
 * Shown instead of closing the app when a screen throws. Exported as `ErrorBoundary`
 * from the layouts, so a bad server response can never crash the whole app.
 */
export function ErrorScreen({ error, retry }: ErrorBoundaryProps) {
  const p = usePalette();
  return (
    <View style={{ flex: 1, backgroundColor: p.bg, alignItems: 'center', justifyContent: 'center', padding: 32, gap: 14 }}>
      <View style={{ width: 64, height: 64, borderRadius: 20, backgroundColor: p.card, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: p.border }}>
        <Icon name="alert-triangle" size={28} color={p.warning} />
      </View>
      <T size={18} weight="800" center>Something went wrong</T>
      <T muted center size={14}>{error?.message || 'An unexpected error occurred.'}</T>
      <View style={{ flexDirection: 'row', gap: 10 }}>
        <Button title="Try again" icon="refresh-cw" onPress={retry} />
        <Button title="Home" icon="home" variant="secondary" onPress={() => { try { router.replace('/'); } catch { retry(); } }} />
      </View>
    </View>
  );
}
