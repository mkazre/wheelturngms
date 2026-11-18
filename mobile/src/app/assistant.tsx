import { useLocalSearchParams } from 'expo-router';
import { useRef, useState } from 'react';
import { ActivityIndicator, FlatList, KeyboardAvoidingView, Platform, Pressable, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Card, Chip, Field, Icon, Row, Segmented, T, Button, useToast } from '@/components/ui';
import { api } from '@/lib/api';
import { brand, usePalette } from '@/lib/theme';

type Msg = { role: 'user' | 'assistant'; content: string };

const STARTERS = [
  'Rough idle when cold on a 2015 Polo 1.4 TSI. Where should I start?',
  'Torque spec for a Hilux 2.8 GD-6 wheel nuts?',
  'Clutch slipping in 3rd and 4th only. Likely causes?',
  'How do I reset the service light on a BMW F30?',
];

export default function Assistant() {
  const { job_id } = useLocalSearchParams<{ job_id?: string }>();
  const p = usePalette();
  const [mode, setMode] = useState<'chat' | 'codes'>('chat');
  return (
    <View style={{ flex: 1, backgroundColor: p.bg }}>
      <View style={{ padding: 16, paddingBottom: 6 }}>
        <Segmented value={mode} onChange={setMode} options={[{ value: 'chat', label: 'Ask the assistant' }, { value: 'codes', label: 'Fault codes' }]} />
      </View>
      {mode === 'chat' ? <Chat jobId={job_id ? Number(job_id) : undefined} /> : <FaultCodes />}
    </View>
  );
}

function Chat({ jobId }: { jobId?: number }) {
  const p = usePalette();
  const insets = useSafeAreaInsets();
  const toast = useToast();
  const [messages, setMessages] = useState<Msg[]>([]);
  const [input, setInput] = useState('');
  const [busy, setBusy] = useState(false);
  const list = useRef<FlatList<Msg>>(null);

  const send = async (text = input) => {
    const content = text.trim();
    if (!content || busy) return;
    const next: Msg[] = [...messages, { role: 'user', content }];
    setMessages(next);
    setInput('');
    setBusy(true);
    try {
      const r = await api.post<{ answer: string }>('ai/ask', { messages: next, ...(jobId ? { job_id: jobId } : {}) });
      setMessages([...next, { role: 'assistant', content: r.answer }]);
    } catch (e) {
      toast((e as Error).message, 'error');
      setMessages(messages);
      setInput(content);
    } finally {
      setBusy(false);
      setTimeout(() => list.current?.scrollToEnd({ animated: true }), 100);
    }
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined} keyboardVerticalOffset={100}>
      <FlatList
        ref={list}
        data={messages}
        keyExtractor={(_, i) => String(i)}
        contentContainerStyle={{ padding: 16, gap: 10, flexGrow: 1 }}
        ListEmptyComponent={
          <View style={{ gap: 12, paddingTop: 10 }}>
            <Card style={{ gap: 6 }}>
              <Row gap={8}><Icon name="cpu" size={18} color="#6d28d9" /><T weight="800">Workshop assistant</T></Row>
              <T size={13} muted>Diagnostics ideas, procedures, specs and customer explanations. Always verify specifications against the manufacturer data before you work.</T>
              {jobId ? <T size={13} weight="700" color="#6d28d9">Using this job&apos;s vehicle and inspection as context.</T> : null}
            </Card>
            {STARTERS.map((s) => (
              <Pressable key={s} onPress={() => send(s)}>
                <Card style={{ paddingVertical: 12 }}><T size={14}>{s}</T></Card>
              </Pressable>
            ))}
          </View>
        }
        renderItem={({ item }) => (
          <View style={{ alignSelf: item.role === 'user' ? 'flex-end' : 'flex-start', maxWidth: '88%', backgroundColor: item.role === 'user' ? brand.navy800 : p.card, borderRadius: 18, borderBottomRightRadius: item.role === 'user' ? 4 : 18, borderBottomLeftRadius: item.role === 'user' ? 18 : 4, padding: 12, borderWidth: item.role === 'user' ? 0 : 1, borderColor: p.border }}>
            <T size={14.5} color={item.role === 'user' ? '#fff' : p.text} style={{ lineHeight: 21 }}>{item.content}</T>
          </View>
        )}
        ListFooterComponent={busy ? <Row gap={8} style={{ padding: 8 }}><ActivityIndicator /><T muted size={13}>Thinking...</T></Row> : undefined}
      />
      <Row gap={8} style={{ padding: 10, paddingBottom: 10 + insets.bottom, backgroundColor: p.card, borderTopWidth: 1, borderColor: p.border }}>
        <TextInput value={input} onChangeText={setInput} placeholder="Ask about a fault, spec or procedure" placeholderTextColor={p.faint} multiline style={{ flex: 1, maxHeight: 120, color: p.text, fontSize: 15, backgroundColor: p.input, borderRadius: 20, paddingHorizontal: 14, paddingVertical: 10, borderWidth: 1, borderColor: p.border }} />
        <Pressable onPress={() => send()} disabled={busy || !input.trim()} style={{ width: 44, height: 44, borderRadius: 22, backgroundColor: input.trim() ? brand.crimson : p.border, alignItems: 'center', justifyContent: 'center' }}>
          <Icon name="send" size={18} color="#fff" />
        </Pressable>
      </Row>
    </KeyboardAvoidingView>
  );
}

function FaultCodes() {
  const toast = useToast();
  const [codes, setCodes] = useState('');
  const [vehicle, setVehicle] = useState('');
  const [answer, setAnswer] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const run = async () => {
    setBusy(true);
    setAnswer(null);
    try {
      const r = await api.post<{ answer: string }>('ai/fault-codes', { codes, vehicle });
      setAnswer(r.answer);
    } catch (e) {
      toast((e as Error).message, 'error');
    } finally {
      setBusy(false);
    }
  };
  return (
    <FlatList
      data={[]}
      renderItem={() => null}
      contentContainerStyle={{ padding: 16, gap: 12 }}
      keyboardShouldPersistTaps="handled"
      ListHeaderComponent={
        <View style={{ gap: 12 }}>
          <Field label="Fault codes" value={codes} onChangeText={(v) => setCodes(v.toUpperCase())} placeholder="P0301, P0171" autoCapitalize="characters" />
          <Row wrap gap={6}>{['P0300', 'P0171', 'P0420', 'P0401', 'U0100'].map((c) => <Chip key={c} label={c} onPress={() => setCodes(codes ? `${codes}, ${c}` : c)} />)}</Row>
          <Field label="Vehicle" value={vehicle} onChangeText={setVehicle} placeholder="e.g. 2017 Ford Ranger 2.2 TDCi" />
          <Button title="Explain codes" icon="cpu" loading={busy} onPress={run} />
          {answer ? <Card><T size={14.5} style={{ lineHeight: 21 }}>{answer}</T></Card> : null}
        </View>
      }
    />
  );
}
