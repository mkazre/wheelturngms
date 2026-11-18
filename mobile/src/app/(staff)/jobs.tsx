import { useInfiniteQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Dimensions, FlatList, Pressable, RefreshControl, ScrollView, View } from 'react-native';
import { JobCardRow } from '@/components/workshop';
import { Badge, Empty, ErrorView, Icon, Loading, Row, SearchBar, Segmented, T } from '@/components/ui';
import { api } from '@/lib/api';
import { useMe } from '@/lib/auth';
import { useApi, useDebounced } from '@/lib/hooks';
import { brand, usePalette } from '@/lib/theme';
import type { JobRow, Paged } from '@/lib/types';

const FILTERS = [
  { value: 'open', label: 'Open' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'awaiting_approval', label: 'Approval' },
  { value: 'awaiting_parts', label: 'Parts' },
  { value: 'completed', label: 'Ready' },
  { value: 'all', label: 'All' },
];

export default function Jobs() {
  const me = useMe();
  const p = usePalette();
  const tech = me.mode === 'technician';
  const [view, setView] = useState<'list' | 'board'>(tech ? 'board' : 'list');

  return (
    <View style={{ flex: 1, backgroundColor: p.bg }}>
      <View style={{ paddingHorizontal: 16, paddingTop: 12 }}>
        <Segmented value={view} onChange={setView} options={[{ value: 'list', label: 'List' }, { value: 'board', label: 'Board' }]} />
      </View>
      {view === 'list' ? <JobList prices={!tech} /> : <Board prices={!tech} />}
      {!tech ? (
        <Pressable
          onPress={() => router.push('/job/new')}
          style={({ pressed }) => ({ position: 'absolute', right: 18, bottom: 18, width: 58, height: 58, borderRadius: 29, backgroundColor: brand.crimson, alignItems: 'center', justifyContent: 'center', opacity: pressed ? 0.85 : 1, shadowColor: '#000', shadowOpacity: 0.25, shadowRadius: 10, shadowOffset: { width: 0, height: 4 }, elevation: 6 })}
        >
          <Icon name="plus" size={26} color="#fff" />
        </Pressable>
      ) : null}
    </View>
  );
}

function JobList({ prices }: { prices: boolean }) {
  const [status, setStatus] = useState('open');
  const [q, setQ] = useState('');
  const dq = useDebounced(q);
  const query = useInfiniteQuery({
    queryKey: [`jobs?status=${status}&q=${dq}`],
    initialPageParam: 1,
    queryFn: ({ pageParam }) => api.get<Paged<JobRow>>(`jobs?status=${status}&q=${encodeURIComponent(dq)}&page=${pageParam}`),
    getNextPageParam: (last, all) => (last.more ? all.length + 1 : undefined),
  });
  const items = query.data?.pages.flatMap((pg) => pg.items) ?? [];

  return (
    <FlatList
      data={items}
      keyExtractor={(j) => String(j.id)}
      contentContainerStyle={{ padding: 16, gap: 10, paddingBottom: 100 }}
      ListHeaderComponent={
        <View style={{ gap: 10, marginBottom: 4 }}>
          <SearchBar value={q} onChange={setQ} placeholder="Registration, job number, customer" />
          <Segmented scroll value={status} onChange={setStatus} options={FILTERS} />
        </View>
      }
      renderItem={({ item }) => <JobCardRow job={item} showPrice={prices} onPress={() => router.push(`/job/${item.id}`)} />}
      onEndReached={() => query.hasNextPage && !query.isFetchingNextPage && query.fetchNextPage()}
      onEndReachedThreshold={0.4}
      refreshControl={<RefreshControl refreshing={query.isRefetching && !query.isFetchingNextPage} onRefresh={() => { query.refetch(); }} />}
      ListEmptyComponent={query.isLoading ? <Loading /> : query.error ? <Empty icon="wifi-off" title="Could not load jobs" message={(query.error as Error).message} /> : <Empty icon="clipboard" title="No jobs here" message="Try another filter or search." />}
      ListFooterComponent={query.isFetchingNextPage ? <ActivityIndicator style={{ margin: 16 }} /> : undefined}
    />
  );
}

function Board({ prices }: { prices: boolean }) {
  const p = usePalette();
  const board = useApi<{ columns: { status: string; label: string; color: string; jobs: JobRow[] }[] }>('board', { refetchInterval: 60_000 });
  if (board.isLoading) return <Loading />;
  if (board.error) return <ErrorView error={board.error} onRetry={board.refresh} />;
  const width = Math.min(330, Dimensions.get('window').width * 0.84);
  return (
    <ScrollView horizontal pagingEnabled={false} snapToInterval={width + 12} decelerationRate="fast" contentContainerStyle={{ padding: 16, gap: 12 }} showsHorizontalScrollIndicator={false}>
      {board.data!.columns.map((col) => (
        <View key={col.status} style={{ width, backgroundColor: p.dark ? '#0b0d2e' : '#e9ebf5', borderRadius: 18, padding: 10 }}>
          <Row style={{ justifyContent: 'space-between', paddingHorizontal: 6, paddingVertical: 6 }}>
            <Row gap={8}>
              <View style={{ width: 10, height: 10, borderRadius: 5, backgroundColor: col.color }} />
              <T weight="800">{col.label}</T>
            </Row>
            <Badge label={String(col.jobs.length)} color={col.color} />
          </Row>
          <ScrollView style={{ maxHeight: Dimensions.get('window').height - 260 }} contentContainerStyle={{ gap: 8, paddingTop: 4 }} refreshControl={<RefreshControl refreshing={board.refreshing} onRefresh={board.refresh} />}>
            {col.jobs.length === 0 ? <T muted size={13} center style={{ padding: 20 }}>Nothing here</T> : null}
            {col.jobs.map((j) => <JobCardRow key={j.id} job={j} showPrice={prices} onPress={() => router.push(`/job/${j.id}`)} />)}
          </ScrollView>
        </View>
      ))}
    </ScrollView>
  );
}
