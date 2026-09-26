# Forum Redesign & UI Upgrade Plan

## 1. Overview & Vision
Transform the Conspodium Community Forum (`/forum/`) from a standard thread listing into a world-class, luxury interactive exchange hub for the global African diaspora. The redesign introduces a high-converting editorial hero with instant live search, humanized author lockups, sortable discussion streams, and a rich magazine-style sidebar with live community pulse metrics.

---

## 2. Core UI & Feature Specifications

### A. Hero Section Redesign (Modern Editorial Masthead)
- **Brand Accent & Badge:** Top gradient pill `🌐 GLOBAL DIASPORA COMMUNITY` with signature Merienda styling:
  > **Where Diaspora Minds <span style="background:linear-gradient(135deg, #00AEFE, #B71F71); -webkit-background-clip:text; -webkit-text-fill-color:transparent;">Connect!</span>**
- **Hero Live Search Bar:** Embedded floating search input with instant debounced keyword autocomplete (`"Search topics, debates, policies, keywords..."`).
- **Quick Action CTA:** Flanked by a high-visibility glowing **"💬 Start Discussion"** modal trigger.

### B. Upgraded Thread Cards (Humanized & Engaging)
- **Top Author Header Lockup:** 
  - Author initial avatar with colored gradient background (`[SF]`).
  - Author name with optional diaspora location tag (e.g., `📍 London, UK`, `📍 Stockholm, Sweden`, `📍 Toronto, Canada`, `📍 Lagos, Nigeria`).
  - Relative timestamp (e.g., `2h ago`, `Yesterday`).
- **Category Micro-Badges & Flairs:** Rounded 30px pill tags with icons (`💡 Innovation`, `🏛️ Policy`, `🎨 Culture`, `🔥 Trending`).
- **Floating Engagement Capsule:** Right-aligned counter pill displaying `💬 14 replies` and `👁️ 320 views` with hover glow.
- **Pinned Editorial Debate Styling:** Distinct gold/cyan gradient border accent and `📌 Featured Debate` banner for official editorial topics.

### C. 2-Column Layout with Magazine-Style Community Sidebar
- **Desktop Grid:** `minmax(0, 1fr) 320px` responsive grid (stacks into 1 column on mobile/tablet).
- **Sidebar Card 1 — Community Pulse Metrics:**
  - 🌍 **54** Nations Connected
  - 👥 **1,400+** Diaspora Leaders & Contributors
  - 💬 **380+** Active Discussions
- **Sidebar Card 2 — Trending Debates of the Week:** Top 3 hottest discussions ranked with direct jump links and reply counters.
- **Sidebar Card 3 — Community Code of Conduct:** Classy, concise guidelines promoting constructive debate and mutual empowerment.

### D. Search, Sort & Filter Toolbar
- **Sort Pills:** Quick toggle between:
  - 🔥 **Trending / Most Active** (recent replies)
  - ⚡ **Latest** (newly created)
  - 💬 **Most Discussed** (highest reply count)
  - 👁️ **Most Viewed**
- **Dynamic Result Count:** Clear indicator (`"Showing 18 active discussions"`) with smooth skeleton pulse transitions.

### E. Glassmorphic "Start Discussion" Modal
- Sleek glassmorphic modal with:
  - Visual category dropdown with icons.
  - Optional location field (`"Your City / Country"`).
  - Floating form inputs with glow focus effects.
  - Client-side validation and instant thread injection upon submission.

---

## 3. Implementation Checklist
- [ ] Implement responsive 2-column layout in `src/pages/forum.html`
- [ ] Build Hero with live search and gradient typography
- [ ] Refactor thread card markup with avatar lockups and location tags
- [ ] Add Sidebar cards (Pulse Metrics, Trending Topics, Forum Rules)
- [ ] Implement Sort filters (Trending / Latest / Most Discussed / Most Viewed)
- [ ] Polish "Start Discussion" modal with category selector & location tag
- [ ] Test responsiveness across mobile, tablet, and desktop
