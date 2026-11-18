import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { View } from 'react-native';
import { Button, Card, Empty, Loading, Row, Screen, Section, Segmented, StatusBadge, T, useToast } from '@/components/ui';
import { fdate, money } from '@/lib/format';
import { useApi } from '@/lib/hooks';
import { sharePdf } from '@/lib/share';
import { usePalette } from '@/lib/theme';
import type { InvoiceRow, MyQuoteRow } from '@/lib/types';

export default function Bills() {
  const p = usePalette();
  const toast = useToast();
  const [tab, setTab] = useState<'invoices' | 'quotes'>('invoices');
  const invoices = useApi<{ items: InvoiceRow[] }>('my/invoices');
  const quotes = useApi<{ items: MyQuoteRow[] }>('my/quotes');
  const [busy, setBusy] = useState<number | null>(null);

  const pdf = async (id: number, url: string, name: string) => {
    setBusy(id);
    try { await sharePdf(url, name); } catch (e) { toast((e as Error).message, 'error'); } finally { setBusy(null); }
  };
  const pay = async (url: string) => {
    await WebBrowser.openBrowserAsync(url);
    invoices.refresh();
  };

  const loading = tab === 'invoices' ? invoices.isLoading : quotes.isLoading;
  return (
    <Screen refreshing={invoices.refreshing || quotes.refreshing} onRefresh={() => { invoices.refresh(); quotes.refresh(); }}>
      <Segmented value={tab} onChange={setTab} options={[{ value: 'invoices', label: 'Invoices' }, { value: 'quotes', label: 'Quotes' }]} />
      {loading ? <Loading /> : null}
      {tab === 'invoices' ? (
        <>
          {invoices.data?.items.length === 0 ? <Card><Empty icon="file-text" title="No invoices yet" /></Card> : null}
          {invoices.data?.items.map((i) => (
            <Card key={i.id} style={{ gap: 10 }}>
              <Row style={{ justifyContent: 'space-between' }}>
                <View>
                  <T weight="800">{i.number}</T>
                  <T size={13} muted>{fdate(i.date)}{i.registration ? ` · ${i.registration}` : ''}</T>
                </View>
                <View style={{ alignItems: 'flex-end', gap: 4 }}>
                  <T weight="800" size={16}>{money(i.balance > 0 ? i.balance : i.total)}</T>
                  <StatusBadge status={i.status} />
                </View>
              </Row>
              <Row gap={8}>
                <Button title="PDF" icon="download" small variant="secondary" flex loading={busy === i.id} onPress={() => pdf(i.id, i.pdf_url!, i.number)} />
                {i.balance > 0 ? <Button title={`Pay ${money(i.balance)}`} icon="credit-card" small variant="accent" flex onPress={() => pay(i.pay_url!)} /> : null}
              </Row>
            </Card>
          ))}
          <T size={12} muted center style={{ color: p.faint }}>Secure payments by PayFast and Ozow. Card details never touch our servers.</T>
        </>
      ) : (
        <Section title="Your quotes">
          {quotes.data?.items.length === 0 ? <Card><Empty icon="file" title="No quotes" /></Card> : null}
          {quotes.data?.items.map((q) => (
            <Card key={q.id} onPress={() => router.push(`/my-quote/${q.id}`)} style={{ gap: 6 }}>
              <Row style={{ justifyContent: 'space-between' }}>
                <T weight="800">{q.number}</T>
                <T weight="800">{money(q.total)}</T>
              </Row>
              <Row style={{ justifyContent: 'space-between' }}>
                <T size={13} muted>{fdate(q.date)}{q.registration ? ` · ${q.registration}` : ''}</T>
                <StatusBadge status={q.status} />
              </Row>
            </Card>
          ))}
        </Section>
      )}
    </Screen>
  );
}
