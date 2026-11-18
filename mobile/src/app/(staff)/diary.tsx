import { router } from 'expo-router';
import { useMemo, useState } from 'react';
import { Alert, Linking, Pressable, View } from 'react-native';
import { Badge, Button, Card, Empty, ErrorView, Icon, IconButton, Loading, Row, Screen, Section, T } from '@/components/ui';
import { useMe } from '@/lib/auth';
import { dayLabel, fdatetime, ftime, parseDate, ymd } from '@/lib/format';
import { useAction, useApi } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';

type DiaryData = {
  jobs: { id: number; number: string; at: string; until: string | null; status_label: string; status_color: string; registration: string; vehicle: string; customer: string | null; job_type: string | null }[];
  requests: { id: number; name: string; phone: string; email: string | null; registration: string | null; vehicle: string | null; service: string | null; at: string; notes: string | null; created_at: string }[];
};

function monday(d: Date) {
  const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  x.setDate(x.getDate() - ((x.getDay() + 6) % 7));
  return x;
}

export default function Diary() {
  const p = usePalette();
  const me = useMe();
  const [weekStart, setWeekStart] = useState(() => monday(new Date()));
  const [day, setDay] = useState(() => ymd(new Date()));
  const days = useMemo(() => Array.from({ length: 7 }, (_, i) => new Date(weekStart.getFullYear(), weekStart.getMonth(), weekStart.getDate() + i)), [weekStart]);
  const from = ymd(days[0]);
  const to = ymd(days[6]);
  const diary = useApi<DiaryData>(`diary?from=${from}&to=${to}`);
  const confirm = useAction<{ id: number }, { job_card_id: number; message: string }>((b) => `bookings/${b.id}/confirm`, { invalidate: ['diary', 'dashboard', 'jobs', 'board'], onSuccess: (r) => router.push(`/job/${r.job_card_id}`) });
  const decline = useAction<{ id: number }>((b) => `bookings/${b.id}/decline`, { invalidate: ['diary', 'dashboard'] });

  const shift = (weeks: number) => {
    const n = new Date(weekStart);
    n.setDate(n.getDate() + weeks * 7);
    setWeekStart(n);
    setDay(ymd(n));
  };

  if (diary.isLoading && !diary.data) return <Loading />;
  if (diary.error && !diary.data) return <ErrorView error={diary.error} onRetry={diary.refresh} />;
  const data = diary.data!;
  const counts: Record<string, number> = {};
  data.jobs.forEach((j) => { const k = j.at.slice(0, 10); counts[k] = (counts[k] ?? 0) + 1; });
  const dayJobs = data.jobs.filter((j) => j.at.startsWith(day));
  const today = ymd(new Date());

  return (
    <Screen refreshing={diary.refreshing} onRefresh={diary.refresh}>
      <Card style={{ padding: 10, gap: 8 }}>
        <Row style={{ justifyContent: 'space-between' }}>
          <IconButton icon="chevron-left" onPress={() => shift(-1)} />
          <Pressable onPress={() => { setWeekStart(monday(new Date())); setDay(today); }}>
            <T weight="800">{dayLabel(days[0])} to {dayLabel(days[6])}</T>
          </Pressable>
          <IconButton icon="chevron-right" onPress={() => shift(1)} />
        </Row>
        <Row gap={4}>
          {days.map((d) => {
            const k = ymd(d);
            const on = k === day;
            return (
              <Pressable key={k} onPress={() => setDay(k)} style={{ flex: 1, alignItems: 'center', paddingVertical: 8, borderRadius: 12, backgroundColor: on ? p.primary : 'transparent' }}>
                <T size={11} weight="700" color={on ? p.primaryText : p.muted}>{['S', 'M', 'T', 'W', 'T', 'F', 'S'][d.getDay()]}</T>
                <T size={16} weight="800" color={on ? p.primaryText : k === today ? p.accent : p.text}>{d.getDate()}</T>
                <View style={{ height: 6, marginTop: 2, flexDirection: 'row', gap: 2 }}>
                  {Array.from({ length: Math.min(3, counts[k] ?? 0) }).map((_, i) => <View key={i} style={{ width: 4, height: 4, borderRadius: 2, backgroundColor: on ? p.primaryText : p.accent }} />)}
                </View>
              </Pressable>
            );
          })}
        </Row>
      </Card>

      <Section title={`${dayLabel(parseDate(day + ' 00:00:00')!)} · ${dayJobs.length} booked`}>
        {dayJobs.length === 0 ? <Card><Empty icon="calendar" title="Nothing booked" message="Free capacity for walk-ins." /></Card> : null}
        {dayJobs.map((j) => (
          <Card key={j.id} onPress={() => router.push(`/job/${j.id}`)} style={{ flexDirection: 'row', gap: 12, alignItems: 'center' }}>
            <View style={{ width: 56, alignItems: 'center' }}>
              <T weight="800" size={16}>{ftime(j.at)}</T>
              {j.until ? <T size={11} muted>to {ftime(j.until)}</T> : null}
            </View>
            <View style={{ width: 3, alignSelf: 'stretch', borderRadius: 2, backgroundColor: j.status_color }} />
            <View style={{ flex: 1, gap: 3 }}>
              <T weight="800">{j.registration} · {j.vehicle}</T>
              <T size={13} muted numberOfLines={1}>{[j.job_type, j.customer].filter(Boolean).join(' · ')}</T>
              <Badge label={j.status_label} color={j.status_color} />
            </View>
            <Icon name="chevron-right" size={18} color={p.faint} />
          </Card>
        ))}
      </Section>

      {me.mode !== 'technician' ? (
        <Section title={`Booking requests (${data.requests.length})`}>
          {data.requests.length === 0 ? <Card><T muted center size={13}>No pending requests. Online and app bookings land here.</T></Card> : null}
          {data.requests.map((b) => (
            <Card key={b.id} style={{ gap: 8, borderColor: '#fcd34d' }}>
              <Row style={{ justifyContent: 'space-between' }}>
                <T weight="800" style={{ flex: 1 }}>{b.name}</T>
                <Badge label="Request" color="#d97706" />
              </Row>
              <Row gap={6}><Icon name="clock" size={14} color={p.muted} /><T size={13}>{fdatetime(b.at)}</T></Row>
              <Row gap={6}><Icon name="tool" size={14} color={p.muted} /><T size={13}>{b.service || 'Service'}{b.registration ? ` · ${b.registration}` : ''}{b.vehicle ? ` · ${b.vehicle}` : ''}</T></Row>
              {b.notes ? <T size={13} muted>{b.notes}</T> : null}
              <Row gap={8}>
                <Button title="Call" icon="phone" small variant="secondary" onPress={() => Linking.openURL(`tel:${b.phone}`)} />
                <Button title="Decline" small variant="danger" onPress={() => Alert.alert('Decline booking?', `${b.name} · ${fdatetime(b.at)}`, [{ text: 'Cancel', style: 'cancel' }, { text: 'Decline', style: 'destructive', onPress: () => decline.mutate({ id: b.id }) }])} />
                <Button title="Confirm" icon="check" small flex loading={confirm.isPending && confirm.variables?.id === b.id} onPress={() => confirm.mutate({ id: b.id })} />
              </Row>
            </Card>
          ))}
        </Section>
      ) : null}
    </Screen>
  );
}
