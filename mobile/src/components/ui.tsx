import Feather from '@expo/vector-icons/Feather';
import * as Haptics from 'expo-haptics';
import React, { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleProp,
  StyleSheet,
  Text,
  TextInput,
  TextInputProps,
  TextStyle,
  View,
  ViewStyle,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { initials } from '@/lib/format';
import { radius, usePalette, type Palette } from '@/lib/theme';

export type IconName = React.ComponentProps<typeof Feather>['name'];

export function Icon({ name, size = 20, color, style }: { name: IconName; size?: number; color?: string; style?: StyleProp<TextStyle> }) {
  const p = usePalette();
  return <Feather name={name} size={size} color={color ?? p.text} style={style} />;
}

// ------------------------------------------------------------------ text

export function T({ children, style, muted, size = 15, weight = '400', color, numberOfLines, center }: {
  children: React.ReactNode; style?: StyleProp<TextStyle>; muted?: boolean; size?: number; weight?: TextStyle['fontWeight']; color?: string; numberOfLines?: number; center?: boolean;
}) {
  const p = usePalette();
  return (
    <Text numberOfLines={numberOfLines} style={[{ color: color ?? (muted ? p.muted : p.text), fontSize: size, fontWeight: weight, textAlign: center ? 'center' : undefined }, style]}>
      {children}
    </Text>
  );
}

export function Title({ children, style }: { children: React.ReactNode; style?: StyleProp<TextStyle> }) {
  return <T size={22} weight="800" style={[{ letterSpacing: -0.3 }, style]}>{children}</T>;
}

export function Label({ children, style }: { children: React.ReactNode; style?: StyleProp<TextStyle> }) {
  const p = usePalette();
  return <Text style={[{ color: p.muted, fontSize: 11, fontWeight: '700', letterSpacing: 0.8, textTransform: 'uppercase' }, style]}>{children}</Text>;
}

// ------------------------------------------------------------------ layout

export function Screen({ children, refreshing, onRefresh, padded = true, scroll = true, style, footer }: {
  children: React.ReactNode; refreshing?: boolean; onRefresh?: () => void; padded?: boolean; scroll?: boolean; style?: StyleProp<ViewStyle>; footer?: React.ReactNode;
}) {
  const p = usePalette();
  const insets = useSafeAreaInsets();
  const content = scroll ? (
    <ScrollView
      style={{ flex: 1 }}
      contentContainerStyle={[padded && { padding: 16, paddingBottom: 32 + (footer ? 0 : insets.bottom) }, { gap: 14 }, style]}
      keyboardShouldPersistTaps="handled"
      refreshControl={onRefresh ? <RefreshControl refreshing={Boolean(refreshing)} onRefresh={onRefresh} tintColor={p.muted} /> : undefined}
    >
      {children}
    </ScrollView>
  ) : (
    <View style={[{ flex: 1 }, padded && { padding: 16 }, style]}>{children}</View>
  );
  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: p.bg }} behavior={Platform.OS === 'ios' ? 'padding' : undefined} keyboardVerticalOffset={Platform.OS === 'ios' ? 90 : 0}>
      {content}
      {footer ? <View style={{ padding: 12, paddingBottom: 12 + insets.bottom, borderTopWidth: StyleSheet.hairlineWidth, borderColor: p.border, backgroundColor: p.card, flexDirection: 'row', gap: 10 }}>{footer}</View> : null}
    </KeyboardAvoidingView>
  );
}

export function Card({ children, style, onPress, padded = true }: { children: React.ReactNode; style?: StyleProp<ViewStyle>; onPress?: () => void; padded?: boolean }) {
  const p = usePalette();
  const base: ViewStyle = {
    backgroundColor: p.card,
    borderRadius: radius.lg,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: p.border,
    padding: padded ? 16 : 0,
    shadowColor: '#0b0d2e',
    shadowOpacity: p.dark ? 0 : 0.05,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 3 },
    elevation: p.dark ? 0 : 1,
  };
  if (onPress) {
    return (
      <Pressable onPress={onPress} style={({ pressed }) => [base, { opacity: pressed ? 0.85 : 1, transform: [{ scale: pressed ? 0.995 : 1 }] }, style]}>
        {children}
      </Pressable>
    );
  }
  return <View style={[base, style]}>{children}</View>;
}

export function Section({ title, action, children, style }: { title: string; action?: { label: string; onPress: () => void }; children: React.ReactNode; style?: StyleProp<ViewStyle> }) {
  const p = usePalette();
  return (
    <View style={[{ gap: 8 }, style]}>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 2 }}>
        <Label>{title}</Label>
        {action ? (
          <Pressable onPress={action.onPress} hitSlop={10}>
            <Text style={{ color: p.accent, fontSize: 13, fontWeight: '700' }}>{action.label}</Text>
          </Pressable>
        ) : null}
      </View>
      {children}
    </View>
  );
}

export function Row({ children, gap = 10, style, wrap }: { children: React.ReactNode; gap?: number; style?: StyleProp<ViewStyle>; wrap?: boolean }) {
  return <View style={[{ flexDirection: 'row', alignItems: 'center', gap, flexWrap: wrap ? 'wrap' : 'nowrap' }, style]}>{children}</View>;
}

export function Divider({ inset = 0 }: { inset?: number }) {
  const p = usePalette();
  return <View style={{ height: StyleSheet.hairlineWidth, backgroundColor: p.border, marginLeft: inset }} />;
}

export function KeyValue({ label, value, strong, color }: { label: string; value: React.ReactNode; strong?: boolean; color?: string }) {
  return (
    <Row style={{ justifyContent: 'space-between', paddingVertical: 5 }}>
      <T muted size={14}>{label}</T>
      {typeof value === 'string' || typeof value === 'number' ? (
        <T size={strong ? 16 : 14} weight={strong ? '800' : '600'} color={color} style={{ flexShrink: 1, textAlign: 'right', marginLeft: 12 }}>{value}</T>
      ) : (
        value
      )}
    </Row>
  );
}

// ------------------------------------------------------------------ list rows

export function ListItem({ icon, iconColor, title, subtitle, right, onPress, chevron = Boolean(onPress), avatar, meta, style }: {
  icon?: IconName; iconColor?: string; title: React.ReactNode; subtitle?: React.ReactNode; right?: React.ReactNode; onPress?: () => void; chevron?: boolean; avatar?: string; meta?: React.ReactNode; style?: StyleProp<ViewStyle>;
}) {
  const p = usePalette();
  return (
    <Pressable onPress={onPress} disabled={!onPress} style={({ pressed }) => [{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, paddingHorizontal: 14, backgroundColor: pressed ? p.cardAlt : 'transparent' }, style]}>
      {avatar !== undefined ? <Avatar name={avatar} /> : null}
      {icon ? (
        <View style={{ width: 38, height: 38, borderRadius: 11, backgroundColor: (iconColor ?? p.primary) + '18', alignItems: 'center', justifyContent: 'center' }}>
          <Icon name={icon} size={18} color={iconColor ?? (p.dark ? '#c7cbf0' : p.primary)} />
        </View>
      ) : null}
      <View style={{ flex: 1, gap: 2 }}>
        {typeof title === 'string' ? <T weight="700" numberOfLines={1}>{title}</T> : title}
        {subtitle ? (typeof subtitle === 'string' ? <T muted size={13} numberOfLines={2}>{subtitle}</T> : subtitle) : null}
        {meta}
      </View>
      {right}
      {chevron ? <Icon name="chevron-right" size={18} color={p.faint} /> : null}
    </Pressable>
  );
}

export function Avatar({ name, size = 38, color }: { name: string; size?: number; color?: string }) {
  const p = usePalette();
  return (
    <View style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: color ?? (p.dark ? '#262c78' : '#eef0fb'), alignItems: 'center', justifyContent: 'center' }}>
      <Text style={{ color: p.dark ? '#fff' : '#1c205c', fontWeight: '800', fontSize: size * 0.36 }}>{initials(name) || '?'}</Text>
    </View>
  );
}

// ------------------------------------------------------------------ controls

type ButtonVariant = 'primary' | 'accent' | 'secondary' | 'danger' | 'ghost' | 'success';

export function Button({ title, onPress, variant = 'primary', icon, loading, disabled, small, style, flex }: {
  title: string; onPress?: () => void; variant?: ButtonVariant; icon?: IconName; loading?: boolean; disabled?: boolean; small?: boolean; style?: StyleProp<ViewStyle>; flex?: boolean;
}) {
  const p = usePalette();
  const colors: Record<ButtonVariant, { bg: string; fg: string; border?: string }> = {
    primary: { bg: p.primary, fg: p.primaryText },
    accent: { bg: p.accent, fg: '#fff' },
    success: { bg: p.success, fg: '#fff' },
    danger: { bg: p.danger + '14', fg: p.danger, border: p.danger + '40' },
    secondary: { bg: p.card, fg: p.text, border: p.border },
    ghost: { bg: 'transparent', fg: p.text },
  };
  const c = colors[variant];
  return (
    <Pressable
      onPress={() => {
        if (loading || disabled) return;
        Haptics.selectionAsync().catch(() => undefined);
        onPress?.();
      }}
      style={({ pressed }) => [
        {
          flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8,
          backgroundColor: c.bg, borderRadius: radius.md, borderWidth: c.border ? 1 : 0, borderColor: c.border,
          paddingVertical: small ? 8 : 13, paddingHorizontal: small ? 12 : 18, minHeight: small ? 36 : 48,
          opacity: disabled ? 0.45 : pressed ? 0.85 : 1,
        },
        flex && { flex: 1 },
        style,
      ]}
    >
      {loading ? <ActivityIndicator color={c.fg} size="small" /> : icon ? <Feather name={icon} size={small ? 15 : 17} color={c.fg} /> : null}
      <Text style={{ color: c.fg, fontWeight: '700', fontSize: small ? 13 : 15 }} numberOfLines={1}>{title}</Text>
    </Pressable>
  );
}

export function IconButton({ icon, onPress, color, bg, size = 40, badge }: { icon: IconName; onPress: () => void; color?: string; bg?: string; size?: number; badge?: number }) {
  const p = usePalette();
  return (
    <Pressable onPress={onPress} hitSlop={6} style={({ pressed }) => ({ width: size, height: size, borderRadius: size / 2, alignItems: 'center', justifyContent: 'center', backgroundColor: bg ?? 'transparent', opacity: pressed ? 0.6 : 1 })}>
      <Feather name={icon} size={20} color={color ?? p.text} />
      {badge ? (
        <View style={{ position: 'absolute', top: 4, right: 4, minWidth: 16, height: 16, borderRadius: 8, backgroundColor: '#ef1c25', alignItems: 'center', justifyContent: 'center', paddingHorizontal: 3 }}>
          <Text style={{ color: '#fff', fontSize: 10, fontWeight: '800' }}>{badge > 99 ? '99+' : badge}</Text>
        </View>
      ) : null}
    </Pressable>
  );
}

export function Badge({ label, color, solid }: { label: string; color?: string; solid?: boolean }) {
  const p = usePalette();
  const c = color ?? p.muted;
  return (
    <View style={{ alignSelf: 'flex-start', flexDirection: 'row', alignItems: 'center', gap: 5, paddingHorizontal: 8, paddingVertical: 3, borderRadius: radius.pill, backgroundColor: solid ? c : c + '1f' }}>
      {!solid ? <View style={{ width: 6, height: 6, borderRadius: 3, backgroundColor: c }} /> : null}
      <Text style={{ color: solid ? '#fff' : c, fontSize: 11.5, fontWeight: '700' }}>{label}</Text>
    </View>
  );
}

export const docStatusColor: Record<string, string> = {
  draft: '#94a3b8', sent: '#3b82f6', accepted: '#10b981', declined: '#ef4444', expired: '#64748b', converted: '#8b5cf6',
  unpaid: '#f59e0b', partial: '#f97316', paid: '#10b981', overdue: '#ef4444', void: '#64748b',
};

export function StatusBadge({ status }: { status: string }) {
  return <Badge label={status.charAt(0).toUpperCase() + status.slice(1).replace(/_/g, ' ')} color={docStatusColor[status] ?? '#64748b'} />;
}

export function Field({ label, hint, error, style, icon, ...props }: TextInputProps & { label?: string; hint?: string; error?: string; icon?: IconName }) {
  const p = usePalette();
  const [focused, setFocused] = useState(false);
  return (
    <View style={[{ gap: 6 }, style as StyleProp<ViewStyle>]}>
      {label ? <T size={13} weight="700" muted>{label}</T> : null}
      <View style={{ flexDirection: 'row', alignItems: props.multiline ? 'flex-start' : 'center', gap: 8, backgroundColor: p.input, borderWidth: 1, borderColor: error ? p.danger : focused ? (p.dark ? '#525bb5' : '#1c205c') : p.border, borderRadius: radius.md, paddingHorizontal: 12 }}>
        {icon ? <Feather name={icon} size={17} color={p.faint} style={{ marginTop: props.multiline ? 13 : 0 }} /> : null}
        <TextInput
          placeholderTextColor={p.faint}
          {...props}
          onFocus={(e) => { setFocused(true); props.onFocus?.(e); }}
          onBlur={(e) => { setFocused(false); props.onBlur?.(e); }}
          style={{ flex: 1, color: p.text, fontSize: 15, paddingVertical: 12, minHeight: props.multiline ? 90 : 46, textAlignVertical: props.multiline ? 'top' : 'center' }}
        />
      </View>
      {error ? <T size={12} color={p.danger}>{error}</T> : hint ? <T size={12} muted>{hint}</T> : null}
    </View>
  );
}

export function SearchBar({ value, onChange, placeholder = 'Search', right }: { value: string; onChange: (v: string) => void; placeholder?: string; right?: React.ReactNode }) {
  const p = usePalette();
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: p.card, borderRadius: radius.md, borderWidth: StyleSheet.hairlineWidth, borderColor: p.border, paddingHorizontal: 12, height: 46 }}>
      <Feather name="search" size={17} color={p.faint} />
      <TextInput value={value} onChangeText={onChange} placeholder={placeholder} placeholderTextColor={p.faint} style={{ flex: 1, color: p.text, fontSize: 15 }} autoCorrect={false} returnKeyType="search" clearButtonMode="while-editing" />
      {right}
    </View>
  );
}

export function Segmented<T extends string>({ options, value, onChange, scroll }: { options: { value: T; label: string; count?: number }[]; value: T; onChange: (v: T) => void; scroll?: boolean }) {
  const p = usePalette();
  const inner = options.map((o) => {
    const on = o.value === value;
    return (
      <Pressable
        key={o.value}
        onPress={() => { Haptics.selectionAsync().catch(() => undefined); onChange(o.value); }}
        style={{ flex: scroll ? undefined : 1, paddingVertical: 8, paddingHorizontal: 14, borderRadius: radius.pill, backgroundColor: on ? p.primary : 'transparent', alignItems: 'center', flexDirection: 'row', justifyContent: 'center', gap: 6 }}
      >
        <Text style={{ color: on ? p.primaryText : p.muted, fontWeight: '700', fontSize: 13 }}>{o.label}</Text>
        {o.count !== undefined ? <Text style={{ color: on ? p.primaryText : p.faint, fontWeight: '700', fontSize: 12 }}>{o.count}</Text> : null}
      </Pressable>
    );
  });
  const wrap: ViewStyle = { flexDirection: 'row', backgroundColor: p.card, borderRadius: radius.pill, padding: 4, borderWidth: StyleSheet.hairlineWidth, borderColor: p.border };
  return scroll ? (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={[wrap, { gap: 2 }]} style={{ flexGrow: 0 }}>{inner}</ScrollView>
  ) : (
    <View style={wrap}>{inner}</View>
  );
}

export function Chip({ label, selected, onPress, icon, color }: { label: string; selected?: boolean; onPress?: () => void; icon?: IconName; color?: string }) {
  const p = usePalette();
  const c = color ?? p.primary;
  return (
    <Pressable onPress={onPress} style={({ pressed }) => ({ flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 12, paddingVertical: 8, borderRadius: radius.pill, borderWidth: 1, borderColor: selected ? c : p.border, backgroundColor: selected ? c : p.card, opacity: pressed ? 0.8 : 1 })}>
      {icon ? <Feather name={icon} size={14} color={selected ? (c === p.primary ? p.primaryText : '#fff') : p.muted} /> : null}
      <Text style={{ color: selected ? (c === p.primary ? p.primaryText : '#fff') : p.text, fontWeight: '600', fontSize: 13 }}>{label}</Text>
    </Pressable>
  );
}

// ------------------------------------------------------------------ states

export function Loading({ label }: { label?: string }) {
  const p = usePalette();
  return (
    <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', padding: 40, gap: 12, backgroundColor: p.bg }}>
      <ActivityIndicator color={p.dark ? '#fff' : '#1c205c'} />
      {label ? <T muted>{label}</T> : null}
    </View>
  );
}

export function Empty({ icon = 'inbox', title, message, action }: { icon?: IconName; title: string; message?: string; action?: { label: string; onPress: () => void } }) {
  const p = usePalette();
  return (
    <View style={{ alignItems: 'center', padding: 32, gap: 10 }}>
      <View style={{ width: 64, height: 64, borderRadius: 20, backgroundColor: p.card, borderWidth: StyleSheet.hairlineWidth, borderColor: p.border, alignItems: 'center', justifyContent: 'center' }}>
        <Feather name={icon} size={26} color={p.faint} />
      </View>
      <T weight="800" size={16} center>{title}</T>
      {message ? <T muted center size={14}>{message}</T> : null}
      {action ? <Button title={action.label} onPress={action.onPress} small variant="secondary" /> : null}
    </View>
  );
}

export function ErrorView({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
  const p = usePalette();
  return (
    <View style={{ flex: 1, backgroundColor: p.bg, justifyContent: 'center' }}>
      <Empty icon="wifi-off" title="Could not load" message={(error as Error)?.message ?? 'Something went wrong.'} action={onRetry ? { label: 'Try again', onPress: onRetry } : undefined} />
    </View>
  );
}

// ------------------------------------------------------------------ sheet

export function Sheet({ visible, onClose, title, children, footer }: { visible: boolean; onClose: () => void; title: string; children: React.ReactNode; footer?: React.ReactNode }) {
  const p = usePalette();
  const insets = useSafeAreaInsets();
  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose} statusBarTranslucent>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={{ flex: 1 }}>
        <Pressable style={{ flex: 1, backgroundColor: 'rgba(6,7,28,0.5)' }} onPress={onClose} />
        <View style={{ backgroundColor: p.bg, borderTopLeftRadius: 24, borderTopRightRadius: 24, maxHeight: '88%', paddingBottom: insets.bottom + 12 }}>
          <View style={{ alignItems: 'center', paddingTop: 8 }}>
            <View style={{ width: 40, height: 4, borderRadius: 2, backgroundColor: p.border }} />
          </View>
          <Row style={{ justifyContent: 'space-between', paddingHorizontal: 18, paddingVertical: 10 }}>
            <T size={18} weight="800">{title}</T>
            <IconButton icon="x" onPress={onClose} size={34} bg={p.card} />
          </Row>
          <ScrollView contentContainerStyle={{ paddingHorizontal: 18, paddingBottom: 12, gap: 12 }} keyboardShouldPersistTaps="handled">
            {children}
          </ScrollView>
          {footer ? <View style={{ paddingHorizontal: 18, paddingTop: 8, flexDirection: 'row', gap: 10 }}>{footer}</View> : null}
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}

// ------------------------------------------------------------------ toast

type ToastKind = 'success' | 'error' | 'info';
const ToastContext = createContext<(message: string, kind?: ToastKind) => void>(() => undefined);

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [toast, setToast] = useState<{ message: string; kind: ToastKind; key: number } | null>(null);
  const [anim] = useState(() => new Animated.Value(0));
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const insets = useSafeAreaInsets();

  const show = useCallback((message: string, kind: ToastKind = 'success') => {
    if (kind === 'success') Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => undefined);
    if (kind === 'error') Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error).catch(() => undefined);
    setToast({ message, kind, key: Date.now() });
  }, []);

  useEffect(() => {
    if (!toast) return;
    anim.setValue(0);
    Animated.spring(anim, { toValue: 1, useNativeDriver: true, friction: 8 }).start();
    if (timer.current) clearTimeout(timer.current);
    timer.current = setTimeout(() => {
      Animated.timing(anim, { toValue: 0, duration: 200, useNativeDriver: true }).start(() => setToast(null));
    }, toast.kind === 'error' ? 4200 : 2600);
  }, [toast, anim]);

  const bg = toast?.kind === 'error' ? '#b91c1c' : toast?.kind === 'info' ? '#1c205c' : '#065f46';
  const icon: IconName = toast?.kind === 'error' ? 'alert-circle' : toast?.kind === 'info' ? 'info' : 'check-circle';
  return (
    <ToastContext.Provider value={show}>
      {children}
      {toast ? (
        <Animated.View
          pointerEvents="none"
          style={{ position: 'absolute', left: 16, right: 16, top: insets.top + 8, opacity: anim, transform: [{ translateY: anim.interpolate({ inputRange: [0, 1], outputRange: [-30, 0] }) }] }}
        >
          <View style={{ backgroundColor: bg, borderRadius: 14, paddingVertical: 12, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', gap: 10, shadowColor: '#000', shadowOpacity: 0.2, shadowRadius: 12, elevation: 6 }}>
            <Feather name={icon} size={18} color="#fff" />
            <Text style={{ color: '#fff', fontWeight: '600', flex: 1 }}>{toast.message}</Text>
          </View>
        </Animated.View>
      ) : null}
    </ToastContext.Provider>
  );
}

export function useToast() {
  return useContext(ToastContext);
}

export function useStyles<T>(factory: (p: Palette) => T): T {
  const p = usePalette();
  return React.useMemo(() => factory(p), [p, factory]);
}
