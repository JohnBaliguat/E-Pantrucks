<?php
/**
 * Shared main content + script for the "Trip Receipt Status" monitoring page.
 * Included by public/Admin/trip_receipt_status.php and
 * public/User/trip_receipt_status.php after each has rendered its own
 * sidebar/navbar. Reconciles encoded waybills against dispatched trip receipts
 * via php/fetch/get_trip_receipt_status.php.
 */
?>
<style>
    .trs-stat {
        border-radius: 1rem;
        border: 1px solid #e9ecef;
        background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
        padding: 1rem 1.25rem;
        cursor: pointer;
        transition: box-shadow .15s, transform .15s;
    }
    .trs-stat:hover { box-shadow: 0 8px 22px rgba(15,23,42,.08); transform: translateY(-1px); }
    .trs-stat.active { outline: 2px solid #0d6efd; outline-offset: 1px; }
    .trs-muted { color: #6c757d; font-size: .875rem; }
    .trs-card { border: 1px solid rgba(13,110,253,.08); box-shadow: 0 10px 30px rgba(15,23,42,.05); }
    .badge-matched { background:#198754; }
    .badge-dne { background:#fd7e14; }        /* dispatched, not encoded */
    .badge-end { background:#6f42c1; }         /* encoded, not dispatched */
    tr.row-dne > * { background-color:#fff4e6 !important; }
    tr.row-end > * { background-color:#f3edff !important; }
</style>

<div class="main-content">
    <div class="content-header mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h2 class="fw-bold mb-1">Trip Receipt Status</h2>
                <p class="text-muted mb-0">Reconciles <strong>dispatched</strong> trip receipts against <strong>encoded</strong> waybills — so you can spot receipts dispatched but not yet encoded, and entries encoded but never dispatched.</p>
            </div>
            <div class="d-flex align-items-end gap-2">
                <div>
                    <label class="trs-muted d-block" for="trsDays">Window</label>
                    <select id="trsDays" class="form-select form-select-sm" style="min-width:150px">
                        <option value="7" selected>Last 7 days</option>
                        <option value="14">Last 14 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="60">Last 60 days</option>
                    </select>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="trsRefresh">
                    <i class="bi bi-arrow-repeat me-1"></i>Refresh
                </button>
            </div>
        </div>
        <div class="trs-muted mt-2">Last update: <span id="trsUpdated">Loading…</span></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="trs-stat active" data-trs-filter="all">
                <div class="trs-muted">All Trip Receipts</div>
                <div class="fs-3 fw-bold" id="trsTotal">0</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="trs-stat" data-trs-filter="dispatched_not_encoded">
                <div class="trs-muted"><i class="bi bi-exclamation-triangle text-warning me-1"></i>Dispatched, Not Encoded</div>
                <div class="fs-3 fw-bold text-warning" id="trsDne">0</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="trs-stat" data-trs-filter="encoded_not_dispatched">
                <div class="trs-muted"><i class="bi bi-question-circle me-1" style="color:#6f42c1"></i>Encoded, Not Dispatched</div>
                <div class="fs-3 fw-bold" style="color:#6f42c1" id="trsEnd">0</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="trs-stat" data-trs-filter="matched">
                <div class="trs-muted"><i class="bi bi-check-circle text-success me-1"></i>Encoded &amp; Dispatched</div>
                <div class="fs-3 fw-bold text-success" id="trsMatched">0</div>
            </div>
        </div>
    </div>

    <div class="card trs-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Reconciliation</h5>
            <span class="trs-muted" id="trsShowing">Showing all</span>
        </div>
        <div class="card-body">
            <div id="trsError" class="alert alert-warning d-none" role="alert"></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="trsTable" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>Trip Receipt No.</th>
                            <th>Status</th>
                            <th>Entry Type</th>
                            <th>Customer</th>
                            <th>Driver</th>
                            <th>Truck / Van</th>
                            <th>Dispatched</th>
                            <th>Encoded</th>
                            <th>Dispatch Stage</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = id => document.getElementById(id);
    const daysSel = el('trsDays');
    const refreshBtn = el('trsRefresh');
    const errBox = el('trsError');
    let currentFilter = 'all';
    let allRecords = [];

    function esc(v) {
        return String(v ?? '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    function fmtStamp(v) {
        if (!v) return '<span class="text-muted">—</span>';
        const d = new Date(v.replace(' ', 'T'));
        if (isNaN(d.getTime())) return esc(v);
        const p = n => String(n).padStart(2,'0');
        return `${p(d.getMonth()+1)}/${p(d.getDate())}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`;
    }

    function statusBadge(s) {
        if (s === 'matched') return '<span class="badge badge-matched">Encoded &amp; Dispatched</span>';
        if (s === 'dispatched_not_encoded') return '<span class="badge badge-dne">Dispatched, Not Encoded</span>';
        return '<span class="badge badge-end">Encoded, Not Dispatched</span>';
    }

    function yesNo(flag) {
        return flag
            ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i></span>'
            : '<span class="text-muted"><i class="bi bi-dash-circle"></i></span>';
    }

    const table = new DataTable('#trsTable', {
        pageLength: 25,
        order: [],
        columns: [
            { data: 'tr' }, { data: 'status' }, { data: 'entry_type' },
            { data: 'customer' }, { data: 'driver' }, { data: 'truck' },
            { data: 'dispatched' }, { data: 'encoded' }, { data: 'stage' }
        ]
    });

    function rowClass(s) {
        if (s === 'dispatched_not_encoded') return 'row-dne';
        if (s === 'encoded_not_dispatched') return 'row-end';
        return '';
    }

    function render() {
        const rows = allRecords
            .filter(r => currentFilter === 'all' || r.status === currentFilter)
            .map(r => ({
                tr: '<strong>' + esc(r.trip_receipt) + '</strong>',
                status: statusBadge(r.status),
                entry_type: r.entry_type ? esc(r.entry_type) : '<span class="text-muted">—</span>',
                customer: r.customer ? esc(r.customer) : '<span class="text-muted">—</span>',
                driver: r.driver ? esc(r.driver) : '<span class="text-muted">—</span>',
                truck: r.truck ? esc(r.truck) : '<span class="text-muted">—</span>',
                dispatched: yesNo(r.dispatched) + ' <span class="trs-muted">' + fmtStamp(r.dispatch_date) + '</span>',
                encoded: yesNo(r.encoded) + ' <span class="trs-muted">' + (r.encoded_date ? esc(r.encoded_date) : '') + '</span>',
                stage: r.workflow_stage ? esc(r.workflow_stage) : '<span class="text-muted">—</span>',
                DT_RowClass: rowClass(r.status)
            }));
        table.clear();
        table.rows.add(rows);
        table.draw();

        const labels = {
            all: 'Showing all',
            dispatched_not_encoded: 'Showing dispatched, not encoded',
            encoded_not_dispatched: 'Showing encoded, not dispatched',
            matched: 'Showing encoded & dispatched'
        };
        el('trsShowing').textContent = labels[currentFilter] + ' (' + rows.length + ')';
    }

    function setFilter(f) {
        currentFilter = f;
        document.querySelectorAll('.trs-stat').forEach(c =>
            c.classList.toggle('active', c.dataset.trsFilter === f));
        render();
    }

    document.querySelectorAll('.trs-stat').forEach(card =>
        card.addEventListener('click', () => setFilter(card.dataset.trsFilter)));

    let loading = false;
    async function load() {
        if (loading) return;
        loading = true;
        refreshBtn.disabled = true;
        el('trsUpdated').textContent = 'Loading…';
        errBox.classList.add('d-none');
        try {
            const res = await fetch('php/fetch/get_trip_receipt_status.php?days=' + encodeURIComponent(daysSel.value), { cache: 'no-store' });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load.');
            allRecords = data.records || [];
            el('trsTotal').textContent = data.total;
            el('trsDne').textContent = data.counts.dispatched_not_encoded;
            el('trsEnd').textContent = data.counts.encoded_not_dispatched;
            el('trsMatched').textContent = data.counts.matched;
            el('trsUpdated').textContent = fmtStamp(data.generated_at);
            render();
        } catch (e) {
            errBox.textContent = e.message;
            errBox.classList.remove('d-none');
            el('trsUpdated').textContent = 'Refresh failed';
        } finally {
            loading = false;
            refreshBtn.disabled = false;
        }
    }

    refreshBtn.addEventListener('click', load);
    daysSel.addEventListener('change', load);
    load();
});
</script>
