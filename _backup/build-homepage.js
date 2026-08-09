/**
 * build-homepage.js
 *
 * Builds the complete landing page structure for output/index.html based on
 * SUGGESTED HOMEPAGE STRUCTURE.docx, adhering to Conspodium's dark glassmorphism design.
 */

import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const INDEX_PATH = fileURLToPath(new URL('./output/index.html', import.meta.url));

function getHomepageCSS() {
  return `
<style id="csp-homepage-styles">
  /* ── Reset & Core Tokens ── */
  :root {
    --csp-bg: #0a0a12;
    --csp-card-bg: rgba(255, 255, 255, 0.035);
    --csp-card-border: rgba(255, 255, 255, 0.08);
    --csp-card-hover: rgba(255, 255, 255, 0.07);
    --csp-accent-cyan: #00c9e0;
    --csp-accent-purple: #8b5cf6;
    --csp-accent-pink: #ec4899;
    --csp-text-main: #f3f4f6;
    --csp-text-muted: #9ca3af;
    --csp-font: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  }

  .csp-section-wrapper {
    background-color: var(--csp-bg);
    color: var(--csp-text-main);
    font-family: var(--csp-font);
    overflow-x: hidden;
  }

  .csp-container {
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 24px;
  }

  .csp-section {
    padding: 72px 0;
    position: relative;
  }

  .csp-section-title-wrap {
    text-align: center;
    margin-bottom: 48px;
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
    font-size: 2.25rem;
    font-weight: 800;
    line-height: 1.25;
    letter-spacing: -0.02em;
    background: linear-gradient(135deg, #ffffff 0%, #d1d5db 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 12px;
  }

  .csp-section-subtitle {
    font-size: 1.0625rem;
    color: var(--csp-text-muted);
    max-width: 640px;
    margin: 0 auto;
    line-height: 1.6;
  }

  /* ── 1. Live News Ticker ── */
  .csp-ticker-bar {
    background: rgba(15, 15, 26, 0.95);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding: 10px 0;
    display: flex;
    align-items: center;
    overflow: hidden;
    white-space: nowrap;
    position: relative;
    z-index: 10;
  }

  .csp-ticker-label {
    flex-shrink: 0;
    background: linear-gradient(135deg, #8b5cf6, #00c9e0);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 4px;
    margin-left: 24px;
    margin-right: 16px;
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
  .csp-ticker-item span.highlight {
    color: var(--csp-accent-cyan);
    font-weight: 600;
  }

  @keyframes cspTicker {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
  }

  /* ── 2. Dynamic Hero Banner ── */
  .csp-hero {
    position: relative;
    padding: 60px 0 80px;
    background: radial-gradient(circle at 50% 20%, rgba(139, 92, 246, 0.15) 0%, rgba(10, 10, 18, 0) 70%);
  }

  .csp-hero-carousel {
    position: relative;
    min-height: 420px;
  }

  .csp-hero-slide {
    display: none;
    opacity: 0;
    transition: opacity 0.5s ease-in-out;
  }
  .csp-hero-slide.active {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 48px;
    align-items: center;
    opacity: 1;
  }

  .csp-hero-content h1 {
    font-size: 3rem;
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -0.03em;
    margin: 16px 0 20px;
    background: linear-gradient(135deg, #ffffff 0%, #9ca3af 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .csp-hero-content p {
    font-size: 1.125rem;
    color: var(--csp-text-muted);
    line-height: 1.7;
    margin-bottom: 32px;
    max-width: 580px;
  }

  .csp-hero-actions {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
  }

  .csp-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, var(--csp-accent-purple) 0%, var(--csp-accent-cyan) 100%);
    color: #ffffff;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9375rem;
    padding: 14px 30px;
    border-radius: 50px;
    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.35);
    transition: all 0.25s ease;
  }
  .csp-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(139, 92, 246, 0.5);
    color: #ffffff;
  }

  .csp-btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.06);
    color: #ffffff;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9375rem;
    padding: 14px 28px;
    border-radius: 50px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    transition: all 0.25s ease;
  }
  .csp-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.3);
    color: #ffffff;
  }

  .csp-hero-card {
    background: var(--csp-card-bg);
    border: 1px solid var(--csp-card-border);
    border-radius: 20px;
    padding: 32px;
    backdrop-filter: blur(12px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
    position: relative;
    overflow: hidden;
  }
  .csp-hero-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--csp-accent-purple), var(--csp-accent-cyan));
  }

  .csp-hero-dots {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 36px;
  }
  .csp-dot {
    width: 12px; height: 12px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    cursor: pointer;
    transition: all 0.3s ease;
  }
  .csp-dot.active {
    width: 32px;
    border-radius: 12px;
    background: var(--csp-accent-cyan);
  }

  /* ── 3. Trending Topics Carousel ── */
  .csp-topics-grid {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    justify-content: center;
  }

  .csp-topic-pill {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 22px;
    background: var(--csp-card-bg);
    border: 1px solid var(--csp-card-border);
    border-radius: 50px;
    color: var(--csp-text-main);
    font-size: 0.9375rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.25s ease;
  }
  .csp-topic-pill:hover {
    background: rgba(0, 201, 224, 0.12);
    border-color: var(--csp-accent-cyan);
    color: var(--csp-accent-cyan);
    transform: translateY(-2px);
  }
  .csp-topic-pill svg { color: var(--csp-accent-cyan); }

  /* ── 4. Scholar Spotlight & Quote ── */
  .csp-spotlight-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 32px;
  }

  .csp-scholar-card {
    background: var(--csp-card-bg);
    border: 1px solid var(--csp-card-border);
    border-radius: 20px;
    padding: 36px;
    display: grid;
    grid-template-columns: 140px 1fr;
    gap: 28px;
    align-items: center;
  }

  .csp-scholar-img {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(0, 201, 224, 0.4);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
  }

  .csp-scholar-info h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 4px;
    color: #fff;
  }
  .csp-scholar-info .role {
    color: var(--csp-accent-cyan);
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 14px;
  }
  .csp-scholar-info p {
    color: var(--csp-text-muted);
    font-size: 0.9375rem;
    line-height: 1.6;
    margin-bottom: 20px;
  }

  .csp-quote-card {
    background: linear-gradient(145deg, rgba(139, 92, 246, 0.1), rgba(10, 10, 18, 0.8));
    border: 1px solid rgba(139, 92, 246, 0.25);
    border-radius: 20px;
    padding: 36px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
  }
  .csp-quote-mark {
    font-size: 4rem;
    line-height: 1;
    color: var(--csp-accent-purple);
    opacity: 0.4;
    font-family: Georgia, serif;
  }
  .csp-quote-text {
    font-size: 1.125rem;
    font-style: italic;
    color: #f3f4f6;
    line-height: 1.6;
    margin: 12px 0 20px;
  }
  .csp-quote-author {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--csp-accent-cyan);
  }

  /* ── 5. Past • Present • Upcoming Tabs ── */
  .csp-tabs-header {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-bottom: 40px;
  }

  .csp-tab-btn {
    background: var(--csp-card-bg);
    border: 1px solid var(--csp-card-border);
    color: var(--csp-text-muted);
    font-size: 0.9375rem;
    font-weight: 600;
    padding: 12px 28px;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.25s ease;
  }
  .csp-tab-btn.active, .csp-tab-btn:hover {
    background: linear-gradient(135deg, var(--csp-accent-purple), var(--csp-accent-cyan));
    color: #ffffff;
    border-color: transparent;
    box-shadow: 0 6px 20px rgba(0, 201, 224, 0.3);
  }

  .csp-tab-content {
    display: none;
  }
  .csp-tab-content.active {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 28px;
  }

  .csp-card {
    background: var(--csp-card-bg);
    border: 1px solid var(--csp-card-border);
    border-radius: 16px;
    padding: 28px;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  .csp-card:hover {
    background: var(--csp-card-hover);
    border-color: rgba(0, 201, 224, 0.3);
    transform: translateY(-4px);
  }
  .csp-card-tag {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--csp-accent-cyan);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 12px;
  }
  .csp-card-title {
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1.4;
    color: #fff;
    margin-bottom: 12px;
  }
  .csp-card-desc {
    font-size: 0.875rem;
    color: var(--csp-text-muted);
    line-height: 1.6;
    margin-bottom: 20px;
  }
  .csp-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.8125rem;
    color: var(--csp-text-muted);
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    padding-top: 16px;
    margin-top: auto;
  }

  /* ── 6. Countdown Timer Section ── */
  .csp-countdown-banner {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(0, 201, 224, 0.15));
    border: 1px solid rgba(0, 201, 224, 0.3);
    border-radius: 24px;
    padding: 48px;
    text-align: center;
    position: relative;
    overflow: hidden;
  }

  .csp-timer-grid {
    display: flex;
    justify-content: center;
    gap: 24px;
    margin: 32px 0;
  }
  .csp-timer-box {
    background: rgba(10, 10, 18, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 20px 28px;
    min-width: 100px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  }
  .csp-timer-num {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--csp-accent-cyan);
    line-height: 1;
    margin-bottom: 6px;
  }
  .csp-timer-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--csp-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  /* ── 7. Readers' Poll & Corner ── */
  .csp-poll-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 36px;
  }

  .csp-poll-box {
    background: var(--csp-card-bg);
    border: 1px solid var(--csp-card-border);
    border-radius: 20px;
    padding: 36px;
  }

  .csp-poll-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 14px 20px;
    margin-bottom: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
  }
  .csp-poll-option:hover {
    background: rgba(0, 201, 224, 0.08);
    border-color: var(--csp-accent-cyan);
  }
  .csp-poll-option.selected {
    border-color: var(--csp-accent-cyan);
    background: rgba(0, 201, 224, 0.12);
  }
  .csp-poll-bar {
    position: absolute;
    top: 0; left: 0; bottom: 0;
    background: rgba(0, 201, 224, 0.15);
    z-index: 1;
    transition: width 0.4s ease;
  }
  .csp-poll-text {
    position: relative;
    z-index: 2;
    font-weight: 500;
    font-size: 0.9375rem;
  }
  .csp-poll-percent {
    position: relative;
    z-index: 2;
    font-weight: 700;
    color: var(--csp-accent-cyan);
    font-size: 0.875rem;
  }

  /* ── 8. Videos Highlights ── */
  .csp-video-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 28px;
  }

  .csp-video-card {
    background: var(--csp-card-bg);
    border: 1px solid var(--csp-card-border);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s ease;
  }
  .csp-video-card:hover {
    transform: translateY(-4px);
    border-color: rgba(139, 92, 246, 0.4);
  }
  .csp-video-thumb {
    height: 200px;
    background: #1e1e2d;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    background-size: cover;
    background-position: center;
  }
  .csp-play-btn {
    width: 56px; height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--csp-accent-purple), var(--csp-accent-cyan));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
    transition: transform 0.25s ease;
  }
  .csp-video-card:hover .csp-play-btn {
    transform: scale(1.1);
  }
  .csp-video-body {
    padding: 24px;
  }

  /* ── 9. Social Proof Metrics ── */
  .csp-metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
    text-align: center;
  }
  .csp-metric-card {
    background: var(--csp-card-bg);
    border: 1px solid var(--csp-card-border);
    border-radius: 20px;
    padding: 32px 20px;
  }
  .csp-metric-num {
    font-size: 2.75rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--csp-accent-cyan), var(--csp-accent-purple));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    line-height: 1;
    margin-bottom: 8px;
  }
  .csp-metric-label {
    color: var(--csp-text-muted);
    font-size: 0.9375rem;
    font-weight: 500;
  }

  /* ── 10. Newsletter Invitation ── */
  .csp-newsletter-card {
    background: radial-gradient(circle at top right, rgba(0, 201, 224, 0.15), rgba(10, 10, 18, 0.95));
    border: 1px solid rgba(0, 201, 224, 0.25);
    border-radius: 24px;
    padding: 60px 40px;
    text-align: center;
    max-width: 800px;
    margin: 0 auto;
  }
  .csp-newsletter-form {
    display: flex;
    gap: 12px;
    max-width: 520px;
    margin: 32px auto 0;
  }
  .csp-newsletter-input {
    flex: 1;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 50px;
    padding: 14px 24px;
    color: #fff;
    font-size: 0.9375rem;
    outline: none;
    transition: border-color 0.2s ease;
  }
  .csp-newsletter-input:focus {
    border-color: var(--csp-accent-cyan);
  }

  /* ── Media Queries ── */
  @media (max-width: 960px) {
    .csp-hero-slide.active { grid-template-columns: 1fr; }
    .csp-spotlight-grid { grid-template-columns: 1fr; }
    .csp-scholar-card { grid-template-columns: 1fr; text-align: center; }
    .csp-scholar-img { margin: 0 auto; }
    .csp-poll-grid { grid-template-columns: 1fr; }
    .csp-metrics-grid { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 600px) {
    .csp-section-heading { font-size: 1.75rem; }
    .csp-hero-content h1 { font-size: 2.125rem; }
    .csp-timer-grid { gap: 10px; }
    .csp-timer-box { padding: 14px 16px; min-width: 70px; }
    .csp-timer-num { font-size: 1.75rem; }
    .csp-newsletter-form { flex-direction: column; }
    .csp-metrics-grid { grid-template-columns: 1fr; }
  }
</style>
`;
}

function getHomepageHTML(root) {
  return `
<div class="csp-section-wrapper">

  <!-- 1. Live News Ticker -->
  <div class="csp-ticker-bar">
    <div class="csp-ticker-label">Live Updates</div>
    <div class="csp-ticker-content">
      <div class="csp-ticker-item">NEW: <span class="highlight">Essay published on Global Governance</span></div>
      <div class="csp-ticker-item">• Professor Michael Brown joins Conspodium next week</div>
      <div class="csp-ticker-item">• <span class="highlight">Registration now open for Annual Symposium</span></div>
      <div class="csp-ticker-item">• New Podcast Episode Released Today</div>
      <div class="csp-ticker-item">• NEW: <span class="highlight">Essay published on Global Governance</span></div>
      <div class="csp-ticker-item">• Professor Michael Brown joins Conspodium next week</div>
    </div>
  </div>

  <!-- 2. Hero Banner (Dynamic & Rotating Carousel) -->
  <section class="csp-hero">
    <div class="csp-container">
      <div class="csp-hero-carousel">

        <!-- Slide 1 -->
        <div class="csp-hero-slide active" data-slide="0">
          <div class="csp-hero-content">
            <span class="csp-badge">✨ Featured Essay</span>
            <h1>A New Perspective on Global Leadership</h1>
            <p>Exploring how emerging diaspora voices and modern intellectual frameworks are shaping international policy and ethical governance.</p>
            <div class="csp-hero-actions">
              <a href="${root}/stories/index.html" class="csp-btn-primary">Read Featured Essay →</a>
              <a href="${root}/submit-story/index.html" class="csp-btn-secondary">Submit Your Story</a>
            </div>
          </div>
          <div class="csp-hero-card">
            <span class="csp-badge">Scholar Focus</span>
            <h3 style="color:#fff; font-size:1.375rem; font-weight:700; margin:12px 0 8px;">Prof. Samuel Okafor</h3>
            <p style="color:var(--csp-text-muted); font-size:0.875rem; line-height:1.6; margin-bottom:16px;">Chair of Global Affairs, Oxford Institute. Author of 12 groundbreaking treatises on Pan-African governance.</p>
            <a href="${root}/stories/index.html" style="color:var(--csp-accent-cyan); text-decoration:none; font-weight:600; font-size:0.875rem;">View Profile &amp; Works →</a>
          </div>
        </div>

        <!-- Slide 2 -->
        <div class="csp-hero-slide" data-slide="1">
          <div class="csp-hero-content">
            <span class="csp-badge">Exclusive Interview</span>
            <h1>Artificial Intelligence &amp; Ethics in 2026</h1>
            <p>In conversation with leading computer scientists and philosophers on the boundaries of machine intelligence and human dignity.</p>
            <div class="csp-hero-actions">
              <a href="${root}/stories/index.html" class="csp-btn-primary">Watch Interview ▶</a>
              <a href="${root}/contact-us/index.html" class="csp-btn-secondary">Join Discussion</a>
            </div>
          </div>
          <div class="csp-hero-card">
            <span class="csp-badge">Upcoming Webinar</span>
            <h3 style="color:#fff; font-size:1.375rem; font-weight:700; margin:12px 0 8px;">Dr. Amina Vance</h3>
            <p style="color:var(--csp-text-muted); font-size:0.875rem; line-height:1.6; margin-bottom:16px;">Director of AI Ethics Lab, Stockholm. Live discussion scheduled for August 15, 2026.</p>
            <a href="#csp-countdown" style="color:var(--csp-accent-cyan); text-decoration:none; font-weight:600; font-size:0.875rem;">Set Reminder →</a>
          </div>
        </div>

        <!-- Slide 3 -->
        <div class="csp-hero-slide" data-slide="2">
          <div class="csp-hero-content">
            <span class="csp-badge">Intellectual Platform</span>
            <h1>Where Ideas Shape Civilizations</h1>
            <p>Conspodium connects world-class scholars, thought leaders, and passionate readers to foster rigorous intellectual debate.</p>
            <div class="csp-hero-actions">
              <a href="${root}/about-us/index.html" class="csp-btn-primary">Learn About Us</a>
              <a href="${root}/sponsorship/index.html" class="csp-btn-secondary">Partner With Us</a>
            </div>
          </div>
          <div class="csp-hero-card">
            <span class="csp-badge">Community Impact</span>
            <h3 style="color:#fff; font-size:1.375rem; font-weight:700; margin:12px 0 8px;">Over 80 Contributors</h3>
            <p style="color:var(--csp-text-muted); font-size:0.875rem; line-height:1.6; margin-bottom:16px;">Empowering intellectuals across 70 countries with peer-reviewed publications and public symposiums.</p>
            <a href="${root}/advert/index.html" style="color:var(--csp-accent-cyan); text-decoration:none; font-weight:600; font-size:0.875rem;">Explore Opportunities →</a>
          </div>
        </div>

      </div>

      <div class="csp-hero-dots">
        <div class="csp-dot active" onclick="cspSetSlide(0)"></div>
        <div class="csp-dot" onclick="cspSetSlide(1)"></div>
        <div class="csp-dot" onclick="cspSetSlide(2)"></div>
      </div>
    </div>
  </section>

  <!-- 3. Trending Topics -->
  <section class="csp-section" style="padding-top:20px;">
    <div class="csp-container">
      <div class="csp-topics-grid">
        <a href="${root}/stories/index.html" class="csp-topic-pill">🏛 Democracy in Digital Age</a>
        <a href="${root}/stories/index.html" class="csp-topic-pill">🌱 Climate &amp; Social Justice</a>
        <a href="${root}/stories/index.html" class="csp-topic-pill">🤖 AI &amp; Ethics</a>
        <a href="${root}/stories/index.html" class="csp-topic-pill">🌍 African Intellectual Heritage</a>
        <a href="${root}/stories/index.html" class="csp-topic-pill">🎓 Future of Higher Education</a>
        <a href="${root}/stories/index.html" class="csp-topic-pill">🧬 Biotechnology Frontiers</a>
      </div>
    </div>
  </section>

  <!-- 4. Scholar Spotlight & Quote of the Week -->
  <section class="csp-section">
    <div class="csp-container">
      <div class="csp-section-title-wrap">
        <span class="csp-badge">Intellectual Minds</span>
        <h2 class="csp-section-heading">Scholar Spotlight &amp; Quote</h2>
        <p class="csp-section-subtitle">Highlighting renowned thinkers shaping global conversations today.</p>
      </div>

      <div class="csp-spotlight-grid">
        <!-- Scholar Card -->
        <div class="csp-scholar-card">
          <img class="csp-scholar-img" src="${root}/wp-content/uploads/2026/01/CONSPODIUM-NEW-2-300x300.png" alt="Prof. Jane Doe">
          <div class="csp-scholar-info">
            <h3>Professor Jane Doe</h3>
            <div class="role">University of Oxford • Senior Research Fellow</div>
            <p>Specializing in Artificial Intelligence and the Future of Human Knowledge. Featured upcoming paper: <i>"Epistemology in the Age of Autonomous Systems"</i>.</p>
            <a href="${root}/stories/index.html" class="csp-btn-primary" style="padding:10px 22px; font-size:0.875rem;">Set Reminder &amp; Follow</a>
          </div>
        </div>

        <!-- Quote of the Week -->
        <div class="csp-quote-card">
          <div class="csp-quote-mark">“</div>
          <div class="csp-quote-text">Education is the passport to the future, for tomorrow belongs to those who prepare for it today.</div>
          <div class="csp-quote-author">— Malcolm X • Quote of the Week</div>
        </div>
      </div>
    </div>
  </section>

  <!-- 5. Past • Present • Upcoming Tabs -->
  <section class="csp-section">
    <div class="csp-container">
      <div class="csp-section-title-wrap">
        <span class="csp-badge">Curated Archives</span>
        <h2 class="csp-section-heading">Explore By Timeline</h2>
        <p class="csp-section-subtitle">Browse through landmark essays, ongoing discussions, and scheduled releases.</p>
      </div>

      <div class="csp-tabs-header">
        <button class="csp-tab-btn active" onclick="cspSwitchTab('present')">Present • Active Debates</button>
        <button class="csp-tab-btn" onclick="cspSwitchTab('upcoming')">Upcoming • Scheduled</button>
        <button class="csp-tab-btn" onclick="cspSwitchTab('past')">Past • Most Read</button>
      </div>

      <!-- PRESENT TAB -->
      <div id="tab-present" class="csp-tab-content active">
        <div class="csp-card">
          <div>
            <div class="csp-card-tag">Trending Now</div>
            <h3 class="csp-card-title">Reimagining Global Governance in a Multipolar World</h3>
            <p class="csp-card-desc">An examination of diplomatic shifts and coalition dynamics among emerging global economies.</p>
          </div>
          <div class="csp-card-footer">
            <span>By Dr. Aris Thorne</span>
            <span>Active Debate</span>
          </div>
        </div>

        <div class="csp-card">
          <div>
            <div class="csp-card-tag">Ethics &amp; Philosophy</div>
            <h3 class="csp-card-title">Algorithmic Bias and Sovereign Institutions</h3>
            <p class="csp-card-desc">How automated decision systems impact civil rights and public trust across judicial systems.</p>
          </div>
          <div class="csp-card-footer">
            <span>By Prof. Elena Rostova</span>
            <span>Published Today</span>
          </div>
        </div>

        <div class="csp-card">
          <div>
            <div class="csp-card-tag">Economic Theory</div>
            <h3 class="csp-card-title">Sustainable Finance in Developing Economies</h3>
            <p class="csp-card-desc">Strategies for green capital deployment without sacrificing industrial modernization.</p>
          </div>
          <div class="csp-card-footer">
            <span>By Michael Adeleke</span>
            <span>Editor's Pick</span>
          </div>
        </div>
      </div>

      <!-- UPCOMING TAB -->
      <div id="tab-upcoming" class="csp-tab-content">
        <div class="csp-card">
          <div>
            <div class="csp-card-tag">August 2026</div>
            <h3 class="csp-card-title">The Future of Universities in the AI Era</h3>
            <p class="csp-card-desc">Featuring Prof. David Anderson. A comprehensive look into higher education models.</p>
          </div>
          <div class="csp-card-footer">
            <span>Scheduled Symposium</span>
            <span style="color:var(--csp-accent-cyan);">Coming Soon</span>
          </div>
        </div>

        <div class="csp-card">
          <div>
            <div class="csp-card-tag">September 2026</div>
            <h3 class="csp-card-title">African Intellectual Traditions &amp; Modern Philosophy</h3>
            <p class="csp-card-desc">A panel discussion on decolonizing knowledge systems and celebrating oral histories.</p>
          </div>
          <div class="csp-card-footer">
            <span>Guest Panel</span>
            <span style="color:var(--csp-accent-cyan);">Set Reminder</span>
          </div>
        </div>
      </div>

      <!-- PAST TAB -->
      <div id="tab-past" class="csp-tab-content">
        <div class="csp-card">
          <div>
            <div class="csp-card-tag">50k+ Reads</div>
            <h3 class="csp-card-title">The Renaissance of Diaspora Journalism</h3>
            <p class="csp-card-desc">How independent publications are challenging traditional media dominance.</p>
          </div>
          <div class="csp-card-footer">
            <span>By Sarah Williams</span>
            <span>Landmark Essay</span>
          </div>
        </div>

        <div class="csp-card">
          <div>
            <div class="csp-card-tag">Archive Spotlight</div>
            <h3 class="csp-card-title">Technological Sovereignty: A Primer</h3>
            <p class="csp-card-desc">Analyzing country-level digital infrastructure control and cyber security policies.</p>
          </div>
          <div class="csp-card-footer">
            <span>By Prof. K. Patel</span>
            <span>Most Cited 2025</span>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- 6. Countdown to Next Feature Event -->
  <section class="csp-section" id="csp-countdown">
    <div class="csp-container">
      <div class="csp-countdown-banner">
        <span class="csp-badge">⚡ Next Live Discussion</span>
        <h2 style="font-size:2rem; font-weight:800; color:#fff; margin:12px 0;">"The Future of Democracy"</h2>
        <p style="color:var(--csp-text-muted); max-width:540px; margin:0 auto;">Featuring Professor Sarah Williams. Join hundreds of international scholars live online.</p>

        <div class="csp-timer-grid">
          <div class="csp-timer-box">
            <div class="csp-timer-num" id="timer-days">05</div>
            <div class="csp-timer-label">Days</div>
          </div>
          <div class="csp-timer-box">
            <div class="csp-timer-num" id="timer-hours">14</div>
            <div class="csp-timer-label">Hours</div>
          </div>
          <div class="csp-timer-box">
            <div class="csp-timer-num" id="timer-mins">23</div>
            <div class="csp-timer-label">Minutes</div>
          </div>
          <div class="csp-timer-box">
            <div class="csp-timer-num" id="timer-secs">45</div>
            <div class="csp-timer-label">Seconds</div>
          </div>
        </div>

        <a href="${root}/contact-us/index.html" class="csp-btn-primary">Register Free For Discussion →</a>
      </div>
    </div>
  </section>

  <!-- 7. Readers' Poll & Corner -->
  <section class="csp-section">
    <div class="csp-container">
      <div class="csp-section-title-wrap">
        <span class="csp-badge">Community Voice</span>
        <h2 class="csp-section-heading">Weekly Readers' Poll</h2>
        <p class="csp-section-subtitle">Cast your vote and help choose Conspodium's next major research theme.</p>
      </div>

      <div class="csp-poll-grid">
        <!-- Interactive Poll Box -->
        <div class="csp-poll-box">
          <h3 style="color:#fff; font-size:1.25rem; font-weight:700; margin-bottom:20px;">Which topic should Conspodium explore next?</h3>

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

          <div class="csp-poll-option" onclick="cspVote(this, 12)">
            <div class="csp-poll-bar" style="width: 12%;"></div>
            <span class="csp-poll-text">🌍 African Economic Development</span>
            <span class="csp-poll-percent">12%</span>
          </div>

          <div style="margin-top:20px; font-size:0.8125rem; color:var(--csp-text-muted); display:flex; justify-content:space-between; align-items:center;">
            <span>1,420 total votes cast</span>
            <span style="color:var(--csp-accent-cyan);">Poll closes in 2 days</span>
          </div>
        </div>

        <!-- Suggestion / Question Box -->
        <div class="csp-poll-box" style="display:flex; flex-direction:column; justify-content:space-between;">
          <div>
            <h3 style="color:#fff; font-size:1.25rem; font-weight:700; margin-bottom:12px;">Readers' Corner</h3>
            <p style="color:var(--csp-text-muted); font-size:0.9375rem; line-height:1.6; margin-bottom:20px;">Have a question or topic suggestion for our upcoming scholar discussions? Submit directly to our editorial board.</p>

            <textarea placeholder="Write your topic suggestion or scholar question here..." style="width:100%; height:120px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); border-radius:12px; padding:14px; color:#fff; font-family:var(--csp-font); font-size:0.875rem; resize:none; outline:none;" id="csp-user-idea"></textarea>
          </div>

          <button onclick="cspSubmitIdea()" class="csp-btn-primary" style="width:100%; justify-content:center; margin-top:16px;">Submit Suggestion →</button>
        </div>
      </div>
    </div>
  </section>

  <!-- 8. Short Video Highlights -->
  <section class="csp-section">
    <div class="csp-container">
      <div class="csp-section-title-wrap">
        <span class="csp-badge">Multimedia</span>
        <h2 class="csp-section-heading">In Conversation With...</h2>
        <p class="csp-section-subtitle">Short 30–90 second clips and scholar interviews.</p>
      </div>

      <div class="csp-video-grid">
        <div class="csp-video-card">
          <div class="csp-video-thumb" style="background-image: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.7)), url('${root}/wp-content/uploads/2026/01/CONSPODIUM-NEW-3-scaled-1024x315.png');">
            <div class="csp-play-btn">▶</div>
          </div>
          <div class="csp-video-body">
            <div class="csp-card-tag">Interview • 1:30 min</div>
            <h4 style="color:#fff; font-size:1.125rem; font-weight:700; margin-bottom:8px;">Why Democracy Needs Better Conversations</h4>
            <p style="color:var(--csp-text-muted); font-size:0.875rem; line-height:1.5;">Prof. John Smith discusses civil discourse in modern media.</p>
          </div>
        </div>

        <div class="csp-video-card">
          <div class="csp-video-thumb" style="background-image: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.7)), url('${root}/wp-content/uploads/2026/01/CONSPODIUM-NEW-2-300x300.png');">
            <div class="csp-play-btn">▶</div>
          </div>
          <div class="csp-video-body">
            <div class="csp-card-tag">Behind The Scenes • 0:45 min</div>
            <h4 style="color:#fff; font-size:1.125rem; font-weight:700; margin-bottom:8px;">Inside Conspodium's Editorial Process</h4>
            <p style="color:var(--csp-text-muted); font-size:0.875rem; line-height:1.5;">A look at how we select peer-reviewed diaspora essays.</p>
          </div>
        </div>

        <div class="csp-video-card">
          <div class="csp-video-thumb" style="background-image: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.7)), url('${root}/wp-content/uploads/2026/01/CONSPODIUM-NEW-3-scaled-1024x315.png');">
            <div class="csp-play-btn">▶</div>
          </div>
          <div class="csp-video-body">
            <div class="csp-card-tag">Summary • 1:15 min</div>
            <h4 style="color:#fff; font-size:1.125rem; font-weight:700; margin-bottom:8px;">The Future of African Technology Hubs</h4>
            <p style="color:var(--csp-text-muted); font-size:0.875rem; line-height:1.5;">Quick insights from Lagos and Nairobi innovation labs.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- 9. Social Proof & Community Metrics -->
  <section class="csp-section">
    <div class="csp-container">
      <div class="csp-metrics-grid">
        <div class="csp-metric-card">
          <div class="csp-metric-num">500+</div>
          <div class="csp-metric-label">Published Essays</div>
        </div>
        <div class="csp-metric-card">
          <div class="csp-metric-num">80+</div>
          <div class="csp-metric-label">Distinguished Contributors</div>
        </div>
        <div class="csp-metric-card">
          <div class="csp-metric-num">70</div>
          <div class="csp-metric-label">Countries Reached</div>
        </div>
        <div class="csp-metric-card">
          <div class="csp-metric-num">2M+</div>
          <div class="csp-metric-label">Reader Impressions</div>
        </div>
      </div>
    </div>
  </section>

  <!-- 10. Newsletter Invitation & Closing Call-to-Action -->
  <section class="csp-section">
    <div class="csp-container">
      <div class="csp-newsletter-card">
        <span class="csp-badge">Stay Connected</span>
        <h2 style="font-size:2.25rem; font-weight:800; color:#fff; margin:16px 0 12px;">Never Miss a Great Idea</h2>
        <p style="color:var(--csp-text-muted); font-size:1.0625rem; max-width:540px; margin:0 auto; line-height:1.6;">Receive exclusive essays, scholar interviews, and event invitations directly to your inbox before everyone else.</p>

        <form class="csp-newsletter-form" onsubmit="event.preventDefault(); alert('Thank you for subscribing to Conspodium!');">
          <input type="email" class="csp-newsletter-input" placeholder="Enter your email address..." required>
          <button type="submit" class="csp-btn-primary" style="padding:14px 28px;">Subscribe Free</button>
        </form>
      </div>

      <div style="text-align:center; margin-top:64px;">
        <h3 style="font-size:1.75rem; font-weight:800; color:#fff; margin-bottom:12px;">Ideas Shape Civilizations. Conversations Change the World.</h3>
        <p style="color:var(--csp-text-muted); font-size:1.125rem; margin-bottom:24px;">Read • Reflect • Debate • Inspire</p>
        <a href="${root}/stories/index.html" class="csp-btn-primary">Join The Conspodium Community →</a>
      </div>
    </div>
  </section>

</div>

<script id="csp-homepage-scripts">
  // Hero Carousel
  let cspCurrentSlide = 0;
  const cspSlides = document.querySelectorAll('.csp-hero-slide');
  const cspDots = document.querySelectorAll('.csp-dot');

  function cspSetSlide(index) {
    cspSlides.forEach(s => s.classList.remove('active'));
    cspDots.forEach(d => d.classList.remove('active'));
    cspCurrentSlide = index;
    if (cspSlides[index]) cspSlides[index].classList.add('active');
    if (cspDots[index]) cspDots[index].classList.add('active');
  }

  setInterval(() => {
    cspCurrentSlide = (cspCurrentSlide + 1) % cspSlides.length;
    cspSetSlide(cspCurrentSlide);
  }, 7000);

  // Tabs Switcher
  function cspSwitchTab(tabName) {
    document.querySelectorAll('.csp-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.csp-tab-content').forEach(content => content.classList.remove('active'));
    
    event.target.classList.add('active');
    const targetContent = document.getElementById('tab-' + tabName);
    if (targetContent) targetContent.classList.add('active');
  }

  // Poll Vote Interaction
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

  // Real-time Countdown Timer
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
}

async function main() {
  let html = await readFile(INDEX_PATH, 'utf8');

  // Preserve header block
  const headerEndMark = '<!-- CSP_HEADER_BLOCK_END -->';
  const headerEndIdx = html.indexOf(headerEndMark);
  
  if (headerEndIdx === -1) {
    console.error('Header block end marker not found in index.html!');
    return;
  }

  const headerPart = html.slice(0, headerEndIdx + headerEndMark.length);

  // Preserve footer section if present
  let footerPart = '';
  const footerIdx = html.indexOf('<footer');
  if (footerIdx !== -1) {
    footerPart = html.slice(footerIdx);
  } else {
    footerPart = '</body></html>';
  }

  // Generate CSS & HTML
  const css = getHomepageCSS();
  const content = getHomepageHTML('.');

  // Combine
  const newHTML = headerPart + '\n' + css + '\n' + content + '\n' + footerPart;

  await writeFile(INDEX_PATH, newHTML, 'utf8');
  console.log('Successfully rebuilt homepage structure into output/index.html!');
}

main().catch(console.error);
