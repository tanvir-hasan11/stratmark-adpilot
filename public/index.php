<?php
declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) require $autoload;
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Env;
use App\Core\Security;
use App\Core\LocalState;
Security::requireAuth();
$env = new Env(dirname(__DIR__) . '/.env');
$localState = new LocalState();
$appState = $localState->all();
$environment = htmlspecialchars($env->get('APP_ENV', 'not configured'), ENT_QUOTES, 'UTF-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
$demo = $env->get('APP_ENV', 'production') !== 'production' && filter_var($env->get('DEMO_MODE', '0'), FILTER_VALIDATE_BOOL);
$summary = ['spend' => 0, 'conversions' => 0, 'revenue' => 0, 'cpa' => 0, 'roas' => 0];
$dbError = null;
$range = in_array((string)($appState['settings']['date_range'] ?? '7'), ['7','30','90'], true) ? (int)$appState['settings']['date_range'] : 7;
try {
    $database = new App\Core\Database($env);
    $repo = new App\Repositories\InsightRepository($database->pdo());
    $summary = $repo->summary(date('Y-m-d', strtotime('-' . ($range - 1) . ' days')), date('Y-m-d'));
} catch (Throwable $e) { $dbError = $e->getMessage(); }
if ($demo && $dbError !== null) $summary = ['spend' => $appState['demo']['spend'], 'conversions' => $appState['demo']['conversions'], 'revenue' => $appState['demo']['revenue'], 'cpa' => $appState['demo']['spend'] / max(1, $appState['demo']['conversions']), 'roas' => $appState['demo']['revenue'] / max(1, $appState['demo']['spend'])];
$hasLiveData = (float)$summary['spend'] > 0 || (float)$summary['conversions'] > 0 || (float)$summary['revenue'] > 0;
$systemStatus = $dbError !== null ? 'Database unavailable' : ($hasLiveData ? 'Live data connected' : 'Awaiting first sync');
$currency = htmlspecialchars((string)($appState['settings']['currency'] ?? $env->get('DEFAULT_CURRENCY', 'USD')), ENT_QUOTES, 'UTF-8');
$brandName = htmlspecialchars((string)($appState['brand']['name'] ?? 'Stratmark Adpilot'), ENT_QUOTES, 'UTF-8');
$logoUrl = !empty($appState['brand']['logo']) ? '/media.php?v=' . rawurlencode((string)($appState['updated_at'] ?? '1')) : null;
$spend = number_format((float)$summary['spend'], 2);
$conversions = number_format((float)$summary['conversions'], 0);
$cpa = number_format((float)$summary['cpa'], 2);
$roas = number_format((float)$summary['roas'], 2);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stratmark Adpilot — Overview</title>
<style>
:root{--navy:#12243a;--ink:#172231;--muted:#718096;--line:#e7edf3;--canvas:#f6f8fb;--white:#fff;--blue:#2864e8;--blue-soft:#edf3ff;--green:#15966b;--green-soft:#e8f8f1;--amber:#b87312;--amber-soft:#fff5e5;--red:#c24d55;--shadow:0 8px 24px rgba(22,43,70,.06)}*{box-sizing:border-box}body{margin:0;background:var(--canvas);color:var(--ink);font:14px/1.5 Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}button{font:inherit;border:0;cursor:pointer}a{text-decoration:none;color:inherit}.shell{display:flex;min-height:100vh}.sidebar{width:238px;flex:none;background:var(--navy);color:#d8e1ec;padding:25px 15px;display:flex;flex-direction:column}.brand{display:flex;gap:11px;align-items:center;padding:0 12px 28px;color:white}.brand-mark{width:34px;height:34px;border-radius:10px;background:#4f8aff;display:grid;place-items:center;font-weight:800;letter-spacing:-1px;box-shadow:0 5px 12px #07172e}.brand strong{font-size:16px;letter-spacing:-.3px}.brand small{display:block;color:#94aac4;font-size:10px;margin-top:1px}.nav-label{font-size:10px;text-transform:uppercase;letter-spacing:1.2px;color:#7188a3;padding:12px 13px 8px}.nav a{display:flex;align-items:center;gap:11px;padding:11px 13px;border-radius:8px;margin:2px 0;color:#b8c7d9}.nav a:hover,.nav a.active{background:#1e3856;color:#fff}.nav a.active{box-shadow:inset 3px 0 #5e94ff}.nav-icon{width:18px;text-align:center;opacity:.9}.sidebar-bottom{margin-top:auto;border-top:1px solid #29415d;padding:16px 12px 0}.sync-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#30c794;margin-right:6px}.sidebar-bottom small{color:#91a6bd;font-size:11px}.main{min-width:0;flex:1}.topbar{height:76px;background:var(--white);border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 34px}.crumb{color:var(--muted);font-size:13px}.crumb strong{color:var(--ink);font-weight:650}.top-actions{display:flex;align-items:center;gap:18px}.icon-btn{background:transparent;color:#65758a;font-size:19px;position:relative;padding:7px}.badge{position:absolute;right:2px;top:2px;width:7px;height:7px;border-radius:50%;background:#e35c64;border:2px solid white}.profile{display:flex;align-items:center;gap:9px;border-left:1px solid var(--line);padding-left:18px}.avatar{width:33px;height:33px;border-radius:50%;display:grid;place-items:center;background:#e6efff;color:#2864e8;font-weight:700;font-size:12px}.profile small{display:block;color:var(--muted);font-size:11px}.content{max-width:1440px;margin:0 auto;padding:31px 34px 45px}.welcome{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:27px}.eyebrow{color:var(--blue);font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:1.1px}.welcome h1{font-size:26px;letter-spacing:-.7px;margin:5px 0 5px}.welcome p{margin:0;color:var(--muted)}.date-select{background:white;border:1px solid var(--line);border-radius:7px;padding:10px 13px;color:#4a5b70;box-shadow:0 2px 4px #12243a08}.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px}.card{background:var(--white);border:1px solid var(--line);border-radius:10px;box-shadow:var(--shadow)}.metric{padding:18px 19px}.metric-head{display:flex;justify-content:space-between;align-items:center;color:var(--muted);font-size:12px;font-weight:600}.metric-icon{width:29px;height:29px;border-radius:8px;display:grid;place-items:center;font-size:14px}.metric-value{font-size:25px;font-weight:700;letter-spacing:-.7px;margin:12px 0 7px}.trend{font-size:11px;color:var(--green);font-weight:650}.trend.down{color:var(--red)}.trend span{color:var(--muted);font-weight:400;margin-left:4px}.grid{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(280px,.9fr);gap:20px;margin-bottom:20px}.panel-head{padding:19px 20px 14px;display:flex;justify-content:space-between;align-items:flex-start}.panel-title{margin:0;font-size:15px}.panel-sub{margin:3px 0 0;color:var(--muted);font-size:12px}.link{color:var(--blue);font-size:12px;font-weight:650}.chart-wrap{padding:7px 18px 17px}.chart{height:190px;width:100%;display:block}.chart-labels{display:flex;justify-content:space-between;color:#97a5b5;font-size:10px;padding:4px 6px 0}.legend{display:flex;gap:17px;font-size:11px;color:var(--muted);margin-top:11px}.legend i{display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:5px}.alert-list{padding:0 20px 13px}.alert{display:flex;align-items:flex-start;gap:10px;padding:13px 0;border-bottom:1px solid #eef2f5}.alert:last-child{border:0}.alert-mark{width:25px;height:25px;border-radius:7px;display:grid;place-items:center;flex:none;font-size:12px}.alert strong{display:block;font-size:12px;margin-bottom:2px}.alert p{margin:0;color:var(--muted);font-size:11px}.table-panel{overflow:hidden}.table-scroll{overflow:auto}.data-table{width:100%;border-collapse:collapse;min-width:620px}.data-table th,.data-table td{text-align:left;padding:13px 20px;border-top:1px solid #eef2f5;white-space:nowrap}.data-table th{color:#8997a8;font-size:10px;text-transform:uppercase;letter-spacing:.7px;font-weight:700;background:#fbfcfd}.data-table td{font-size:12px}.campaign{display:flex;align-items:center;gap:9px;font-weight:650}.platform{width:26px;height:26px;border-radius:7px;display:grid;place-items:center;font-size:10px;font-weight:800}.meta{background:#e9efff;color:#2764e5}.google{background:#e8f7ee;color:#1f9a63}.status{font-size:10px;border-radius:20px;padding:4px 8px;font-weight:700}.status.live{color:var(--green);background:var(--green-soft)}.status.learning{color:var(--amber);background:var(--amber-soft)}.bar{height:5px;background:#edf1f5;border-radius:5px;width:70px;display:inline-block;vertical-align:middle;margin-right:7px}.bar b{height:100%;display:block;background:#4f8aff;border-radius:5px}.footer-note{color:#98a5b5;font-size:11px;margin-top:20px}.mobile-menu{display:none}.menu-backdrop{display:none}.sidebar-close{display:none}
@media(max-width:900px){.sidebar{width:205px}.topbar,.content{padding-left:22px;padding-right:22px}.metrics{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}}
@media(max-width:640px){.sidebar{display:flex;position:fixed;z-index:20;inset:0 auto 0 0;width:270px;transform:translateX(-105%);transition:transform .2s ease;box-shadow:12px 0 28px rgba(10,30,55,.2)}body.menu-open .sidebar{transform:translateX(0)}body.menu-open .menu-backdrop{display:block;position:fixed;z-index:10;inset:0;background:rgba(10,25,45,.42)}.sidebar-close{display:block;position:absolute;right:13px;top:18px;background:transparent;color:#b8c7d9;font-size:22px;padding:4px}.mobile-menu{display:inline-block}.topbar{height:64px;padding:0 16px}.top-actions{gap:7px}.profile div:last-child{display:none}.content{padding:24px 15px}.welcome{display:block}.welcome h1{font-size:23px}.date-select{margin-top:16px;width:100%}.metrics{gap:10px}.metric{padding:14px}.metric-value{font-size:21px}.metric-head{font-size:11px}.grid{gap:13px}.panel-head{padding:16px}.chart-wrap{padding:5px 10px 14px}.chart{height:165px}.data-table th,.data-table td{padding-left:14px;padding-right:14px}}
</style>
</head>
<body>
<div class="shell"><div class="menu-backdrop" aria-hidden="true"></div>
<aside class="sidebar"><button class="sidebar-close" type="button" aria-label="Close menu">×</button>
  <div class="brand"><?php if ($logoUrl): ?><img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Brand logo" style="width:34px;height:34px;border-radius:10px;object-fit:contain;background:#fff;padding:4px"><?php else: ?><div class="brand-mark">SA</div><?php endif; ?><div><strong><?= $brandName ?></strong><small>ADPILOT</small></div></div>
  <nav class="nav"><div class="nav-label">Workspace</div><a class="active" href="#"><span class="nav-icon">▦</span>Overview</a><a href="/page.php?view=campaigns"><span class="nav-icon">◉</span>Campaigns</a><a href="/page.php?view=creatives"><span class="nav-icon">◌</span>Creatives</a><a href="/page.php?view=insights"><span class="nav-icon">⌁</span>Insights</a><div class="nav-label">Control</div><a href="/page.php?view=approvals"><span class="nav-icon">✓</span>Approvals <span style="margin-left:auto;background:#e7efff;color:#4f8aff;border-radius:12px;padding:2px 6px;font-size:10px">3</span></a><a href="/page.php?view=automations"><span class="nav-icon">↗</span>Automations</a><a href="/page.php?view=activity"><span class="nav-icon">≡</span>Activity log</a><div class="nav-label">Settings</div><a href="/settings.php"><span class="nav-icon">⚙</span>Workspace settings</a></nav>
  <div class="sidebar-bottom"><small><span class="sync-dot"></span><?= htmlspecialchars($systemStatus, ENT_QUOTES, 'UTF-8') ?></small><br><small><?= $hasLiveData ? 'Metrics available for selected range' : 'No completed sync in selected range' ?></small></div>
</aside>
<main class="main">
<header class="topbar"><div class="crumb"><button class="icon-btn mobile-menu" type="button" aria-label="Open menu" aria-expanded="false">☰</button> <strong>Overview</strong> <span> / Performance</span></div><div class="top-actions"><button class="icon-btn">⌕</button><button class="icon-btn">♢<i class="badge"></i></button><div class="profile"><div class="avatar">SA</div><div><strong>Admin</strong><small><a href="/logout.php">Sign out</a></small></div></div></div></header>
<section class="content">
  <div class="welcome"><div><div class="eyebrow">Good morning, Admin</div><h1>Campaign overview</h1><p>Here’s how your advertising is performing across all channels.</p></div><select class="date-select" aria-label="Date range"><option>Last 7 days · Jun 10–16, 2025</option><option>Last 30 days</option><option>This month</option></select></div>
  <div class="metrics">
    <div class="card metric"><div class="metric-head"><span>Total spend</span><span class="metric-icon" style="background:#edf3ff;color:#2864e8">$</span></div><div class="metric-value"><?= $currency ?> <?= $spend ?></div><div class="trend">↑ 12.8% <span>vs. previous period</span></div></div>
    <div class="card metric"><div class="metric-head"><span>Conversions</span><span class="metric-icon" style="background:#e8f8f1;color:#15966b">↗</span></div><div class="metric-value"><?= $conversions ?></div><div class="trend">↑ 18.4% <span>vs. previous period</span></div></div>
    <div class="card metric"><div class="metric-head"><span>Average CPA</span><span class="metric-icon" style="background:#fff5e5;color:#b87312">◎</span></div><div class="metric-value"><?= $currency ?> <?= $cpa ?></div><div class="trend">↓ 4.2% <span>vs. previous period</span></div></div>
    <div class="card metric"><div class="metric-head"><span>Blended ROAS</span><span class="metric-icon" style="background:#f1edff;color:#7855d7">✦</span></div><div class="metric-value"><?= $roas ?>x</div><div class="trend">↑ 9.6% <span>vs. previous period</span></div></div>
  </div>
  <div class="grid"><section class="card"><div class="panel-head"><div><h2 class="panel-title">Spend & conversions</h2><p class="panel-sub">Daily performance across Meta and Google Ads</p></div><a class="link" href="/page.php?view=insights">View report →</a></div><div class="chart-wrap"><svg class="chart" viewBox="0 0 700 190" preserveAspectRatio="none" role="img" aria-label="Spend and conversions trend"><defs><linearGradient id="fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2864e8" stop-opacity=".18"/><stop offset="1" stop-color="#2864e8" stop-opacity="0"/></linearGradient></defs><g stroke="#eef2f6" stroke-width="1"><path d="M0 25H700M0 73H700M0 121H700M0 169H700"/></g><path d="M0 146 C50 142 75 125 105 135 S165 112 205 120 S260 85 300 105 S355 91 395 96 S450 67 490 83 S550 53 590 67 S655 36 700 42 L700 190 L0 190Z" fill="url(#fill)"/><path d="M0 146 C50 142 75 125 105 135 S165 112 205 120 S260 85 300 105 S355 91 395 96 S450 67 490 83 S550 53 590 67 S655 36 700 42" fill="none" stroke="#2864e8" stroke-width="3"/><path d="M0 161 C55 153 80 150 108 157 S163 134 207 148 S260 121 302 137 S355 131 398 137 S450 110 491 124 S549 105 591 114 S655 94 700 100" fill="none" stroke="#35b98b" stroke-width="2.5" stroke-dasharray="5 5"/></svg><div class="chart-labels"><span>Jun 10</span><span>Jun 11</span><span>Jun 12</span><span>Jun 13</span><span>Jun 14</span><span>Jun 15</span><span>Jun 16</span></div><div class="legend"><span><i style="background:#2864e8"></i>Spend</span><span><i style="background:#35b98b"></i>Conversions</span></div></div></section>
  <section class="card"><div class="panel-head"><div><h2 class="panel-title">Needs attention</h2><p class="panel-sub">Signals from your ad accounts</p></div><span class="link">3 open</span></div><div class="alert-list"><div class="alert"><div class="alert-mark" style="background:#fff5e5;color:#b87312">!</div><div><strong>Creative fatigue detected</strong><p>3 ads in Prospecting · Meta</p></div></div><div class="alert"><div class="alert-mark" style="background:#edf3ff;color:#2864e8">↗</div><div><strong>Budget opportunity</strong><p>Move budget to “Summer offer”</p></div></div><div class="alert"><div class="alert-mark" style="background:#e8f8f1;color:#15966b">✓</div><div><strong>Tracking is healthy</strong><p>All active pixels reporting</p></div></div></div></section></div>
  <section class="card table-panel"><div class="panel-head"><div><h2 class="panel-title">Top campaigns</h2><p class="panel-sub">Ranked by attributed revenue</p></div><a class="link" href="/page.php?view=campaigns">View all campaigns →</a></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Campaign</th><th>Platform</th><th>Status</th><th>Spend</th><th>Conversions</th><th>CPA</th><th>ROAS</th></tr></thead><tbody><tr><td><div class="campaign"><span class="platform meta">M</span> Summer offer · Prospecting</div></td><td>Meta Ads</td><td><span class="status live">Active</span></td><td>$8,420</td><td>482</td><td>$17.47</td><td><span class="bar"><b style="width:82%"></b></span><strong>6.1x</strong></td></tr><tr><td><div class="campaign"><span class="platform google">G</span Brand search · Core</div></td><td>Google Ads</td><td><span class="status live">Active</span></td><td>$5,960</td><td>316</td><td>$18.86</td><td><span class="bar"><b style="width:71%"></b></span><strong>5.3x</strong></td></tr><tr><td><div class="campaign"><span class="platform meta">M</span> New arrivals · Retargeting</div></td><td>Meta Ads</td><td><span class="status learning">Learning</span></td><td>$4,280</td><td>198</td><td>$21.62</td><td><span class="bar"><b style="width:57%"></b></span><strong>4.2x</strong></td></tr></tbody></table></div></section>
  <div class="footer-note">Data shown is from the connected Meta and Google Ads accounts · <?= $demo && !$hasLiveData ? 'Demo preview data' : ($hasLiveData ? 'Live data' : 'No synced data') ?> · Environment: <?= $environment ?></div>
</section>
</main>
</div>
<script>
(function () {
  const body = document.body;
  const openButton = document.querySelector('.mobile-menu');
  const closeButton = document.querySelector('.sidebar-close');
  const backdrop = document.querySelector('.menu-backdrop');
  const links = document.querySelectorAll('.sidebar a');
  function setOpen(open) {
    body.classList.toggle('menu-open', open);
    if (openButton) openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  openButton?.addEventListener('click', () => setOpen(true));
  closeButton?.addEventListener('click', () => setOpen(false));
  backdrop?.addEventListener('click', () => setOpen(false));
  links.forEach(link => link.addEventListener('click', () => setOpen(false)));
  document.addEventListener('keydown', event => { if (event.key === 'Escape') setOpen(false); });
})();
</script>
</body>
</html>
