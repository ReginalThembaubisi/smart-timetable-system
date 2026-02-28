</div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<!-- Confirmation Dialog -->
<div class="confirm-dialog" id="confirmDialog">
    <h3>Confirm Action</h3>
    <p id="confirmMessage">Are you sure you want to proceed?</p>
    <div class="confirm-dialog-actions">
        <button class="btn btn-cancel" onclick="closeConfirmDialog()">Cancel</button>
        <button class="btn btn-danger" id="confirmButton" onclick="confirmAction()">Confirm</button>
    </div>
</div>

<!-- Custom Popup Modal -->
<div id="customPopup"
    style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center;">
    <div
        style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.10); border-radius: 24px; padding: 32px; max-width: 480px; width: 90%; backdrop-filter: blur(20px); box-shadow: 0 8px 32px rgba(0,0,0,0.6); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
        <div id="customPopupIcon"
            style="width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; font-size: 24px;">
        </div>
        <h3 id="customPopupTitle"
            style="color: #e8edff; font-size: 20px; font-weight: 700; letter-spacing: -0.2px; margin-bottom: 12px; margin-top: 0;">
        </h3>
        <p id="customPopupMessage"
            style="color: rgba(220,230,255,0.85); font-size: 14px; line-height: 1.6; margin-bottom: 24px; margin-top: 0;">
        </p>
        <div id="customPopupButtons" style="display: flex; gap: 10px; justify-content: flex-end;"></div>
    </div>
</div>

<!-- Custom Confirm Modal -->
<div id="customConfirm"
    style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(8px); z-index: 10001; align-items: center; justify-content: center;">
    <div
        style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.10); border-radius: 24px; padding: 32px; max-width: 480px; width: 90%; backdrop-filter: blur(20px); box-shadow: 0 8px 32px rgba(0,0,0,0.6); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
        <div
            style="width: 48px; height: 48px; background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
            <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; color: #fca5a5;" fill="none"
                stroke="currentColor" stroke-width="1.7">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z">
                </path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
        </div>
        <h3
            style="color: #e8edff; font-size: 20px; font-weight: 700; letter-spacing: -0.2px; margin-bottom: 12px; margin-top: 0;">
            Confirm Action</h3>
        <p id="customConfirmMessage"
            style="color: rgba(220,230,255,0.85); font-size: 14px; line-height: 1.6; margin-bottom: 24px; margin-top: 0;">
        </p>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button onclick="closeCustomConfirm()"
                style="background: rgba(255,255,255,0.05); color: rgba(220,230,255,0.8); border: 1px solid rgba(255,255,255,0.12); padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);"
                onmouseover="this.style.background='rgba(255,255,255,0.08)'; this.style.borderColor='rgba(255,255,255,0.18)'"
                onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.borderColor='rgba(255,255,255,0.12)'">Cancel</button>
            <button id="customConfirmOk"
                style="background: rgba(239, 68, 68, 0.25); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);"
                onmouseover="this.style.background='rgba(239, 68, 68, 0.35)'; this.style.borderColor='rgba(239, 68, 68, 0.5)'"
                onmouseout="this.style.background='rgba(239, 68, 68, 0.25)'; this.style.borderColor='rgba(239, 68, 68, 0.4)'">Confirm</button>
        </div>
    </div>
</div>

<!-- Toasts -->
<div id="toastContainer"
    style="position:fixed; top:16px; right:16px; display:flex; flex-direction:column; gap:8px; z-index:70;"></div>

<script>
    // Custom Popup System
    let customConfirmCallback = null;

    function showCustomPopup(message, type = 'info', title = null) {
        const popup = document.getElementById('customPopup');
        const iconEl = document.getElementById('customPopupIcon');
        const titleEl = document.getElementById('customPopupTitle');
        const messageEl = document.getElementById('customPopupMessage');
        const buttonsEl = document.getElementById('customPopupButtons');

        // Set icon and colors based on type
        if (type === 'error') {
            iconEl.style.background = 'rgba(239, 68, 68, 0.2)';
            iconEl.style.border = '1px solid rgba(239, 68, 68, 0.3)';
            iconEl.innerHTML = '<svg viewBox="0 0 24 24" style="width: 24px; height: 24px; color: #fca5a5;" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>';
            titleEl.textContent = title || 'Error';
        } else if (type === 'success') {
            iconEl.style.background = 'rgba(16, 185, 129, 0.2)';
            iconEl.style.border = '1px solid rgba(16, 185, 129, 0.3)';
            iconEl.innerHTML = '<svg viewBox="0 0 24 24" style="width: 24px; height: 24px; color: #6ee7b7;" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 6 9 17l-5-5"/></svg>';
            titleEl.textContent = title || 'Success';
        } else if (type === 'warning') {
            iconEl.style.background = 'rgba(241, 196, 15, 0.2)';
            iconEl.style.border = '1px solid rgba(241, 196, 15, 0.3)';
            iconEl.innerHTML = '<svg viewBox="0 0 24 24" style="width: 24px; height: 24px; color: #fcd34d;" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
            titleEl.textContent = title || 'Warning';
        } else {
            iconEl.style.background = 'rgba(59, 130, 246, 0.2)';
            iconEl.style.border = '1px solid rgba(59, 130, 246, 0.3)';
            iconEl.innerHTML = '<svg viewBox="0 0 24 24" style="width: 24px; height: 24px; color: #93c5fd;" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>';
            titleEl.textContent = title || 'Information';
        }

        messageEl.textContent = message;
        buttonsEl.innerHTML = '<button onclick="closeCustomPopup()" style="background: rgba(59, 130, 246, 0.25); color: #dce3ff; border: 1px solid rgba(59, 130, 246, 0.4); padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);" onmouseover="this.style.background=\'rgba(59, 130, 246, 0.35)\'; this.style.borderColor=\'rgba(59, 130, 246, 0.5)\'" onmouseout="this.style.background=\'rgba(59, 130, 246, 0.25)\'; this.style.borderColor=\'rgba(59, 130, 246, 0.4)\'">OK</button>';

        popup.style.display = 'flex';
    }

    function closeCustomPopup() {
        document.getElementById('customPopup').style.display = 'none';
    }

    function showCustomConfirm(message, onConfirm) {
        const confirmEl = document.getElementById('customConfirm');
        const messageEl = document.getElementById('customConfirmMessage');
        const okBtn = document.getElementById('customConfirmOk');

        messageEl.textContent = message;
        customConfirmCallback = onConfirm;

        // Remove old event listener and add new one
        const newOkBtn = okBtn.cloneNode(true);
        okBtn.parentNode.replaceChild(newOkBtn, okBtn);
        newOkBtn.onclick = () => {
            if (customConfirmCallback) {
                customConfirmCallback();
            }
            closeCustomConfirm();
        };

        confirmEl.style.display = 'flex';
    }

    function closeCustomConfirm() {
        document.getElementById('customConfirm').style.display = 'none';
        customConfirmCallback = null;
    }

    // Close popups on background click
    document.getElementById('customPopup').addEventListener('click', function (e) {
        if (e.target === this) closeCustomPopup();
    });
    document.getElementById('customConfirm').addEventListener('click', function (e) {
        if (e.target === this) closeCustomConfirm();
    });

    // Close popups on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeCustomPopup();
            closeCustomConfirm();
        }
    });

    // Shared helpers: tables (sort/search/paginate), filters, bulk toolbar, simple toasts, form validation
    const UI = (() => {
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const el = document.createElement('div');
            el.className = 'card';
            el.style.padding = '10px 12px';
            el.style.border = '1px solid var(--border-color)';
            el.style.borderLeft = type === 'error' ? '3px solid #ef4444' : '3px solid #10b981';
            el.style.background = 'var(--bg-secondary)';
            el.style.color = 'var(--text-primary)';
            el.style.boxShadow = 'var(--shadow-card)';
            el.textContent = message;
            container.appendChild(el);
            setTimeout(() => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-6px)';
                setTimeout(() => el.remove(), 250);
            }, 2200);
        }
        function debounce(fn, ms = 250) {
            let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
        }
        function textContent(el) { return (el?.textContent || '').trim().toLowerCase(); }
        function cmp(a, b, numeric = false) {
            if (numeric) return (parseFloat(a) || 0) - (parseFloat(b) || 0);
            return a.localeCompare(b);
        }
        function paginate(array, page, perPage) {
            if (perPage === 'All') return array;
            const start = (page - 1) * perPage; return array.slice(start, start + perPage);
        }
        function buildPagination(container, total, page, perPage, onChange) {
            container.innerHTML = '';
            if (perPage === 'All') return;
            const pages = Math.max(1, Math.ceil(total / perPage));
            for (let i = 1; i <= pages; i++) {
                const btn = document.createElement('button');
                btn.textContent = i;
                btn.className = 'btn' + (i === page ? '' : ' btn-cancel');
                btn.style.padding = '6px 10px'; btn.style.fontSize = '12px';
                btn.addEventListener('click', () => onChange(i));
                container.appendChild(btn);
            }
        }
        function initTable(tableEl, opts = {}) {
            if (!tableEl) return;
            const tbody = tableEl.querySelector('tbody');
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll('tr'));
            let state = {
                sortIndex: null, sortDir: 'asc',
                search: '', filters: {}, page: 1, perPage: 10
            };
            const perPageSelect = document.querySelector(opts.perPageSelector || '[data-page-size]');
            const searchInput = document.querySelector(opts.searchSelector || '[data-table-search]');
            const paginationContainer = document.querySelector(opts.paginationSelector || '[data-pagination]');
            const filterSelectors = opts.filterSelectors || {};

            function applyFilters(row) {
                // Search across cells
                if (state.search) {
                    const hay = textContent(row);
                    if (!hay.includes(state.search)) return false;
                }
                // Specific filters by data attributes (e.g., data-program, data-year)
                for (const [key, selector] of Object.entries(filterSelectors)) {
                    const val = state.filters[key];
                    if (!val || val === '') continue;
                    const cell = row.querySelector(selector);
                    if (!cell) continue;
                    const cellVal = (cell.getAttribute('data-value') || textContent(cell)).toLowerCase();
                    if (Array.isArray(val)) {
                        if (val.length && !val.map(v => String(v).toLowerCase()).includes(cellVal)) return false;
                    } else {
                        if (String(val).toLowerCase() !== '' && !cellVal.includes(String(val).toLowerCase())) return false;
                    }
                }
                return true;
            }
            function applySort(a, b) {
                if (state.sortIndex === null) return 0;
                const aCell = a.children[state.sortIndex], bCell = b.children[state.sortIndex];
                const aText = (aCell?.getAttribute('data-value') || textContent(aCell));
                const bText = (bCell?.getAttribute('data-value') || textContent(bCell));
                const numeric = aCell?.hasAttribute('data-numeric') || bCell?.hasAttribute('data-numeric');
                const res = cmp(aText.toLowerCase(), bText.toLowerCase(), numeric);
                return state.sortDir === 'asc' ? res : -res;
            }
            function updateAria(ths) {
                ths.forEach((th, idx) => {
                    th.setAttribute('role', 'columnheader');
                    th.setAttribute('tabindex', '0');
                    if (idx === state.sortIndex) {
                        th.setAttribute('aria-sort', state.sortDir === 'asc' ? 'ascending' : 'descending');
                    } else {
                        th.setAttribute('aria-sort', 'none');
                    }
                });
            }
            function render() {
                let filtered = rows.filter(r => applyFilters(r)).sort(applySort);
                const perPage = state.perPage === 'All' ? 'All' : parseInt(state.perPage, 10);
                const pageRows = paginate(filtered, state.page, perPage);
                tbody.innerHTML = '';
                pageRows.forEach(r => tbody.appendChild(r));
                if (paginationContainer) {
                    buildPagination(paginationContainer, filtered.length, state.page, perPage, (p) => { state.page = p; render(); });
                }
            }
            // Sorting by header click
            const ths = Array.from(tableEl.querySelectorAll('thead th'));
            ths.forEach((th, idx) => {
                th.style.cursor = 'pointer';
                const toggle = () => {
                    if (state.sortIndex === idx) {
                        state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        state.sortIndex = idx; state.sortDir = 'asc';
                    }
                    state.page = 1; render(); updateAria(ths);
                };
                th.addEventListener('click', toggle);
                th.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggle();
                    }
                });
            });
            updateAria(ths);
            // Search
            if (searchInput) {
                const onSearch = debounce(() => { state.search = searchInput.value.trim().toLowerCase(); state.page = 1; render(); }, 200);
                searchInput.addEventListener('input', onSearch);
            }
            // Per-page
            if (perPageSelect) {
                perPageSelect.addEventListener('change', () => { state.perPage = perPageSelect.value; state.page = 1; render(); });
            }
            // External filters
            Object.entries(filterSelectors).forEach(([key, selector]) => {
                const el = document.querySelector(`[data-filter="${key}"]`);
                if (!el) return;
                const handler = () => {
                    if (el.tagName === 'SELECT' && el.multiple) {
                        state.filters[key] = Array.from(el.selectedOptions).map(o => o.value);
                    } else {
                        state.filters[key] = el.value;
                    }
                    state.page = 1; render();
                };
                el.addEventListener('change', handler);
            });
            render();
        }
        function initBulkToolbar(containerSelector, tableSelector, formSelector) {
            const bar = document.querySelector(containerSelector);
            const table = document.querySelector(tableSelector);
            const form = document.querySelector(formSelector);
            if (!bar || !table || !form) return;
            const selectAll = table.querySelector('#selectAll');
            const itemCheckboxes = table.querySelectorAll('.item-checkbox');
            const countEl = bar.querySelector('[data-selected-count]');
            const deleteBtn = bar.querySelector('[data-bulk-delete]');
            const exportBtn = bar.querySelector('[data-bulk-export]');
            function update() {
                const checked = table.querySelectorAll('.item-checkbox:checked').length;
                countEl.textContent = checked;
                bar.style.display = checked > 0 ? 'flex' : 'none';
                if (selectAll) {
                    selectAll.checked = checked > 0 && checked === itemCheckboxes.length;
                }
            }
            if (selectAll) {
                selectAll.addEventListener('change', () => {
                    table.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = selectAll.checked);
                    update();
                });
            }
            itemCheckboxes.forEach(cb => cb.addEventListener('change', update));
            if (deleteBtn) {
                deleteBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const count = table.querySelectorAll('.item-checkbox:checked').length;
                    if (!count) return;
                    showCustomConfirm(`Delete ${count} selected item(s)? This cannot be undone.`, () => {
                        form.submit();
                    });
                });
            }
            if (exportBtn) {
                exportBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    // Simple CSV export of visible rows
                    const rows = [];
                    table.querySelectorAll('thead tr').forEach(tr => {
                        rows.push(Array.from(tr.children).slice(1).map(th => `"${(th.textContent || '').trim().replace(/"/g, '""')}"`).join(','));
                    });
                    table.querySelectorAll('tbody tr').forEach(tr => {
                        if (tr.querySelector('.item-checkbox:checked')) {
                            rows.push(Array.from(tr.children).slice(1, -1).map(td => `"${(td.textContent || '').trim().replace(/"/g, '""')}"`).join(','));
                        }
                    });
                    const blob = new Blob([rows.join('\\n')], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url; a.download = 'export.csv'; a.click(); URL.revokeObjectURL(url);
                });
            }
            update();
        }
        function validateForms() {
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function (e) {
                    const required = Array.from(form.querySelectorAll('[required]'));
                    let ok = true;
                    required.forEach(el => {
                        if (!el.value.trim()) {
                            ok = false;
                            el.style.borderColor = '#ef4444';
                        } else {
                            el.style.borderColor = '';
                        }
                    });
                    // Relaxed email validation for demo - just check for @ symbol if provided
                    const emailEl = form.querySelector('input[name="email"], input[type="email"]');
                    if (emailEl && emailEl.value && emailEl.value.trim() !== '') {
                        // Just check if it contains @ symbol (very lenient for demo)
                        if (!emailEl.value.includes('@')) {
                            ok = false;
                            emailEl.style.borderColor = '#ef4444';
                            showCustomPopup('Email should contain @ symbol', 'warning');
                        } else {
                            emailEl.style.borderColor = '';
                        }
                    }
                    if (!ok) {
                        e.preventDefault();
                    }
                });
            });
        }
        return { initTable, initBulkToolbar, validateForms, showToast };
    })();

    // Mobile menu toggle
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const isOpen = sidebar.classList.toggle('open');
        if (backdrop) backdrop.classList.toggle('open', isOpen);
    }

    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('open');
    }

    // Close sidebar on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });

    // Loading overlay
    function showLoading() {
        document.getElementById('loadingOverlay').classList.add('active');
    }

    function hideLoading() {
        document.getElementById('loadingOverlay').classList.remove('active');
    }

    // Confirmation dialog
    let confirmCallback = null;

    function showConfirmDialog(message, callback) {
        // Use custom confirm popup instead
        showCustomConfirm(message, callback);
    }

    function closeConfirmDialog() {
        closeCustomConfirm();
    }

    function confirmAction() {
        if (customConfirmCallback) {
            customConfirmCallback();
        }
        closeCustomConfirm();
    }

    // Enhanced delete confirmation
    document.addEventListener('DOMContentLoaded', function () {
        const deleteLinks = document.querySelectorAll('a[href*="delete"], a.btn-danger, .delete-link');

        deleteLinks.forEach(link => {
            if (link.hasAttribute('onclick')) return; // Skip if already has onclick handler

            link.addEventListener('click', function (e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                const itemName = this.closest('tr')?.querySelector('td:not(:first-child):not(:last-child)')?.textContent?.trim() || 'this item';

                showCustomConfirm('Are you sure you want to delete ' + itemName + '? This action cannot be undone.', function () {
                    showLoading();
                    window.location.href = href;
                });
            });
        });

        // Auto-hide alerts
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });

        // Show toast from ?notice= param
        (function () {
            const params = new URLSearchParams(window.location.search);
            const notice = params.get('notice');
            if (notice) {
                const map = {
                    'session_created': 'Session created successfully',
                    'session_deleted': 'Session deleted'
                };
                const msg = map[notice] || notice.replace(/_/g, ' ');
                if (window.UI && msg) UI.showToast(msg);
            }
        })();

        // Live system status ping (if hero chip exists)
        (function () {
            const dot = document.getElementById('system-status-dot');
            const text = document.getElementById('system-status-text');
            const chip = document.getElementById('system-status-chip');
            if (!dot || !text || !chip) return;
            function setStatus(ok, msg) {
                if (ok) {
                    dot.style.background = '#10b981';
                    text.textContent = msg || 'System all good';
                    chip.style.borderColor = 'rgba(16,185,129,0.35)';
                } else {
                    dot.style.background = '#ef4444';
                    text.textContent = msg || 'Degraded';
                    chip.style.borderColor = 'rgba(239,68,68,0.35)';
                }
            }
            async function ping() {
                try {
                    // Try clean route first
                    const tries = [
                        // Local admin fallback (DB ping)
                        'health_check.php',
                        '../api/health',
                        '../api/index.php/health',
                        '/public/api/health',
                        '/public/api/index.php/health'
                    ];
                    let res, ok = false;
                    for (const url of tries) {
                        try {
                            res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            if (res.ok) { ok = true; break; }
                        } catch (_) { /* try next */ }
                    }
                    if (ok) {
                        const data = await res.json();
                        setStatus(data?.status === 'ok');
                    } else {
                        throw new Error('bad');
                    }
                } catch (e) {
                    setStatus(false);
                }
            }
            ping();
            setInterval(ping, 30000);
        })();

        // Form submission loading - hide after navigation or timeout
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function (e) {
                showLoading();
                // Hide loading after 5 seconds as safety measure
                setTimeout(() => {
                    hideLoading();
                }, 5000);
                // Also hide on page unload
                window.addEventListener('beforeunload', () => {
                    hideLoading();
                });
            });
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href !== '#' && href.length > 1) {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }
            });
        });
    });

    // Add ripple effect to buttons
    document.addEventListener('DOMContentLoaded', function () {
        const buttons = document.querySelectorAll('.btn, button[type="submit"]');

        buttons.forEach(button => {
            button.addEventListener('click', function (e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;

                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');

                this.appendChild(ripple);

                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
        // Global enable simple validation
        UI.validateForms();

        // Local time chip updater and greeting (using client's timezone)
        (function () {
            const timeEl = document.getElementById('local-time');
            const dateEl = document.getElementById('local-date');
            const greetingEl = document.getElementById('greeting-text');

            function getGreeting(hour) {
                if (hour >= 5 && hour < 12) return 'Good Morning';
                if (hour >= 12 && hour < 17) return 'Good Afternoon';
                if (hour >= 17 && hour < 21) return 'Good Evening';
                return 'Good Night';
            }

            function formatDate(date) {
                const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                const day = days[date.getDay()];
                const month = months[date.getMonth()];
                const dayNum = date.getDate();
                const year = date.getFullYear();
                return `${day}, ${month} ${dayNum}, ${year}`;
            }

            function tick() {
                try {
                    const now = new Date(); // Uses client's local timezone automatically
                    const hour = now.getHours();

                    if (timeEl) {
                        timeEl.textContent = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true });
                    }

                    if (dateEl) {
                        dateEl.textContent = formatDate(now);
                    }

                    if (greetingEl) {
                        greetingEl.textContent = getGreeting(hour);
                    }
                } catch (e) { }
            }
            tick();
            setInterval(tick, 1000); // update every second for smooth time display
        })();
    });
</script>

<style>
    /* Ripple effect */
    .btn,
    button[type="submit"] {
        position: relative;
        overflow: hidden;
    }

    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: scale(0);
        animation: ripple-animation 0.6s ease-out;
        pointer-events: none;
    }

    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
</style>
</body>

</html>