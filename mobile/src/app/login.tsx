import { Image } from 'expo-image';
import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Button, Card, Field, Icon, Row, T } from '@/components/ui';
import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { brand, usePalette } from '@/lib/theme';

export default function Login() {
  const p = usePalette();
  const insets = useSafeAreaInsets();
  const { signIn } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [code, setCode] = useState('');
  const [needCode, setNeedCode] = useState(false);
  const [show, setShow] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [advanced, setAdvanced] = useState(false);
  const [server, setServer] = useState(api.baseUrl === api.defaultUrl ? '' : api.baseUrl);

  const submit = async () => {
    setError('');
    if (!email.trim() || !password) {
      setError('Enter your email and password.');
      return;
    }
    setBusy(true);
    if (advanced) await api.setServer(server || null);
    const res = await signIn(email.trim().toLowerCase(), password, needCode ? code.trim() : undefined);
    setBusy(false);
    if (res.ok) {
      router.replace('/');
      return;
    }
    if (res.twoFactor) setNeedCode(true);
    setError(res.error);
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: p.bg }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={{ flexGrow: 1 }} keyboardShouldPersistTaps="handled">
        <View style={{ backgroundColor: brand.navy900, paddingTop: insets.top + 36, paddingBottom: 70, paddingHorizontal: 24, borderBottomLeftRadius: 32, borderBottomRightRadius: 32, overflow: 'hidden' }}>
          <View style={{ position: 'absolute', right: -60, top: -40, width: 220, height: 220, borderRadius: 110, borderWidth: 28, borderColor: '#ffffff0d' }} />
          <View style={{ position: 'absolute', left: -50, bottom: -70, width: 180, height: 180, borderRadius: 90, borderWidth: 22, borderColor: '#ef1c2522' }} />
          <View style={{ backgroundColor: '#fff', alignSelf: 'flex-start', borderRadius: 16, paddingHorizontal: 14, paddingVertical: 8 }}>
            <Image source={require('../../assets/logo.png')} style={{ width: 190, height: 60 }} contentFit="contain" />
          </View>
          <T color="#fff" size={28} weight="800" style={{ marginTop: 28, letterSpacing: -0.5 }}>Welcome back</T>
          <T color="#c7cbf0" size={15} style={{ marginTop: 6 }}>Workshop, accounts and your vehicle, in your pocket.</T>
        </View>

        <View style={{ paddingHorizontal: 20, marginTop: -44, gap: 14, paddingBottom: insets.bottom + 24 }}>
          <Card style={{ gap: 14, padding: 20 }}>
            <Field label="Email" icon="mail" value={email} onChangeText={setEmail} autoCapitalize="none" keyboardType="email-address" autoComplete="email" textContentType="username" placeholder="you@example.co.za" returnKeyType="next" />
            <View>
              <Field label="Password" icon="lock" value={password} onChangeText={setPassword} secureTextEntry={!show} autoComplete="password" textContentType="password" placeholder="Your password" onSubmitEditing={submit} returnKeyType="go" />
              <Pressable onPress={() => setShow(!show)} hitSlop={10} style={{ position: 'absolute', right: 14, top: 40 }}>
                <Icon name={show ? 'eye-off' : 'eye'} size={18} color={p.faint} />
              </Pressable>
            </View>
            {needCode ? (
              <Field label="Security code" icon="shield" value={code} onChangeText={setCode} keyboardType="number-pad" maxLength={6} placeholder="6-digit code" autoFocus textContentType="oneTimeCode" hint="From your authenticator app." onSubmitEditing={submit} />
            ) : null}
            {error ? (
              <Row gap={8} style={{ backgroundColor: p.danger + '14', padding: 10, borderRadius: 10 }}>
                <Icon name="alert-circle" size={16} color={p.danger} />
                <T size={13} color={p.danger} style={{ flex: 1 }}>{error}</T>
              </Row>
            ) : null}
            <Button title="Sign in" icon="log-in" onPress={submit} loading={busy} variant="accent" />
            <Pressable onPress={() => WebBrowser.openBrowserAsync(api.webUrl + 'forgot-password')} hitSlop={8}>
              <T size={13} muted center>Forgot your password?</T>
            </Pressable>
          </Card>

          <Card style={{ gap: 6 }}>
            <Row gap={10}>
              <Icon name="info" size={16} color={p.muted} />
              <T size={13} muted style={{ flex: 1 }}>Staff sign in with their workshop account. Customers use the details from their Wheelsturn portal invitation.</T>
            </Row>
          </Card>

          <Pressable onPress={() => setAdvanced(!advanced)} hitSlop={8}>
            <Row gap={6} style={{ justifyContent: 'center' }}>
              <Icon name="server" size={14} color={p.faint} />
              <T size={12} color={p.faint}>{advanced ? 'Hide server settings' : 'Server settings'}</T>
            </Row>
          </Pressable>
          {advanced ? (
            <Card style={{ gap: 8 }}>
              <Field label="Server address" value={server} onChangeText={setServer} autoCapitalize="none" keyboardType="url" placeholder={api.defaultUrl.replace('/api/v1', '')} hint="Leave empty to use the live Wheelsturn server." />
            </Card>
          ) : null}
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
