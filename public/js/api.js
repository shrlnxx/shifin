/**
 * SHIFFIN - API Client
 */
const API = {
    base: '/shifin/api',

    async request(endpoint, options = {}) {
        const url = this.base + endpoint;
        const config = {
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            ...options
        };

        if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
            config.body = JSON.stringify(config.body);
        }

        // Remove Content-Type for FormData (browser sets it with boundary)
        if (config.body instanceof FormData) {
            delete config.headers['Content-Type'];
        }

        try {
            const res = await fetch(url, config);
            const data = await res.json();
            
            if (res.status === 401) {
                App.showLogin();
                throw new Error('Session expired');
            }
            
            if (!data.success && !data.data) {
                throw new Error(data.message || 'Request failed');
            }
            
            return data;
        } catch (err) {
            if (err.message !== 'Session expired') {
                console.error('API Error:', err);
            }
            throw err;
        }
    },

    get(endpoint) { return this.request(endpoint); },
    
    post(endpoint, body) {
        return this.request(endpoint, { method: 'POST', body });
    },
    
    put(endpoint, body) {
        return this.request(endpoint, { method: 'PUT', body });
    },

    del(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    },

    // Auth
    login(username, password) { return this.post('/auth/login', { username, password }); },
    logout() { return this.post('/auth/logout', {}); },
    me() { return this.get('/auth/me'); },

    // Master
    getStudents(params = '') { return this.get('/master/students' + (params ? '?' + params : '')); },
    getStudent(id) { return this.get('/master/students/' + id); },
    createStudent(data) { return this.post('/master/students', data); },
    updateStudent(id, data) { return this.put('/master/students/' + id, data); },
    deleteStudent(id) { return this.del('/master/students/' + id); },
    importStudentsCSV(formData) {
        return this.request('/master/students/import', { method: 'POST', body: formData });
    },
    getCategories() { return this.get('/master/categories'); },
    createCategory(data) { return this.post('/master/categories', data); },
    updateCategory(id, data) { return this.put('/master/categories/' + id, data); },
    getAcademicYears() { return this.get('/master/academic-years'); },
    createAcademicYear(data) { return this.post('/master/academic-years', data); },
    updateAcademicYear(id, data) { return this.put('/master/academic-years/' + id, data); },
    getCOA() { return this.get('/master/coa'); },
    updateCOA(id, data) { return this.put('/master/coa/' + id, data); },
    getDepartments() { return this.get('/master/departments'); },
    updateDepartment(id, data) { return this.put('/master/departments/' + id, data); },
    getDistributionRules(type) { return this.get('/master/distribution-rules' + (type ? '?source_type=' + type : '')); },
    getDistributionRuleDetails(id) { return this.get('/master/distribution-rules/' + id); },
    getUsers() { return this.get('/master/users'); },
    createUser(data) { return this.post('/master/users', data); },
    updateUser(id, data) { return this.put('/master/users/' + id, data); },
    getSettings() { return this.get('/master/settings'); },
    updateSettings(data) { return this.put('/master/settings', data); },
    getHijriMonths() { return this.get('/master/hijri-months'); },
    generateBills(data) { return this.post('/master/generate-bills', data); },

    // Cashier
    searchStudents(q) { return this.get('/cashier/search?q=' + encodeURIComponent(q)); },
    getStudentBills(id, type) { return this.get('/cashier/students/' + id + '/bills' + (type ? '?type=' + type : '')); },
    recordPayment(data) { return this.post('/cashier/payments', data); },
    getReceipt(id) { return this.get('/cashier/receipt/' + id); },
    getPaymentHistory(params = '') { return this.get('/cashier/payments' + (params ? '?' + params : '')); },

    // Treasurer
    recordIncome(data) { return this.post('/treasurer/income', data); },
    recordExpense(data) { return this.post('/treasurer/expense', data); },
    runDistribution(data) { return this.post('/treasurer/distribute', data); },
    previewDistribution() { return this.get('/treasurer/distribute'); },
    addDepartmentBalance(data) { return this.post('/treasurer/add-balance', data); },
    reverseTransaction(data) { return this.post('/treasurer/reversal', data); },
    getTransactions(params = '') { return this.get('/treasurer/transactions' + (params ? '?' + params : '')); },
    getDepartmentBalances() { return this.get('/treasurer/department-balances'); },
    getDistributionHistory(params = '') { return this.get('/treasurer/distribution-history' + (params ? '?' + params : '')); },

    // Reports
    getDashboard() { return this.get('/reports/dashboard'); },
    getPaymentReport(params) { return this.get('/reports/student-payments?' + params); },
    getOutstandingBills(params) { return this.get('/reports/outstanding-bills?' + params); },
    getOutstandingMonthlySummary() { return this.get('/reports/outstanding-monthly-summary'); },
    getMonthlyIncome(params) { return this.get('/reports/monthly-income?' + params); },
    getMonthlyExpense(params) { return this.get('/reports/monthly-expense?' + params); },
    getGeneralLedger(params) { return this.get('/reports/general-ledger?' + params); },
    getGeneralJournal(params) { return this.get('/reports/general-journal?' + params); },
    getFinancialSummary(params) { return this.get('/reports/financial-summary?' + params); },
    getDistributionHistoryReport(params) { return this.get('/reports/distribution-history?' + params); },

    // Excel download helper
    downloadExcel(reportEndpoint, params) {
        const url = this.base + '/reports/' + reportEndpoint + '?' + params + '&export=excel';
        window.open(url, '_blank');
    }
};
