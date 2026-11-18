import { router } from 'expo-router';
import { useState } from 'react';
import { FlatList, RefreshControl, View } from 'react-native';
import { Card, Divider, Empty, ListItem, Loading, SearchBar, T, IconButton } from '@/components/ui';
import { money } from '@/lib/format';
import { useApi, useDebounced } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';
import type { CustomerRow, Paged } from '@/lib/types';

export default function Customers() {
  const p = usePalette();
  const [q, setQ] = useState('');
  const dq = useDebounced(q);
  const list = useApi<Paged<CustomerRow>>(`customers?q=${encodeURIComponent(dq)}`, { placeholderData: (prev) => prev });

  return (
    <View style={{ flex: 1, backgroundColor: p.bg }}>
      <FlatList
        data={list.data?.items ?? []}
        keyExtractor={(c) => String(c.id)}
        contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
        ListHeaderComponent={
          <View style={{ marginBottom: 12 }}>
            <SearchBar value={q} onChange={setQ} placeholder="Name, phone, email, registration" right={<IconButton icon="user-plus" size={34} onPress={() => router.push('/customer/new')} />} />
          </View>
        }
        renderItem={({ item, index }) => (
          <Card padded={false} style={{ borderRadius: 0, borderTopLeftRadius: index === 0 ? 16 : 0, borderTopRightRadius: index === 0 ? 16 : 0, borderBottomLeftRadius: index === (list.data?.items.length ?? 0) - 1 ? 16 : 0, borderBottomRightRadius: index === (list.data?.items.length ?? 0) - 1 ? 16 : 0, borderTopWidth: index === 0 ? undefined : 0 }}>
            <ListItem
              avatar={item.name}
              title={item.name}
              subtitle={[item.phone, `${item.vehicles} vehicle${item.vehicles === 1 ? '' : 's'}`].filter(Boolean).join(' · ')}
              right={item.balance > 0 ? <T size={13} weight="800" color={p.warning}>{money(item.balance)}</T> : undefined}
              onPress={() => router.push(`/customer/${item.id}`)}
            />
            {index < (list.data?.items.length ?? 0) - 1 ? <Divider inset={64} /> : null}
          </Card>
        )}
        refreshControl={<RefreshControl refreshing={list.refreshing} onRefresh={list.refresh} />}
        ListEmptyComponent={list.isLoading ? <Loading /> : <Empty icon="users" title="No customers found" action={{ label: 'Add a customer', onPress: () => router.push('/customer/new') }} />}
      />
    </View>
  );
}
