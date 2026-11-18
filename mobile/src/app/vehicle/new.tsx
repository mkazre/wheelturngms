import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { Scanner } from '@/components/workshop';
import { Button, Card, Chip, Field, IconButton, Label, ListItem, Row, Screen, T, useToast } from '@/components/ui';
import { api } from '@/lib/api';
import { useAction, useApi, useDebounced } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';
import type { CustomerDetail, CustomerRow } from '@/lib/types';

const FUEL = ['Petrol', 'Diesel', 'Hybrid', 'Electric'];
const GEARBOX = ['Manual', 'Automatic'];

export default function NewVehicle() {
  const params = useLocalSearchParams<{ customer_id?: string; then?: string }>();
  const p = usePalette();
  const toast = useToast();
  const [customerId, setCustomerId] = useState<number | null>(params.customer_id ? Number(params.customer_id) : null);
  const [q, setQ] = useState('');
  const dq = useDebounced(q);
  const customers = useApi<{ items: CustomerRow[] }>(!customerId && dq.length >= 2 ? `customers?q=${encodeURIComponent(dq)}` : null);
  const owner = useApi<CustomerDetail>(customerId ? `customers/${customerId}` : null);
  const [f, setF] = useState({ registration: '', vin: '', make: '', model: '', year: '', colour: '', engine_size: '', fuel_type: 'Petrol', transmission: 'Manual', odometer: '' });
  const [scan, setScan] = useState(false);
  const [decoding, setDecoding] = useState(false);
  const set = (k: keyof typeof f) => (v: string) => setF((cur) => ({ ...cur, [k]: v }));

  const create = useAction<Record<string, unknown>, { id: number }>('vehicles', {
    success: 'Vehicle added',
    invalidate: ['vehicles', 'customers'],
    onSuccess: (r) => {
      if (params.then === 'checkin') router.replace({ pathname: '/job/new', params: { vehicle_id: String(r.id) } });
      else router.replace(`/vehicle/${r.id}`);
    },
  });

  const decode = async (vin = f.vin) => {
    const clean = vin.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    setF((cur) => ({ ...cur, vin: clean }));
    if (clean.length !== 17) return toast('A VIN has 17 characters.', 'error');
    setDecoding(true);
    try {
      const r = await api.get<{ make: string | null; model: string | null; year: string | null; engine_size: string | null; fuel_type: string | null; transmission: string | null }>(`vin?vin=${clean}`);
      setF((cur) => ({ ...cur, make: r.make ?? cur.make, model: r.model ?? cur.model, year: r.year ?? cur.year, engine_size: r.engine_size ?? cur.engine_size, fuel_type: r.fuel_type ?? cur.fuel_type, transmission: r.transmission ?? cur.transmission }));
      toast('VIN decoded');
    } catch (e) {
      toast((e as Error).message, 'error');
    } finally {
      setDecoding(false);
    }
  };

  if (!customerId) {
    return (
      <Screen>
        <T muted>Who owns this vehicle?</T>
        <Field icon="search" value={q} onChangeText={setQ} placeholder="Search customers" autoFocus />
        {customers.isFetching ? <ActivityIndicator /> : null}
        {customers.data?.items.map((c) => (
          <Card key={c.id} padded={false}><ListItem avatar={c.name} title={c.name} subtitle={c.phone ?? undefined} onPress={() => setCustomerId(c.id)} /></Card>
        ))}
        <Button title="New customer" icon="user-plus" variant="secondary" onPress={() => router.replace({ pathname: '/customer/new', params: { then: params.then ?? '' } })} />
      </Screen>
    );
  }

  return (
    <Screen footer={<Button title={params.then === 'checkin' ? 'Next: check in' : 'Save vehicle'} icon="check" flex variant="accent" loading={create.isPending} onPress={() => create.mutate({ ...f, customer_id: customerId, year: Number(f.year) || undefined, odometer: Number(f.odometer) || undefined })} />}>
      {owner.data ? <Card padded={false}><ListItem avatar={owner.data.name} title={owner.data.name} subtitle="Owner" /></Card> : null}
      <Field label="Registration" value={f.registration} onChangeText={(v) => set('registration')(v.toUpperCase())} autoCapitalize="characters" placeholder="CA 123-456" />
      <View style={{ gap: 6 }}>
        <Label>VIN</Label>
        <Row gap={8}>
          <View style={{ flex: 1 }}><Field value={f.vin} onChangeText={(v) => set('vin')(v.toUpperCase())} autoCapitalize="characters" maxLength={17} placeholder="17 characters" /></View>
          <IconButton icon="maximize" bg={p.card} onPress={() => setScan(true)} />
        </Row>
        <Button title="Decode VIN" icon="cpu" small variant="secondary" loading={decoding} onPress={() => decode()} />
      </View>
      <Row gap={10}>
        <View style={{ flex: 1 }}><Field label="Make" value={f.make} onChangeText={set('make')} autoCapitalize="words" /></View>
        <View style={{ flex: 1 }}><Field label="Model" value={f.model} onChangeText={set('model')} autoCapitalize="words" /></View>
      </Row>
      <Row gap={10}>
        <View style={{ flex: 1 }}><Field label="Year" value={f.year} onChangeText={set('year')} keyboardType="number-pad" maxLength={4} /></View>
        <View style={{ flex: 1 }}><Field label="Colour" value={f.colour} onChangeText={set('colour')} autoCapitalize="words" /></View>
      </Row>
      <Row gap={10}>
        <View style={{ flex: 1 }}><Field label="Engine" value={f.engine_size} onChangeText={set('engine_size')} placeholder="2.0L" /></View>
        <View style={{ flex: 1 }}><Field label="Odometer (km)" value={f.odometer} onChangeText={set('odometer')} keyboardType="number-pad" /></View>
      </Row>
      <View style={{ gap: 6 }}><Label>Fuel</Label><Row wrap gap={6}>{FUEL.map((x) => <Chip key={x} label={x} selected={f.fuel_type === x} onPress={() => set('fuel_type')(x)} />)}</Row></View>
      <View style={{ gap: 6 }}><Label>Gearbox</Label><Row wrap gap={6}>{GEARBOX.map((x) => <Chip key={x} label={x} selected={f.transmission === x} onPress={() => set('transmission')(x)} />)}</Row></View>
      <Scanner visible={scan} title="Scan VIN barcode" types={['code39', 'code128', 'datamatrix', 'qr', 'pdf417']} onClose={() => setScan(false)} onScan={(code) => { setScan(false); const vin = (code.match(/[A-HJ-NPR-Z0-9]{17}/i)?.[0] ?? code).toUpperCase(); decode(vin); }} />
    </Screen>
  );
}
