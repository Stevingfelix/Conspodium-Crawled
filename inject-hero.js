/**
 * inject-hero.js
 *
 * Replaces the static Elementor hero (375eb214) with a dynamic rotating
 * banner as described in the Suggested Homepage Structure document:
 *   - Full-width, full-height slides
 *   - 4 rotating messages with "Read More" / CTA buttons
 *   - Auto-advance every 6 seconds with crossfade transition
 *   - Navigation arrows + dot indicators
 *   - Preserves existing background images from the crawl
 */

import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const FILE = fileURLToPath(new URL('./output2/index.html', import.meta.url));

const HERO_START = '<!-- CSP_HERO_START -->';
const HERO_END   = '<!-- CSP_HERO_END -->';

// ── Image paths (all confirmed to exist locally) ─────────────────────────────
const IMG1 = './wp-content/uploads/2026/01/girls-walk-along-streets-city-scaled.jpg';
const IMG2 = './wp-content/uploads/2026/01/portrait-two-friends-holding-each-other-city-scaled.jpg';
const IMG3 = './wp-content/uploads/2026/01/couple-using-technology-while-traveling-city-scaled.jpg';

const HERO_HTML = `${HERO_START}
<section id="csp-hero" aria-label="Featured content banner">
<style>
/* ── Reset for hero section ── */
#csp-hero,#csp-hero *{box-sizing:border-box;margin:0;padding:0;}
#csp-hero{position:relative;width:100%;height:100vh;min-height:600px;max-height:920px;overflow:hidden;font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;}

/* ── Slides ── */
.csp-hero-slide{
  position:absolute;inset:0;
  display:flex;align-items:center;justify-content:center;
  padding:0 8% 10% 8%;
  opacity:0;transition:opacity 1.1s cubic-bezier(0.4,0,0.2,1);
  pointer-events:none;
}
.csp-hero-slide.active{opacity:1;pointer-events:auto;}

/* ── Background image layer ── */
.csp-hero-bg{
  position:absolute;inset:0;
  background-size:cover;background-position:center center;
  transform:scale(1.06);
  transition:transform 7s cubic-bezier(0.4,0,0.2,1);
}
.csp-hero-slide.active .csp-hero-bg{transform:scale(1);}

/* ── Gradient overlay ── */
.csp-hero-overlay{
  position:absolute;inset:0;
  background:linear-gradient(
    180deg,
    rgba(5,13,26,0.25) 0%,
    rgba(5,13,26,0.55) 45%,
    rgba(5,13,26,0.88) 100%
  );
}

/* ── Content ── */
.csp-hero-content{position:relative;z-index:2;max-width:760px;text-align:center;}
.csp-hero-eyebrow{
  display:inline-flex;align-items:center;gap:12px;
  font-size:0.72rem;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;
  color:#fff;
  background:linear-gradient(135deg,rgba(0,174,254,0.35),rgba(183,31,113,0.25));
  border:1px solid rgba(0,174,254,0.5);
  border-radius:50px;padding:10px 36px;margin-bottom:32px;
  backdrop-filter:blur(8px);
  -webkit-backdrop-filter:blur(8px);
  transform:translateY(24px);opacity:0;
  transition:transform 0.7s 0.2s ease,opacity 0.7s 0.2s ease;
}
.csp-hero-eyebrow-dot{width:8px;height:8px;border-radius:50%;background:#00AEFE;box-shadow:0 0 10px #00AEFE;flex-shrink:0;animation:csp-hero-blink 1.4s infinite;}
@keyframes csp-hero-blink{0%,100%{opacity:1;}50%{opacity:0.2;}}

.csp-hero-slide.active .csp-hero-eyebrow{transform:translateY(0);opacity:1;}

.csp-hero-title{
  font-family:'Merriweather',Georgia,serif;
  font-size:clamp(2rem,5vw,3.6rem);
  font-weight:400;line-height:1.18;color:#fff;
  margin-bottom:28px;
  transform:translateY(32px);opacity:0;
  transition:transform 0.75s 0.35s ease,opacity 0.75s 0.35s ease;
}
.csp-hero-slide.active .csp-hero-title{transform:translateY(0);opacity:1;}
.csp-hero-title em{font-style:normal;background:linear-gradient(135deg,#00AEFE,#B71F71);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}

.csp-hero-sub{
  font-size:clamp(0.9rem,1.8vw,1.1rem);color:rgba(255,255,255,0.72);line-height:1.65;
  margin-bottom:64px;max-width:600px;margin-left:auto;margin-right:auto;
  transform:translateY(28px);opacity:0;
  transition:transform 0.75s 0.5s ease,opacity 0.75s 0.5s ease;
}
.csp-hero-slide.active .csp-hero-sub{transform:translateY(0);opacity:1;}

.csp-hero-btns{
  display:flex;gap:18px;flex-wrap:wrap;justify-content:center;
  transform:translateY(24px);opacity:0;
  transition:transform 0.75s 0.65s ease,opacity 0.75s 0.65s ease;
}
.csp-hero-slide.active .csp-hero-btns{transform:translateY(0);opacity:1;}

.csp-hero-btn-primary{
  display:inline-flex;align-items:center;justify-content:center;gap:12px;
  background:linear-gradient(110deg,#00AEFE 0%,#B71F71 100%);
  color:#fff;text-decoration:none;font-weight:600;
  font-family:'Merriweather',Georgia,serif;
  font-size:0.95rem;padding:16px 44px;border-radius:15px;
  min-width:200px;
  box-shadow:0 6px 28px rgba(0,174,254,0.35);
  transition:transform 0.22s ease,box-shadow 0.22s ease,filter 0.22s ease;
  white-space:nowrap;
}
.csp-hero-btn-primary:hover{
  background:linear-gradient(110deg,#B71F71 0%,#00AEFE 100%);
  transform:translateY(-3px);
  box-shadow:0 10px 36px rgba(0,174,254,0.5);
  filter:brightness(1.08);
}

.csp-hero-btn-secondary{
  display:inline-flex;align-items:center;justify-content:center;gap:12px;
  background:rgba(255,255,255,0.08);border:2px solid rgba(255,255,255,0.45);
  color:#fff;text-decoration:none;font-weight:500;
  font-family:'Merriweather',Georgia,serif;
  font-size:0.95rem;padding:15px 44px;border-radius:15px;
  min-width:200px;
  backdrop-filter:blur(8px);
  -webkit-backdrop-filter:blur(8px);
  transition:background 0.22s ease,border-color 0.22s ease,transform 0.22s ease;
  white-space:nowrap;
}
.csp-hero-btn-secondary:hover{
  background:rgba(255,255,255,0.18);
  border-color:rgba(255,255,255,0.85);
  transform:translateY(-2px);
}

/* ── Navigation arrows ── */
.csp-hero-arrow{
  position:absolute;top:50%;transform:translateY(-50%);
  z-index:10;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);
  border-radius:50%;width:48px;height:48px;display:flex;align-items:center;justify-content:center;
  cursor:pointer;color:#fff;transition:background 0.2s,border-color 0.2s,transform 0.2s;
  backdrop-filter:blur(6px);
}
.csp-hero-arrow:hover{background:rgba(255,255,255,0.2);border-color:rgba(255,255,255,0.5);transform:translateY(-50%) scale(1.1);}
#csp-hero-prev{left:28px;}
#csp-hero-next{right:28px;}
@media(max-width:600px){.csp-hero-arrow{width:38px;height:38px;}.csp-hero-arrow svg{width:16px;height:16px;} #csp-hero-prev{left:12px;} #csp-hero-next{right:12px;}}

/* ── Dot indicators ── */
.csp-hero-dots{
  position:absolute;bottom:32px;left:50%;transform:translateX(-50%);
  z-index:10;display:flex;gap:10px;align-items:center;
}
.csp-hero-dot{
  width:7px;height:7px;border-radius:50%;
  background:rgba(255,255,255,0.35);cursor:pointer;
  transition:background 0.25s,width 0.25s;border:none;padding:0;
}
.csp-hero-dot.active{background:#00AEFE;width:24px;border-radius:4px;}

/* ── Progress bar ── */
.csp-hero-progress{
  position:absolute;bottom:0;left:0;height:3px;width:0%;
  background:linear-gradient(90deg,#00AEFE,#B71F71);
  transition:width 6s linear;z-index:11;
}

/* ── Slide counter ── */
.csp-hero-counter{
  position:absolute;right:32px;bottom:36px;z-index:10;
  font-size:0.78rem;font-weight:600;color:rgba(255,255,255,0.5);
  letter-spacing:0.08em;
}
@media(max-width:600px){
  .csp-hero-slide{padding:0 5% 18% 5%;}
  .csp-hero-counter{display:none;}
}
</style>

<!-- Slide 1: This Week's Featured Essay -->
<div class="csp-hero-slide active" data-slide="0">
  <div class="csp-hero-bg" style="background-image:url('${IMG1}');"></div>
  <div class="csp-hero-overlay"></div>
  <div class="csp-hero-content">
    <div class="csp-hero-eyebrow"><span class="csp-hero-eyebrow-dot"></span>This Week's Featured Essay</div>
    <h1 class="csp-hero-title">The Digital Sovereignty Crisis:<br><em>Who Controls Africa's Data Future?</em></h1>
    <p class="csp-hero-sub">A landmark investigation into how global tech giants are shaping African digital policy — and what diaspora leaders are doing to fight back.</p>
    <div class="csp-hero-btns">
      <a href="./stories/" class="csp-hero-btn-primary">Read More <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
      <a href="./stories/" class="csp-hero-btn-secondary">Explore All Essays</a>
    </div>
  </div>
</div>

<!-- Slide 2: Coming Soon -->
<div class="csp-hero-slide" data-slide="1">
  <div class="csp-hero-bg" style="background-image:url('${IMG2}');"></div>
  <div class="csp-hero-overlay"></div>
  <div class="csp-hero-content">
    <div class="csp-hero-eyebrow"><span class="csp-hero-eyebrow-dot"></span>Coming Soon</div>
    <h1 class="csp-hero-title">A New Perspective on<br><em>Global Leadership</em></h1>
    <p class="csp-hero-sub">Prof. Amara Diallo of the London School of Economics shares his groundbreaking framework for African-led multilateral governance in the digital age.</p>
    <div class="csp-hero-btns">
      <a href="./contact-us/" class="csp-hero-btn-primary">Set Reminder <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M8 1v14M1 8h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></a>
      <a href="./about-us/" class="csp-hero-btn-secondary">Meet Our Scholars</a>
    </div>
  </div>
</div>

<!-- Slide 3: Exclusive Interview -->
<div class="csp-hero-slide" data-slide="2">
  <div class="csp-hero-bg" style="background-image:url('${IMG3}');"></div>
  <div class="csp-hero-overlay"></div>
  <div class="csp-hero-content">
    <div class="csp-hero-eyebrow"><span class="csp-hero-eyebrow-dot"></span>Exclusive Interview</div>
    <h1 class="csp-hero-title">In Conversation with<br><em>Dr. Ngozi Eze</em></h1>
    <p class="csp-hero-sub">"Biotechnology is the next frontier of African liberation. We must own our science, our data, and our story." — Dr. Ngozi Eze, MIT Media Lab.</p>
    <div class="csp-hero-btns">
      <a href="./stories/" class="csp-hero-btn-primary">Read Transcript <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
      <a href="./stories/" class="csp-hero-btn-secondary">Watch Interview</a>
    </div>
  </div>
</div>

<!-- Slide 4: Join the Conversation -->
<div class="csp-hero-slide" data-slide="3">
  <div class="csp-hero-bg" style="background-image:url('${IMG1}');background-position:top center;"></div>
  <div class="csp-hero-overlay" style="background:linear-gradient(180deg,rgba(5,13,26,0.4) 0%,rgba(5,13,26,0.65) 40%,rgba(3,18,42,0.95) 100%);"></div>
  <div class="csp-hero-content">
    <div class="csp-hero-eyebrow"><span class="csp-hero-eyebrow-dot"></span>Join the Conversation</div>
    <h1 class="csp-hero-title">Your Ideas Matter —<br><em>Share Your Story</em> With the World</h1>
    <p class="csp-hero-sub">Conspodium publishes the stories that matter most to the global diaspora. Whether you're a scholar, activist, or community leader — your voice belongs here.</p>
    <div class="csp-hero-btns">
      <a href="./submit-story/" class="csp-hero-btn-primary">Submit Your Story <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
      <a href="./about-us/" class="csp-hero-btn-secondary">Learn More</a>
    </div>
  </div>
</div>

<!-- Navigation -->
<button id="csp-hero-prev" class="csp-hero-arrow" aria-label="Previous slide">
  <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M12 5l-5 5 5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
</button>
<button id="csp-hero-next" class="csp-hero-arrow" aria-label="Next slide">
  <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M8 5l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
</button>

<div class="csp-hero-dots" id="csp-hero-dots">
  <button class="csp-hero-dot active" data-to="0" aria-label="Slide 1"></button>
  <button class="csp-hero-dot" data-to="1" aria-label="Slide 2"></button>
  <button class="csp-hero-dot" data-to="2" aria-label="Slide 3"></button>
  <button class="csp-hero-dot" data-to="3" aria-label="Slide 4"></button>
</div>

<div class="csp-hero-counter"><span id="csp-hero-cur">01</span> / <span id="csp-hero-tot">04</span></div>
<div class="csp-hero-progress" id="csp-hero-progress"></div>

<script>
(function(){
  var DURATION = 6000;
  var slides  = document.querySelectorAll('.csp-hero-slide');
  var dots    = document.querySelectorAll('.csp-hero-dot');
  var cur     = 0;
  var timer   = null;
  var prog    = document.getElementById('csp-hero-progress');
  var curEl   = document.getElementById('csp-hero-cur');
  var n       = slides.length;

  function pad(x){ return x < 10 ? '0'+x : ''+x; }

  function goTo(idx) {
    slides[cur].classList.remove('active');
    dots[cur].classList.remove('active');
    cur = (idx + n) % n;
    slides[cur].classList.add('active');
    dots[cur].classList.add('active');
    if(curEl) curEl.textContent = pad(cur+1);
    startProgress();
  }

  function startProgress(){
    if(prog){
      prog.style.transition = 'none';
      prog.style.width = '0%';
      requestAnimationFrame(function(){
        requestAnimationFrame(function(){
          prog.style.transition = 'width '+DURATION+'ms linear';
          prog.style.width = '100%';
        });
      });
    }
    clearInterval(timer);
    timer = setInterval(function(){ goTo(cur+1); }, DURATION);
  }

  // Dot nav
  dots.forEach(function(d){
    d.addEventListener('click', function(){ goTo(parseInt(d.dataset.to)); });
  });

  // Arrow nav
  var prev = document.getElementById('csp-hero-prev');
  var next = document.getElementById('csp-hero-next');
  if(prev) prev.addEventListener('click', function(){ goTo(cur-1); });
  if(next) next.addEventListener('click', function(){ goTo(cur+1); });

  // Keyboard
  document.addEventListener('keydown', function(e){
    if(e.key==='ArrowLeft') goTo(cur-1);
    if(e.key==='ArrowRight') goTo(cur+1);
  });

  // Pause on hover
  var hero = document.getElementById('csp-hero');
  if(hero){
    hero.addEventListener('mouseenter', function(){ clearInterval(timer); if(prog) prog.style.animationPlayState='paused'; });
    hero.addEventListener('mouseleave', function(){ startProgress(); });
  }

  // Touch swipe
  var touchX = null;
  if(hero){
    hero.addEventListener('touchstart', function(e){ touchX = e.touches[0].clientX; }, {passive:true});
    hero.addEventListener('touchend', function(e){
      if(touchX===null) return;
      var dx = e.changedTouches[0].clientX - touchX;
      if(Math.abs(dx) > 50){ goTo(dx < 0 ? cur+1 : cur-1); }
      touchX = null;
    }, {passive:true});
  }

  startProgress();
})();
</script>
</section>
<!-- CSP_HERO_END -->`;

// ── CSS to hide the original Elementor hero ──────────────────────────────────
const HIDE_OLD_HERO = `<style id="csp-hide-old-hero">
/* Hide the original static Elementor hero — replaced by rotating banner above */
.elementor-6 .elementor-element.elementor-element-375eb214 { display: none !important; }
</style>`;

let html = await readFile(FILE, 'utf8');

// Strip any existing injected hero
const si = html.indexOf(HERO_START);
const ei = html.indexOf(HERO_END);
if (si !== -1 && ei !== -1) {
  html = html.slice(0, si) + html.slice(ei + HERO_END.length);
  console.log('Removed old csp-hero block');
}

// Also remove old hide style if present
html = html.replace(/<style id="csp-hide-old-hero">[\s\S]*?<\/style>/g, '');

// Inject hide style before </head>
html = html.replace('</head>', HIDE_OLD_HERO + '\n</head>');

// Inject new hero BEFORE the old Elementor hero section (375eb214)
const anchor = 'elementor-element-375eb214';
const idx = html.indexOf(anchor);
if (idx === -1) {
  console.error('Could not find hero anchor!');
  process.exit(1);
}
// Walk back to find the opening <
let start = idx;
while (start > 0 && html[start] !== '<') start--;

html = html.slice(0, start) + HERO_HTML + '\n' + html.slice(start);

await writeFile(FILE, html, 'utf8');
console.log('✅ Dynamic rotating hero injected into output2/index.html');
console.log('   Hard-refresh (Cmd+Shift+R) at http://localhost:8080');
