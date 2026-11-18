import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { AndroidImportance } from 'expo-notifications';
import { Platform } from 'react-native';
import type { Href } from 'expo-router';
import { api } from './api';
import type { Mode } from './types';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowBanner: true,
    shouldShowList: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
  }),
});

/**
 * Ask permission and register this phone's Expo push token with the server.
 * Silently skips on simulators, web, or when the EAS project id is not configured yet
 * (push needs a development or store build: it does not work in Expo Go on Android).
 */
export async function registerForPush(): Promise<string | null> {
  try {
    if (Platform.OS === 'web' || !Device.isDevice) return null;
    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync('default', {
        name: 'Workshop updates',
        importance: AndroidImportance.HIGH,
        vibrationPattern: [0, 200, 120, 200],
        lightColor: '#9b1631',
      });
    }
    const current = await Notifications.getPermissionsAsync();
    let granted = current.granted;
    if (!granted && current.canAskAgain) {
      granted = (await Notifications.requestPermissionsAsync()).granted;
    }
    if (!granted) return null;
    const projectId = Constants.easConfig?.projectId ?? (Constants.expoConfig?.extra as { eas?: { projectId?: string } } | undefined)?.eas?.projectId;
    if (!projectId) return null;
    const { data } = await Notifications.getExpoPushTokenAsync({ projectId });
    await api.post('me/push-token', { token: data });
    return data;
  } catch {
    return null;
  }
}

export async function unregisterPush(): Promise<void> {
  try {
    await api.post('me/push-token', { token: '' });
  } catch {
    // signing out anyway
  }
}

/** Map a push payload or a web-style notification URL to an app route. */
export function routeFor(data: Record<string, unknown> | undefined | null, mode: Mode): Href | null {
  if (!data) return null;
  if (data.screen === 'job' && data.id) {
    return mode === 'customer' ? (`/track/${data.id}` as Href) : (`/job/${data.id}` as Href);
  }
  return routeForUrl(typeof data.url === 'string' ? data.url : null, mode);
}

export function routeForUrl(url: string | null, mode: Mode): Href | null {
  if (!url) return null;
  const path = url.replace(/^https?:\/\/[^/]+\//, '').replace(/^\//, '');
  let m: RegExpExecArray | null;
  if (mode === 'customer') return (path.startsWith('pay') || path.startsWith('portal/invoices') ? '/bills' : '/garage') as Href;
  if ((m = /^job-cards\/(\d+)/.exec(path))) return `/job/${m[1]}` as Href;
  if ((m = /^invoices\/(\d+)/.exec(path))) return `/invoice/${m[1]}` as Href;
  if ((m = /^quotes\/(\d+)/.exec(path))) return `/quote/${m[1]}` as Href;
  if ((m = /^customers\/(\d+)/.exec(path))) return `/customer/${m[1]}` as Href;
  if ((m = /^vehicles\/(\d+)/.exec(path))) return `/vehicle/${m[1]}` as Href;
  if (path.startsWith('diary')) return '/diary' as Href;
  if (path.startsWith('tasks')) return '/tasks' as Href;
  if (path.startsWith('inventory')) return '/stock' as Href;
  if (path.startsWith('workshop') || path.startsWith('job-cards')) return '/jobs' as Href;
  if (path.startsWith('invoices')) return '/invoices' as Href;
  if (path.startsWith('quotes')) return '/quotes' as Href;
  return null;
}
