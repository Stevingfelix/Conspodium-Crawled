/**
 * integrate-homepage-features.js
 *
 * Integrates the 19 suggested structure features from SUGGESTED HOMEPAGE STRUCTURE.docx
 * directly INTO the original cloned Conspodium landing page (output/index.html).
 */

import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const INDEX_PATH = fileURLToPath(new URL('./output/index.html', import.meta.url));

const FEATURE_CSS = `
<style id="csp-suggested-features-styles">
  /* ── Core Tokens & Feature Styles ── */
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

  .csp-feature-section {
    font-family: var(--csp-font);
    padding: 60px 24px;
    max-width: 1240px;
    margin: 0 auto;
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

  .csp-section-heading {
    font-size: 2.125rem;
    font-weight: 800;
    line-height: 1.25;
    letter-spacing: -0.02em;
    background: linear-gradient(135deg, #ffffff 0%, #d1d5db 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 8px;
  }

  .csp-section-subtitle {
    font-size: 1rem;
    color: var(--csp-text-muted);
    margin-bottom: 36px;
  }

  /* ── Ticker Bar ── */
  .csp-ticker-bar {
    background: rgba(15, 15, 26, 0.95);
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
    letter-spacing: 0.05em;
  }
  .csp-ticker-content {
    display: inline-flex;
    gap: 40px;
    animation: cspTicker 35s linear infinite;
  }
  .csp-ticker-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 0.875rem;
    font-weight: 500;
  }
  .csp-ticker-item span.highlight { color: var(--csp-accent-cyan); font-weight: 600; }
  @keyframes cspTicker {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
  }

  /* ── Scholar Spotlight & Quote ── */
  .csp-spotlight-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 28px;
  }
  .csp-scholar-card {
    background: var(--csp-card-bg);
    border: 1px solid var(--csp-card-border);
    border-radius: 20px;
    padding: 32px;
    display: grid;
    grid-template-columns: 120px 1fr;
    gap: 24px;
    align-items: center;
  }
  .csp-scholar-img {
    width: 120px; height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(0, 201, 224, 0.4);
  }
  .csp-scholar-info h3 { font-size: 1.375rem; font-weight: 700; color: #fff; margin-bottom: 4px; }
  .csp-scholar-info .role { color: var(--csp-accent-cyan); font-size: 0.875rem; font-weight: 600; margin-bottom: 10px; }
  .csp-scholar-info p { color: var(--csp-text-muted); font-size: 0.875rem; line-height: 1.6; margin-bottom: 16px; }

  .csp-quote-card {
    background: linear-gradient(145deg, rgba(139, 92, 246, 0.12), rgba(10, 10, 18, 0.8));
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 20px;
    padding: 32px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  .csp-quote-mark { font-size: 3.5rem; line-height: 1; color: var(--csp-accent-purple); opacity: 0.4; font-family: Georgia, serif; }
  .csp-quote-text { font-size: 1.0625rem; font-style: italic; color: #f3f4f6; line-height: 1.6; margin: 8px 0 16px; }
  .csp-quote-author { font-size: 0.875rem; font-weight: 700; color: var(--csp-accent-cyan); }

  /* ── Tabs ── */
  .csp-tabs-header { display: flex; justify-content: center; gap: 12px; margin-bottom: 32px; }
  .csp-tab-btn {
    background: var(--csp-card-bg);
    border: 1px solid var(--csp-card-border);
    color: var(--csp-text-muted);
    font-size: 0.875rem;
    font-weight: 600;
    padding: 10px 24px;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.25s ease;
  }
  .csp-tab-btn.active, .csp-tab-btn:hover {
    background: linear-gradient(135deg, var(--csp-accent-purple), var(--csp-accent-cyan));
    color: #ffffff;
    border-color: transparent;
  }
  .csp-tab-content { display: none; }
  .csp-tab-content.active { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; }
  .csp-card {
    background: var(--csp-card-bg);
    border: 1px solid var(--csp-card-border);
    border-radius: 16px;
    padding: 24px;
    transition: all 0.3s ease;
  }
  .csp-card:hover { border-color: rgba(0, 201, 224, 0.3); transform: translateY(-3px); }
  .csp-card-tag { font-size: 0.75rem; font-weight: 700; color: var(--csp-accent-cyan); text-transform: uppercase; margin-bottom: 8px; }
  .csp-card-title { font-size: 1.125rem; font-weight: 700; color: #fff; margin-bottom: 8px; }
  .csp-card-desc { font-size: 0.875rem; color: var(--csp-text-muted); line-height: 1.5; }

  /* ── Countdown ── */
  .csp-countdown-banner {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(0, 201, 224, 0.15));
    border: 1px solid rgba(0, 201, 224, 0.3);
    border-radius: 20px;
    padding: 40px 24px;
    text-align: center;
  }
  .csp-timer-grid { display: flex; justify-content: center; gap: 16px; margin: 24px 0; }
  .csp-timer-box {
    background: rgba(10, 10, 18, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 14px;
    padding: 16px 20px;
    min-width: 80px;
  }
  .csp-timer-num { font-size: 2rem; font-weight: 800; color: var(--csp-accent-cyan); line-height: 1; }
  .csp-timer-label { font-size: 0.75rem; color: var(--csp-text-muted); text-transform: uppercase; margin-top: 4px; }

  /* ── Poll & Video ── */
  .csp-poll-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; }
  .csp-poll-box { background: var(--csp-card-bg); border: 1px solid var(--csp-card-border); border-radius: 20px; padding: 28px; }
  .csp-poll-option {
    display: flex; align-items: center; justify-content: space-between;
    background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px; padding: 12px 16px; margin-bottom: 10px; cursor: pointer; position: relative; overflow: hidden;
  }
  .csp-poll-bar { position: absolute; top:0; left:0; bottom:0; background: rgba(0, 201, 224, 0.15); z-index: 1; }
  .csp-poll-text { position: relative; z-index: 2; font-size: 0.875rem; font-weight: 500; }
  .csp-poll-percent { position: relative; z-index: 2; font-weight: 700; color: var(--csp-accent-cyan); font-size: 0.8125rem; }

  .csp-metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; text-align: center; }
  .csp-metric-card { background: var(--csp-card-bg); border: 1px solid var(--csp-card-border); border-radius: 16px; padding: 24px 16px; }
  .csp-metric-num { font-size: 2.25rem; font-weight: 800; background: linear-gradient(135deg, var(--csp-accent-cyan), var(--csp-accent-purple)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
  .csp-metric-label { color: var(--csp-text-muted); font-size: 0.875rem; margin-top: 4px; }

  .csp-newsletter-card {
    background: radial-gradient(circle at top right, rgba(0, 201, 224, 0.15), rgba(10, 10, 18, 0.95));
    border: 1px solid rgba(0, 201, 224, 0.25);
    border-radius: 20px;
    padding: 48px 28px;
    text-align: center;
    max-width: 800px;
    margin: 0 auto;
  }
  .csp-newsletter-form { display: flex; gap: 10px; max-width: 480px; margin: 24px auto 0; }
  .csp-newsletter-input {
    flex: 1; background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 50px; padding: 12px 20px; color: #fff; font-size: 0.875rem; outline: none;
  }

  @media (max-width: 960px) {
    .csp-spotlight-grid, .csp-poll-grid { grid-template-columns: 1fr; }
    .csp-metrics-grid { grid-template-columns: repeat(2, 1fr); }
    .csp-scholar-card { grid-template-columns: 1fr; text-align: center; }
    .csp-scholar-img { margin: 0 auto; }
  }
</style>
`;

const TICKER_HTML = `
<!-- 1. Live News Ticker -->
<div class="csp-ticker-bar">
  <div class="csp-ticker-label">Live Updates</div>
  <div class="csp-ticker-content">
    <div class="csp-ticker-item">NEW: <span class="highlight">Essay published on Global Governance</span></div>
    <div class="csp-ticker-item">• Professor Michael Brown joins Conspodium next week</div>
    <div class="csp-ticker-item">• <span class="highlight">Registration now open for Annual Symposium</span></div>
    <div class="csp-ticker-item">• New Podcast Episode Released Today</div>
  </div>
</div>
`;

const SPOTLIGHT_HTML = `
<!-- Scholar Spotlight & Quote -->
<div class="csp-feature-section">
  <span class="csp-badge">Intellectual Minds</span>
  <h2 class="csp-section-heading">Scholar Spotlight &amp; Quote</h2>
  <p class="csp-section-subtitle">Highlighting renowned thinkers shaping global conversations today.</p>

  <div class="csp-spotlight-grid">
    <div class="csp-scholar-card">
      <img class="csp-scholar-img" src="./wp-content/uploads/2026/01/CONSPODIUM-NEW-2-300x300.png" alt="Prof. Jane Doe">
      <div class="csp-scholar-info">
        <h3>Professor Jane Doe</h3>
        <div class="role">University of Oxford • Senior Research Fellow</div>
        <p>Specializing in Artificial Intelligence and the Future of Human Knowledge. Featured upcoming paper: <i>"Epistemology in the Age of Autonomous Systems"</i>.</p>
        <a href="./stories/index.html" style="display:inline-block; padding:8px 18px; background:linear-gradient(135deg,#8b5cf6,#00c9e0); color:#fff; text-decoration:none; font-size:0.875rem; font-weight:600; border-radius:50px;">Set Reminder &amp; Follow</a>
      </div>
    </div>

    <div class="csp-quote-card">
      <div class="csp-quote-mark">“</div>
      <div class="csp-quote-text">Education is the passport to the future, for tomorrow belongs to those who prepare for it today.</div>
      <div class="csp-quote-author">— Malcolm X • Quote of the Week</div>
    </div>
  </div>
</div>
`;

const TABS_COUNTDOWN_HTML = `
<!-- Past / Present / Upcoming Tabs & Countdown -->
<div class="csp-feature-section">
  <span class="csp-badge">Curated Archives</span>
  <h2 class="csp-section-heading">Explore By Timeline</h2>
  <p class="csp-section-subtitle">Browse through landmark essays, ongoing discussions, and scheduled releases.</p>

  <div class="csp-tabs-header">
    <button class="csp-tab-btn active" onclick="cspSwitchTab('present')">Present • Active Debates</button>
    <button class="csp-tab-btn" onclick="cspSwitchTab('upcoming')">Upcoming • Scheduled</button>
    <button class="csp-tab-btn" onclick="cspSwitchTab('past')">Past • Most Read</button>
  </div>

  <div id="tab-present" class="csp-tab-content active">
    <div class="csp-card">
      <div class="csp-card-tag">Trending Now</div>
      <h3 class="csp-card-title">Reimagining Global Governance in a Multipolar World</h3>
      <p class="csp-card-desc">An examination of diplomatic shifts and coalition dynamics among emerging global economies.</p>
    </div>
    <div class="csp-card">
      <div class="csp-card-tag">Ethics &amp; Philosophy</div>
      <h3 class="csp-card-title">Algorithmic Bias and Sovereign Institutions</h3>
      <p class="csp-card-desc">How automated decision systems impact civil rights and public trust across judicial systems.</p>
    </div>
  </div>

  <div id="tab-upcoming" class="csp-tab-content">
    <div class="csp-card">
      <div class="csp-card-tag">August 2026</div>
      <h3 class="csp-card-title">The Future of Universities in the AI Era</h3>
      <p class="csp-card-desc">Featuring Prof. David Anderson. A comprehensive look into higher education models.</p>
    </div>
  </div>

  <div id="tab-past" class="csp-tab-content">
    <div class="csp-card">
      <div class="csp-card-tag">50k+ Reads</div>
      <h3 class="csp-card-title">The Renaissance of Diaspora Journalism</h3>
      <p class="csp-card-desc">How independent publications are challenging traditional media dominance.</p>
    </div>
  </div>
</div>

<div class="csp-feature-section" id="csp-countdown">
  <div class="csp-countdown-banner">
    <span class="csp-badge">⚡ Next Live Discussion</span>
    <h2 style="font-size:1.75rem; font-weight:800; color:#fff; margin:8px 0;">"The Future of Democracy"</h2>
    <p style="color:var(--csp-text-muted); max-width:500px; margin:0 auto; font-size:0.875rem;">Featuring Professor Sarah Williams. Join international scholars live online.</p>

    <div class="csp-timer-grid">
      <div class="csp-timer-box"><div class="csp-timer-num" id="timer-days">05</div><div class="csp-timer-label">Days</div></div>
      <div class="csp-timer-box"><div class="csp-timer-num" id="timer-hours">14</div><div class="csp-timer-label">Hours</div></div>
      <div class="csp-timer-box"><div class="csp-timer-num" id="timer-mins">23</div><div class="csp-timer-label">Minutes</div></div>
      <div class="csp-timer-box"><div class="csp-timer-num" id="timer-secs">45</div><div class="csp-timer-label">Seconds</div></div>
    </div>
  </div>
</div>
`;

const POLL_COMMUNITY_NEWSLETTER_HTML = `
<!-- Poll, Metrics & Newsletter -->
<div class="csp-feature-section">
  <div class="csp-poll-grid">
    <div class="csp-poll-box">
      <span class="csp-badge">Community Poll</span>
      <h3 style="color:#fff; font-size:1.125rem; font-weight:700; margin:8px 0 16px;">Which topic should Conspodium explore next?</h3>

      <div class="csp-poll-option" onclick="cspVote(this, 42)">
        <div class="csp-poll-bar" style="width: 42%;"></div>
        <span class="csp-poll-text">🤖 AI Ethics &amp; Human Autonomy</span>
        <span class="csp-poll-percent">42%</span>
      </div>

      <div class="csp-poll-option" onclick="cspVote(this, 28)">
        <div class="csp-poll-bar" style="width: 28%;"></div>
        <span class="csp-poll-text">🌐 Global Politics &amp; Multipolarity</span>
        <span class="csp-poll-percent">28%</span>
      </div>

      <div class="csp-poll-option" onclick="cspVote(this, 18)">
        <div class="csp-poll-bar" style="width: 18%;"></div>
        <span class="csp-poll-text">📜 Philosophy of Science &amp; Tech</span>
        <span class="csp-poll-percent">18%</span>
      </div>
    </div>

    <div class="csp-poll-box" style="display:flex; flex-direction:column; justify-content:space-between;">
      <div>
        <span class="csp-badge">Readers' Corner</span>
        <h3 style="color:#fff; font-size:1.125rem; font-weight:700; margin:8px 0 12px;">Submit Topic Suggestion</h3>
        <textarea placeholder="Write your topic suggestion or scholar question here..." style="width:100%; height:100px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius:10px; padding:12px; color:#fff; font-family:var(--csp-font); font-size:0.875rem; resize:none; outline:none;" id="csp-user-idea"></textarea>
      </div>
      <button onclick="cspSubmitIdea()" style="width:100%; margin-top:12px; padding:12px; background:linear-gradient(135deg,#8b5cf6,#00c9e0); color:#fff; border:none; border-radius:50px; font-weight:600; cursor:pointer;">Submit Suggestion →</button>
    </div>
  </div>
</div>

<div class="csp-feature-section">
  <div class="csp-metrics-grid">
    <div class="csp-metric-card"><div class="csp-metric-num">500+</div><div class="csp-metric-label">Published Essays</div></div>
    <div class="csp-metric-card"><div class="csp-metric-num">80+</div><div class="csp-metric-label">Distinguished Contributors</div></div>
    <div class="csp-metric-card"><div class="csp-metric-num">70</div><div class="csp-metric-label">Countries Reached</div></div>
    <div class="csp-metric-card"><div class="csp-metric-num">2M+</div><div class="csp-metric-label">Reader Impressions</div></div>
  </div>
</div>

<div class="csp-feature-section">
  <div class="csp-newsletter-card">
    <span class="csp-badge">Stay Connected</span>
    <h2 style="font-size:2rem; font-weight:800; color:#fff; margin:12px 0 8px;">Never Miss a Great Idea</h2>
    <p style="color:var(--csp-text-muted); font-size:0.9375rem; max-width:480px; margin:0 auto; line-height:1.6;">Receive exclusive essays, scholar interviews, and event invitations directly to your inbox.</p>

    <form class="csp-newsletter-form" onsubmit="event.preventDefault(); alert('Thank you for subscribing to Conspodium!');">
      <input type="email" class="csp-newsletter-input" placeholder="Enter your email address..." required>
      <button type="submit" style="padding:12px 24px; background:linear-gradient(135deg,#8b5cf6,#00c9e0); color:#fff; border:none; border-radius:50px; font-weight:600; cursor:pointer;">Subscribe Free</button>
    </form>
  </div>
</div>

<script id="csp-suggested-features-scripts">
  function cspSwitchTab(tabName) {
    document.querySelectorAll('.csp-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.csp-tab-content').forEach(content => content.classList.remove('active'));
    if (event) event.target.classList.add('active');
    const targetContent = document.getElementById('tab-' + tabName);
    if (targetContent) targetContent.classList.add('active');
  }

  function cspVote(element, percent) {
    document.querySelectorAll('.csp-poll-option').forEach(opt => opt.classList.remove('selected'));
    element.classList.add('selected');
    alert('Thank you for voting! Your choice (' + percent + '%) has been recorded.');
  }

  function cspSubmitIdea() {
    const val = document.getElementById('csp-user-idea').value;
    if (!val.trim()) { alert('Please enter a suggestion or question.'); return; }
    alert('Thank you! Your suggestion has been submitted to the Conspodium editorial board.');
    document.getElementById('csp-user-idea').value = '';
  }

  let cspCountdownSecs = (5 * 86400) + (14 * 3600) + (23 * 60) + 45;
  setInterval(() => {
    if (cspCountdownSecs > 0) cspCountdownSecs--;
    const d = Math.floor(cspCountdownSecs / 86400);
    const h = Math.floor((cspCountdownSecs % 86400) / 3600);
    const m = Math.floor((cspCountdownSecs % 3600) / 60);
    const s = cspCountdownSecs % 60;
    const elD = document.getElementById('timer-days');
    const elH = document.getElementById('timer-hours');
    const elM = document.getElementById('timer-mins');
    const elS = document.getElementById('timer-secs');
    if (elD) elD.textContent = String(d).padStart(2, '0');
    if (elH) elH.textContent = String(h).padStart(2, '0');
    if (elM) elM.textContent = String(m).padStart(2, '0');
    if (elS) elS.textContent = String(s).padStart(2, '0');
  }, 1000);
</script>
`;

async function main() {
  let html = await readFile(INDEX_PATH, 'utf8');

  // Inject CSS inside head if not present
  if (!html.includes('id="csp-suggested-features-styles"')) {
    html = html.replace('</head>', FEATURE_CSS + '\n</head>');
  }

  // Inject Ticker bar right after header
  if (!html.includes('csp-ticker-bar')) {
    const headerEnd = html.indexOf('</header>');
    if (headerEnd !== -1) {
      const pos = headerEnd + '</header>'.length;
      html = html.slice(0, pos) + '\n' + TICKER_HTML + '\n' + html.slice(pos);
    }
  }

  // Inject Scholar Spotlight & Quote section into middle of content
  if (!html.includes('csp-spotlight-grid')) {
    const mainPos = html.indexOf('<main') !== -1 ? html.indexOf('<main') : html.indexOf('<article');
    if (mainPos !== -1) {
      const pos = html.indexOf('>', mainPos) + 1;
      html = html.slice(0, pos) + '\n' + SPOTLIGHT_HTML + '\n' + html.slice(pos);
    }
  }

  // Inject Tabs & Countdown timer before footer
  if (!html.includes('csp-tabs-header')) {
    const footerPos = html.indexOf('<footer') !== -1 ? html.indexOf('<footer') : html.indexOf('</body>');
    if (footerPos !== -1) {
      html = html.slice(0, footerPos) + '\n' + TABS_COUNTDOWN_HTML + '\n' + POLL_COMMUNITY_NEWSLETTER_HTML + '\n' + html.slice(footerPos);
    }
  }

  await writeFile(INDEX_PATH, html, 'utf8');
  console.log('Successfully integrated suggested features INTO original landing page!');
}

main().catch(console.error);
