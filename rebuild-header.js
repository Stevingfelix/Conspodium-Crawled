/**
 * rebuild-header.js
 *
 * Clean, reliable script to rebuild the header across all output HTML files.
 */

import { readFile, writeFile, readdir, stat } from 'node:fs/promises';
import { join, relative, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const OUT = fileURLToPath(new URL('./output/', import.meta.url));

async function walk(dir) {
  const results = [];
  for (const name of await readdir(dir)) {
    const full = join(dir, name);
    const s = await stat(full);
    if (s.isDirectory()) results.push(...(await walk(full)));
    else results.push(full);
  }
  return results;
}

function toRoot(file) {
  let r = relative(dirname(file), OUT).split('\\').join('/');
  if (!r) r = '.';
  if (!r.startsWith('.')) r = './' + r;
  return r;
}

function getActivePath(file) {
  const rel = relative(OUT, file).split('\\').join('/');
  if (rel === 'index.html') return '/';
  const dir = rel.replace(/\/?index\.html$/, '');
  return '/' + dir + '/';
}

const NAV_LINKS = [
  { label: 'Home',        href: '/' },
  { label: 'About Us',   href: '/about-us/' },
  { label: 'Contact Us', href: '/contact-us/' },
  { label: 'Stories',    href: '/stories/' },
  { label: 'Sponsorship',href: '/sponsorship/' },
  { label: 'Advert',     href: '/advert/' },
];

function buildHeader(root, activePath) {
  const logoSrc    = `${root}/wp-content/uploads/2026/01/CONSPODIUM-NEW-3-scaled-300x92.png`;
  const homeHref   = root + '/index.html';
  const submitHref = root + '/submit-story/index.html';

  const navItems = NAV_LINKS.map(({ label, href }) => {
    const localHref = href === '/' ? root + '/index.html' : root + href + 'index.html';
    const isActive  = (activePath === href) || (href !== '/' && activePath.startsWith(href));
    return `<a href="${localHref}" class="csp-nav-link${isActive ? ' csp-nav-active' : ''}">${label}</a>`;
  }).join('\n        ');

  return `<!-- CSP_HEADER_BLOCK_START -->
<style>
  #csp-header, #csp-header *, #csp-drawer, #csp-drawer * { box-sizing: border-box; margin: 0; padding: 0; }

  #csp-header {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 99999;
    background: rgba(10, 10, 18, 0.96);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid rgba(255,255,255,0.08);
    height: 76px;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    padding: 0 40px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  }

  #csp-logo {
    grid-column: 1;
    justify-self: start;
    line-height: 0;
    text-decoration: none;
    display: flex;
    align-items: center;
  }
  #csp-logo img {
    height: 42px;
    width: auto;
    display: block;
    object-fit: contain;
  }

  #csp-nav {
    grid-column: 2;
    justify-self: center;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .csp-nav-link {
    color: rgba(255,255,255,0.85);
    text-decoration: none;
    font-size: 0.9375rem;
    font-weight: 500;
    padding: 10px 22px;
    border-radius: 6px;
    position: relative;
    letter-spacing: 0.01em;
    transition: color 0.2s ease, background 0.2s ease;
    white-space: nowrap;
  }
  .csp-nav-link::after {
    content: '';
    position: absolute;
    bottom: 4px; left: 22px; right: 22px;
    height: 2px;
    background: #00c9e0;
    transform: scaleX(0);
    transform-origin: center;
    transition: transform 0.2s ease;
    border-radius: 2px;
  }
  .csp-nav-link:hover { color: #ffffff; background: rgba(255,255,255,0.05); }
  .csp-nav-link:hover::after { transform: scaleX(1); }
  .csp-nav-active { color: #00c9e0 !important; font-weight: 600; }
  .csp-nav-active::after { transform: scaleX(1) !important; }

  #csp-cta {
    grid-column: 3;
    justify-self: end;
  }

  .csp-submit-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #8b5cf6 0%, #06b6d4 100%);
    color: #ffffff !important;
    text-decoration: none !important;
    font-size: 0.9375rem;
    font-weight: 600;
    padding: 12px 28px;
    border-radius: 50px;
    letter-spacing: 0.02em;
    transition: all 0.2s ease;
    white-space: nowrap;
    box-shadow: 0 4px 20px rgba(139,92,246,0.4);
  }
  .csp-submit-btn:hover {
    opacity: 0.95;
    transform: translateY(-2px);
    box-shadow: 0 6px 26px rgba(139,92,246,0.55);
  }
  .csp-submit-btn svg { flex-shrink: 0; }

  #csp-burger {
    display: none;
    grid-column: 3;
    justify-self: end;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 8px;
    cursor: pointer;
    padding: 8px 10px;
    color: #ffffff;
    transition: all 0.2s ease;
  }
  #csp-burger:hover { background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.3); }

  @media (max-width: 960px) {
    #csp-header {
      display: flex;
      justify-content: space-between;
      padding: 0 20px;
      height: 68px;
    }
    #csp-logo img {
      height: 36px;
    }
    #csp-nav, #csp-cta {
      display: none !important;
    }
    #csp-burger {
      display: flex;
      align-items: center;
      justify-content: center;
    }
  }

  #csp-drawer {
    position: fixed;
    top: 68px; left: 0; right: 0;
    background: rgba(10, 10, 18, 0.98);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(255,255,255,0.1);
    box-shadow: 0 20px 40px rgba(0,0,0,0.7);
    z-index: 99998;
    transform: translateY(-100%);
    opacity: 0;
    visibility: hidden;
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease, visibility 0.3s;
  }
  #csp-drawer.csp-open {
    transform: translateY(0);
    opacity: 1;
    visibility: visible;
  }
  #csp-drawer-inner {
    display: flex;
    flex-direction: column;
    padding: 16px 20px 24px;
    gap: 4px;
  }
  #csp-drawer .csp-nav-link {
    font-size: 1.0625rem;
    padding: 12px 18px;
    border-radius: 8px;
    color: rgba(255,255,255,0.9);
  }
  #csp-drawer .csp-nav-link:hover,
  #csp-drawer .csp-nav-active { background: rgba(255,255,255,0.08); }
  #csp-drawer .csp-nav-link::after { display: none; }
  #csp-drawer-divider {
    height: 1px;
    background: rgba(255,255,255,0.1);
    margin: 12px 0;
    border: none;
  }
  #csp-drawer .csp-submit-btn {
    margin-top: 4px;
    justify-content: center;
    width: 100%;
  }

  body { padding-top: 76px !important; }
  @media (max-width: 960px) { body { padding-top: 68px !important; } }
  .sticel-sticky-placeholder { display: none !important; }
</style>

<header id="csp-header">
  <a id="csp-logo" href="${homeHref}">
    <img src="${logoSrc}" alt="Conspodium">
  </a>
  <nav id="csp-nav" aria-label="Main navigation">
    ${navItems}
  </nav>
  <div id="csp-cta">
    <a class="csp-submit-btn" href="${submitHref}">
      Submit Story
      <svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </a>
  </div>
  <button id="csp-burger" aria-label="Toggle menu" aria-expanded="false">
    <svg id="csp-icon-menu" width="22" height="22" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M2.5 5h15M2.5 10h15M2.5 15h15" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
    </svg>
    <svg id="csp-icon-close" width="22" height="22" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:none">
      <path d="M4 4l12 12M16 4L4 16" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
    </svg>
  </button>
</header>

<div id="csp-drawer" role="navigation" aria-label="Mobile navigation">
  <div id="csp-drawer-inner">
    ${navItems}
    <hr id="csp-drawer-divider">
    <a class="csp-submit-btn" href="${submitHref}">
      Submit Story
      <svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </a>
  </div>
</div>

<script>
(function() {
  var burger    = document.getElementById('csp-burger');
  var drawer    = document.getElementById('csp-drawer');
  var iconMenu  = document.getElementById('csp-icon-menu');
  var iconClose = document.getElementById('csp-icon-close');
  if (!burger || !drawer) return;
  function setOpen(open) {
    drawer.classList.toggle('csp-open', open);
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (iconMenu)  iconMenu.style.display  = open ? 'none'  : 'block';
    if (iconClose) iconClose.style.display = open ? 'block' : 'none';
  }
  burger.addEventListener('click', function(e) {
    e.stopPropagation();
    setOpen(!drawer.classList.contains('csp-open'));
  });
  document.addEventListener('click', function(e) {
    if (drawer.classList.contains('csp-open') && !drawer.contains(e.target) && !burger.contains(e.target)) setOpen(false);
  });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape') setOpen(false); });
})();
</script>
<!-- CSP_HEADER_BLOCK_END -->`;
}

function cleanHtml(html) {
  // Strip any CSP_HEADER_BLOCK_START ... CSP_HEADER_BLOCK_END
  const startIdx = html.indexOf('<!-- CSP_HEADER_BLOCK_START -->');
  if (startIdx !== -1) {
    const endIdx = html.indexOf('<!-- CSP_HEADER_BLOCK_END -->', startIdx);
    if (endIdx !== -1) {
      html = html.slice(0, startIdx) + html.slice(endIdx + '<!-- CSP_HEADER_BLOCK_END -->'.length);
    }
  }

  // Strip any old CSP test headers or previous iterations
  html = html.replace(/<!-- ═══ CONSPODIUM HEADER[\s\S]*?<!-- ═══ END CONSPODIUM HEADER ═══════════════════════════════════════ -->/g, '');
  html = html.replace(/<header id="csp-header">[\s\S]*?<\/header>/g, '');
  html = html.replace(/<div id="csp-drawer"[\s\S]*?<\/script>/g, '');

  // Strip elementor header block data-elementor-id="1969"
  const elemIdx = html.indexOf('data-elementor-id="1969"');
  if (elemIdx !== -1) {
    const wrapperStart = html.lastIndexOf('<div', elemIdx);
    let depth = 0;
    let pos = wrapperStart;
    let wrapperEnd = -1;
    while (pos < html.length) {
      if (html.slice(pos, pos + 4) === '<div') {
        depth++;
        pos += 4;
      } else if (html.slice(pos, pos + 6) === '</div>') {
        depth--;
        pos += 6;
        if (depth === 0) {
          wrapperEnd = pos;
          break;
        }
      } else {
        pos++;
      }
    }
    if (wrapperEnd !== -1) {
      html = html.slice(0, wrapperStart) + html.slice(wrapperEnd);
    }
  }

  // Clean all preloader scripts/styles/divs
  html = html.replace(/<e-page-transition[\s\S]*?<\/e-page-transition>/gi, '');
  html = html.replace(/<script[^>]*safelayout[^>]*>[\s\S]*?<\/script>/gi, '');
  html = html.replace(/<style[^>]*safelayout[^>]*>[\s\S]*?<\/style>/gi, '');
  html = html.replace(/<link[^>]*safelayout[^>]*>/gi, '');
  html = html.replace(/<div[^>]*id=["']sl-preloader["'][^>]*>[\s\S]*?<\/div>/gi, '');
  html = html.replace(/<style[^>]*>\s*#sl-preloader\s*\{[^}]*\}\s*<\/style>/gi, '');

  return html;
}

async function main() {
  const files = (await walk(OUT)).filter(f => /\.html?$/i.test(f));
  let updated = 0;

  for (const file of files) {
    let html = await readFile(file, 'utf8');
    html = cleanHtml(html);

    const bodyIdx = html.indexOf('<body');
    if (bodyIdx === -1) continue;
    const bodyTagEnd = html.indexOf('>', bodyIdx) + 1;

    const root = toRoot(file);
    const activePath = getActivePath(file);
    const header = buildHeader(root, activePath);

    html = html.slice(0, bodyTagEnd) + '\n' + header + '\n' + html.slice(bodyTagEnd);
    await writeFile(file, html, 'utf8');
    updated++;
  }

  console.log(`Rebuilt header in ${updated} HTML files successfully.`);
}

main().catch(console.error);
