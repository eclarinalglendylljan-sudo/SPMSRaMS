<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$_role = $_SESSION['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'MSRMS'); ?> — Sibalom Market</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/fontawesome-free-6.4.0-web/css/all.min.css" rel="stylesheet">

    <script>
    var _dOpen=false;

    /* ===== THEME ADD START ===== */
    function setTheme(theme){
        document.body.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);

        var icon = document.getElementById('themeIcon');
        if(icon){
            icon.classList.remove('fa-moon','fa-sun');
            icon.classList.add(theme === 'dark' ? 'fa-moon' : 'fa-sun');
        }
    }

    function toggleTheme(){
        var current = document.body.getAttribute('data-theme') || 'dark';
        setTheme(current === 'dark' ? 'light' : 'dark');
    }
    /* ===== THEME ADD END ===== */

    function openDrawer(){_dOpen=true;var d=document.getElementById('sideNav'),o=document.getElementById('navOverlay'),i=document.getElementById('menuIcon');if(d)d.classList.add('open');if(o)o.classList.add('open');if(i){i.classList.remove('fa-bars');i.classList.add('fa-times');}document.body.style.overflow='hidden';}
    function closeDrawer(){_dOpen=false;var d=document.getElementById('sideNav'),o=document.getElementById('navOverlay'),i=document.getElementById('menuIcon');if(d)d.classList.remove('open');if(o)o.classList.remove('open');if(i){i.classList.remove('fa-times');i.classList.add('fa-bars');}document.body.style.overflow='';}
    function toggleDrawer(){_dOpen?closeDrawer():openDrawer();}
    function toggleDropdown(){var d=document.getElementById('acctMenu');if(d)d.classList.toggle('show');}
    function closeDropdown(){var d=document.getElementById('acctMenu');if(d)d.classList.remove('show');}
    window.showSpinner=function(){var s=document.getElementById('spin');if(s)s.classList.add('on');};
    window.hideSpinner=function(){var s=document.getElementById('spin');if(s)s.classList.remove('on');};
    window.showToast=function(msg,type){
        var stack=document.getElementById('toasts');if(!stack)return;
        var clr={success:'#16a34a',danger:'#dc2626',warning:'#d97706',info:'#2563eb'};
        var icn={success:'check-circle',danger:'exclamation-circle',warning:'exclamation-triangle',info:'info-circle'};
        var t=document.createElement('div');t.className='toast-n';
        t.innerHTML='<i class="fas fa-'+(icn[type]||icn.info)+'" style="color:'+(clr[type]||clr.info)+';flex-shrink:0"></i><span style="flex:1">'+msg+'</span><button onclick="this.parentNode.remove()" style="background:none;border:none;color:#9ca3af;cursor:pointer;font-size:1.1rem;line-height:1;padding:0">×</button>';
        stack.appendChild(t);
        setTimeout(function(){t.style.cssText+='opacity:0;transform:translateX(14px)';setTimeout(function(){t.remove();},300);},4500);
    };

    document.addEventListener('DOMContentLoaded',function(){

        /* ===== THEME INIT ===== */
        var savedTheme = localStorage.getItem('theme') || 'dark';
        setTheme(savedTheme);
        var btnTheme = document.getElementById('themeToggle');
        if(btnTheme){ btnTheme.addEventListener('click', toggleTheme); }

        var btn=document.getElementById('menuBtn');if(btn)btn.addEventListener('click',function(e){e.stopPropagation();toggleDrawer();});
        var cx=document.getElementById('navClose');if(cx)cx.addEventListener('click',closeDrawer);
        var ov=document.getElementById('navOverlay');if(ov)ov.addEventListener('click',closeDrawer);
        var sn=document.getElementById('sideNav');if(sn)sn.addEventListener('click',function(e){var a=e.target.closest('.nl');if(a&&!a.classList.contains('logout-link'))closeDrawer();});
        var ab=document.getElementById('acctBtn');if(ab)ab.addEventListener('click',function(e){e.stopPropagation();toggleDropdown();});
        document.addEventListener('click',function(e){var m=document.getElementById('acctMenu'),b=document.getElementById('acctBtn');if(m&&b&&!b.contains(e.target)&&!m.contains(e.target))closeDropdown();});
        document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeDrawer();closeDropdown();}});
    });
    </script>

    <style>

    /* ===== THEME ADD (LIGHT MODE) ===== */
    body[data-theme="light"] {
        --bg:#F9FAFB !important;
        --bg-nav:#FFFFFF !important;
        --bg-card:#FFFFFF !important;
        --bg-input:#FFFFFF !important;
        --bg-hover:#F3F4F6 !important;
        --bg-active:#E5E7EB !important;

        --bd:#E5E7EB !important;
        --bd-md:#D1D5DB !important;

        --tx-1:#111827 !important;
        --tx-2:#374151 !important;
        --tx-3:#6B7280 !important;
        --tx-4:#9CA3AF !important;

        background: var(--bg) !important;
        color: var(--tx-2) !important;
    }

    body {
        transition: background .25s ease, color .25s ease;
    }

    /* ===== YOUR ORIGINAL CSS CONTINUES ===== */
    /* ════════════════════════════════════════════════════
       SIBALOM MSRMS — Light Minimalist Design System
       Clean whites · Subtle shadows · Gold accent
    ════════════════════════════════════════════════════ */
    :root {
        /* Core palette */
        --gold:       #D97706;
        --gold-lt:    #F59E0B;
        --gold-dim:   rgba(217,119,6,.08);
        --gold-bd:    rgba(217,119,6,.25);

        /* Backgrounds */
        --bg:         #0A0A0A;          /* very light grey-white page */
        --bg-nav:     rgba(255,255,255,.06);          /* pure white topbar/sidenav */
        --bg-card:    rgba(255,255,255,.07);          /* pure white cards */
        --bg-input:   rgba(255,255,255,.08);          /* nearly white inputs */
        --bg-hover:   rgba(255,255,255,.10);          /* subtle hover */
        --bg-active:  rgba(255,255,255,.14);          /* gold-tinted active */

        /* Borders */
        --bd:         rgba(255,255,255,.15);
        --bd-md:      rgba(255,255,255,.25);
        --bd-gold:  rgba(217,119,6,.45);

        /* Typography — dark on light */
        --tx-1:  #F9FAFB;   /* near-black headings */
        --tx-2:  #D1D5DB;   /* dark grey body */
        --tx-3:  #9CA3AF;   /* medium grey muted */
        --tx-4:  #6B7280;   /* light grey placeholders */

        /* Semantic */
        --green:  #4ade80; --green-bg: rgba(74,222,128,.12); --green-bd: rgba(74,222,128,.30);
        --red:    #f87171; --red-bg:   rgba(248,113,113,.12); --red-bd:   rgba(248,113,113,.30);
        --amber:  #fbbf24; --amber-bg: rgba(251,191,36,.12);  --amber-bd: rgba(251,191,36,.30);
        --blue:   #60a5fa; --blue-bg:  rgba(96,165,250,.12);  --blue-bd:  rgba(96,165,250,.30);
        --gray:   #9ca3af; --gray-bg:  rgba(156,163,175,.10); --gray-bd:  rgba(156,163,175,.25);

        /* Layout */
        --nav-h:  60px;
        --side-w: 256px;
        --r:      10px;
        --r-sm:   7px;
        --r-xs:   5px;
        --shadow: 0 2px 12px rgba(0,0,0,.4), inset 0 1px 0 rgba(255,255,255,.08);
        --shadow-md: 0 4px 20px rgba(0,0,0,.5), inset 0 1px 0 rgba(255,255,255,.10);
        --shadow-lg: 0 12px 40px rgba(0,0,0,.6), inset 0 1px 0 rgba(255,255,255,.10);
        --ease: cubic-bezier(.4,0,.2,1);
        --dur: .15s;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        background: var(--bg);
        font-family: -apple-system, 'Segoe UI', system-ui, sans-serif;
        font-size: 15px; line-height: 1.6;
        color: var(--tx-2);
        padding-top: var(--nav-h);
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
    }

    /* ── TOPBAR ──────────────────────────────────────────── */
    .topbar {
        position: fixed; top: 0; left: 0; right: 0; height: var(--nav-h);
        background: rgba(255,255,255,.06);
        border-bottom: 1px solid var(--bd);
        box-shadow: var(--shadow);
        z-index: 1000;
        display: flex; align-items: center;
        padding: 0 20px; gap: 12px;
    }

    .menu-btn {
        width: 36px; height: 36px; border-radius: var(--r-sm);
        background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.15);
        color: var(--tx-3); cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: .88rem; transition: all var(--dur); flex-shrink: 0;
    }
    .menu-btn:hover { background: rgba(255,255,255,.12); color: var(--tx-1); border-color: rgba(255,255,255,.28); }

    .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; flex-shrink: 0; }
    .brand-mark {
        width: 32px; height: 32px; border-radius: 8px;
        background: var(--gold); display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; overflow: hidden;
    }
    .brand-mark img { width: 100%; height: 100%; object-fit: contain; }
    .brand-mark span { font-weight: 900; color: #000; font-size: .95rem; }
    .brand-name { font-size: .88rem; font-weight: 700; color: var(--tx-1); line-height: 1.25; }
    .brand-sub  { font-size: .62rem; color: var(--tx-4); }
    .top-spacer { flex: 1; }

    .acct-btn {
        display: flex; align-items: center; gap: 8px;
        background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.16);
        border-radius: var(--r-sm); padding: 5px 10px 5px 6px;
        cursor: pointer; transition: all var(--dur); position: relative;
        backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    }
    .acct-btn:hover { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.28); box-shadow: var(--shadow); }
    .acct-avatar {
        width: 28px; height: 28px; border-radius: 50%;
        background: var(--gold); display: flex; align-items: center; justify-content: center;
        font-weight: 700; color: #fff; font-size: .78rem;
        overflow: hidden; flex-shrink: 0;
    }
    .acct-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .acct-name { font-size: .84rem; font-weight: 600; color: var(--tx-1); max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .acct-role { font-size: .62rem; color: var(--gold); text-transform: uppercase; letter-spacing: .7px; font-weight: 600; }
    .acct-chevron { font-size: .62rem; color: var(--tx-4); margin-left: 2px; }

    .acct-menu {
        position: absolute; top: calc(100% + 8px); right: 0;
        background: rgba(20,20,20,.85);
        backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255,255,255,.16);
        border-radius: var(--r); min-width: 210px;
        box-shadow: var(--shadow-lg); overflow: hidden;
        opacity: 0; visibility: hidden;
        transform: translateY(-6px) scale(.97);
        transition: opacity var(--dur) var(--ease), visibility var(--dur), transform var(--dur) var(--ease);
        z-index: 100;
    }
    .acct-menu.show { opacity: 1; visibility: visible; transform: translateY(0) scale(1); }
    .acct-menu-header { padding: 12px 14px; border-bottom: 1px solid rgba(255,255,255,.10); background: rgba(255,255,255,.04); }
    .acct-menu-name { font-size: .88rem; font-weight: 700; color: var(--tx-1); }
    .acct-menu-role { font-size: .68rem; color: var(--gold); text-transform: capitalize; font-weight: 600; }
    .acct-menu-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; color: var(--tx-2); font-size: .86rem; text-decoration: none; transition: background var(--dur); }
    .acct-menu-item i { width: 15px; text-align: center; color: var(--tx-4); font-size: .78rem; }
    .acct-menu-item:hover { background: var(--bg-hover); color: var(--tx-1); }
    .acct-menu-item:hover i { color: var(--gold); }
    .acct-menu-item.danger { color: var(--red); }
    .acct-menu-item.danger i { color: var(--red); }
    .acct-menu-item.danger:hover { background: var(--red-bg); }
    .acct-menu-div { height: 1px; background: var(--bd); }

    /* ── SIDE NAV ─────────────────────────────────────────── */
    .nav-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.30); z-index: 1010; opacity: 0; visibility: hidden; transition: opacity .25s, visibility .25s; }
    .nav-overlay.open { opacity: 1; visibility: visible; }

    .side-nav {
        position: fixed; top: 0; left: 0; bottom: 0; width: var(--side-w);
        background: rgba(255,255,255,.06);
        backdrop-filter: blur(24px) saturate(1.5);
        -webkit-backdrop-filter: blur(24px) saturate(1.5);
        border-right: 1px solid rgba(255,255,255,.12);
        box-shadow: 4px 0 32px rgba(0,0,0,.5); z-index: 1020;
        transform: translateX(calc(-1 * var(--side-w)));
        transition: transform .26s var(--ease);
        display: flex; flex-direction: column; overflow: hidden;
    }
    .side-nav.open { transform: translateX(0); }

    .nav-header { height: var(--nav-h); display: flex; align-items: center; padding: 0 14px; gap: 10px; border-bottom: 1px solid var(--bd); flex-shrink: 0; }
    .nav-close { margin-left: auto; width: 28px; height: 28px; border-radius: var(--r-xs); border: 1px solid var(--bd); background: transparent; color: var(--tx-4); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: .78rem; transition: all var(--dur); }
    .nav-close:hover { background: var(--bg-hover); color: var(--tx-1); border-color: var(--bd-md); }

    .nav-user { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,.10); display: flex; align-items: center; gap: 11px; flex-shrink: 0; background: rgba(255,255,255,.04); }
    .nav-user-av { width: 38px; height: 38px; border-radius: 50%; background: var(--gold); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: .9rem; flex-shrink: 0; overflow: hidden; }
    .nav-user-av img { width: 100%; height: 100%; object-fit: cover; }
    .nav-user-name { font-size: .9rem; font-weight: 700; color: var(--tx-1); line-height: 1.3; }
    .nav-user-role { font-size: .64rem; color: var(--gold); text-transform: uppercase; letter-spacing: .9px; font-weight: 600; margin-top: 1px; }

    .nav-scroll { flex: 1; overflow-y: auto; padding: 8px 0; }
    .nav-scroll::-webkit-scrollbar { width: 3px; }
    .nav-scroll::-webkit-scrollbar-thumb { background: var(--bd-md); border-radius: 2px; }

    .nav-sec { padding: 16px 16px 5px; font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--tx-4); user-select: none; }

    .nl {
        display: flex; align-items: center; gap: 10px;
        padding: 9px 16px; color: var(--tx-3); font-size: .9rem;
        text-decoration: none; border-left: 3px solid transparent;
        transition: all var(--dur); margin: 1px 8px; border-radius: 0 var(--r-sm) var(--r-sm) 0;
    }
    .nl .ni { width: 16px; text-align: center; font-size: .8rem; color: var(--tx-4); flex-shrink: 0; transition: color var(--dur); }
    .nl:hover { background: rgba(255,255,255,.09); color: var(--tx-1); border-left-color: rgba(255,255,255,.25); }
    .nl:hover .ni { color: var(--gold); }
    .nl.active { background: rgba(255,255,255,.12); border-left-color: var(--gold); color: var(--gold); font-weight: 700; }
    .nl.active .ni { color: var(--gold); }
    .nav-badge { margin-left: auto; background: var(--red); color: #fff; font-size: .62rem; font-weight: 700; padding: 2px 7px; border-radius: 10px; }

    .nav-foot { border-top: 1px solid var(--bd); padding: 8px 0; flex-shrink: 0; }
    .nl.logout-link { color: var(--red); }
    .nl.logout-link .ni { color: var(--red); }
    .nl.logout-link:hover { background: var(--red-bg); border-left-color: var(--red); }

    /* ── CONTENT — NO z-index ─────────────────────────────── */
    .content { position: relative; padding: 28px 28px; min-height: calc(100vh - var(--nav-h)); max-width: 1440px; margin: 0 auto; }
    @media(max-width:576px){ .content { padding: 16px 14px; } }

    /* ── PAGE HEADER ──────────────────────────────────────── */
   /* ===== ENHANCED PAGE HEADER ===== */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 22px;
    margin-bottom: 24px;

    border-radius: var(--r);
    border: 1px solid var(--bd);

    backdrop-filter: blur(12px);

    background: rgba(255,255,255,.06);
}

/* LIGHT MODE FIX */
body[data-theme="light"] .page-header {
    background: rgba(20,20,20,.9) !important;
}

/* TITLE */
.page-title {
    font-size: 1.6rem;
    font-weight: 800;
    letter-spacing: -.4px;
}

/* GOLD ICON STILL POPS */
.page-title i {
    color: var(--gold);
}

/* TITLE */
.page-title {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--tx-1);
    letter-spacing: -.4px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-title i {
    color: var(--gold);
    font-size: 1.2rem;
}

/* SUBTITLE */
.page-subtitle {
    font-size: .85rem;
    color: var(--tx-3);
    margin-top: 4px;
}
    .page-title { font-size: 1.5rem; font-weight: 800; color: var(--tx-1); letter-spacing: -.4px; line-height: 1.2; }
    .page-title i { color: var(--gold); margin-right: 10px; }
    .page-subtitle { font-size: .88rem; color: var(--tx-3); margin-top: 3px; font-weight: 400; }

    /* ── CARDS ────────────────────────────────────────────── */
    /* backdrop-filter safe — Bootstrap moves modals to <body> */
    .card {
        background: rgba(255,255,255,.07);
        backdrop-filter: blur(16px) saturate(1.4);
        -webkit-backdrop-filter: blur(16px) saturate(1.4);
        border: 1px solid rgba(255,255,255,.15);
        border-radius: var(--r);
        color: var(--tx-2); margin-bottom: 20px;
        box-shadow: 0 4px 24px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.10);
        transition: box-shadow var(--dur), border-color var(--dur), transform .2s;
    }
    .card:hover { box-shadow: 0 8px 32px rgba(0,0,0,.45), inset 0 1px 0 rgba(255,255,255,.14); border-color: rgba(255,255,255,.25); transform: translateY(-1px); }
    .card-header {
        background: rgba(255,255,255,.05);
        border-bottom: 1px solid rgba(255,255,255,.12);
        padding: 14px 18px;
        display: flex; align-items: center; gap: 9px;
        font-size: .95rem; font-weight: 700; color: var(--tx-1);
        border-radius: var(--r) var(--r) 0 0 !important;
    }
    .card-header i { color: var(--gold); font-size: .88rem; }
    .card-body { padding: 20px; }
    .card-footer { padding: 13px 18px; border-top: 1px solid rgba(255,255,255,.10); background: rgba(255,255,255,.04); border-radius: 0 0 var(--r) var(--r); }

    /* ── STAT CARDS ───────────────────────────────────────── */
    .stat-card {
        background: rgba(255,255,255,.07);
        backdrop-filter: blur(16px) saturate(1.4);
        -webkit-backdrop-filter: blur(16px) saturate(1.4);
        border: 1px solid rgba(255,255,255,.15); border-radius: var(--r);
        padding: 22px; position: relative; overflow: hidden;
        box-shadow: 0 4px 24px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.10);
        transition: box-shadow .2s, transform .2s;
    }
    .stat-card:hover { box-shadow: 0 10px 36px rgba(0,0,0,.5), inset 0 1px 0 rgba(255,255,255,.14); transform: translateY(-3px); border-color: rgba(255,255,255,.25); }
    .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--gold), var(--gold-lt)); }
    .stat-icon { font-size: 1.5rem; color: var(--gold); margin-bottom: 14px; }
    .stat-value { font-size: 2.2rem; font-weight: 800; color: var(--tx-1); letter-spacing: -1.5px; line-height: 1; }
    .stat-label { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .9px; color: var(--tx-3); margin-top: 7px; }
    .stat-sub   { font-size: .85rem; color: var(--tx-3); margin-top: 5px; }
    .stat-trend { font-size: .84rem; font-weight: 600; margin-top: 6px; }
    .stat-trend.up   { color: var(--green); }
    .stat-trend.down { color: var(--red); }

    /* ── BUTTONS ──────────────────────────────────────────── */
    .btn { border-radius: var(--r-sm); font-family: inherit; font-size: .9rem; font-weight: 600; line-height: 1.45; transition: all .14s var(--ease); display: inline-flex; align-items: center; }
    .btn:active { transform: scale(.97) !important; }
    .btn:disabled { opacity: .5; cursor: not-allowed; pointer-events: none; }

    .btn-primary { background: var(--gold); border: none; color: #fff; font-weight: 700; box-shadow: 0 1px 3px rgba(217,119,6,.35); }
    .btn-primary:hover { background: var(--gold-lt); color: #fff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(217,119,6,.40); }

    .btn-secondary { background: var(--bg-card); border: 1px solid var(--bd-md); color: var(--tx-2); }
    .btn-secondary:hover { background: var(--bg-hover); color: var(--tx-1); border-color: var(--bd-md); transform: translateY(-1px); box-shadow: var(--shadow); }

    .btn-outline-primary { border: 1.5px solid var(--gold-bd); color: var(--gold); background: transparent; }
    .btn-outline-primary:hover { background: var(--gold-dim); border-color: var(--gold); }

    .btn-danger { background: var(--red); border: none; color: #fff; }
    .btn-danger:hover { background: #b91c1c; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(220,38,38,.30); }

    .btn-success { background: var(--green); border: none; color: #fff; }
    .btn-success:hover { background: #15803d; transform: translateY(-1px); }

    .btn-warning { background: var(--amber); border: none; color: #fff; }
    .btn-warning:hover { background: #b45309; transform: translateY(-1px); }

    .btn-info { background: var(--blue); border: none; color: #fff; }
    .btn-info:hover { background: #1d4ed8; transform: translateY(-1px); }

    .btn-outline-success { border: 1.5px solid var(--green-bd); color: var(--green); background: transparent; }
    .btn-outline-success:hover { background: var(--green-bg); }
    .btn-outline-danger  { border: 1.5px solid var(--red-bd);   color: var(--red);   background: transparent; }
    .btn-outline-danger:hover  { background: var(--red-bg); }
    .btn-outline-warning { border: 1.5px solid var(--amber-bd); color: var(--amber); background: transparent; }
    .btn-outline-warning:hover { background: var(--amber-bg); }

    .btn-sm   { font-size: .82rem; padding: 5px 12px; }
    .btn-lg   { font-size: 1rem;   padding: 12px 24px; border-radius: var(--r); }
    .btn-icon { width: 34px; height: 34px; padding: 0; justify-content: center; border-radius: var(--r-sm); }
    .btn-icon.btn-sm { width: 30px; height: 30px; }
    .btn-group .btn { border-radius: 0; }
    .btn-group .btn:first-child { border-radius: var(--r-sm) 0 0 var(--r-sm); }
    .btn-group .btn:last-child  { border-radius: 0 var(--r-sm) var(--r-sm) 0; }

    /* ── TABLES ───────────────────────────────────────────── */
    .table { color: var(--tx-2); --bs-table-bg: transparent; }
    .table thead th { background: rgba(255,255,255,.05); color: var(--tx-4); border-color: var(--bd); font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; padding: 12px 16px; white-space: nowrap; }
    .table tbody tr { border-color: var(--bd); transition: background .1s; }
    .table tbody tr:hover { background: var(--bg-hover) !important; }
    .table tbody td { padding: 13px 16px; vertical-align: middle; border-color: var(--bd); font-size: .92rem; color: var(--tx-2); }
    .table tbody td strong { color: var(--tx-1); }
    .table-bordered td, .table-bordered th { border-color: var(--bd) !important; }

    /* ── FORMS ────────────────────────────────────────────── */
    .form-control, .form-select {
        background: rgba(255,255,255,.08) !important;
        border: 1.5px solid rgba(255,255,255,.18) !important;
        color: var(--tx-1) !important;
        border-radius: var(--r-sm) !important;
        font-family: inherit; font-size: .92rem;
        transition: border-color var(--dur), box-shadow var(--dur);
    }
    .form-control:focus, .form-select:focus {
        background: rgba(15,15,15,.96) !important;
        border-color: var(--gold) !important;
        color: var(--tx-1) !important;
        box-shadow: 0 0 0 3px rgba(217,119,6,.12) !important;
    }
    .form-control::placeholder { color: var(--tx-4) !important; }
    .form-select option { background: #1a1a1a; color: var(--tx-1); }
    .form-label { font-size: .88rem; font-weight: 600; color: var(--tx-2); margin-bottom: 6px; }
    .form-text  { font-size: .8rem; color: var(--tx-4); }
    .input-group-text { background: var(--bg); border-color: var(--bd-md) !important; color: var(--tx-3); font-size: .9rem; }
    .form-check-input:checked { background-color: var(--gold); border-color: var(--gold); }
    .form-check-label { color: var(--tx-2); font-size: .9rem; }
    .form-switch .form-check-input { background-color: var(--bd-md); }
    .form-switch .form-check-input:checked { background-color: var(--gold); }

    /* ── BADGES ───────────────────────────────────────────── */
    .badge-status { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: .75rem; font-weight: 700; letter-spacing: .2px; white-space: nowrap; border: 1px solid; }
    .badge-status::before { content: ''; width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }

    .badge-active,.badge-paid,.badge-approved,.badge-available,.badge-completed { background: var(--green-bg); color: var(--green); border-color: var(--green-bd); }
    .badge-active::before,.badge-paid::before,.badge-approved::before,.badge-available::before,.badge-completed::before { background: var(--green); }
    .badge-inactive,.badge-cancelled,.badge-terminated { background: var(--gray-bg); color: var(--gray); border-color: var(--gray-bd); }
    .badge-inactive::before,.badge-cancelled::before,.badge-terminated::before { background: var(--gray); }
    .badge-pending,.badge-in_progress,.badge-maintenance { background: var(--amber-bg); color: var(--amber); border-color: var(--amber-bd); }
    .badge-pending::before,.badge-in_progress::before,.badge-maintenance::before { background: var(--amber); }
    .badge-rejected,.badge-overdue,.badge-occupied,.badge-urgent { background: var(--red-bg); color: var(--red); border-color: var(--red-bd); }
    .badge-rejected::before,.badge-overdue::before,.badge-occupied::before,.badge-urgent::before { background: var(--red); }
    .badge-reserved,.badge-expired { background: var(--blue-bg); color: var(--blue); border-color: var(--blue-bd); }
    .badge-reserved::before,.badge-expired::before { background: var(--blue); }

    /* ── ALERTS ───────────────────────────────────────────── */
    .alert { border-radius: var(--r-sm); font-size: .92rem; border-width: 1px; }
    .alert-success { background: var(--green-bg); border-color: var(--green-bd); color: #4ade80; }
    .alert-danger  { background: var(--red-bg);   border-color: var(--red-bd);   color: #f87171; }
    .alert-warning { background: var(--amber-bg);  border-color: var(--amber-bd); color: #fbbf24; }
    .alert-info    { background: var(--blue-bg);   border-color: var(--blue-bd);  color: #60a5fa; }
    .btn-close { filter: invert(1); opacity: .5; }
    .btn-close:hover { opacity: .9; }

    /* ── MODALS — solid, NO backdrop-filter, hard z-index ─── */
    .modal          { z-index: 1055 !important; }
    .modal-backdrop { z-index: 1040 !important; }
    .modal-dialog   { z-index: 1056 !important; position: relative; }
    .modal-content {
        background: #fff !important;
        backdrop-filter: none !important; -webkit-backdrop-filter: none !important;
        border: 1px solid var(--bd) !important;
        border-radius: var(--r) !important;
        color: var(--tx-2) !important;
        box-shadow: 0 20px 60px rgba(0,0,0,.18), 0 4px 16px rgba(0,0,0,.08) !important;
    }
    .modal-header { background: rgba(255,255,255,.05) !important; border-bottom: 1px solid var(--bd) !important; padding: 16px 20px !important; border-radius: var(--r) var(--r) 0 0 !important; }
    .modal-title  { font-size: 1rem; font-weight: 700; color: var(--tx-1) !important; }
    .modal-body   { padding: 20px !important; font-size: .92rem; color: var(--tx-2) !important; }
    .modal-footer { border-top: 1px solid rgba(255,255,255,.10) !important; padding: 13px 20px !important; background: rgba(255,255,255,.03) !important; border-radius: 0 0 var(--r) var(--r) !important; }
    .modal .form-control, .modal .form-select { background: rgba(255,255,255,.08) !important; border-color: rgba(255,255,255,.20) !important; color: var(--tx-1) !important; }
    .modal .form-label { color: var(--tx-2) !important; }

    /* ── DROPDOWN ─────────────────────────────────────────── */
    .dropdown-menu { background: rgba(15,15,15,.88); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid var(--bd); border-radius: var(--r); padding: 5px; box-shadow: var(--shadow-lg); }
    .dropdown-item { color: var(--tx-2); border-radius: var(--r-xs); padding: 9px 13px; font-size: .88rem; font-family: inherit; }
    .dropdown-item:hover { background: var(--bg-hover) !important; color: var(--tx-1) !important; }
    .dropdown-item:hover { background: var(--bg-hover); color: var(--tx-1); }
    .dropdown-divider { border-color: var(--bd); }

    /* ── LIST GROUP ───────────────────────────────────────── */
    .list-group-item { background: rgba(255,255,255,.06); border-color: var(--bd); color: var(--tx-2); font-size: .9rem; }
    .list-group-item:hover { background: var(--bg-hover); }

    /* ── PROGRESS ─────────────────────────────────────────── */
    .progress { background: rgba(255,255,255,.10); border-radius: 6px; height: 7px; }
    .progress-bar { background: var(--gold); border-radius: 6px; }

    /* ── UTILITIES ────────────────────────────────────────── */
    .text-gold, .text-warning { color: var(--gold) !important; }
    .text-muted  { color: var(--tx-3) !important; }
    .text-success{ color: var(--green) !important; }
    .text-danger { color: var(--red) !important; }
    .text-info   { color: var(--blue) !important; }
    small { color: var(--tx-4); font-size: .79rem; }
    hr { border-color: var(--bd); opacity: 1; }

    .info-row { display: flex; justify-content: space-between; align-items: baseline; padding: 10px 0; border-bottom: 1px solid var(--bd); font-size: .92rem; }
    .info-row:last-child { border-bottom: none; }
    .info-key { color: var(--tx-3); font-weight: 500; }
    .info-val { color: var(--tx-1); font-weight: 600; text-align: right; }

    .section-label { font-size: .73rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--tx-4); margin-bottom: 14px; padding-bottom: 9px; border-bottom: 1px solid var(--bd); }

    .priority-pill { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: .74rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; border: 1px solid; }
    .pp-low    { background: var(--gray-bg);  color: var(--gray);  border-color: var(--gray-bd); }
    .pp-medium { background: var(--blue-bg);  color: var(--blue);  border-color: var(--blue-bd); }
    .pp-high   { background: var(--amber-bg); color: var(--amber); border-color: var(--amber-bd); }
    .pp-urgent { background: var(--red-bg);   color: var(--red);   border-color: var(--red-bd); }

    /* ── SPINNER ──────────────────────────────────────────── */
    .spin-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 9999; display: flex; align-items: center; justify-content: center; visibility: hidden; opacity: 0; transition: opacity .15s, visibility .15s; }
    .spin-overlay.on { visibility: visible; opacity: 1; }
    .spin-ring { width: 40px; height: 40px; border-radius: 50%; border: 3px solid #333; border-top-color: var(--gold); animation: spin .65s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── TOAST ────────────────────────────────────────────── */
    #toasts { position: fixed; bottom: 24px; right: 24px; z-index: 9998; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
    .toast-n { background: rgba(20,20,20,.88); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,.15); border-radius: var(--r-sm); padding: 13px 16px; color: var(--tx-2); font-size: .88rem; display: flex; align-items: flex-start; gap: 10px; box-shadow: var(--shadow-lg); min-width: 240px; pointer-events: auto; animation: tin .22s var(--ease); transition: opacity .3s, transform .3s; }
    @keyframes tin { from { transform: translateY(10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

    /* ── SCROLLBAR ────────────────────────────────────────── */
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: rgba(255,255,255,.18); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,.32); }

    /* ── RESPONSIVE ───────────────────────────────────────── */
    @media(max-width:576px){
        .content { padding: 16px 14px; }
        .page-title { font-size: 1.25rem; }
        .stat-value { font-size: 1.75rem; }
        .stat-card { padding: 16px; }
        .page-header { padding-bottom: 16px; }
        .card-body { padding: 15px; }
        .acct-name { display: none; }
    }

    /* ===== IMPROVED LIGHT MODE CONTRAST ===== */
body[data-theme="light"] {

    /* Backgrounds */
    --bg: #F9FAFB !important;
    --bg-nav: #FFFFFF !important;
    --bg-card: #FFFFFF !important;
    --bg-input: #FFFFFF !important;

    /* 👇 KEY FIX: darker surfaces for visibility */
    --bg-hover: #E5E7EB !important;
    --bg-active: #D1D5DB !important;

    /* Borders */
    --bd: #D1D5DB !important;
    --bd-md: #9CA3AF !important;
    --bd-gold: rgba(217,119,6,.4) !important;

    /* 👇 TEXT (DARK for contrast) */
    --tx-1: #111827 !important;   /* headings */
    --tx-2: #374151 !important;   /* body */
    --tx-3: #6B7280 !important;   /* muted */
    --tx-4: #9CA3AF !important;

    /* Cards / glass fix */
    --bg-card: #FFFFFF !important;

    /* Shadows lighter */
    --shadow: 0 2px 10px rgba(0,0,0,.08) !important;
    --shadow-md: 0 4px 16px rgba(0,0,0,.10) !important;
    --shadow-lg: 0 10px 30px rgba(0,0,0,.12) !important;
}

/* Fix cards + nav specifically */
body[data-theme="light"] .card,
body[data-theme="light"] .side-nav,
body[data-theme="light"] .topbar {
    background: #FFFFFF !important;
    border-color: #E5E7EB !important;
}

/* Table readability */
body[data-theme="light"] .table thead th {
    background: #F3F4F6 !important;
    color: #374151 !important;
}

body[data-theme="light"] .table tbody tr:hover {
    background: #F9FAFB !important;
}

/* =========================================================
   🔁 TRUE COLOR INVERSION SYSTEM (FOR YOUR EXISTING DESIGN)
   ========================================================= */

/* ===== LIGHT MODE (INVERTED) ===== */
body[data-theme="light"] {

    /* PAGE BACKGROUND → LIGHT */
    background: #F9FAFB !important;
    color: #111827 !important;
}

/* ===== NAVBAR & SIDEBAR → DARK ===== */
body[data-theme="light"] .topbar,
body[data-theme="light"] .side-nav,
body[data-theme="light"] .nav-header,
body[data-theme="light"] .nav-user,
body[data-theme="light"] .nav-foot {
    background: rgba(20,20,20,.95) !important;
    color: #E5E7EB !important;
    border-color: rgba(0,0,0,.25) !important;
}

/* ===== LINKS / MENU ITEMS ===== */
body[data-theme="light"] .nl {
    color: #D1D5DB !important;
}
body[data-theme="light"] .nl:hover {
    background: rgba(255,255,255,.08) !important;
    color: #FFFFFF !important;
}

/* ===== CARDS → DARK ===== */
body[data-theme="light"] .card,
body[data-theme="light"] .stat-card {
    background: rgba(20,20,20,.95) !important;
    color: #E5E7EB !important;
    border-color: rgba(0,0,0,.25) !important;
}

/* ===== TABLES ===== */
body[data-theme="light"] .table {
    color: #E5E7EB !important;
}
body[data-theme="light"] .table thead th {
    background: rgba(0,0,0,.2) !important;
    color: #F9FAFB !important;
}
body[data-theme="light"] .table tbody tr:hover {
    background: rgba(255,255,255,.05) !important;
}

/* ===== FORMS ===== */
body[data-theme="light"] .form-control,
body[data-theme="light"] .form-select {
    background: rgba(30,30,30,.95) !important;
    color: #F9FAFB !important;
    border-color: rgba(0,0,0,.4) !important;
}

/* ===== BUTTONS ===== */
body[data-theme="light"] .btn-secondary {
    background: rgba(30,30,30,.95) !important;
    color: #F9FAFB !important;
}

/* ===== DROPDOWN ===== */
body[data-theme="light"] .dropdown-menu,
body[data-theme="light"] .acct-menu {
    background: rgba(20,20,20,.95) !important;
    color: #F9FAFB !important;
}

/* ===== TEXT FIX INSIDE DARK ELEMENTS ===== */
body[data-theme="light"] .card,
body[data-theme="light"] .side-nav,
body[data-theme="light"] .topbar {
    --tx-1: #F9FAFB !important;
    --tx-2: #D1D5DB !important;
    --tx-3: #9CA3AF !important;
}

/* ===== INPUT PLACEHOLDER ===== */
body[data-theme="light"] input::placeholder {
    color: #9CA3AF !important;
}

/* ===== SCROLLBAR (OPTIONAL NICE TOUCH) ===== */
body[data-theme="light"] ::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,.3);
}

/* ===== HEADER FIX IN LIGHT MODE ===== */
body[data-theme="light"] .page-header {
    background: rgba(20,20,20,.95) !important;
    border-color: rgba(0,0,0,.25) !important;
}

body[data-theme="light"] .page-title {
    color: #F9FAFB !important;
}

body[data-theme="light"] .page-subtitle {
    color: #D1D5DB !important;
}

/* small label above title */
.page-label {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--tx-4);
    margin-bottom: 4px;
}

/* ===== GLOBAL SMOOTH THEME TRANSITION ===== */
* {
    transition:
        background-color .25s ease,
        color .25s ease,
        border-color .25s ease,
        box-shadow .25s ease !important;
}

/* ===== THEME OVERLAY SYSTEM ===== */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
    transition: background .3s ease;
}

/* DARK MODE OVERLAY */
body[data-theme="dark"]::before {
    background: rgba(0,0,0,0);
}

/* LIGHT MODE OVERLAY */
body[data-theme="light"]::before {
    background: rgba(255,255,255,0.15);
}

/* ===== AUTO TEXT CONTRAST FIX ===== */

/* DARK MODE */
body[data-theme="dark"] {
    color: #D1D5DB !important;
}
body[data-theme="dark"] h1,
body[data-theme="dark"] h2,
body[data-theme="dark"] h3,
body[data-theme="dark"] h4,
body[data-theme="dark"] h5 {
    color: #F9FAFB !important;
}

/* LIGHT MODE */
body[data-theme="light"] {
    color: #374151 !important;
}
body[data-theme="light"] h1,
body[data-theme="light"] h2,
body[data-theme="light"] h3,
body[data-theme="light"] h4,
body[data-theme="light"] h5 {
    color: #111827 !important;
}
    </style>
</head>
<body>
<div class="spin-overlay" id="spin"><div class="spin-ring"></div></div>
<div id="toasts"></div>
<div class="nav-overlay" id="navOverlay"></div>

<!-- ── SIDE NAV ──────────────────────────────────────────── -->
<nav class="side-nav" id="sideNav">
    <div class="nav-header">
        <div class="brand-mark" style="width:28px;height:28px;border-radius:7px">
            <?php if(file_exists('assets/images/logo1.png')):?><img src="assets/images/logo1.png" alt=""><?php else:?><span style="font-size:.82rem;color:#fff;font-weight:900">S</span><?php endif;?>
        </div>
        <div style="line-height:1.2">
            <div style="font-size:.82rem;font-weight:700;color:var(--tx-1)">Sibalom Market</div>
            <div style="font-size:.6rem;color:var(--tx-4)">MSRMS</div>
        </div>
        <button class="nav-close" id="navClose"><i class="fas fa-times"></i></button>
    </div>

    <div class="nav-user">
        <div class="nav-user-av">
            <?php if(!empty($_SESSION['profile_picture'])&&file_exists($_SESSION['profile_picture'])):?><img src="<?php echo htmlspecialchars($_SESSION['profile_picture']);?>" alt=""><?php else:?><?php echo strtoupper(substr($_SESSION['full_name']??'U',0,1));?><?php endif;?>
        </div>
        <div>
            <div class="nav-user-name"><?php echo htmlspecialchars($_SESSION['full_name']??'User');?></div>
            <div class="nav-user-role"><?php echo htmlspecialchars($_SESSION['role']??'');?></div>
        </div>
    </div>

    <div class="nav-scroll">
        <?php
        $cp  = $currentPage ?? '';
        $isV = ($_role==='vendor');
        $isS = ($_role==='staff'||$_role==='administrator');
        $isA = ($_role==='administrator');
        $isAp= ($_role==='applicant');
        ?>
        <?php if(!$isV&&!$isAp):?>
        <div class="nav-sec">Main</div>
        <a class="nl <?php echo $cp==='dashboard'?'active':'';?>" href="dashboard.php"><i class="fas fa-tachometer-alt ni"></i> Dashboard</a>
        <?php endif;?>
        <?php if($isV):?>
        <div class="nav-sec">My Space</div>
        <a class="nl <?php echo $cp==='my-stall'?'active':'';?>" href="mystall.php"><i class="fas fa-store ni"></i> My Stall</a>
        <a class="nl <?php echo $cp==='my-payments'?'active':'';?>" href="mypayment.php"><i class="fas fa-receipt ni"></i> My Payments</a>
        <a class="nl <?php echo $cp==='maintenance'?'active':'';?>" href="maintenance.php"><i class="fas fa-tools ni"></i> Maintenance</a>
        <?php endif;?>
        <?php if($isAp):?>
        <div class="nav-sec">Application</div>
        <a class="nl <?php echo $cp==='my-application'?'active':'';?>" href="applicant-dashboard.php"><i class="fas fa-file-alt ni"></i> My Application</a>
        <?php endif;?>
        <?php if($isS):?>
        <div class="nav-sec">Management</div>
        <a class="nl <?php echo $cp==='vendors'?'active':'';?>" href="vendors.php"><i class="fas fa-users ni"></i> Vendors</a>
        <a class="nl <?php echo $cp==='stalls'?'active':'';?>" href="stalls.php"><i class="fas fa-store ni"></i> Stall Management</a>
        <a class="nl <?php echo $cp==='rentals'?'active':'';?>" href="rental_records.php"><i class="fas fa-file-contract ni"></i> Rental Records</a>
        <a class="nl <?php echo $cp==='appmanagement'?'active':'';?>" href="appmanagement.php">
            <i class="fas fa-file-alt ni"></i> Applications
            <?php try{$n=(int)getDB()->query("SELECT COUNT(*) FROM rental_applications WHERE status='pending'")->fetchColumn();if($n>0)echo "<span class='nav-badge'>$n</span>";}catch(Exception $e){}?>
        </a>
        <div class="nav-sec">Finance</div>
        <a class="nl <?php echo $cp==='payments'?'active':'';?>" href="payments.php"><i class="fas fa-money-bill-wave ni"></i> Payments</a>
        <div class="nav-sec">Operations</div>
        <a class="nl <?php echo $cp==='maintenance'?'active':'';?>" href="maintenance.php"><i class="fas fa-tools ni"></i> Maintenance</a>
        <a class="nl <?php echo $cp==='map'?'active':'';?>" href="map.php"><i class="fas fa-map ni"></i> Interactive Map</a>
        <a class="nl <?php echo $cp==='reports'?'active':'';?>" href="reports.php"><i class="fas fa-chart-bar ni"></i> Reports</a>
        <?php if($isA):?>
        <div class="nav-sec">Admin</div>
        <a class="nl <?php echo $cp==='settings'?'active':'';?>" href="settings.php"><i class="fas fa-cog ni"></i> Settings</a>
        <a class="nl <?php echo $cp==='users'?'active':'';?>" href="users.php"><i class="fas fa-users-cog ni"></i> User Management</a>
        <?php endif;?>
        <?php endif;?>
    </div>

    <div class="nav-foot">
        <a class="nl" href="profile.php"><i class="fas fa-user-circle ni"></i> Profile</a>
        <a class="nl logout-link" href="logout.php"><i class="fas fa-sign-out-alt ni"></i> Logout</a>
    </div>
</nav>
<!-- ── TOPBAR ─────────────────────────────────────────────── -->
<header class="topbar">
    <button class="menu-btn" id="menuBtn"><i class="fas fa-bars" id="menuIcon"></i></button>
    <a class="brand" href="<?php echo $isV?'mystall.php':'dashboard.php';?>">
        <div class="brand-mark">
            <?php if(file_exists('assets/images/logo1.png')):?><img src="assets/images/logo1.png" alt=""><?php else:?><span>S</span><?php endif;?>
        </div>
        <div>
            <div class="brand-name">Sibalom Market Stall</div>
            <div class="brand-sub">Rental &amp; Mapping System</div>
        </div>
    </a>
    <div class="top-spacer"></div>

<button class="menu-btn" id="themeToggle" title="Toggle Theme">
    <i class="fas fa-moon" id="themeIcon"></i>
</button>
    <div style="position:relative">
        <button class="acct-btn" id="acctBtn">
            <div class="acct-avatar">
                <?php if(!empty($_SESSION['profile_picture'])&&file_exists($_SESSION['profile_picture'])):?><img src="<?php echo htmlspecialchars($_SESSION['profile_picture']);?>" alt=""><?php else:?><?php echo strtoupper(substr($_SESSION['full_name']??'U',0,1));?><?php endif;?>
            </div>
            <div style="text-align:left">
                <div class="acct-name"><?php echo htmlspecialchars(explode(' ',$_SESSION['full_name']??'User')[0]);?></div>
                <div class="acct-role"><?php echo htmlspecialchars($_SESSION['role']??'');?></div>
            </div>
            <i class="fas fa-chevron-down acct-chevron"></i>
        </button>
        <div class="acct-menu" id="acctMenu">
            <div class="acct-menu-header">
                <div class="acct-menu-name"><?php echo htmlspecialchars($_SESSION['full_name']??'User');?></div>
                <div class="acct-menu-role"><?php echo htmlspecialchars($_SESSION['role']??'');?></div>
            </div>
            <a class="acct-menu-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
            <?php if($isA):?><a class="acct-menu-item" href="settings.php"><i class="fas fa-cog"></i> Settings</a><?php endif;?>
            <div class="acct-menu-div"></div>
            <a class="acct-menu-item danger logout-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</header>

<main class="content">
<?php
if(function_exists('getMessage')){$msg=getMessage();if($msg):?>
<div class="alert alert-<?php echo htmlspecialchars($msg['type']);?> alert-dismissible fade show alert-auto mb-4">
    <i class="fas fa-<?php $t=$msg['type'];echo $t==='success'?'check-circle':($t==='danger'?'exclamation-circle':($t==='warning'?'exclamation-triangle':'info-circle'));?> me-2"></i>
    <?php echo htmlspecialchars($msg['message']);?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>


<?php endif;}
if(isset($_GET['error'])&&$_GET['error']==='unauthorized'):?>
<div class="alert alert-danger alert-dismissible fade show alert-auto mb-4">
    <i class="fas fa-lock me-2"></i> You don't have permission to access this page.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif;?>