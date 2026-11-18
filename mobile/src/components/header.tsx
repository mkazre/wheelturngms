import { router } from 'expo-router';
import { View } from 'react-native';
import { useAuth } from '@/lib/auth';
import { IconButton } from './ui';

/** Bell (with unread count) and profile buttons for tab headers. */
export function HeaderActions() {
  const { me } = useAuth();
  return (
    <View style={{ flexDirection: 'row', marginRight: 8 }}>
      <IconButton icon="bell" badge={me?.unread ?? 0} onPress={() => router.push('/notifications')} />
      <IconButton icon="user" onPress={() => router.push(me?.mode === 'customer' ? '/account' : '/profile')} />
    </View>
  );
}
