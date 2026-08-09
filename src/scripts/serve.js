/**
 * src/scripts/serve.js
 * =====================================================================
 * Express server for Conspodium.
 *
 * Usage:  npm run serve
 *         npm run dev  (builds first, then serves)
 *
 * Routes:
 *   GET /              → output/index.html
 *   GET /dashboard     → output/dashboard/index.html  (future)
 *   GET /*             → static files from output/
 *   404                → output/404.html or fallback
 * =====================================================================
 */

import express from 'express';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { existsSync } from 'node:fs';

import { initDb } from '../backend/db.js';
import postsRouter from '../backend/routes/posts.js';
import pollsRouter from '../backend/routes/polls.js';
import remindersRouter from '../backend/routes/reminders.js';
import submissionsRouter from '../backend/routes/submissions.js';
import dashboardRouter from '../backend/routes/dashboard.js';
import uploadRouter from '../backend/routes/upload.js';

const __dirname = dirname(fileURLToPath(import.meta.url));
const PUBLIC    = join(__dirname, '../../public');
const PORT      = process.env.PORT || 8080;

const app = express();

// Initialize SQLite Database & Tables
initDb();

// ── Middleware ────────────────────────────────────────────────────────────────
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Serve uploaded images statically
app.use('/uploads', express.static(join(PUBLIC, 'uploads')));

// ── Logging middleware ────────────────────────────────────────────────────────
app.use((req, res, next) => {
  const start = Date.now();
  res.on('finish', () => {
    const ms = Date.now() - start;
    const color = res.statusCode >= 400 ? '\x1b[31m' : '\x1b[32m';
    console.log(`${color}${res.statusCode}\x1b[0m  ${req.method} ${req.url}  \x1b[2m${ms}ms\x1b[0m`);
  });
  next();
});

// ── API Routes ────────────────────────────────────────────────────────────────
app.use('/api', postsRouter);
app.use('/api', pollsRouter);
app.use('/api', remindersRouter);
app.use('/api', submissionsRouter);
app.use('/api', dashboardRouter);
app.use('/api', uploadRouter);

// ── Static assets (images, CSS, JS, fonts etc.) ──────────────────────────────
// Served with caching disabled in dev; tweak for production.
app.use(express.static(PUBLIC, {
  etag: false,
  lastModified: false,
  setHeaders: (res) => {
    res.setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
  }
}));

// ── Page routes ───────────────────────────────────────────────────────────────

/** Serve a page from public/, falling back to public/<slug>/index.html */
function servePage(slug) {
  return (req, res) => {
    const direct = join(PUBLIC, slug, 'index.html');
    const root   = join(PUBLIC, 'index.html');
    if (existsSync(direct)) return res.sendFile(direct);
    if (slug === '' && existsSync(root)) return res.sendFile(root);
    res.status(404).send(`<h1>Page not found</h1><p>Build may be needed. Run: npm run build</p>`);
  };
}

// Homepage
app.get('/', servePage(''));

// ── Future routes ─────────────────────────────────────────────────────────────
// These stubs are ready to wire up as full Express route handlers.
// To add a new page: create src/pages/<name>.html, add a route here.
app.get('/dashboard',         servePage('dashboard'));
app.get('/dashboard/{*path}', servePage('dashboard'));

// Catch-all: try to find an index.html in the matching public subdirectory
app.get('/{*path}', (req, res) => {
  // Strip leading/trailing slashes and clean the path
  const slug = req.path.replace(/^\/|\/$/g, '');
  const file = join(PUBLIC, slug, 'index.html');
  const direct = join(PUBLIC, slug);

  if (existsSync(file)) return res.sendFile(file);
  if (existsSync(direct) && !direct.endsWith('.html')) {
    return res.sendFile(join(direct, 'index.html'));
  }

  // True 404
  const notFound = join(PUBLIC, '404.html');
  if (existsSync(notFound)) return res.status(404).sendFile(notFound);
  res.status(404).send(`
    <html>
      <head><title>404 — Conspodium</title></head>
      <body style="font-family:sans-serif;padding:40px;background:#050D1A;color:#fff;">
        <h1 style="color:#00AEFE;">404 — Page not found</h1>
        <p>The page <code>${req.path}</code> does not exist in the built public directory.</p>
        <p><a href="/" style="color:#00AEFE;">← Back to homepage</a></p>
        <p style="opacity:0.4;font-size:0.8rem;">If you just added a new page, run <code>npm run build</code> first.</p>
      </body>
    </html>
  `);
});

// ── Start ─────────────────────────────────────────────────────────────────────
app.listen(PORT, () => {
  console.log('\n🚀 Conspodium server running');
  console.log(`   Local:  \x1b[36mhttp://localhost:${PORT}\x1b[0m`);
  console.log(`   Public: ${PUBLIC}`);
  console.log('\n   Press Ctrl+C to stop\n');
});
