/**
 * SHIFFIN - Pages (Income, Expense, Distribution, Reports, Students, Master Data)
 */

// ---- INCOME ----
App.income = async function(c) {
    const [coaRes, txnRes] = await Promise.all([API.getCOA(), API.getTransactions('transaction_type=INCOME&limit=20')]);
    const coaList = (coaRes.data || []).filter(a => a.coa_group === 'REVENUE'); const txns = txnRes.data || [];
    c.innerHTML = `<div class="grid-2"><div class="card"><div class="card-header"><h3><span class="material-symbols-rounded">add_circle</span>Catat Pemasukan</h3></div>
    <form id="income-form"><div class="form-group"><label>Akun Pendapatan</label><select id="inc-coa">${coaList.map(a=>`<option value="${a.coa_id}">${a.coa_id} - ${a.coa_name}</option>`).join('')}</select></div>
    <div class="form-group"><label>Jumlah</label><input type="number" id="inc-amount" min="0" required placeholder="0"></div>
    <div class="form-group"><label>Sumber</label><input type="text" id="inc-source" placeholder="Sumber pemasukan"></div>
    <div class="form-group"><label>Keterangan</label><textarea id="inc-desc" rows="2" placeholder="Keterangan transaksi"></textarea></div>
    <button type="submit" class="btn btn-success btn-full"><span class="material-symbols-rounded">save</span>Simpan Pemasukan</button></form></div>
    <div class="card"><div class="card-header"><h3><span class="material-symbols-rounded">list</span>Pemasukan Terakhir</h3></div>
    <div class="table-container"><table><thead><tr><th>ID</th><th>Tanggal</th><th>Akun</th><th>Jumlah</th><th>Ket</th></tr></thead><tbody>${txns.map(t=>`<tr${t.is_reversed?' style="opacity:0.5;text-decoration:line-through"':''}><td>TXN-${t.transaction_id}</td><td>${t.transaction_date}</td><td>${t.credit_account_name}</td><td style="font-weight:600;color:var(--accent-success)">${App.fmtRp(t.amount)}</td><td>${t.description||''}</td></tr>`).join('')||'<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">Belum ada</td></tr>'}</tbody></table></div></div></div>`;
    document.getElementById('income-form').addEventListener('submit', async e => {
        e.preventDefault();
        try { await API.recordIncome({ coa_credit: document.getElementById('inc-coa').value, amount: parseFloat(document.getElementById('inc-amount').value), income_source: document.getElementById('inc-source').value, description: document.getElementById('inc-desc').value }); App.toast('Pemasukan berhasil dicatat'); App.income(c); } catch (err) { App.toast(err.message, 'error'); }
    });
};

// ---- EXPENSE (with COA auto-select) ----
App.expense = async function(c) {
    const [coaRes, deptRes, txnRes] = await Promise.all([API.getCOA(), API.getDepartments(), API.getTransactions('transaction_type=EXPENSE&limit=20')]);
    const coaList = (coaRes.data || []).filter(a => a.coa_group === 'EXPENSE'); const depts = deptRes.data || []; const txns = txnRes.data || [];
    App._depts = depts;
    c.innerHTML = `<div class="grid-2"><div class="card"><div class="card-header"><h3><span class="material-symbols-rounded">remove_circle</span>Catat Pengeluaran</h3></div>
    <form id="expense-form"><div class="form-group"><label>Bidang</label><select id="exp-dept" onchange="App.autoSelectCOA()"><option value="">- Pilih Bidang -</option>${depts.map(d=>`<option value="${d.department_id}" data-coa="${d.coa_id||''}">${d.department_name}</option>`).join('')}</select></div>
    <div class="form-group"><label>Akun Beban</label><select id="exp-coa">${coaList.map(a=>`<option value="${a.coa_id}">${a.coa_id} - ${a.coa_name}</option>`).join('')}</select></div>
    <div class="form-group"><label>Jumlah</label><input type="number" id="exp-amount" min="0" required placeholder="0"></div>
    <div class="form-group"><label>Keterangan</label><textarea id="exp-desc" rows="2" placeholder="Keterangan pengeluaran"></textarea></div>
    <button type="submit" class="btn btn-danger btn-full"><span class="material-symbols-rounded">save</span>Simpan Pengeluaran</button></form></div>
    <div class="card"><div class="card-header"><h3><span class="material-symbols-rounded">list</span>Pengeluaran Terakhir</h3></div>
    <div class="table-container"><table><thead><tr><th>ID</th><th>Tanggal</th><th>Akun</th><th>Jumlah</th><th>Ket</th></tr></thead><tbody>${txns.map(t=>`<tr${t.is_reversed?' style="opacity:0.5;text-decoration:line-through"':''}><td>TXN-${t.transaction_id}</td><td>${t.transaction_date}</td><td>${t.debit_account_name}</td><td style="font-weight:600;color:var(--accent-danger)">${App.fmtRp(t.amount)}</td><td>${t.description||''}</td></tr>`).join('')||'<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">Belum ada</td></tr>'}</tbody></table></div></div></div>`;
    document.getElementById('expense-form').addEventListener('submit', async e => {
        e.preventDefault();
        try { await API.recordExpense({ coa_debit: document.getElementById('exp-coa').value, department_id: document.getElementById('exp-dept').value || undefined, amount: parseFloat(document.getElementById('exp-amount').value), description: document.getElementById('exp-desc').value }); App.toast('Pengeluaran berhasil dicatat'); App.expense(c); } catch (err) { App.toast(err.message, 'error'); }
    });
};
App.autoSelectCOA = function() {
    const sel = document.getElementById('exp-dept'); const coaSel = document.getElementById('exp-coa');
    const opt = sel.options[sel.selectedIndex]; const coaId = opt?.dataset?.coa;
    if (coaId && coaSel) { coaSel.value = coaId; }
};

// ---- DISTRIBUTION (with Tambah Saldo) ----
App.distribution = async function(c) {
    const [previewRes, balRes, histRes, deptRes] = await Promise.all([API.previewDistribution(), API.getDepartmentBalances(), API.getDistributionHistory(), API.getDepartments()]);
    const preview = previewRes.data || {}; const bal = balRes.data || {}; const history = histRes.data || []; const depts = deptRes.data || [];
    c.innerHTML = `<div class="stats-grid"><div class="stat-card balance"><div class="stat-card-header"><span class="label">Kas Utama</span><div class="icon"><span class="material-symbols-rounded">account_balance</span></div></div><div class="stat-value">${App.fmtRp(bal.kas_utama)}</div></div>
    <div class="stat-card income"><div class="stat-card-header"><span class="label">Saldo Syahriah</span><div class="icon"><span class="material-symbols-rounded">savings</span></div></div><div class="stat-value">${App.fmtRp(preview.available_balance)}</div></div>
    <div class="stat-card info"><div class="stat-card-header"><span class="label">Total Kebutuhan</span><div class="icon"><span class="material-symbols-rounded">calculate</span></div></div><div class="stat-value">${App.fmtRp(preview.total_needed)}</div></div></div>
    <div class="grid-2">
    <div class="card"><div class="card-header"><h3><span class="material-symbols-rounded">add_card</span>Tambah Saldo Bidang</h3></div>
    <p style="color:var(--text-secondary);margin-bottom:12px;font-size:0.85rem">Transfer dana dari Kas Utama ke saldo bidang</p>
    <form id="add-balance-form"><div class="form-group"><label>Bidang</label><select id="ab-dept">${depts.map(d=>`<option value="${d.department_id}">${d.department_name}</option>`).join('')}</select></div>
    <div class="form-group"><label>Jumlah</label><input type="number" id="ab-amount" min="0" required placeholder="0"></div>
    <div class="form-group"><label>Keterangan</label><input type="text" id="ab-desc" placeholder="Keterangan (opsional)"></div>
    <button type="submit" class="btn btn-primary btn-full"><span class="material-symbols-rounded">send</span>Tambah Saldo</button></form></div>
    <div class="card"><div class="card-header"><h3><span class="material-symbols-rounded">account_tree</span>Distribusi Syahriah</h3><button class="btn btn-primary" onclick="App.runDistribution('SYAHRIAH')"><span class="material-symbols-rounded">play_arrow</span>Jalankan</button></div>
    ${(preview.allocations||[]).map(a=>`<div class="dist-item ${a.status.toLowerCase()}"><span class="dept-name">${a.department_name}</span><span class="badge ${a.status==='FULL'?'badge-success':a.status==='PARTIAL'?'badge-warning':'badge-danger'}">${a.status}</span><span class="dist-amount">${App.fmtRp(a.allocated)} / ${App.fmtRp(a.needed)}</span></div>`).join('')}</div></div>
    <div class="card" style="margin-top:16px"><div class="card-header"><h3><span class="material-symbols-rounded">history</span>Riwayat Distribusi & Transfer</h3></div>
    <div class="table-container"><table><thead><tr><th>Tanggal</th><th>Jenis</th><th>Total</th><th>Status</th></tr></thead><tbody>${history.map(h=>`<tr><td>${h.distribution_date}</td><td><span class="badge badge-primary">${h.source_type}</span></td><td style="font-weight:600">${App.fmtRp(h.total_distributed)}</td><td>${App.statusBadge(h.status)}</td></tr>`).join('')||'<tr><td colspan="4" style="text-align:center;color:var(--text-muted)">Belum ada</td></tr>'}</tbody></table></div></div>`;
    document.getElementById('add-balance-form').addEventListener('submit', async e => {
        e.preventDefault();
        try { await API.addDepartmentBalance({ department_id: document.getElementById('ab-dept').value, amount: parseFloat(document.getElementById('ab-amount').value), description: document.getElementById('ab-desc').value }); App.toast('Saldo bidang berhasil ditambahkan!'); App.navigate('distribution'); } catch (err) { App.toast(err.message, 'error'); }
    });
};
App.runDistribution = async function(type) {
    if (!confirm(`Jalankan distribusi ${type}?`)) return;
    try { const res = await API.runDistribution({ source_type: type }); App.toast(`Distribusi berhasil! Total: ${App.fmtRp(res.data.total_distributed)}`); App.navigate('distribution'); } catch (err) { App.toast(err.message, 'error'); }
};

// ---- REPORTS (with filters + Excel) ----
App.reports = async function(c) {
    const deptRes = await API.getDepartments(); const depts = deptRes.data || [];
    c.innerHTML = `<div class="tabs" id="report-tabs"><button class="tab active" onclick="App.showReport('journal',this)">Jurnal Umum</button><button class="tab" onclick="App.showReport('income-report',this)">Pemasukan</button><button class="tab" onclick="App.showReport('expense-report',this)">Pengeluaran</button><button class="tab" onclick="App.showReport('outstanding',this)">Tunggakan</button><button class="tab" onclick="App.showReport('dept-balance',this)">Saldo Bidang</button><button class="tab" onclick="App.showReport('summary',this)">Ringkasan</button></div>
    <div class="filter-bar"><input type="date" id="rpt-from" value="${new Date().toISOString().slice(0,8)}01"><input type="date" id="rpt-to" value="${new Date().toISOString().slice(0,10)}">
    <select id="rpt-dept"><option value="">- Semua Bidang -</option>${depts.map(d=>`<option value="${d.department_id}">${d.department_name}</option>`).join('')}</select>
    <button class="btn btn-secondary" onclick="App.showReport(App._currentReport)"><span class="material-symbols-rounded">refresh</span>Muat</button></div>
    <div id="report-content"><div class="empty-state"><p>Pilih laporan dan klik Muat</p></div></div>`;
    App._currentReport = 'journal'; App._reportDepts = depts; App.showReport('journal');
};
App.showReport = async function(type, tabEl) {
    App._currentReport = type;
    if (tabEl) { document.querySelectorAll('#report-tabs .tab').forEach(t => t.classList.remove('active')); tabEl.classList.add('active'); }
    const rc = document.getElementById('report-content'); if (!rc) return;
    rc.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    const df = document.getElementById('rpt-from')?.value || ''; const dt = document.getElementById('rpt-to')?.value || '';
    const deptId = document.getElementById('rpt-dept')?.value || '';
    const params = `date_from=${df}&date_to=${dt}${deptId ? '&department_id='+deptId : ''}`;
    try {
        let html = ''; let exBtn = '';
        if (type === 'journal') {
            const res = await API.getGeneralJournal(params); const rows = res.data || [];
            exBtn = `<button class="btn btn-sm btn-secondary" onclick="API.downloadExcel('general-journal','${params}')"><span class="material-symbols-rounded">download</span>Excel</button>`;
            html = `<div class="card"><div class="card-header"><h3>Jurnal Umum</h3>${exBtn}</div><div class="table-container"><table><thead><tr><th>ID</th><th>Tanggal</th><th>Tipe</th><th>Debit</th><th>Kredit</th><th>Jumlah</th><th>Keterangan</th></tr></thead><tbody>${rows.map(r=>`<tr><td>TXN-${r.transaction_id}</td><td>${r.transaction_date}</td><td><span class="badge badge-primary">${r.transaction_type}</span></td><td>${r.debit_account_name}</td><td>${r.credit_account_name}</td><td style="font-weight:600">${App.fmtRp(r.amount)}</td><td>${r.description||''}</td></tr>`).join('')||'<tr><td colspan="7" style="text-align:center;color:var(--text-muted)">Tidak ada data</td></tr>'}</tbody></table></div></div>`;
        } else if (type === 'income-report') {
            const res = await API.getMonthlyIncome(params); const rows = res.data || [];
            exBtn = `<button class="btn btn-sm btn-secondary" onclick="API.downloadExcel('monthly-income','${params}')"><span class="material-symbols-rounded">download</span>Excel</button>`;
            if (deptId) {
                html = `<div class="card"><div class="card-header"><h3>Detail Pemasukan</h3>${exBtn}</div><div class="table-container"><table><thead><tr><th>ID</th><th>Tanggal</th><th>Akun</th><th>Jumlah</th><th>Keterangan</th></tr></thead><tbody>${rows.map(r=>`<tr><td>TXN-${r.transaction_id}</td><td>${r.transaction_date}</td><td>${r.coa_name}</td><td style="font-weight:600;color:var(--accent-success)">${App.fmtRp(r.amount)}</td><td>${r.description||''}</td></tr>`).join('')||'<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">Tidak ada</td></tr>'}</tbody></table></div></div>`;
            } else {
                const total = rows.reduce((s,r)=>s+parseFloat(r.total||0),0);
                html = `<div class="card"><div class="card-header"><h3>Laporan Pemasukan</h3><div>${exBtn}<span style="font-weight:700;color:var(--accent-success);margin-left:8px">Total: ${App.fmtRp(total)}</span></div></div><div class="table-container"><table><thead><tr><th>Kode</th><th>Nama Akun</th><th>Jumlah</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${r.coa_id}</td><td>${r.coa_name}</td><td style="font-weight:600;color:var(--accent-success)">${App.fmtRp(r.total)}</td></tr>`).join('')}</tbody></table></div></div>`;
            }
        } else if (type === 'expense-report') {
            const res = await API.getMonthlyExpense(params); const rows = res.data || [];
            exBtn = `<button class="btn btn-sm btn-secondary" onclick="API.downloadExcel('monthly-expense','${params}')"><span class="material-symbols-rounded">download</span>Excel</button>`;
            if (deptId) {
                html = `<div class="card"><div class="card-header"><h3>Detail Pengeluaran</h3>${exBtn}</div><div class="table-container"><table><thead><tr><th>ID</th><th>Tanggal</th><th>Akun</th><th>Jumlah</th><th>Keterangan</th></tr></thead><tbody>${rows.map(r=>`<tr><td>TXN-${r.transaction_id}</td><td>${r.transaction_date}</td><td>${r.coa_name}</td><td style="font-weight:600;color:var(--accent-danger)">${App.fmtRp(r.amount)}</td><td>${r.description||''}</td></tr>`).join('')||'<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">Tidak ada</td></tr>'}</tbody></table></div></div>`;
            } else {
                const total = rows.reduce((s,r)=>s+parseFloat(r.total||0),0);
                html = `<div class="card"><div class="card-header"><h3>Laporan Pengeluaran</h3><div>${exBtn}<span style="font-weight:700;color:var(--accent-danger);margin-left:8px">Total: ${App.fmtRp(total)}</span></div></div><div class="table-container"><table><thead><tr><th>Kode</th><th>Nama Akun</th><th>Jumlah</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${r.coa_id}</td><td>${r.coa_name}</td><td style="font-weight:600;color:var(--accent-danger)">${App.fmtRp(r.total)}</td></tr>`).join('')}</tbody></table></div></div>`;
            }
        } else if (type === 'outstanding') {
            const res = await API.getOutstandingMonthlySummary(); const rows = res.data || [];
            exBtn = `<button class="btn btn-sm btn-secondary" onclick="API.downloadExcel('outstanding-monthly-summary','')"><span class="material-symbols-rounded">download</span>Excel</button>`;
            const grandTotal = rows.reduce((s,r)=>s+parseFloat(r.total_outstanding||0),0);
            html = `<div class="card"><div class="card-header"><h3>Rekap Tunggakan Per Bulan</h3><div>${exBtn}<span style="font-weight:700;color:var(--accent-warning);margin-left:8px">Total: ${App.fmtRp(grandTotal)}</span></div></div><div class="table-container"><table><thead><tr><th>Bulan</th><th>Tahun</th><th>Jenis</th><th>Jml Tagihan</th><th>Total Nominal</th><th>Dibayar</th><th>Tunggakan</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${r.hijri_month_name}</td><td>${r.hijri_year}</td><td><span class="badge badge-primary">${r.payment_type}</span></td><td>${r.bill_count}</td><td>${App.fmtRp(r.total_amount)}</td><td>${App.fmtRp(r.total_paid)}</td><td style="font-weight:600;color:var(--accent-warning)">${App.fmtRp(r.total_outstanding)}</td></tr>`).join('')||'<tr><td colspan="7" style="text-align:center;color:var(--text-muted)">Tidak ada tunggakan</td></tr>'}</tbody></table></div></div>`;
        } else if (type === 'dept-balance') {
            const res = await API.getDepartmentBalances(); const d = res.data || {};
            exBtn = `<button class="btn btn-sm btn-secondary" onclick="API.downloadExcel('department-balances','')"><span class="material-symbols-rounded">download</span>Excel</button>`;
            html = `<div class="card"><div class="card-header"><h3>Saldo Bidang</h3><div>${exBtn}<span style="font-weight:700;color:var(--accent-primary-light);margin-left:8px">Kas Utama: ${App.fmtRp(d.kas_utama)}</span></div></div><div class="table-container"><table><thead><tr><th>Bidang</th><th>Tipe</th><th>Saldo</th></tr></thead><tbody>${(d.departments||[]).map(dp=>`<tr><td>${dp.department_name}</td><td><span class="badge ${dp.department_type==='REVENUE'?'badge-success':'badge-info'}">${dp.department_type}</span></td><td style="font-weight:600">${App.fmtRp(dp.balance)}</td></tr>`).join('')}</tbody></table></div></div>`;
        } else if (type === 'summary') {
            const res = await API.getFinancialSummary(params); const s = res.data || {};
            html = `<div class="stats-grid"><div class="stat-card income"><div class="stat-card-header"><span class="label">Total Pemasukan</span><div class="icon"><span class="material-symbols-rounded">trending_up</span></div></div><div class="stat-value">${App.fmtRp(s.total_income)}</div></div><div class="stat-card expense"><div class="stat-card-header"><span class="label">Total Pengeluaran</span><div class="icon"><span class="material-symbols-rounded">trending_down</span></div></div><div class="stat-value">${App.fmtRp(s.total_expense)}</div></div><div class="stat-card ${s.net_income>=0?'balance':'warning'}"><div class="stat-card-header"><span class="label">Laba/Rugi Bersih</span><div class="icon"><span class="material-symbols-rounded">balance</span></div></div><div class="stat-value">${App.fmtRp(s.net_income)}</div></div><div class="stat-card info"><div class="stat-card-header"><span class="label">Saldo Kas</span><div class="icon"><span class="material-symbols-rounded">account_balance_wallet</span></div></div><div class="stat-value">${App.fmtRp(s.kas_balance)}</div></div></div>`;
        }
        rc.innerHTML = html;
    } catch (err) { rc.innerHTML = `<div class="alert alert-error">${err.message}</div>`; }
};
