/**
 * fix-hero-placement-v3.js
 *
 * Precisely moves the custom hero section from inside <head> into the body,
 * immediately before the Elementor hero div (data-id="375eb214").
 * Also centers the hero text and buttons.
 *
 * Uses `</head>` as the boundary to confirm the hero is in the wrong place.
 */

import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const FILE = fileURLToPath(new URL('./output2/index.html', import.meta.url));

const HERO_START_TAG = '<!-- CSP_HERO_START -->';
const HERO_END_TAG   = '<!-- CSP_HERO_END -->';

// The actual div we insert before (data-id only appears once, on the real div)
const BODY_ANCHOR = 'data-id="375eb214"';

let html = await readFile(FILE, 'utf8');

// ── Step 1: Find hero markers ─────────────────────────────────────────────────
const heroStart = html.indexOf(HERO_START_TAG);
const heroEnd   = html.indexOf(HERO_END_TAG);
const headClose = html.indexOf('</head>');
const bodyOpen  = html.indexOf('<body');

if (heroStart === -1 || heroEnd === -1) {
  console.error('❌ CSP_HERO markers not found!');
  process.exit(1);
}

const heroEndFull = heroEnd + HERO_END_TAG.length;
let heroBlock = html.slice(heroStart, heroEndFull);

console.log(`Hero currently at char ${heroStart}–${heroEndFull}`);
console.log(`</head> at char: ${headClose}, <body at char: ${bodyOpen}`);

if (heroStart > headClose) {
  console.log('✔ Hero is already inside body — only centering fix needed');
} else {
  console.log('⚠  Hero is inside <head> — will move to body');
}

// ── Step 2: Fix centering in hero CSS ────────────────────────────────────────
// a) Slides: change flex bottom-left to center
heroBlock = heroBlock.replace(
  /display:flex;align-items:flex-end;justify-content:flex-start;/g,
  'display:flex;align-items:center;justify-content:center;'
);
// b) Content div: add text-align center
heroBlock = heroBlock.replace(
  /\.csp-hero-content\{position:relative;z-index:2;max-width:760px;\}/g,
  '.csp-hero-content{position:relative;z-index:2;max-width:760px;text-align:center;}'
);
// c) Button row: center the flex
heroBlock = heroBlock.replace(
  /display:flex;gap:14px;flex-wrap:wrap;\s*\n\s*transform:translateY\(24px\)/g,
  'display:flex;gap:14px;flex-wrap:wrap;justify-content:center;\n  transform:translateY(24px)'
);

console.log('✔ Applied centering fixes to hero block');

// ── Step 3: Remove hero from its current location ────────────────────────────
html = html.slice(0, heroStart) + html.slice(heroEndFull);
console.log('✔ Removed hero from current location');

// ── Step 4: Find the correct body injection point ────────────────────────────
const anchorIdx = html.indexOf(BODY_ANCHOR);
if (anchorIdx === -1) {
  console.error('❌ Body anchor data-id="375eb214" not found!');
  process.exit(1);
}

// Walk back to the opening < of this div tag
let insertAt = anchorIdx;
while (insertAt > 0 && html[insertAt] !== '<') insertAt--;

// Verify we are now in the body
const newHeadClose = html.indexOf('</head>');
const newBodyOpen  = html.indexOf('<body');
if (insertAt < newHeadClose) {
  console.error(`❌ Injection point (${insertAt}) is still inside <head> (${newHeadClose})!`);
  process.exit(1);
}

console.log(`✔ Injection point at char ${insertAt} (after </head> at ${newHeadClose})`);

// ── Step 5: Inject ────────────────────────────────────────────────────────────
html = html.slice(0, insertAt) + heroBlock + '\n' + html.slice(insertAt);
console.log('✔ Hero injected at correct body location');

await writeFile(FILE, html, 'utf8');
console.log('\n✅ All done! Hard-refresh (Cmd+Shift+R) at http://localhost:8080');
