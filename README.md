# Conspodium

Africa's intellectual platform for the global diaspora.

---

## Project Structure

```
conspodium-clone/
│
├── src/                        ← SOURCE OF TRUTH — edit here
│   ├── assets/                 ← Static WP assets, images, CSS, JS, fonts
│   ├── pages/
│   │   ├── home.html           ← Homepage
│   │   ├── about.html          ← About Us page
│   │   ├── stories.html        ← Stories/Essays page
│   │   ├── contact.html        ← Contact Us page
│   │   ├── sponsorship.html    ← Sponsorship page
│   │   ├── advert.html         ← Advertise page
│   │   ├── submit-story.html   ← Submit Story page
│   │   └── dashboard.html      ← Member dashboard
│   ├── components/
│   │   └── header-fixes.html   ← Shared header & CSS fixes
│   └── scripts/
│       ├── build.js            ← Assembles public/ from src/
│       └── serve.js            ← Express server
│
├── public/                     ← BUILT SITE — served, do not edit directly
├── vercel.json
├── package.json
└── README.md
```

---

## Workflow

### Edit content

Open any page in `src/pages/*.html` or assets in `src/assets/`, make changes, then rebuild:

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

---

## How Pages Are Built

| Page | Source file | Output |
|---|---|---|
| `/` | `src/pages/home.html` | `public/index.html` |
| `/about-us/` | `src/pages/about.html` | `public/about-us/index.html` |
| `/stories/` | `src/pages/stories.html` | `public/stories/index.html` |
| `/contact-us/` | `src/pages/contact.html` | `public/contact-us/index.html` |
| `/sponsorship/` | `src/pages/sponsorship.html` | `public/sponsorship/index.html` |
| `/advert/` | `src/pages/advert.html` | `public/advert/index.html` |
| `/submit-story/` | `src/pages/submit-story.html` | `public/submit-story/index.html` |
| `/dashboard` | `src/pages/dashboard.html` | `public/dashboard/index.html` |

All pages in `src/pages/` are 100% self-contained standalone HTML source files. `build.js` copies `src/assets/` and processes `src/pages/` into `public/`.

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
