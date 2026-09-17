document.addEventListener('DOMContentLoaded', function() {
    // One-time purge of the retired "device name for activity log" value from
    // this browser (it could contain sensitive text a user typed by mistake).
    try {
        document.cookie = 'activity_device_name=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax';
        localStorage.removeItem('activityDeviceName');
        localStorage.removeItem('activityDeviceNamePrompted');
    } catch (e) { /* ignore */ }

    // ---- Notification bell ----
    // mode "open"     (Admin/User): records flagged for update, needing action.
    // mode "resolved" (Billing):    records they flagged that have been updated.
    (function initUpdateNotifications() {
        const bell = document.getElementById('notifBell');
        const menu = document.getElementById('notifMenu');
        const badge = document.getElementById('notifBadge');
        const headerCount = document.getElementById('notifHeaderCount');
        const empty = document.getElementById('notifEmpty');
        if (!bell || !menu || !empty) return;
        const mode = bell.dataset.mode || 'open';

        function esc(v) {
            return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function setBadge(n) {
            if (headerCount) headerCount.textContent = String(n);
            if (badge) {
                if (n > 0) { badge.textContent = n > 99 ? '99+' : String(n); badge.classList.remove('d-none'); }
                else { badge.classList.add('d-none'); }
            }
        }

        function render(items) {
            menu.querySelectorAll('.notif-item').forEach(el => el.remove());
            if (!items.length) { empty.style.display = ''; return; }
            empty.style.display = 'none';
            items.slice(0, 15).forEach(function (f) {
                const li = document.createElement('li');
                li.className = 'notif-item';
                const head = `#${esc(f.entry_id)} · ${esc(f.entry_type || '-')}${f.waybill ? ' · WB ' + esc(f.waybill) : ''}`;
                if (mode === 'resolved') {
                    li.innerHTML = `<span class="dropdown-item-text">
                        <div class="small ${f.seen ? '' : 'fw-semibold'}">${head} <span class="badge bg-success ms-1">updated</span></div>
                        <div class="text-muted small text-truncate" style="max-width: 300px;">was: ${esc(f.remarks || '')}</div>
                    </span>`;
                } else {
                    li.innerHTML = `<a class="dropdown-item" href="${esc(f.edit_url)}" title="Open the record to update it">
                        <div class="fw-semibold small">${head}</div>
                        <div class="text-muted small text-truncate" style="max-width: 300px;">${esc(f.remarks || '')}</div>
                    </a>`;
                }
                menu.insertBefore(li, empty);
            });
        }

        function load() {
            const url = mode === 'resolved'
                ? 'php/fetch/get_flag_notifications.php'
                : 'php/fetch/get_update_flags.php?status=open';
            fetch(url, { cache: 'no-store' })
                .then(r => r.json())
                .then(d => {
                    if (!d || !d.success) return;
                    const items = d.flags || [];
                    render(items);
                    setBadge(mode === 'resolved' ? (d.unseen_count || 0) : items.length);
                })
                .catch(() => {});
        }

        bell.addEventListener('click', function () {
            if (mode === 'resolved') {
                // Opening the bell acknowledges the updates, then reload (badge clears).
                fetch('php/update/ack_flag_notifications.php', { method: 'POST' })
                    .then(() => load())
                    .catch(() => load());
            } else {
                load();
            }
        });
        load();
    })();

    const sidebarCollapse = document.getElementById('sidebarCollapse');
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');
    const togglePassword = document.getElementById('togglePassword');
    const loginForm = document.getElementById('loginForm');
    const personalInfoForm = document.getElementById('personalInfoForm');
    const passwordForm = document.getElementById('passwordForm');
    const addUserForm = document.getElementById('addUserForm');
    const editUserForm = document.getElementById('editUserForm');
    const selectAll = document.getElementById('selectAll');
    const selectAllUsers = document.getElementById('selectAllUsers');

    // Restore sidebar state from localStorage on page load
    if (sidebar) {
        const sidebarState = localStorage.getItem('sidebarCollapsed');
        if (sidebarState === 'true') {
            sidebar.classList.add('collapsed');
            if (content) {
                content.classList.add('expanded');
            }
        }
    }

    if (sidebarCollapse) {
        sidebarCollapse.addEventListener('click', function() {
            if (sidebar) {
                sidebar.classList.toggle('collapsed');
                // Save sidebar state to localStorage
                const isCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', isCollapsed);
            }
            if (content) {
                content.classList.toggle('expanded');
            }
        });
    }

    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const passwordInput = document.getElementById('pass') || document.getElementById('password');
            const icon = this.querySelector('i');

            if (passwordInput && passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else if (passwordInput) {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    }

    if (personalInfoForm) {
        personalInfoForm.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Personal information updated successfully!');
        });
    }

    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (newPassword !== confirmPassword) {
                alert('New passwords do not match!');
                return;
            }

            if (newPassword.length < 8) {
                alert('Password must be at least 8 characters long!');
                return;
            }

            alert('Password updated successfully!');
            passwordForm.reset();
        });
    }

    if (addUserForm) {
        addUserForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const firstName = document.getElementById('newFirstName').value;
            const lastName = document.getElementById('newLastName').value;
            const email = document.getElementById('newEmail').value;
            const password = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('newConfirmPassword').value;

            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return;
            }

            alert(`User ${firstName} ${lastName} added successfully!`);

            addUserForm.reset();

            const modal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
            modal.hide();
        });
    }

    if (editUserForm) {
        editUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('User information updated successfully!');

            const modal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
            modal.hide();
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    if (selectAllUsers) {
        selectAllUsers.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('tbody .form-check-input');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#entriesTable tbody tr');

            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    const searchUsers = document.getElementById('searchUsers');
    if (searchUsers) {
        searchUsers.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');

            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    const filterButtons = document.querySelectorAll('[data-filter]');
    filterButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const filter = this.getAttribute('data-filter');
            const tableRows = document.querySelectorAll('tbody tr');

            tableRows.forEach(row => {
                if (filter === 'all') {
                    row.style.display = '';
                } else {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(filter.toLowerCase())) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });
    });

    const now = new Date();
    const currentDate = `${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getDate()).padStart(2, '0')}/${now.getFullYear()}`;
    const dateInput = document.getElementById('entryDate');
    if (dateInput) {
        dateInput.value = currentDate;
    }

    function formatManualDateInput(rawValue) {
        const trimmed = String(rawValue || '').trim();
        if (!trimmed) {
            return { valid: true, formatted: '' };
        }

        const normalized = trimmed.replace(/\s+/g, '').replace(/-/g, '/');
        const parts = normalized.split('/');

        if (parts.length < 2 || parts.length > 3 || parts.some(part => part === '')) {
            return { valid: false, message: 'Use M/D or M/D/YYYY.' };
        }

        const allNumeric = parts.every(part => /^\d+$/.test(part));
        if (!allNumeric) {
            return { valid: false, message: 'Date must contain numbers only.' };
        }

        let month;
        let day;
        let year;

        if (parts.length === 3 && parts[0].length === 4) {
            year = Number.parseInt(parts[0], 10);
            month = Number.parseInt(parts[1], 10);
            day = Number.parseInt(parts[2], 10);
        } else {
            month = Number.parseInt(parts[0], 10);
            day = Number.parseInt(parts[1], 10);
            year = new Date().getFullYear();

            if (parts[2] !== undefined) {
                if (parts[2].length !== 4) {
                    return { valid: false, message: 'Year must use YYYY format.' };
                }
                year = Number.parseInt(parts[2], 10);
            }
        }

        if (month < 1 || month > 12 || day < 1 || day > 31) {
            return { valid: false, message: 'Enter a valid calendar date.' };
        }

        const candidate = new Date(year, month - 1, day);
        const isValidDate =
            candidate.getFullYear() === year &&
            candidate.getMonth() === month - 1 &&
            candidate.getDate() === day;

        if (!isValidDate) {
            return { valid: false, message: 'Enter a valid calendar date.' };
        }

        return {
            valid: true,
            formatted: [
                String(month).padStart(2, '0'),
                String(day).padStart(2, '0'),
                String(year).padStart(4, '0')
            ].join('/')
        };
    }

    function applyManualDateFormatting(input, shouldReportValidity) {
        if (!input) {
            return true;
        }

        const result = formatManualDateInput(input.value);

        if (!result.valid) {
            input.setCustomValidity(result.message || 'Invalid date');
            input.classList.add('is-invalid');
            if (shouldReportValidity) {
                input.reportValidity();
            }
            return false;
        }

        input.setCustomValidity('');
        input.classList.remove('is-invalid');
        input.value = result.formatted;
        return true;
    }

    function prepareManualDateInput(input) {
        if (!input) {
            return;
        }

        input.type = 'text';
        input.inputMode = 'numeric';
        input.autocomplete = 'off';

        if (!input.placeholder) {
            input.placeholder = 'M/D or M/D/YYYY';
        }
    }

    function isManualDateField(target) {
        return !!(target && target.matches && target.matches('#dataEntryForm input[data-manual-date="true"]'));
    }

    function formatManualTimeInput(rawValue) {
        const trimmed = String(rawValue || '').trim();
        if (!trimmed) {
            return { valid: true, formatted: '' };
        }

        if (/^\d{2}:\d{2}$/.test(trimmed)) {
            const [hourText, minuteText] = trimmed.split(':');
            const hour = Number.parseInt(hourText, 10);
            const minute = Number.parseInt(minuteText, 10);

            if (hour >= 0 && hour <= 23 && minute >= 0 && minute <= 59) {
                return {
                    valid: true,
                    formatted: `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`
                };
            }

            return { valid: false, message: 'Enter a valid time.' };
        }

        const digits = trimmed.replace(/\s+/g, '');
        if (!/^\d{3,4}$/.test(digits)) {
            return { valid: false, message: 'Use HHMM or HH:MM.' };
        }

        const normalized = digits.length === 3 ? `0${digits}` : digits;
        const hour = Number.parseInt(normalized.slice(0, 2), 10);
        const minute = Number.parseInt(normalized.slice(2, 4), 10);

        if (hour < 0 || hour > 23 || minute < 0 || minute > 59) {
            return { valid: false, message: 'Enter a valid time.' };
        }

        return {
            valid: true,
            formatted: `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`
        };
    }

    function applyManualTimeFormatting(input, shouldReportValidity) {
        if (!input) {
            return true;
        }

        const result = formatManualTimeInput(input.value);

        if (!result.valid) {
            input.setCustomValidity(result.message || 'Invalid time');
            input.classList.add('is-invalid');
            if (shouldReportValidity) {
                input.reportValidity();
            }
            return false;
        }

        input.setCustomValidity('');
        input.classList.remove('is-invalid');
        input.value = result.formatted;
        return true;
    }

    function prepareManualTimeInput(input) {
        if (!input) {
            return;
        }

        input.type = 'text';
        input.inputMode = 'numeric';
        input.autocomplete = 'off';

        if (!input.placeholder) {
            input.placeholder = 'HHMM';
        }
    }

    function isManualTimeField(target) {
        return !!(target && target.matches && target.matches('#dataEntryForm input[data-manual-time="true"]'));
    }

    function formatManualDateTimeInput(rawValue) {
        const trimmed = String(rawValue || '').trim();
        if (!trimmed) {
            return { valid: true, formatted: '' };
        }

        const compactMatch = trimmed.match(/^(\d{1,2}[\/-]\d{1,2}(?:[\/-]\d{4})?)\s+(\d{3,4})$/);
        if (compactMatch) {
            const dateResult = formatManualDateInput(compactMatch[1]);
            const timeResult = formatManualTimeInput(compactMatch[2]);

            if (!dateResult.valid) {
                return dateResult;
            }
            if (!timeResult.valid) {
                return timeResult;
            }

            return {
                valid: true,
                formatted: `${dateResult.formatted} ${timeResult.formatted}`
            };
        }

        const normalized = trimmed.replace('T', ' ');
        const isoMatch = normalized.match(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})$/);
        if (isoMatch) {
            const dateResult = formatManualDateInput(isoMatch[1]);
            const timeResult = formatManualTimeInput(isoMatch[2]);

            if (!dateResult.valid) {
                return dateResult;
            }
            if (!timeResult.valid) {
                return timeResult;
            }

            return {
                valid: true,
                formatted: `${dateResult.formatted} ${timeResult.formatted}`
            };
        }

        const parts = normalized.split(/\s+/).filter(Boolean);
        if (parts.length !== 2) {
            return { valid: false, message: 'Use MM/DD/YYYY HHMM or MM/DD/YYYY HH:MM.' };
        }

        const dateResult = formatManualDateInput(parts[0]);
        if (!dateResult.valid) {
            return dateResult;
        }

        const timeResult = formatManualTimeInput(parts[1]);
        if (!timeResult.valid) {
            return timeResult;
        }

        return {
            valid: true,
            formatted: `${dateResult.formatted} ${timeResult.formatted}`
        };
    }

    function applyManualDateTimeFormatting(input, shouldReportValidity) {
        if (!input) {
            return true;
        }

        const result = formatManualDateTimeInput(input.value);

        if (!result.valid) {
            input.setCustomValidity(result.message || 'Invalid date and time');
            input.classList.add('is-invalid');
            if (shouldReportValidity) {
                input.reportValidity();
            }
            return false;
        }

        input.setCustomValidity('');
        input.classList.remove('is-invalid');
        input.value = result.formatted;
        return true;
    }

    function prepareManualDateTimeInput(input) {
        if (!input) {
            return;
        }

        input.type = 'text';
        input.inputMode = 'text';
        input.autocomplete = 'off';

        if (!input.placeholder) {
            input.placeholder = 'M/D HHMM';
        }
    }

    function isManualDateTimeField(target) {
        return !!(target && target.matches && target.matches('#dataEntryForm input[data-manual-datetime="true"]'));
    }

    document.querySelectorAll('#dataEntryForm input[data-manual-date="true"]').forEach(prepareManualDateInput);
    document.querySelectorAll('#dataEntryForm input[data-manual-time="true"]').forEach(prepareManualTimeInput);
    document.querySelectorAll('#dataEntryForm input[data-manual-datetime="true"]').forEach(prepareManualDateTimeInput);

    document.addEventListener('input', function(event) {
        if (!isManualDateField(event.target)) {
            if (!isManualTimeField(event.target)) {
                if (!isManualDateTimeField(event.target)) {
                    return;
                }
            }
        }

        event.target.setCustomValidity('');
        event.target.classList.remove('is-invalid');
    });

    document.addEventListener('focusout', function(event) {
        if (isManualDateField(event.target)) {
            applyManualDateFormatting(event.target, true);
            return;
        }

        if (isManualTimeField(event.target)) {
            applyManualTimeFormatting(event.target, true);
            return;
        }

        if (isManualDateTimeField(event.target)) {
            applyManualDateTimeFormatting(event.target, true);
        }
    });

    document.addEventListener('change', function(event) {
        if (isManualDateField(event.target)) {
            applyManualDateFormatting(event.target, false);
            return;
        }

        if (isManualTimeField(event.target)) {
            applyManualTimeFormatting(event.target, false);
            return;
        }

        if (isManualDateTimeField(event.target)) {
            applyManualDateTimeFormatting(event.target, false);
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key !== 'Enter') {
            return;
        }

        if (isManualDateField(event.target)) {
            applyManualDateFormatting(event.target, true);
            return;
        }

        if (isManualTimeField(event.target)) {
            applyManualTimeFormatting(event.target, true);
            return;
        }

        if (isManualDateTimeField(event.target)) {
            applyManualDateTimeFormatting(event.target, true);
        }
    });
});
