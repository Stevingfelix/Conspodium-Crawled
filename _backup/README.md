# Conspodium clone

A full offline capture of **https://conspodium.com** (WordPress) — the basis for
rebuilding it as a React/Node app.

## What's here

```
crawl.js     Headless-Chromium crawler: renders each page, saves HTML + every
             CSS/JS/image/font it requests (incl. cross-origin → _ext/).
rewrite.js   Rewrites absolute URLs to relative paths so the clone runs offline.
serve.js     Tiny static server for browsing the clone over http://.
verify.js    Loads cloned pages, reports broken refs, screenshots for comparison.
output/      The captured site (21 pages, ~19 MB).
```

## Browse the clone

```bash
npm run serve     # then open http://localhost:8080
```

## Re-capture (if the live site changes)

```bash
npm run crawl && npm run rewrite
```

## What was captured vs. what must be rebuilt

The clone is a faithful **design + content** reference. It captures everything
that is sent to the browser. It does **not** (and cannot) capture server-side
logic — that has to be rebuilt for the React/Node version:

| Captured (static)                          | Must be rebuilt (dynamic / server-side)        |
| ------------------------------------------ | ---------------------------------------------- |
| Home, About, Contact, Stories, Sponsorship | Login / accounts (`/login`, `/account`)        |
| Advert, Submit Story (markup), Community   | User dashboard (`/dashboard`, `/edit`)         |
| 4 blog posts + layout                      | Subscriptions & payments (`/subscription`,     |
| All images, fonts, CSS, the visual design  | `/payment`, `/order-received`) → needs Stripe  |
|                                            | Story submission form handler → needs an API   |

### Known limitations of the static capture
- A few Elementor/plugin **icon-font glyphs** and one widget CSS file 404 offline
  (the plugins lazy-load them via JS). Irrelevant to the React rebuild.
- `wp-admin/admin-ajax.php` calls fail offline (they're backend endpoints).
- Auth/payment pages only show their **logged-out** state.

## Tech stack detected on the source site
WordPress 6.8 · Elementor + Elementor Pro · Jeg Elementor Kit · ElementsKit ·
LiteSpeed cache · hosted on Hostinger. A membership/subscription + story-
submission magazine.
