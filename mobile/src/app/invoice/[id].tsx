import { useQueryClient } from '@tanstack/react-query';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { Stack, router, useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { Linking, Pressable, Share, View } from 'react-native';
import { Button, Card, Chip, Divider, ErrorView, Field, Icon, IconButton, KeyValue, ListItem, Loading, Row, Screen, Section, Sheet, StatusBadge, T, useToast } from '@/components/ui';
import { api } from '@/lib/api';
import { fdate, money } from '@/lib/format';
import { invalidate, useAction, useApi } from '@/lib/hooks';
import { sharePdf } from '@/lib/share';
import { usePalette } from '@/lib/theme';
import type { InvoiceDetail } from '@/lib/types';

export default function InvoiceScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const p = usePalette();
  const toast = useToast();
  const inv = useApi<InvoiceDetail>(`invoices/${id}`);
  const [payOpen, setPayOpen] = useState(false);
  const [sharing, setSharing] = useState(false);
  const send = useAction<{ action: string }>((b) => `invoices/${id}/${b.action}`, { invalidate: [`invoices/${id}`] });

  if (inv.isLoading) return <Loading />;
  if (inv.error || !inv.data) return <ErrorView error={inv.error} onRetry={inv.refresh} />;
  const d = inv.data;
  const open = d.totals.balance > 0.009 && !['void', 'paid'].includes(d.status);

  const pdf = async () => {
    setSharing(true);
    try {
      await sharePdf(d.pdf_url, `${d.number}`);
    } catch (e) {
      toast((e as Error).message, 'error');
    } finally {
      setSharing(false);
    }
  };

  return (
    <>
      <Stack.Screen options={{ title: d.number, headerRight: () => <IconButton icon="share" onPress={pdf} /> }} />
      <Screen refreshing={inv.refreshing} onRefresh={inv.refresh} footer={open ? <Button title={`Record payment · ${money(d.totals.balance)}`} icon="credit-card" variant="success" flex onPress={() => setPayOpen(true)} /> : undefined}>
        <Card style={{ gap: 6 }}>
          <Row style={{ justifyContent: 'space-between' }}>
            <T muted size={13}>Tax invoice · {fdate(d.date)}</T>
            <StatusBadge status={d.status} />
          </Row>
          <T size={30} weight="800" style={{ letterSpacing: -0.6 }}>{money(open ? d.totals.balance : d.totals.total)}</T>
          <T muted size={13}>{open ? `Outstanding of ${money(d.totals.total)} · due ${fdate(d.due)}` : `Total ${money(d.totals.total)}`}</T>
        </Card>

        <Card padded={false}>
          <ListItem avatar={d.customer.name} title={d.customer.name} subtitle={[d.vehicle ? `${d.vehicle.registration} · ${d.vehicle.label}` : null, d.customer.phone].filter(Boolean).join(' · ')} onPress={() => router.push(`/customer/${d.customer.id}`)} />
        </Card>

        {open ? (
          <Section title="Get paid">
            <Card padded={false}>
              <ListItem icon="mail" iconColor="#2563eb" title="Email invoice with pay link" subtitle={d.customer.email ?? 'No email on file'} onPress={d.customer.email ? () => send.mutate({ action: 'send' }) : undefined} right={send.isPending && send.variables?.action === 'send' ? <Icon name="loader" /> : undefined} />
              <Divider inset={64} />
              {d.whatsapp ? (
                <>
                  <ListItem icon="message-circle" iconColor="#16a34a" title="Send on WhatsApp" subtitle="Message with a secure PayFast / Ozow link" onPress={() => Linking.openURL(d.whatsapp!)} />
                  <Divider inset={64} />
                </>
              ) : null}
              <ListItem icon="bell" iconColor="#d97706" title="Send payment reminder" onPress={() => send.mutate({ action: 'remind' })} />
              <Divider inset={64} />
              <ListItem icon="link" title="Share pay link" subtitle="Card, instant EFT and more" onPress={() => Share.share({ message: `Invoice ${d.number} for ${money(d.totals.balance)}. Pay securely: ${d.pay_url}` })} />
            </Card>
          </Section>
        ) : null}

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
            <View style={{ paddingTop: 6 }}>
              <KeyValue label="Subtotal (excl. VAT)" value={money(d.totals.subtotal)} />
              <KeyValue label="VAT 15%" value={money(d.totals.vat)} />
              <KeyValue label="Total" value={money(d.totals.total)} strong />
              {d.totals.paid ? <KeyValue label="Paid" value={`-${money(d.totals.paid)}`} color={p.success} /> : null}
              {d.totals.credited ? <KeyValue label="Credited" value={`-${money(d.totals.credited)}`} /> : null}
              <KeyValue label="Balance" value={money(d.totals.balance)} strong color={d.totals.balance > 0 ? p.warning : p.success} />
            </View>
          </Card>
        </Section>

        {d.payments.length ? (
          <Section title="Payments">
            <Card padded={false}>
              {d.payments.map((pm, idx) => (
                <View key={pm.number}>
                  <ListItem icon="check-circle" iconColor="#059669" title={money(pm.amount)} subtitle={`${pm.method} · ${fdate(pm.date)} · ${pm.number}`} />
                  {idx < d.payments.length - 1 ? <Divider inset={64} /> : null}
                </View>
              ))}
            </Card>
          </Section>
        ) : null}

        <Row gap={10}>
          <Button title="PDF" icon="download" variant="secondary" flex loading={sharing} onPress={pdf} />
          <Button title="View online" icon="external-link" variant="secondary" flex onPress={() => WebBrowser.openBrowserAsync(d.pay_url)} />
        </Row>
      </Screen>
      <PaymentSheet visible={payOpen} onClose={() => setPayOpen(false)} invoice={d} />
    </>
  );
}

function PaymentSheet({ visible, onClose, invoice }: { visible: boolean; onClose: () => void; invoice: InvoiceDetail }) {
  const p = usePalette();
  const toast = useToast();
  const qc = useQueryClient();
  const [amount, setAmount] = useState(String(invoice.totals.balance.toFixed(2)));
  const [method, setMethod] = useState('eft');
  const [reference, setReference] = useState('');
  const [receipt, setReceipt] = useState(true);
  const [slip, setSlip] = useState<ImagePicker.ImagePickerAsset | null>(null);
  const [busy, setBusy] = useState(false);

  const pickSlip = async (camera: boolean) => {
    if (camera) {
      const perm = await ImagePicker.requestCameraPermissionsAsync();
      if (!perm.granted) return toast('Allow camera access to photograph the slip.', 'error');
    }
    const res = camera ? await ImagePicker.launchCameraAsync({ mediaTypes: ['images'], quality: 0.6 }) : await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.6 });
    if (!res.canceled && res.assets?.[0]) setSlip(res.assets[0]);
  };

  const save = async () => {
    const value = Number(amount.replace(',', '.'));
    if (!(value > 0)) return toast('Enter the amount received.', 'error');
    setBusy(true);
    try {
      const fields = { amount: value.toFixed(2), method, reference, send_receipt: receipt ? '1' : '' };
      const res = slip
        ? await api.upload<{ message: string }>(`invoices/${invoice.id}/payment`, 'slip', { uri: slip.uri, name: slip.fileName ?? 'slip.jpg', type: slip.mimeType ?? 'image/jpeg' }, fields)
        : await api.post<{ message: string }>(`invoices/${invoice.id}/payment`, fields);
      toast(res.message || 'Payment recorded');
      invalidate(qc, [`invoices/${invoice.id}`, 'invoices', 'dashboard', 'customers', 'jobs']);
      setSlip(null);
      onClose();
    } catch (e) {
      toast((e as Error).message, 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Sheet visible={visible} onClose={onClose} title="Record payment" footer={<Button title="Save payment" icon="check" variant="success" flex loading={busy} onPress={save} />}>
      <Field label="Amount received (R)" value={amount} onChangeText={setAmount} keyboardType="decimal-pad" />
      <View style={{ gap: 6 }}>
        <T size={13} weight="700" muted>Method</T>
        <Row wrap gap={6}>{Object.entries(invoice.methods).map(([k, label]) => <Chip key={k} label={label} selected={method === k} onPress={() => setMethod(k)} />)}</Row>
      </View>
      <Field label="Reference" value={reference} onChangeText={setReference} placeholder="Bank reference or slip number" />
      <View style={{ gap: 6 }}>
        <T size={13} weight="700" muted>Proof of payment</T>
        {slip ? (
          <Row gap={10}>
            <Image source={{ uri: slip.uri }} style={{ width: 64, height: 64, borderRadius: 10 }} />
            <T style={{ flex: 1 }} size={13}>Slip attached</T>
            <IconButton icon="trash-2" color={p.danger} onPress={() => setSlip(null)} />
          </Row>
        ) : (
          <Row gap={8}>
            <Button title="Photograph slip" icon="camera" small variant="secondary" flex onPress={() => pickSlip(true)} />
            <Button title="From photos" icon="image" small variant="secondary" flex onPress={() => pickSlip(false)} />
          </Row>
        )}
      </View>
      <Pressable onPress={() => setReceipt(!receipt)} style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
        <View style={{ width: 22, height: 22, borderRadius: 6, borderWidth: 2, borderColor: receipt ? p.success : p.border, backgroundColor: receipt ? p.success : 'transparent', alignItems: 'center', justifyContent: 'center' }}>
          {receipt ? <Icon name="check" size={14} color="#fff" /> : null}
        </View>
        <T size={14}>Email a receipt to the customer</T>
      </Pressable>
    </Sheet>
  );
}
