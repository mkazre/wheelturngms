import { router } from 'expo-router';
import { useState } from 'react';
import { FlatList, RefreshControl, View } from 'react-native';
import { Card, Empty, ListItem, Loading, Segmented, StatusBadge, T } from '@/components/ui';
import { fdate, money } from '@/lib/format';
import { useApi } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';
import type { QuoteRow } from '@/lib/types';

export default function Quotes() {
  const p = usePalette();
  const [status, setStatus] = useState('sent');
  const list = useApi<{ items: QuoteRow[] }>(`quotes${status === 'all' ? '' : `?status=${status}`}`, { placeholderData: (prev) => prev });
  return (
    <View style={{ flex: 1, backgroundColor: p.bg }}>
      <FlatList
        data={list.data?.items ?? []}
        keyExtractor={(q) => String(q.id)}
        contentContainerStyle={{ padding: 16, gap: 8 }}
        ListHeaderComponent={<View style={{ marginBottom: 4 }}><Segmented value={status} onChange={setStatus} options={[{ value: 'draft', label: 'Draft' }, { value: 'sent', label: 'Sent' }, { value: 'accepted', label: 'Accepted' }, { value: 'all', label: 'All' }]} /></View>}
        renderItem={({ item }) => (
          <Card padded={false}>
            <ListItem
              title={`${item.number} · ${item.customer}`}
              subtitle={`${fdate(item.date)}${item.registration ? ` · ${item.registration}` : ''} · expires ${fdate(item.expires)}`}
              right={<View style={{ alignItems: 'flex-end', gap: 4 }}><T weight="800">{money(item.total)}</T><StatusBadge status={item.status} /></View>}
              onPress={() => router.push(`/quote/${item.id}`)}
            />
          </Card>
        )}
        refreshControl={<RefreshControl refreshing={list.refreshing} onRefresh={list.refresh} />}
        ListEmptyComponent={list.isLoading ? <Loading /> : <Empty icon="file" title="No quotes here" message="Create quotes on the web app; follow them up here." />}
      />
    </View>
  );
}
