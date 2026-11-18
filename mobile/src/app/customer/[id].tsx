import { router, Stack, useLocalSearchParams } from 'expo-router';
import { Linking, View } from 'react-native';
import { Avatar, Badge, Button, Card, Divider, Empty, ErrorView, KeyValue, ListItem, Loading, Row, Screen, Section, StatusBadge, T } from '@/components/ui';
import { fdate, km, money } from '@/lib/format';
import { useApi } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';
import type { CustomerDetail } from '@/lib/types';

export default function CustomerScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const p = usePalette();
  const c = useApi<CustomerDetail>(`customers/${id}`);
  if (c.isLoading) return <Loading />;
  if (c.error || !c.data) return <ErrorView error={c.error} onRetry={c.refresh} />;
  const d = c.data;
  const wa = d.phone?.replace(/\D/g, '').replace(/^0/, '27');

  return (
    <>
      <Stack.Screen options={{ title: d.name }} />
      <Screen refreshing={c.refreshing} onRefresh={c.refresh}>
        <Card style={{ alignItems: 'center', gap: 8, paddingVertical: 20 }}>
          <Avatar name={d.name} size={64} />
          <T size={20} weight="800" center>{d.name}</T>
          <T muted center>{[d.phone, d.email].filter(Boolean).join(' · ')}</T>
          {d.balance > 0 ? <Badge label={`Owes ${money(d.balance)}`} color={p.warning} /> : <Badge label="Account up to date" color={p.success} />}
          <Row gap={8} style={{ marginTop: 8 }}>
            {d.phone ? <Button title="Call" icon="phone" small variant="secondary" onPress={() => Linking.openURL(`tel:${d.phone}`)} /> : null}
            {wa ? <Button title="WhatsApp" icon="message-circle" small variant="secondary" onPress={() => Linking.openURL(`https://wa.me/${wa}`)} /> : null}
            {d.email ? <Button title="Email" icon="mail" small variant="secondary" onPress={() => Linking.openURL(`mailto:${d.email}`)} /> : null}
          </Row>
        </Card>

        <Section title={`Vehicles (${d.vehicles.length})`} action={{ label: 'Add vehicle', onPress: () => router.push({ pathname: '/vehicle/new', params: { customer_id: String(d.id) } }) }}>
          <Card padded={false}>
            {d.vehicles.length === 0 ? <Empty icon="truck" title="No vehicles yet" /> : null}
            {d.vehicles.map((v, i) => (
              <View key={v.id}>
                <ListItem icon="truck" title={`${v.registration} · ${v.label}`} subtitle={[v.odometer ? km(v.odometer) : null, v.next_service_date ? `Service due ${fdate(v.next_service_date)}` : null].filter(Boolean).join(' · ')} onPress={() => router.push(`/vehicle/${v.id}`)} />
                {i < d.vehicles.length - 1 ? <Divider inset={64} /> : null}
              </View>
            ))}
          </Card>
        </Section>

        {d.vehicles.length ? <Button title="Check in a vehicle" icon="log-in" variant="accent" onPress={() => router.push({ pathname: '/job/new', params: { vehicle_id: String(d.vehicles[0].id) } })} /> : null}

        <Section title="Recent jobs">
          <Card padded={false}>
            {d.jobs.length === 0 ? <Empty icon="clipboard" title="No jobs yet" /> : null}
            {d.jobs.map((j, i) => (
              <View key={j.id}>
                <ListItem title={`${j.number} · ${j.registration}`} subtitle={fdate(j.date)} right={<View style={{ alignItems: 'flex-end', gap: 4 }}><T weight="700">{money(j.total)}</T><Badge label={j.status_label} color={j.status_color} /></View>} onPress={() => router.push(`/job/${j.id}`)} />
                {i < d.jobs.length - 1 ? <Divider /> : null}
              </View>
            ))}
          </Card>
        </Section>

        <Section title="Invoices">
          <Card padded={false}>
            {d.invoices.length === 0 ? <Empty icon="file-text" title="No invoices yet" /> : null}
            {d.invoices.map((inv, i) => (
              <View key={inv.id}>
                <ListItem title={inv.number} subtitle={fdate(inv.date)} right={<View style={{ alignItems: 'flex-end', gap: 4 }}><T weight="700">{money(inv.total)}</T><StatusBadge status={inv.status} /></View>} onPress={() => router.push(`/invoice/${inv.id}`)} />
                {i < d.invoices.length - 1 ? <Divider /> : null}
              </View>
            ))}
          </Card>
        </Section>

        {d.address || d.vat_number || d.notes ? (
          <Card>
            {d.address ? <KeyValue label="Address" value={d.address} /> : null}
            {d.vat_number ? <KeyValue label="VAT number" value={d.vat_number} /> : null}
            {d.notes ? <KeyValue label="Notes" value={d.notes} /> : null}
          </Card>
        ) : null}
      </Screen>
    </>
  );
}
