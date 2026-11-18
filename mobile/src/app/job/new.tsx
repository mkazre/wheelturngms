import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { Scanner } from '@/components/workshop';
import { Button, Card, Chip, Field, Icon, IconButton, Label, ListItem, Row, Screen, Section, Segmented, T } from '@/components/ui';
import { km, ymd } from '@/lib/format';
import { useAction, useApi, useDebounced } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';
import type { VehicleDetail, VehicleRow } from '@/lib/types';

const JOB_TYPES = ['Service', 'Repair', 'Diagnostics', 'Brakes', 'Suspension', 'Clutch & gearbox', 'Electrical', 'Air conditioning', 'Tyres & alignment', 'Roadworthy', 'Panel & paint', 'Warranty', 'Insurance claim', 'Other'];
const FUEL = ['E', '1/4', '1/2', '3/4', 'F'];

export default function CheckIn() {
  const params = useLocalSearchParams<{ vehicle_id?: string }>();
  const p = usePalette();
  const [vehicleId, setVehicleId] = useState<number | null>(params.vehicle_id ? Number(params.vehicle_id) : null);
  const [q, setQ] = useState('');
  const [scan, setScan] = useState(false);
  const dq = useDebounced(q);
  const search = useApi<{ items: VehicleRow[] }>(!vehicleId && dq.length >= 2 ? `vehicles?q=${encodeURIComponent(dq)}` : null);
  const vehicle = useApi<VehicleDetail>(vehicleId ? `vehicles/${vehicleId}` : null);
  const staff = useApi<{ items: { id: number; name: string; role: string }[] }>('staff');
  const packages = useApi<{ items: { id: number; name: string; job_type: string | null; hours: number }[] }>('packages');

  const [mode, setMode] = useState<'checked_in' | 'booked'>('checked_in');
  const [jobType, setJobType] = useState('Service');
  const [complaint, setComplaint] = useState('');
  const [odoInput, setOdo] = useState<string | null>(null);
  const [fuel, setFuel] = useState('1/2');
  const [priority, setPriority] = useState('normal');
  const [promised, setPromised] = useState<string | null>(null);
  const [bookedFor, setBookedFor] = useState('');
  const [mechanics, setMechanics] = useState<number[]>([]);
  const [pkg, setPkg] = useState<number | null>(null);

  const create = useAction<Record<string, unknown>, { id: number }>('jobs', {
    success: mode === 'booked' ? 'Booking created' : 'Vehicle checked in',
    invalidate: ['jobs', 'board', 'dashboard', 'diary'],
    onSuccess: (r) => router.replace(`/job/${r.id}`),
  });

  const today = new Date();
  const tomorrow = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);
  const promiseOptions = [
    { label: 'Today 13:00', value: `${ymd(today)} 13:00` },
    { label: 'Today 17:00', value: `${ymd(today)} 17:00` },
    { label: 'Tomorrow 12:00', value: `${ymd(tomorrow)} 12:00` },
    { label: 'Tomorrow 17:00', value: `${ymd(tomorrow)} 17:00` },
  ];

  if (!vehicleId) {
    return (
      <Screen>
        <T muted>Find the vehicle by registration, VIN, owner, make or model. New here? Add the customer first.</T>
        <Row gap={8}>
          <View style={{ flex: 1 }}><Field icon="search" value={q} onChangeText={setQ} placeholder="e.g. CA 123-456" autoCapitalize="characters" autoFocus /></View>
          <IconButton icon="maximize" bg={p.card} onPress={() => setScan(true)} />
        </Row>
        {search.isFetching ? <ActivityIndicator /> : null}
        {search.data?.items.map((v) => (
          <Card key={v.id} padded={false}>
            <ListItem icon="truck" title={`${v.registration} · ${v.label}`} subtitle={`${v.owner}${v.odometer ? ` · ${km(v.odometer)}` : ''}`} onPress={() => setVehicleId(v.id)} />
          </Card>
        ))}
        {dq.length >= 2 && search.data?.items.length === 0 ? <T muted center>{`No vehicle found for "${dq}".`}</T> : null}
        <Section title="New to the workshop?">
          <Row gap={10}>
            <Button title="New customer" icon="user-plus" variant="secondary" flex onPress={() => router.push({ pathname: '/customer/new', params: { then: 'checkin' } })} />
            <Button title="Add vehicle" icon="plus" variant="secondary" flex onPress={() => router.push({ pathname: '/vehicle/new', params: { then: 'checkin' } })} />
          </Row>
        </Section>
        <Scanner visible={scan} title="Scan licence disc or VIN" onClose={() => setScan(false)} onScan={(code) => { setScan(false); setQ(extractReg(code)); }} />
      </Screen>
    );
  }

  const v = vehicle.data;
  const odo = odoInput ?? (v?.odometer ? String(v.odometer) : '');
  const techs = (staff.data?.items ?? []).filter((s) => s.role === 'mechanic');
  const submit = () =>
    create.mutate({
      customer_id: v?.owner.id,
      vehicle_id: vehicleId,
      status: mode,
      job_type: jobType,
      customer_complaint: complaint,
      odometer_in: Number(odo) || undefined,
      fuel_level: mode === 'checked_in' ? fuel : undefined,
      priority,
      promised_at: promised ?? undefined,
      booked_for: mode === 'booked' && bookedFor ? bookedFor : undefined,
      mechanics,
      canned_job_id: pkg ?? undefined,
    });

  return (
    <Screen footer={<Button title={mode === 'booked' ? 'Create booking' : 'Check in vehicle'} icon="check" variant="accent" flex loading={create.isPending} disabled={!v} onPress={submit} />}>
      <Card padded={false}>
        {v ? <ListItem icon="truck" title={`${v.registration} · ${v.label}`} subtitle={`${v.owner.name}${v.odometer ? ` · last ${km(v.odometer)}` : ''}`} right={<Button title="Change" small variant="ghost" onPress={() => setVehicleId(null)} />} /> : <ActivityIndicator style={{ margin: 16 }} />}
      </Card>
      <Segmented value={mode} onChange={setMode} options={[{ value: 'checked_in', label: 'Check in now' }, { value: 'booked', label: 'Book for later' }]} />

      {mode === 'booked' ? <Field label="Booking date and time" value={bookedFor} onChangeText={setBookedFor} placeholder="YYYY-MM-DD HH:MM" hint="e.g. 2026-11-20 08:00" /> : null}

      <Section title="Job type">
        <Row wrap gap={6}>{JOB_TYPES.map((t) => <Chip key={t} label={t} selected={jobType === t} onPress={() => setJobType(t)} />)}</Row>
      </Section>

      <Field label="Customer complaint / request" value={complaint} onChangeText={setComplaint} multiline placeholder="What does the customer report?" />

      {mode === 'checked_in' ? (
        <>
          <Field label="Odometer in (km)" icon="activity" value={odo} onChangeText={setOdo} keyboardType="number-pad" />
          <View style={{ gap: 6 }}>
            <Label>Fuel level</Label>
            <Row gap={6}>{FUEL.map((f) => <View key={f} style={{ flex: 1 }}><Chip label={f} selected={fuel === f} onPress={() => setFuel(f)} /></View>)}</Row>
          </View>
        </>
      ) : null}

      <View style={{ gap: 6 }}>
        <Label>Priority</Label>
        <Segmented value={priority} onChange={setPriority} options={[{ value: 'low', label: 'Low' }, { value: 'normal', label: 'Normal' }, { value: 'high', label: 'High' }, { value: 'urgent', label: 'Urgent' }]} />
      </View>

      <View style={{ gap: 6 }}>
        <Label>Promised by</Label>
        <Row wrap gap={6}>
          {promiseOptions.map((o) => <Chip key={o.value} label={o.label} icon="clock" selected={promised === o.value} onPress={() => setPromised(promised === o.value ? null : o.value)} />)}
        </Row>
      </View>

      {techs.length ? (
        <View style={{ gap: 6 }}>
          <Label>Assign technicians</Label>
          <Row wrap gap={6}>
            {techs.map((t) => <Chip key={t.id} label={t.name} icon="tool" selected={mechanics.includes(t.id)} onPress={() => setMechanics(mechanics.includes(t.id) ? mechanics.filter((x) => x !== t.id) : [...mechanics, t.id])} />)}
          </Row>
          <T size={12} muted>They get a push notification with the job.</T>
        </View>
      ) : null}

      {packages.data?.items.length ? (
        <View style={{ gap: 6 }}>
          <Label>Service package (optional)</Label>
          <Row wrap gap={6}>
            {packages.data.items.map((pk) => <Chip key={pk.id} label={pk.name} icon="layers" selected={pkg === pk.id} onPress={() => setPkg(pkg === pk.id ? null : pk.id)} />)}
          </Row>
        </View>
      ) : null}

      <Card style={{ flexDirection: 'row', gap: 10, alignItems: 'center' }}>
        <Icon name="info" size={16} color={p.muted} />
        <T size={12.5} muted style={{ flex: 1 }}>After check-in, record existing damage and capture the customer&apos;s signature on the Check-in tab.</T>
      </Card>
    </Screen>
  );
}

/** SA licence discs encode the registration among %-separated fields; fall back to the raw value. */
function extractReg(code: string): string {
  if (code.includes('%')) {
    const parts = code.split('%').map((s) => s.trim()).filter(Boolean);
    const reg = parts.find((s) => /^[A-Z]{2,3}\s?\d{2,3}\s?[A-Z]{0,3}\s?(GP|CA|ZN|EC|FS|MP|NW|NC|L|WP)?$/i.test(s) || /^[A-Z0-9 -]{5,10}(GP|WP|CA|ZN|EC|FS|MP|NW|NC|L)$/i.test(s));
    if (reg) return reg.toUpperCase();
  }
  return code.length === 17 ? code.toUpperCase() : code.slice(0, 20).toUpperCase();
}
