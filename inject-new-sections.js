/**
 * inject-new-sections.js
 *
 * Injects 8 new landing page sections into output2/index.html.
 * Uses comment markers so re-runs are safe (old blocks removed, new inserted).
 *
 * Injection map (inserted BEFORE the next Elementor section):
 *   1. News Ticker       → before About     (4d857de7)
 *   2. Scholar Spotlight → before Categories (6c7c1b5d)
 *   3. Trending Topics   → before Contributor (4f7bfe8e)
 *   4. Quote of the Week → before Contributor (4f7bfe8e), after Trending
 *   5. Tabs (Past/Present/Upcoming) → before Contributor (4f7bfe8e), after Quote
 *   6. Countdown Timer   → before Contributor (4f7bfe8e), after Tabs
 *   7. Readers Poll      → before Contributor (4f7bfe8e), after Countdown
 *   8. Social Proof      → before How It Works (27d63d2d)
 */

import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const FILE = fileURLToPath(new URL('./output2/index.html', import.meta.url));

// ─── Section HTML blocks ────────────────────────────────────────────────────

const TICKER = `<!-- CSP_SECTION_TICKER_START -->
<section id="csp-ticker" style="background:#020918;border-top:1px solid rgba(0,174,254,0.2);border-bottom:1px solid rgba(0,174,254,0.15);overflow:hidden;padding:0;position:relative;z-index:10;">
<style>
#csp-ticker{font-family:'Inter',-apple-system,sans-serif;}
.csp-ticker-wrap{display:flex;align-items:center;height:44px;gap:0;}
.csp-ticker-label{flex-shrink:0;display:flex;align-items:center;gap:8px;padding:0 20px;background:linear-gradient(135deg,#00AEFE,#B71F71);font-size:0.72rem;font-weight:700;letter-spacing:0.1em;color:#fff;height:100%;white-space:nowrap;}
.csp-ticker-dot{width:7px;height:7px;border-radius:50%;background:#fff;animation:csp-blink 1.2s ease-in-out infinite;}
@keyframes csp-blink{0%,100%{opacity:1;}50%{opacity:0.2;}}
.csp-ticker-track-wrap{flex:1;overflow:hidden;height:100%;display:flex;align-items:center;}
.csp-ticker-track{display:flex;align-items:center;gap:0;white-space:nowrap;animation:csp-scroll 40s linear infinite;}
.csp-ticker-track:hover{animation-play-state:paused;}
.csp-ticker-item{display:inline-flex;align-items:center;gap:8px;padding:0 32px;color:rgba(255,255,255,0.82);font-size:0.85rem;border-right:1px solid rgba(255,255,255,0.1);}
.csp-ticker-item b{color:#00AEFE;font-weight:600;}
@keyframes csp-scroll{0%{transform:translateX(0);}100%{transform:translateX(-50%);}}
</style>
<div class="csp-ticker-wrap">
  <div class="csp-ticker-label"><span class="csp-ticker-dot"></span>LIVE</div>
  <div class="csp-ticker-track-wrap">
    <div class="csp-ticker-track">
      <span class="csp-ticker-item"><b>NEW:</b> Essay published on Global Governance &amp; Democracy</span>
      <span class="csp-ticker-item"><b>UPCOMING:</b> Professor Amara Diallo joins Conspodium next month</span>
      <span class="csp-ticker-item"><b>NOW OPEN:</b> Registration for the next diaspora symposium</span>
      <span class="csp-ticker-item"><b>PUBLISHED:</b> New podcast episode — African Futures in a Digital World</span>
      <span class="csp-ticker-item"><b>FEATURE:</b> Exclusive interview with Dr. Ngozi Eze on biotechnology</span>
      <span class="csp-ticker-item"><b>POLL:</b> Vote on the next Conspodium topic — closes Sunday</span>
      <span class="csp-ticker-item"><b>COMMUNITY:</b> 2,000+ readers now part of the Conspodium circle</span>
      <span class="csp-ticker-item"><b>NEW:</b> Essay published on Global Governance &amp; Democracy</span>
      <span class="csp-ticker-item"><b>UPCOMING:</b> Professor Amara Diallo joins Conspodium next month</span>
      <span class="csp-ticker-item"><b>NOW OPEN:</b> Registration for the next diaspora symposium</span>
      <span class="csp-ticker-item"><b>PUBLISHED:</b> New podcast episode — African Futures in a Digital World</span>
      <span class="csp-ticker-item"><b>FEATURE:</b> Exclusive interview with Dr. Ngozi Eze on biotechnology</span>
      <span class="csp-ticker-item"><b>POLL:</b> Vote on the next Conspodium topic — closes Sunday</span>
      <span class="csp-ticker-item"><b>COMMUNITY:</b> 2,000+ readers now part of the Conspodium circle</span>
    </div>
  </div>
</div>
</section>
<!-- CSP_SECTION_TICKER_END -->`;

const SCHOLARS = `<!-- CSP_SECTION_SCHOLARS_START -->
<section id="csp-scholars" style="background:#050D1A;padding:90px 0;">
<style>
#csp-scholars{font-family:'Inter',-apple-system,sans-serif;color:#fff;}
.csp-scholars-inner{max-width:1200px;margin:0 auto;padding:0 40px;}
.csp-section-label{font-size:0.75rem;font-weight:700;letter-spacing:0.15em;color:#00AEFE;text-transform:uppercase;margin-bottom:12px;}
.csp-section-title{font-family:'Merriweather',serif;font-size:clamp(1.8rem,3vw,2.6rem);font-weight:400;color:#fff;line-height:1.25;margin:0 0 12px;}
.csp-section-title span{background:linear-gradient(135deg,#00AEFE,#B71F71);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.csp-section-sub{color:rgba(255,255,255,0.55);font-size:0.95rem;margin:0 0 56px;max-width:560px;}
.csp-scholars-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:28px;}
@media(max-width:900px){.csp-scholars-grid{grid-template-columns:1fr;max-width:460px;margin:0 auto;}}
.csp-scholar-card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.09);border-radius:18px;padding:36px 28px;text-align:center;transition:transform 0.25s ease,border-color 0.25s ease,background 0.25s ease;position:relative;overflow:hidden;}
.csp-scholar-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#00AEFE,#B71F71);opacity:0;transition:opacity 0.25s;}
.csp-scholar-card:hover{transform:translateY(-6px);border-color:rgba(0,174,254,0.3);background:rgba(255,255,255,0.07);}
.csp-scholar-card:hover::before{opacity:1;}
.csp-scholar-avatar{width:100px;height:100px;border-radius:50%;margin:0 auto 22px;border:3px solid rgba(0,174,254,0.45);overflow:hidden;display:block;background:linear-gradient(135deg,#00AEFE22,#B71F7122);box-shadow:0 0 0 6px rgba(0,174,254,0.1);}
.csp-scholar-avatar img{width:100%;height:100%;object-fit:cover;object-position:center top;display:block;border-radius:50%;}
.csp-scholar-coming{display:inline-block;font-size:0.68rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#B71F71;border:1px solid rgba(183,31,113,0.4);border-radius:20px;padding:4px 12px;margin-bottom:14px;}
.csp-scholar-name{font-family:'Merriweather',serif;font-size:1.1rem;font-weight:700;color:#fff;margin-bottom:4px;}
.csp-scholar-uni{font-size:0.8rem;color:rgba(255,255,255,0.45);margin-bottom:20px;}
.csp-scholar-topic{font-size:0.88rem;color:rgba(255,255,255,0.7);line-height:1.5;margin-bottom:24px;min-height:56px;}
.csp-scholar-btn{display:inline-flex;align-items:center;gap:8px;font-size:0.82rem;font-weight:600;color:#00AEFE;border:1px solid rgba(0,174,254,0.35);border-radius:50px;padding:9px 20px;text-decoration:none;transition:all 0.2s;}
.csp-scholar-btn:hover{background:rgba(0,174,254,0.1);border-color:#00AEFE;}
</style>
<div class="csp-scholars-inner">
  <p class="csp-section-label">Scholar Spotlight</p>
  <h2 class="csp-section-title">Meet the <span>Minds</span> Behind the Ideas</h2>
  <p class="csp-section-sub">Distinguished scholars who challenge conventional thinking and shape the intellectual future of the diaspora.</p>
  <div class="csp-scholars-grid">
    <div class="csp-scholar-card">
      <div class="csp-scholar-avatar"><img src="./wp-content/uploads/2026/08/scholar-amara-diallo.png" alt="Prof. Amara Diallo"></div>
      <span class="csp-scholar-coming">Coming Next Month</span>
      <div class="csp-scholar-name">Prof. Amara Diallo</div>
      <div class="csp-scholar-uni">London School of Economics</div>
      <div class="csp-scholar-topic">"Democracy, Digital Sovereignty &amp; the African Voice in Global Governance"</div>
      <a href="./stories/" class="csp-scholar-btn">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M8 1v14M1 8h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        Set Reminder
      </a>
    </div>
    <div class="csp-scholar-card">
      <div class="csp-scholar-avatar"><img src="./wp-content/uploads/2026/08/scholar-ngozi-eze.png" alt="Dr. Ngozi Eze"></div>
      <span class="csp-scholar-coming">Featured This Month</span>
      <div class="csp-scholar-name">Dr. Ngozi Eze</div>
      <div class="csp-scholar-uni">MIT Media Lab</div>
      <div class="csp-scholar-topic">"Biotechnology and the Future of African Health Systems — Who Controls the Science?"</div>
      <a href="./stories/" class="csp-scholar-btn">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Read Essay
      </a>
    </div>
    <div class="csp-scholar-card">
      <div class="csp-scholar-avatar"><img src="./wp-content/uploads/2026/08/scholar-kwame-osei.png" alt="Prof. Kwame Osei"></div>
      <span class="csp-scholar-coming">August 2026</span>
      <div class="csp-scholar-name">Prof. Kwame Osei</div>
      <div class="csp-scholar-uni">University of Ghana / Oxford</div>
      <div class="csp-scholar-topic">"African Intellectual Heritage and the Decolonisation of Academic Thought"</div>
      <a href="./stories/" class="csp-scholar-btn">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M8 1v14M1 8h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        Set Reminder
      </a>
    </div>
  </div>
</div>
</section>
<!-- CSP_SECTION_SCHOLARS_END -->`;

const TRENDING = `<!-- CSP_SECTION_TRENDING_START -->
<section id="csp-trending" style="background:#040c18;padding:70px 0 50px;">
<style>
#csp-trending{font-family:'Inter',-apple-system,sans-serif;color:#fff;}
.csp-trending-inner{max-width:1200px;margin:0 auto;padding:0 40px;}
.csp-trending-header{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:36px;flex-wrap:wrap;gap:16px;}
.csp-trending-tags-wrap{overflow:hidden;position:relative;}
.csp-trending-tags-wrap::after{content:'';position:absolute;right:0;top:0;bottom:0;width:80px;background:linear-gradient(to left,#040c18,transparent);pointer-events:none;}
.csp-trending-tags{display:flex;gap:14px;animation:csp-slide 25s linear infinite;width:max-content;}
.csp-trending-tags:hover{animation-play-state:paused;}
@keyframes csp-slide{0%{transform:translateX(0);}100%{transform:translateX(-50%);}}
.csp-tag{display:inline-flex;align-items:center;gap:8px;padding:10px 22px;border-radius:50px;border:1px solid rgba(0,174,254,0.25);background:rgba(0,174,254,0.06);color:rgba(255,255,255,0.8);font-size:0.88rem;white-space:nowrap;cursor:pointer;transition:all 0.2s;text-decoration:none;}
.csp-tag:hover{background:linear-gradient(135deg,rgba(0,174,254,0.18),rgba(183,31,113,0.18));border-color:rgba(0,174,254,0.5);color:#fff;transform:translateY(-2px);}
.csp-tag-num{font-size:0.7rem;color:rgba(255,255,255,0.35);font-weight:500;}
</style>
<div class="csp-trending-inner">
  <div class="csp-trending-header">
    <div>
      <p class="csp-section-label" style="font-size:0.75rem;font-weight:700;letter-spacing:0.15em;color:#00AEFE;text-transform:uppercase;margin:0 0 8px;">Trending Now</p>
      <h2 style="font-family:'Merriweather',serif;font-size:clamp(1.5rem,2.5vw,2.2rem);font-weight:400;color:#fff;margin:0;">What the Community Is <span style="background:linear-gradient(135deg,#00AEFE,#B71F71);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Talking About</span></h2>
    </div>
    <a href="./stories/" style="font-size:0.85rem;color:#00AEFE;text-decoration:none;white-space:nowrap;padding-bottom:6px;">Explore All Topics →</a>
  </div>
  <div class="csp-trending-tags-wrap">
    <div class="csp-trending-tags">
      <a href="./stories/" class="csp-tag"><span>🌍</span> Democracy in the Digital Age <span class="csp-tag-num">1,204 reads</span></a>
      <a href="./stories/" class="csp-tag"><span>🌿</span> Climate Justice <span class="csp-tag-num">987 reads</span></a>
      <a href="./stories/" class="csp-tag"><span>🤖</span> AI &amp; Ethics <span class="csp-tag-num">1,891 reads</span></a>
      <a href="./stories/" class="csp-tag"><span>📚</span> African Intellectual Heritage <span class="csp-tag-num">743 reads</span></a>
      <a href="./stories/" class="csp-tag"><span>🎓</span> Future of Higher Education <span class="csp-tag-num">612 reads</span></a>
      <a href="./stories/" class="csp-tag"><span>🧬</span> Biotechnology <span class="csp-tag-num">834 reads</span></a>
      <a href="./stories/" class="csp-tag"><span>✊🏾</span> Pan-Africanism <span class="csp-tag-num">1,102 reads</span></a>
      <a href="./stories/" class="csp-tag"><span>💡</span> Diaspora Innovation <span class="csp-tag-num">559 reads</span></a>
      <a href="./stories/" class="csp-tag"><span>🌍</span> Democracy in the Digital Age <span class="csp-tag-num">1,204 reads</span></a>
      <a href="./stories/" class="csp-tag"><span>🌿</span> Climate Justice <span class="csp-tag-num">987 reads</span></a>
      <a href="./stories/" class="csp-tag"><span>🤖</span> AI &amp; Ethics <span class="csp-tag-num">1,891 reads</span></a>
      <a href="./stories/" class="csp-tag"><span>📚</span> African Intellectual Heritage <span class="csp-tag-num">743 reads</span></a>
      <a href="./stories/" class="csp-tag"><span>🎓</span> Future of Higher Education <span class="csp-tag-num">612 reads</span></a>
      <a href="./stories/" class="csp-tag"><span>🧬</span> Biotechnology <span class="csp-tag-num">834 reads</span></a>
      <a href="./stories/" class="csp-tag"><span>✊🏾</span> Pan-Africanism <span class="csp-tag-num">1,102 reads</span></a>
      <a href="./stories/" class="csp-tag"><span>💡</span> Diaspora Innovation <span class="csp-tag-num">559 reads</span></a>
    </div>
  </div>
</div>
</section>
<!-- CSP_SECTION_TRENDING_END -->`;

const QUOTE = `<!-- CSP_SECTION_QUOTE_START -->
<section id="csp-quote" style="background:linear-gradient(135deg,#050D1A 0%,#0a1628 100%);padding:90px 0;position:relative;overflow:hidden;">
<style>
#csp-quote{font-family:'Inter',-apple-system,sans-serif;}
#csp-quote::before{content:'"';position:absolute;top:-40px;left:5%;font-size:280px;font-family:'Merriweather',serif;color:rgba(0,174,254,0.05);line-height:1;pointer-events:none;user-select:none;}
.csp-quote-inner{max-width:860px;margin:0 auto;padding:0 40px;text-align:center;position:relative;}
.csp-quote-label{font-size:0.72rem;font-weight:700;letter-spacing:0.18em;color:#00AEFE;text-transform:uppercase;margin-bottom:36px;display:flex;align-items:center;justify-content:center;gap:12px;}
.csp-quote-label::before,.csp-quote-label::after{content:'';flex:1;max-width:60px;height:1px;background:linear-gradient(90deg,transparent,#00AEFE);}
.csp-quote-label::after{background:linear-gradient(90deg,#00AEFE,transparent);}
.csp-quotes-container{position:relative;}
.csp-quote-text{font-family:'Merriweather',serif;font-size:clamp(1.3rem,2.5vw,1.9rem);font-weight:400;font-style:italic;color:rgba(255,255,255,0.92);line-height:1.65;margin:0 0 32px;}
.csp-quote-author{display:flex;align-items:center;justify-content:center;gap:14px;}
.csp-quote-author-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#00AEFE,#B71F71);display:flex;align-items:center;justify-content:center;font-size:0.85rem;font-weight:700;color:#fff;}
.csp-quote-author-info{text-align:left;}
.csp-quote-author-name{font-weight:600;color:#fff;font-size:0.9rem;}
.csp-quote-author-role{color:rgba(255,255,255,0.45);font-size:0.78rem;}
.csp-quote-dots{display:flex;justify-content:center;gap:8px;margin-top:40px;}
.csp-quote-dot{width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,0.2);cursor:pointer;transition:all 0.2s;}
.csp-quote-dot.active{background:#00AEFE;width:22px;border-radius:4px;}
</style>
<div class="csp-quote-inner">
  <div class="csp-quote-label">Quote of the Week</div>
  <div class="csp-quotes-container">
    <div class="csp-quote-slide" data-idx="0">
      <p class="csp-quote-text">"Education is the passport to the future, for tomorrow belongs to those who prepare for it today."</p>
      <div class="csp-quote-author">
        <div class="csp-quote-author-avatar">MX</div>
        <div class="csp-quote-author-info">
          <div class="csp-quote-author-name">Malcolm X</div>
          <div class="csp-quote-author-role">Civil Rights Leader &amp; Intellectual</div>
        </div>
      </div>
    </div>
    <div class="csp-quote-slide" data-idx="1" style="display:none;">
      <p class="csp-quote-text">"Until the lion learns to write, every story will glorify the hunter."</p>
      <div class="csp-quote-author">
        <div class="csp-quote-author-avatar">AC</div>
        <div class="csp-quote-author-info">
          <div class="csp-quote-author-name">Chinua Achebe</div>
          <div class="csp-quote-author-role">Author &amp; Literary Philosopher</div>
        </div>
      </div>
    </div>
    <div class="csp-quote-slide" data-idx="2" style="display:none;">
      <p class="csp-quote-text">"The most courageous act is still to think for yourself. Aloud."</p>
      <div class="csp-quote-author">
        <div class="csp-quote-author-avatar">CC</div>
        <div class="csp-quote-author-info">
          <div class="csp-quote-author-name">Coco Chanel</div>
          <div class="csp-quote-author-role">Visionary &amp; Pioneer</div>
        </div>
      </div>
    </div>
    <div class="csp-quote-dots">
      <div class="csp-quote-dot active" data-to="0"></div>
      <div class="csp-quote-dot" data-to="1"></div>
      <div class="csp-quote-dot" data-to="2"></div>
    </div>
  </div>
</div>
<script>
(function(){
  var slides=document.querySelectorAll('.csp-quote-slide');
  var dots=document.querySelectorAll('.csp-quote-dot');
  var cur=0;
  function show(n){
    slides[cur].style.display='none';
    dots[cur].classList.remove('active');
    cur=n;
    slides[cur].style.display='block';
    dots[cur].classList.add('active');
  }
  dots.forEach(function(d){d.addEventListener('click',function(){show(parseInt(d.dataset.to));});});
  setInterval(function(){show((cur+1)%slides.length);},6000);
})();
</script>
</section>
<!-- CSP_SECTION_QUOTE_END -->`;

const TABS = `<!-- CSP_SECTION_TABS_START -->
<section id="csp-tabs" style="background:#050D1A;padding:90px 0;">
<style>
#csp-tabs{font-family:'Inter',-apple-system,sans-serif;color:#fff;}
.csp-tabs-inner{max-width:1100px;margin:0 auto;padding:0 40px;}
.csp-tab-nav{display:flex;gap:0;border-bottom:1px solid rgba(255,255,255,0.1);margin-bottom:48px;overflow-x:auto;}
.csp-tab-btn{flex-shrink:0;padding:14px 36px;font-size:0.9rem;font-weight:600;color:rgba(255,255,255,0.45);background:none;border:none;border-bottom:2px solid transparent;cursor:pointer;transition:all 0.2s;font-family:'Inter',sans-serif;letter-spacing:0.02em;}
.csp-tab-btn:hover{color:rgba(255,255,255,0.8);}
.csp-tab-btn.active{color:#00AEFE;border-bottom-color:#00AEFE;}
.csp-tab-panel{display:none;}
.csp-tab-panel.active{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
@media(max-width:700px){.csp-tab-panel.active{grid-template-columns:1fr;}}
.csp-tab-item{display:flex;align-items:flex-start;gap:14px;padding:18px 20px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.07);border-radius:12px;transition:border-color 0.2s,background 0.2s;cursor:default;}
.csp-tab-item:hover{border-color:rgba(0,174,254,0.25);background:rgba(0,174,254,0.05);}
.csp-tab-icon{flex-shrink:0;width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,rgba(0,174,254,0.15),rgba(183,31,113,0.1));display:flex;align-items:center;justify-content:center;font-size:1.1rem;}
.csp-tab-item-title{font-weight:600;font-size:0.9rem;color:#fff;margin-bottom:4px;}
.csp-tab-item-sub{font-size:0.78rem;color:rgba(255,255,255,0.45);}
</style>
<div class="csp-tabs-inner">
  <p class="csp-section-label" style="font-size:0.75rem;font-weight:700;letter-spacing:0.15em;color:#00AEFE;text-transform:uppercase;margin:0 0 12px;">The Conspodium Timeline</p>
  <h2 style="font-family:'Merriweather',serif;font-size:clamp(1.6rem,3vw,2.4rem);font-weight:400;color:#fff;margin:0 0 40px;">Past • Present • <span style="background:linear-gradient(135deg,#00AEFE,#B71F71);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Upcoming</span></h2>
  <div class="csp-tab-nav">
    <button class="csp-tab-btn active" data-tab="past">Past</button>
    <button class="csp-tab-btn" data-tab="present">Present</button>
    <button class="csp-tab-btn" data-tab="upcoming">Upcoming</button>
  </div>
  <div id="csp-tab-past" class="csp-tab-panel active">
    <div class="csp-tab-item"><div class="csp-tab-icon">📰</div><div><div class="csp-tab-item-title">African Tech Diaspora — Issue 01</div><div class="csp-tab-item-sub">Most-read essay of 2025 · 8,400 views</div></div></div>
    <div class="csp-tab-item"><div class="csp-tab-icon">🎙️</div><div><div class="csp-tab-item-title">Interview: Dr. Amaka Obi on Climate Policy</div><div class="csp-tab-item-sub">Top interview · 5,200 listens</div></div></div>
    <div class="csp-tab-item"><div class="csp-tab-icon">🏛️</div><div><div class="csp-tab-item-title">Lagos Symposium 2024 Highlights</div><div class="csp-tab-item-sub">Previous symposium · 320 attendees</div></div></div>
    <div class="csp-tab-item"><div class="csp-tab-icon">✍️</div><div><div class="csp-tab-item-title">The Stolen Century — Historical Essay</div><div class="csp-tab-item-sub">Popular essay · 11,000 reads</div></div></div>
  </div>
  <div id="csp-tab-present" class="csp-tab-panel">
    <div class="csp-tab-item"><div class="csp-tab-icon">🔥</div><div><div class="csp-tab-item-title">AI Ethics in African Contexts</div><div class="csp-tab-item-sub">Trending essay this week</div></div></div>
    <div class="csp-tab-item"><div class="csp-tab-icon">⚖️</div><div><div class="csp-tab-item-title">Democracy &amp; Digital Sovereignty — Debate</div><div class="csp-tab-item-sub">Active debate · 42 contributors</div></div></div>
    <div class="csp-tab-item"><div class="csp-tab-icon">📖</div><div><div class="csp-tab-item-title">Conspodium Vol. 3 — Now Live</div><div class="csp-tab-item-sub">Latest publication · Download now</div></div></div>
    <div class="csp-tab-item"><div class="csp-tab-icon">🎧</div><div><div class="csp-tab-item-title">New Podcast: Futures of the Diaspora</div><div class="csp-tab-item-sub">Episodes 1–4 available now</div></div></div>
  </div>
  <div id="csp-tab-upcoming" class="csp-tab-panel">
    <div class="csp-tab-item"><div class="csp-tab-icon">👤</div><div><div class="csp-tab-item-title">Prof. Amara Diallo — September 2026</div><div class="csp-tab-item-sub">Upcoming scholar · Set reminder</div></div></div>
    <div class="csp-tab-item"><div class="csp-tab-icon">💬</div><div><div class="csp-tab-item-title">Panel: Future of African Universities</div><div class="csp-tab-item-sub">Scheduled discussion · Oct 2026</div></div></div>
    <div class="csp-tab-item"><div class="csp-tab-icon">📝</div><div><div class="csp-tab-item-title">Essay: The Post-Colonial Digital Mind</div><div class="csp-tab-item-sub">Upcoming release · November 2026</div></div></div>
    <div class="csp-tab-item"><div class="csp-tab-icon">⏱️</div><div><div class="csp-tab-item-title">Conspodium Live Discussion — Oct 15</div><div class="csp-tab-item-sub">Register now — limited seats</div></div></div>
  </div>
</div>
<script>
(function(){
  var btns=document.querySelectorAll('.csp-tab-btn');
  var panels=document.querySelectorAll('.csp-tab-panel');
  btns.forEach(function(btn){
    btn.addEventListener('click',function(){
      btns.forEach(function(b){b.classList.remove('active');});
      panels.forEach(function(p){p.classList.remove('active');});
      btn.classList.add('active');
      document.getElementById('csp-tab-'+btn.dataset.tab).classList.add('active');
    });
  });
})();
</script>
</section>
<!-- CSP_SECTION_TABS_END -->`;

const COUNTDOWN = `<!-- CSP_SECTION_COUNTDOWN_START -->
<section id="csp-countdown" style="background:linear-gradient(135deg,#020918 0%,#08142a 100%);padding:90px 0;position:relative;overflow:hidden;">
<style>
#csp-countdown{font-family:'Inter',-apple-system,sans-serif;color:#fff;text-align:center;}
#csp-countdown::before{content:'';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:600px;height:600px;background:radial-gradient(circle,rgba(0,174,254,0.05) 0%,transparent 70%);pointer-events:none;}
.csp-countdown-inner{max-width:800px;margin:0 auto;padding:0 40px;position:relative;}
.csp-countdown-eyebrow{font-size:0.72rem;font-weight:700;letter-spacing:0.18em;color:#00AEFE;text-transform:uppercase;margin-bottom:20px;}
.csp-countdown-title{font-family:'Merriweather',serif;font-size:clamp(1.6rem,3vw,2.4rem);font-weight:400;margin:0 0 8px;}
.csp-countdown-scholar{font-size:0.95rem;color:rgba(255,255,255,0.5);margin:0 0 52px;}
.csp-countdown-scholar span{color:#00AEFE;}
.csp-countdown-grid{display:flex;justify-content:center;gap:20px;flex-wrap:wrap;}
.csp-countdown-unit{background:rgba(255,255,255,0.05);border:1px solid rgba(0,174,254,0.2);border-radius:16px;padding:24px 28px;min-width:90px;}
.csp-countdown-num{font-size:clamp(2.2rem,5vw,3.4rem);font-weight:700;font-variant-numeric:tabular-nums;line-height:1;background:linear-gradient(135deg,#fff,rgba(255,255,255,0.7));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.csp-countdown-lbl{font-size:0.72rem;font-weight:600;letter-spacing:0.1em;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-top:8px;}
.csp-countdown-cta{margin-top:48px;}
.csp-countdown-cta a{display:inline-flex;align-items:center;gap:10px;background:linear-gradient(135deg,#00AEFE,#B71F71);color:#fff;text-decoration:none;font-weight:600;padding:14px 32px;border-radius:50px;font-size:0.9rem;transition:opacity 0.2s,transform 0.2s;}
.csp-countdown-cta a:hover{opacity:0.88;transform:translateY(-2px);}
</style>
<div class="csp-countdown-inner">
  <p class="csp-countdown-eyebrow">⏱ Next Live Discussion</p>
  <h2 class="csp-countdown-title">The Future of African Democracy</h2>
  <p class="csp-countdown-scholar">Featuring <span>Prof. Amara Diallo</span> · London School of Economics</p>
  <div class="csp-countdown-grid">
    <div class="csp-countdown-unit"><div class="csp-countdown-num" id="csp-cd-days">--</div><div class="csp-countdown-lbl">Days</div></div>
    <div class="csp-countdown-unit"><div class="csp-countdown-num" id="csp-cd-hrs">--</div><div class="csp-countdown-lbl">Hours</div></div>
    <div class="csp-countdown-unit"><div class="csp-countdown-num" id="csp-cd-min">--</div><div class="csp-countdown-lbl">Minutes</div></div>
    <div class="csp-countdown-unit"><div class="csp-countdown-num" id="csp-cd-sec">--</div><div class="csp-countdown-lbl">Seconds</div></div>
  </div>
  <div class="csp-countdown-cta">
    <a href="./contact-us/">
      Register Now
      <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </a>
  </div>
</div>
<script>
(function(){
  // Target: October 15, 2026
  var target=new Date('2026-10-15T18:00:00Z').getTime();
  function pad(n){return String(n).padStart(2,'0');}
  function tick(){
    var now=Date.now();
    var diff=target-now;
    if(diff<=0){diff=0;}
    var d=Math.floor(diff/86400000);
    var h=Math.floor((diff%86400000)/3600000);
    var m=Math.floor((diff%3600000)/60000);
    var s=Math.floor((diff%60000)/1000);
    document.getElementById('csp-cd-days').textContent=pad(d);
    document.getElementById('csp-cd-hrs').textContent=pad(h);
    document.getElementById('csp-cd-min').textContent=pad(m);
    document.getElementById('csp-cd-sec').textContent=pad(s);
  }
  tick();
  setInterval(tick,1000);
})();
</script>
</section>
<!-- CSP_SECTION_COUNTDOWN_END -->`;

const POLL = `<!-- CSP_SECTION_POLL_START -->
<section id="csp-poll" style="background:#050D1A;padding:90px 0;">
<style>
#csp-poll{font-family:'Inter',-apple-system,sans-serif;color:#fff;}
.csp-poll-inner{max-width:620px;margin:0 auto;padding:0 40px;text-align:center;}
.csp-poll-card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.09);border-radius:24px;padding:44px 40px;}
.csp-poll-q{font-family:'Merriweather',serif;font-size:clamp(1.15rem,2.5vw,1.5rem);font-weight:400;color:#fff;line-height:1.5;margin:0 0 32px;}
.csp-poll-opts{display:flex;flex-direction:column;gap:12px;margin-bottom:28px;text-align:left;}
.csp-poll-opt{display:flex;align-items:center;gap:14px;padding:14px 20px;border:1px solid rgba(255,255,255,0.1);border-radius:12px;cursor:pointer;transition:all 0.2s;user-select:none;}
.csp-poll-opt:hover{border-color:rgba(0,174,254,0.4);background:rgba(0,174,254,0.06);}
.csp-poll-opt.selected{border-color:#00AEFE;background:rgba(0,174,254,0.1);}
.csp-poll-opt input[type=radio]{accent-color:#00AEFE;width:18px;height:18px;flex-shrink:0;}
.csp-poll-opt-label{flex:1;font-size:0.9rem;color:rgba(255,255,255,0.85);}
.csp-poll-bar-wrap{display:none;margin-top:4px;height:5px;background:rgba(255,255,255,0.08);border-radius:3px;overflow:hidden;}
.csp-poll-bar{height:100%;background:linear-gradient(90deg,#00AEFE,#B71F71);border-radius:3px;width:0%;transition:width 0.8s cubic-bezier(0.4,0,0.2,1);}
.csp-poll-pct{font-size:0.75rem;color:rgba(255,255,255,0.4);margin-top:4px;display:none;}
.csp-poll-vote-btn{display:inline-flex;align-items:center;gap:10px;background:linear-gradient(135deg,#00AEFE,#B71F71);color:#fff;font-weight:600;font-size:0.9rem;padding:13px 30px;border-radius:50px;border:none;cursor:pointer;transition:opacity 0.2s,transform 0.2s;font-family:'Inter',sans-serif;}
.csp-poll-vote-btn:hover{opacity:0.88;transform:translateY(-2px);}
.csp-poll-vote-btn:disabled{opacity:0.4;cursor:not-allowed;transform:none;}
.csp-poll-thanks{display:none;font-size:0.88rem;color:rgba(255,255,255,0.5);margin-top:16px;}
</style>
<div class="csp-poll-inner">
  <p class="csp-section-label" style="font-size:0.75rem;font-weight:700;letter-spacing:0.15em;color:#00AEFE;text-transform:uppercase;margin:0 0 12px;">Weekly Poll</p>
  <h2 style="font-family:'Merriweather',serif;font-size:clamp(1.5rem,2.5vw,2rem);font-weight:400;color:#fff;margin:0 0 40px;">Which topic should Conspodium <span style="background:linear-gradient(135deg,#00AEFE,#B71F71);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">explore next?</span></h2>
  <div class="csp-poll-card">
    <p class="csp-poll-q">What is the most pressing issue facing the African diaspora today?</p>
    <div class="csp-poll-opts" id="csp-poll-opts">
      <label class="csp-poll-opt"><input type="radio" name="csp-poll" value="0"> <span class="csp-poll-opt-label">AI Ethics &amp; African Data Sovereignty</span></label>
      <label class="csp-poll-opt"><input type="radio" name="csp-poll" value="1"> <span class="csp-poll-opt-label">Global Political Representation</span></label>
      <label class="csp-poll-opt"><input type="radio" name="csp-poll" value="2"> <span class="csp-poll-opt-label">Climate Justice &amp; African Communities</span></label>
      <label class="csp-poll-opt"><input type="radio" name="csp-poll" value="3"> <span class="csp-poll-opt-label">Philosophy &amp; Decolonising Education</span></label>
    </div>
    <button class="csp-poll-vote-btn" id="csp-poll-btn" disabled>Vote Now ›</button>
    <p class="csp-poll-thanks" id="csp-poll-thanks">Thank you for voting! Results update weekly.</p>
  </div>
</div>
<script>
(function(){
  var opts=document.querySelectorAll('.csp-poll-opt');
  var btn=document.getElementById('csp-poll-btn');
  var thanks=document.getElementById('csp-poll-thanks');
  // Demo vote counts (will look realistic)
  var votes=[312,198,271,145];
  var total=votes.reduce(function(a,b){return a+b;},0);
  var voted=localStorage.getItem('csp-poll-v1');

  function showResults(chosen){
    opts.forEach(function(opt,i){
      var radio=opt.querySelector('input');
      radio.disabled=true;
      var bar=opt.querySelector('.csp-poll-bar-wrap');
      var barInner=opt.querySelector('.csp-poll-bar');
      var pct=opt.querySelector('.csp-poll-pct');
      if(!bar){
        bar=document.createElement('div');bar.className='csp-poll-bar-wrap';
        barInner=document.createElement('div');barInner.className='csp-poll-bar';
        bar.appendChild(barInner);opt.appendChild(bar);
        pct=document.createElement('div');pct.className='csp-poll-pct';opt.appendChild(pct);
      }
      bar.style.display='block';
      pct.style.display='block';
      var p=Math.round(votes[i]/total*100);
      pct.textContent=p+'%';
      setTimeout(function(){barInner.style.width=p+'%';},50);
      if(parseInt(opt.querySelector('input').value)===chosen){opt.style.borderColor='#00AEFE';}
    });
    btn.disabled=true;
    thanks.style.display='block';
  }

  if(voted!==null){showResults(parseInt(voted));}

  opts.forEach(function(opt){
    opt.addEventListener('click',function(){
      opts.forEach(function(o){o.classList.remove('selected');});
      opt.classList.add('selected');
      btn.disabled=false;
    });
  });

  btn.addEventListener('click',function(){
    var sel=document.querySelector('.csp-poll-opt.selected input');
    if(!sel)return;
    var v=parseInt(sel.value);
    votes[v]+=1;total+=1;
    localStorage.setItem('csp-poll-v1',v);
    showResults(v);
  });
})();
</script>
</section>
<!-- CSP_SECTION_POLL_END -->`;

const STATS = `<!-- CSP_SECTION_STATS_START -->
<section id="csp-stats" style="background:linear-gradient(135deg,rgba(0,174,254,0.06) 0%,rgba(183,31,113,0.06) 100%);border-top:1px solid rgba(255,255,255,0.07);border-bottom:1px solid rgba(255,255,255,0.07);padding:70px 0;">
<style>
#csp-stats{font-family:'Inter',-apple-system,sans-serif;color:#fff;}
.csp-stats-inner{max-width:1100px;margin:0 auto;padding:0 40px;}
.csp-stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:32px;text-align:center;}
@media(max-width:700px){.csp-stats-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:400px){.csp-stats-grid{grid-template-columns:1fr;}}
.csp-stat-item{padding:24px 16px;}
.csp-stat-num{font-size:clamp(2.2rem,4vw,3rem);font-weight:700;background:linear-gradient(135deg,#00AEFE,#B71F71);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1;margin-bottom:10px;}
.csp-stat-label{font-size:0.88rem;color:rgba(255,255,255,0.5);font-weight:500;letter-spacing:0.02em;}
</style>
<div class="csp-stats-inner">
  <div class="csp-stats-grid">
    <div class="csp-stat-item">
      <div class="csp-stat-num" data-target="500" data-suffix="+">0+</div>
      <div class="csp-stat-label">Published Essays</div>
    </div>
    <div class="csp-stat-item">
      <div class="csp-stat-num" data-target="80" data-suffix="">0</div>
      <div class="csp-stat-label">Distinguished Contributors</div>
    </div>
    <div class="csp-stat-item">
      <div class="csp-stat-num" data-target="70" data-suffix="">0</div>
      <div class="csp-stat-label">Countries Represented</div>
    </div>
    <div class="csp-stat-item">
      <div class="csp-stat-num" data-target="2" data-suffix="M+">0</div>
      <div class="csp-stat-label">Total Views</div>
    </div>
  </div>
</div>
<script>
(function(){
  function countUp(el){
    var target=parseInt(el.dataset.target);
    var suffix=el.dataset.suffix||'';
    var duration=1800;
    var step=duration/60;
    var current=0;
    var inc=target/60;
    var timer=setInterval(function(){
      current=Math.min(current+inc,target);
      el.textContent=Math.round(current)+suffix;
      if(current>=target)clearInterval(timer);
    },step);
  }
  var nums=document.querySelectorAll('.csp-stat-num[data-target]');
  var observed=false;
  var obs=new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if(e.isIntersecting&&!observed){
        observed=true;
        nums.forEach(countUp);
      }
    });
  },{threshold:0.3});
  if(nums.length)obs.observe(nums[0]);
})();
</script>
</section>
<!-- CSP_SECTION_STATS_END -->`;

// ─── Injection logic ────────────────────────────────────────────────────────

function stripSection(html, name) {
  const start = `<!-- CSP_SECTION_${name}_START -->`;
  const end   = `<!-- CSP_SECTION_${name}_END -->`;
  const si = html.indexOf(start);
  const ei = html.indexOf(end);
  if (si !== -1 && ei !== -1) {
    html = html.slice(0, si) + html.slice(ei + end.length);
  }
  return html;
}

function injectBefore(html, anchor, block) {
  // Find the FIRST occurrence of the anchor Elementor div
  const marker = `elementor-element-${anchor}`;
  // Go back to find the opening <div or \t\t<div before it
  const idx = html.indexOf(marker);
  if (idx === -1) {
    console.warn(`⚠  Anchor not found: ${anchor}`);
    return html;
  }
  // Walk backwards to the start of the tag
  let start = idx;
  while (start > 0 && html[start] !== '<') start--;
  return html.slice(0, start) + block + '\n' + html.slice(start);
}

// ─── Main ───────────────────────────────────────────────────────────────────

let html = await readFile(FILE, 'utf8');

// Strip any previously injected blocks
const SECTIONS = ['TICKER','SCHOLARS','TRENDING','QUOTE','TABS','COUNTDOWN','POLL','STATS'];
for (const s of SECTIONS) html = stripSection(html, s);

// Inject in order:
// 1. News Ticker → before About (4d857de7)  — first occurrence
html = injectBefore(html, '4d857de7', TICKER);

// 2. Scholar Spotlight → before Categories (6c7c1b5d)
html = injectBefore(html, '6c7c1b5d', SCHOLARS);

// 3–7. Trending / Quote / Tabs / Countdown / Poll → before Contributor (4f7bfe8e)
//   Inject in reverse order so each ends up in correct position
html = injectBefore(html, '4f7bfe8e', POLL);
html = injectBefore(html, '4f7bfe8e', COUNTDOWN);
html = injectBefore(html, '4f7bfe8e', TABS);
html = injectBefore(html, '4f7bfe8e', QUOTE);
html = injectBefore(html, '4f7bfe8e', TRENDING);

// 8. Social Proof → before How It Works (27d63d2d)
html = injectBefore(html, '27d63d2d', STATS);

await writeFile(FILE, html, 'utf8');
console.log('✅ All 8 sections injected into output2/index.html');
console.log('   Hard-refresh (Cmd+Shift+R) at http://localhost:8080');
