import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { FlatList, RefreshControl, View } from 'react-native';
import { Scanner } from '@/components/workshop';
import { Badge, Card, Chip, Empty, IconButton, Loading, Row, SearchBar, T } from '@/components/ui';
import { money } from '@/lib/format';
import { useApi, useDebounced } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';
import type { Product } from '@/lib/types';

export default function Stock() {
  const params = useLocalSearchParams<{ barcode?: string; low?: string }>();
  const p = usePalette();
  const [q, setQ] = useState('');
  const [barcode, setBarcode] = useState<string | null>(params.barcode ?? null);
  const [low, setLow] = useState(params.low === '1');
  const [scan, setScan] = useState(false);
  const dq = useDebounced(q);

  const path = barcode ? `products?barcode=${encodeURIComponent(barcode)}` : `products?q=${encodeURIComponent(dq)}${low ? '&low=1' : ''}`;
  const list = useApi<{ items: Product[] }>(path, { placeholderData: (prev) => prev });

  return (
    <View style={{ flex: 1, backgroundColor: p.bg }}>
      <FlatList
        data={list.data?.items ?? []}
        keyExtractor={(i) => String(i.id)}
        contentContainerStyle={{ padding: 16, gap: 8 }}
        ListHeaderComponent={
          <View style={{ gap: 10, marginBottom: 4 }}>
            <SearchBar value={q} onChange={(v) => { setQ(v); setBarcode(null); }} placeholder="Part name, SKU or barcode" right={<IconButton icon="maximize" size={34} onPress={() => setScan(true)} />} />
            <Row gap={8} wrap>
              <Chip label="Low stock only" icon="alert-triangle" selected={low} onPress={() => setLow(!low)} />
              {barcode ? <Chip label={`Barcode ${barcode}`} icon="x" selected onPress={() => setBarcode(null)} /> : null}
            </Row>
          </View>
        }
        renderItem={({ item }) => (
          <Card style={{ gap: 6 }}>
            <Row style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
              <View style={{ flex: 1, gap: 2 }}>
                <T weight="700">{item.name}</T>
                <T size={12.5} muted>{[item.sku, item.category, item.bin ? `Bin ${item.bin}` : null].filter(Boolean).join(' · ')}</T>
              </View>
              {item.price !== null ? <T weight="800">{money(item.price)}</T> : null}
            </Row>
            <Row gap={8}>
              {item.stock !== null ? <Badge label={`${item.stock} in stock`} color={item.low ? '#ef4444' : '#10b981'} /> : <Badge label="Not stocked" />}
              {item.low ? <Badge label="Reorder" color="#ef4444" solid /> : null}
            </Row>
          </Card>
        )}
        refreshControl={<RefreshControl refreshing={list.refreshing} onRefresh={list.refresh} />}
        ListEmptyComponent={list.isLoading ? <Loading /> : <Empty icon="package" title={barcode ? 'No item with that barcode' : 'No stock items found'} />}
      />
      <Scanner visible={scan} onClose={() => setScan(false)} onScan={(code) => { setScan(false); setBarcode(code); setQ(''); }} />
    </View>
  );
}
