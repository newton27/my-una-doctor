<?php defined('BX_DOL') or die('hack attempt');

/**
 * Self-contained public maintenance page used by My UNA Doctor.
 *
 * The page deliberately has no external CSS, JavaScript, fonts, images or UNA
 * cache assets. During soft maintenance the public request is terminated by
 * UNA before normal page rendering, so every visual and the countdown must be
 * able to run from the HTML stored in sys_maintenance_mode_html alone.
 */
class MyUnaDoctorMaintenance
{
    /**
     * Build the public maintenance page.
     *
     * @param int $iStartedAt Unix timestamp when Doctor armed maintenance.
     * @param int $iEtaAt Unix timestamp for the estimated return target.
     * @return string Complete HTML document suitable for sys_maintenance_mode_html.
     */
    public static function buildPage($iStartedAt, $iEtaAt)
    {
        $iStartedAt = max(1, (int)$iStartedAt);
        $iEtaAt = max($iStartedAt + 60, (int)$iEtaAt);

        $sStart = (string)$iStartedAt;
        $sEta = (string)$iEtaAt;

        return strtr(<<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Scheduled Maintenance | My UNA Doctor</title>
<style>
:root{--navy:#081943;--ink:#14264e;--muted:#607193;--line:#dbe7f4;--soft:#f5f9fd;--teal:#0bb3ae;--cyan:#1597ed;--green:#22a864;--purple:#7051d8;--card:#fff;--shadow:0 18px 55px rgba(22,55,103,.10)}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;color:var(--navy);background:radial-gradient(circle at 78% 38%,rgba(29,151,237,.08),transparent 25%),linear-gradient(180deg,#fff 0,#f8fbfe 100%)}
body{min-height:100vh}.mud-page{min-height:100vh;display:flex;flex-direction:column}.mud-header{height:112px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 4.5vw;background:rgba(255,255,255,.94)}
.mud-brand{display:flex;align-items:center;gap:15px}.mud-heart{width:58px;height:52px;border-radius:19px 19px 24px 24px;background:linear-gradient(145deg,#16b8b0,#078ba2);display:grid;place-items:center;box-shadow:0 8px 24px rgba(11,179,174,.18)}.mud-heart svg{width:42px;height:42px}.mud-brandtext strong{display:block;font-size:31px;line-height:1;font-weight:800;letter-spacing:-.8px}.mud-brandtext span{display:block;margin-top:4px;font-size:15px;color:var(--muted)}
.mud-header-left{display:flex;align-items:center;gap:30px}.mud-divider{width:1px;height:44px;background:var(--line)}.mud-pill{display:flex;align-items:center;gap:10px;padding:13px 23px;border-radius:999px;background:#e8f8f7;color:#078b92;font-weight:800;font-size:17px}.mud-trust{display:flex;align-items:center;gap:12px;text-align:left}.mud-trust svg{width:38px;height:38px}.mud-trust strong{display:block;font-size:15px}.mud-trust span{display:block;color:var(--muted);font-size:13px;margin-top:4px}
.mud-main{width:min(1500px,92vw);margin:30px auto 0;display:grid;grid-template-columns:minmax(0,1.38fr) minmax(420px,.9fr);gap:52px;align-items:start}.mud-hero{padding:20px 8px 0}.mud-hero h1{font-size:clamp(50px,5vw,80px);line-height:.98;margin:0;letter-spacing:-3px;font-weight:850}.mud-hero h1 .accent{color:var(--teal)}.mud-cross{display:inline-grid;place-items:center;width:.82em;height:.82em;border-radius:18px;background:linear-gradient(160deg,#12b5b1,#0b7fb0);color:#fff;font-size:.72em;vertical-align:.08em;margin-left:.12em;box-shadow:0 10px 26px rgba(15,148,182,.20)}
.mud-lead{font-size:20px;line-height:1.6;color:var(--muted);max-width:760px;margin:34px 0 52px}.mud-progress-title{display:flex;justify-content:space-between;max-width:760px;font-size:17px;font-weight:800;margin-bottom:18px}.mud-progress-title span:last-child{color:#079da5}.mud-track{height:28px;max-width:760px;border-radius:999px;background:#e7edf5;overflow:hidden;box-shadow:inset 0 1px 2px rgba(8,25,67,.06)}.mud-fill{height:100%;width:0;border-radius:inherit;background:linear-gradient(90deg,#1479ef,#0bb3ae);transition:width .55s ease}.mud-verified{display:flex;align-items:center;gap:12px;margin-top:22px;color:var(--muted);font-size:15px}.mud-check{width:25px;height:25px;border-radius:50%;display:grid;place-items:center;background:var(--teal);color:#fff;font-weight:900}
.mud-side{position:relative}.mud-timer{background:var(--card);border:1px solid var(--line);border-radius:26px;padding:24px 28px 20px;box-shadow:var(--shadow)}.mud-timer-title{display:flex;align-items:center;gap:13px;font-weight:800;font-size:19px}.mud-clock{width:39px;height:39px;border-radius:50%;display:grid;place-items:center;background:#edf6ff;color:#117be8}.mud-count{display:grid;grid-template-columns:1fr auto 1fr auto 1fr;align-items:center;text-align:center;margin:20px 0 13px}.mud-num{font-variant-numeric:tabular-nums;font-size:clamp(49px,4vw,68px);line-height:1;font-weight:780;letter-spacing:1px}.mud-colon{font-size:43px;line-height:1;color:#22345d;padding:0 6px 20px}.mud-unit{display:block;color:#0d76df;font-size:13px;font-weight:800;margin-top:11px;letter-spacing:.3px}.mud-timer-foot{border-top:1px solid var(--line);margin-top:18px;padding-top:16px;display:flex;align-items:center;gap:13px}.mud-timer-foot .icon{width:43px;height:43px;border-radius:50%;display:grid;place-items:center;background:#eef6ff;color:#157ee8;font-size:23px}.mud-timer-foot strong{display:block;font-size:15px}.mud-timer-foot span{display:block;color:var(--muted);font-size:14px;margin-top:5px}
.mud-visual{height:305px;position:relative;margin-top:18px}.mud-pulse{position:absolute;left:0;right:0;top:135px;height:70px}.mud-server{position:absolute;left:12%;bottom:30px;width:160px}.mud-server i{display:block;height:40px;margin:5px 0;border-radius:9px;background:linear-gradient(180deg,#edf3f8,#cbd9e5);box-shadow:0 6px 12px rgba(22,55,103,.12);position:relative}.mud-server i:before{content:"";position:absolute;left:16px;top:15px;width:48px;height:9px;border-radius:5px;background:repeating-linear-gradient(90deg,#0ba9aa 0 7px,#9ad7d7 7px 11px)}.mud-server i:after{content:"";position:absolute;right:17px;top:17px;width:47px;height:5px;border-radius:3px;background:repeating-linear-gradient(90deg,#6f88a5 0 3px,transparent 3px 7px)}
.mud-shield{position:absolute;left:40%;bottom:20px;width:190px;height:218px;filter:drop-shadow(0 18px 20px rgba(15,62,111,.18))}.mud-monitor{position:absolute;right:2%;bottom:37px;width:185px;height:120px;border:10px solid #cedae6;border-bottom-width:18px;border-radius:15px;background:#0b3859;box-shadow:0 11px 23px rgba(21,53,87,.17)}.mud-monitor:before{content:"";position:absolute;left:18px;right:18px;top:43px;height:2px;background:#10c2bf;box-shadow:25px -18px 0 -1px #10c2bf,52px 12px 0 -1px #10c2bf}.mud-monitor:after{content:"";position:absolute;width:75px;height:10px;background:#c9d6e3;bottom:-29px;left:45px;border-radius:0 0 10px 10px}.mud-wrench{position:absolute;right:0;bottom:20px;font-size:76px;line-height:1;filter:grayscale(.6);opacity:.75}.mud-ok{position:absolute;left:49%;bottom:12px;width:58px;height:58px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(145deg,#0bb4b1,#038ea0);color:#fff;font-size:35px;font-weight:900;box-shadow:0 10px 20px rgba(8,147,156,.22)}
.mud-status{width:min(1540px,94vw);margin:18px auto 0;background:rgba(255,255,255,.95);border:1px solid var(--line);border-radius:24px;box-shadow:0 10px 35px rgba(22,55,103,.06);display:grid;grid-template-columns:repeat(4,1fr);padding:26px 28px}.mud-card{display:grid;grid-template-columns:70px 1fr;gap:17px;align-items:center;padding:0 23px;border-right:1px solid var(--line)}.mud-card:last-child{border-right:0}.mud-card-icon{width:66px;height:66px;border-radius:50%;display:grid;place-items:center;font-size:30px}.mud-card:nth-child(1) .mud-card-icon{background:#e5f8f7;color:#07999b}.mud-card:nth-child(2) .mud-card-icon{background:#eaf3ff;color:#1479ef}.mud-card:nth-child(3) .mud-card-icon{background:#f1edff;color:var(--purple)}.mud-card:nth-child(4) .mud-card-icon{background:#eaf8ef;color:var(--green)}.mud-card small{font-size:15px;font-weight:800}.mud-card:nth-child(1) small{color:#07999b}.mud-card:nth-child(2) small{color:#1479ef}.mud-card:nth-child(3) small{color:var(--purple)}.mud-card:nth-child(4) small{color:var(--green)}.mud-card strong{display:block;font-size:16px;margin:7px 0 7px}.mud-card p{margin:0;color:var(--muted);font-size:13px;line-height:1.55}
.mud-footer{width:min(1540px,94vw);margin:18px auto 28px;border:1px solid #e5eef8;border-radius:18px;padding:18px 28px;background:rgba(255,255,255,.78);display:flex;justify-content:center;align-items:center;gap:18px;color:var(--muted);font-size:14px}.mud-footer strong{color:#1279df}.mud-footer .sep{height:27px;width:1px;background:var(--line)}
@media(max-width:1050px){.mud-header{height:auto;min-height:96px;padding:20px 4vw}.mud-trust{display:none}.mud-main{grid-template-columns:1fr;gap:25px}.mud-hero{padding-top:0}.mud-lead{margin-bottom:35px}.mud-side{display:grid;grid-template-columns:1fr 1fr;gap:18px}.mud-visual{margin-top:0}.mud-status{grid-template-columns:1fr 1fr;gap:20px}.mud-card{border-right:0;border-bottom:1px solid var(--line);padding-bottom:18px}.mud-card:nth-child(3),.mud-card:nth-child(4){border-bottom:0}}
@media(max-width:720px){.mud-header-left{gap:14px}.mud-divider,.mud-pill{display:none}.mud-heart{width:48px;height:44px}.mud-brandtext strong{font-size:25px}.mud-main{width:90vw;margin-top:22px}.mud-hero h1{font-size:49px;letter-spacing:-2px}.mud-lead{font-size:17px;margin-top:25px}.mud-side{grid-template-columns:1fr}.mud-visual{height:240px}.mud-server{left:3%;transform:scale(.8);transform-origin:left bottom}.mud-shield{left:36%;transform:scale(.78);transform-origin:center bottom}.mud-monitor{right:0;transform:scale(.72);transform-origin:right bottom}.mud-wrench{display:none}.mud-status{grid-template-columns:1fr;padding:22px}.mud-card{border-bottom:1px solid var(--line)!important;padding:16px 4px}.mud-card:last-child{border-bottom:0!important}.mud-footer{flex-direction:column;text-align:center}.mud-footer .sep{display:none}}
@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;transition:none!important}}
</style>
</head>
<body>
<div class="mud-page" id="mud-maintenance" data-start="__START__" data-end="__ETA__">
<header class="mud-header">
  <div class="mud-header-left">
    <div class="mud-brand">
      <div class="mud-heart" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none"><path d="M8 34h11l4-12 7 25 7-34 7 21h12" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      <div class="mud-brandtext"><strong>My UNA Doctor</strong><span>Powered by UNA CMS</span></div>
    </div>
    <div class="mud-divider"></div>
    <div class="mud-pill"><span aria-hidden="true">🛠</span> Maintenance</div>
  </div>
  <div class="mud-trust"><svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M24 4l16 6v12c0 10-6 17-16 22C14 39 8 32 8 22V10l16-6z" stroke="#6d82a5" stroke-width="3"/><path d="M17 23l5 5 10-11" stroke="#6d82a5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg><div><strong>Secure. Verified. Trusted.</strong><span>Your community, our priority.</span></div></div>
</header>

<main class="mud-main">
  <section class="mud-hero">
    <h1>Scheduled Maintenance<br>in <span class="accent">Progress</span><span class="mud-cross" aria-hidden="true">+</span></h1>
    <p class="mud-lead">Our site is temporarily unavailable while My UNA Doctor performs a verified maintenance workflow. The public site will return automatically when maintenance is complete.</p>
    <div class="mud-progress-title"><span>Estimated progress</span><span id="mud-progress-label">Starting…</span></div>
    <div class="mud-track" role="progressbar" aria-label="Estimated maintenance progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="mud-fill" id="mud-progress-fill"></div></div>
    <div class="mud-verified"><span class="mud-check">✓</span><span>Maintenance workflow verified and running under My UNA Doctor.</span></div>
  </section>

  <aside class="mud-side">
    <section class="mud-timer" aria-label="Estimated time until return">
      <div class="mud-timer-title"><span class="mud-clock" aria-hidden="true">◷</span>Estimated time until return</div>
      <div class="mud-count" aria-live="polite">
        <div><div class="mud-num" id="mud-hours">00</div><span class="mud-unit">HOURS</span></div><div class="mud-colon">:</div>
        <div><div class="mud-num" id="mud-minutes">00</div><span class="mud-unit">MINUTES</span></div><div class="mud-colon">:</div>
        <div><div class="mud-num" id="mud-seconds">00</div><span class="mud-unit">SECONDS</span></div>
      </div>
      <div class="mud-timer-foot"><span class="icon" aria-hidden="true">▣</span><div><strong id="mud-timer-message">We appreciate your patience.</strong><span id="mud-timer-sub">We’ll be back online as soon as the verified workflow finishes.</span></div></div>
    </section>

    <div class="mud-visual" aria-hidden="true">
      <svg class="mud-pulse" viewBox="0 0 600 80" preserveAspectRatio="none"><path d="M0 43h75l11-25 15 49 16-34 16 10h85l12-22 15 43 18-53 20 32h317" fill="none" stroke="#24c6c3" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" opacity=".82"/></svg>
      <div class="mud-server"><i></i><i></i><i></i><i></i></div>
      <svg class="mud-shield" viewBox="0 0 200 230"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#20b8c0"/><stop offset="1" stop-color="#075b9b"/></linearGradient></defs><path d="M100 5L185 37v68c0 58-33 96-85 119-52-23-85-61-85-119V37L100 5z" fill="#dfe9f3" stroke="#b9c8d8" stroke-width="5"/><path d="M100 18l70 27v59c0 47-25 79-70 101-45-22-70-54-70-101V45l70-27z" fill="url(#g)"/><rect x="81" y="62" width="38" height="101" rx="7" fill="#fff"/><rect x="50" y="94" width="100" height="38" rx="7" fill="#fff"/></svg>
      <div class="mud-monitor"></div><div class="mud-wrench">🔧</div><div class="mud-ok">✓</div>
    </div>
  </aside>
</main>

<section class="mud-status">
  <div class="mud-card"><div class="mud-card-icon">⚙</div><div><small>Status</small><strong>Maintenance mode active</strong><p>The site is in controlled soft maintenance.</p></div></div>
  <div class="mud-card"><div class="mud-card-icon">⬡</div><div><small>Module</small><strong>My UNA Doctor</strong><p>The verified workflow is managed by Doctor.</p></div></div>
  <div class="mud-card"><div class="mud-card-icon">↻</div><div><small>Next step</small><strong>Restoring services</strong><p>Doctor will return the public site after validation.</p></div></div>
  <div class="mud-card"><div class="mud-card-icon">✓</div><div><small>Data safety</small><strong>Rollback state preserved</strong><p>Original maintenance state is retained for recovery.</p></div></div>
</section>

<footer class="mud-footer"><strong>My UNA Doctor maintenance</strong><span class="sep"></span><span>Thank you for your patience while the verified workflow completes.</span></footer>
</div>
<script>
(function(){
  'use strict';
  var root=document.getElementById('mud-maintenance');
  if(!root)return;
  var start=Number(root.getAttribute('data-start'))*1000;
  var end=Number(root.getAttribute('data-end'))*1000;
  var duration=Math.max(60000,end-start);
  var hours=document.getElementById('mud-hours');
  var minutes=document.getElementById('mud-minutes');
  var seconds=document.getElementById('mud-seconds');
  var fill=document.getElementById('mud-progress-fill');
  var label=document.getElementById('mud-progress-label');
  var track=fill.parentNode;
  var msg=document.getElementById('mud-timer-message');
  var sub=document.getElementById('mud-timer-sub');
  function pad(n){return String(Math.max(0,n)).padStart(2,'0');}
  function render(){
    var now=Date.now();
    var remaining=Math.max(0,end-now);
    var totalSeconds=Math.floor(remaining/1000);
    var h=Math.floor(totalSeconds/3600);
    var m=Math.floor((totalSeconds%3600)/60);
    var s=totalSeconds%60;
    hours.textContent=pad(h);minutes.textContent=pad(m);seconds.textContent=pad(s);
    var elapsed=Math.max(0,Math.min(duration,now-start));
    var percent=Math.floor((elapsed/duration)*100);
    if(remaining>0){percent=Math.min(94,Math.max(1,percent));label.textContent=percent+'% Complete';msg.textContent='We appreciate your patience.';sub.textContent='Estimated return time is counting down live.';}
    else{percent=95;label.textContent='Final verification';msg.textContent='Final verification in progress.';sub.textContent='The estimate has elapsed; Doctor will restore the site only after validation succeeds.';}
    fill.style.width=percent+'%';track.setAttribute('aria-valuenow',String(percent));
  }
  render();window.setInterval(render,1000);
})();
</script>
</body>
</html>
HTML
        , array(
            '__START__' => $sStart,
            '__ETA__' => $sEta,
        ));
    }
}
