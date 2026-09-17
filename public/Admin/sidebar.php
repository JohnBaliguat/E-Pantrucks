<?php $role = ucfirst(
    strtolower((string) ($_SESSION["user_type"] ?? "User")),
);

/**
 * Sidebar menu definition. Each section lists its links; "roles" limits who sees
 * a section or an individual link (omitted = everyone). Ids are load-bearing:
 * each page marks its own link active via $("#<id>").
 *
 * "collapsible" => false pins a section open (no toggle) — Dashboard, Profile and
 * Logout stay one click away at all times.
 */
$sidebarSections = [
    [
        "title" => "MAIN",
        "roles" => ["Admin", "Subadmin"],
        "collapsible" => false,
        "items" => [
            ["href" => "dashboard", "id" => "dnav", "icon" => "bi-speedometer2", "label" => "Dashboard"],
        ],
    ],
    [
        "title" => "OPERATIONS",
        "roles" => ["Admin", "User", "Subadmin"],
        "items" => [
            ["href" => "entry", "id" => "enav", "icon" => "bi-table", "label" => "Data Entry"],
            // Monitoring, Records and Transmittals are hidden from Sub Admin.
            ["href" => "monitoring", "id" => "mnav", "icon" => "bi-broadcast-pin", "label" => "Monitoring", "roles" => ["Admin", "User"]],
            // Trip Receipt Status is available to Sub Admin too (unlike Monitoring).
            ["href" => "trip-receipt-status", "id" => "trsnav", "icon" => "bi-clipboard-check", "label" => "Trip Receipt Status"],
            ["href" => "records", "id" => "rnav", "icon" => "bi-journal-text", "label" => "Records", "roles" => ["Admin", "User"]],
            ["href" => "transactions", "id" => "txnav", "icon" => "bi-file-earmark-spreadsheet", "label" => "Transactions", "roles" => ["Admin", "Subadmin"]],
            ["href" => "transmittals", "id" => "tnav", "icon" => "bi-archive", "label" => "Transmittals", "roles" => ["Admin"]],
            ["href" => "for-update", "id" => "funav", "icon" => "bi-flag", "label" => "For Update"],
        ],
    ],
    [
        "title" => "BILLING",
        "roles" => ["Admin", "Billing", "Subadmin"],
        "items" => [
            ["href" => "customer-billing", "id" => "cbnav", "icon" => "bi-receipt", "label" => "Billing"],
        ],
    ],
    [
        "title" => "REPORTS",
        "roles" => ["Admin", "Manager", "Subadmin"],
        "items" => [
            ["href" => "analytics", "id" => "anav", "icon" => "bi-bar-chart-line", "label" => "Analytics"],
            ["href" => "driver-performance", "id" => "dpnav", "icon" => "bi-person-bounding-box", "label" => "Driver Performance"],
            ["href" => "driver-trips", "id" => "dtnav", "icon" => "bi-signpost-split", "label" => "Driver Trips", "roles" => ["Admin", "Subadmin"]],
            ["href" => "utilization", "id" => "utnav", "icon" => "bi-truck-front", "label" => "Utilization"],
            ["href" => "performance", "id" => "pfnav", "icon" => "bi-graph-up-arrow", "label" => "Performance", "roles" => ["Admin", "Subadmin"]],
            ["href" => "payroll", "id" => "paynav", "icon" => "bi-cash-coin", "label" => "Payroll", "roles" => ["Admin", "Subadmin"]],
        ],
    ],
    [
        "title" => "ADMINISTRATION",
        "roles" => ["Admin", "Subadmin"],
        "items" => [
            // Users and Settings stay Admin-only; Sub Admin keeps Drivers and Activity Log.
            ["href" => "users", "id" => "unav", "icon" => "bi-people", "label" => "Users", "roles" => ["Admin"]],
            ["href" => "drivers", "id" => "drnav", "icon" => "bi-person-vcard", "label" => "Drivers"],
            ["href" => "settings", "id" => "snav", "icon" => "bi-sliders", "label" => "Settings", "roles" => ["Admin"]],
            ["href" => "activity-log", "id" => "alnav", "icon" => "bi-clock-history", "label" => "Activity Log"],
        ],
    ],
    [
        "title" => "ACCOUNT",
        "collapsible" => false,
        "items" => [
            ["href" => "profile", "id" => "pnav", "icon" => "bi-person", "label" => "Profile"],
            ["href" => "logout", "icon" => "bi-box-arrow-left", "label" => "Logout"],
        ],
    ],
];

$sidebarVisible = static fn(array $entry, string $role): bool =>
    !isset($entry["roles"]) || in_array($role, $entry["roles"], true);
?>
<style>
/* Sidebar group styles live here rather than in styles.css: that file is cached by
   the browser, and a stale copy would leave the toggles rendered as bare grey
   buttons. Inlined with the markup, they are always in step with it. */
#sidebar .menu-section { margin-bottom: 14px; }

#sidebar button.menu-title {
    width: 100%;
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    background: none !important;
    background-color: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
    border-radius: 0;
    text-align: left;
    cursor: pointer;
    color: rgba(255, 255, 255, 0.5);
    font-weight: 600;
    font-size: 11px;
    letter-spacing: 1px;
    font-family: inherit;
    line-height: 1.5;
    padding: 6px 20px;
    margin-bottom: 4px;
    transition: color 0.2s;
    -webkit-appearance: none;
    appearance: none;
}

#sidebar button.menu-title:hover,
#sidebar button.menu-title:focus {
    color: rgba(255, 255, 255, 0.9);
    background: none !important;
    outline: none;
}

#sidebar .menu-caret {
    font-size: 12px;
    transition: transform 0.25s ease;
    flex: 0 0 auto;
}

#sidebar .menu-section.is-collapsed .menu-caret { transform: rotate(-90deg); }

#sidebar .menu-items {
    overflow: hidden;
    max-height: 600px;
    transition: max-height 0.25s ease;
}

#sidebar .menu-section.is-collapsed .menu-items { max-height: 0; }
</style>
<nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="bi bi-database-fill-gear"></i>
                    <span>e-PANTRUCKS</span>
                </div>
            </div>

            <div class="sidebar-menu">
                <?php foreach ($sidebarSections as $index => $section) { ?>
                    <?php
                    if (!$sidebarVisible($section, $role)) {
                        continue;
                    }
                    $items = array_values(array_filter(
                        $section["items"],
                        static fn($item) => $sidebarVisible($item, $role)
                    ));
                    if (!$items) {
                        continue;
                    }
                    $sectionKey = strtolower($section["title"]);
                    $listId = "menuItems-" . $sectionKey;
                    $collapsible = $section["collapsible"] ?? true;
                    ?>
                <div class="menu-section" data-section="<?php echo htmlspecialchars($sectionKey); ?>"<?php echo $collapsible ? ' data-collapsible="true"' : ""; ?>>
                    <?php if ($collapsible) { ?>
                    <button type="button" class="menu-title menu-toggle" aria-expanded="true" aria-controls="<?php echo $listId; ?>">
                        <span><?php echo htmlspecialchars($section["title"]); ?></span>
                        <i class="bi bi-chevron-down menu-caret"></i>
                    </button>
                    <?php } else { ?>
                    <small class="menu-title"><?php echo htmlspecialchars($section["title"]); ?></small>
                    <?php } ?>
                    <ul class="nav flex-column menu-items" id="<?php echo $listId; ?>">
                        <?php foreach ($items as $item) { ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo htmlspecialchars($item["href"]); ?>"<?php echo isset($item["id"]) ? ' id="' . htmlspecialchars($item["id"]) . '"' : ""; ?>>
                                <i class="bi <?php echo htmlspecialchars($item["icon"]); ?>"></i>
                                <span><?php echo htmlspecialchars($item["label"]); ?></span>
                            </a>
                        </li>
                        <?php } ?>
                    </ul>
                </div>
                <?php } ?>
            </div>
        </nav>
<script>
(function () {
    // Collapsible sidebar groups. The section holding the current page is opened;
    // everything else starts closed to keep the menu short. Choices persist.
    // Sections without [data-collapsible] (Dashboard, Account) are always shown.
    var STORAGE_KEY = 'sidebarOpenSections';
    var sections = document.querySelectorAll('#sidebar .menu-section[data-collapsible="true"]');
    if (!sections.length) return;

    function readStored() {
        try {
            var raw = window.localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function writeStored(open) {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(open));
        } catch (e) {
            /* private mode / storage disabled - collapsing still works for this page */
        }
    }

    // Current route, e.g. "/E-Pantrucks/driver-trips" -> "driver-trips".
    var currentRoute = window.location.pathname.split('/').filter(Boolean).pop() || '';

    function sectionOwnsCurrentPage(section) {
        var links = section.querySelectorAll('.nav-link');
        for (var i = 0; i < links.length; i++) {
            var href = (links[i].getAttribute('href') || '').replace(/^\.\//, '');
            if (href && href !== 'logout' && href === currentRoute) return true;
        }
        return false;
    }

    function setOpen(section, open) {
        section.classList.toggle('is-collapsed', !open);
        var toggle = section.querySelector('.menu-toggle');
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    var stored = readStored();
    var activeSection = null;

    sections.forEach(function (section) {
        if (sectionOwnsCurrentPage(section)) activeSection = section;
    });

    sections.forEach(function (section) {
        var key = section.dataset.section;
        var open;
        if (section === activeSection) {
            open = true; // always reveal where you are
        } else if (stored && Object.prototype.hasOwnProperty.call(stored, key)) {
            open = !!stored[key];
        } else {
            open = false;
        }
        setOpen(section, open);
    });

    // Sub-pages have their own route (e.g. "abcrv", "payroll-driver") that matches
    // no link, but each marks its parent link active ("#enav", "#paynav"). That
    // happens on jQuery ready -- which lands after this script -- so watch for the
    // class instead of guessing when it arrives.
    if (!activeSection) {
        var revealActive = function () {
            var activeLink = document.querySelector('#sidebar .nav-link.active');
            if (!activeLink) return false;
            var owner = activeLink.closest('.menu-section[data-collapsible="true"]');
            if (owner) setOpen(owner, true);
            return true;
        };

        if (!revealActive()) {
            var observer = new MutationObserver(function () {
                if (revealActive()) observer.disconnect();
            });
            observer.observe(document.getElementById('sidebar'), {
                subtree: true,
                attributes: true,
                attributeFilter: ['class']
            });
            // Give up once the page has settled, so the observer never lingers.
            window.addEventListener('load', function () {
                setTimeout(function () { observer.disconnect(); }, 3000);
            });
        }
    }

    sections.forEach(function (section) {
        var toggle = section.querySelector('.menu-toggle');
        if (!toggle) return;
        toggle.addEventListener('click', function () {
            var willOpen = section.classList.contains('is-collapsed');
            setOpen(section, willOpen);
            var open = readStored() || {};
            open[section.dataset.section] = willOpen;
            writeStored(open);
        });
    });
})();
</script>
