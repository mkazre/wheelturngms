import { useQueryClient } from '@tanstack/react-query';
import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, ApiError } from './api';
import { registerForPush, unregisterPush } from './push';
import type { Me } from './types';

type Status = 'loading' | 'signedOut' | 'signedIn' | 'offline';
type LoginResult = { ok: true } | { ok: false; twoFactor?: boolean; error: string };

type AuthState = {
  status: Status;
  me: Me | null;
  signIn: (email: string, password: string, code?: string) => Promise<LoginResult>;
  signOut: () => Promise<void>;
  refresh: () => Promise<void>;
};

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const qc = useQueryClient();
  const [status, setStatus] = useState<Status>('loading');
  const [me, setMe] = useState<Me | null>(null);

  const loadMe = useCallback(async () => {
    const data = await api.get<Me>('me');
    // An older server returns a bare profile without mode/permissions: refuse it rather than crash later.
    if (!data || !data.user || !data.mode || !data.can || !data.company) {
      await api.setToken(null);
      throw new ApiError('The workshop server needs an update before the app can sign in. Please ask your administrator to update app.wheelsturn.co.za.', 426);
    }
    setMe(data);
    setStatus('signedIn');
    return data;
  }, []);

  useEffect(() => {
    api.onUnauthorized(() => {
      setMe(null);
      setStatus('signedOut');
      qc.clear();
    });
    (async () => {
      const token = await api.load();
      if (!token) {
        setStatus('signedOut');
        return;
      }
      try {
        await loadMe();
        registerForPush();
      } catch (e) {
        // Offline at launch: keep the token and offer a retry; only a rejected token signs out.
        setStatus(e instanceof ApiError && (e.status === 401 || e.status === 426) ? 'signedOut' : 'offline');
      }
    })();
  }, [loadMe, qc]);

  const signIn = useCallback(
    async (email: string, password: string, code?: string): Promise<LoginResult> => {
      try {
        const res = await api.post<{ token: string }>('auth/login', { email, password, ...(code ? { code } : {}) });
        await api.setToken(res.token);
        await loadMe();
        registerForPush();
        return { ok: true };
      } catch (e) {
        const err = e as ApiError;
        return { ok: false, error: err.message, twoFactor: Boolean(err.data?.two_factor_required) };
      }
    },
    [loadMe],
  );

  const signOut = useCallback(async () => {
    await unregisterPush();
    await api.setToken(null);
    qc.clear();
    setMe(null);
    setStatus('signedOut');
  }, [qc]);

  const refresh = useCallback(async () => {
    try {
      await loadMe();
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) setStatus('signedOut');
    }
  }, [loadMe]);

  const value = useMemo(() => ({ status, me, signIn, signOut, refresh }), [status, me, signIn, signOut, refresh]);
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider');
  return ctx;
}

/** Signed-in profile (screens behind the auth gate). */
export function useMe(): Me {
  const { me } = useAuth();
  if (!me) throw new Error('Not signed in');
  return me;
}
