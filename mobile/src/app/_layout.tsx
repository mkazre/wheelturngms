import { QueryClient, QueryClientProvider, focusManager } from '@tanstack/react-query';
import * as Notifications from 'expo-notifications';
import { router, Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { Image } from 'expo-image';
import { useEffect, useState } from 'react';
import { AppState, Platform, View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { Button, T, ToastProvider } from '@/components/ui';
import { AuthProvider, useAuth } from '@/lib/auth';
import { routeFor } from '@/lib/push';
import { usePalette } from '@/lib/theme';

SplashScreen.preventAutoHideAsync().catch(() => undefined);
SplashScreen.setOptions({ duration: 300, fade: true });

// Refetch when the app returns to the foreground.
AppState.addEventListener('change', (s) => {
  if (Platform.OS !== 'web') focusManager.setFocused(s === 'active');
});

export default function RootLayout() {
  const [qc] = useState(() => new QueryClient({ defaultOptions: { queries: { staleTime: 20_000, retry: 1 } } }));
  return (
    <SafeAreaProvider>
      <QueryClientProvider client={qc}>
        <ToastProvider>
          <AuthProvider>
            <Navigator />
          </AuthProvider>
        </ToastProvider>
      </QueryClientProvider>
    </SafeAreaProvider>
  );
}

function Navigator() {
  const { status, me, refresh } = useAuth();
  const p = usePalette();
  const lastResponse = Notifications.useLastNotificationResponse();
  const signedIn = status === 'signedIn' && me !== null;
  const staff = signedIn && me.mode !== 'customer';
  const customer = signedIn && me.mode === 'customer';

  useEffect(() => {
    if (status !== 'loading') SplashScreen.hideAsync().catch(() => undefined);
  }, [status]);

  // Open the right screen when a push notification is tapped.
  useEffect(() => {
    if (!signedIn || !lastResponse) return;
    const href = routeFor(lastResponse.notification.request.content.data as Record<string, unknown>, me.mode);
    Notifications.clearLastNotificationResponseAsync().catch(() => undefined);
    if (href) setTimeout(() => router.push(href), 50);
  }, [lastResponse, signedIn, me]);

  if (status === 'loading') return null;

  if (status === 'offline') {
    return (
      <View style={{ flex: 1, backgroundColor: p.bg, alignItems: 'center', justifyContent: 'center', padding: 32, gap: 16 }}>
        <Image source={require('../../assets/logo.png')} style={{ width: 220, height: 70 }} contentFit="contain" />
        <T weight="800" size={18} center>You are offline</T>
        <T muted center>We could not reach the workshop server. Check your connection and try again.</T>
        <Button title="Try again" icon="refresh-cw" onPress={refresh} />
      </View>
    );
  }

  return (
    <>
      <StatusBar style={p.dark ? 'light' : 'dark'} />
      <Stack
        screenOptions={{
          headerStyle: { backgroundColor: p.card },
          headerTintColor: p.text,
          headerTitleStyle: { fontWeight: '800' },
          headerShadowVisible: false,
          headerBackButtonDisplayMode: 'minimal',
          contentStyle: { backgroundColor: p.bg },
        }}
      >
        <Stack.Screen name="index" options={{ headerShown: false }} />
        <Stack.Protected guard={!signedIn}>
          <Stack.Screen name="login" options={{ headerShown: false }} />
        </Stack.Protected>

        <Stack.Protected guard={staff}>
          <Stack.Screen name="(staff)" options={{ headerShown: false }} />
          <Stack.Screen name="job/[id]" options={{ title: 'Job card' }} />
          <Stack.Screen name="job/new" options={{ title: 'Check in a vehicle', presentation: 'modal' }} />
          <Stack.Screen name="customer/[id]" options={{ title: 'Customer' }} />
          <Stack.Screen name="customer/new" options={{ title: 'New customer', presentation: 'modal' }} />
          <Stack.Screen name="vehicle/[id]" options={{ title: 'Vehicle' }} />
          <Stack.Screen name="vehicle/new" options={{ title: 'New vehicle', presentation: 'modal' }} />
          <Stack.Screen name="invoices" options={{ title: 'Invoices' }} />
          <Stack.Screen name="invoice/[id]" options={{ title: 'Invoice' }} />
          <Stack.Screen name="quotes" options={{ title: 'Quotes' }} />
          <Stack.Screen name="quote/[id]" options={{ title: 'Quote' }} />
          <Stack.Screen name="vehicles" options={{ title: 'Vehicles' }} />
          <Stack.Screen name="stock" options={{ title: 'Stock lookup' }} />
          <Stack.Screen name="tasks" options={{ title: 'My tasks' }} />
          <Stack.Screen name="assistant" options={{ title: 'AI workshop assistant' }} />
        </Stack.Protected>

        <Stack.Protected guard={customer}>
          <Stack.Screen name="(customer)" options={{ headerShown: false }} />
          <Stack.Screen name="track/[id]" options={{ title: 'Vehicle progress' }} />
          <Stack.Screen name="my-quote/[id]" options={{ title: 'Quote' }} />
          <Stack.Screen name="my-vehicle/[id]" options={{ title: 'My vehicle' }} />
        </Stack.Protected>

        <Stack.Protected guard={signedIn}>
          <Stack.Screen name="notifications" options={{ title: 'Notifications' }} />
          <Stack.Screen name="profile" options={{ title: 'Profile & security' }} />
        </Stack.Protected>
      </Stack>
    </>
  );
}

export { ErrorScreen as ErrorBoundary } from '@/components/error-screen';
