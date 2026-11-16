/* Wheelsturn GMS – front-end helpers (Alpine components, CSRF fetch, charts) */
(function () {
  'use strict';

  const csrf = () => ({
    name: document.querySelector('meta[name="csrf-name"]')?.content,
    hash: document.querySelector('meta[name="csrf-token"]')?.content,
  });

  /** POST helper that carries the CSRF token and refreshes it from the response. */
  window.wtPost = async function (url, data = {}) {
    const body = new FormData();
    const c = csrf();
    if (c.name) body.append(c.name, c.hash);
    Object.entries(data).forEach(([k, v]) => {
      if (Array.isArray(v)) v.forEach((x) => body.append(k + '[]', x));
      else body.append(k, v ?? '');
    });
    const res = await fetch(url, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const token = res.headers.get('X-CSRF-TOKEN');
    if (token) document.querySelector('meta[name="csrf-token"]').content = token;
    let json = null;
    try { json = await res.json(); } catch (e) { /* not JSON */ }
    if (json && json.csrf) document.querySelector('meta[name="csrf-token"]').content = json.csrf;
    if (!res.ok) throw new Error((json && (json.error || json.message)) || 'Request failed');
    return json;
  };

  window.toast = (message, type = 'success') => window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));

  window.money = (n) => 'R ' + Number(n || 0).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/,/g, ' ');

  document.addEventListener('alpine:init', () => {
    Alpine.data('toasts', (initial = []) => ({
      items: [],
      init() { initial.forEach((t) => this.push(t)); },
      push(t) {
        const item = { id: Date.now() + Math.random(), show: true, ...t };
        this.items.push(item);
        setTimeout(() => { item.show = false; }, t.type === 'error' ? 7000 : 4000);
      },
    }));

    /**
     * Line-item editor shared by job cards, quotes and invoices.
     * Prices are VAT-exclusive; VAT is calculated per line.
     */
    Alpine.data('lineItems', (initial = [], opts = {}) => ({
      lines: [],
      vatOn: opts.vatOn ?? true,
      vatRate: opts.vatRate ?? 15,
      labourRate: opts.labourRate ?? 0,
      purchase: opts.mode === 'purchase',
      types: opts.types || {},
      init() {
        this.lines = (initial.length ? initial : []).map((l) => this.normalise(l));
        if (!this.lines.length) this.add('labour');
      },
      normalise(l) {
        return {
          key: Math.random().toString(36).slice(2),
          item_type: l.item_type || 'part', product_id: l.product_id || '', description: l.description || '',
          quantity: Number(l.quantity ?? 1), unit_price: Number(l.unit_price ?? 0), unit_cost: Number(l.unit_cost ?? 0),
          discount_percent: Number(l.discount_percent ?? 0), vat_rate: Number(l.vat_rate ?? this.vatRate), mechanic_id: l.mechanic_id || '', account_id: l.account_id || '', warranty_months: l.warranty_months ?? '',
        };
      },
      add(type = 'part') {
        this.lines.push(this.normalise({ item_type: type, unit_price: type === 'labour' && !this.purchase ? this.labourRate : 0 }));
        this.$nextTick(() => { const els = this.$root.querySelectorAll('[data-desc]'); els[els.length - 1]?.focus(); });
      },
      remove(i) { this.lines.splice(i, 1); },
      async loadPackage(id) {
        if (!id) return;
        const r = await fetch(window.WT_BASE + 'canned-jobs/' + id + '/lines', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const rows = await r.json();
        if (this.lines.length === 1 && !this.lines[0].description) this.lines = [];
        rows.forEach((l) => this.lines.push(this.normalise(l)));
        toast(rows.length + ' lines added from package');
      },
      duplicate(i) { this.lines.splice(i + 1, 0, this.normalise({ ...this.lines[i] })); },
      move(i, d) { const j = i + d; if (j < 0 || j >= this.lines.length) return; [this.lines[i], this.lines[j]] = [this.lines[j], this.lines[i]]; },
      net(l) { const s = l.quantity * l.unit_price; return s - (s * l.discount_percent / 100); },
      vat(l) { return this.vatOn ? Math.round(this.net(l) * l.vat_rate) / 100 : 0; },
      total(l) { return this.net(l) + this.vat(l); },
      get subtotal() { return this.lines.reduce((a, l) => a + l.quantity * l.unit_price, 0); },
      get discount() { return this.lines.reduce((a, l) => a + (l.quantity * l.unit_price * l.discount_percent / 100), 0); },
      get vatTotal() { return this.lines.reduce((a, l) => a + this.vat(l), 0); },
      get grand() { return this.subtotal - this.discount + this.vatTotal; },
      get profit() { return this.lines.reduce((a, l) => a + this.net(l) - l.quantity * l.unit_cost, 0); },
      async search(l, q) {
        if (!q || q.length < 2) { l.results = []; return; }
        const r = await fetch(window.WT_BASE + 'inventory/lookup?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        l.results = await r.json();
      },
      pick(l, p) {
        Object.assign(l, {
          product_id: p.id, description: p.name + (p.sku ? ' (' + p.sku + ')' : ''), unit_price: Number(this.purchase ? p.cost_price : p.sell_price),
          unit_cost: Number(p.cost_price), item_type: p.type, vat_rate: Number(p.vat_rate), warranty_months: p.warranty_months ?? '', results: [],
        });
      },
    }));

    /** Dropzone-style uploader for slips, photos and documents. */
    Alpine.data('uploader', (url, entityType, entityId) => ({
      dragging: false, uploading: false, progress: 0, category: 'document',
      async send(files) {
        if (!files.length) return;
        this.uploading = true;
        for (const f of files) {
          const fd = new FormData();
          const c = csrf();
          fd.append(c.name, c.hash);
          fd.append('file', f);
          fd.append('entity_type', entityType);
          fd.append('entity_id', entityId);
          fd.append('category', this.category);
          try {
            const res = await fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (json.csrf) document.querySelector('meta[name="csrf-token"]').content = json.csrf;
            if (!res.ok) throw new Error(json.error || 'Upload failed');
            toast(f.name + ' uploaded');
          } catch (e) { toast(e.message, 'error'); }
        }
        this.uploading = false;
        setTimeout(() => window.location.reload(), 600);
      },
    }));
  });

  // Theme store (attached to <html>)
  window.themeStore = () => ({
    dark: document.documentElement.classList.contains('dark'),
    toggle() {
      this.dark = !this.dark;
      try { localStorage.setItem('wt-theme', this.dark ? 'dark' : 'light'); } catch (e) {}
      window.dispatchEvent(new CustomEvent('theme-changed'));
    },
  });

  // Confirm before destructive form submits: <form data-confirm="Are you sure?">
  document.addEventListener('submit', (e) => {
    const msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Enhanced selects: <select data-select> (searchable) / data-remote="url" (ajax)
  function initSelects(root = document) {
    if (!window.TomSelect) return;
    root.querySelectorAll('select[data-select]:not(.tomselected)').forEach((el) => {
      const remote = el.getAttribute('data-remote');
      const cfg = { allowEmptyOption: true, maxOptions: 200, plugins: el.multiple ? ['remove_button'] : [] };
      if (remote) {
        Object.assign(cfg, {
          valueField: 'id', labelField: 'text', searchField: ['text'], preload: 'focus',
          load(q, cb) { fetch(remote + (remote.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then((r) => r.json()).then(cb).catch(() => cb()); },
        });
      }
      new TomSelect(el, cfg);
    });
  }
  document.addEventListener('DOMContentLoaded', () => initSelects());

  // ---------------------------------------------------------------- voice-to-text
  // Adds a microphone button to every textarea (and inputs marked data-voice) in supporting browsers.
  function initVoice(root = document) {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) return;
    root.querySelectorAll('textarea:not([data-no-voice]):not([data-voice-ready]), input[data-voice]:not([data-voice-ready])').forEach((el) => {
      el.dataset.voiceReady = '1';
      const wrap = document.createElement('div');
      wrap.className = 'relative';
      el.parentNode.insertBefore(wrap, el);
      wrap.appendChild(el);
      el.style.paddingRight = '2.5rem';
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.title = 'Dictate (voice to text)';
      btn.className = 'absolute right-2 top-2 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-crimson-700 dark:hover:bg-white/10';
      btn.innerHTML = '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v5"/></svg>';
      let rec = null;
      btn.addEventListener('click', () => {
        if (rec) { rec.stop(); return; }
        rec = new SR();
        rec.lang = 'en-ZA';
        rec.interimResults = false;
        rec.continuous = true;
        btn.classList.add('text-white', 'bg-red-600', 'animate-pulse');
        rec.onresult = (e) => {
          const text = Array.from(e.results).slice(e.resultIndex).map((r) => r[0].transcript).join(' ').trim();
          if (text) { el.value = (el.value ? el.value.replace(/\s*$/, ' ') : '') + text.charAt(0).toUpperCase() + text.slice(1); el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); }
        };
        rec.onend = () => { btn.classList.remove('text-white', 'bg-red-600', 'animate-pulse'); rec = null; };
        rec.onerror = (e) => { if (e.error !== 'aborted') toast('Voice input: ' + e.error, 'error'); };
        rec.start();
      });
      wrap.appendChild(btn);
    });
  }
  document.addEventListener('DOMContentLoaded', () => initVoice());

  // ---------------------------------------------------------------- barcode scanner (camera)
  // Usage: <button @click="$dispatch('scan', { target: 'inputId' })">. Uses the browser BarcodeDetector API
  // (Chrome / Android / Edge); falls back to typing / USB scanners, which act as keyboards.
  window.wtScanner = () => ({
    open: false, stream: null, target: null, supported: 'BarcodeDetector' in window, error: '',
    async start(detail) {
      this.target = detail.target; this.open = true; this.error = '';
      if (!this.supported) { this.error = 'Camera scanning is not supported in this browser. Use a USB scanner or type the code.'; return; }
      try {
        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        const video = this.$refs.video; video.srcObject = this.stream; await video.play();
        const detector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e', 'qr_code'] });
        const tick = async () => {
          if (!this.open) return;
          try { const codes = await detector.detect(video); if (codes.length) { this.found(codes[0].rawValue); return; } } catch (e) {}
          requestAnimationFrame(tick);
        };
        tick();
      } catch (e) { this.error = 'Camera unavailable: ' + e.message; }
    },
    found(code) {
      const el = document.getElementById(this.target);
      if (el) { el.value = code; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new CustomEvent('scanned', { detail: code, bubbles: true })); }
      if (navigator.vibrate) navigator.vibrate(80);
      this.stop();
    },
    stop() { this.open = false; if (this.stream) this.stream.getTracks().forEach((t) => t.stop()); this.stream = null; },
  });
  window.initVoice = initVoice;
  window.initSelects = initSelects;

  // ---------------------------------------------------------------- charts
  window.WT_COLORS = ['#9b1631', '#1c205c', '#ef1c25', '#525bb5', '#f59e0b', '#10b981', '#8b5cf6', '#06b6d4', '#64748b', '#ec4899'];

  window.wtChartDefaults = function () {
    if (!window.Chart) return;
    const dark = document.documentElement.classList.contains('dark');
    Chart.defaults.font.family = '"Plus Jakarta Sans", Inter, system-ui, sans-serif';
    Chart.defaults.font.size = 11;
    Chart.defaults.color = dark ? '#94a3b8' : '#64748b';
    Chart.defaults.borderColor = dark ? 'rgba(255,255,255,.06)' : 'rgba(15,23,42,.06)';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.tooltip.backgroundColor = '#0b0d2e';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 10;
    Chart.defaults.maintainAspectRatio = false;
  };

  window.wtMoneyTick = (v) => 'R' + (Math.abs(v) >= 1000 ? (v / 1000).toFixed(0) + 'k' : v);
})();
