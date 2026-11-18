import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, View } from 'react-native';
import { Badge, Button, Card, Chip, Divider, Empty, Field, Icon, Loading, Row, Screen, Sheet, T } from '@/components/ui';
import { useMe } from '@/lib/auth';
import { fdatetime, ymd } from '@/lib/format';
import { useAction, useApi } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';
import type { Task } from '@/lib/types';

export default function Tasks() {
  const me = useMe();
  const p = usePalette();
  const tasks = useApi<{ items: Task[] }>('tasks');
  const staff = useApi<{ items: { id: number; name: string; role: string }[] }>('staff');
  const complete = useAction<{ id: number }>((b) => `tasks/${b.id}/complete`, { success: 'Task done', invalidate: ['tasks'] });
  const [open, setOpen] = useState(false);
  const [title, setTitle] = useState('');
  const [notes, setNotes] = useState('');
  const [due, setDue] = useState<string | null>(null);
  const [assignee, setAssignee] = useState<number>(me.user.id);
  const create = useAction<Record<string, unknown>>('tasks', { success: 'Task added', invalidate: ['tasks'], onSuccess: () => { setOpen(false); setTitle(''); setNotes(''); setDue(null); } });

  const today = new Date();
  const dueOptions = [
    { label: 'Today', value: `${ymd(today)} 17:00` },
    { label: 'Tomorrow', value: `${ymd(new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1))} 09:00` },
    { label: 'Next week', value: `${ymd(new Date(today.getFullYear(), today.getMonth(), today.getDate() + 7))} 09:00` },
  ];

  if (tasks.isLoading) return <Loading />;
  const items = tasks.data?.items ?? [];

  return (
    <Screen refreshing={tasks.refreshing} onRefresh={tasks.refresh} footer={<Button title="New task" icon="plus" flex onPress={() => setOpen(true)} />}>
      {items.length === 0 ? <Card><Empty icon="check-square" title="All clear" message="No open tasks assigned to you." /></Card> : null}
      {items.length ? (
        <Card padded={false}>
          {items.map((t, i) => (
            <View key={t.id}>
              <Row style={{ padding: 14, alignItems: 'flex-start' }} gap={12}>
                <Pressable onPress={() => complete.mutate({ id: t.id })} hitSlop={10} style={{ width: 24, height: 24, borderRadius: 12, borderWidth: 2, borderColor: t.overdue ? p.danger : p.border, marginTop: 1 }} />
                <Pressable style={{ flex: 1, gap: 3 }} onPress={t.entity_type === 'job_card' && t.entity_id ? () => router.push(`/job/${t.entity_id}`) : t.entity_type === 'customer' && t.entity_id ? () => router.push(`/customer/${t.entity_id}`) : undefined}>
                  <T weight="700">{t.title}</T>
                  {t.notes ? <T size={13} muted>{t.notes}</T> : null}
                  <Row gap={8}>
                    {t.due_at ? <Row gap={4}><Icon name="clock" size={12} color={t.overdue ? p.danger : p.muted} /><T size={12} color={t.overdue ? p.danger : p.muted}>{fdatetime(t.due_at)}</T></Row> : null}
                    {t.priority === 'high' ? <Badge label="High" color="#ef4444" /> : null}
                    {t.entity_type ? <Badge label={t.entity_type === 'job_card' ? 'Job' : t.entity_type} /> : null}
                  </Row>
                </Pressable>
              </Row>
              {i < items.length - 1 ? <Divider inset={50} /> : null}
            </View>
          ))}
        </Card>
      ) : null}
      <T size={12} muted center>Tap the circle to complete a task.</T>

      <Sheet visible={open} onClose={() => setOpen(false)} title="New task" footer={<Button title="Add task" flex loading={create.isPending} onPress={() => create.mutate({ title, notes, due_at: due ?? undefined, assigned_to: assignee })} />}>
        <Field label="Task" value={title} onChangeText={setTitle} placeholder="e.g. Call Mr Botha about the quote" autoFocus />
        <Field label="Notes" value={notes} onChangeText={setNotes} multiline />
        <View style={{ gap: 6 }}>
          <T size={13} weight="700" muted>Due</T>
          <Row wrap gap={6}>{dueOptions.map((o) => <Chip key={o.label} label={o.label} selected={due === o.value} onPress={() => setDue(due === o.value ? null : o.value)} />)}</Row>
        </View>
        {me.mode === 'manager' && staff.data ? (
          <View style={{ gap: 6 }}>
            <T size={13} weight="700" muted>Assign to</T>
            <Row wrap gap={6}>{staff.data.items.map((s) => <Chip key={s.id} label={s.id === me.user.id ? 'Me' : s.name} selected={assignee === s.id} onPress={() => setAssignee(s.id)} />)}</Row>
          </View>
        ) : null}
      </Sheet>
    </Screen>
  );
}
