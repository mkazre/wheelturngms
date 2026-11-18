import { router } from 'expo-router';
import { Alert, Linking, View } from 'react-native';
import { Avatar, Card, Divider, ListItem, Screen, Section, T } from '@/components/ui';
import { useAuth, useMe } from '@/lib/auth';

export default function Account() {
  const me = useMe();
  const { signOut } = useAuth();
  const wa = me.company.phone?.replace(/\D/g, '').replace(/^0/, '27');
  return (
    <Screen>
      <Card style={{ flexDirection: 'row', alignItems: 'center', gap: 14 }}>
        <Avatar name={`${me.user.first_name} ${me.user.last_name}`} size={52} />
        <View style={{ flex: 1 }}>
          <T weight="800" size={17}>{me.user.first_name} {me.user.last_name}</T>
          <T muted size={13}>{me.user.email}</T>
        </View>
      </Card>
      <Section title={me.company.name}>
        <Card padded={false}>
          {me.company.phone ? <ListItem icon="phone" title="Call the workshop" subtitle={me.company.phone} onPress={() => Linking.openURL(`tel:${me.company.phone}`)} /> : null}
          {wa ? <><Divider inset={64} /><ListItem icon="message-circle" iconColor="#16a34a" title="WhatsApp us" onPress={() => Linking.openURL(`https://wa.me/${wa}`)} /></> : null}
          {me.company.email ? <><Divider inset={64} /><ListItem icon="mail" title="Email us" subtitle={me.company.email} onPress={() => Linking.openURL(`mailto:${me.company.email}`)} /></> : null}
          {me.company.address ? <><Divider inset={64} /><ListItem icon="map-pin" title="Directions" subtitle={me.company.address.replace(/\n/g, ', ')} onPress={() => Linking.openURL(`https://maps.google.com/?q=${encodeURIComponent(me.company.address)}`)} /></> : null}
        </Card>
      </Section>
      <Section title="Account">
        <Card padded={false}>
          <ListItem icon="bell" title="Notifications" onPress={() => router.push('/notifications')} />
          <Divider inset={64} />
          <ListItem icon="shield" title="Password & notifications" onPress={() => router.push('/profile')} />
          <Divider inset={64} />
          <ListItem icon="log-out" iconColor="#dc2626" title="Sign out" onPress={() => Alert.alert('Sign out?', undefined, [{ text: 'Cancel', style: 'cancel' }, { text: 'Sign out', style: 'destructive', onPress: signOut }])} />
        </Card>
      </Section>
    </Screen>
  );
}
