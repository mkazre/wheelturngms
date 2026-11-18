import { CameraView, useCameraPermissions, type BarcodeType } from 'expo-camera';
import { Image } from 'expo-image';
import { useVideoPlayer, VideoView } from 'expo-video';
import React, { useRef, useState } from 'react';
import { Dimensions, Modal, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import SignatureView, { type SignatureViewRef } from 'react-native-signature-canvas';
import Svg, { Path, Rect, Text as SvgText } from 'react-native-svg';
import { authSource } from '@/lib/api';
import { duration, fdatetime } from '@/lib/format';
import { useTicker } from '@/lib/hooks';
import { usePalette } from '@/lib/theme';
import type { DamageMark, JobRow } from '@/lib/types';
import { Badge, Button, Card, Chip, Field, Icon, IconButton, Row, Sheet, T } from './ui';

// ------------------------------------------------------------------ job row

export function JobCardRow({ job, onPress, showPrice }: { job: JobRow; onPress: () => void; showPrice?: boolean }) {
  const p = usePalette();
  const mechanics = (job.mechanics as ({ name: string } | string)[]).map((m) => (typeof m === 'string' ? m : m.name));
  return (
    <Card onPress={onPress} style={{ gap: 8, borderLeftWidth: 4, borderLeftColor: job.status_color }}>
      <Row style={{ justifyContent: 'space-between' }}>
        <Row gap={8} style={{ flex: 1 }}>
          <View style={{ paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6, backgroundColor: p.dark ? '#262c78' : '#fef9c3', borderWidth: 1, borderColor: p.dark ? '#353d96' : '#facc15' }}>
            <T size={13} weight="800" color={p.dark ? '#fff' : '#0b0d2e'} style={{ letterSpacing: 0.5 }}>{job.registration}</T>
          </View>
          <T size={12} muted>{job.number}</T>
          {job.priority === 'urgent' || job.priority === 'high' ? <Badge label={job.priority === 'urgent' ? 'Urgent' : 'High'} color="#ef4444" /> : null}
        </Row>
        {job.running ? (
          <Row gap={4}>
            <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: '#10b981' }} />
            <T size={11} weight="700" color="#10b981">ON CLOCK</T>
          </Row>
        ) : null}
      </Row>
      <T weight="700" numberOfLines={1}>{job.vehicle}{job.job_type ? ` · ${job.job_type}` : ''}</T>
      {job.complaint ? <T size={13} muted numberOfLines={2}>{job.complaint}</T> : null}
      <Row style={{ justifyContent: 'space-between' }}>
        <Badge label={job.status_label} color={job.status_color} />
        <Row gap={10}>
          {job.late ? <Badge label="Overdue" color="#ef4444" solid /> : null}
          {mechanics.length ? (
            <Row gap={4}>
              <Icon name="tool" size={12} color={p.muted} />
              <T size={12} muted numberOfLines={1}>{mechanics.join(', ')}</T>
            </Row>
          ) : null}
          {showPrice && job.total ? <T size={13} weight="800">R {Math.round(job.total).toLocaleString('en-ZA')}</T> : null}
        </Row>
      </Row>
      {job.customer ? <T size={12} muted>{job.customer}{job.promised_at ? ` · promised ${fdatetime(job.promised_at)}` : ''}</T> : null}
    </Card>
  );
}

/** Global "clocked on" banner for technicians. */
export function TimerBanner({ timer, onPress }: { timer: { job_id: number; number: string; registration: string; started_at: string } | null; onPress: (id: number) => void }) {
  const now = useTicker(Boolean(timer));
  if (!timer) return null;
  return (
    <Pressable onPress={() => onPress(timer.job_id)} style={{ backgroundColor: '#065f46', borderRadius: 14, padding: 12, flexDirection: 'row', alignItems: 'center', gap: 10 }}>
      <Icon name="clock" color="#fff" size={18} />
      <View style={{ flex: 1 }}>
        <T color="#fff" weight="800">Clocked on {timer.registration}</T>
        <T color="#d1fae5" size={12}>{timer.number}</T>
      </View>
      <T color="#fff" weight="800" size={18} style={{ fontVariant: ['tabular-nums'] }}>{duration(timer.started_at, now)}</T>
    </Pressable>
  );
}

// ------------------------------------------------------------------ damage diagram

export const DAMAGE_ZONES: Record<string, [string, string]> = {
  front_bumper: ['Front bumper', 'M70 12 h160 a20 20 0 0 1 20 20 v14 h-200 v-14 a20 20 0 0 1 20-20z'],
  bonnet: ['Bonnet', 'M58 50 h184 v104 h-184z'],
  windscreen: ['Windscreen', 'M66 158 h168 l-14 44 h-140z'],
  roof: ['Roof', 'M80 206 h140 v150 h-140z'],
  rear_window: ['Rear window', 'M80 360 h140 l14 40 h-168z'],
  boot: ['Boot / tailgate', 'M58 404 h184 v86 h-184z'],
  rear_bumper: ['Rear bumper', 'M50 494 h200 v14 a20 20 0 0 1 -20 20 h-160 a20 20 0 0 1 -20-20z'],
  lf_fender: ['Left front fender', 'M20 60 h34 v110 h-34z'],
  lf_door: ['Left front door', 'M20 174 h56 v100 h-56z'],
  lr_door: ['Left rear door', 'M20 278 h56 v100 h-56z'],
  lr_quarter: ['Left rear quarter', 'M20 382 h34 v110 h-34z'],
  rf_fender: ['Right front fender', 'M246 60 h34 v110 h-34z'],
  rf_door: ['Right front door', 'M224 174 h56 v100 h-56z'],
  rr_door: ['Right rear door', 'M224 278 h56 v100 h-56z'],
  rr_quarter: ['Right rear quarter', 'M246 382 h34 v110 h-34z'],
  l_mirror: ['Left mirror', 'M2 168 h16 v18 h-16z'],
  r_mirror: ['Right mirror', 'M282 168 h16 v18 h-16z'],
};

export const DAMAGE_TYPES: Record<DamageMark['type'], [string, string]> = {
  scratch: ['Scratch', '#f59e0b'],
  dent: ['Dent', '#ef1c25'],
  chip: ['Chip', '#8b5cf6'],
  crack: ['Crack', '#0ea5e9'],
  rust: ['Rust', '#92400e'],
  other: ['Other', '#64748b'],
};

export function DamageDiagram({ marks, editable, onChange }: { marks: Record<string, DamageMark>; editable?: boolean; onChange?: (m: Record<string, DamageMark>) => void }) {
  const p = usePalette();
  const [zone, setZone] = useState<string | null>(null);
  const [type, setType] = useState<DamageMark['type']>('scratch');
  const [note, setNote] = useState('');
  const height = 380;
  const width = (height * 300) / 540;

  const open = (z: string) => {
    if (!editable) return;
    setZone(z);
    setType(marks[z]?.type ?? 'scratch');
    setNote(marks[z]?.note ?? '');
  };

  return (
    <View style={{ gap: 12 }}>
      <View style={{ alignItems: 'center' }}>
        <Svg width={width} height={height} viewBox="0 0 300 540">
          <Rect x={14} y={8} width={272} height={524} rx={60} fill={p.dark ? '#ffffff0d' : '#f8fafc'} stroke={p.dark ? '#ffffff33' : '#cbd5e1'} strokeWidth={2} />
          {Object.entries(DAMAGE_ZONES).map(([key, [, d]]) => {
            const m = marks[key];
            return (
              <Path key={key} d={d} onPress={() => open(key)} fill={m ? DAMAGE_TYPES[m.type][1] : p.dark ? '#ffffff08' : '#ffffff'} fillOpacity={m ? 0.8 : 1} stroke={p.dark ? '#ffffff4d' : '#94a3b8'} strokeWidth={1.5} />
            );
          })}
          <SvgText x={150} y={105} textAnchor="middle" fontSize={11} fontWeight="bold" fill={p.faint}>FRONT</SvgText>
          <SvgText x={150} y={450} textAnchor="middle" fontSize={11} fontWeight="bold" fill={p.faint}>REAR</SvgText>
        </Svg>
      </View>
      <Row wrap gap={10} style={{ justifyContent: 'center' }}>
        {Object.entries(DAMAGE_TYPES).map(([k, [l, c]]) => (
          <Row key={k} gap={5}>
            <View style={{ width: 10, height: 10, borderRadius: 3, backgroundColor: c }} />
            <T size={12} muted>{l}</T>
          </Row>
        ))}
      </Row>
      <View style={{ gap: 6 }}>
        {Object.keys(marks).length === 0 ? <T muted size={13} center>{editable ? 'Tap a panel to record existing damage. It prints on the job card the customer signs.' : 'No existing damage recorded.'}</T> : null}
        {Object.entries(marks).map(([z, m]) => (
          <Pressable key={z} onPress={() => open(z)}>
            <Row gap={8}>
              <View style={{ width: 10, height: 10, borderRadius: 5, backgroundColor: DAMAGE_TYPES[m.type][1] }} />
              <T size={14} weight="700">{DAMAGE_ZONES[z]?.[0] ?? z}</T>
              <T size={13} muted style={{ flex: 1 }} numberOfLines={1}>{DAMAGE_TYPES[m.type][0]}{m.note ? ` · ${m.note}` : ''}</T>
            </Row>
          </Pressable>
        ))}
      </View>
      <Sheet
        visible={zone !== null}
        onClose={() => setZone(null)}
        title={zone ? DAMAGE_ZONES[zone][0] : ''}
        footer={
          <>
            <Button title="No damage" variant="secondary" flex onPress={() => { const next = { ...marks }; delete next[zone!]; onChange?.(next); setZone(null); }} />
            <Button title="Mark damage" flex onPress={() => { onChange?.({ ...marks, [zone!]: { type, note: note.trim() } }); setZone(null); }} />
          </>
        }
      >
        <Row wrap gap={8}>
          {Object.entries(DAMAGE_TYPES).map(([k, [l, c]]) => (
            <Chip key={k} label={l} selected={type === k} color={c} onPress={() => setType(k as DamageMark['type'])} />
          ))}
        </Row>
        <Field label="Note" value={note} onChangeText={setNote} placeholder="Size, location..." maxLength={120} />
      </Sheet>
    </View>
  );
}

// ------------------------------------------------------------------ signature

export function SignaturePad({ visible, onClose, onSave, title = 'Customer signature', saving }: { visible: boolean; onClose: () => void; onSave: (dataUrl: string) => void; title?: string; saving?: boolean }) {
  const ref = useRef<SignatureViewRef>(null);
  const insets = useSafeAreaInsets();
  const p = usePalette();
  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose} presentationStyle="fullScreen">
      <View style={{ flex: 1, backgroundColor: p.bg, paddingTop: insets.top, paddingBottom: insets.bottom + 10 }}>
        <Row style={{ justifyContent: 'space-between', padding: 16 }}>
          <View>
            <T size={18} weight="800">{title}</T>
            <T size={13} muted>I accept the vehicle condition and the workshop terms.</T>
          </View>
          <IconButton icon="x" onPress={onClose} />
        </Row>
        <View style={{ flex: 1, marginHorizontal: 16, borderRadius: 16, overflow: 'hidden', borderWidth: 1, borderColor: p.border, backgroundColor: '#fff' }}>
          <SignatureView
            ref={ref}
            onOK={(sig) => onSave(sig)}
            imageType="image/png"
            backgroundColor="#ffffff"
            penColor="#0b0d2e"
            autoClear={false}
            webStyle={`.m-signature-pad { box-shadow: none; border: none; margin: 0; } .m-signature-pad--body { border: none; } .m-signature-pad--footer { display: none; } body, html { height: 100%; }`}
          />
        </View>
        <Row style={{ padding: 16 }} gap={10}>
          <Button title="Clear" icon="rotate-ccw" variant="secondary" flex onPress={() => ref.current?.clearSignature()} />
          <Button title="Save signature" icon="check" flex loading={saving} onPress={() => ref.current?.readSignature()} />
        </Row>
      </View>
    </Modal>
  );
}

// ------------------------------------------------------------------ scanner

export function Scanner({ visible, onClose, onScan, title = 'Scan barcode', types = ['ean13', 'ean8', 'code128', 'code39', 'upc_a', 'upc_e', 'qr', 'pdf417', 'datamatrix'] }: {
  visible: boolean; onClose: () => void; onScan: (data: string) => void; title?: string; types?: BarcodeType[];
}) {
  const [perm, request] = useCameraPermissions();
  const done = useRef(false);
  const insets = useSafeAreaInsets();
  const [torch, setTorch] = useState(false);
  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose} onShow={() => { done.current = false; }}>
      <View style={{ flex: 1, backgroundColor: '#000' }}>
        {perm?.granted ? (
          <CameraView
            style={StyleSheet.absoluteFill}
            facing="back"
            enableTorch={torch}
            barcodeScannerSettings={{ barcodeTypes: types }}
            onBarcodeScanned={(r) => {
              if (done.current || !r.data) return;
              done.current = true;
              onScan(r.data.trim());
            }}
          />
        ) : (
          <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', padding: 30, gap: 14 }}>
            <Icon name="camera-off" color="#fff" size={34} />
            <T color="#fff" center>Camera access is needed to scan.</T>
            <Button title="Allow camera" onPress={request} variant="accent" />
          </View>
        )}
        <View style={{ position: 'absolute', top: insets.top + 10, left: 16, right: 16, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
          <T color="#fff" weight="800" size={17}>{title}</T>
          <Row gap={6}>
            <IconButton icon={torch ? 'zap-off' : 'zap'} color="#fff" bg="#ffffff26" onPress={() => setTorch(!torch)} />
            <IconButton icon="x" color="#fff" bg="#ffffff26" onPress={onClose} />
          </Row>
        </View>
        {perm?.granted ? (
          <View pointerEvents="none" style={{ position: 'absolute', top: '32%', left: '10%', right: '10%', height: 170, borderWidth: 2, borderColor: '#ffffffcc', borderRadius: 18 }} />
        ) : null}
      </View>
    </Modal>
  );
}

// ------------------------------------------------------------------ media

export function MediaGrid({ files, columns = 3 }: { files: { id: number; url: string; is_video: boolean; name?: string }[]; columns?: number }) {
  const p = usePalette();
  const [open, setOpen] = useState<number | null>(null);
  const size = (Dimensions.get('window').width - 32 - 32 - (columns - 1) * 8) / columns;
  return (
    <>
      <Row wrap gap={8}>
        {files.map((f, i) => (
          <Pressable key={f.id} onPress={() => setOpen(i)} style={{ width: size, height: size, borderRadius: 12, overflow: 'hidden', backgroundColor: p.cardAlt, alignItems: 'center', justifyContent: 'center' }}>
            {f.is_video ? (
              <View style={{ alignItems: 'center', gap: 4 }}>
                <Icon name="play-circle" size={30} color={p.muted} />
                <T size={11} muted>Video</T>
              </View>
            ) : (
              <Image source={authSource(f.url)} style={{ width: '100%', height: '100%' }} contentFit="cover" transition={150} />
            )}
          </Pressable>
        ))}
      </Row>
      <Viewer files={files} index={open} onClose={() => setOpen(null)} />
    </>
  );
}

function Viewer({ files, index, onClose }: { files: { id: number; url: string; is_video: boolean }[]; index: number | null; onClose: () => void }) {
  const insets = useSafeAreaInsets();
  const { width, height } = Dimensions.get('window');
  return (
    <Modal visible={index !== null} transparent animationType="fade" onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: '#000' }}>
        {index !== null ? (
          <ScrollView horizontal pagingEnabled contentOffset={{ x: index * width, y: 0 }} showsHorizontalScrollIndicator={false}>
            {files.map((f) => (
              <View key={f.id} style={{ width, height, alignItems: 'center', justifyContent: 'center' }}>
                {f.is_video ? <VideoItem url={f.url} /> : <Image source={authSource(f.url)} style={{ width, height: height * 0.8 }} contentFit="contain" />}
              </View>
            ))}
          </ScrollView>
        ) : null}
        <View style={{ position: 'absolute', top: insets.top + 10, right: 16 }}>
          <IconButton icon="x" color="#fff" bg="#ffffff26" onPress={onClose} />
        </View>
      </View>
    </Modal>
  );
}

function VideoItem({ url }: { url: string }) {
  const { width } = Dimensions.get('window');
  const player = useVideoPlayer(authSource(url), (pl) => {
    pl.loop = false;
  });
  return <VideoView player={player} style={{ width, height: (width * 9) / 16 }} nativeControls contentFit="contain" />;
}
