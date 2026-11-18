import { router } from 'expo-router';
import { useState } from 'react';
import { FlatList, RefreshControl, View } from 'react-native';
import { Card, Empty, IconButton, ListItem, Loading, SearchBar } from '@/components/ui';
import { km } from '@/lib/format';
import { useApi, useDebounced } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';
import type { VehicleRow } from '@/lib/types';

export default function Vehicles() {
  const p = usePalette();
  const [q, setQ] = useState('');
  const dq = useDebounced(q);
  const list = useApi<{ items: VehicleRow[] }>(`vehicles?q=${encodeURIComponent(dq)}`, { placeholderData: (prev) => prev });
  return (
    <View style={{ flex: 1, backgroundColor: p.bg }}>
      <FlatList
        data={list.data?.items ?? []}
        keyExtractor={(v) => String(v.id)}
        contentContainerStyle={{ padding: 16, gap: 8 }}
        ListHeaderComponent={<View style={{ marginBottom: 4 }}><SearchBar value={q} onChange={setQ} placeholder="Registration, VIN, make, model" right={<IconButton icon="plus" size={34} onPress={() => router.push('/vehicle/new')} />} /></View>}
        renderItem={({ item }) => (
          <Card padded={false}>
            <ListItem icon="truck" title={`${item.registration} · ${item.label}`} subtitle={`${item.owner}${item.odometer ? ` · ${km(item.odometer)}` : ''}`} onPress={() => router.push(`/vehicle/${item.id}`)} />
          </Card>
        )}
        refreshControl={<RefreshControl refreshing={list.refreshing} onRefresh={list.refresh} />}
        ListEmptyComponent={list.isLoading ? <Loading /> : <Empty icon="truck" title="No vehicles found" />}
      />
    </View>
  );
}
