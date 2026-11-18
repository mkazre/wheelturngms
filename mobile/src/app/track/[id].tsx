import { Stack, useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { View } from 'react-native';
import { MediaGrid } from '@/components/workshop';
import { Badge, Button, Card, ErrorView, Icon, Loading, Row, Screen, Section, T } from '@/components/ui';
import { fdatetime, money } from '@/lib/format';
import { useAction, useApi } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';
import type { MyJob } from '@/lib/types';

export default function Track() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const p = usePalette();
  const job = useApi<MyJob>(`my/jobs/${id}`, { refetchInterval: 30_000 });
  const decide = useAction<{ item_id: number; decision: 'approve' | 'decline' }>(`my/jobs/${id}/decide`, { invalidate: [`my/jobs/${id}`, 'my/overview'] });

  if (job.isLoading) return <Loading />;
  if (job.error || !job.data) return <ErrorView error={job.error} onRetry={job.refresh} />;
  const j = job.data;
  const pending = j.findings.filter((f) => !f.decision);

  return (
    <>
      <Stack.Screen options={{ title: j.vehicle.registration }} />
      <Screen refreshing={job.refreshing} onRefresh={job.refresh}>
        <Card style={{ gap: 4 }}>
          <T muted size={13}>{j.vehicle.label} · {j.number}</T>
          <T size={22} weight="800">{j.status_label}</T>
          {j.promised_at ? <T size={13} muted>Expected ready {fdatetime(j.promised_at)}</T> : null}
        </Card>

        <Card style={{ gap: 0 }}>
          {j.steps.map((s, i) => {
            const done = i < j.step_index;
            const now = i === j.step_index;
            return (
              <Row key={s.key} gap={12} style={{ alignItems: 'flex-start' }}>
                <View style={{ alignItems: 'center' }}>
                  <View style={{ width: 26, height: 26, borderRadius: 13, backgroundColor: done || now ? j.status_color : p.border, alignItems: 'center', justifyContent: 'center' }}>
                    {done ? <Icon name="check" size={14} color="#fff" /> : now ? <View style={{ width: 10, height: 10, borderRadius: 5, backgroundColor: '#fff' }} /> : null}
                  </View>
                  {i < j.steps.length - 1 ? <View style={{ width: 2, height: 24, backgroundColor: done ? j.status_color : p.border }} /> : null}
                </View>
                <T weight={now ? '800' : '500'} muted={!done && !now} style={{ paddingTop: 3 }}>{s.label}</T>
              </Row>
            );
          })}
        </Card>

        {j.findings.length ? (
          <Section title={pending.length ? `Needs your approval (${pending.length})` : 'Technician findings'}>
            {j.findings.map((f) => (
              <Card key={f.id} style={{ gap: 8, borderColor: !f.decision && f.severity === 'urgent' ? '#fca5a5' : p.border }}>
                <Row style={{ justifyContent: 'space-between' }}>
                  <T weight="800" style={{ flex: 1 }}>{f.item}</T>
                  <Badge label={f.severity === 'urgent' ? 'Urgent' : 'Recommended'} color={f.severity === 'urgent' ? '#ef4444' : '#f59e0b'} />
                </Row>
                {f.note ? <T size={14} muted>{f.note}</T> : null}
                {f.estimate ? <T weight="800">{money(f.estimate)} <T size={12} muted>incl. VAT</T></T> : null}
                {f.decision ? (
                  <Badge label={f.decision === 'approved' ? 'You approved this' : 'You declined this'} color={f.decision === 'approved' ? '#10b981' : '#64748b'} />
                ) : j.can_decide ? (
                  <Row gap={8}>
                    <Button title="Not now" small variant="secondary" flex loading={decide.isPending && decide.variables?.item_id === f.id && decide.variables?.decision === 'decline'} onPress={() => decide.mutate({ item_id: f.id, decision: 'decline' })} />
                    <Button title="Approve" icon="check" small variant="success" flex loading={decide.isPending && decide.variables?.item_id === f.id && decide.variables?.decision === 'approve'} onPress={() => decide.mutate({ item_id: f.id, decision: 'approve' })} />
                  </Row>
                ) : null}
              </Card>
            ))}
          </Section>
        ) : null}

        {j.photos.length ? (
          <Section title="Photos & videos from the workshop">
            <Card><MediaGrid files={j.photos} /></Card>
          </Section>
        ) : null}

        {j.work_performed || j.recommendations ? (
          <Section title="Work report">
            <Card style={{ gap: 8 }}>
              {j.work_performed ? <T size={14}>{j.work_performed}</T> : null}
              {j.recommendations ? <><T weight="800" size={13}>Recommendations</T><T size={14} muted>{j.recommendations}</T></> : null}
            </Card>
          </Section>
        ) : null}

        {j.invoice ? (
          <Card style={{ gap: 10 }}>
            <Row style={{ justifyContent: 'space-between' }}>
              <View>
                <T weight="800">Invoice {j.invoice.number}</T>
                <T size={13} muted>Total {money(j.invoice.total)}</T>
              </View>
              <Badge label={j.invoice.balance > 0 ? 'Due' : 'Paid'} color={j.invoice.balance > 0 ? '#f59e0b' : '#10b981'} />
            </Row>
            {j.invoice.balance > 0 ? <Button title={`Pay ${money(j.invoice.balance)} securely`} icon="credit-card" variant="accent" onPress={async () => { await WebBrowser.openBrowserAsync(j.invoice!.pay_url); job.refresh(); }} /> : null}
          </Card>
        ) : null}

        {j.timeline.length ? (
          <Section title="Timeline">
            <Card style={{ gap: 10 }}>
              {j.timeline.map((t, i) => (
                <Row key={i} gap={10} style={{ alignItems: 'flex-start' }}>
                  <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: p.faint, marginTop: 6 }} />
                  <View style={{ flex: 1 }}>
                    <T size={14}>{t.description}</T>
                    <T size={12} muted>{fdatetime(t.created_at)}</T>
                  </View>
                </Row>
              ))}
            </Card>
          </Section>
        ) : null}
      </Screen>
    </>
  );
}
