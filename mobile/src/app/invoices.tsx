import { router } from 'expo-router';
import { useState } from 'react';
import { FlatList, RefreshControl, View } from 'react-native';
import { Card, Empty, ListItem, Loading, SearchBar, Segmented, StatusBadge, T } from '@/components/ui';
import { fdate, money } from '@/lib/format';
import { useApi, useDebounced } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';
import type { InvoiceRow } from '@/lib/types';

export default function Invoices() {
  const p = usePalette();
  const [status, setStatus] = useState('outstanding');
  const [q, setQ] = useState('');
  const dq = useDebounced(q);
  const list = useApi<{ items: InvoiceRow[] }>(`invoices?status=${status === 'all' ? '' : status}&q=${encodeURIComponent(dq)}`, { placeholderData: (prev) => prev });
  const owed = (list.data?.items ?? []).reduce((s, i) => s + i.balance, 0);

  return (
    <View style={{ flex: 1, backgroundColor: p.bg }}>
      <FlatList
        data={list.data?.items ?? []}
        keyExtractor={(i) => String(i.id)}
        contentContainerStyle={{ padding: 16, gap: 8 }}
        ListHeaderComponent={
          <View style={{ gap: 10, marginBottom: 4 }}>
            <SearchBar value={q} onChange={setQ} placeholder="Invoice number, customer, registration" />
            <Segmented value={status} onChange={setStatus} options={[{ value: 'outstanding', label: 'Unpaid' }, { value: 'overdue', label: 'Overdue' }, { value: 'paid', label: 'Paid' }, { value: 'all', label: 'All' }]} />
            {status !== 'paid' && owed > 0 ? <T size={13} muted>{money(owed)} outstanding on the invoices shown</T> : null}
          </View>
        }
        renderItem={({ item }) => (
          <Card padded={false}>
            <ListItem
              title={`${item.number} · ${item.customer}`}
              subtitle={`${fdate(item.date)}${item.registration ? ` · ${item.registration}` : ''}${item.balance > 0 ? ` · due ${fdate(item.due)}` : ''}`}
              right={<View style={{ alignItems: 'flex-end', gap: 4 }}><T weight="800">{money(item.balance > 0 ? item.balance : item.total)}</T><StatusBadge status={item.status} /></View>}
              onPress={() => router.push(`/invoice/${item.id}`)}
            />
          </Card>
        )}
        refreshControl={<RefreshControl refreshing={list.refreshing} onRefresh={list.refresh} />}
        ListEmptyComponent={list.isLoading ? <Loading /> : <Empty icon="file-text" title="No invoices here" />}
      />
    </View>
  );
}
