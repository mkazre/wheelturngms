import { Image } from 'expo-image';
import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { Alert, Linking, View } from 'react-native';
import { Avatar, Card, Divider, ListItem, Row, Screen, Section, T, type IconName } from '@/components/ui';
import { api } from '@/lib/api';
import { useAuth, useMe } from '@/lib/auth';
import { usePalette } from '@/lib/theme';

type Item = { icon: IconName; label: string; sub?: string; onPress: () => void; color?: string; show?: boolean };

export default function More() {
  const me = useMe();
  const { signOut } = useAuth();
  const p = usePalette();
  const tech = me.mode === 'technician';

  const groups: { title: string; items: Item[] }[] = [
    {
      title: 'Sales & money',
      items: [
        { icon: 'file-text', label: 'Invoices', sub: 'Record payments, send pay links, slips', onPress: () => router.push('/invoices'), show: !tech && me.can.invoices, color: '#059669' },
        { icon: 'file', label: 'Quotes', sub: 'Send, follow up, convert to job', onPress: () => router.push('/quotes'), show: !tech && me.can.quotes, color: '#2563eb' },
      ],
    },
    {
      title: 'Workshop',
      items: [
        { icon: 'truck', label: 'Vehicles', sub: 'History, VIN, service due', onPress: () => router.push('/vehicles'), show: me.can.vehicles, color: '#0e7490' },
        { icon: 'package', label: 'Stock lookup', sub: 'Search or scan a barcode', onPress: () => router.push('/stock'), show: me.can.inventory || tech, color: '#b45309' },
        { icon: 'check-square', label: 'My tasks', sub: 'Follow-ups and reminders', onPress: () => router.push('/tasks'), color: '#7c3aed' },
        { icon: 'cpu', label: 'AI workshop assistant', sub: 'Diagnostics help and fault codes', onPress: () => router.push('/assistant'), show: me.ai, color: '#6d28d9' },
      ],
    },
    {
      title: 'Account',
      items: [
        { icon: 'bell', label: 'Notifications', onPress: () => router.push('/notifications') },
        { icon: 'shield', label: 'Profile & security', onPress: () => router.push('/profile') },
        { icon: 'monitor', label: 'Open the full web app', sub: 'Accounting, reports, settings', onPress: () => WebBrowser.openBrowserAsync(api.webUrl), show: !tech },
        { icon: 'phone', label: `Call ${me.company.name}`, onPress: () => Linking.openURL(`tel:${me.company.phone}`), show: Boolean(me.company.phone) && tech },
        {
          icon: 'log-out', label: 'Sign out', color: '#dc2626',
          onPress: () => Alert.alert('Sign out?', 'You will stop receiving notifications on this phone.', [{ text: 'Cancel', style: 'cancel' }, { text: 'Sign out', style: 'destructive', onPress: signOut }]),
        },
      ],
    },
  ];

  return (
    <Screen>
      <Card style={{ flexDirection: 'row', alignItems: 'center', gap: 14 }} onPress={() => router.push('/profile')}>
        <Avatar name={`${me.user.first_name} ${me.user.last_name}`} size={52} />
        <View style={{ flex: 1 }}>
          <T weight="800" size={17}>{me.user.first_name} {me.user.last_name}</T>
          <T muted size={13}>{me.user.role_label} · {me.user.email}</T>
        </View>
      </Card>
      {groups.map((g) => {
        const items = g.items.filter((i) => i.show !== false);
        if (!items.length) return null;
        return (
          <Section key={g.title} title={g.title}>
            <Card padded={false}>
              {items.map((i, idx) => (
                <View key={i.label}>
                  <ListItem icon={i.icon} iconColor={i.color} title={i.label} subtitle={i.sub} onPress={i.onPress} />
                  {idx < items.length - 1 ? <Divider inset={64} /> : null}
                </View>
              ))}
            </Card>
          </Section>
        );
      })}
      <Row style={{ justifyContent: 'center', marginTop: 8 }}>
        <Image source={require('../../../assets/logo.png')} style={{ width: 150, height: 48, opacity: p.dark ? 0.9 : 1 }} contentFit="contain" />
      </Row>
      <T size={11} muted center>Wheelsturn GMS mobile · v1.0.0</T>
    </Screen>
  );
}
