import { router, Stack, useLocalSearchParams } from 'expo-router';
import { View } from 'react-native';
import { Button, Card, Empty, ErrorView, KeyValue, ListItem, Loading, Row, Screen, Section, T } from '@/components/ui';
import { fdate, km, money } from '@/lib/format';
import { useApi } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';
import type { VehicleDetail } from '@/lib/types';

export default function VehicleScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const p = usePalette();
  const v = useApi<VehicleDetail>(`vehicles/${id}`);
  if (v.isLoading) return <Loading />;
  if (v.error || !v.data) return <ErrorView error={v.error} onRetry={v.refresh} />;
  const d = v.data;
  const due = d.next_service_date && d.next_service_date < new Date().toISOString().slice(0, 10);

  return (
    <>
      <Stack.Screen options={{ title: d.registration }} />
      <Screen refreshing={v.refreshing} onRefresh={v.refresh}>
        <Card style={{ gap: 6 }}>
          <View style={{ alignSelf: 'flex-start', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8, backgroundColor: p.dark ? '#262c78' : '#fef9c3', borderWidth: 1.5, borderColor: p.dark ? '#525bb5' : '#eab308' }}>
            <T size={18} weight="800" style={{ letterSpacing: 1 }} color={p.dark ? '#fff' : '#0b0d2e'}>{d.registration}</T>
          </View>
          <T size={18} weight="800">{d.label}</T>
          {d.variant ? <T muted>{d.variant}</T> : null}
        </Card>
        <Card>
          <KeyValue label="Odometer" value={km(d.odometer)} />
          <KeyValue label="VIN" value={d.vin ?? '-'} />
          <KeyValue label="Engine" value={d.engine || '-'} />
          <KeyValue label="Gearbox" value={d.transmission ?? '-'} />
          <KeyValue label="Colour" value={d.colour ?? '-'} />
          <KeyValue label="Next service" value={d.next_service_date ? fdate(d.next_service_date) + (d.next_service_km ? ` or ${km(d.next_service_km)}` : '') : '-'} color={due ? p.danger : undefined} />
          <KeyValue label="Licence disc expires" value={fdate(d.licence_expiry)} />
        </Card>
        <Card padded={false}>
          <ListItem avatar={d.owner.name} title={d.owner.name} subtitle={d.owner.phone ?? 'Owner'} onPress={() => router.push(`/customer/${d.owner.id}`)} />
        </Card>
        <Button title="Check in this vehicle" icon="log-in" variant="accent" onPress={() => router.push({ pathname: '/job/new', params: { vehicle_id: String(d.id) } })} />
        <Section title={`Service history (${d.history.length})`}>
          {d.history.length === 0 ? <Card><Empty icon="clock" title="No history yet" /></Card> : null}
          {d.history.map((h) => (
            <Card key={h.id} onPress={() => router.push(`/job/${h.id}`)} style={{ gap: 4 }}>
              <Row style={{ justifyContent: 'space-between' }}>
                <T weight="800">{fdate(h.date)}</T>
                <T weight="700">{money(h.total)}</T>
              </Row>
              <T size={13} muted>{h.number} · {h.status_label}{h.odometer ? ` · ${km(h.odometer)}` : ''}</T>
              {h.complaint ? <T size={13}>{h.complaint}</T> : null}
              {h.work.length ? <T size={12.5} muted numberOfLines={3}>{h.work.join(' · ')}</T> : null}
            </Card>
          ))}
        </Section>
      </Screen>
    </>
  );
}
