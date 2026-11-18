import { Redirect } from 'expo-router';
import { useAuth } from '@/lib/auth';

/** Entry: send each user to their mode's home. */
export default function Index() {
  const { status, me } = useAuth();
  if (status !== 'signedIn' || !me) return <Redirect href="/login" />;
  return <Redirect href={me.mode === 'customer' ? '/garage' : '/home'} />;
}
