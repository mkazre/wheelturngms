import { router } from 'expo-router';
import { useEffect } from 'react';
import { FlatList, RefreshControl, View } from 'react-native';
import { Card, Empty, Icon, Loading, Row, T, type IconName } from '@/components/ui';
import { useAuth, useMe } from '@/lib/auth';
import { ago } from '@/lib/format';
import { useApi } from '@/lib/hooks';
import { routeForUrl } from '@/lib/push';
import { usePalette } from '@/lib/theme';
import type { Notification } from '@/lib/types';

const ICONS: Record<string, IconName> = { calendar: 'calendar', check: 'check-circle', x: 'x-circle', clipboard: 'clipboard', package: 'package', alert: 'alert-triangle', money: 'dollar-sign', star: 'star', wrench: 'tool', bell: 'bell' };

export default function Notifications() {
  const p = usePalette();
  const me = useMe();
  const { refresh } = useAuth();
  const list = useApi<{ items: Notification[] }>('notifications');
  // Opening this screen marks everything as read; refresh the bell badge.
  useEffect(() => { if (list.data) refresh(); }, [list.data, refresh]);

  return (
    <View style={{ flex: 1, backgroundColor: p.bg }}>
      <FlatList
        data={list.data?.items ?? []}
        keyExtractor={(n) => String(n.id)}
        contentContainerStyle={{ padding: 16, gap: 8 }}
        renderItem={({ item }) => {
          const href = routeForUrl(item.url, me.mode);
          return (
            <Card onPress={href ? () => router.push(href) : undefined} style={{ flexDirection: 'row', gap: 12, borderLeftWidth: item.unread ? 3 : undefined, borderLeftColor: p.accent }}>
              <View style={{ width: 38, height: 38, borderRadius: 12, backgroundColor: p.cardAlt, alignItems: 'center', justifyContent: 'center' }}>
                <Icon name={ICONS[item.icon ?? ''] ?? 'bell'} size={18} color={p.muted} />
              </View>
              <View style={{ flex: 1, gap: 2 }}>
                <Row style={{ justifyContent: 'space-between' }}>
                  <T weight="800" style={{ flex: 1 }} numberOfLines={2}>{item.title}</T>
                  <T size={11.5} muted>{ago(item.date)}</T>
                </Row>
                {item.body ? <T size={13} muted>{item.body}</T> : null}
              </View>
            </Card>
          );
        }}
        refreshControl={<RefreshControl refreshing={list.refreshing} onRefresh={list.refresh} />}
        ListEmptyComponent={list.isLoading ? <Loading /> : <Empty icon="bell" title="You're all caught up" message="Bookings, approvals, payments and job updates appear here." />}
      />
    </View>
  );
}
