/**
 * SHIFFIN - Pages 2 (Students CRUD, CSV Import, Master Data Management)
 */

// ---- STUDENTS ----
App.students = async function(c) {
    const res = await API.getStudents();
    const students = res.data || [];
    const pagination = res.pagination || {};
    
    c.innerHTML = `
        <div class="card">
            <div class="card-header">
                <h3><span class="material-symbols-rounded">school</span>Data Santri</h3>
                <div class="header-actions">
                    <button class="btn btn-secondary" onclick="App.showImportCSVModal()">
                        <span class="material-symbols-rounded">upload_file</span> Import CSV
                    </button>
                    <button class="btn btn-primary" onclick="App.showAddStudentModal()">
                        <span class="material-symbols-rounded">person_add</span> Tambah Santri
                    </button>
                </div>
            </div>
            <div class="filter-bar">
                <input type="text" id="student-search-input" placeholder="Cari nama atau NIS..." onkeyup="if(event.key==='Enter') App.searchStudentsList()">
                <button class="btn btn-secondary" onclick="App.searchStudentsList()"><span class="material-symbols-rounded">search</span></button>
            </div>
            <div class="table-container">
                <table id="students-table">
                    <thead>
                        <tr>
                            <th>NIS</th>
                            <th>Nama</th>
                            <th>Tipe</th>
                            <th>Kategori</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${students.map(s => `
                            <tr>
                                <td>${s.nis}</td>
                                <td>${s.name}</td>
                                <td>${s.student_type}</td>
                                <td>${s.category_name}</td>
                                <td>${App.statusBadge(s.status)}</td>
                                <td class="actions">
                                    <button class="btn btn-ghost btn-sm" onclick="App.showEditStudentModal(${s.student_id})" title="Edit">
                                        <span class="material-symbols-rounded">edit</span>
                                    </button>
                                    <button class="btn btn-ghost btn-sm text-danger" onclick="App.deleteStudent(${s.student_id})" title="Hapus">
                                        <span class="material-symbols-rounded">delete</span>
                                    </button>
                                </td>
                            </tr>
                        `).join('') || '<tr><td colspan="6" style="text-align:center">Belum ada data</td></tr>'}
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                <span>Halaman ${pagination.page} dari ${pagination.total_pages} (Total: ${pagination.total})</span>
                <div class="pagination-buttons">
                    <button class="btn btn-ghost btn-sm" ${pagination.page <= 1 ? 'disabled' : ''} onclick="App.loadStudentsPage(${pagination.page - 1})">Sebelumnya</button>
                    <button class="btn btn-ghost btn-sm" ${pagination.page >= pagination.total_pages ? 'disabled' : ''} onclick="App.loadStudentsPage(${pagination.page + 1})">Selanjutnya</button>
                </div>
            </div>
        </div>
    `;
};

App.loadStudentsPage = async function(page) {
    const q = document.getElementById('student-search-input')?.value || '';
    const res = await API.getStudents(`page=${page}&search=${encodeURIComponent(q)}`);
    const c = document.getElementById('page-content');
    App.students(c); // Re-render with new data
};

App.searchStudentsList = function() {
    App.loadStudentsPage(1);
};

App.showAddStudentModal = async function() {
    const [cats, ays, hms] = await Promise.all([API.getCategories(), API.getAcademicYears(), API.getHijriMonths()]);
    
    const body = `
        <form id="student-form">
            <div class="form-group">
                <label>NIS</label>
                <input type="text" id="stu-nis" required>
            </div>
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" id="stu-name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Tipe Santri</label>
                    <select id="stu-type">
                        <option value="Mukim">Mukim</option>
                        <option value="Non-Mukim">Non-Mukim</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kategori Syahriah</label>
                    <select id="stu-cat">
                        ${cats.data.map(c => `<option value="${c.category_id}">${c.category_name} (${App.fmtRp(c.monthly_fee)})</option>`).join('')}
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Tahun Ajaran Masuk</label>
                    <select id="stu-ay">
                        ${ays.data.map(a => `<option value="${a.academic_year_id}">${a.year_name}</option>`).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label>Tanggal Masuk (Masehi)</label>
                    <input type="date" id="stu-date" value="${new Date().toISOString().split('T')[0]}">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Bulan Hijri Masuk</label>
                    <select id="stu-hm">
                        ${hms.data.map(m => `<option value="${m.month_id}">${m.month_name}</option>`).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label>Tahun Hijri Masuk</label>
                    <input type="number" id="stu-hy" value="1445">
                </div>
            </div>
        </form>
    `;
    
    App.openModal('Tambah Santri Baru', body, `
        <button class="btn btn-ghost" onclick="App.closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="App.saveStudent()">Simpan</button>
    `);
};

App.showEditStudentModal = async function(id) {
    const [res, cats, ays, hms] = await Promise.all([
        API.getStudent(id),
        API.getCategories(),
        API.getAcademicYears(),
        API.getHijriMonths()
    ]);
    const s = res.data;
    
    const body = `
        <form id="student-form">
            <input type="hidden" id="edit-stu-id" value="${s.student_id}">
            <div class="form-group">
                <label>NIS</label>
                <input type="text" id="stu-nis" value="${s.nis}" required>
            </div>
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" id="stu-name" value="${s.name}" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Tipe Santri</label>
                    <select id="stu-type">
                        <option value="Mukim" ${s.student_type==='Mukim'?'selected':''}>Mukim</option>
                        <option value="Non-Mukim" ${s.student_type==='Non-Mukim'?'selected':''}>Non-Mukim</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kategori Syahriah</label>
                    <select id="stu-cat">
                        ${cats.data.map(c => `<option value="${c.category_id}" ${s.syahriah_category_id==c.category_id?'selected':''}>${c.category_name} (${App.fmtRp(c.monthly_fee)})</option>`).join('')}
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select id="stu-status">
                    <option value="ACTIVE" ${s.status==='ACTIVE'?'selected':''}>Aktif</option>
                    <option value="DROPPED" ${s.status==='DROPPED'?'selected':''}>Keluar</option>
                    <option value="GRADUATED" ${s.status==='GRADUATED'?'selected':''}>Lulus</option>
                </select>
            </div>
        </form>
    `;
    
    App.openModal('Edit Data Santri', body, `
        <button class="btn btn-ghost" onclick="App.closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="App.saveStudent()">Update</button>
    `);
};

App.saveStudent = async function() {
    const id = document.getElementById('edit-stu-id')?.value;
    const data = {
        nis: document.getElementById('stu-nis').value,
        name: document.getElementById('stu-name').value,
        student_type: document.getElementById('stu-type').value,
        syahriah_category_id: document.getElementById('stu-cat').value,
    };
    
    if (id) {
        data.status = document.getElementById('stu-status').value;
    } else {
        // New student specific fields
        data.academic_year_id = document.getElementById('stu-ay').value;
        data.entry_date = document.getElementById('stu-date').value;
        data.entry_hijri_month = document.getElementById('stu-hm').value;
        data.entry_hijri_year = document.getElementById('stu-hy').value;
    }
    
    try {
        if (id) {
            await API.updateStudent(id, data);
            App.toast('Data santri berhasil diupdate');
        } else {
            await API.createStudent(data);
            App.toast('Santri baru berhasil ditambahkan');
        }
        App.closeModal();
        App.students(document.getElementById('page-content'));
    } catch (err) {
        App.toast(err.message, 'error');
    }
};

App.deleteStudent = async function(id) {
    if (!confirm('Apakah Anda yakin ingin menghapus (non-aktifkan) santri ini?')) return;
    try {
        await API.deleteStudent(id);
        App.toast('Santri telah di-non-aktifkan');
        App.students(document.getElementById('page-content'));
    } catch (err) {
        App.toast(err.message, 'error');
    }
};

App.showImportCSVModal = function() {
    const body = `
        <div class="import-help">
            <p>Upload file CSV dengan kolom: <b>nis, name, student_type, syahriah_category_id</b></p>
            <p style="font-size:0.8rem; color:var(--text-muted)">Gunakan koma (,) sebagai pemisah kolom.</p>
        </div>
        <form id="import-form">
            <div class="form-group">
                <label>Pilih File CSV</label>
                <input type="file" id="csv-file" accept=".csv" required>
            </div>
        </form>
        <div id="import-results" style="margin-top:16px; max-height:200px; overflow-y:auto; font-size:0.85rem"></div>
    `;
    
    App.openModal('Import Santri dari CSV', body, `
        <button class="btn btn-ghost" onclick="App.closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="App.handleImportCSV()" id="import-btn">Mulai Import</button>
    `);
};

App.handleImportCSV = async function() {
    const fileInput = document.getElementById('csv-file');
    if (!fileInput.files.length) {
        App.toast('Pilih file CSV terlebih dahulu', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('csv_file', fileInput.files[0]);
    
    const btn = document.getElementById('import-btn');
    btn.disabled = true;
    btn.textContent = 'Memproses...';
    
    try {
        const res = await API.importStudentsCSV(formData);
        App.toast(res.message);
        
        const results = res.data || [];
        let html = '<ul class="import-list">';
        results.forEach(r => {
            html += `<li class="${r.status}">${r.nis} - ${r.name}: ${r.status === 'success' ? 'OK' : r.message}</li>`;
        });
        html += '</ul>';
        document.getElementById('import-results').innerHTML = html;
        
        btn.textContent = 'Import Berhasil';
        setTimeout(() => {
            App.closeModal();
            App.students(document.getElementById('page-content'));
        }, 2000);
    } catch (err) {
        App.toast(err.message, 'error');
        btn.disabled = false;
        btn.textContent = 'Mulai Import';
    }
};

// ---- MASTER DATA ----
App.masterData = async function(c) {
    c.innerHTML = `
        <div class="tabs" id="master-tabs">
            <button class="tab active" onclick="App.showMasterTab('categories', this)">Kategori Syahriah</button>
            <button class="tab" onclick="App.showMasterTab('coa', this)">Daftar Akun (COA)</button>
            <button class="tab" onclick="App.showMasterTab('departments', this)">Bidang / Divisi</button>
            <button class="tab" onclick="App.showMasterTab('academic-years', this)">Tahun Ajaran</button>
            <button class="tab" onclick="App.showMasterTab('users', this)">User System</button>
            <button class="tab" onclick="App.showMasterTab('settings', this)">Pengaturan</button>
        </div>
        <div id="master-content" class="card" style="margin-top:16px">
            <div class="loading"><div class="spinner"></div></div>
        </div>
    `;
    App.showMasterTab('categories');
};

App.showMasterTab = async function(tab, tabEl) {
    if (tabEl) {
        document.querySelectorAll('#master-tabs .tab').forEach(t => t.classList.remove('active'));
        tabEl.classList.add('active');
    }
    
    const mc = document.getElementById('master-content');
    mc.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    
    try {
        let html = '';
        switch(tab) {
            case 'categories':
                const cats = await API.getCategories();
                html = `
                    <div class="card-header">
                        <h3>Kategori Syahriah</h3>
                        <button class="btn btn-primary btn-sm" onclick="App.showEditCategoryModal()">Tambah</button>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>Nama Kategori</th><th>Bulanan</th><th>Status</th><th>Aksi</th></tr></thead>
                            <tbody>${cats.data.map(c => `<tr><td>${c.category_name}</td><td>${App.fmtRp(c.monthly_fee)}</td><td>${App.statusBadge(c.is_active ? 'ACTIVE' : 'DROPPED')}</td><td><button class="btn btn-ghost btn-sm" onclick="App.showEditCategoryModal(${c.category_id})"><span class="material-symbols-rounded">edit</span></button></td></tr>`).join('')}</tbody>
                        </table>
                    </div>
                `;
                break;
            case 'coa':
                const coa = await API.getCOA();
                html = `
                    <div class="card-header"><h3>Daftar Akun</h3></div>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>Kode</th><th>Nama Akun</th><th>Grup</th><th>Aksi</th></tr></thead>
                            <tbody>${coa.data.map(c => `<tr><td>${c.coa_id}</td><td>${c.coa_name}</td><td>${c.coa_group}</td><td><button class="btn btn-ghost btn-sm" onclick="App.showEditCOAModal('${c.coa_id}')"><span class="material-symbols-rounded">edit</span></button></td></tr>`).join('')}</tbody>
                        </table>
                    </div>
                `;
                break;
            case 'departments':
                const depts = await API.getDepartments();
                html = `
                    <div class="card-header"><h3>Bidang / Divisi</h3></div>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>Nama Bidang</th><th>Tipe</th><th>COA Pemetaan</th><th>Aksi</th></tr></thead>
                            <tbody>${depts.data.map(d => `<tr><td>${d.department_name}</td><td>${d.department_type}</td><td>${d.coa_name || '-'}</td><td><button class="btn btn-ghost btn-sm" onclick="App.showEditDeptModal(${d.department_id})"><span class="material-symbols-rounded">edit</span></button></td></tr>`).join('')}</tbody>
                        </table>
                    </div>
                `;
                break;
            case 'academic-years':
                const ays = await API.getAcademicYears();
                html = `
                    <div class="card-header"><h3>Tahun Ajaran</h3></div>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>Nama Tahun</th><th>Status</th><th>Aksi</th></tr></thead>
                            <tbody>${ays.data.map(a => `<tr><td>${a.year_name}</td><td>${App.statusBadge(a.is_active ? 'ACTIVE' : 'DROPPED')}</td><td><button class="btn btn-ghost btn-sm" onclick="App.showEditAYModal(${a.academic_year_id})"><span class="material-symbols-rounded">edit</span></button></td></tr>`).join('')}</tbody>
                        </table>
                    </div>
                `;
                break;
            case 'users':
                const users = await API.getUsers();
                html = `
                    <div class="card-header"><h3>User System</h3></div>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>Username</th><th>Nama Lengkap</th><th>Role</th><th>Status</th><th>Aksi</th></tr></thead>
                            <tbody>${users.data.map(u => `<tr><td>${u.username}</td><td>${u.full_name}</td><td>${u.role}</td><td>${App.statusBadge(u.is_active ? 'ACTIVE' : 'DROPPED')}</td><td><button class="btn btn-ghost btn-sm" onclick="App.showEditUserModal(${u.user_id})"><span class="material-symbols-rounded">edit</span></button></td></tr>`).join('')}</tbody>
                        </table>
                    </div>
                `;
                break;
            case 'settings':
                const settings = await API.getSettings();
                html = `
                    <div class="card-header"><h3>Pengaturan Sistem</h3></div>
                    <div style="padding:16px">
                        <form id="settings-form">
                            ${settings.data.map(s => `
                                <div class="form-group">
                                    <label>${s.setting_key.replace(/_/g, ' ').toUpperCase()}</label>
                                    <input type="text" name="${s.setting_key}" value="${s.setting_value}">
                                </div>
                            `).join('')}
                            <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
                        </form>
                    </div>
                `;
                setTimeout(() => {
                    document.getElementById('settings-form').onsubmit = async (e) => {
                        e.preventDefault();
                        const formData = new FormData(e.target);
                        const data = Object.fromEntries(formData.entries());
                        try {
                            await API.updateSettings(data);
                            App.toast('Pengaturan berhasil disimpan');
                            App.showMasterTab('settings');
                        } catch (err) { App.toast(err.message, 'error'); }
                    };
                }, 100);
                break;
        }
        mc.innerHTML = html;
    } catch (err) { mc.innerHTML = `<div class="alert alert-error">${err.message}</div>`; }
};

// Modals for master data editing
App.showEditCategoryModal = async function(id) {
    let c = { category_id: '', category_name: '', monthly_fee: 0, is_active: 1 };
    if (id) {
        const res = await API.getCategories();
        c = res.data.find(x => x.category_id == id);
    }
    
    const body = `
        <form id="edit-form">
            <div class="form-group"><label>Nama Kategori</label><input type="text" id="cat-name" value="${c.category_name}" required></div>
            <div class="form-group"><label>Biaya Bulanan</label><input type="number" id="cat-fee" value="${c.monthly_fee}" required></div>
            <div class="form-group"><label>Aktif</label><select id="cat-active"><option value="1" ${c.is_active?'selected':''}>Ya</option><option value="0" ${!c.is_active?'selected':''}>Tidak</option></select></div>
        </form>
    `;
    
    App.openModal(id ? 'Edit Kategori' : 'Tambah Kategori', body, `
        <button class="btn btn-ghost" onclick="App.closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="App.saveCategory(${id||'null'})">Simpan</button>
    `);
};

App.saveCategory = async function(id) {
    const data = {
        category_name: document.getElementById('cat-name').value,
        monthly_fee: parseFloat(document.getElementById('cat-fee').value),
        is_active: parseInt(document.getElementById('cat-active').value)
    };
    try {
        if (id) await API.updateCategory(id, data);
        else await API.createCategory(data);
        App.toast('Kategori berhasil disimpan');
        App.closeModal();
        App.showMasterTab('categories');
    } catch (err) { App.toast(err.message, 'error'); }
};

App.showEditCOAModal = async function(id) {
    const res = await API.getCOA();
    const c = res.data.find(x => x.coa_id == id);
    const body = `
        <form id="edit-form">
            <div class="form-group"><label>Kode Akun</label><input type="text" id="coa-id" value="${c.coa_id}" readonly></div>
            <div class="form-group"><label>Nama Akun</label><input type="text" id="coa-name" value="${c.coa_name}" required></div>
            <div class="form-group"><label>Grup</label><input type="text" id="coa-group" value="${c.coa_group}" readonly></div>
        </form>
    `;
    App.openModal('Edit COA', body, `
        <button class="btn btn-ghost" onclick="App.closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="App.saveCOA('${id}')">Update</button>
    `);
};

App.saveCOA = async function(id) {
    const data = { coa_name: document.getElementById('coa-name').value };
    try {
        await API.updateCOA(id, data);
        App.toast('COA berhasil diupdate');
        App.closeModal();
        App.showMasterTab('coa');
    } catch (err) { App.toast(err.message, 'error'); }
};

App.showEditDeptModal = async function(id) {
    const [res, coa] = await Promise.all([API.getDepartments(), API.getCOA()]);
    const d = res.data.find(x => x.department_id == id);
    const coaExpense = coa.data.filter(c => c.coa_group === 'EXPENSE');
    
    const body = `
        <form id="edit-form">
            <div class="form-group"><label>Nama Bidang</label><input type="text" id="dept-name" value="${d.department_name}" required></div>
            <div class="form-group"><label>COA Pemetaan (Mapping)</label>
                <select id="dept-coa">
                    <option value="">- Tanpa Mapping -</option>
                    ${coaExpense.map(c => `<option value="${c.coa_id}" ${d.coa_id==c.coa_id?'selected':''}>${c.coa_id} - ${c.coa_name}</option>`).join('')}
                </select>
            </div>
        </form>
    `;
    App.openModal('Edit Bidang', body, `
        <button class="btn btn-ghost" onclick="App.closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="App.saveDept(${id})">Update</button>
    `);
};

App.saveDept = async function(id) {
    const data = { department_name: document.getElementById('dept-name').value, coa_id: document.getElementById('dept-coa').value };
    try {
        await API.updateDepartment(id, data);
        App.toast('Bidang berhasil diupdate');
        App.closeModal();
        App.showMasterTab('departments');
    } catch (err) { App.toast(err.message, 'error'); }
};

App.showEditUserModal = async function(id) {
    const res = await API.getUsers();
    const u = res.data.find(x => x.user_id == id);
    const body = `
        <form id="edit-form">
            <div class="form-group"><label>Username</label><input type="text" id="u-user" value="${u.username}" required></div>
            <div class="form-group"><label>Nama Lengkap</label><input type="text" id="u-name" value="${u.full_name}" required></div>
            <div class="form-group"><label>Role</label>
                <select id="u-role">
                    <option value="ADMIN" ${u.role==='ADMIN'?'selected':''}>ADMIN</option>
                    <option value="TREASURER" ${u.role==='TREASURER'?'selected':''}>TREASURER</option>
                    <option value="CASHIER" ${u.role==='CASHIER'?'selected':''}>CASHIER</option>
                </select>
            </div>
            <div class="form-group"><label>Status</label><select id="u-active"><option value="1" ${u.is_active?'selected':''}>Aktif</option><option value="0" ${!u.is_active?'selected':''}>Non-Aktif</option></select></div>
            <div class="form-group"><label>Password (Kosongkan jika tidak ganti)</label><input type="password" id="u-pass"></div>
        </form>
    `;
    App.openModal('Edit User', body, `
        <button class="btn btn-ghost" onclick="App.closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="App.saveUser(${id})">Update</button>
    `);
};

App.saveUser = async function(id) {
    const data = {
        username: document.getElementById('u-user').value,
        full_name: document.getElementById('u-name').value,
        role: document.getElementById('u-role').value,
        is_active: parseInt(document.getElementById('u-active').value)
    };
    const pass = document.getElementById('u-pass').value;
    if (pass) data.password = pass;
    try {
        await API.updateUser(id, data);
        App.toast('User berhasil diupdate');
        App.closeModal();
        App.showMasterTab('users');
    } catch (err) { App.toast(err.message, 'error'); }
};

App.showEditAYModal = async function(id) {
    const res = await API.getAcademicYears();
    const a = res.data.find(x => x.academic_year_id == id);
    const body = `
        <form id="edit-form">
            <div class="form-group"><label>Nama Tahun Ajaran</label><input type="text" id="ay-name" value="${a.year_name}" required></div>
            <div class="form-group"><label>Aktif</label><select id="ay-active"><option value="1" ${a.is_active?'selected':''}>Ya</option><option value="0" ${!a.is_active?'selected':''}>Tidak</option></select></div>
        </form>
    `;
    App.openModal('Edit Tahun Ajaran', body, `
        <button class="btn btn-ghost" onclick="App.closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="App.saveAY(${id})">Update</button>
    `);
};

App.saveAY = async function(id) {
    const data = { year_name: document.getElementById('ay-name').value, is_active: parseInt(document.getElementById('ay-active').value) };
    try {
        await API.updateAcademicYear(id, data);
        App.toast('Tahun ajaran berhasil diupdate');
        App.closeModal();
        App.showMasterTab('academic-years');
    } catch (err) { App.toast(err.message, 'error'); }
};
