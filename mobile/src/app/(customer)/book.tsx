import { router } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, ScrollView, View } from 'react-native';
import { Button, Card, Chip, Field, Icon, Label, Loading, Row, Screen, T } from '@/components/ui';
import { dayLabel, ymd } from '@/lib/format';
import { useAction, useApi } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';
import type { MyOverview } from '@/lib/types';

const TIMES = ['07:30', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00'];

export default function Book() {
  const p = usePalette();
  const ov = useApi<MyOverview>('my/overview');
  const services = useApi<{ items: string[] }>('my/services');
  const days = useMemo(() => {
    const out: Date[] = [];
    const d = new Date();
    d.setDate(d.getDate() + 1);
    while (out.length < 14) {
      if (d.getDay() !== 0) out.push(new Date(d));
      d.setDate(d.getDate() + 1);
    }
    return out;
  }, []);
  const [vehicle, setVehicle] = useState<number | null>(null);
  const [service, setService] = useState<string | null>(null);
  const [day, setDay] = useState(ymd(days[0]));
  const [time, setTime] = useState('08:00');
  const [notes, setNotes] = useState('');
  const [done, setDone] = useState(false);
  const book = useAction<Record<string, unknown>>('my/bookings', { invalidate: ['my/overview'], onSuccess: () => setDone(true) });

  if (ov.isLoading) return <Loading />;
  const vehicles = ov.data?.vehicles ?? [];
  const selectedVehicle = vehicle ?? vehicles[0]?.id ?? null;
  const isSaturday = new Date(day + 'T00:00:00').getDay() === 6;
  const times = isSaturday ? TIMES.filter((t) => t < '12:00') : TIMES;

  if (done) {
    return (
      <Screen>
        <Card style={{ alignItems: 'center', gap: 12, paddingVertical: 30 }}>
          <View style={{ width: 64, height: 64, borderRadius: 32, backgroundColor: '#10b98122', alignItems: 'center', justifyContent: 'center' }}><Icon name="check" size={30} color="#10b981" /></View>
          <T size={20} weight="800" center>Request sent</T>
          <T muted center>We will confirm your booking shortly. You will get a notification as soon as it is confirmed.</T>
          <Button title="Back to my garage" onPress={() => { setDone(false); router.push('/garage'); }} />
        </Card>
      </Screen>
    );
  }

  return (
    <Screen footer={<Button title="Request booking" icon="send" variant="accent" flex loading={book.isPending} disabled={!service} onPress={() => book.mutate({ vehicle_id: selectedVehicle, service, preferred_at: `${day} ${time}`, notes })} />}>
      {vehicles.length ? (
        <View style={{ gap: 6 }}>
          <Label>Vehicle</Label>
          <Row wrap gap={6}>{vehicles.map((v) => <Chip key={v.id} label={`${v.registration} · ${v.label}`} icon="truck" selected={selectedVehicle === v.id} onPress={() => setVehicle(v.id)} />)}</Row>
        </View>
      ) : null}
      <View style={{ gap: 6 }}>
        <Label>Service</Label>
        <Row wrap gap={6}>{(services.data?.items ?? []).map((s) => <Chip key={s} label={s} selected={service === s} onPress={() => setService(s)} />)}</Row>
      </View>
      <View style={{ gap: 6 }}>
        <Label>Day</Label>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
          {days.map((d) => {
            const k = ymd(d);
            const on = k === day;
            const [dow, num, mon] = dayLabel(d).split(' ');
            return (
              <Pressable key={k} onPress={() => setDay(k)} style={{ width: 64, paddingVertical: 10, borderRadius: 14, alignItems: 'center', backgroundColor: on ? p.primary : p.card, borderWidth: 1, borderColor: on ? p.primary : p.border }}>
                <T size={12} weight="700" color={on ? p.primaryText : p.muted}>{dow}</T>
                <T size={20} weight="800" color={on ? p.primaryText : p.text}>{num}</T>
                <T size={11} color={on ? p.primaryText : p.muted}>{mon}</T>
              </Pressable>
            );
          })}
        </ScrollView>
      </View>
      <View style={{ gap: 6 }}>
        <Label>Drop-off time</Label>
        <Row wrap gap={6}>{times.map((t) => <Chip key={t} label={t} icon="clock" selected={time === t} onPress={() => setTime(t)} />)}</Row>
        {isSaturday ? <T size={12} muted>Saturdays: mornings only.</T> : null}
      </View>
      <Field label="Anything we should know?" value={notes} onChangeText={setNotes} multiline placeholder="Noises, warning lights, when it happens..." />
    </Screen>
  );
}
