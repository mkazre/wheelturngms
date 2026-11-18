import React, { useState } from 'react';
import { LayoutChangeEvent, Pressable, View } from 'react-native';
import Svg, { Circle, G, Line, Path, Rect, Text as SvgText } from 'react-native-svg';
import { compactMoney, money } from '@/lib/format';
import { chartColors, usePalette } from '@/lib/theme';
import { Row, T } from './ui';

function useWidth(): [number, (e: LayoutChangeEvent) => void] {
  const [w, setW] = useState(0);
  return [w, (e) => setW(Math.round(e.nativeEvent.layout.width))];
}

/** Grouped bar chart (e.g. revenue vs collected) with tap-to-inspect. */
export function BarChart({ labels, series, height = 190, format = compactMoney }: {
  labels: string[]; series: { name: string; values: number[]; color: string }[]; height?: number; format?: (v: number) => string;
}) {
  const p = usePalette();
  const [width, onLayout] = useWidth();
  const [sel, setSel] = useState<number | null>(null);
  const n = labels.length;
  const max = Math.max(1, ...series.flatMap((s) => s.values));
  const left = 44;
  const bottom = 22;
  const plotW = Math.max(0, width - left - 4);
  const plotH = height - bottom - 8;
  const group = n ? plotW / n : 0;
  const barW = Math.max(2, Math.min(16, (group * 0.72) / series.length));
  const ticks = [0, 0.5, 1].map((f) => f * max);
  const every = Math.max(1, Math.ceil(n / 6));

  return (
    <View onLayout={onLayout}>
      {width > 0 ? (
        <Svg width={width} height={height}>
          {ticks.map((t, i) => {
            const y = 8 + plotH - (t / max) * plotH;
            return (
              <G key={i}>
                <Line x1={left} x2={width} y1={y} y2={y} stroke={p.border} strokeDasharray={i === 0 ? undefined : '3 4'} />
                <SvgText x={left - 6} y={y + 4} fontSize={10} fill={p.faint} textAnchor="end">{compactMoney(t).replace('R ', '')}</SvgText>
              </G>
            );
          })}
          {labels.map((l, i) => {
            const gx = left + i * group + (group - barW * series.length) / 2;
            return (
              <G key={i} opacity={sel === null || sel === i ? 1 : 0.35}>
                {series.map((s, si) => {
                  const v = s.values[i] ?? 0;
                  const h = Math.max(v > 0 ? 2 : 0, (v / max) * plotH);
                  return <Rect key={si} x={gx + si * barW} y={8 + plotH - h} width={barW - 1.5} height={h} rx={Math.min(3, barW / 3)} fill={s.color} />;
                })}
                {i % every === 0 ? (
                  <SvgText x={left + i * group + group / 2} y={height - 6} fontSize={10} fill={p.faint} textAnchor="middle">{l}</SvgText>
                ) : null}
              </G>
            );
          })}
        </Svg>
      ) : (
        <View style={{ height }} />
      )}
      {width > 0 ? (
        <View style={{ position: 'absolute', left, top: 0, right: 0, height: height - bottom, flexDirection: 'row' }}>
          {labels.map((_, i) => (
            <Pressable key={i} style={{ flex: 1 }} onPress={() => setSel(sel === i ? null : i)} />
          ))}
        </View>
      ) : null}
      <Row wrap gap={14} style={{ marginTop: 6 }}>
        {series.map((s) => (
          <Row key={s.name} gap={6}>
            <View style={{ width: 10, height: 10, borderRadius: 3, backgroundColor: s.color }} />
            <T size={12} muted>{s.name}{sel !== null ? `: ${format(s.values[sel] ?? 0)}` : ''}</T>
          </Row>
        ))}
        {sel !== null ? <T size={12} weight="700">{labels[sel]}</T> : null}
      </Row>
    </View>
  );
}

function arc(cx: number, cy: number, r: number, start: number, end: number): string {
  const a0 = (start - 90) * (Math.PI / 180);
  const a1 = (end - 90) * (Math.PI / 180);
  const x0 = cx + r * Math.cos(a0);
  const y0 = cy + r * Math.sin(a0);
  const x1 = cx + r * Math.cos(a1);
  const y1 = cy + r * Math.sin(a1);
  return `M ${x0} ${y0} A ${r} ${r} 0 ${end - start > 180 ? 1 : 0} 1 ${x1} ${y1}`;
}

/** Donut (pie) chart with legend; tap a legend row to highlight the slice. */
export function Donut({ data, size = 150, centerLabel, format = money }: {
  data: { label: string; value: number; color?: string }[]; size?: number; centerLabel?: string; format?: (v: number) => string;
}) {
  const p = usePalette();
  const [sel, setSel] = useState<number | null>(null);
  const items = data.filter((d) => d.value > 0).map((d, i) => ({ ...d, color: d.color ?? chartColors[i % chartColors.length] }));
  const total = items.reduce((s, d) => s + d.value, 0);
  const stroke = size * 0.15;
  const r = size / 2 - stroke / 2 - 2;
  let angle = 0;
  const shown = sel !== null ? items[sel] : null;

  return (
    <Row gap={16} style={{ alignItems: 'center' }}>
      <View style={{ width: size, height: size }}>
        <Svg width={size} height={size}>
          <Circle cx={size / 2} cy={size / 2} r={r} stroke={p.border} strokeWidth={stroke} fill="none" />
          {total > 0
            ? items.map((d, i) => {
                const sweep = (d.value / total) * 360;
                const start = angle + 0.8;
                const end = angle + Math.max(sweep - 0.8, 0.9);
                angle += sweep;
                if (items.length === 1) return <Circle key={i} cx={size / 2} cy={size / 2} r={r} stroke={d.color} strokeWidth={stroke} fill="none" />;
                return <Path key={i} d={arc(size / 2, size / 2, r, start, end)} stroke={d.color} strokeWidth={sel === i ? stroke + 6 : stroke} fill="none" opacity={sel === null || sel === i ? 1 : 0.3} />;
              })
            : null}
        </Svg>
        <View style={{ position: 'absolute', inset: 0, alignItems: 'center', justifyContent: 'center', padding: stroke + 4 }}>
          <T size={shown ? 13 : 15} weight="800" center numberOfLines={1}>{shown ? format(shown.value) : compactMoney(total)}</T>
          <T size={10.5} muted center numberOfLines={2}>{shown ? `${shown.label} · ${total ? Math.round((shown.value / total) * 100) : 0}%` : centerLabel ?? 'Total'}</T>
        </View>
      </View>
      <View style={{ flex: 1, gap: 6 }}>
        {items.length === 0 ? <T muted size={13}>No data for this period.</T> : null}
        {items.slice(0, 7).map((d, i) => (
          <Pressable key={d.label} onPress={() => setSel(sel === i ? null : i)}>
            <Row gap={8}>
              <View style={{ width: 10, height: 10, borderRadius: 3, backgroundColor: d.color }} />
              <T size={13} style={{ flex: 1 }} numberOfLines={1} weight={sel === i ? '800' : '500'}>{d.label}</T>
              <T size={12} muted>{total ? Math.round((d.value / total) * 100) : 0}%</T>
            </Row>
          </Pressable>
        ))}
      </View>
    </Row>
  );
}

/** Horizontal labelled bars (pipeline, technicians). */
export function HBars({ data, format = (v: number) => String(v) }: { data: { label: string; value: number; color?: string; sub?: string }[]; format?: (v: number) => string }) {
  const p = usePalette();
  const max = Math.max(1, ...data.map((d) => d.value));
  return (
    <View style={{ gap: 10 }}>
      {data.map((d, i) => (
        <View key={d.label + i} style={{ gap: 4 }}>
          <Row style={{ justifyContent: 'space-between' }}>
            <T size={13} weight="600" numberOfLines={1} style={{ flex: 1 }}>{d.label}</T>
            <T size={13} weight="800">{format(d.value)}</T>
          </Row>
          <View style={{ height: 8, borderRadius: 4, backgroundColor: p.border, overflow: 'hidden' }}>
            <View style={{ width: `${Math.max(2, (d.value / max) * 100)}%`, height: 8, borderRadius: 4, backgroundColor: d.color ?? chartColors[i % chartColors.length] }} />
          </View>
          {d.sub ? <T size={11.5} muted>{d.sub}</T> : null}
        </View>
      ))}
    </View>
  );
}

/** Progress toward a target with a "where we should be" marker. */
export function TargetMeter({ value, target, expectedPct }: { value: number; target: number; expectedPct: number }) {
  const p = usePalette();
  const pct = target > 0 ? Math.min(100, (value / target) * 100) : 0;
  const ahead = pct >= expectedPct;
  return (
    <View style={{ gap: 8 }}>
      <Row style={{ justifyContent: 'space-between' }}>
        <T size={24} weight="800">{Math.round(pct)}%</T>
        <T size={13} muted>{compactMoney(value)} of {compactMoney(target)}</T>
      </Row>
      <View style={{ height: 12, borderRadius: 6, backgroundColor: p.border }}>
        <View style={{ width: `${pct}%`, height: 12, borderRadius: 6, backgroundColor: ahead ? p.success : p.accent }} />
        <View style={{ position: 'absolute', left: `${Math.min(99, expectedPct)}%`, top: -3, width: 2, height: 18, backgroundColor: p.text, opacity: 0.6 }} />
      </View>
      <T size={12.5} muted>{ahead ? 'Ahead of pace' : 'Behind pace'}: {expectedPct}% of the month has passed.</T>
    </View>
  );
}
