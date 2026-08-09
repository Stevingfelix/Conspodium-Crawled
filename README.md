# Conspodium

Africa's intellectual platform for the global diaspora.

---

## Project Structure

```
conspodium-clone/
│
├── src/                        ← SOURCE OF TRUTH — edit here
│   ├── pages/
│   │   ├── home.html           ← Full standalone homepage
│   │   ├── about.html          ← About Us custom sections
│   │   ├── stories.html        ← Stories/Essays custom sections
│   │   ├── contact.html        ← Contact custom sections
│   │   ├── sponsorship.html    ← Sponsorship tier cards
│   │   ├── advert.html         ← Advertise page
│   │   ├── submit-story.html   ← Submit Story page
│   │   └── dashboard.html      ← Full standalone member dashboard
│   ├── components/
│   │   └── header-fixes.html   ← CSS fixes injected into every page
│   └── scripts/
│       ├── build.js            ← Assembles output/ from src/
│       ├── serve.js            ← Express server
│       └── crawl.js            ← Re-crawl wrapper
│
├── public/                    ← BUILT SITE — served, do not edit directly
├── output2/                    ← CRAWLED BASE — raw WordPress HTML
├── _backup/                    ← Backup of original project before restructure
│
├── recrawl-all.js              ← Full site crawl script
├── package.json
└── README.md
```

---

## Workflow

### Edit content

Open the relevant file in `src/pages/`, make changes, then rebuild:

```bash
npm run build
```

### Run the server

```bash
npm start
# or npm run serve
# → http://localhost:8080
```

### Build + serve in one command

```bash
npm run dev
```

### Re-crawl from the live site

```bash
npm run crawl
# Then: npm run build
```

---

## How Pages Are Built

| Page | Source file | Output |
|---|---|---|
| `/` | `src/pages/home.html` | `public/index.html` |
| `/about-us/` | `src/pages/about.html` + crawled base | `public/about-us/index.html` |
| `/stories/` | `src/pages/stories.html` + crawled base | `public/stories/index.html` |
| `/contact-us/` | `src/pages/contact.html` + crawled base | `public/contact-us/index.html` |
| `/sponsorship/` | `src/pages/sponsorship.html` + crawled base | `public/sponsorship/index.html` |
| `/advert/` | `src/pages/advert.html` + crawled base | `public/advert/index.html` |
| `/submit-story/` | `src/pages/submit-story.html` + crawled base | `public/submit-story/index.html` |
| `/dashboard` | `src/pages/dashboard.html` | `public/dashboard/index.html` |

**Standalone pages** (`home`, `dashboard`) — `src/pages/*.html` is a complete HTML file copied directly to public.

**Crawled-base pages** (all others) — `build.js` reads the crawled WordPress page from `output2/`, applies `header-fixes.html`, injects the custom sections from `src/pages/*.html` before `</body>`, and writes the result to `public/`.

---

## Adding a New Page

1. Create `src/pages/newpage.html` with your content wrapped in `<!-- SECTION: name -->` blocks
2. Add an entry to the `PAGES` array in `src/scripts/build.js`
3. Add a route in `src/scripts/serve.js`
4. Run `npm run build`

---

## Adding a New Section to Home

The homepage is standalone — just edit `src/pages/home.html` directly and run `npm run build`.

---

## Future Routes (stubbed in serve.js)

- `/dashboard` — Member dashboard (built)
- More routes ready to wire up as the platform grows

---

## Brand Colours

| Name | Hex |
|---|---|
| Background deep | `#020918` |
| Background dark | `#050D1A` |
| Blue accent | `#00AEFE` |
| Pink accent | `#B71F71` |
