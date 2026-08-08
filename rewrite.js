// Rewrite pass: turn every absolute conspodium.com / cross-origin URL in the
// captured HTML and CSS files into a relative path pointing at the local mirror,
// and fuzzy-resolve versioned asset paths so CSS & JS always load offline.

import { readFile, writeFile, readdir, stat } from 'node:fs/promises';
import { join, relative, dirname, basename } from 'node:path';
import { fileURLToPath } from 'node:url';

const OUT = fileURLToPath(new URL('./output/', import.meta.url));
const ORIGIN = 'https://conspodium.com';

const manifest = JSON.parse(await readFile(join(OUT, '_manifest.json'), 'utf8'));

// remoteURL -> localPath (relative to OUT).
const map = new Map(Object.entries(manifest.assets));
const entries = [...map.entries()].sort((a, b) => b[0].length - a[0].length);

const pageLinks = manifest.pages.map((p) => ({
  url: ORIGIN + p,
  local: p === '/' ? 'index.html' : p.replace(/^\//, '') + 'index.html',
}));

async function walk(dir) {
  const out = [];
  for (const name of await readdir(dir)) {
    const full = join(dir, name);
    const s = await stat(full);
    if (s.isDirectory()) out.push(...(await walk(full)));
    else out.push(full);
  }
  return out;
}

// Fuzzy asset resolver for versioned CSS/JS filenames
const localFileCache = new Map();
async function resolveLocalAsset(relPath, fileDir) {
  const cleanPath = relPath.split('?')[0].replace(/^\.\//, '');
  const fullTarget = join(OUT, cleanPath);

  try {
    await stat(fullTarget);
    return null; // exact file exists
  } catch {}

  const dir = dirname(fullTarget);
  const ext = relPath.includes('.css') ? '.css' : (relPath.includes('.js') ? '.js' : '');
  if (!ext) return null;

  const fileBase = basename(cleanPath).replace(ext, '');

  let dirFiles = localFileCache.get(dir);
  if (!dirFiles) {
    try {
      dirFiles = await readdir(dir);
      localFileCache.set(dir, dirFiles);
    } catch {
      return null;
    }
  }

  const match = dirFiles.find((f) => f.startsWith(fileBase) && f.endsWith(ext));
  if (match) {
    const matchFullPath = join(dir, match);
    let rel = relative(fileDir, matchFullPath).split('\\').join('/');
    if (!rel.startsWith('.')) rel = './' + rel;
    return rel;
  }
  return null;
}

const files = (await walk(OUT)).filter((f) => /\.(html?|css)$/i.test(f));
let edited = 0;

for (const file of files) {
  let text = await readFile(file, 'utf8');
  const before = text;
  const fileDir = dirname(file);

  for (const [remote, local] of entries) {
    if (!text.includes(remote) && !text.includes(remote.replace('https:', ''))) continue;
    let rel = relative(fileDir, join(OUT, local)).split('\\').join('/');
    if (!rel.startsWith('.')) rel = './' + rel;
    text = text.split(remote).join(rel);
    text = text.split(remote.replace('https:', '')).join(rel);
    const esc = remote.split('/').join('\\/');
    if (text.includes(esc)) text = text.split(esc).join(rel.split('/').join('\\/'));
  }

  let toRoot = relative(fileDir, OUT).split('\\').join('/');
  if (toRoot === '') toRoot = '.';
  else if (!toRoot.startsWith('.')) toRoot = './' + toRoot;

  for (const { url, local } of pageLinks) {
    let rel = relative(fileDir, join(OUT, local)).split('\\').join('/');
    if (!rel.startsWith('.')) rel = './' + rel;
    const esc = url.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const re = new RegExp(esc + '(?=["\'\\s>#?])', 'g');
    text = text.replace(re, rel);
  }

  text = text.split(ORIGIN + '/').join(toRoot + '/');
  text = text.split(ORIGIN).join(toRoot + '/');

  text = text
    .split('index.htmlwp-content').join('wp-content')
    .split('index.htmlwp-includes').join('wp-includes')
    .split('index.htmlwp-admin').join('wp-admin');

  // Fuzzy-resolve versioned asset href/src paths
  const assetRegex = /(href|src)=["']([^"']+\.(css|js)[^"']*)["']/gi;
  let match;
  const replacements = [];
  while ((match = assetRegex.exec(text)) !== null) {
    const attr = match[1];
    const rawUrl = match[2];
    if (rawUrl.startsWith('http://') || rawUrl.startsWith('https://')) continue;
    const resolved = await resolveLocalAsset(rawUrl, fileDir);
    if (resolved) {
      replacements.push({ raw: `${attr}="${rawUrl}"`, fixed: `${attr}="${resolved}"` });
      replacements.push({ raw: `${attr}='${rawUrl}'`, fixed: `${attr}='${resolved}'` });
    }
  }

  for (const r of replacements) {
    text = text.split(r.raw).join(r.fixed);
  }

  if (text !== before) {
    await writeFile(file, text);
    edited++;
  }
}

console.log(`Rewrote ${edited} of ${files.length} HTML/CSS files (including fuzzy asset resolution).`);
