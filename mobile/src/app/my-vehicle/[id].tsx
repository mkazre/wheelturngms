import { router, Stack, useLocalSearchParams } from 'expo-router';
import { Button, Card, Empty, ErrorView, KeyValue, Loading, Row, Screen, Section, T } from '@/components/ui';
import { fdate, km } from '@/lib/format';
import { useApi } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';
import type { MyVehicle } from '@/lib/types';

export default function MyVehicleScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const p = usePalette();
  const v = useApi<MyVehicle>(`my/vehicles/${id}`);
  if (v.isLoading) return <Loading />;
  if (v.error || !v.data) return <ErrorView error={v.error} onRetry={v.refresh} />;
  const d = v.data;
  const today = new Date().toISOString().slice(0, 10);
  return (
    <>
      <Stack.Screen options={{ title: d.registration }} />
      <Screen refreshing={v.refreshing} onRefresh={v.refresh}>
        <Card style={{ gap: 4 }}>
          <T size={20} weight="800">{d.registration}</T>
          <T muted>{d.label}</T>
        </Card>
        <Card>
          <KeyValue label="Last odometer" value={km(d.odometer)} />
          <KeyValue label="Next service" value={d.next_service_date ? fdate(d.next_service_date) + (d.next_service_km ? ` or ${km(d.next_service_km)}` : '') : '-'} color={d.next_service_date && d.next_service_date <= today ? p.danger : undefined} />
          <KeyValue label="Licence disc expires" value={fdate(d.licence_expiry)} color={d.licence_expiry && d.licence_expiry <= today ? p.danger : undefined} />
        </Card>
        <Button title="Book this vehicle in" icon="calendar" variant="accent" onPress={() => router.push('/book')} />
        <Section title="Service history">
          {d.history.length === 0 ? <Card><Empty icon="clock" title="No history yet" /></Card> : null}
          {d.history.map((h) => (
            <Card key={h.id} style={{ gap: 4 }}>
              <Row style={{ justifyContent: 'space-between' }}>
                <T weight="800">{fdate(h.date)}</T>
                <T size={12.5} muted>{h.status_label}</T>
              </Row>
              <T size={13} muted>{h.number}{h.odometer ? ` · ${km(h.odometer)}` : ''}</T>
              {h.summary ? <T size={14}>{h.summary}</T> : null}
            </Card>
          ))}
        </Section>
      </Screen>
    </>
  );
}
