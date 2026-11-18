import Constants from 'expo-constants';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

/**
 * Thin client for the Wheelsturn /api/v1 backend.
 * The server address defaults to app.json "extra.apiUrl" and can be changed on the sign-in screen
 * (useful for a test server). The JWT lives in the device keychain (SecureStore).
 */

const DEFAULT_URL: string = (Constants.expoConfig?.extra as { apiUrl?: string } | undefined)?.apiUrl ?? 'https://app.wheelsturn.co.za/api/v1';
const TOKEN_KEY = 'wt_token';
const SERVER_KEY = 'wt_server';

let token: string | null = null;
let baseUrl = DEFAULT_URL;
let onUnauthorized: (() => void) | null = null;

async function storeGet(key: string): Promise<string | null> {
  if (Platform.OS === 'web') return globalThis.localStorage?.getItem(key) ?? null;
  return SecureStore.getItemAsync(key);
}
async function storeSet(key: string, value: string | null): Promise<void> {
  if (Platform.OS === 'web') {
    if (value === null) globalThis.localStorage?.removeItem(key);
    else globalThis.localStorage?.setItem(key, value);
    return;
  }
  if (value === null) await SecureStore.deleteItemAsync(key);
  else await SecureStore.setItemAsync(key, value);
}

export class ApiError extends Error {
  status: number;
  data: Record<string, unknown>;
  constructor(message: string, status: number, data: Record<string, unknown> = {}) {
    super(message);
    this.status = status;
    this.data = data;
  }
}

export const api = {
  async load(): Promise<string | null> {
    baseUrl = (await storeGet(SERVER_KEY)) || DEFAULT_URL;
    token = await storeGet(TOKEN_KEY);
    return token;
  },
  defaultUrl: DEFAULT_URL,
  get baseUrl() {
    return baseUrl;
  },
  get token() {
    return token;
  },
  /** Web root (without /api/v1), e.g. for links to the web app. */
  get webUrl() {
    return baseUrl.replace(/\/api\/v1\/?$/, '/');
  },
  async setServer(url: string | null) {
    const clean = url ? url.trim().replace(/\/+$/, '') : '';
    baseUrl = clean ? (/\/api\/v1$/.test(clean) ? clean : clean + '/api/v1') : DEFAULT_URL;
    await storeSet(SERVER_KEY, clean ? baseUrl : null);
  },
  async setToken(t: string | null) {
    token = t;
    await storeSet(TOKEN_KEY, t);
  },
  onUnauthorized(fn: () => void) {
    onUnauthorized = fn;
  },
  authHeaders(): Record<string, string> {
    return token ? { Authorization: `Bearer ${token}` } : {};
  },

  async request<T = any>(method: string, path: string, body?: unknown): Promise<T> {
    const isForm = typeof FormData !== 'undefined' && body instanceof FormData;
    let res: Response;
    try {
      res = await fetch(`${baseUrl}/${path.replace(/^\//, '')}`, {
        method,
        headers: {
          Accept: 'application/json',
          ...(body !== undefined && !isForm ? { 'Content-Type': 'application/json' } : {}),
          ...api.authHeaders(),
        },
        body: body === undefined ? undefined : isForm ? (body as FormData) : JSON.stringify(body),
      });
    } catch {
      throw new ApiError('No connection to the workshop server. Check your internet and try again.', 0);
    }
    const text = await res.text();
    let data: any = {};
    try {
      data = text ? JSON.parse(text) : {};
    } catch {
      data = {};
    }
    if (res.status === 401 && token && path !== 'auth/login') {
      await api.setToken(null);
      onUnauthorized?.();
    }
    if (!res.ok) {
      throw new ApiError(data?.error || data?.message || `Request failed (${res.status})`, res.status, data);
    }
    return data as T;
  },
  get<T = any>(path: string) {
    return api.request<T>('GET', path);
  },
  post<T = any>(path: string, body: unknown = {}) {
    return api.request<T>('POST', path, body);
  },
  /** Multipart upload of a local file (photo, video, slip). */
  upload<T = any>(path: string, field: string, file: { uri: string; name?: string | null; type?: string | null }, extra: Record<string, string> = {}) {
    const fd = new FormData();
    const name = file.name || file.uri.split('/').pop() || 'upload';
    fd.append(field, { uri: file.uri, name, type: file.type || guessMime(name) } as unknown as Blob);
    Object.entries(extra).forEach(([k, v]) => fd.append(k, v));
    return api.request<T>('POST', path, fd);
  },
};

function guessMime(name: string): string {
  const ext = name.split('.').pop()?.toLowerCase();
  if (ext === 'mp4' || ext === 'mov') return ext === 'mov' ? 'video/quicktime' : 'video/mp4';
  if (ext === 'png') return 'image/png';
  if (ext === 'pdf') return 'application/pdf';
  if (ext === 'heic') return 'image/heic';
  return 'image/jpeg';
}

/** Image/video source for authenticated files (api/v1/files/{id}). */
export function authSource(uri: string) {
  return { uri, headers: api.authHeaders() };
}
