import { Stack, router, useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { Alert, Linking, Share, View } from 'react-native';
import { Button, Card, Divider, ErrorView, KeyValue, ListItem, Loading, Row, Screen, Section, StatusBadge, T, useToast } from '@/components/ui';
import { fdate, money } from '@/lib/format';
import { useAction, useApi } from '@/lib/hooks';
import { sharePdf } from '@/lib/share';
import type { QuoteDetail } from '@/lib/types';

export default function QuoteScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const toast = useToast();
  const q = useApi<QuoteDetail>(`quotes/${id}`);
  const [sharing, setSharing] = useState(false);
  const send = useAction(`quotes/${id}/send`, { invalidate: [`quotes/${id}`, 'quotes'] });
  const convert = useAction<Record<string, never>, { job_card_id: number }>(`quotes/${id}/convert`, { invalidate: [`quotes/${id}`, 'quotes', 'jobs', 'board'], onSuccess: (r) => router.replace(`/job/${r.job_card_id}`) });

  if (q.isLoading) return <Loading />;
  if (q.error || !q.data) return <ErrorView error={q.error} onRetry={q.refresh} />;
  const d = q.data;
  const wa = d.customer.phone?.replace(/\D/g, '').replace(/^0/, '27');

  return (
    <>
      <Stack.Screen options={{ title: d.number }} />
      <Screen refreshing={q.refreshing} onRefresh={q.refresh}>
        <Card style={{ gap: 6 }}>
          <Row style={{ justifyContent: 'space-between' }}>
            <T muted size={13}>Quote · {fdate(d.date)} · valid to {fdate(d.expires)}</T>
            <StatusBadge status={d.status} />
          </Row>
          <T size={30} weight="800">{money(d.totals.total)}</T>
          <T muted size={13}>incl. VAT</T>
        </Card>
        <Card padded={false}>
          <ListItem avatar={d.customer.name} title={d.customer.name} subtitle={[d.vehicle ? `${d.vehicle.registration} · ${d.vehicle.label}` : null, d.customer.phone].filter(Boolean).join(' · ')} onPress={() => router.push(`/customer/${d.customer.id}`)} />
        </Card>
        <Section title="Items">
          <Card>
            {d.items.map((i, idx) => (
              <View key={idx} style={{ paddingVertical: 6 }}>
                <Row style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
                  <T style={{ flex: 1 }} size={14}>{i.description}</T>
                  <T weight="700" size={14}>{money(i.total)}</T>
                </Row>
                <T size={12} muted>{i.quantity} x {money(i.unit_price ?? 0)}</T>
              </View>
            ))}
            <Divider />
            <KeyValue label="Subtotal (excl. VAT)" value={money(d.totals.subtotal)} />
            <KeyValue label="VAT 15%" value={money(d.totals.vat)} />
            <KeyValue label="Total" value={money(d.totals.total)} strong />
          </Card>
        </Section>
        {d.notes ? <Card><T size={13} muted>{d.notes}</T></Card> : null}

        {d.job_card_id ? (
          <Button title="Open job card" icon="clipboard" onPress={() => router.push(`/job/${d.job_card_id}`)} />
        ) : (
          <View style={{ gap: 10 }}>
            <Row gap={10}>
              <Button title="Email" icon="mail" flex variant="secondary" loading={send.isPending} onPress={() => send.mutate({})} />
              {wa ? <Button title="WhatsApp" icon="message-circle" flex variant="secondary" onPress={() => Linking.openURL(`https://wa.me/${wa}?text=${encodeURIComponent(`Hi ${d.customer.name}, here is your quote ${d.number} for ${money(d.totals.total)}. View and accept online: ${d.public_url}`)}`)} /> : null}
            </Row>
            {['draft', 'sent', 'accepted'].includes(d.status) ? (
              <Button title="Convert to job card" icon="log-in" variant="accent" loading={convert.isPending} onPress={() => Alert.alert('Convert to job card?', 'The quote lines are copied to a new job card.', [{ text: 'Cancel', style: 'cancel' }, { text: 'Convert', onPress: () => convert.mutate({}) }])} />
            ) : null}
          </View>
        )}
        <Row gap={10}>
          <Button title="PDF" icon="download" variant="secondary" flex loading={sharing} onPress={async () => { setSharing(true); try { await sharePdf(d.pdf_url, d.number); } catch (e) { toast((e as Error).message, 'error'); } finally { setSharing(false); } }} />
          <Button title="Share link" icon="share-2" variant="secondary" flex onPress={() => Share.share({ message: `Quote ${d.number}: ${d.public_url}` })} />
          <Button title="Online" icon="external-link" variant="secondary" flex onPress={() => WebBrowser.openBrowserAsync(d.public_url)} />
        </Row>
      </Screen>
    </>
  );
}
