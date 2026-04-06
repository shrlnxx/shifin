/**
 * SHIFFIN - Main Application (Core + Dashboard + Cashier + Payment History)
 */
const App = {
    user: null, currentPage: 'dashboard', hijriMonths: [], _depts: [], _cats: [], _ays: [], _hms: [],

    async init() {
        try { const res = await API.me(); this.user = res.data; const hm = await API.getHijriMonths(); this.hijriMonths = hm.data || []; this.showApp(); } catch { this.showLogin(); }
        this.bindEvents();
    },

    bindEvents() {
        document.getElementById('login-form').addEventListener('submit', e => { e.preventDefault(); this.handleLogin(); });
        document.getElementById('logout-btn').addEventListener('click', () => this.handleLogout());
        document.getElementById('sidebar-toggle').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
        document.getElementById('modal-close-btn').addEventListener('click', () => this.closeModal());
        document.getElementById('modal-overlay').addEventListener('click', e => { if (e.target === e.currentTarget) this.closeModal(); });
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', e => { e.preventDefault(); const page = item.dataset.page; if (page) this.navigate(page); document.getElementById('sidebar').classList.remove('open'); });
        });
    },

    async handleLogin() {
        const btn = document.getElementById('login-btn'); const errEl = document.getElementById('login-error');
        btn.disabled = true; errEl.style.display = 'none';
        try { const u = document.getElementById('login-username').value; const p = document.getElementById('login-password').value; const res = await API.login(u, p); this.user = res.data; const hm = await API.getHijriMonths(); this.hijriMonths = hm.data || []; this.showApp(); } catch (err) { errEl.textContent = err.message || 'Login gagal'; errEl.style.display = 'block'; }
        btn.disabled = false;
    },

    async handleLogout() { try { await API.logout(); } catch {} this.user = null; this.showLogin(); },
    showLogin() { document.getElementById('login-screen').style.display = 'flex'; document.getElementById('main-app').style.display = 'none'; },
    showApp() {
        document.getElementById('login-screen').style.display = 'none'; document.getElementById('main-app').style.display = 'flex';
        document.getElementById('sidebar-user-name').textContent = this.user.full_name; document.getElementById('sidebar-user-role').textContent = this.user.role;
        this.applyRoleNav(); this.navigate('dashboard');
    },
    applyRoleNav() {
        const r = this.user.role; const show = (id, v) => { const el = document.getElementById(id); if (el) el.style.display = v ? '' : 'none'; };
        show('nav-cashier', r === 'CASHIER' || r === 'ADMIN'); show('nav-treasurer', r === 'TREASURER' || r === 'ADMIN'); show('nav-admin', r === 'ADMIN');
    },
    navigate(page) {
        this.currentPage = page;
        document.querySelectorAll('.nav-item').forEach(n => n.classList.toggle('active', n.dataset.page === page));
        const titles = { dashboard:'Dashboard', cashier:'Pembayaran Kasir', 'payment-history':'Riwayat Pembayaran', income:'Pemasukan', expense:'Pengeluaran', distribution:'Distribusi Dana', reports:'Laporan Keuangan', students:'Data Santri', 'master-data':'Data Master' };
        document.getElementById('page-title').textContent = titles[page] || page; this.renderPage(page);
    },
    async renderPage(page) {
        const c = document.getElementById('page-content'); c.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        try {
            switch (page) {
                case 'dashboard': await App.dashboard(c); break; case 'cashier': await App.cashier(c); break;
                case 'payment-history': await App.paymentHistory(c); break; case 'income': await App.income(c); break;
                case 'expense': await App.expense(c); break; case 'distribution': await App.distribution(c); break;
                case 'reports': await App.reports(c); break; case 'students': await App.students(c); break;
                case 'master-data': await App.masterData(c); break;
                default: c.innerHTML = '<div class="empty-state"><p>Halaman tidak ditemukan</p></div>';
            }
        } catch (err) { c.innerHTML = `<div class="alert alert-error">Error: ${err.message}</div>`; }
    },

    // Utils
    toast(msg, type = 'success') { const tc = document.getElementById('toast-container'); const t = document.createElement('div'); t.className = `toast toast-${type}`; t.innerHTML = `<span class="material-symbols-rounded">${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info'}</span>${msg}`; tc.appendChild(t); setTimeout(() => t.remove(), 4000); },
    openModal(title, bodyHtml, footerHtml = '') { document.getElementById('modal-title').textContent = title; document.getElementById('modal-body').innerHTML = bodyHtml; document.getElementById('modal-footer').innerHTML = footerHtml; document.getElementById('modal-overlay').style.display = 'flex'; },
    closeModal() { document.getElementById('modal-overlay').style.display = 'none'; },
    fmt(n) { return new Intl.NumberFormat('id-ID').format(n || 0); },
    fmtRp(n) { return 'Rp ' + this.fmt(n); },
    statusBadge(s) { const m = { PAID:['badge-success','Lunas'], PARTIAL:['badge-warning','Sebagian'], UNPAID:['badge-danger','Belum Bayar'], ACTIVE:['badge-success','Aktif'], GRADUATED:['badge-info','Lulus'], DROPPED:['badge-danger','Keluar'] }; const [cls, txt] = m[s] || ['badge-info', s]; return `<span class="badge ${cls}">${txt}</span>`; },
    hijriName(id) { const m = this.hijriMonths.find(h => h.month_id == id); return m ? m.month_name : id; },

    showReceipt(data) {
        const p = data.payment; const d = data.details;
        let rows = d.map(i => `<div class="receipt-row"><span>${i.hijri_month_name} ${i.hijri_year}</span><span>Rp ${this.fmt(i.paid_amount)}</span></div>`).join('');
        document.getElementById('receipt-content').innerHTML = `<h2>${data.pesantren_name || 'Pesantren'}</h2><p class="receipt-sub">${data.pesantren_address || ''}</p><div class="receipt-divider"></div><div class="receipt-row"><span>No:</span><span>PAY-${p.payment_id}</span></div><div class="receipt-row"><span>Tanggal:</span><span>${p.payment_date}</span></div><div class="receipt-row"><span>NIS:</span><span>${p.nis}</span></div><div class="receipt-row"><span>Nama:</span><span>${p.student_name}</span></div><div class="receipt-row"><span>Jenis:</span><span>${p.payment_type}</span></div><div class="receipt-divider"></div>${rows}<div class="receipt-divider"></div><div class="receipt-row receipt-total"><span>TOTAL</span><span>Rp ${this.fmt(p.total_amount)}</span></div><div class="receipt-divider"></div><div class="receipt-row"><span>Kasir:</span><span>${p.cashier_name}</span></div><p style="text-align:center;margin-top:16px;font-size:0.75rem;color:#999">Terima Kasih</p><div style="text-align:center;margin-top:12px"><button onclick="window.print()" style="margin-right:8px;padding:6px 16px;cursor:pointer">Cetak</button><button onclick="document.getElementById('receipt-overlay').style.display='none'" style="padding:6px 16px;cursor:pointer">Tutup</button></div>`;
        document.getElementById('receipt-overlay').style.display = 'flex';
    },

    // ---- DASHBOARD ----
    async dashboard(c) {
        let data; try { const res = await API.getDashboard(); data = res.data; } catch { data = { financial_summary: {}, recent_payments: [], outstanding: {}, department_balances: [], current_period: {} }; }
        const s = data.financial_summary || {}; const cp = data.current_period || {};
        document.getElementById('hijri-period').textContent = App.hijriName(cp.hijri_month) + ' ' + (cp.hijri_year || '');
        c.innerHTML = `<div class="stats-grid"><div class="stat-card balance"><div class="stat-card-header"><span class="label">Saldo Kas Utama</span><div class="icon"><span class="material-symbols-rounded">account_balance_wallet</span></div></div><div class="stat-value">${App.fmtRp(s.kas_balance)}</div><div class="stat-sub">Saldo terkini</div></div><div class="stat-card income"><div class="stat-card-header"><span class="label">Pemasukan Bulan Ini</span><div class="icon"><span class="material-symbols-rounded">trending_up</span></div></div><div class="stat-value">${App.fmtRp(s.total_income)}</div><div class="stat-sub">Periode Gregorian</div></div><div class="stat-card expense"><div class="stat-card-header"><span class="label">Pengeluaran Bulan Ini</span><div class="icon"><span class="material-symbols-rounded">trending_down</span></div></div><div class="stat-value">${App.fmtRp(s.total_expense)}</div><div class="stat-sub">Periode Gregorian</div></div><div class="stat-card warning"><div class="stat-card-header"><span class="label">Tunggakan Total</span><div class="icon"><span class="material-symbols-rounded">warning</span></div></div><div class="stat-value">${App.fmtRp(data.outstanding?.total)}</div><div class="stat-sub">Syahriah: ${App.fmtRp(data.outstanding?.syahriah)}</div></div></div>
        <div class="grid-2"><div class="card"><div class="card-header"><h3><span class="material-symbols-rounded">receipt_long</span>Pembayaran Terakhir</h3></div><div class="table-container"><table><thead><tr><th>Tanggal</th><th>NIS</th><th>Nama</th><th>Jenis</th><th>Jumlah</th></tr></thead><tbody>${(data.recent_payments||[]).map(p=>`<tr><td>${p.payment_date}</td><td>${p.nis}</td><td>${p.student_name}</td><td><span class="badge badge-primary">${p.payment_type}</span></td><td style="font-weight:600">${App.fmtRp(p.total_amount)}</td></tr>`).join('')||'<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">Belum ada data</td></tr>'}</tbody></table></div></div>
        <div class="card"><div class="card-header"><h3><span class="material-symbols-rounded">account_tree</span>Saldo Bidang</h3></div><div class="table-container"><table><thead><tr><th>Bidang</th><th>Tipe</th><th>Saldo</th></tr></thead><tbody>${(data.department_balances||[]).map(d=>`<tr><td>${d.department_name}</td><td><span class="badge ${d.department_type==='REVENUE'?'badge-success':'badge-info'}">${d.department_type}</span></td><td style="font-weight:600">${App.fmtRp(d.balance)}</td></tr>`).join('')||'<tr><td colspan="3" style="text-align:center;color:var(--text-muted)">Belum ada data</td></tr>'}</tbody></table></div></div></div>`;
    },

    // ---- CASHIER ----
    async cashier(c) {
        c.innerHTML = `<div class="grid-sidebar"><div><div class="card"><div class="card-header"><h3><span class="material-symbols-rounded">search</span>Cari Santri</h3></div><div class="search-box"><span class="material-symbols-rounded">search</span><input type="text" id="cashier-search" placeholder="Ketik NIS atau nama santri..."></div><div id="search-results"></div></div></div><div id="cashier-panel"><div class="empty-state"><span class="material-symbols-rounded">point_of_sale</span><p>Pilih santri untuk melihat tagihan</p></div></div></div>`;
        let timer; document.getElementById('cashier-search').addEventListener('input', e => { clearTimeout(timer); timer = setTimeout(async () => { const q = e.target.value.trim(); if (q.length < 2) { document.getElementById('search-results').innerHTML = ''; return; } try { const res = await API.searchStudents(q); const students = res.data || []; document.getElementById('search-results').innerHTML = students.length ? students.map(s => `<div class="bill-checkbox" onclick="App.selectStudent(${s.student_id})" style="cursor:pointer"><div class="bill-info"><div class="bill-period">${s.name}</div><div class="bill-amount">NIS: ${s.nis} · ${s.category_name}</div></div></div>`).join('') : '<p style="color:var(--text-muted);padding:8px">Tidak ditemukan</p>'; } catch {} }, 300); });
    },

    async selectStudent(studentId) {
        const panel = document.getElementById('cashier-panel');
        panel.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        try {
            const res = await API.getStudentBills(studentId);
            const { student, bills, summary } = res.data;
            const unpaid = bills.filter(b => b.status !== 'PAID');
            const types = [...new Set(unpaid.map(b => b.payment_type))];
            const today = new Date().toISOString().slice(0,10);

            panel.innerHTML = `<div class="student-info-panel"><h3>${student.name}</h3><div class="student-meta"><span><span class="material-symbols-rounded" style="font-size:16px">badge</span>NIS: ${student.nis}</span><span><span class="material-symbols-rounded" style="font-size:16px">home</span>${student.student_type}</span><span><span class="material-symbols-rounded" style="font-size:16px">payments</span>${student.category_name} - ${App.fmtRp(student.monthly_fee)}/bln</span><span><span class="material-symbols-rounded" style="font-size:16px">warning</span>Tunggakan: ${App.fmtRp(summary.total_outstanding)}</span></div></div>
            <div class="card"><div class="card-header"><h3><span class="material-symbols-rounded">receipt</span>Tagihan Belum Lunas</h3></div>
            <div class="tabs" id="bill-type-tabs">${types.map((t,i)=>`<button class="tab ${i===0?'active':''}" data-type="${t}" onclick="App.filterBillType(this,'${t}')">${t}</button>`).join('')}</div>
            <div id="bill-list">${this.renderBillList(unpaid, types[0])}</div>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border-light)">
                <div class="form-group"><label>Tanggal Pembayaran</label><input type="date" id="pay-date" value="${today}"></div>
                <div class="form-inline"><div class="form-group"><label>Jumlah Bayar</label><input type="number" id="pay-amount" placeholder="0" min="0" ${types[0]==='SYAHRIAH'?'readonly':''}></div>
                <button class="btn btn-success" onclick="App.processPayment(${student.student_id})"><span class="material-symbols-rounded">payments</span>Bayar</button></div>
            </div></div>`;

            this._currentBills = unpaid; this._currentType = types[0] || 'SYAHRIAH'; this._currentStudentId = studentId;
            this.recalcPayAmount();
        } catch (err) { panel.innerHTML = `<div class="alert alert-error">${err.message}</div>`; }
    },

    renderBillList(bills, type) {
        const filtered = bills.filter(b => b.payment_type === type);
        if (!filtered.length) return '<div class="empty-state"><p>Tidak ada tagihan</p></div>';
        const isSyahriah = type === 'SYAHRIAH';
        return filtered.map(b => {
            const remaining = b.amount - b.paid_amount;
            return `<label class="bill-checkbox"><input type="checkbox" name="bill" value="${b.bill_id}" data-amount="${remaining}" ${isSyahriah?'':'checked'} onchange="App.recalcPayAmount()"><div class="bill-info"><div class="bill-period">${b.hijri_month_name} ${b.hijri_year}</div><div class="bill-amount">Tagihan: ${App.fmtRp(b.amount)} ${b.paid_amount > 0 ? `· Dibayar: ${App.fmtRp(b.paid_amount)}` : ''}</div></div><div class="bill-remaining">${App.fmtRp(remaining)}</div></label>`;
        }).join('');
    },

    recalcPayAmount() {
        const amountEl = document.getElementById('pay-amount'); if (!amountEl) return;
        const isSyahriah = this._currentType === 'SYAHRIAH';
        if (isSyahriah) {
            const checked = document.querySelectorAll('#bill-list input[name="bill"]:checked');
            let total = 0; checked.forEach(cb => total += parseFloat(cb.dataset.amount || 0));
            amountEl.value = total; amountEl.readOnly = true;
        } else { amountEl.readOnly = false; }
    },

    filterBillType(el, type) {
        document.querySelectorAll('#bill-type-tabs .tab').forEach(t => t.classList.remove('active')); el.classList.add('active');
        this._currentType = type;
        document.getElementById('bill-list').innerHTML = this.renderBillList(this._currentBills || [], type);
        const amountEl = document.getElementById('pay-amount');
        if (amountEl) { amountEl.readOnly = type === 'SYAHRIAH'; if (type === 'SYAHRIAH') this.recalcPayAmount(); else amountEl.value = ''; }
    },

    async processPayment(studentId) {
        const amount = parseFloat(document.getElementById('pay-amount').value);
        if (!amount || amount <= 0) { App.toast('Masukkan jumlah pembayaran', 'error'); return; }
        const payDate = document.getElementById('pay-date')?.value || '';
        const payload = { student_id: studentId, payment_type: this._currentType, amount, payment_date: payDate };
        if (this._currentType === 'SYAHRIAH') {
            const checked = document.querySelectorAll('#bill-list input[name="bill"]:checked');
            payload.bill_ids = Array.from(checked).map(cb => parseInt(cb.value));
            if (!payload.bill_ids.length) { App.toast('Pilih minimal 1 bulan tagihan', 'error'); return; }
        }
        try {
            const res = await API.recordPayment(payload); App.toast(`Pembayaran ${App.fmtRp(amount)} berhasil!`);
            const receipt = await API.getReceipt(res.data.payment_id); App.showReceipt(receipt.data); this.selectStudent(studentId);
        } catch (err) { App.toast(err.message, 'error'); }
    },

    // ---- PAYMENT HISTORY ----
    async paymentHistory(c) {
        try { const res = await API.getPaymentHistory(); const pmnts = res.data || [];
        c.innerHTML = `<div class="card"><div class="card-header"><h3><span class="material-symbols-rounded">receipt_long</span>Riwayat Pembayaran</h3></div><div class="table-container"><table><thead><tr><th>ID</th><th>Tanggal</th><th>NIS</th><th>Nama</th><th>Jenis</th><th>Jumlah</th><th>Kasir</th><th>Aksi</th></tr></thead><tbody>${pmnts.map(p=>`<tr><td>PAY-${p.payment_id}</td><td>${p.payment_date}</td><td>${p.nis}</td><td>${p.student_name}</td><td><span class="badge badge-primary">${p.payment_type}</span></td><td style="font-weight:600">${App.fmtRp(p.total_amount)}</td><td>${p.cashier_name}</td><td><button class="btn btn-secondary btn-sm" onclick="App.viewReceipt(${p.payment_id})"><span class="material-symbols-rounded">receipt</span></button></td></tr>`).join('')||'<tr><td colspan="8" style="text-align:center;color:var(--text-muted)">Belum ada data</td></tr>'}</tbody></table></div></div>`;
        } catch (err) { c.innerHTML = `<div class="alert alert-error">${err.message}</div>`; }
    },
    async viewReceipt(id) { try { const res = await API.getReceipt(id); App.showReceipt(res.data); } catch (err) { App.toast(err.message, 'error'); } },
};

document.addEventListener('DOMContentLoaded', () => App.init());
