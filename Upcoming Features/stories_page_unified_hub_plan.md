# Implementation Plan: Unified Interactive Stories Hub (`/stories/`)

## 1. Executive Summary & Core Objective
The goal is to simplify the Conspodium story browsing experience by consolidating category browsing directly into the **Stories Page (`/stories/`)**. 
Instead of maintaining separate, redundant `category.html` pages that require full browser reloads, `/stories/` becomes a single, high-performance interactive hub where visitors can filter categories instantly with smooth animations, zero page refreshes, and deep-linkable URLs (`/stories/?category=innovation`).

---

## 2. Key Architecture & User Experience Flow

### A. Seamless Category Filter Interactions (No Page Reloads)
- **Top Pill Navigation Bar:** Clicking any category pill (*Culture & Heritage*, *Innovation*, *Art & Entertainment*, *Community*, *Success Stories*, etc.) triggers an instant AJAX fetch from `api/posts.php?category=:slug&limit=6&offset=0`.
- **Card Limit per Page:** Strictly capped at **6 cards per page** in a clean 3x2 responsive grid (1-column on mobile).
- **Pagination Sync:** Smooth pagination controls (`← Prev`, `1`, `2`, `3`, `Next →`) update dynamically based on the category post count and automatically scroll the viewport to the top of the stories grid.

### B. "Discover by Category" Lower Section Integration
- The rich category image cards lower down the page (*Discover By Category*) will no longer link to separate static pages.
- **Action on Click:** 
  1. Triggers a smooth scroll up to `#latest-stories`.
  2. Activates the corresponding category filter pill with the gradient active style.
  3. Loads the 6 latest posts for that category with smooth transition.
  4. Updates the browser URL to `/stories/?category=:slug` via `history.pushState` without reloading.

### C. Deep Linking & Shareable URLs (`/stories/?category=...`)
- **Direct Entry Handling:** If a visitor arrives from a social share, newsletter, or external link with `http://localhost:8080/stories/?category=innovation`:
  - The page reads the query parameter on load.
  - Automatically highlights the *Innovation* pill.
  - Fetches and displays the 6 innovation stories.
  - Smoothly scrolls the visitor directly to the grid.
- **Browser History Support:** Using `history.pushState`, users can use their browser's **Back** and **Forward** buttons to navigate through their category browsing history effortlessly.

### D. Redirection & Deprecation of Standalone Category Pages
- Legacy URLs (e.g. `/category/innovation/`) will automatically 301/JavaScript redirect to `/stories/?category=innovation`.
- Eliminates duplicated code, reduces maintenance surface, and ensures all future enhancements (card designs, badges, metrics) apply globally in one place.

---

## 3. Step-by-Step Implementation Steps

| Step | Component | Action |
| :--- | :--- | :--- |
| **Step 1** | `src/pages/stories.html` | Update category pills & "Discover by Category" cards to trigger in-page filter and smooth scroll |
| **Step 2** | `src/pages/stories.html` | Implement `URLSearchParams` parser on page initialization to auto-select pill from `?category=...` |
| **Step 3** | `src/pages/stories.html` | Add `history.pushState` to update browser address bar without reload when switching pills |
| **Step 4** | `src/scripts/build.mjs` | Update category redirects so `/category/:slug` routes point seamlessly to `/stories/?category=:slug` |
| **Step 5** | Navigation & Header | Update header and footer category links to point to `/stories/?category=:slug` |

---

## 4. Expected Benefits
- **Lightning-fast UX:** Immediate visual feedback with zero white-screen reload flashes.
- **Better Engagement:** Visitors stay on the page longer and explore more stories without navigation friction.
- **Single Source of Truth:** One template to manage, test, and style for all article browsing.
