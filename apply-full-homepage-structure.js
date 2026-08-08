/**
 * apply-full-homepage-structure.js
 *
 * Implements all 19 suggested homepage sections into output/index.html,
 * seamlessly integrated with the original Conspodium Elementor styles and rebuilt header.
 */

import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const INDEX_PATH = fileURLToPath(new URL('./output/index.html', import.meta.url));

const STYLES = `
<style id="csp-full-structure-styles">
  :root {
    --csp-bg: #0a0a12;
    --csp-card-bg: rgba(255, 255, 255, 0.035);
    --csp-card-border: rgba(255, 255, 255, 0.08);
    --csp-accent-cyan: #00c9e0;
    --csp-accent-purple: #8b5cf6;
    --csp-text-main: #f3f4f6;
    --csp-text-muted: #9ca3af;
    --csp-font: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  }

  .csp-sec {
    padding: 64px 24px;
    max-width: 1240px;
    margin: 0 auto;
    font-family: var(--csp-font);
    color: var(--csp-text-main);
  }

  .csp-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 50px;
    font-size: 0.8125rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    background: rgba(0, 201, 224, 0.1);
    color: var(--csp-accent-cyan);
    border: 1px solid rgba(0, 201, 224, 0.25);
    margin-bottom: 12px;
  }

  .csp-title {
    font-size: 2.25rem;
    font-weight: 800;
    line-height: 1.25;
    letter-spacing: -0.02em;
    background: linear-gradient(135deg, #ffffff 0%, #d1d5db 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 8px;
  }

  .csp-sub {
    font-size: 1rem;
    color: var(--csp-text-muted);
    margin-bottom: 36px;
    max-width: 600px;
  }

  /* 1. Ticker */
  .csp-ticker-bar {
    background: rgba(15, 15, 26, 0.96);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding: 10px 0;
    display: flex;
    align-items: center;
    overflow: hidden;
    white-space: nowrap;
    position: relative;
    z-index: 100;
    font-family: var(--csp-font);
  }
  .csp-ticker-label {
    flex-shrink: 0;
    background: linear-gradient(135deg, #8b5cf6, #00c9e0);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 4px;
    margin: 0 16px 0 24px;
    text-transform: uppercase;
  }
  .csp-ticker-content { display: inline-flex; gap: 40px; animation: cspTicker 35s linear infinite; }
  .csp-ticker-item { display: inline-flex; align-items: center; gap: 8px; color: rgba(255, 255, 255, 0.85); font-size: 0.875rem; }
  .csp-ticker-item span.highlight { color: var(--csp-accent-cyan); font-weight: 600; }
  @keyframes cspTicker { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

  /* 2. Rotating Hero Banner */
  .csp-hero-wrapper {
    background: radial-gradient(circle at 50% 20%, rgba(139, 92, 246, 0.15) 0%, rgba(10, 10, 18, 0) 70%);
    padding: 48px 24px 64px;
  }
  .csp-hero-container {
    max-width: 1240px;
    margin: 0 auto;
  }
  .csp-hero-slide { display: none; opacity: 0; transition: opacity 0.5s ease; }
  .csp-hero-slide.active { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 40px; align-items: center; opacity: 1; }
  .csp-hero-slide h1 { font-size: 2.75rem; font-weight: 800; line-height: 1.15; color: #fff; margin: 16px 0; }
  .csp-hero-slide p { font-size: 1.0625rem; color: var(--csp-text-muted); line-height: 1.6; margin-bottom: 28px; }
  .csp-hero-card {
    background: var(--csp-card-bg);
    border: 1px solid var(--csp-card-border);
    border-radius: 20px;
    padding: 32px;
    backdrop-filter: blur(12px);
  }
  .csp-hero-dots { display: flex; justify-content: center; gap: 10px; margin-top: 28px; }
  .csp-dot { width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,0.2); cursor: pointer; transition: all 0.3s; }
  .csp-dot.active { width: 28px; border-radius: 10px; background: var(--csp-accent-cyan); }

  /* Buttons */
  .csp-btn-gradient {
    display: inline-flex; align-items: center; gap: 8px;
    background: linear-gradient(135deg, var(--csp-accent-purple), var(--csp-accent-cyan));
    color: #fff !important; text-decoration: none !important; font-weight: 600; font-size: 0.9375rem;
    padding: 12px 26px; border-radius: 50px; box-shadow: 0 4px 20px rgba(139,92,246,0.35);
  }
  .csp-btn-outline {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,0.06); color: #fff !important; text-decoration: none !important;
    font-weight: 600; font-size: 0.9375rem; padding: 12px 24px; border-radius: 50px;
    border: 1px solid rgba(255,255,255,0.15);
  }

  /* 3. Trending Topics */
  .csp-topics-flex { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 40px; }
  .csp-pill {
    padding: 10px 20px; background: var(--csp-card-bg); border: 1px solid var(--csp-card-border);
    border-radius: 50px; color: #fff; font-size: 0.875rem; text-decoration: none; transition: all 0.2s;
  }
  .csp-pill:hover { border-color: var(--csp-accent-cyan); color: var(--csp-accent-cyan); }

  /* 4. Scholar & Quote */
  .csp-grid-2 { display: grid; grid-template-columns: 1.8fr 1.2fr; gap: 28px; }
  .csp-scholar-box {
    background: var(--csp-card-bg); border: 1px solid var(--csp-card-border); border-radius: 20px;
    padding: 32px; display: grid; grid-template-columns: 120px 1fr; gap: 24px; align-items: center;
  }
  .csp-scholar-avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(0,201,224,0.4); }
  .csp-quote-box {
    background: linear-gradient(145deg, rgba(139,92,246,0.12), rgba(10,10,18,0.8));
    border: 1px solid rgba(139,92,246,0.3); border-radius: 20px; padding: 32px;
    display: flex; flex-direction: column; justify-content: space-between;
  }

  /* 5. Tabs */
  .csp-tabs-bar { display: flex; gap: 10px; margin-bottom: 28px; }
  .csp-tab {
    padding: 10px 22px; background: var(--csp-card-bg); border: 1px solid var(--csp-card-border);
    border-radius: 50px; color: var(--csp-text-muted); font-size: 0.875rem; font-weight: 600; cursor: pointer;
  }
  .csp-tab.active { background: linear-gradient(135deg, var(--csp-accent-purple), var(--csp-accent-cyan)); color: #fff; border-color: transparent; }
  .csp-tab-pane { display: none; }
  .csp-tab-pane.active { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; }

  /* 6. Countdown */
  .csp-timer-banner {
    background: linear-gradient(135deg, rgba(139,92,246,0.15), rgba(0,201,224,0.15));
    border: 1px solid rgba(0,201,224,0.3); border-radius: 20px; padding: 40px 24px; text-align: center;
  }
  .csp-timer-flex { display: flex; justify-content: center; gap: 16px; margin: 24px 0; }
  .csp-t-box { background: rgba(10,10,18,0.8); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 14px 18px; min-width: 80px; }
  .csp-t-num { font-size: 2rem; font-weight: 800; color: var(--csp-accent-cyan); line-height: 1; }
  .csp-t-lbl { font-size: 0.75rem; color: var(--csp-text-muted); text-transform: uppercase; margin-top: 4px; }

  /* 7. Poll & Corner */
  .csp-poll-opt {
    display: flex; justify-content: space-between; align-items: center;
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px; padding: 12px 16px; margin-bottom: 10px; cursor: pointer; position: relative; overflow: hidden;
  }
  .csp-poll-fill { position: absolute; top:0; left:0; bottom:0; background: rgba(0,201,224,0.15); z-index: 1; }
  .csp-poll-txt { position: relative; z-index: 2; font-size: 0.875rem; }
  .csp-poll-pct { position: relative; z-index: 2; font-weight: 700; color: var(--csp-accent-cyan); }

  /* 8. Popular Essays & Meet Minds */
  .csp-pop-list { display: flex; flex-direction: column; gap: 14px; }
  .csp-pop-item {
    display: flex; align-items: center; gap: 16px; padding: 16px;
    background: var(--csp-card-bg); border: 1px solid var(--csp-card-border); border-radius: 14px;
  }
  .csp-rank { font-size: 1.5rem; font-weight: 800; color: var(--csp-accent-cyan); width: 36px; text-align: center; }

  .csp-minds-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; }
  .csp-mind-card {
    background: var(--csp-card-bg); border: 1px solid var(--csp-card-border); border-radius: 16px;
    padding: 20px; text-align: center;
  }
  .csp-mind-avatar { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; margin: 0 auto 12px; }

  /* 9. Metrics & Testimonials */
  .csp-metrics-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; text-align: center; }
  .csp-m-box { background: var(--csp-card-bg); border: 1px solid var(--csp-card-border); border-radius: 16px; padding: 24px 16px; }
  .csp-m-num { font-size: 2.25rem; font-weight: 800; background: linear-gradient(135deg, var(--csp-accent-cyan), var(--csp-accent-purple)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

  .csp-testi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; }
  .csp-testi-card { background: var(--csp-card-bg); border: 1px solid var(--csp-card-border); border-radius: 16px; padding: 24px; }

  /* 10. Newsletter & Final CTA */
  .csp-news-box {
    background: radial-gradient(circle at top right, rgba(0,201,224,0.15), rgba(10,10,18,0.95));
    border: 1px solid rgba(0,201,224,0.25); border-radius: 20px; padding: 48px 28px; text-align: center; max-width: 800px; margin: 0 auto;
  }

  @media (max-width: 960px) {
    .csp-hero-slide.active { grid-template-columns: 1fr; }
    .csp-grid-2 { grid-template-columns: 1fr; }
    .csp-metrics-4 { grid-template-columns: repeat(2, 1fr); }
    .csp-scholar-box { grid-template-columns: 1fr; text-align: center; }
    .csp-scholar-avatar { margin: 0 auto; }
  }
</style>
`;

const ALL_19_SECTIONS_HTML = `
<!-- 1. Live News Ticker -->
<div class="csp-ticker-bar">
  <div class="csp-ticker-label">Live News</div>
  <div class="csp-ticker-content">
    <div class="csp-ticker-item">NEW: <span class="highlight">Essay published on Global Governance</span></div>
    <div class="csp-ticker-item">• Professor Michael Brown joins Conspodium next week</div>
    <div class="csp-ticker-item">• <span class="highlight">Registration now open for Annual Symposium</span></div>
    <div class="csp-ticker-item">• New Podcast Episode Released Today</div>
    <div class="csp-ticker-item">NEW: <span class="highlight">Essay published on Global Governance</span></div>
  </div>
</div>

<!-- 2. Dynamic Rotating Hero Banner -->
<div class="csp-hero-wrapper">
  <div class="csp-hero-container">
    <!-- Slide 1 -->
    <div class="csp-hero-slide active" data-slide="0">
      <div>
        <span class="csp-badge">✨ Featured Essay</span>
        <h1>This Week's Featured Essay</h1>
        <p>A New Perspective on Global Leadership: Exploring how emerging diaspora voices and modern intellectual frameworks shape international governance.</p>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
          <a href="./stories/index.html" class="csp-btn-gradient">Read Featured Essay →</a>
          <a href="./submit-story/index.html" class="csp-btn-outline">Submit Your Story</a>
        </div>
      </div>
      <div class="csp-hero-card">
        <span class="csp-badge">Scholar Focus</span>
        <h3 style="color:#fff; font-size:1.25rem; font-weight:700; margin:10px 0 6px;">Prof. Samuel Okafor</h3>
        <p style="color:var(--csp-text-muted); font-size:0.875rem; line-height:1.5;">Chair of Global Affairs, Oxford Institute. Author of 12 treatises on Pan-African governance.</p>
      </div>
    </div>

    <!-- Slide 2 -->
    <div class="csp-hero-slide" data-slide="1">
      <div>
        <span class="csp-badge">Coming Soon</span>
        <h1>A New Perspective on Global Leadership</h1>
        <p>Exclusive symposium preview featuring international scholars on the future of higher education and technological sovereignty.</p>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
          <a href="./stories/index.html" class="csp-btn-gradient">View Preview →</a>
          <a href="./contact-us/index.html" class="csp-btn-outline">Register Interest</a>
        </div>
      </div>
      <div class="csp-hero-card">
        <span class="csp-badge">Featured Panelist</span>
        <h3 style="color:#fff; font-size:1.25rem; font-weight:700; margin:10px 0 6px;">Dr. Amina Vance</h3>
        <p style="color:var(--csp-text-muted); font-size:0.875rem; line-height:1.5;">Director of AI Ethics Lab, Stockholm. Live webinar August 2026.</p>
      </div>
    </div>

    <!-- Slide 3 -->
    <div class="csp-hero-slide" data-slide="2">
      <div>
        <span class="csp-badge">Exclusive Interview</span>
        <h1>Exclusive Interview with Prof. Jane Doe</h1>
        <p>Artificial Intelligence &amp; the Future of Human Knowledge: In conversation with Oxford's leading AI ethicist.</p>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
          <a href="./stories/index.html" class="csp-btn-gradient">Watch Interview ▶</a>
          <a href="./about-us/index.html" class="csp-btn-outline">About Conspodium</a>
        </div>
      </div>
      <div class="csp-hero-card">
        <span class="csp-badge">Interview Spotlight</span>
        <h3 style="color:#fff; font-size:1.25rem; font-weight:700; margin:10px 0 6px;">Prof. Jane Doe</h3>
        <p style="color:var(--csp-text-muted); font-size:0.875rem; line-height:1.5;">"Epistemology in the Age of Autonomous Systems"</p>
      </div>
    </div>

    <div class="csp-hero-dots">
      <div class="csp-dot active" onclick="cspSlideTo(0)"></div>
      <div class="csp-dot" onclick="cspSlideTo(1)"></div>
      <div class="csp-dot" onclick="cspSlideTo(2)"></div>
    </div>
  </div>
</div>

<!-- 5. Trending Topics -->
<div class="csp-sec">
  <div style="text-align:center; margin-bottom:24px;">
    <span class="csp-badge">Exploration Topics</span>
    <h2 class="csp-title">Trending Topics</h2>
  </div>
  <div class="csp-topics-flex" style="justify-center;">
    <a href="./stories/index.html" class="csp-pill">🏛 Democracy in the Digital Age</a>
    <a href="./stories/index.html" class="csp-pill">🌱 Climate Justice</a>
    <a href="./stories/index.html" class="csp-pill">🤖 Artificial Intelligence &amp; Ethics</a>
    <a href="./stories/index.html" class="csp-pill">🌍 African Intellectual Heritage</a>
    <a href="./stories/index.html" class="csp-pill">🎓 Future of Higher Education</a>
    <a href="./stories/index.html" class="csp-pill">🧬 Biotechnology</a>
  </div>
</div>

<!-- 4. Scholar Spotlight & 5. Quote of the Week -->
<div class="csp-sec">
  <div class="csp-grid-2">
    <!-- Scholar Spotlight -->
    <div class="csp-scholar-box">
      <img class="csp-scholar-avatar" src="./wp-content/uploads/2026/01/CONSPODIUM-NEW-2-300x300.png" alt="Prof. Jane Doe">
      <div>
        <span class="csp-badge">Scholar Spotlight</span>
        <h3 style="color:#fff; font-size:1.25rem; font-weight:700;">Professor Jane Doe</h3>
        <div style="color:var(--csp-accent-cyan); font-size:0.875rem; font-weight:600; margin-bottom:8px;">University of Oxford</div>
        <p style="color:var(--csp-text-muted); font-size:0.875rem; line-height:1.5; margin-bottom:12px;">Coming Next Month: <i>Artificial Intelligence and the Future of Human Knowledge</i>.</p>
        <a href="./stories/index.html" class="csp-btn-gradient" style="padding:8px 18px; font-size:0.8125rem;">Set Reminder --</a>
      </div>
    </div>

    <!-- Quote of the Week -->
    <div class="csp-quote-box">
      <div style="font-size:2.5rem; color:var(--csp-accent-purple); line-height:1;">“</div>
      <div style="font-style:italic; color:#fff; font-size:1rem; line-height:1.5; margin:8px 0;">Education is the passport to the future, for tomorrow belongs to those who prepare for it today.</div>
      <div style="font-size:0.8125rem; font-weight:700; color:var(--csp-accent-cyan);">— Malcolm X • Quote of the Week</div>
    </div>
  </div>
</div>

<!-- 6. Past | Present | Upcoming Tabs -->
<div class="csp-sec">
  <span class="csp-badge">Curated Archives</span>
  <h2 class="csp-title">Past • Present • Upcoming</h2>
  <div class="csp-tabs-bar">
    <button class="csp-tab active" onclick="cspTab(this, 'tab-pres')">PRESENT (Active Debates)</button>
    <button class="csp-tab" onclick="cspTab(this, 'tab-up')">UPCOMING (Future Releases)</button>
    <button class="csp-tab" onclick="cspTab(this, 'tab-pst')">PAST (Most Read Essays)</button>
  </div>

  <div id="tab-pres" class="csp-tab-pane active">
    <div style="background:var(--csp-card-bg); border:1px solid var(--csp-card-border); border-radius:16px; padding:24px;">
      <div style="color:var(--csp-accent-cyan); font-size:0.75rem; font-weight:700; margin-bottom:6px;">PRESENT • TRENDING</div>
      <h3 style="color:#fff; font-size:1.125rem; font-weight:700;">Reimagining Global Governance in a Multipolar World</h3>
      <p style="color:var(--csp-text-muted); font-size:0.875rem; margin-top:8px;">Essays currently trending &amp; active debates on diplomatic shifts.</p>
    </div>
  </div>

  <div id="tab-up" class="csp-tab-pane">
    <div style="background:var(--csp-card-bg); border:1px solid var(--csp-card-border); border-radius:16px; padding:24px;">
      <div style="color:var(--csp-accent-cyan); font-size:0.75rem; font-weight:700; margin-bottom:6px;">UPCOMING • AUGUST 2026</div>
      <h3 style="color:#fff; font-size:1.125rem; font-weight:700;">The Future of Universities in the AI Era</h3>
      <p style="color:var(--csp-text-muted); font-size:0.875rem; margin-top:8px;">Featuring Professor David Anderson. Scheduled discussions &amp; countdown.</p>
    </div>
  </div>

  <div id="tab-pst" class="csp-tab-pane">
    <div style="background:var(--csp-card-bg); border:1px solid var(--csp-card-border); border-radius:16px; padding:24px;">
      <div style="color:var(--csp-accent-cyan); font-size:0.75rem; font-weight:700; margin-bottom:6px;">PAST • MOST READ</div>
      <h3 style="color:#fff; font-size:1.125rem; font-weight:700;">The Renaissance of Diaspora Journalism</h3>
      <p style="color:var(--csp-text-muted); font-size:0.875rem; margin-top:8px;">Popular interviews and previous symposium highlights.</p>
    </div>
  </div>
</div>

<!-- 7. Countdown to Next Event -->
<div class="csp-sec" id="csp-timer">
  <div class="csp-timer-banner">
    <span class="csp-badge">⚡ Countdown to Next Feature</span>
    <h2 style="font-size:1.75rem; font-weight:800; color:#fff; margin:8px 0;">NEXT LIVE DISCUSSION</h2>
    <p style="color:var(--csp-text-muted); font-size:0.9375rem;">Featuring Professor Sarah Williams: "The Future of Democracy"</p>

    <div class="csp-timer-flex">
      <div class="csp-t-box"><div class="csp-t-num" id="t-d">05</div><div class="csp-t-lbl">Days</div></div>
      <div class="csp-t-box"><div class="csp-t-num" id="t-h">14</div><div class="csp-t-lbl">Hours</div></div>
      <div class="csp-t-box"><div class="csp-t-num" id="t-m">23</div><div class="csp-t-lbl">Minutes</div></div>
      <div class="csp-t-box"><div class="csp-t-num" id="t-s">45</div><div class="csp-t-lbl">Seconds</div></div>
    </div>
    <a href="./contact-us/index.html" class="csp-btn-gradient">Register Now →</a>
  </div>
</div>

<!-- 8. Readers' Corner & 9. Weekly Poll -->
<div class="csp-sec">
  <div class="csp-grid-2">
    <!-- Weekly Poll -->
    <div style="background:var(--csp-card-bg); border:1px solid var(--csp-card-border); border-radius:20px; padding:28px;">
      <span class="csp-badge">Weekly Poll</span>
      <h3 style="color:#fff; font-size:1.125rem; font-weight:700; margin:8px 0 16px;">Which topic should Conspodium explore next?</h3>

      <div class="csp-poll-opt" onclick="cspVote(this, 42)"><div class="csp-poll-fill" style="width:42%;"></div><span class="csp-poll-txt">○ AI Ethics</span><span class="csp-poll-pct">42%</span></div>
      <div class="csp-poll-opt" onclick="cspVote(this, 28)"><div class="csp-poll-fill" style="width:28%;"></div><span class="csp-poll-txt">○ Global Politics</span><span class="csp-poll-pct">28%</span></div>
      <div class="csp-poll-opt" onclick="cspVote(this, 18)"><div class="csp-poll-fill" style="width:18%;"></div><span class="csp-poll-txt">○ Philosophy</span><span class="csp-poll-pct">18%</span></div>
      <div class="csp-poll-opt" onclick="cspVote(this, 12)"><div class="csp-poll-fill" style="width:12%;"></div><span class="csp-poll-txt">○ African Development</span><span class="csp-poll-pct">12%</span></div>
    </div>

    <!-- Readers' Corner -->
    <div style="background:var(--csp-card-bg); border:1px solid var(--csp-card-border); border-radius:20px; padding:28px; display:flex; flex-direction:column; justify-space-between;">
      <div>
        <span class="csp-badge">Readers' Corner</span>
        <h3 style="color:#fff; font-size:1.125rem; font-weight:700; margin:8px 0 12px;">Participate in Discussion</h3>
        <p style="color:var(--csp-text-muted); font-size:0.875rem; margin-bottom:12px;">Comment on essays, submit questions, or suggest future topics.</p>
        <textarea id="csp-idea-txt" placeholder="Write your question or topic suggestion..." style="width:100%; height:90px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius:10px; padding:12px; color:#fff; font-size:0.875rem; outline:none; resize:none;"></textarea>
      </div>
      <button onclick="cspSubmitIdea()" class="csp-btn-gradient" style="margin-top:12px; justify-content:center; width:100%;">Submit Suggestion →</button>
    </div>
  </div>
</div>

<!-- 10. Short Video Highlights & 16. "In Conversation With..." -->
<div class="csp-sec">
  <span class="csp-badge">Short Video Highlights</span>
  <h2 class="csp-title">In Conversation With...</h2>
  <p class="csp-sub">Embedded 30–90 second clips: scholar introductions, essay summaries, and previews.</p>

  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:24px;">
    <div style="background:var(--csp-card-bg); border:1px solid var(--csp-card-border); border-radius:16px; overflow:hidden;">
      <div style="height:180px; background:linear-gradient(rgba(0,0,0,0.3),rgba(0,0,0,0.6)), url('./wp-content/uploads/2026/01/CONSPODIUM-NEW-3-scaled-1024x315.png') center/cover; display:flex; align-items:center; justify-content:center;">
        <div style="width:48px; height:48px; border-radius:50%; background:linear-gradient(135deg,#8b5cf6,#00c9e0); display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.25rem;">▶</div>
      </div>
      <div style="padding:20px;">
        <div style="color:var(--csp-accent-cyan); font-size:0.75rem; font-weight:700;">In Conversation With Professor John Smith</div>
        <h4 style="color:#fff; font-size:1rem; font-weight:700; margin:6px 0;">"Why Democracy Needs Better Conversations"</h4>
        <div style="display:flex; gap:12px; font-size:0.8125rem; margin-top:10px;">
          <a href="#" style="color:var(--csp-accent-cyan); text-decoration:none;">▶ Watch Clip</a>
          <a href="#" style="color:var(--csp-text-muted); text-decoration:none;">📖 Read Transcript</a>
        </div>
      </div>
    </div>

    <div style="background:var(--csp-card-bg); border:1px solid var(--csp-card-border); border-radius:16px; overflow:hidden;">
      <div style="height:180px; background:linear-gradient(rgba(0,0,0,0.3),rgba(0,0,0,0.6)), url('./wp-content/uploads/2026/01/CONSPODIUM-NEW-2-300x300.png') center/cover; display:flex; align-items:center; justify-content:center;">
        <div style="width:48px; height:48px; border-radius:50%; background:linear-gradient(135deg,#8b5cf6,#00c9e0); display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.25rem;">▶</div>
      </div>
      <div style="padding:20px;">
        <div style="color:var(--csp-accent-cyan); font-size:0.75rem; font-weight:700;">Scholar Introductions • 60s</div>
        <h4 style="color:#fff; font-size:1rem; font-weight:700; margin:6px 0;">Behind the Scenes Preview</h4>
        <div style="display:flex; gap:12px; font-size:0.8125rem; margin-top:10px;">
          <a href="#" style="color:var(--csp-accent-cyan); text-decoration:none;">▶ Watch Clip</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 11. "Coming Soon" Cards -->
<div class="csp-sec">
  <span class="csp-badge">Upcoming Feature</span>
  <h2 class="csp-title">"Coming Soon" Cards</h2>

  <div style="background:var(--csp-card-bg); border:1px solid var(--csp-card-border); border-radius:20px; padding:32px; max-width:600px;">
    <div style="color:var(--csp-accent-cyan); font-size:0.8125rem; font-weight:700;">COMING SOON</div>
    <h3 style="color:#fff; font-size:1.5rem; font-weight:800; margin:8px 0;">The Future of Universities</h3>
    <p style="color:var(--csp-text-muted); font-size:0.9375rem; margin-bottom:16px;">Featuring Professor David Anderson</p>
    <div style="font-weight:600; color:var(--csp-accent-cyan); font-size:0.875rem;">August 2026-</div>
  </div>
</div>

<!-- 13. Most Popular This Month & 14. Meet the Minds -->
<div class="csp-sec">
  <div class="csp-grid-2">
    <!-- Most Popular -->
    <div>
      <span class="csp-badge">Top 5 Most-Read</span>
      <h2 class="csp-title" style="font-size:1.75rem;">Most Popular This Month</h2>

      <div class="csp-pop-list">
        <div class="csp-pop-item"><div class="csp-rank">#1</div><div><div style="color:#fff; font-weight:700;">Democracy in the Digital Age</div><div style="font-size:0.75rem; color:var(--csp-text-muted);">48,200 views • Prof. Michael Brown</div></div></div>
        <div class="csp-pop-item"><div class="csp-rank">#2</div><div><div style="color:#fff; font-weight:700;">Artificial Intelligence &amp; Ethics</div><div style="font-size:0.75rem; color:var(--csp-text-muted);">39,100 views • Prof. Jane Doe</div></div></div>
        <div class="csp-pop-item"><div class="csp-rank">#3</div><div><div style="color:#fff; font-weight:700;">African Intellectual Heritage</div><div style="font-size:0.75rem; color:var(--csp-text-muted);">31,400 views • Dr. Kwame Asante</div></div></div>
        <div class="csp-pop-item"><div class="csp-rank">#4</div><div><div style="color:#fff; font-weight:700;">Future of Higher Education</div><div style="font-size:0.75rem; color:var(--csp-text-muted);">27,800 views • Prof. David Anderson</div></div></div>
        <div class="csp-pop-item"><div class="csp-rank">#5</div><div><div style="color:#fff; font-weight:700;">Biotechnology &amp; Society</div><div style="font-size:0.75rem; color:var(--csp-text-muted);">22,500 views • Dr. Elena Vance</div></div></div>
      </div>
    </div>

    <!-- Meet the Minds -->
    <div>
      <span class="csp-badge">Distinguished Gallery</span>
      <h2 class="csp-title" style="font-size:1.75rem;">Meet the Minds</h2>
      <p style="color:var(--csp-text-muted); font-size:0.875rem; margin-bottom:20px;">A gallery of scholars who have contributed or are scheduled to contribute, building credibility immediately.</p>

      <div class="csp-minds-grid">
        <div class="csp-mind-card">
          <img class="csp-mind-avatar" src="./wp-content/uploads/2026/01/CONSPODIUM-NEW-2-300x300.png" alt="Prof. Jane Doe">
          <div style="color:#fff; font-weight:700; font-size:0.875rem;">Prof. Jane Doe</div>
          <div style="color:var(--csp-text-muted); font-size:0.75rem;">Univ. of Oxford</div>
        </div>
        <div class="csp-mind-card">
          <img class="csp-mind-avatar" src="./wp-content/uploads/2026/01/CONSPODIUM-NEW-3-scaled-300x92.png" alt="Prof. Michael Brown">
          <div style="color:#fff; font-weight:700; font-size:0.875rem;">Prof. Michael Brown</div>
          <div style="color:var(--csp-text-muted); font-size:0.75rem;">Global Governance</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 15. Interactive Timeline -->
<div class="csp-sec">
  <span class="csp-badge">Interactive Timeline</span>
  <h2 class="csp-title">Browse Our History &amp; Roadmap</h2>
  <p class="csp-sub">Visitors can browse past symposiums, major essays, landmark interviews, and future releases.</p>

  <div style="border-left:2px solid var(--csp-accent-cyan); padding-left:20px; display:flex; flex-direction:column; gap:20px;">
    <div>
      <div style="color:var(--csp-accent-cyan); font-weight:700; font-size:0.8125rem;">2024 - 2025 • PAST SYMPOSIUMS</div>
      <div style="color:#fff; font-weight:700;">Foundation &amp; 500+ Landmark Essays</div>
    </div>
    <div>
      <div style="color:var(--csp-accent-cyan); font-weight:700; font-size:0.8125rem;">PRESENT 2026 • ACTIVE DEBATES</div>
      <div style="color:#fff; font-weight:700;">AI Ethics, Multipolarity &amp; Global Public Forums</div>
    </div>
    <div>
      <div style="color:var(--csp-accent-cyan); font-weight:700; font-size:0.8125rem;">UPCOMING 2026 - 2027 • FUTURE RELEASES</div>
      <div style="color:#fff; font-weight:700;">Global Scholar Fellowship &amp; Printed Anthology</div>
    </div>
  </div>
</div>

<!-- 17. Social Proof & 18. Community Wall (Reader Testimonials) -->
<div class="csp-sec">
  <div style="text-align:center; margin-bottom:32px;">
    <span class="csp-badge">Social Proof</span>
    <h2 class="csp-title">Community Wall &amp; Global Impact</h2>
  </div>

  <div class="csp-metrics-4" style="margin-bottom:40px;">
    <div class="csp-m-box"><div class="csp-m-num">500+</div><div style="color:var(--csp-text-muted); font-size:0.875rem;">Published Essays</div></div>
    <div class="csp-m-box"><div class="csp-m-num">80</div><div style="color:var(--csp-text-muted); font-size:0.875rem;">Distinguished Contributors</div></div>
    <div class="csp-m-box"><div class="csp-m-num">70</div><div style="color:var(--csp-text-muted); font-size:0.875rem;">Countries Reached</div></div>
    <div class="csp-m-box"><div class="csp-m-num">2M+</div><div style="color:var(--csp-text-muted); font-size:0.875rem;">Total Views</div></div>
  </div>

  <div class="csp-testi-grid">
    <div class="csp-testi-card">
      <p style="color:#fff; font-style:italic; font-size:0.9375rem; line-height:1.5;">"Conspodium challenges conventional thinking. The depth of essays and global perspectives are unmatched."</p>
      <div style="color:var(--csp-accent-cyan); font-weight:700; font-size:0.8125rem; margin-top:12px;">— Reader, Canada</div>
    </div>
    <div class="csp-testi-card">
      <p style="color:#fff; font-style:italic; font-size:0.9375rem; line-height:1.5;">"An invaluable platform for diaspora intellectuals and rigorous academic discussions."</p>
      <div style="color:var(--csp-accent-cyan); font-weight:700; font-size:0.8125rem; margin-top:12px;">— Scholar, UK</div>
    </div>
  </div>
</div>

<!-- 12. Newsletter Invitation & 19. Elegant Call-to-Action -->
<div class="csp-sec">
  <div class="csp-news-box">
    <span class="csp-badge">Newsletter Invitation</span>
    <h2 style="font-size:2rem; font-weight:800; color:#fff; margin:12px 0;">Never Miss a Great Idea</h2>
    <p style="color:var(--csp-text-muted); font-size:0.9375rem; max-width:500px; margin:0 auto;">Receive exclusive essays, interviews, and event invitations before everyone else.</p>

    <form onsubmit="event.preventDefault(); alert('Thank you for subscribing!');" style="display:flex; gap:10px; max-width:480px; margin:24px auto 0;">
      <input type="email" placeholder="Enter your email address..." required style="flex:1; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); border-radius:50px; padding:12px 20px; color:#fff; outline:none;">
      <button type="submit" class="csp-btn-gradient">Subscribe Free</button>
    </form>

    <div style="margin-top:48px; border-top:1px solid rgba(255,255,255,0.1); padding-top:32px;">
      <h3 style="font-size:1.5rem; font-weight:800; color:#fff;">Ideas Shape Civilizations. Conversations Change the World</h3>
      <p style="color:var(--csp-accent-cyan); font-weight:700; margin:8px 0 20px;">Join the Conspodium Community</p>
      <p style="color:var(--csp-text-muted); font-weight:600; font-size:0.9375rem;">Read • Reflect • Debate • Inspire</p>
    </div>
  </div>
</div>

<script id="csp-full-structure-scripts">
  let cspCurSlide = 0;
  const cspHeroSlides = document.querySelectorAll('.csp-hero-slide');
  const cspHeroDots = document.querySelectorAll('.csp-hero-dots .csp-dot');

  function cspSlideTo(idx) {
    cspHeroSlides.forEach(s => s.classList.remove('active'));
    cspHeroDots.forEach(d => d.classList.remove('active'));
    cspCurSlide = idx;
    if (cspHeroSlides[idx]) cspHeroSlides[idx].classList.add('active');
    if (cspHeroDots[idx]) cspHeroDots[idx].classList.add('active');
  }

  setInterval(() => {
    cspCurSlide = (cspCurSlide + 1) % cspHeroSlides.length;
    cspSlideTo(cspCurSlide);
  }, 6000);

  function cspTab(btn, paneId) {
    document.querySelectorAll('.csp-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.csp-tab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const el = document.getElementById(paneId);
    if (el) el.classList.add('active');
  }

  function cspVote(opt, pct) {
    document.querySelectorAll('.csp-poll-opt').forEach(o => o.style.borderColor = 'rgba(255,255,255,0.08)');
    opt.style.borderColor = 'var(--csp-accent-cyan)';
    alert('Thank you for voting! Option recorded (' + pct + '%).');
  }

  function cspSubmitIdea() {
    const v = document.getElementById('csp-idea-txt').value;
    if (!v.trim()) { alert('Please enter a question or suggestion.'); return; }
    alert('Thank you! Your submission has been received.');
    document.getElementById('csp-idea-txt').value = '';
  }

  let cspSecs = (5 * 86400) + (14 * 3600) + (23 * 60) + 45;
  setInterval(() => {
    if (cspSecs > 0) cspSecs--;
    const d = Math.floor(cspSecs / 86400);
    const h = Math.floor((cspSecs % 86400) / 3600);
    const m = Math.floor((cspSecs % 3600) / 60);
    const s = cspSecs % 60;
    const elD = document.getElementById('t-d');
    const elH = document.getElementById('t-h');
    const elM = document.getElementById('t-m');
    const elS = document.getElementById('t-s');
    if (elD) elD.textContent = String(d).padStart(2, '0');
    if (elH) elH.textContent = String(h).padStart(2, '0');
    if (elM) elM.textContent = String(m).padStart(2, '0');
    if (elS) elS.textContent = String(s).padStart(2, '0');
  }, 1000);
</script>
`;

async function main() {
  let html = await readFile(INDEX_PATH, 'utf8');

  // Strip previous custom injected styles or feature scripts if re-running
  html = html.replace(/<style id="csp-full-structure-styles">[\s\S]*?<\/style>/g, '');
  html = html.replace(/<style id="csp-suggested-features-styles">[\s\S]*?<\/style>/g, '');
  html = html.replace(/<div class="csp-ticker-bar"[\s\S]*?<\/script>/g, '');

  // 1. Inject STYLES in head
  html = html.replace('</head>', STYLES + '\n</head>');

  // 2. Inject ALL 19 SECTIONS into the body right after header
  const headerEnd = html.indexOf('<!-- CSP_HEADER_BLOCK_END -->');
  if (headerEnd !== -1) {
    const pos = headerEnd + '<!-- CSP_HEADER_BLOCK_END -->'.length;
    html = html.slice(0, pos) + '\n' + ALL_19_SECTIONS_HTML + '\n' + html.slice(pos);
  } else {
    const headerEndTag = html.indexOf('</header>');
    if (headerEndTag !== -1) {
      const pos = headerEndTag + '</header>'.length;
      html = html.slice(0, pos) + '\n' + ALL_19_SECTIONS_HTML + '\n' + html.slice(pos);
    }
  }

  await writeFile(INDEX_PATH, html, 'utf8');
  console.log('Successfully applied all 19 suggested homepage structure features to output/index.html!');
}

main().catch(console.error);
