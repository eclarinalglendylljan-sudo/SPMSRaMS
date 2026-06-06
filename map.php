<?php
require_once 'config.php';
requireLogin();

$pageTitle   = 'Interactive Map';
$currentPage = 'map';
$db = getDB();

$stalls = $db->query("
    SELECT ms.*,
           t.full_name AS tenant_name, t.business_name,
           t.contact_no, t.tenant_id,
           rr.record_id, rr.start_date, rr.monthly_rate
    FROM market_stalls ms
    LEFT JOIN rental_records rr ON ms.stall_id = rr.stall_id AND rr.status = 'active'
    LEFT JOIN tenants t ON rr.tenant_id = t.tenant_id
    ORDER BY ms.section, ms.stall_number
")->fetchAll();

$by_section = [];
foreach ($stalls as $s) {
    $by_section[$s['section']][] = $s;
}

$total     = count($stalls);
$available = count(array_filter($stalls, fn($s) => $s['status'] === 'available'));
$occupied  = count(array_filter($stalls, fn($s) => $s['status'] === 'occupied'));
$maint     = count(array_filter($stalls, fn($s) => $s['status'] === 'maintenance'));
$reserved  = count(array_filter($stalls, fn($s) => $s['status'] === 'reserved'));
$occ_pct   = $total > 0 ? round(($occupied / $total) * 100) : 0;

include 'includes/header.php';
?>

<style>
/* ── MODAL STACKING FIX: backdrop-filter on .card creates isolated CSS stacking
   contexts that trap .modal-content below .modal-backdrop. Remove it on this
   page and give the modal hard z-index values above everything. ─────────── */
.card,.stat-card,.page-header{backdrop-filter:none!important;-webkit-backdrop-filter:none!important;}
.modal{z-index:1055!important;}
.modal-backdrop{z-index:1040!important;}
.modal-dialog{z-index:1056!important;position:relative;}
.modal-content{background:#13131e!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;border:1px solid rgba(255,255,255,.16)!important;color:#F0F0F0!important;border-radius:12px!important;box-shadow:0 24px 64px rgba(0,0,0,.9)!important;}
.modal-header{background:rgba(0,0,0,.35)!important;border-bottom:1px solid rgba(255,255,255,.10)!important;border-radius:12px 12px 0 0!important;}
.modal-footer{background:rgba(0,0,0,.25)!important;border-top:1px solid rgba(255,255,255,.10)!important;border-radius:0 0 12px 12px!important;}
.modal-title,.modal-body{color:#F0F0F0!important;}
/* ── END FIX ── */
/* ═══════════════════════════════════════════════════════
   MAP STYLES
═══════════════════════════════════════════════════════ */
.map-stall {
    border-radius: 10px;
    cursor: pointer;
    text-align: center;
    padding: 14px 8px 12px;
    min-height: 108px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    border: 1.5px solid transparent;
    transition: transform .16s, box-shadow .16s, border-color .16s;
    user-select: none;
    position: relative;
    overflow: hidden;
}
.map-stall::before {
    content: '';
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity .16s;
    border-radius: inherit;
}
.map-stall:hover { transform: translateY(-4px); }
.map-stall:hover::before { opacity: 1; }
.map-stall:active { transform: scale(.95); }

/* ── Status themes ───────────────────────────── */
/* GREEN — Available */
.ms-available {
    background: rgba(52,211,153,.10);
    border-color: rgba(52,211,153,.28);
}
.ms-available::before { background: rgba(52,211,153,.06); }
.ms-available:hover { border-color: #34D399; box-shadow: 0 8px 28px rgba(52,211,153,.22); }
.ms-available .stall-num { color: #34D399; }
.ms-available .stall-dot { background: #34D399; }

/* RED — Occupied */
.ms-occupied {
    background: rgba(248,113,113,.10);
    border-color: rgba(248,113,113,.25);
}
.ms-occupied::before { background: rgba(248,113,113,.06); }
.ms-occupied:hover { border-color: #F87171; box-shadow: 0 8px 28px rgba(248,113,113,.20); }
.ms-occupied .stall-num { color: #F87171; }
.ms-occupied .stall-dot { background: #F87171; }

/* AMBER — Maintenance */
.ms-maintenance {
    background: rgba(251,191,36,.09);
    border-color: rgba(251,191,36,.24);
}
.ms-maintenance::before { background: rgba(251,191,36,.05); }
.ms-maintenance:hover { border-color: #FBBF24; box-shadow: 0 8px 28px rgba(251,191,36,.18); }
.ms-maintenance .stall-num { color: #FBBF24; }
.ms-maintenance .stall-dot { background: #FBBF24; }

/* BLUE — Reserved */
.ms-reserved {
    background: rgba(96,165,250,.09);
    border-color: rgba(96,165,250,.22);
}
.ms-reserved::before { background: rgba(96,165,250,.05); }
.ms-reserved:hover { border-color: #60A5FA; box-shadow: 0 8px 28px rgba(96,165,250,.18); }
.ms-reserved .stall-num { color: #60A5FA; }
.ms-reserved .stall-dot { background: #60A5FA; }

/* Stall card elements */
.stall-dot {
    position: absolute;
    top: 7px; right: 7px;
    width: 7px; height: 7px;
    border-radius: 50%;
    animation: pulse-dot 2.5s infinite;
}
.ms-available .stall-dot { animation: none; }
@keyframes pulse-dot {
    0%,100% { opacity: 1; transform: scale(1); }
    50% { opacity: .5; transform: scale(.7); }
}

.stall-num {
    font-size: 1.05rem;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -.3px;
}
.stall-label {
    font-size: .64rem;
    color: var(--tx-3);
    line-height: 1.3;
    max-width: 90%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.stall-rate {
    font-size: .62rem;
    font-weight: 600;
    color: var(--tx-2);
    margin-top: 2px;
}

/* Section header */
.section-lbl {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 16px;
    margin: 22px 0 12px;
    background: var(--bg-card2);
    border: 1px solid var(--border);
    border-radius: var(--r-sm);
    border-left: 3px solid var(--gold);
}
.sec-name  { font-weight: 700; color: var(--gold); font-size: .9rem; }
.sec-meta  { font-size: .75rem; color: var(--tx-3); }

/* Modal detail rows */
.mdr {
    display: flex; gap: 12px;
    padding: 9px 0;
    border-bottom: 1px solid var(--border);
    font-size: .86rem;
}
.mdr:last-child { border-bottom: none; }
.mdk { color: var(--tx-3); min-width: 120px; font-weight: 500; flex-shrink: 0; }
.mdv { color: var(--tx-1); font-weight: 500; flex: 1; }

/* Modal status bar */
.modal-status-bar {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px;
    border-radius: var(--r-sm);
    margin-bottom: 18px;
}

/* Tenant info block */
.tenant-block {
    background: var(--bg-card2);
    border: 1px solid var(--border);
    border-radius: var(--r-sm);
    padding: 14px 16px;
    margin-top: 16px;
}
.tenant-block-head {
    font-size: .68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 1.2px;
    color: var(--tx-3); margin-bottom: 12px;
    display: flex; align-items: center; gap: 6px;
}

/* Legend pill */
.lpill {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 5px 12px; border-radius: 20px;
    font-size: .77rem; font-weight: 600;
}
</style>

<!-- ── PAGE HEADER ──────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-map"></i> Interactive Stall Map</h1>
        <p class="page-subtitle">Live overview of market stall layout and occupancy</p>
    </div>
    <?php if (isStaff()): ?>
    <div class="d-flex gap-2">
        <a href="add_stall.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Stall</a>
        <a href="stalls.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-list me-1"></i>Manage</a>
    </div>
    <?php endif; ?>
</div>

<!-- ── STATS ────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-store"></i></div>
            <div class="stat-value"><?php echo $total; ?></div>
            <div class="stat-label">Total Stalls</div>
            <div class="progress mt-2" style="height:3px">
                <div class="progress-bar" style="width:100%"></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-color:rgba(52,211,153,.3)">
            <div class="stat-icon" style="color:#34D399"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value" style="color:#34D399"><?php echo $available; ?></div>
            <div class="stat-label">Available</div>
            <div class="progress mt-2" style="height:3px">
                <div class="progress-bar" style="width:<?php echo $total?round($available/$total*100):0;?>%;background:#34D399"></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-color:rgba(248,113,113,.3)">
            <div class="stat-icon" style="color:#F87171"><i class="fas fa-user"></i></div>
            <div class="stat-value" style="color:#F87171"><?php echo $occupied; ?></div>
            <div class="stat-label">Occupied</div>
            <div class="progress mt-2" style="height:3px">
                <div class="progress-bar" style="width:<?php echo $occ_pct;?>%;background:#F87171"></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-color:rgba(251,191,36,.3)">
            <div class="stat-icon" style="color:#FBBF24"><i class="fas fa-tools"></i></div>
            <div class="stat-value" style="color:#FBBF24"><?php echo $maint; ?></div>
            <div class="stat-label">Maintenance</div>
            <div class="progress mt-2" style="height:3px">
                <div class="progress-bar" style="width:<?php echo $total?round($maint/$total*100):0;?>%;background:#FBBF24"></div>
            </div>
        </div>
    </div>
</div>

<!-- ── LEGEND + OCCUPANCY ───────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-body" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;justify-content:space-between">
        <div style="display:flex;flex-wrap:wrap;gap:8px">
            <span class="lpill" style="background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.3);color:#34D399">
                <span style="width:8px;height:8px;border-radius:50%;background:#34D399;flex-shrink:0"></span> Available
            </span>
            <span class="lpill" style="background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);color:#F87171">
                <span style="width:8px;height:8px;border-radius:50%;background:#F87171;flex-shrink:0"></span> Occupied
            </span>
            <span class="lpill" style="background:rgba(251,191,36,.09);border:1px solid rgba(251,191,36,.3);color:#FBBF24">
                <span style="width:8px;height:8px;border-radius:50%;background:#FBBF24;flex-shrink:0"></span> Maintenance
            </span>
            <span class="lpill" style="background:rgba(96,165,250,.09);border:1px solid rgba(96,165,250,.3);color:#60A5FA">
                <span style="width:8px;height:8px;border-radius:50%;background:#60A5FA;flex-shrink:0"></span> Reserved
            </span>
        </div>
        <div style="display:flex;align-items:center;gap:10px">
            <div style="font-size:.78rem;color:var(--tx-3)">Occupancy</div>
            <div style="font-size:1.1rem;font-weight:800;color:<?php echo $occ_pct>75?'#F87171':($occ_pct>50?'#FBBF24':'#34D399');?>">
                <?php echo $occ_pct; ?>%
            </div>
            <div style="width:80px;background:rgba(255,255,255,.06);border-radius:4px;height:6px">
                <div style="width:<?php echo $occ_pct;?>%;height:100%;border-radius:4px;background:<?php echo $occ_pct>75?'#F87171':($occ_pct>50?'#FBBF24':'#34D399');?>"></div>
            </div>
        </div>
    </div>
</div>

<!-- ── STALL MAP ─────────────────────────────────────────────── -->
<div class="card">
    <div class="card-body" style="padding:20px 20px 24px">

        <?php if (empty($by_section)): ?>
        <div style="text-align:center;padding:56px;color:var(--tx-3)">
            <i class="fas fa-store fa-2x d-block mb-3" style="opacity:.2"></i>
            No stalls found.
            <?php if (isStaff()): ?>
            <div class="mt-3"><a href="add_stall.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add First Stall</a></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php foreach ($by_section as $section => $section_stalls):
            $sa = count(array_filter($section_stalls, fn($s) => $s['status']==='available'));
            $so = count(array_filter($section_stalls, fn($s) => $s['status']==='occupied'));
        ?>
        <div class="section-lbl">
            <i class="fas fa-th" style="color:var(--gold);font-size:.85rem"></i>
            <span class="sec-name">Section <?php echo htmlspecialchars($section); ?></span>
            <span class="sec-meta">
                <?php echo count($section_stalls); ?> stalls &nbsp;·&nbsp;
                <span style="color:#34D399"><?php echo $sa; ?> free</span>
                &nbsp;·&nbsp;
                <span style="color:#F87171"><?php echo $so; ?> taken</span>
            </span>
        </div>

        <div class="row g-2 mb-2">
            <?php foreach ($section_stalls as $stall):
                /* Safe JSON — eliminates apostrophe/quote crash */
                $safeJson = htmlspecialchars(
                    json_encode($stall, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE),
                    ENT_QUOTES
                );
            ?>
            <div class="col-xl-2 col-lg-2 col-md-3 col-sm-4 col-6">
                <div class="map-stall ms-<?php echo $stall['status']; ?> open-stall-modal"
                     data-stall="<?php echo $safeJson; ?>"
                     title="Stall <?php echo htmlspecialchars($stall['stall_number']); ?> — <?php echo ucfirst($stall['status']); ?>">

                    <div class="stall-dot"></div>
                    <div class="stall-num"><?php echo htmlspecialchars($stall['stall_number']); ?></div>

                    <div class="stall-label">
                        <?php if ($stall['status'] === 'occupied' && $stall['tenant_name']): ?>
                            <?php echo htmlspecialchars(mb_strimwidth($stall['tenant_name'], 0, 15, '…')); ?>
                        <?php elseif ($stall['status'] === 'maintenance'): ?>
                            <i class="fas fa-tools" style="font-size:.55rem"></i> Maintenance
                        <?php elseif ($stall['status'] === 'reserved'): ?>
                            <i class="fas fa-lock" style="font-size:.55rem"></i> Reserved
                        <?php else: ?>
                            <i class="fas fa-door-open" style="font-size:.55rem"></i> Available
                        <?php endif; ?>
                    </div>

                    <div class="stall-rate"><?php echo formatCurrency($stall['price_per_month']); ?>/mo</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     STALL DETAIL MODAL
     Action buttons are created with createElement + addEventListener
     — NEVER using onclick="" in innerHTML (that's what froze things)
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="stallModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-store me-2" id="modalTitleIcon"></i>
                    <span id="modalStallNum">Stall Details</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody" style="padding:20px"></div>
            <div class="modal-footer" id="modalFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
var _role = <?php echo json_encode($_SESSION['role'] ?? ''); ?>;

/* ── Bind stall cards ──────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.open-stall-modal').forEach(function (card) {
        card.addEventListener('click', function () {
            var s;
            try { s = JSON.parse(this.dataset.stall); }
            catch (e) { alert('Error reading stall data. Please refresh.'); return; }
            buildModal(s);
        });
    });
});

/* ── Build modal content ───────────────────────────────────── */
function buildModal(s) {
    /* Icon colour by status */
    var clr = { available:'#34D399', occupied:'#F87171', maintenance:'#FBBF24', reserved:'#60A5FA' }[s.status] || 'var(--gold)';
    var icon = document.getElementById('modalTitleIcon');
    icon.style.color = clr;
    document.getElementById('modalStallNum').textContent = 'Stall ' + s.stall_number + ' — ' + cap(s.status);

    /* Status bar */
    var statusIcon = { available:'check-circle', occupied:'user', maintenance:'tools', reserved:'lock' }[s.status] || 'store';
    var html = '<div class="modal-status-bar" style="background:' + hex2bg(s.status) + ';border:1px solid ' + clr + '44">'
        + '<i class="fas fa-' + statusIcon + '" style="color:' + clr + ';font-size:1.1rem"></i>'
        + '<div><div style="font-weight:700;color:' + clr + ';font-size:.9rem">' + cap(s.status) + '</div>'
        + '<div style="font-size:.76rem;color:var(--tx-3)">Stall ' + esc(s.stall_number) + ' · Section ' + esc(s.section) + '</div>'
        + '</div></div>';

    /* Stall info — ALL statuses show this */
    html += '<div style="margin-bottom:4px">';
    html += mdr('Stall Number',  '<strong style="color:var(--gold)">' + esc(s.stall_number) + '</strong>');
    html += mdr('Section',       esc(s.section));
    html += mdr('Location',      esc(s.location || '—'));
    html += mdr('Size',          s.size_sqm ? s.size_sqm + ' sqm' : '—');
    html += mdr('Daily Rate',    '<span style="color:#34D399;font-weight:600">' + fc(s.price_per_day) + '</span>');
    html += mdr('Monthly Rate',  '<span style="color:#34D399;font-weight:600;font-size:1rem">' + fc(s.price_per_month) + '</span>');
    html += '</div>';

    /* Occupied → show tenant block */
    if (s.status === 'occupied') {
        html += '<div class="tenant-block">'
            + '<div class="tenant-block-head"><i class="fas fa-user"></i>Current Tenant</div>';
        if (s.tenant_name) {
            html += mdr('Name',     '<strong>' + esc(s.tenant_name) + '</strong>');
            html += mdr('Business', esc(s.business_name || '—'));
            html += mdr('Contact',  esc(s.contact_no   || '—'));
            html += mdr('Since',    s.start_date ? new Date(s.start_date).toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'}) : '—');
            html += mdr('Rate',     '<span style="color:#34D399;font-weight:600">' + fc(s.monthly_rate || 0) + '</span>');
        } else {
            html += '<div style="color:var(--tx-3);font-size:.84rem">No tenant info on record.</div>';
        }
        html += '</div>';
    }

    /* Maintenance note */
    if (s.status === 'maintenance') {
        html += '<div style="margin-top:14px;padding:12px 14px;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);border-radius:var(--r-sm);font-size:.84rem;color:#FBBF24">'
            + '<i class="fas fa-exclamation-triangle me-2"></i>This stall is under maintenance and temporarily unavailable.'
            + '</div>';
    }

    /* Reserved note */
    if (s.status === 'reserved') {
        html += '<div style="margin-top:14px;padding:12px 14px;background:rgba(96,165,250,.08);border:1px solid rgba(96,165,250,.2);border-radius:var(--r-sm);font-size:.84rem;color:#60A5FA">'
            + '<i class="fas fa-lock me-2"></i>This stall is reserved and pending finalization.'
            + '</div>';
    }

    /* Description */
    if (s.description) {
        html += '<div style="margin-top:14px;padding:12px 14px;background:var(--bg-card2);border:1px solid var(--border);border-radius:var(--r-sm)">'
            + '<div style="font-size:.72rem;color:var(--tx-3);margin-bottom:4px;font-weight:600;text-transform:uppercase;letter-spacing:.8px">Notes</div>'
            + '<div style="font-size:.85rem;color:var(--tx-2)">' + esc(s.description) + '</div>'
            + '</div>';
    }

    document.getElementById('modalBody').innerHTML = html;

    /* ── Action buttons via createElement (NEVER onclick in innerHTML) ── */
    buildFooterButtons(s, clr);

    bootstrap.Modal.getOrCreateInstance(document.getElementById('stallModal')).show();
}

function buildFooterButtons(s, clr) {
    var footer = document.getElementById('modalFooter');
    footer.innerHTML = '';

    /* Close always first */
    footer.appendChild(makeBtn('Close', 'btn btn-secondary', null, function () {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('stallModal')).hide();
    }));

    /* ── Staff / Admin ── */
    if (_role === 'administrator' || _role === 'staff') {

        /* VIEW — always shown for staff, opens full detail page */
        footer.appendChild(makeLink('View', 'btn btn-outline-primary', 'view_stall.php?id=' + s.stall_id, 'fas fa-eye'));

        if (s.status === 'available') {
            footer.appendChild(makeLink('Edit', 'btn btn-outline-warning', 'edit_stall.php?id=' + s.stall_id, 'fas fa-edit'));
            footer.appendChild(makeLink('Assign Vendor', 'btn btn-success', 'add_vendor.php', 'fas fa-user-plus'));
            footer.appendChild(makeLink('Delete', 'btn btn-outline-danger', 'delete_stall.php?id=' + s.stall_id, 'fas fa-trash'));
        }

        else if (s.status === 'occupied') {
            if (s.tenant_id) footer.appendChild(makeLink('Tenant', 'btn btn-outline-primary', 'view_vendor.php?id=' + s.tenant_id, 'fas fa-user'));
            if (s.tenant_id) footer.appendChild(makeLink('Payment', 'btn btn-success', 'add_payment.php?tenant_id=' + s.tenant_id, 'fas fa-receipt'));
            footer.appendChild(makeLink('Edit', 'btn btn-outline-warning', 'edit_stall.php?id=' + s.stall_id, 'fas fa-edit'));
            if (_role === 'administrator' && s.record_id) {
                footer.appendChild(makeBtn('Terminate', 'btn btn-danger', 'fas fa-ban', function () {
                    if (confirm('Terminate this rental?\n\nStall will be marked Available and vendor set Inactive.\nThis cannot be undone.')) {
                        window.location.href = 'terminate_rental.php?id=' + s.record_id;
                    }
                }));
            }
        }

        else { /* maintenance / reserved */
            footer.appendChild(makeLink('Edit', 'btn btn-outline-warning', 'edit_stall.php?id=' + s.stall_id, 'fas fa-edit'));
            footer.appendChild(makeBtn('Mark Available', 'btn btn-success', 'fas fa-check', function () {
                if (confirm('Mark stall ' + s.stall_number + ' as Available?')) {
                    window.location.href = 'mark_stall_available.php?id=' + s.stall_id;
                }
            }));
            footer.appendChild(makeLink('Delete', 'btn btn-outline-danger', 'delete_stall.php?id=' + s.stall_id, 'fas fa-trash'));
        }
    }

    /* ── Vendor ── */
    else if (_role === 'vendor') {
        if (s.status === 'available') {
            footer.appendChild(makeLink('Apply for This Stall', 'btn btn-primary', 'vendorapplication.php?stall_id=' + s.stall_id, 'fas fa-file-signature'));
        }
    }
}

/* createElement helpers — no inline onclick ever */
function makeBtn(label, cls, iconCls, fn) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = cls;
    if (iconCls) b.innerHTML = '<i class="' + iconCls + ' me-1"></i>';
    b.appendChild(document.createTextNode(label));
    b.addEventListener('click', fn);
    return b;
}
function makeLink(label, cls, href, iconCls) {
    var a = document.createElement('a');
    a.href = href;
    a.className = cls;
    if (iconCls) a.innerHTML = '<i class="' + iconCls + ' me-1"></i>';
    a.appendChild(document.createTextNode(label));
    return a;
}

/* Utility */
function cap(str)  { return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''; }
function esc(str)  { var d=document.createElement('div'); d.textContent=str||''; return d.innerHTML; }
function fc(n)     { return '₱'+parseFloat(n||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function mdr(k, v) { return '<div class="mdr"><span class="mdk">'+k+'</span><span class="mdv">'+v+'</span></div>'; }
function hex2bg(status) {
    return {available:'rgba(52,211,153,.08)',occupied:'rgba(248,113,113,.08)',maintenance:'rgba(251,191,36,.08)',reserved:'rgba(96,165,250,.08)'}[status]||'rgba(255,255,255,.04)';
}
</script>

<?php include 'includes/footer.php'; ?>