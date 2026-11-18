import { useQueryClient } from '@tanstack/react-query';
import * as ImagePicker from 'expo-image-picker';
import { router, Stack, useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useMemo, useState } from 'react';
import { ActivityIndicator, Alert, Linking, Pressable, Share, View } from 'react-native';
import { DamageDiagram, MediaGrid, Scanner, SignaturePad } from '@/components/workshop';
import {
  Avatar, Badge, Button, Card, Chip, Divider, Empty, ErrorView, Field, Icon, IconButton, KeyValue, Label, ListItem, Loading, Row, Screen, Section, Segmented, Sheet, T, useToast,
} from '@/components/ui';
import { api } from '@/lib/api';
import { useMe } from '@/lib/auth';
import { duration, fdatetime, km, minutesLabel, money } from '@/lib/format';
import { invalidate, useAction, useApi, useDebounced, useTicker } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';
import type { DamageMark, Inspection, JobDetail, Product } from '@/lib/types';

type Tab = 'overview' | 'work' | 'parts' | 'media' | 'checkin' | 'time';

export default function JobScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const me = useMe();
  const job = useApi<JobDetail>(`jobs/${id}`);
  const [tab, setTab] = useState<Tab>('overview');

  if (job.isLoading) return <Loading />;
  if (job.error || !job.data) return <ErrorView error={job.error} onRetry={job.refresh} />;
  const j = job.data;
  const tabs: { value: Tab; label: string }[] = [
    { value: 'overview', label: 'Overview' },
    { value: 'work', label: 'Inspection' },
    { value: 'parts', label: me.mode === 'technician' ? 'Parts used' : 'Parts & labour' },
    { value: 'media', label: `Media${j.files.length ? ` ${j.files.length}` : ''}` },
    { value: 'checkin', label: 'Check-in' },
    { value: 'time', label: 'Time' },
  ];

  return (
    <>
      <Stack.Screen options={{ title: j.number }} />
      <Screen refreshing={job.refreshing} onRefresh={job.refresh}>
        <JobHeader j={j} />
        <Segmented scroll value={tab} onChange={setTab} options={tabs} />
        {tab === 'overview' ? <Overview j={j} /> : null}
        {tab === 'work' ? <Work j={j} /> : null}
        {tab === 'parts' ? <Parts j={j} /> : null}
        {tab === 'media' ? <Media j={j} /> : null}
        {tab === 'checkin' ? <CheckIn j={j} /> : null}
        {tab === 'time' ? <Time j={j} /> : null}
      </Screen>
    </>
  );
}

function useJobAction<B = Record<string, unknown>>(j: JobDetail, suffix: string, success?: string) {
  return useAction<B>(`jobs/${j.id}/${suffix}`, { success, invalidate: [`jobs/${j.id}`, 'jobs', 'board', 'timer', 'dashboard', 'diary'] });
}

// ------------------------------------------------------------------ header

function JobHeader({ j }: { j: JobDetail }) {
  const p = usePalette();
  const [statusOpen, setStatusOpen] = useState(false);
  const [odo, setOdo] = useState('');
  const [pending, setPending] = useState<string | null>(null);
  const status = useJobAction<{ status: string; odometer_out?: number }>(j, 'status');
  const timer = useJobAction(j, 'timer');
  const running = Boolean(j.time.running_since);
  const now = useTicker(running);

  const pick = (s: string) => {
    if (s === j.status) return setStatusOpen(false);
    if (s === 'completed') return setPending(s);
    status.mutate({ status: s }, { onSuccess: () => setStatusOpen(false) });
  };

  return (
    <Card style={{ gap: 12 }}>
      <Row style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <View style={{ flex: 1, gap: 6 }}>
          <View style={{ alignSelf: 'flex-start', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8, backgroundColor: p.dark ? '#262c78' : '#fef9c3', borderWidth: 1.5, borderColor: p.dark ? '#525bb5' : '#eab308' }}>
            <T size={18} weight="800" style={{ letterSpacing: 1 }} color={p.dark ? '#fff' : '#0b0d2e'}>{j.vehicle?.registration ?? 'No vehicle'}</T>
          </View>
          <T weight="800" size={16}>{j.vehicle?.label}</T>
          <T size={13} muted>{[j.job_type, j.odometer_in ? km(j.odometer_in) : null, j.bay ? `Bay ${j.bay}` : null].filter(Boolean).join(' · ')}</T>
        </View>
        <Pressable onPress={() => setStatusOpen(true)} hitSlop={8} style={{ alignItems: 'flex-end', gap: 4 }}>
          <Badge label={j.status_label} color={j.status_color} solid />
          <Row gap={3}><T size={11} muted>Change</T><Icon name="chevron-down" size={12} color={p.muted} /></Row>
        </Pressable>
      </Row>
      <Button
        title={running ? `Stop clock · ${duration(j.time.running_since, now)}` : 'Start clock on this job'}
        icon={running ? 'pause-circle' : 'play-circle'}
        variant={running ? 'success' : 'primary'}
        loading={timer.isPending}
        onPress={() => timer.mutate({})}
      />
      <Sheet visible={statusOpen} onClose={() => { setStatusOpen(false); setPending(null); }} title={pending ? 'Vehicle ready' : 'Update status'}>
        {pending ? (
          <>
            <T muted>Record the odometer reading at completion. The customer is notified that the vehicle is ready.</T>
            <Field label="Odometer out (km)" value={odo} onChangeText={setOdo} keyboardType="number-pad" placeholder={j.odometer_in ? String(j.odometer_in) : ''} autoFocus />
            <Button title="Mark as ready" icon="check-circle" variant="success" loading={status.isPending} onPress={() => status.mutate({ status: pending, odometer_out: Number(odo) || 0 }, { onSuccess: () => { setPending(null); setStatusOpen(false); } })} />
          </>
        ) : (
          j.statuses.map((s) => (
            <Pressable key={s.value} onPress={() => pick(s.value)} style={({ pressed }) => ({ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderRadius: 12, backgroundColor: s.value === j.status ? s.color + '22' : pressed ? p.cardAlt : p.card, borderWidth: 1, borderColor: s.value === j.status ? s.color : p.border })}>
              <View style={{ width: 12, height: 12, borderRadius: 6, backgroundColor: s.color }} />
              <T weight="700" style={{ flex: 1 }}>{s.label}</T>
              {s.value === j.status ? <Icon name="check" size={18} color={s.color} /> : null}
              {status.isPending && status.variables?.status === s.value ? <ActivityIndicator /> : null}
            </Pressable>
          ))
        )}
      </Sheet>
    </Card>
  );
}

// ------------------------------------------------------------------ overview

function Overview({ j }: { j: JobDetail }) {
  const me = useMe();
  const p = usePalette();
  const toast = useToast();
  const manager = me.mode !== 'technician';
  const [notes, setNotes] = useState({ customer_complaint: j.customer_complaint ?? '', diagnosis: j.diagnosis ?? '', work_performed: j.work_performed ?? '', recommendations: j.recommendations ?? '', internal_notes: j.internal_notes ?? '' });
  const dirty = notes.customer_complaint !== (j.customer_complaint ?? '') || notes.diagnosis !== (j.diagnosis ?? '') || notes.work_performed !== (j.work_performed ?? '') || notes.recommendations !== (j.recommendations ?? '') || (manager && notes.internal_notes !== (j.internal_notes ?? ''));
  const save = useJobAction(j, 'notes', 'Notes saved');
  const invoice = useAction<Record<string, never>, { invoice_id: number }>(`jobs/${j.id}/invoice`, { success: 'Invoice created', invalidate: [`jobs/${j.id}`, 'jobs', 'board', 'invoices', 'dashboard'], onSuccess: (r) => router.push(`/invoice/${r.invoice_id}`) });
  const [ai, setAi] = useState<string | null>(null);
  const [aiBusy, setAiBusy] = useState(false);

  const askAi = async () => {
    setAiBusy(true);
    try {
      const r = await api.post<{ text: string }>(`jobs/${j.id}/ai-summary`);
      setAi(r.text);
    } catch (e) {
      toast((e as Error).message, 'error');
    } finally {
      setAiBusy(false);
    }
  };

  const phone = j.customer.phone?.replace(/\D/g, '').replace(/^0/, '27');

  return (
    <>
      {manager ? (
        <Card padded={false}>
          <ListItem avatar={j.customer.name} title={j.customer.name} subtitle={[j.customer.phone, j.customer.email].filter(Boolean).join(' · ')} onPress={j.customer.id ? () => router.push(`/customer/${j.customer.id}`) : undefined} />
          <Divider />
          <Row style={{ padding: 10 }} gap={8}>
            {j.customer.phone ? <Button title="Call" icon="phone" small variant="secondary" flex onPress={() => Linking.openURL(`tel:${j.customer.phone}`)} /> : null}
            {phone ? <Button title="WhatsApp" icon="message-circle" small variant="secondary" flex onPress={() => Linking.openURL(`https://wa.me/${phone}?text=${encodeURIComponent(`Hi ${j.customer.name}, track your vehicle ${j.vehicle?.registration} live here: ${j.tracker_url}`)}`)} /> : null}
            <Button title="Tracker" icon="share-2" small variant="secondary" flex onPress={() => Share.share({ message: `Track your vehicle ${j.vehicle?.registration} at ${me.company.name}: ${j.tracker_url}` })} />
          </Row>
        </Card>
      ) : (
        <Card><Row gap={10}><Avatar name={j.customer.name} size={34} /><T weight="700">{j.customer.name}</T></Row></Card>
      )}

      {j.mechanics.length ? (
        <Card style={{ gap: 6 }}>
          <Label>Technicians</Label>
          <Row wrap gap={6}>{j.mechanics.map((m) => <Chip key={m.id} label={m.name} icon="tool" />)}</Row>
        </Card>
      ) : null}

      <Section title="Job notes">
        <Card style={{ gap: 12 }}>
          <Field label="Customer complaint" value={notes.customer_complaint} onChangeText={(v) => setNotes({ ...notes, customer_complaint: v })} multiline />
          <Field label="Diagnosis" value={notes.diagnosis} onChangeText={(v) => setNotes({ ...notes, diagnosis: v })} multiline placeholder="What did you find?" />
          <Field label="Work performed" value={notes.work_performed} onChangeText={(v) => setNotes({ ...notes, work_performed: v })} multiline placeholder="Shown on the invoice" />
          <Field label="Recommendations" value={notes.recommendations} onChangeText={(v) => setNotes({ ...notes, recommendations: v })} multiline placeholder="Advice for the next visit" />
          {manager ? <Field label="Internal notes (staff only)" value={notes.internal_notes} onChangeText={(v) => setNotes({ ...notes, internal_notes: v })} multiline /> : null}
          <Button title="Save notes" icon="save" disabled={!dirty} loading={save.isPending} onPress={() => save.mutate(manager ? notes : { customer_complaint: notes.customer_complaint, diagnosis: notes.diagnosis, work_performed: notes.work_performed, recommendations: notes.recommendations })} />
        </Card>
      </Section>

      {j.can.ai ? (
        <Card style={{ gap: 10 }}>
          <Row gap={8}><Icon name="cpu" size={18} color="#6d28d9" /><T weight="800">AI customer summary</T></Row>
          <T size={13} muted>Turn the technical findings into a plain-language message for the customer.</T>
          {ai ? (
            <>
              <View style={{ backgroundColor: p.cardAlt, borderRadius: 12, padding: 12 }}><T size={14}>{ai}</T></View>
              <Row gap={8}>
                <Button title="Use as work performed" small variant="secondary" flex onPress={() => setNotes({ ...notes, work_performed: ai })} />
                <Button title="Share" icon="share" small variant="secondary" onPress={() => Share.share({ message: ai })} />
              </Row>
            </>
          ) : null}
          <Row gap={8}>
            <Button title={ai ? 'Write again' : 'Write summary'} icon="zap" variant="secondary" flex loading={aiBusy} onPress={askAi} />
            <Button title="Ask about this job" icon="message-square" variant="secondary" flex onPress={() => router.push({ pathname: '/assistant', params: { job_id: String(j.id) } })} />
          </Row>
        </Card>
      ) : null}

      <Section title="Documents">
        <Card padded={false}>
          <ListItem icon="printer" title="Job card PDF" subtitle="With damage map and signature" onPress={() => WebBrowser.openBrowserAsync(j.pdf_url)} />
          <Divider inset={64} />
          <ListItem icon="map-pin" title="Live customer tracker" subtitle="Progress, photos and approvals" onPress={() => WebBrowser.openBrowserAsync(j.tracker_url)} />
          {j.invoice ? (
            <>
              <Divider inset={64} />
              <ListItem icon="file-text" iconColor="#059669" title={`Invoice ${j.invoice.number}`} subtitle={j.invoice.balance > 0 ? `${money(j.invoice.balance)} outstanding` : 'Paid in full'} onPress={() => router.push(`/invoice/${j.invoice!.id}`)} />
            </>
          ) : null}
        </Card>
      </Section>

      {j.can.invoice ? (
        <Button
          title={`Create invoice${j.totals ? ` · ${money(j.totals.total)}` : ''}`}
          icon="file-plus"
          variant="accent"
          loading={invoice.isPending}
          onPress={() => Alert.alert('Create invoice?', 'The job lines are copied to a tax invoice, stock is issued and the ledger is posted.', [{ text: 'Cancel', style: 'cancel' }, { text: 'Create invoice', onPress: () => invoice.mutate({}) }])}
        />
      ) : null}
      <T size={11} muted center>Checked in {fdatetime(j.checked_in_at)}{j.promised_at ? ` · promised ${fdatetime(j.promised_at)}` : ''}</T>
    </>
  );
}

// ------------------------------------------------------------------ inspection & checklist

const INSPECT: { value: NonNullable<Inspection['status']>; label: string; color: string; icon: React.ComponentProps<typeof Icon>['name'] }[] = [
  { value: 'ok', label: 'OK', color: '#10b981', icon: 'check' },
  { value: 'advisory', label: 'Soon', color: '#f59e0b', icon: 'alert-triangle' },
  { value: 'urgent', label: 'Urgent', color: '#ef4444', icon: 'alert-octagon' },
  { value: 'na', label: 'N/A', color: '#94a3b8', icon: 'minus' },
];

function Work({ j }: { j: JobDetail }) {
  const me = useMe();
  const p = usePalette();
  const checklist = useJobAction<{ item_id?: number; done?: boolean; item?: string }>(j, 'checklist');
  const inspect = useJobAction<{ item_id: number; status?: string | null; note?: string; estimate?: number }>(j, 'inspection');
  const [newItem, setNewItem] = useState('');
  const [edit, setEdit] = useState<Inspection | null>(null);
  const [note, setNote] = useState('');
  const [estimate, setEstimate] = useState('');

  const sections = useMemo(() => {
    const m = new Map<string, Inspection[]>();
    j.inspections.forEach((i) => m.set(i.section, [...(m.get(i.section) ?? []), i]));
    return [...m.entries()];
  }, [j.inspections]);
  const done = j.inspections.filter((i) => i.status).length;
  const flagged = j.inspections.filter((i) => i.status === 'advisory' || i.status === 'urgent');

  return (
    <>
      <Section title={`Checklist · ${j.checklist.filter((c) => c.done).length}/${j.checklist.length}`}>
        <Card padded={false}>
          {j.checklist.map((c, idx) => (
            <View key={c.id}>
              <Pressable onPress={() => checklist.mutate({ item_id: c.id, done: !c.done })} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14 }}>
                <View style={{ width: 24, height: 24, borderRadius: 7, borderWidth: 2, borderColor: c.done ? '#10b981' : p.border, backgroundColor: c.done ? '#10b981' : 'transparent', alignItems: 'center', justifyContent: 'center' }}>
                  {c.done ? <Icon name="check" size={15} color="#fff" /> : null}
                </View>
                <T style={{ flex: 1, textDecorationLine: c.done ? 'line-through' : 'none' }} muted={c.done}>{c.item}</T>
              </Pressable>
              {idx < j.checklist.length - 1 ? <Divider inset={50} /> : null}
            </View>
          ))}
          <Row style={{ padding: 10 }} gap={8}>
            <View style={{ flex: 1 }}><Field value={newItem} onChangeText={setNewItem} placeholder="Add a checklist item" onSubmitEditing={() => { if (newItem.trim()) { checklist.mutate({ item: newItem.trim() }); setNewItem(''); } }} /></View>
            <IconButton icon="plus" bg={p.cardAlt} onPress={() => { if (newItem.trim()) { checklist.mutate({ item: newItem.trim() }); setNewItem(''); } }} />
          </Row>
        </Card>
      </Section>

      <Card style={{ gap: 8 }}>
        <Row style={{ justifyContent: 'space-between' }}>
          <T weight="800">Vehicle health check</T>
          <T size={13} muted>{done}/{j.inspections.length} checked</T>
        </Row>
        <View style={{ height: 8, borderRadius: 4, backgroundColor: p.border, flexDirection: 'row', overflow: 'hidden' }}>
          {INSPECT.map((s) => {
            const n = j.inspections.filter((i) => i.status === s.value).length;
            return n ? <View key={s.value} style={{ flex: n, backgroundColor: s.color }} /> : null;
          })}
          {j.inspections.length - done ? <View style={{ flex: j.inspections.length - done }} /> : null}
        </View>
        {flagged.length ? <T size={12.5} muted>{flagged.length} item{flagged.length > 1 ? 's' : ''} flagged. Flagged items with an estimate appear on the customer&apos;s tracker for approval.</T> : null}
      </Card>

      {sections.map(([section, items]) => (
        <Section key={section} title={section}>
          <Card padded={false}>
            {items.map((i, idx) => (
              <View key={i.id}>
                <View style={{ padding: 12, gap: 8 }}>
                  <Pressable onPress={() => { setEdit(i); setNote(i.note ?? ''); setEstimate(i.estimate ? String(i.estimate) : ''); }}>
                    <Row style={{ justifyContent: 'space-between' }}>
                      <T weight="600" style={{ flex: 1 }}>{i.item}</T>
                      {i.decision ? <Badge label={i.decision === 'approved' ? 'Approved' : 'Declined'} color={i.decision === 'approved' ? '#10b981' : '#64748b'} /> : null}
                      <Icon name="edit-3" size={14} color={p.faint} style={{ marginLeft: 8 }} />
                    </Row>
                    {i.note || i.estimate ? <T size={12.5} muted>{[i.note, i.estimate && me.mode !== 'technician' ? money(i.estimate) : null].filter(Boolean).join(' · ')}</T> : null}
                  </Pressable>
                  <Row gap={6}>
                    {INSPECT.map((s) => {
                      const on = i.status === s.value;
                      return (
                        <Pressable key={s.value} onPress={() => inspect.mutate({ item_id: i.id, status: on ? null : s.value })} style={{ flex: 1, flexDirection: 'row', gap: 4, alignItems: 'center', justifyContent: 'center', paddingVertical: 8, borderRadius: 10, borderWidth: 1, borderColor: on ? s.color : p.border, backgroundColor: on ? s.color : 'transparent' }}>
                          <Icon name={s.icon} size={13} color={on ? '#fff' : s.color} />
                          <T size={12} weight="700" color={on ? '#fff' : p.muted}>{s.label}</T>
                        </Pressable>
                      );
                    })}
                  </Row>
                </View>
                {idx < items.length - 1 ? <Divider /> : null}
              </View>
            ))}
          </Card>
        </Section>
      ))}
      {j.inspections.length === 0 ? <Card><Empty icon="clipboard" title="No inspection on this job" /></Card> : null}

      <Sheet visible={edit !== null} onClose={() => setEdit(null)} title={edit?.item ?? ''} footer={<Button title="Save" flex loading={inspect.isPending} onPress={() => edit && inspect.mutate({ item_id: edit.id, note, ...(me.mode !== 'technician' ? { estimate: Number(estimate) || 0 } : {}) }, { onSuccess: () => setEdit(null) })} />}>
        <Field label="Finding" value={note} onChangeText={setNote} placeholder="e.g. Front pads at 3 mm" multiline />
        {me.mode !== 'technician' ? <Field label="Estimate incl. VAT (R)" value={estimate} onChangeText={setEstimate} keyboardType="decimal-pad" hint="The customer can approve this on their phone." /> : null}
      </Sheet>
    </>
  );
}

// ------------------------------------------------------------------ parts & labour

function Parts({ j }: { j: JobDetail }) {
  const me = useMe();
  const p = usePalette();
  const manager = j.can.edit_prices;
  const add = useAction<Record<string, unknown>>(`jobs/${j.id}/items`, { success: 'Added to job', invalidate: [`jobs/${j.id}`, 'jobs', 'board'] });
  const remove = useAction<{ itemId: number }>((b) => `jobs/${j.id}/items/${b.itemId}/remove`, { success: 'Line removed', invalidate: [`jobs/${j.id}`, 'jobs', 'board'] });
  const [sheet, setSheet] = useState<null | 'part' | 'labour' | 'package' | 'sundry'>(null);

  return (
    <>
      <Card padded={false}>
        {j.items.length === 0 ? <Empty icon="package" title="No parts or labour yet" message={j.can.add_lines ? 'Add what was used on this job.' : undefined} /> : null}
        {j.items.map((i, idx) => (
          <View key={i.id}>
            <Pressable
              onLongPress={j.can.remove_lines ? () => Alert.alert('Remove line?', i.description, [{ text: 'Cancel', style: 'cancel' }, { text: 'Remove', style: 'destructive', onPress: () => remove.mutate({ itemId: i.id }) }]) : undefined}
              style={{ flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14 }}
            >
              <View style={{ width: 34, height: 34, borderRadius: 10, backgroundColor: p.cardAlt, alignItems: 'center', justifyContent: 'center' }}>
                <Icon name={i.type === 'labour' ? 'clock' : i.type === 'tyre' ? 'disc' : i.type === 'fluid' ? 'droplet' : 'package'} size={16} color={p.muted} />
              </View>
              <View style={{ flex: 1 }}>
                <T weight="600" numberOfLines={2}>{i.description}</T>
                <T size={12.5} muted>{i.quantity} {i.type === 'labour' ? 'h' : 'x'}{i.unit_price !== null ? ` @ ${money(i.unit_price)}` : ''}{i.mechanic ? ` · ${i.mechanic}` : ''}</T>
              </View>
              {i.total !== null ? <T weight="800">{money(i.total)}</T> : null}
            </Pressable>
            {idx < j.items.length - 1 ? <Divider inset={60} /> : null}
          </View>
        ))}
        {j.totals ? (
          <View style={{ padding: 14, borderTopWidth: 1, borderColor: p.border, backgroundColor: p.cardAlt, borderBottomLeftRadius: 16, borderBottomRightRadius: 16 }}>
            <KeyValue label="Subtotal (excl. VAT)" value={money(j.totals.subtotal)} />
            <KeyValue label="VAT 15%" value={money(j.totals.vat)} />
            <KeyValue label="Total" value={money(j.totals.total)} strong />
          </View>
        ) : null}
      </Card>
      {j.can.remove_lines && j.items.length ? <T size={11.5} muted center>Press and hold a line to remove it.</T> : null}

      {j.can.add_lines ? (
        <View style={{ gap: 10 }}>
          <Row gap={10}>
            <Button title="Part from stock" icon="package" flex onPress={() => setSheet('part')} />
            <Button title="Labour" icon="clock" variant="secondary" flex onPress={() => setSheet('labour')} />
          </Row>
          {manager ? (
            <Row gap={10}>
              <Button title="Service package" icon="layers" variant="secondary" flex onPress={() => setSheet('package')} />
              <Button title="Other line" icon="edit" variant="secondary" flex onPress={() => setSheet('sundry')} />
            </Row>
          ) : null}
        </View>
      ) : j.invoice ? <Card><Row gap={8}><Icon name="lock" size={16} color={p.muted} /><T size={13} muted style={{ flex: 1 }}>This job is invoiced ({j.invoice.number}), so its lines are locked.</T></Row></Card> : null}

      {sheet === 'part' ? <PartSheet visible onClose={() => setSheet(null)} onAdd={(b) => add.mutate(b, { onSuccess: () => setSheet(null) })} busy={add.isPending} manager={manager} /> : null}
      {sheet === 'labour' ? <LabourSheet visible onClose={() => setSheet(null)} onAdd={(b) => add.mutate(b, { onSuccess: () => setSheet(null) })} busy={add.isPending} manager={manager} mechanics={j.mechanics} self={me.user.id} /> : null}
      {manager && sheet === 'package' ? <PackageSheet visible onClose={() => setSheet(null)} onAdd={(b) => add.mutate(b, { onSuccess: () => setSheet(null) })} busy={add.isPending} /> : null}
      {manager && sheet === 'sundry' ? <SundrySheet visible onClose={() => setSheet(null)} onAdd={(b) => add.mutate(b, { onSuccess: () => setSheet(null) })} busy={add.isPending} /> : null}
    </>
  );
}

function PartSheet({ visible, onClose, onAdd, busy, manager }: { visible: boolean; onClose: () => void; onAdd: (b: Record<string, unknown>) => void; busy: boolean; manager: boolean }) {
  const p = usePalette();
  const [q, setQ] = useState('');
  const [barcode, setBarcode] = useState<string | null>(null);
  const [scan, setScan] = useState(false);
  const [picked, setPicked] = useState<Product | null>(null);
  const [qty, setQty] = useState('1');
  const [priceInput, setPriceInput] = useState<string | null>(null);
  const dq = useDebounced(q);
  const path = barcode ? `products?barcode=${encodeURIComponent(barcode)}` : dq.length >= 2 ? `products?q=${encodeURIComponent(dq)}` : null;
  const products = useApi<{ items: Product[] }>(visible ? path : null);
  // A barcode that matches exactly one item selects it straight away.
  const sel = picked ?? (barcode && products.data?.items.length === 1 ? products.data.items[0] : null);
  const price = priceInput ?? (sel?.price !== null && sel?.price !== undefined ? String(sel.price) : '');
  const setPrice = setPriceInput;
  const setSel = (pr: Product | null) => { setPicked(pr); setPriceInput(null); if (!pr) setBarcode(null); };

  return (
    <Sheet visible={visible} onClose={onClose} title={sel ? sel.name : 'Add a part'} footer={sel ? (
      <>
        <Button title="Back" variant="secondary" onPress={() => setSel(null)} />
        <Button title="Add to job" icon="plus" flex loading={busy} onPress={() => onAdd({ product_id: sel.id, quantity: Number(qty) || 1, ...(manager && price !== '' && Number(price) !== sel.price ? { unit_price: Number(price) } : {}) })} />
      </>
    ) : undefined}>
      {sel ? (
        <>
          <Card style={{ gap: 4 }}>
            <T muted size={13}>{sel.sku}{sel.bin ? ` · Bin ${sel.bin}` : ''}</T>
            <Row style={{ justifyContent: 'space-between' }}>
              <T weight="700">{sel.stock} in stock</T>
              {manager ? <T weight="800">{money(sel.price)}</T> : null}
            </Row>
            {sel.stock !== null && sel.stock <= 0 ? <T size={12.5} color={p.danger}>Out of stock: the line still adds, but order more.</T> : null}
          </Card>
          <Row gap={10}>
            <IconButton icon="minus" bg={p.card} onPress={() => setQty(String(Math.max(1, (Number(qty) || 1) - 1)))} />
            <View style={{ flex: 1 }}><Field label="Quantity" value={qty} onChangeText={setQty} keyboardType="decimal-pad" /></View>
            <IconButton icon="plus" bg={p.card} onPress={() => setQty(String((Number(qty) || 0) + 1))} />
          </Row>
          {manager ? <Field label="Unit price excl. VAT (R)" value={price} onChangeText={setPrice} keyboardType="decimal-pad" /> : null}
        </>
      ) : (
        <>
          <Row gap={8}>
            <View style={{ flex: 1 }}><Field icon="search" value={q} onChangeText={(v) => { setQ(v); setBarcode(null); }} placeholder="Name, SKU or barcode" autoFocus /></View>
            <IconButton icon="maximize" bg={p.card} onPress={() => setScan(true)} />
          </Row>
          {barcode ? <Chip label={`Barcode ${barcode}`} icon="x" onPress={() => setBarcode(null)} /> : null}
          {products.isFetching ? <ActivityIndicator /> : null}
          {path && products.data?.items.length === 0 ? <T muted center>No matching stock items.</T> : null}
          {products.data?.items.map((pr) => (
            <Card key={pr.id} padded={false}>
              <ListItem icon="package" title={pr.name} subtitle={`${pr.sku} · ${pr.stock} in stock${pr.bin ? ` · Bin ${pr.bin}` : ''}`} right={manager ? <T weight="700">{money(pr.price)}</T> : pr.low ? <Badge label="Low" color="#ef4444" /> : undefined} onPress={() => setSel(pr)} />
            </Card>
          ))}
          <Scanner visible={scan} onClose={() => setScan(false)} onScan={(code) => { setScan(false); setBarcode(code); setQ(''); }} />
        </>
      )}
    </Sheet>
  );
}

function LabourSheet({ visible, onClose, onAdd, busy, manager, mechanics, self }: { visible: boolean; onClose: () => void; onAdd: (b: Record<string, unknown>) => void; busy: boolean; manager: boolean; mechanics: { id: number; name: string }[]; self: number }) {
  const [desc, setDesc] = useState('');
  const [hours, setHours] = useState('1');
  const [rate, setRate] = useState('');
  const [mech, setMech] = useState<number | null>(mechanics[0]?.id ?? null);
  return (
    <Sheet visible={visible} onClose={onClose} title="Add labour" footer={<Button title="Add labour" icon="plus" flex loading={busy} onPress={() => onAdd({ item_type: 'labour', description: desc.trim() || 'Labour', hours: Number(hours) || 1, ...(manager ? { mechanic_id: mech ?? undefined, ...(rate ? { unit_price: Number(rate) } : {}) } : {}) })} />}>
      <Field label="Work done" value={desc} onChangeText={setDesc} placeholder="e.g. Replace front brake pads and discs" autoFocus />
      <Field label="Hours" value={hours} onChangeText={setHours} keyboardType="decimal-pad" />
      <Row wrap gap={6}>{['0.5', '1', '1.5', '2', '3', '4'].map((h) => <Chip key={h} label={`${h} h`} selected={hours === h} onPress={() => setHours(h)} />)}</Row>
      {manager ? (
        <>
          <Field label="Rate per hour excl. VAT (R)" value={rate} onChangeText={setRate} keyboardType="decimal-pad" placeholder="Default workshop rate" />
          {mechanics.length ? (
            <View style={{ gap: 6 }}>
              <T size={13} weight="700" muted>Technician</T>
              <Row wrap gap={6}>{mechanics.map((m) => <Chip key={m.id} label={m.name} selected={mech === m.id} onPress={() => setMech(m.id)} />)}</Row>
            </View>
          ) : null}
        </>
      ) : <T size={12.5} muted>Booked to you (#{self}) at the workshop rate.</T>}
    </Sheet>
  );
}

function PackageSheet({ visible, onClose, onAdd, busy }: { visible: boolean; onClose: () => void; onAdd: (b: Record<string, unknown>) => void; busy: boolean }) {
  const pkgs = useApi<{ items: { id: number; name: string; job_type: string | null; hours: number }[] }>(visible ? 'packages' : null);
  return (
    <Sheet visible={visible} onClose={onClose} title="Service packages">
      {pkgs.isLoading ? <ActivityIndicator /> : null}
      {pkgs.data?.items.length === 0 ? <Empty icon="layers" title="No packages" message="Create service packages on the web app under Settings." /> : null}
      {pkgs.data?.items.map((pk) => (
        <Card key={pk.id} padded={false}>
          <ListItem icon="layers" title={pk.name} subtitle={[pk.job_type, pk.hours ? `${pk.hours} h labour` : null].filter(Boolean).join(' · ')} right={busy ? <ActivityIndicator /> : undefined} onPress={() => onAdd({ canned_job_id: pk.id })} />
        </Card>
      ))}
    </Sheet>
  );
}

function SundrySheet({ visible, onClose, onAdd, busy }: { visible: boolean; onClose: () => void; onAdd: (b: Record<string, unknown>) => void; busy: boolean }) {
  const [type, setType] = useState('sundry');
  const [desc, setDesc] = useState('');
  const [qty, setQty] = useState('1');
  const [price, setPrice] = useState('');
  const [cost, setCost] = useState('');
  const types = [['sundry', 'Sundry'], ['part', 'Part'], ['sublet', 'Sublet'], ['tyre', 'Tyre'], ['fluid', 'Fluid']];
  return (
    <Sheet visible={visible} onClose={onClose} title="Other line" footer={<Button title="Add line" icon="plus" flex loading={busy} onPress={() => onAdd({ item_type: type, description: desc, quantity: Number(qty) || 1, unit_price: Number(price) || 0, unit_cost: Number(cost) || 0 })} />}>
      <Row wrap gap={6}>{types.map(([v, l]) => <Chip key={v} label={l} selected={type === v} onPress={() => setType(v)} />)}</Row>
      <Field label="Description" value={desc} onChangeText={setDesc} autoFocus />
      <Row gap={10}>
        <View style={{ flex: 1 }}><Field label="Qty" value={qty} onChangeText={setQty} keyboardType="decimal-pad" /></View>
        <View style={{ flex: 2 }}><Field label="Price excl. VAT" value={price} onChangeText={setPrice} keyboardType="decimal-pad" /></View>
      </Row>
      <Field label="Cost (for job profit)" value={cost} onChangeText={setCost} keyboardType="decimal-pad" />
    </Sheet>
  );
}

// ------------------------------------------------------------------ media

function Media({ j }: { j: JobDetail }) {
  const toast = useToast();
  const qc = useQueryClient();
  const [busy, setBusy] = useState<string | null>(null);

  const capture = async (source: 'photo' | 'video' | 'library') => {
    try {
      if (source !== 'library') {
        const perm = await ImagePicker.requestCameraPermissionsAsync();
        if (!perm.granted) return toast('Allow camera access in Settings to take photos.', 'error');
      }
      const opts: ImagePicker.ImagePickerOptions = { mediaTypes: source === 'video' ? ['videos'] : source === 'library' ? ['images', 'videos'] : ['images'], quality: 0.6, videoMaxDuration: 60, allowsMultipleSelection: source === 'library', selectionLimit: 10 };
      const res = source === 'library' ? await ImagePicker.launchImageLibraryAsync(opts) : await ImagePicker.launchCameraAsync(opts);
      if (res.canceled || !res.assets?.length) return;
      for (let i = 0; i < res.assets.length; i++) {
        const a = res.assets[i];
        setBusy(res.assets.length > 1 ? `Uploading ${i + 1} of ${res.assets.length}` : a.type === 'video' ? 'Uploading video' : 'Uploading photo');
        await api.upload(`jobs/${j.id}/files`, 'file', { uri: a.uri, name: a.fileName, type: a.mimeType }, { category: a.type === 'video' ? 'video' : 'photo' });
      }
      toast(res.assets.length > 1 ? `${res.assets.length} files uploaded` : 'Uploaded. The customer sees it on their tracker.');
      invalidate(qc, [`jobs/${j.id}`]);
    } catch (e) {
      toast((e as Error).message, 'error');
    } finally {
      setBusy(null);
    }
  };

  return (
    <>
      <Row gap={8}>
        <Button title="Photo" icon="camera" flex onPress={() => capture('photo')} disabled={Boolean(busy)} />
        <Button title="Video" icon="video" variant="secondary" flex onPress={() => capture('video')} disabled={Boolean(busy)} />
        <Button title="Library" icon="image" variant="secondary" flex onPress={() => capture('library')} disabled={Boolean(busy)} />
      </Row>
      {busy ? <Card><Row gap={10}><ActivityIndicator /><T>{busy}...</T></Row></Card> : null}
      <Card>
        {j.files.length === 0 ? <Empty icon="camera" title="No photos yet" message="Photos and short videos build trust and speed up approvals." /> : <MediaGrid files={j.files} />}
      </Card>
    </>
  );
}

// ------------------------------------------------------------------ check-in: damage + signature

function CheckIn({ j }: { j: JobDetail }) {
  const p = usePalette();
  const initial = Array.isArray(j.damage) ? {} : j.damage;
  const [marks, setMarks] = useState<Record<string, DamageMark>>(initial);
  const [sign, setSign] = useState(false);
  const damage = useJobAction<{ marks: Record<string, DamageMark> }>(j, 'damage', 'Damage map saved');
  const signature = useJobAction<{ signature: string }>(j, 'signature', 'Signature saved');

  return (
    <>
      <Section title="Existing damage">
        <Card>
          <DamageDiagram marks={marks} editable onChange={(m) => { setMarks(m); damage.mutate({ marks: m }); }} />
        </Card>
      </Section>
      <Section title="Customer signature">
        <Card style={{ gap: 10 }}>
          {j.signed ? (
            <Row gap={10}>
              <View style={{ width: 36, height: 36, borderRadius: 18, backgroundColor: '#10b98122', alignItems: 'center', justifyContent: 'center' }}><Icon name="check" color="#10b981" /></View>
              <View style={{ flex: 1 }}>
                <T weight="800">Signed</T>
                <T size={12.5} muted>{fdatetime(j.signed_at)}</T>
              </View>
            </Row>
          ) : (
            <T muted size={13}>Hand the phone to the customer to sign for the vehicle condition and workshop terms.</T>
          )}
          <Button title={j.signed ? 'Sign again' : 'Capture signature'} icon="edit-3" variant={j.signed ? 'secondary' : 'primary'} onPress={() => setSign(true)} />
        </Card>
      </Section>
      <T size={11.5} muted center style={{ color: p.faint }}>Both print on the job card PDF.</T>
      <SignaturePad visible={sign} onClose={() => setSign(false)} saving={signature.isPending} onSave={(sig) => signature.mutate({ signature: sig }, { onSuccess: () => setSign(false) })} />
    </>
  );
}

// ------------------------------------------------------------------ time & costing

function Time({ j }: { j: JobDetail }) {
  const p = usePalette();
  const c = j.costing;
  return (
    <>
      <Card style={{ gap: 4 }}>
        <Label>Time on this job</Label>
        <T size={26} weight="800">{minutesLabel(j.time.total_minutes)}</T>
        {j.time.running_since ? <Badge label="Clock running" color="#10b981" /> : null}
      </Card>
      {c ? (
        <Section title="Job profitability">
          <Card>
            <KeyValue label="Revenue (excl. VAT)" value={money(c.revenue)} />
            <KeyValue label="Parts cost" value={money(c.parts_cost)} />
            <KeyValue label="Labour cost" value={money(c.labour_cost)} />
            <Divider />
            <KeyValue label="Gross profit" value={money(c.profit)} strong color={c.profit >= 0 ? p.success : p.danger} />
            <KeyValue label="Margin" value={`${c.margin}%`} />
            <KeyValue label="Hours sold / clocked" value={`${c.hours_sold} h / ${c.hours_clocked} h`} />
            {c.efficiency !== null ? <KeyValue label="Efficiency" value={`${c.efficiency}%`} color={c.efficiency >= 100 ? p.success : p.warning} /> : null}
          </Card>
        </Section>
      ) : null}
      <Section title="Clock history">
        <Card padded={false}>
          {j.time.logs.length === 0 ? <Empty icon="clock" title="No time logged yet" /> : null}
          {j.time.logs.map((l, idx) => (
            <View key={l.id}>
              <ListItem icon="clock" title={l.first_name} subtitle={`${fdatetime(l.started_at)}${l.ended_at ? ` to ${fdatetime(l.ended_at).split(', ')[1]}` : ' · running'}`} right={<T weight="700">{l.minutes !== null ? minutesLabel(l.minutes) : 'Live'}</T>} />
              {idx < j.time.logs.length - 1 ? <Divider inset={64} /> : null}
            </View>
          ))}
        </Card>
      </Section>
    </>
  );
}
