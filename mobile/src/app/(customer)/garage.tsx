import { Image } from 'expo-image';
import { router } from 'expo-router';
import { Linking, View } from 'react-native';
import { Badge, Button, Card, Empty, ErrorView, Icon, Loading, Row, Screen, Section, T, Title } from '@/components/ui';
import { useMe } from '@/lib/auth';
import { fdate, fdatetime, km, money } from '@/lib/format';
import { useApi } from '@/lib/hooks';
import { brand, usePalette } from '@/lib/theme';
import type { MyOverview } from '@/lib/types';

const FLOW = ['booked', 'checked_in', 'in_progress', 'quality_check', 'completed'];

export default function Garage() {
  const me = useMe();
  const p = usePalette();
  const ov = useApi<MyOverview>('my/overview', { refetchInterval: 60_000 });
  if (ov.isLoading) return <Loading />;
  if (ov.error || !ov.data) return <ErrorView error={ov.error} onRetry={ov.refresh} />;
  const d = ov.data;
  const today = new Date().toISOString().slice(0, 10);
  const wa = me.company.phone?.replace(/\D/g, '').replace(/^0/, '27');

  return (
    <Screen refreshing={ov.refreshing} onRefresh={ov.refresh}>
      <View style={{ backgroundColor: brand.navy900, borderRadius: 22, padding: 18, gap: 12, overflow: 'hidden' }}>
        <View style={{ position: 'absolute', right: -40, top: -40, width: 160, height: 160, borderRadius: 80, borderWidth: 22, borderColor: '#ffffff0d' }} />
        <Row style={{ justifyContent: 'space-between' }}>
          <View style={{ backgroundColor: '#fff', borderRadius: 12, paddingHorizontal: 10, paddingVertical: 5 }}>
            <Image source={require('../../../assets/logo.png')} style={{ width: 120, height: 38 }} contentFit="contain" />
          </View>
        </Row>
        <View>
          <T color="#c7cbf0" size={14}>Hello,</T>
          <Title style={{ color: '#fff' }}>{me.user.first_name}</Title>
        </View>
        <Row gap={8}>
          <Button title="Book" icon="calendar" small variant="accent" onPress={() => router.push('/book')} />
          {me.company.phone ? <Button title="Call" icon="phone" small variant="secondary" onPress={() => Linking.openURL(`tel:${me.company.phone}`)} /> : null}
          {wa ? <Button title="WhatsApp" icon="message-circle" small variant="secondary" onPress={() => Linking.openURL(`https://wa.me/${wa}`)} /> : null}
        </Row>
      </View>

      {d.approvals_waiting > 0 ? (
        <Card style={{ backgroundColor: '#fffbeb', borderColor: '#fcd34d', flexDirection: 'row', gap: 12, alignItems: 'center' }} onPress={() => d.active_jobs[0] && router.push(`/track/${d.active_jobs[0].id}`)}>
          <Icon name="alert-circle" size={22} color="#b45309" />
          <View style={{ flex: 1 }}>
            <T weight="800" color="#92400e">{d.approvals_waiting} item{d.approvals_waiting > 1 ? 's' : ''} need your approval</T>
            <T size={13} color="#b45309">Our technician found work for you to approve or decline.</T>
          </View>
          <Icon name="chevron-right" color="#b45309" />
        </Card>
      ) : null}
      {d.amount_owed > 0 ? (
        <Card style={{ flexDirection: 'row', gap: 12, alignItems: 'center' }} onPress={() => router.push('/bills')}>
          <Icon name="credit-card" size={22} color={p.accent} />
          <View style={{ flex: 1 }}>
            <T weight="800">{money(d.amount_owed)} due</T>
            <T size={13} muted>Pay securely by card or instant EFT.</T>
          </View>
          <Icon name="chevron-right" color={p.faint} />
        </Card>
      ) : null}

      <Section title="In the workshop">
        {d.active_jobs.length === 0 ? <Card><Empty icon="truck" title="No vehicles in the workshop" message="Book a service and track it here live." action={{ label: 'Book a service', onPress: () => router.push('/book') }} /></Card> : null}
        {d.active_jobs.map((j) => {
          const idx = Math.max(0, FLOW.indexOf(['awaiting_parts', 'awaiting_approval'].includes(j.status) ? 'in_progress' : j.status === 'invoiced' ? 'completed' : j.status));
          return (
            <Card key={j.id} onPress={() => router.push(`/track/${j.id}`)} style={{ gap: 10 }}>
              <Row style={{ justifyContent: 'space-between' }}>
                <View>
                  <T weight="800" size={16}>{j.registration}</T>
                  <T size={13} muted>{j.vehicle}</T>
                </View>
                <Badge label={j.status_label} color={j.status_color} solid />
              </Row>
              <Row gap={4}>
                {FLOW.map((s, i) => <View key={s} style={{ flex: 1, height: 6, borderRadius: 3, backgroundColor: i <= idx ? j.status_color : p.border }} />)}
              </Row>
              <Row style={{ justifyContent: 'space-between' }}>
                <T size={12.5} muted>{j.promised_at ? `Ready by ${fdatetime(j.promised_at)}` : j.number}</T>
                <Row gap={4}><T size={13} weight="700" color={p.accent}>Track</T><Icon name="chevron-right" size={14} color={p.accent} /></Row>
              </Row>
            </Card>
          );
        })}
      </Section>

      <Section title="My vehicles">
        {d.vehicles.map((v) => {
          const due = v.next_service_date && v.next_service_date <= today;
          return (
            <Card key={v.id} onPress={() => router.push(`/my-vehicle/${v.id}`)} style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
              <View style={{ width: 46, height: 46, borderRadius: 14, backgroundColor: p.cardAlt, alignItems: 'center', justifyContent: 'center' }}><Icon name="truck" size={20} color={p.muted} /></View>
              <View style={{ flex: 1, gap: 2 }}>
                <T weight="800">{v.registration}</T>
                <T size={13} muted>{v.label}{v.odometer ? ` · ${km(v.odometer)}` : ''}</T>
                {v.next_service_date ? <T size={12.5} weight="700" color={due ? p.danger : p.muted}>{due ? 'Service overdue' : `Next service ${fdate(v.next_service_date)}`}</T> : null}
              </View>
              <Icon name="chevron-right" color={p.faint} />
            </Card>
          );
        })}
        {d.vehicles.length === 0 ? <Card><T muted center>No vehicles on your profile yet.</T></Card> : null}
      </Section>

      {d.open_quotes > 0 ? (
        <Card onPress={() => router.push('/bills')} style={{ flexDirection: 'row', gap: 12, alignItems: 'center' }}>
          <Icon name="file" size={20} color={p.muted} />
          <T style={{ flex: 1 }} weight="700">{d.open_quotes} quote{d.open_quotes > 1 ? 's' : ''} waiting for you</T>
          <Icon name="chevron-right" color={p.faint} />
        </Card>
      ) : null}
    </Screen>
  );
}
