import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Pressable, View } from 'react-native';
import { Button, Card, Field, Icon, Row, Screen, Segmented, T } from '@/components/ui';
import { useAction } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';

export default function NewCustomer() {
  const { then } = useLocalSearchParams<{ then?: string }>();
  const [type, setType] = useState<'individual' | 'business'>('individual');
  const [f, setF] = useState({ first_name: '', last_name: '', company_name: '', phone: '', email: '' });
  const [popia, setPopia] = useState(false);
  const [marketing, setMarketing] = useState(false);
  const create = useAction<Record<string, unknown>, { id: number }>('customers', {
    success: 'Customer added',
    invalidate: ['customers'],
    onSuccess: (r) => {
      if (then === 'checkin') router.replace({ pathname: '/vehicle/new', params: { customer_id: String(r.id), then: 'checkin' } });
      else router.replace(`/customer/${r.id}`);
    },
  });
  const set = (k: keyof typeof f) => (v: string) => setF({ ...f, [k]: v });

  return (
    <Screen footer={<Button title={then === 'checkin' ? 'Next: add vehicle' : 'Save customer'} icon={then === 'checkin' ? 'arrow-right' : 'check'} flex variant="accent" loading={create.isPending} onPress={() => create.mutate({ ...f, type, popia, marketing })} />}>
      <Segmented value={type} onChange={setType} options={[{ value: 'individual', label: 'Individual' }, { value: 'business', label: 'Business' }]} />
      {type === 'business' ? <Field label="Company name" value={f.company_name} onChangeText={set('company_name')} /> : null}
      <Row gap={10}>
        <View style={{ flex: 1 }}><Field label={type === 'business' ? 'Contact first name' : 'First name'} value={f.first_name} onChangeText={set('first_name')} autoCapitalize="words" /></View>
        <View style={{ flex: 1 }}><Field label="Last name" value={f.last_name} onChangeText={set('last_name')} autoCapitalize="words" /></View>
      </Row>
      <Field label="Mobile number" icon="phone" value={f.phone} onChangeText={set('phone')} keyboardType="phone-pad" placeholder="082 123 4567" />
      <Field label="Email" icon="mail" value={f.email} onChangeText={set('email')} keyboardType="email-address" autoCapitalize="none" hint="Invoices and pay links are emailed here." />
      <Card style={{ gap: 12 }}>
        <Toggle on={popia} onPress={() => setPopia(!popia)} title="POPIA consent given" sub="The customer agreed that we may store and use their details for workshop services." />
        <Toggle on={marketing} onPress={() => setMarketing(!marketing)} title="Marketing opt-in" sub="Service reminders and specials by SMS, WhatsApp or email." />
      </Card>
    </Screen>
  );
}

function Toggle({ on, onPress, title, sub }: { on: boolean; onPress: () => void; title: string; sub: string }) {
  const p = usePalette();
  return (
    <Pressable onPress={onPress} style={{ flexDirection: 'row', gap: 12, alignItems: 'flex-start' }}>
      <View style={{ width: 24, height: 24, borderRadius: 7, borderWidth: 2, borderColor: on ? p.accent : p.border, backgroundColor: on ? p.accent : 'transparent', alignItems: 'center', justifyContent: 'center', marginTop: 1 }}>
        {on ? <Icon name="check" size={15} color="#fff" /> : null}
      </View>
      <View style={{ flex: 1 }}>
        <T weight="700">{title}</T>
        <T size={12.5} muted>{sub}</T>
      </View>
    </Pressable>
  );
}
