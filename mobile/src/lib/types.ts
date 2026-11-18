/** Response shapes of the Wheelsturn /api/v1 endpoints. */

export type Mode = 'manager' | 'technician' | 'customer';

export type Me = {
  user: { id: number; first_name: string; last_name: string; email: string; phone: string | null; role: string; role_label: string; customer_id: number | null; two_factor: boolean };
  mode: Mode;
  can: Record<string, boolean>;
  company: { name: string; phone: string; email: string; address: string; logo: string; web: string; booking_url: string };
  ai: boolean;
  unread: number;
};

export type JobRow = {
  id: number;
  number: string;
  status: string;
  status_label: string;
  status_color: string;
  priority: string;
  job_type: string | null;
  bay: string | null;
  registration: string;
  vehicle: string;
  customer: string | null;
  complaint: string | null;
  booked_for: string | null;
  promised_at: string | null;
  late: boolean;
  total: number | null;
  mechanics: { id: number; name: string }[] | string[];
  running: boolean;
};

export type Paged<T> = { items: T[]; page?: number; more?: boolean };

export type LineItem = { id: number; type: string; description: string; quantity: number; unit_price: number | null; total: number | null; mechanic: string | null };
export type Inspection = { id: number; section: string; item: string; status: 'ok' | 'advisory' | 'urgent' | 'na' | null; note: string | null; estimate: number; decision: string | null };
export type FileRow = { id: number; url: string; name: string; mime: string; category: string; is_video: boolean; is_image: boolean; created_at: string };
export type DamageMark = { type: 'scratch' | 'dent' | 'chip' | 'crack' | 'rust' | 'other'; note: string };

export type JobDetail = {
  id: number;
  number: string;
  status: string;
  status_label: string;
  status_color: string;
  statuses: { value: string; label: string; color: string }[];
  priority: string;
  job_type: string | null;
  bay: string | null;
  fuel_level: string | null;
  odometer_in: number | null;
  booked_for: string | null;
  promised_at: string | null;
  checked_in_at: string | null;
  completed_at: string | null;
  customer_complaint: string | null;
  diagnosis: string | null;
  work_performed: string | null;
  recommendations: string | null;
  internal_notes: string | null;
  vehicle: { id: number; registration: string; label: string; vin: string | null; colour: string | null; engine: string; transmission: string | null } | null;
  customer: { id?: number; name: string; phone?: string | null; email?: string | null };
  mechanics: { id: number; name: string }[];
  items: LineItem[];
  totals: { subtotal: number; vat: number; total: number } | null;
  inspections: Inspection[];
  checklist: { id: number; item: string; done: boolean }[];
  damage: Record<string, DamageMark> | [];
  files: FileRow[];
  time: { logs: { id: number; started_at: string; ended_at: string | null; minutes: number | null; first_name: string }[]; total_minutes: number; running_since: string | null };
  signed: boolean;
  signed_at: string | null;
  invoice: { id: number; number: string; balance: number; status: string } | null;
  costing: { revenue: number; labour_cost: number; parts_cost: number; profit: number; margin: number; hours_clocked: number; hours_sold: number; efficiency: number | null } | null;
  tracker_url: string;
  pdf_url: string;
  can: { edit_prices: boolean; invoice: boolean; ai: boolean; add_lines: boolean; remove_lines: boolean };
};

export type Kpi = { key: string; label: string; value: number; money: boolean; delta?: number | null; hint?: string };
export type Dashboard = {
  period: string;
  periods: Record<string, string>;
  from: string;
  to: string;
  kpis: Kpi[];
  margin: number;
  series: { labels: string[]; revenue: number[]; collected: number[]; expenses: number[] };
  mix: { label: string; value: number }[];
  methods: { label: string; value: number; count: number }[];
  pipeline: { key: string; label: string; value: number; color: string }[];
  technicians: { name: string; jobs: number; hours_worked: number; hours_sold: number; efficiency: number; revenue: number }[];
  target: { target: number; mtd: number; day_pct: number } | null;
  pending_bookings: number;
  low_stock: number;
};

export type DocLine = { description: string; type?: string; quantity: number; unit_price?: number; total: number };

export type InvoiceRow = { id: number; number: string; status: string; date: string; due: string; total: number; balance: number; customer?: string; registration: string | null; pay_url?: string; pdf_url?: string };
export type MyQuoteRow = { id: number; number: string; status: string; date: string; expires: string; total: number; registration: string | null; url: string; pdf_url: string };
export type InvoiceDetail = {
  id: number;
  number: string;
  status: string;
  date: string;
  due: string;
  customer: { id: number; name: string; phone: string | null; email: string | null };
  vehicle: { registration: string; label: string } | null;
  items: DocLine[];
  totals: { subtotal: number; vat: number; total: number; paid: number; credited: number; balance: number };
  payments: { number: string; date: string; amount: number; method: string }[];
  pay_url: string;
  pdf_url: string;
  whatsapp: string | null;
  methods: Record<string, string>;
};

export type QuoteRow = { id: number; number: string; status: string; date: string; expires: string; total: number; customer?: string; registration: string | null };
export type QuoteDetail = {
  id: number;
  number: string;
  status: string;
  date: string;
  expires: string;
  notes: string | null;
  customer: { id: number; name: string; phone: string | null; email: string | null };
  vehicle: { registration: string; label: string } | null;
  items: DocLine[];
  totals: { subtotal: number; vat: number; total: number };
  public_url: string;
  pdf_url: string;
  job_card_id: number | null;
};

export type CustomerRow = { id: number; name: string; phone: string | null; email: string | null; type: string; vehicles: number; balance: number };
export type CustomerDetail = {
  id: number;
  name: string;
  type: string;
  phone: string | null;
  email: string | null;
  vat_number: string | null;
  address: string;
  notes: string | null;
  balance: number;
  vehicles: { id: number; registration: string; label: string; odometer: number | null; next_service_date: string | null }[];
  jobs: { id: number; number: string; status: string; status_label: string; status_color: string; date: string; registration: string; total: number }[];
  invoices: { id: number; number: string; date: string; total: number; balance: number; status: string }[];
};

export type VehicleRow = { id: number; registration: string; label: string; owner: string; customer_id: number; odometer: number | null };
export type VehicleDetail = {
  id: number;
  registration: string;
  label: string;
  vin: string | null;
  variant: string | null;
  colour: string | null;
  engine: string;
  transmission: string | null;
  odometer: number | null;
  licence_expiry: string | null;
  next_service_date: string | null;
  next_service_km: number | null;
  owner: { id: number; name: string; phone: string | null };
  history: { id: number; number: string; date: string; odometer: number | null; status_label: string; complaint: string | null; work: string[]; total: number }[];
};

export type Product = { id: number; sku: string; barcode: string | null; name: string; type: string; category: string | null; price: number | null; stock: number | null; low: boolean; bin: string | null };

export type Notification = { id: number; title: string; body: string; url: string | null; icon: string | null; date: string; unread: boolean };

export type Task = { id: number; title: string; notes: string | null; due_at: string | null; priority: string; overdue: boolean; entity_type: string | null; entity_id: number | null };

// Customer app
export type MyOverview = {
  vehicles: { id: number; registration: string; label: string; next_service_date: string | null; licence_expiry: string | null; odometer: number | null }[];
  active_jobs: { id: number; number: string; status: string; status_label: string; status_color: string; registration: string; vehicle: string; promised_at: string | null }[];
  approvals_waiting: number;
  amount_owed: number;
  open_quotes: number;
};

export type MyJob = {
  id: number;
  number: string;
  status: string;
  status_label: string;
  status_color: string;
  promised_at: string | null;
  complaint: string | null;
  work_performed: string | null;
  recommendations: string | null;
  vehicle: { registration: string; label: string };
  steps: { key: string; label: string }[];
  step_index: number;
  findings: { id: number; item: string; note: string | null; severity: 'advisory' | 'urgent'; estimate: number; decision: string | null }[];
  can_decide: boolean;
  photos: { id: number; url: string; is_video: boolean }[];
  timeline: { description: string; created_at: string }[];
  invoice: { id: number; number: string; total: number; balance: number; status: string; pay_url: string } | null;
  tracker_url: string;
  vat_rate: number;
};

export type MyQuote = {
  id: number;
  number: string;
  status: string;
  date: string;
  expires: string;
  notes: string | null;
  vehicle: string | null;
  items: { description: string; quantity: number; total: number }[];
  totals: { subtotal: number; vat: number; total: number };
  can_respond: boolean;
  pdf_url: string;
};

export type MyVehicle = {
  id: number;
  registration: string;
  label: string;
  odometer: number | null;
  next_service_date: string | null;
  next_service_km: number | null;
  licence_expiry: string | null;
  history: { id: number; number: string; date: string; odometer: number | null; summary: string | null; status_label: string }[];
};
