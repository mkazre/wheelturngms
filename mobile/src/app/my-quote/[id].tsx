import { Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Alert, View } from 'react-native';
import { Button, Card, Divider, ErrorView, KeyValue, Loading, Row, Screen, StatusBadge, T, useToast } from '@/components/ui';
import { fdate, money } from '@/lib/format';
import { useAction, useApi } from '@/lib/hooks';
import { sharePdf } from '@/lib/share';
import type { MyQuote } from '@/lib/types';

export default function MyQuoteScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const toast = useToast();
  const q = useApi<MyQuote>(`my/quotes/${id}`);
  const [busy, setBusy] = useState(false);
  const respond = useAction<{ decision: 'accept' | 'decline' }>(`my/quotes/${id}/respond`, { invalidate: [`my/quotes/${id}`, 'my/quotes', 'my/overview'] });

  if (q.isLoading) return <Loading />;
  if (q.error || !q.data) return <ErrorView error={q.error} onRetry={q.refresh} />;
  const d = q.data;

  return (
    <>
      <Stack.Screen options={{ title: d.number }} />
      <Screen refreshing={q.refreshing} onRefresh={q.refresh} footer={d.can_respond ? (
        <>
          <Button title="Decline" variant="secondary" flex onPress={() => Alert.alert('Decline this quote?', undefined, [{ text: 'Cancel', style: 'cancel' }, { text: 'Decline', style: 'destructive', onPress: () => respond.mutate({ decision: 'decline' }) }])} />
          <Button title="Accept quote" icon="check" variant="success" flex loading={respond.isPending} onPress={() => respond.mutate({ decision: 'accept' })} />
        </>
      ) : undefined}>
        <Card style={{ gap: 6 }}>
          <Row style={{ justifyContent: 'space-between' }}>
            <T muted size={13}>{fdate(d.date)} · valid to {fdate(d.expires)}</T>
            <StatusBadge status={d.status} />
          </Row>
          <T size={30} weight="800">{money(d.totals.total)}</T>
          {d.vehicle ? <T muted size={13}>{d.vehicle}</T> : null}
        </Card>
        <Card>
          {d.items.map((i, idx) => (
            <Row key={idx} style={{ justifyContent: 'space-between', paddingVertical: 6, alignItems: 'flex-start' }}>
              <T style={{ flex: 1 }} size={14}>{i.description}{i.quantity !== 1 ? ` x${i.quantity}` : ''}</T>
              <T weight="700" size={14}>{money(i.total)}</T>
            </Row>
          ))}
          <Divider />
          <KeyValue label="Subtotal (excl. VAT)" value={money(d.totals.subtotal)} />
          <KeyValue label="VAT 15%" value={money(d.totals.vat)} />
          <KeyValue label="Total" value={money(d.totals.total)} strong />
        </Card>
        {d.notes ? <Card><T size={13} muted>{d.notes}</T></Card> : null}
        <View>
          <Button title="Download PDF" icon="download" variant="secondary" loading={busy} onPress={async () => { setBusy(true); try { await sharePdf(d.pdf_url, d.number); } catch (e) { toast((e as Error).message, 'error'); } finally { setBusy(false); } }} />
        </View>
      </Screen>
    </>
  );
}
