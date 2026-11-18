/** South African formatting helpers: R 1 234.56, 17 Nov 2026, 09:30. */

export function money(v: number | string | null | undefined, cents = true): string {
  const n = Number(v ?? 0);
  const fixed = Math.abs(n).toFixed(cents ? 2 : 0);
  const [int, dec] = fixed.split('.');
  const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  return (n < 0 ? '-R ' : 'R ') + grouped + (dec ? '.' + dec : '');
}

export function compactMoney(v: number): string {
  const n = Math.abs(v);
  const sign = v < 0 ? '-' : '';
  if (n >= 1_000_000) return `${sign}R ${(n / 1_000_000).toFixed(1)}m`;
  if (n >= 10_000) return `${sign}R ${Math.round(n / 1000)}k`;
  if (n >= 1_000) return `${sign}R ${(n / 1000).toFixed(1)}k`;
  return money(v, false);
}

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/** Parse "YYYY-MM-DD HH:MM:SS" (server local time) without timezone shifting. */
export function parseDate(s: string | null | undefined): Date | null {
  if (!s) return null;
  const m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/.exec(s);
  if (!m) return null;
  return new Date(+m[1], +m[2] - 1, +m[3], +(m[4] ?? 0), +(m[5] ?? 0), +(m[6] ?? 0));
}

export function fdate(s: string | null | undefined): string {
  const d = parseDate(s);
  return d ? `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}` : '-';
}

export function ftime(s: string | null | undefined): string {
  const d = parseDate(s);
  return d ? `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}` : '';
}

export function fdatetime(s: string | null | undefined): string {
  const d = parseDate(s);
  return d ? `${fdate(s)}, ${ftime(s)}` : '-';
}

export function dayLabel(d: Date): string {
  return `${DAYS[d.getDay()]} ${d.getDate()} ${MONTHS[d.getMonth()]}`;
}

export function ymd(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

export function ago(s: string | null | undefined): string {
  const d = parseDate(s);
  if (!d) return '';
  const sec = Math.max(0, (Date.now() - d.getTime()) / 1000);
  if (sec < 60) return 'just now';
  if (sec < 3600) return `${Math.floor(sec / 60)} min ago`;
  if (sec < 86400) return `${Math.floor(sec / 3600)} h ago`;
  if (sec < 86400 * 7) return `${Math.floor(sec / 86400)} d ago`;
  return fdate(s);
}

export function duration(fromIso: string | null | undefined, now = Date.now()): string {
  const d = parseDate(fromIso);
  if (!d) return '00:00:00';
  const total = Math.max(0, Math.floor((now - d.getTime()) / 1000));
  const h = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  const s = total % 60;
  return [h, m, s].map((x) => String(x).padStart(2, '0')).join(':');
}

export function minutesLabel(min: number): string {
  const h = Math.floor(min / 60);
  const m = Math.round(min % 60);
  return h ? `${h}h ${m}m` : `${m}m`;
}

export function initials(name: string | null | undefined): string {
  return (name ?? '')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]!.toUpperCase())
    .join('');
}

export function km(v: number | null | undefined): string {
  return v ? `${String(Math.round(v)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ')} km` : '-';
}
