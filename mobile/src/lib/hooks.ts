import { useMutation, useQuery, useQueryClient, type UseQueryOptions } from '@tanstack/react-query';
import { useCallback, useEffect, useState } from 'react';
import { useToast } from '@/components/ui';
import { api } from './api';

/** GET an API path with React Query; the path is the cache key. */
export function useApi<T>(path: string | null, options: Omit<UseQueryOptions<T>, 'queryKey' | 'queryFn'> = {}) {
  const q = useQuery<T>({
    queryKey: [path],
    queryFn: () => api.get<T>(path as string),
    enabled: path !== null && (options.enabled ?? true),
    ...options,
  });
  const [manual, setManual] = useState(false);
  const refresh = useCallback(async () => {
    setManual(true);
    try {
      await q.refetch();
    } finally {
      setManual(false);
    }
  }, [q]);
  return { ...q, refreshing: manual, refresh };
}

/**
 * POST helper: shows a toast with the server's message (or `success`), haptic feedback,
 * and refreshes every cached query whose path starts with one of `invalidate`.
 */
export function useAction<TBody = Record<string, unknown>, TRes = any>(
  path: string | ((body: TBody) => string),
  opts: { success?: string | ((res: TRes) => string | null); invalidate?: string[]; onSuccess?: (res: TRes) => void; upload?: boolean } = {},
) {
  const qc = useQueryClient();
  const toast = useToast();
  return useMutation<TRes, Error, TBody>({
    mutationFn: (body) => api.post<TRes>(typeof path === 'function' ? path(body) : path, body ?? {}),
    onSuccess: (res) => {
      const msg = typeof opts.success === 'function' ? opts.success(res) : opts.success ?? ((res as { message?: string })?.message || null);
      if (msg) toast(msg);
      invalidate(qc, opts.invalidate);
      opts.onSuccess?.(res);
    },
    onError: (e) => toast(e.message, 'error'),
  });
}

export function invalidate(qc: ReturnType<typeof useQueryClient>, prefixes: string[] = []) {
  if (!prefixes.length) return;
  qc.invalidateQueries({
    predicate: (query) => {
      const key = String(query.queryKey[0] ?? '');
      return prefixes.some((p) => key === p || key.startsWith(p + '/') || key.startsWith(p + '?'));
    },
  });
}

/** Debounced value for search boxes. */
export function useDebounced<T>(value: T, ms = 300): T {
  const [v, setV] = useState(value);
  useEffect(() => {
    const t = setTimeout(() => setV(value), ms);
    return () => clearTimeout(t);
  }, [value, ms]);
  return v;
}

/** Re-render every second (live timers). */
export function useTicker(active: boolean): number {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    if (!active) return;
    const t = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(t);
  }, [active]);
  return now;
}
