import { useState } from 'react';
import { Alert, View } from 'react-native';
import { Avatar, Badge, Button, Card, Field, KeyValue, Screen, Section, T } from '@/components/ui';
import { api } from '@/lib/api';
import { useAuth, useMe } from '@/lib/auth';
import { useAction } from '@/lib/hooks';
import { registerForPush } from '@/lib/push';
import { usePalette } from '@/lib/theme';

export default function Profile() {
  const me = useMe();
  const p = usePalette();
  const { signOut } = useAuth();
  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [confirm, setConfirm] = useState('');
  const change = useAction('me/password', { onSuccess: () => { setCurrent(''); setNext(''); setConfirm(''); } });
  const [pushState, setPushState] = useState<string | null>(null);

  return (
    <Screen>
      <Card style={{ alignItems: 'center', gap: 6, paddingVertical: 20 }}>
        <Avatar name={`${me.user.first_name} ${me.user.last_name}`} size={64} />
        <T size={20} weight="800">{me.user.first_name} {me.user.last_name}</T>
        <T muted>{me.user.email}</T>
        <Badge label={me.user.role_label} color={p.accent} />
      </Card>

      <Section title="Security">
        <Card>
          <KeyValue label="Two-factor sign-in" value={me.user.two_factor ? 'On' : 'Off'} color={me.user.two_factor ? p.success : p.warning} />
          {!me.user.two_factor && me.mode !== 'customer' ? <T size={12.5} muted>Turn on two-factor sign-in from your profile on the web app.</T> : null}
        </Card>
        <Card style={{ gap: 12 }}>
          <T weight="800">Change password</T>
          <Field label="Current password" value={current} onChangeText={setCurrent} secureTextEntry />
          <Field label="New password" value={next} onChangeText={setNext} secureTextEntry hint="At least 8 characters." />
          <Field label="Confirm new password" value={confirm} onChangeText={setConfirm} secureTextEntry error={confirm && confirm !== next ? 'Passwords do not match.' : undefined} />
          <Button title="Update password" icon="lock" loading={change.isPending} disabled={!current || next.length < 8 || next !== confirm} onPress={() => change.mutate({ current, new: next })} />
        </Card>
      </Section>

      <Section title="Notifications on this phone">
        <Card style={{ gap: 10 }}>
          <T size={13} muted>Get alerts for new jobs, bookings, approvals, payments and vehicle updates.</T>
          <Button title="Enable push notifications" icon="bell" variant="secondary" onPress={async () => { const t = await registerForPush(); setPushState(t ? 'Push notifications are on for this phone.' : 'Push is unavailable: allow notifications in Settings, or use the installed Wheelsturn app (not Expo Go).'); }} />
          {pushState ? <T size={12.5} muted>{pushState}</T> : null}
        </Card>
      </Section>

      <Section title="Company">
        <Card>
          <KeyValue label="Workshop" value={me.company.name} />
          {me.company.phone ? <KeyValue label="Phone" value={me.company.phone} /> : null}
          {me.company.email ? <KeyValue label="Email" value={me.company.email} /> : null}
          <KeyValue label="Server" value={api.baseUrl.replace(/^https?:\/\//, '').replace('/api/v1', '')} />
        </Card>
      </Section>

      <View style={{ gap: 10 }}>
        <Button title="Sign out" icon="log-out" variant="danger" onPress={() => Alert.alert('Sign out?', undefined, [{ text: 'Cancel', style: 'cancel' }, { text: 'Sign out', style: 'destructive', onPress: signOut }])} />
      </View>
    </Screen>
  );
}
