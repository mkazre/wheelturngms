import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, View } from 'react-native';
import { BarChart, Donut, HBars, TargetMeter } from '@/components/charts';
import { JobCardRow, Scanner, TimerBanner } from '@/components/workshop';
import { Badge, Card, Empty, Icon, IconName, Label, Loading, Row, Screen, Section, Segmented, T, Title } from '@/components/ui';
import { useMe } from '@/lib/auth';
import { compactMoney, money } from '@/lib/format';
import { useApi } from '@/lib/hooks';
import { brand, usePalette } from '@/lib/theme';
import type { Dashboard, JobRow, Paged, Task } from '@/lib/types';

function greeting() {
  const h = new Date().getHours();
  return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
}

export default function Home() {
  const me = useMe();
  return me.mode === 'technician' ? <TechnicianHome /> : <ManagerHome />;
}

function QuickAction({ icon, label, onPress, color }: { icon: IconName; label: string; onPress: () => void; color: string }) {
  const p = usePalette();
  return (
    <Pressable onPress={onPress} style={({ pressed }) => ({ flex: 1, alignItems: 'center', gap: 8, opacity: pressed ? 0.7 : 1 })}>
      <View style={{ width: 54, height: 54, borderRadius: 18, backgroundColor: color, alignItems: 'center', justifyContent: 'center' }}>
        <Icon name={icon} size={22} color="#fff" />
      </View>
      <T size={11.5} weight="700" center color={p.text}>{label}</T>
    </Pressable>
  );
}

function QuickActions({ tech }: { tech?: boolean }) {
  const me = useMe();
  const [scan, setScan] = useState(false);
  return (
    <Card style={{ paddingVertical: 14 }}>
      <Row>
        {!tech ? <QuickAction icon="plus" label="Check in" color={brand.crimson} onPress={() => router.push('/job/new')} /> : null}
        <QuickAction icon="maximize" label="Scan part" color={brand.navy700} onPress={() => setScan(true)} />
        {!tech ? <QuickAction icon="user-plus" label="Customer" color="#0e7490" onPress={() => router.push('/customer/new')} /> : <QuickAction icon="check-square" label="Tasks" color="#0e7490" onPress={() => router.push('/tasks')} />}
        {me.ai ? <QuickAction icon="cpu" label="AI assist" color="#6d28d9" onPress={() => router.push('/assistant')} /> : <QuickAction icon="search" label="Stock" color="#6d28d9" onPress={() => router.push('/stock')} />}
      </Row>
      <Scanner visible={scan} onClose={() => setScan(false)} onScan={(code) => { setScan(false); router.push({ pathname: '/stock', params: { barcode: code } }); }} />
    </Card>
  );
}

function ManagerHome() {
  const me = useMe();
  const p = usePalette();
  const [period, setPeriod] = useState('mtd');
  const d = useApi<Dashboard>(me.can.dashboard ? `dashboard?period=${period}` : null, { placeholderData: (prev) => prev });
  const timer = useApi<{ timer: { job_id: number; number: string; registration: string; started_at: string } | null }>('timer');

  if (me.can.dashboard && !d.data) {
    return d.error ? <Screen><Empty icon="wifi-off" title="Could not load" message={(d.error as Error).message} action={{ label: 'Try again', onPress: d.refresh }} /></Screen> : <Loading />;
  }
  const data = d.data;

  return (
    <Screen refreshing={d.refreshing} onRefresh={() => { d.refresh(); timer.refetch(); }}>
      <View style={{ gap: 2 }}>
        <T muted size={14}>{greeting()},</T>
        <Title>{me.user.first_name}</Title>
      </View>
      <TimerBanner timer={timer.data?.timer ?? null} onPress={(id) => router.push(`/job/${id}`)} />
      <QuickActions />

      {data && (data.pending_bookings > 0 || data.low_stock > 0) ? (
        <Row gap={10}>
          {data.pending_bookings > 0 ? (
            <Card style={{ flex: 1, backgroundColor: '#fffbeb', borderColor: '#fcd34d' }} onPress={() => router.push('/diary')}>
              <Row gap={8}><Icon name="calendar" size={16} color="#b45309" /><T weight="800" color="#92400e">{data.pending_bookings} booking{data.pending_bookings > 1 ? 's' : ''}</T></Row>
              <T size={12} color="#b45309">waiting for confirmation</T>
            </Card>
          ) : null}
          {data.low_stock > 0 ? (
            <Card style={{ flex: 1, backgroundColor: '#fef2f2', borderColor: '#fca5a5' }} onPress={() => router.push({ pathname: '/stock', params: { low: '1' } })}>
              <Row gap={8}><Icon name="package" size={16} color="#b91c1c" /><T weight="800" color="#991b1b">{data.low_stock} low stock</T></Row>
              <T size={12} color="#b91c1c">items at reorder level</T>
            </Card>
          ) : null}
        </Row>
      ) : null}

      {data ? (
        <>
          <Segmented scroll value={period} onChange={setPeriod} options={Object.entries(data.periods).map(([value, label]) => ({ value, label }))} />
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 10 }}>
            {data.kpis.map((k) => {
              const up = (k.delta ?? 0) >= 0;
              const good = k.key === 'outstanding' ? !up : up;
              return (
                <Card key={k.key} style={{ width: '48.4%', gap: 6, padding: 14 }}>
                  <T size={12} muted weight="600" numberOfLines={1}>{k.label}</T>
                  <T size={20} weight="800" numberOfLines={1} style={{ letterSpacing: -0.4 }}>{k.money ? compactMoney(k.value) : k.value}</T>
                  {k.delta !== undefined && k.delta !== null ? (
                    <Row gap={4}>
                      <Icon name={up ? 'trending-up' : 'trending-down'} size={13} color={good ? p.success : p.danger} />
                      <T size={12} weight="700" color={good ? p.success : p.danger}>{Math.abs(k.delta).toFixed(1)}%</T>
                      <T size={11} muted>vs prior</T>
                    </Row>
                  ) : k.hint ? <T size={12} muted>{k.hint}</T> : null}
                </Card>
              );
            })}
          </View>

          {data.target ? (
            <Section title="Monthly revenue target">
              <Card><TargetMeter value={data.target.mtd} target={data.target.target} expectedPct={data.target.day_pct} /></Card>
            </Section>
          ) : null}

          <Section title="Revenue vs cash collected">
            <Card>
              <Row style={{ justifyContent: 'space-between', marginBottom: 8 }}>
                <T size={13} muted>Gross margin</T>
                <Badge label={`${data.margin}%`} color={data.margin >= 40 ? p.success : p.warning} />
              </Row>
              <BarChart labels={data.series.labels} series={[{ name: 'Revenue', values: data.series.revenue, color: brand.navy700 }, { name: 'Collected', values: data.series.collected, color: brand.crimson }]} format={money} />
            </Card>
          </Section>

          <Section title="Sales mix">
            <Card><Donut data={data.mix} centerLabel="Sales" /></Card>
          </Section>

          <Section title="Payments by method">
            <Card><Donut data={data.methods} centerLabel="Collected" /></Card>
          </Section>

          <Section title="Workshop pipeline" action={{ label: 'Open board', onPress: () => router.push('/jobs') }}>
            <Card><HBars data={data.pipeline.filter((x) => x.value > 0).map((x) => ({ label: x.label, value: x.value, color: x.color }))} /></Card>
          </Section>

          {data.technicians.length ? (
            <Section title="Technician leaderboard">
              <Card>
                <HBars
                  format={(v) => money(v, false)}
                  data={data.technicians.map((t) => ({ label: t.name, value: t.revenue, sub: `${t.jobs} jobs · ${t.hours_sold}h sold of ${t.hours_worked}h worked · ${t.efficiency}% efficient` }))}
                />
              </Card>
            </Section>
          ) : null}
          <T size={11} muted center>{data.from} to {data.to} · amounts exclude VAT unless stated</T>
        </>
      ) : null}
    </Screen>
  );
}

function TechnicianHome() {
  const me = useMe();
  const jobs = useApi<Paged<JobRow>>('jobs?mine=1');
  const timer = useApi<{ timer: { job_id: number; number: string; registration: string; started_at: string } | null }>('timer');
  const tasks = useApi<{ items: Task[] }>('tasks');
  const refresh = () => { jobs.refresh(); timer.refetch(); tasks.refetch(); };

  return (
    <Screen refreshing={jobs.refreshing} onRefresh={refresh}>
      <View style={{ gap: 2 }}>
        <T muted size={14}>{greeting()},</T>
        <Title>{me.user.first_name}</Title>
      </View>
      <TimerBanner timer={timer.data?.timer ?? null} onPress={(id) => router.push(`/job/${id}`)} />
      <QuickActions tech />
      <Section title={`Assigned to me${jobs.data ? ` (${jobs.data.items.length})` : ''}`}>
        {jobs.isLoading ? <Loading /> : null}
        {jobs.data?.items.length === 0 ? <Card><Empty icon="check-circle" title="No jobs assigned" message="New jobs assigned to you appear here, and you get a notification." /></Card> : null}
        {jobs.data?.items.map((j) => <JobCardRow key={j.id} job={j} onPress={() => router.push(`/job/${j.id}`)} />)}
      </Section>
      {tasks.data?.items.length ? (
        <Section title="My tasks" action={{ label: 'All tasks', onPress: () => router.push('/tasks') }}>
          <Card padded={false}>
            {tasks.data.items.slice(0, 4).map((t) => (
              <Row key={t.id} style={{ padding: 14 }} gap={10}>
                <Icon name="circle" size={16} color={t.overdue ? '#ef4444' : '#94a3b8'} />
                <T style={{ flex: 1 }} numberOfLines={1}>{t.title}</T>
                {t.overdue ? <Badge label="Overdue" color="#ef4444" /> : null}
              </Row>
            ))}
          </Card>
        </Section>
      ) : null}
      <Label style={{ textAlign: 'center' }}>Prices are hidden in technician mode</Label>
    </Screen>
  );
}
