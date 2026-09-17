<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-light">
                        <i class="bi bi-list"></i>
                    </button>

                    <div class="ms-auto d-flex align-items-center">
                        <?php
                        $navRole = ucfirst(strtolower((string) ($_SESSION["user_type"] ?? "")));
                        $navBilling = ($navRole === "Billing" || $navRole === "Billingadmin");
                        $navShowBell = ($navRole === "Admin" || $navRole === "User" || $navBilling);
                        if ($navShowBell):
                            $navMode = $navBilling ? "resolved" : "open";
                            $navHeader = $navBilling ? "Updated records" : "Records for update";
                            $navEmpty = $navBilling ? "No updates yet." : "No records flagged for update.";
                            $navTitle = $navBilling ? "Records you flagged that were updated" : "Records flagged for update";
                        ?>
                        <div class="dropdown me-3">
                            <button class="btn btn-light position-relative" type="button" data-bs-toggle="dropdown" id="notifBell" data-mode="<?php echo $navMode; ?>" title="<?php echo $navTitle; ?>" aria-expanded="false">
                                <i class="bi bi-bell"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notifBadge">0</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow" id="notifMenu" style="min-width: 340px; max-height: 420px; overflow-y: auto;">
                                <li>
                                    <h6 class="dropdown-header d-flex justify-content-between align-items-center">
                                        <span><i class="bi bi-flag me-1"></i><?php echo $navHeader; ?></span>
                                        <span class="badge bg-warning text-dark" id="notifHeaderCount">0</span>
                                    </h6>
                                </li>
                                <li id="notifEmpty"><span class="dropdown-item-text text-muted small"><?php echo $navEmpty; ?></span></li>
                                <?php if (!$navBilling): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center small text-primary" href="for-update">View all in For Update</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <div class="dropdown">
                            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name']); ?>&background=0D6EFD&color=fff" alt="User" class="rounded-circle me-2" width="32" height="32">
                                <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile"><i class="bi bi-person me-2"></i>Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
<style>
    .update-field-highlight { border: 2px solid #dc3545 !important; background: #fff5f5 !important; }
    .update-field-note { display: block; width: fit-content; max-width: 100%; margin: 0 0 .35rem; padding: .35rem .55rem; border-radius: .4rem; color: #842029; background: #f8d7da; border: 1px solid #f1aeb5; font-size: .8rem; font-weight: 600; }
</style>
<script>
(() => {
    const flagId = new URLSearchParams(window.location.search).get('flag_id');
    if (!flagId) return;
    const applyNotes = (flag) => {
        const notes = Array.isArray(flag.field_notes) ? flag.field_notes : [];
        notes.forEach(({ field, message }) => {
            if (!field || !message) return;
            const input = document.getElementById(field) || document.querySelector(`[name="${CSS.escape(field)}"]`);
            if (!input) return;
            input.classList.add('update-field-highlight');
            const old = input.parentElement?.querySelector(`.update-field-note[data-update-field="${field}"]`);
            if (old) old.remove();
            const note = document.createElement('div');
            note.className = 'update-field-note';
            note.dataset.updateField = field;
            note.innerHTML = `<i class="bi bi-flag-fill me-1"></i>${String(message).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}`;
            input.parentElement?.insertBefore(note, input);
        });
        if (flag.remarks && notes.length) {
            const form = document.querySelector('form');
            if (form && !document.getElementById('updateFlagSummary')) {
                const summary = document.createElement('div');
                summary.id = 'updateFlagSummary';
                summary.className = 'alert alert-warning border-warning mb-3';
                summary.innerHTML = `<i class="bi bi-flag-fill me-2"></i><strong>Fields marked for correction:</strong> ${notes.length}. Follow the red field instructions below.`;
                form.prepend(summary);
            }
        }
    };
    document.addEventListener('DOMContentLoaded', async () => {
        try {
            const res = await fetch(`php/fetch/get_update_flag_fields.php?flag_id=${encodeURIComponent(flagId)}`, { cache: 'no-store' });
            const data = await res.json();
            if (data.success && data.status === 'open') applyNotes(data);
        } catch (_) { /* the record can still be edited without field hints */ }
    });
})();
</script>
