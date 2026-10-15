# 🏛️ Conspodium — Project Handoff & Technical Documentation

> **Current System Version**: `v2.1.9`  
> **Last Updated**: October 15, 2026  
> **Repository**: `https://github.com/Stevingfelix/Conspodium-Crawled.git`  
> **Branch**: `main`  
> **Local Testing URL**: `http://localhost:8088/dashboard/`  
> **Active Release Package**: `dist/conspodium-update-v2.1.9.zip` (27.44 MB — 100% Sandbox Tested)

---

## 📌 1. Executive Summary

**Conspodium** is a high-performance digital media platform and CMS dedicated to storytelling, culture, business, innovation, and community across the African diaspora.

The platform is built on a **zero-dependency Native PHP PDO + SQLite / MySQL architecture** paired with modern Vanilla HTML5/CSS3/ES6+ frontends. It requires **no node runtime in production**, zero composer dependencies, and operates seamlessly on any shared hosting environment (Hostinger, cPanel, LiteSpeed, Apache, Nginx).

### Core Capabilities
* **Full CMS Engine**: Articles, rich story submissions, category hierarchy, weekly polls, scholar directory, and reader comments.
* **Diaspora Forum**: Categorized community discussion threads with nested comment replies (`/forum/`, `/forum/thread/`).
* **Marketing & Monetization**: Newsletter campaign manager (SMTP/SendGrid/Mailgun/Brevo), modal popup adverts with smart maintenance-mode suppression, and payment gateway configuration (Stripe/PayPal/Paystack/Flutterwave).
* **Self-Healing In-App Updater**: Native ZIP-based in-app updater engine (`api/updater.php`) allowing one-click upgrades directly from the browser with zero risk of database or media loss.
* **10-Step Automated Sandbox Test Suite**: Strict pre-deployment automated testing that audits syntax, assets (404 detection), form pre-filling, DOM hierarchy, and CSS layout clipping before any update is packaged.

---

## ⚡ 2. Quick Start & Operational Commands

```bash
# 1. Start Local PHP Development Server (runs on http://localhost:8080)
npm start
# OR
php -d upload_max_filesize=64M -d post_max_size=64M -d memory_limit=256M -S localhost:8080 -t public router.php

# 2. Recompile Source Templates (src/pages/ → public/)
npm run build

# 3. Build, Validate & Package an In-App System Update
npm run package-update

# 4. Run Isolated 10-Step Sandbox Verification on Any Package
npm run test:package [dist/conspodium-update-v2.1.6.zip]
```

---

## 🔑 3. Admin CMS & Credentials

* **Dashboard URL**: `http://localhost:8080/dashboard/` (or `https://your-domain.com/dashboard/`)
* **Default Credentials**:
  * **Username**: `admin`
  * **Password**: `admin123` (or `conspodium2026`)
* **Session Storage Keys**:
  * `conspodium_admin_token` / `csp_admin_token`: Admin session bearer token passed via `X-Admin-Token` header.
  * `csp_last_auth_pass`: Remembers active session password for prefilling during editing.
  * `csp_active_dashboard_tab`: Preserves current sidebar tab (e.g. `settings`, `profile`).
  * `csp_active_settings_subtab`: Preserves subtab under Settings (e.g. `general`, `updates`).

---

## 📦 4. Release History & Changelog

### **v2.1.9 (Current Release — October 15, 2026)**
* **Dynamic Clean URL Fallbacks**: Added dynamic directory index rewrite conditions (`RewriteCond %{REQUEST_URI} !^/public/` with `RewriteCond %{DOCUMENT_ROOT}/public%{REQUEST_URI}/index.html -f`) ensuring any present and future subfolder automatically routes without throwing 404s.
* **Update Packaging Sync**: Updated `package-update.mjs` to automatically package `.htaccess` into release ZIPs, and updated `api/updater.php` to extract `.htaccess` during 1-click in-app updates.
* **Subscriber Directory**: Added 1-click Subscriber Deletion action in the Subscriber Directory with confirmation dialog and live list refreshing.
* **Popup Advert Live Preview & Placement**: Aligned backend live popup advert preview with frontend design (edge-to-edge flyer presentation with floating circular close button) and added Button Placement Position selector (Floating vs Below Image).

### **v2.1.8 (October 14, 2026)**
* **Subscriber Directory**: Added 1-click Subscriber Deletion action in the Subscriber Directory with confirmation dialog and live list refreshing.
* **Popup Advert Live Preview & Placement**: Aligned backend live popup advert preview with frontend design (edge-to-edge flyer presentation) and added Button Placement Position selector (Floating vs Below Image).

### **v2.1.7 (October 14, 2026)**
* **Dashboard Sidebar Single Logo Mark**: Updated sidebar logo to use the single logo style without text (`CONSPODIUM-NEW-2.png`), matching the Branding Settings "Dashboard Logo" specification.
* **Two-Way Branding Wiring**: Fully wired sidebar logo with `api/settings.php` (`dashboard_logo`), ensuring immediate visual synchronization on page load, image upload/replacement, and default reset.
* **Automated Password Masking on Save**: Fixed password visibility leak where submitting or saving the Admin Profile form left password inputs exposed as `type="text"`. The system now guarantees that all password inputs (`prof-tab-current-pass`, `prof-tab-new-pass`, `prof-tab-confirm-pass`) immediately revert to `type="password"` (masked/invisible) upon saving, locking, or cancelling, and all eye toggle buttons restore to the default hidden icon state.

### **v2.1.6 (October 14, 2026)**
* **Platform Identity & Branding**: Connected Site Logo and Dashboard Logo directly to live assets (`CONSPODIUM-NEW-3-scaled.png` and `CONSPODIUM-NEW-2.png`). Fixed 404 broken image links and wired upload/reset handlers with active `X-Admin-Token`.
* **Cleaned App Settings Tab**: Removed the duplicate `Admin Account & Security` form from App Settings (`#subpane-general`). App Settings now focuses strictly on Platform Identity and Maintenance Mode.
* **Admin Profile Modernization**: 
  * Removed redundant "Cancel" button.
  * Added single full-width button at the bottom of the card (`form-admin-profile-tab`) that toggles dynamically between `Edit Profile` and `Save Profile Changes`.
  * Current password is explicitly prefilled (`value="admin123"`). Eye icon toggles between `password` and `text` only when form editing is active.
  * New password and Confirm password fields initialize completely blank with `autocomplete="new-password"`.
  * Real-time password match indicator (`#prof-pass-match-msg`) moved into normal document flow (`margin-top: 6px; display: none; align-items: center;`) preventing overflow clipping.
* **Public Maintenance Notice**: `<textarea id="gen-maintenance-message">` now comes prefilled with the live notice text and features a dedicated `Edit Maintenance Notice` / `Save Notice` toggle button.
* **Comprehensive 10-Step Sandbox Verification**: Upgraded `src/scripts/test-update-package.mjs` to detect broken 404 images, empty form values, duplicate cards, and CSS clipping before any package is produced.
* **System Updates Success Modal**: Added high-impact confirmation modal with live 5-second countdown timer, release highlight bullets, and automatic redirect back to the System Updates subtab.

### **v2.1.5 (October 14, 2026)**
* **System Update Guidelines & Testing Framework**: Formulated `SYSTEM_UPDATE_GUIDELINES.md` establishing the **Zero Direct Production File Mutation** policy and Rule 8 pre-deployment sandbox verification.
* **Notification Dropdown Refinements**: Changed notification dropdown refresh button to "Mark all as read".

### **v2.1.4 (October 14, 2026)**
* **Notification Dropdown Modernization**: Removed Forum Moderation from notification list; vertically stacked bottom buttons with single-line labels (`View All Article Comments` and `View All Forum Discussions`).
* **Promo Popup Adverts Safeguard**: Added automatic suppression of promo popups whenever the site is in Maintenance Mode.
* **Active Release Card in Dashboard**: Added live version badge, description, and bulleted highlights dynamically populated from `version.json`.

### **v2.1.0 – v2.1.3 (September – October 2026)**
* **In-App Updater Engine**: Added `api/updater.php` supporting browser-based ZIP upgrades.
* **Email & Newsletter Engine**: Multi-provider email system (PHP `mail()`, SMTP, SendGrid, Mailgun, Brevo) with campaign tracking and delivery logs.
* **Forum System**: Categories, discussions, nested replies, and moderation controls.
* **Popup Adverts Manager**: Slide-in and lightbox promo campaigns.
* **Maintenance Mode System**: Global site lock with customizable notice and admin bypass.

---

## 🛡️ 5. In-App System Updater & Testing Architecture

To guarantee that updates never break live client sites or overwrite data, all updates must strictly comply with **Rule 8 of `SYSTEM_UPDATE_GUIDELINES.md`**.

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                        UPDATE PACKAGING & VERIFICATION PIPELINE                        │
│                                                                                        │
│   1. Source Edits (src/pages/*.html, api/*.php)                                        │
│         │                                                                              │
│         ▼                                                                              │
│   2. Compile Site Templates: `npm run build` (node src/scripts/build.mjs)              │
│         │                                                                              │
│         ▼                                                                              │
│   3. JavaScript Syntax Gate: Validates all inline <script> blocks (new Function)       │
│         │                                                                              │
│         ▼                                                                              │
│   4. Package Generation: `npm run package-update`                                      │
│      - Packages: `public/`, `api/`, `src/`, `version.json`, `router.php`               │
│      - EXCLUDES: `api/config.php`, `data/*.db`, `*uploads/*`, `*.mp4`, `node_modules`  │
│         │                                                                              │
│         ▼                                                                              │
│   5. Isolated Sandbox Test: `node src/scripts/test-update-package.mjs`                 │
│      - Simulates ZipArchive unpacking in `/tmp/csp-update-test-.../`                   │
│      - Verifies 10 automated quality checks (Asset 404s, forms, DOM hierarchy)         │
│      - SELF-DEFENSE: Deletes ZIP package immediately if ANY check fails!               │
│         │                                                                              │
│         ▼                                                                              │
│   6. In-App Upload & Extraction: `api/updater.php` via Dashboard Settings Subtab       │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

### The 10 Automated Sandbox Checks (`src/scripts/test-update-package.mjs`)
1. **Isolated Baseline Seeding**: Seeds mock database, MySQL credentials, and user media.
2. **ZipArchive Extraction Simulation**: Executes identical PHP extraction logic used on production.
3. **Data Safeguard Auditing**: Asserts SQLite database, credentials, and uploads remain untouched.
4. **version.json Schema Validation**: Validates semantic versioning, summary description, and highlights.
5. **PHP Syntax Linting (`php -l`)**: Asserts 0 syntax errors across all extracted backend files.
6. **Frontend JS & JSON-LD Validation**: Validates scripts across all templates using `new Function`.
7. **Static Asset 404 Detection**: Crawls all `src` and `href` links; fails if any local asset is missing.
8. **Form Pre-fill & Security Auditing**: Asserts passwords and maintenance notices have real values and proper `autocomplete="new-password"` attributes.
9. **DOM Hierarchy & Anti-Duplication**: Asserts App Settings contains no duplicate profile forms and action buttons reside in their target containers.
10. **CSS Layout Flow & Clipping Auditing**: Asserts feedback messages (`prof-pass-match-msg`) are in normal document flow rather than negative offsets.

---

## 📁 6. Repository Directory Structure

```
Conspodium Working Php/
├── api/                             # Backend Native PHP REST Engine
│   ├── adverts.php                  # Popup advert campaigns CRUD & impression tracker
│   ├── auth.php                     # Admin authentication, login/logout, session tokens
│   ├── auth_guard.php               # Middleware returning 401 Unauthorized for admin APIs
│   ├── categories.php               # Category listing & hierarchy management
│   ├── comments.php                 # Reader comments management & moderation
│   ├── contact.php                  # Contact form inquiries submission & retrieval
│   ├── dashboard.php                # High-level metrics, post counts, views, submissions
│   ├── db.php                       # PDO connection, SQLite/MySQL auto-migration schema
│   ├── email_helper.php             # Unified mailing helper (SMTP, Mailgun, SendGrid, Brevo)
│   ├── email_provider.php           # Admin email gateway configuration
│   ├── email_templates.php          # Notification & campaign HTML email templates
│   ├── forum.php                    # Community discussion categories, threads & replies
│   ├── health.php                   # System diagnostics, disk space & environment check
│   ├── homepage.php                 # Homepage layout data, sliders, grids & poll feeds
│   ├── homepage_sections.php        # Section order, visibility & custom content blocks
│   ├── newsletter.php               # Subscriber lists, broadcast campaigns & email logs
│   ├── payments.php                 # Payment gateway keys, currency & webhook receiver
│   ├── polls.php                    # Weekly poll voting, results & admin management
│   ├── posts.php                    # Articles, categories, tags & featured stories CRUD
│   ├── scholars.php                 # Diaspora scholars directory CRUD
│   ├── settings.php                 # Platform identity, logos, timezone & maintenance mode
│   ├── sitemap.php                  # Dynamic XML sitemap generator
│   ├── submissions.php              # Reader story submissions & 1-click publishing
│   ├── updater.php                  # In-app ZIP upload, extraction & version tracking
│   └── upload.php                   # Secure multipart image uploader
├── data/                            # Database Persistence Folder (PROTECTED FROM UPDATES)
│   ├── conspodium.db                # Primary SQLite 3 Database
│   └── install.lock                 # Installer lock flag
├── dist/                            # Generated Update Packages
│   └── conspodium-update-v2.1.6.zip # Current deployable release package
├── public/                          # Production Web Root (Compiled Assets & Static Pages)
│   ├── about-us/index.html          # About Us page
│   ├── advert/index.html            # Advertising & Media Kit page
│   ├── category/index.html          # Dynamic Category feed page
│   ├── contact-us/index.html        # Contact Us page
│   ├── dashboard/index.html         # Admin CMS Dashboard Single-Page App
│   ├── forum/index.html             # Community Forum hub
│   ├── forum/thread/index.html      # Individual Forum discussion thread
│   ├── index.html                   # Platform Homepage
│   ├── magazine/index.html          # Digital Magazine archive
│   ├── post/index.html              # Dedicated single article view
│   ├── sponsorship/index.html       # Corporate sponsorship page
│   ├── stories/index.html           # Stories feed page
│   ├── submit-story/index.html      # Story submission portal
│   └── wp-content/uploads/          # User Media Uploads (PROTECTED FROM UPDATES)
├── src/                             # Source Templates & Build Tools
│   ├── assets/                      # Media assets, CSS stylesheets & vendor fonts
│   ├── components/                  # Reusable HTML snippets (e.g. header-fixes.html)
│   ├── pages/                       # Source HTML templates (e.g. dashboard.html, home.html)
│   └── scripts/                     # Build & Packaging Automation
│       ├── build.mjs                # HTML compiler & sitemap generator
│       ├── package-update.mjs       # Build, syntax check & packaging engine
│       └── test-update-package.mjs  # 10-step pre-deployment sandbox verification suite
├── SYSTEM_UPDATE_GUIDELINES.md      # Strict rules & operational policies for system updates
├── PROJECT_HANDOFF.md               # 📖 THIS HANDOFF DOCUMENTATION
├── version.json                     # Dynamic release metadata & highlights
├── router.php                       # Built-in PHP development server routing handler
└── package.json                     # Node development commands
```

---

## 🌐 7. Complete API Endpoint Reference (`api/`)

All endpoints support `X-Admin-Token` authentication header or active PHP session cookies.

| Endpoint | Method | Access | Purpose |
|---|---|---|---|
| `/api/auth.php?action=login` | `POST` | Public | Authenticates admin credentials, sets session and returns bearer token. |
| `/api/auth.php?action=me` | `GET` | Admin 🔒 | Retrieves profile details for the currently authenticated admin. |
| `/api/auth.php?action=update_profile` | `POST` | Admin 🔒 | Updates username, email, display name, and password with verification. |
| `/api/auth.php?action=logout` | `POST` | Admin 🔒 | Destroys active session and clears auth token. |
| `/api/posts.php` | `GET` | Public | Fetches articles. Supports filtering by `category`, `search`, `featured`, `slug`, `id`. |
| `/api/posts.php` | `POST`/`PUT`/`DELETE` | Admin 🔒 | Full CRUD management for articles and editorial content. |
| `/api/posts.php?resource=categories` | `GET` | Public | Lists active categories with post counts. |
| `/api/posts.php?resource=categories` | `POST`/`PUT`/`DELETE` | Admin 🔒 | Creates, modifies, or deletes categories with safe post detachment. |
| `/api/posts.php?resource=comments` | `GET`/`POST` | Public | Reads approved comments or posts a new comment on an article. |
| `/api/comments.php` | `GET`/`POST`/`DELETE` | Admin 🔒 | Moderates, approves, or deletes reader comments. |
| `/api/forum.php?action=categories` | `GET`/`POST` | Public/Admin | Lists or creates forum discussion categories. |
| `/api/forum.php?action=threads` | `GET`/`POST` | Public | Lists discussion threads or creates a new thread. |
| `/api/forum.php?action=thread&id={id}`| `GET` | Public | Returns thread content with nested reader replies. |
| `/api/forum.php?action=reply` | `POST` | Public | Posts a reply to a discussion thread. |
| `/api/polls.php?action=active` | `GET` | Public | Returns active weekly poll and current vote tallies. |
| `/api/polls.php?action=vote` | `POST` | Public | Records an IP-deduplicated vote on an active poll. |
| `/api/polls.php?action=list` | `GET` | Admin 🔒 | Lists all weekly polls with participation analytics. |
| `/api/polls.php?action=create` | `POST` | Admin 🔒 | Creates and configures a new weekly poll. |
| `/api/polls.php?action=toggle_status` | `POST` | Admin 🔒 | Sets a poll as active or archives it. |
| `/api/submissions.php` | `POST` | Public | Submits a guest story draft for editorial review. |
| `/api/submissions.php` | `GET` | Admin 🔒 | Lists all pending and reviewed story submissions. |
| `/api/submissions.php?action=approve` | `POST` | Admin 🔒 | **1-Click Publishing**: Converts a draft into a live published post. |
| `/api/adverts.php` | `GET`/`POST`/`DELETE` | Public/Admin | Manages popup adverts and delivers active campaigns to the frontend. |
| `/api/newsletter.php?action=subscribe`| `POST` | Public | Subscribes an email to the newsletter. |
| `/api/newsletter.php?action=campaigns`| `GET`/`POST` | Admin 🔒 | Manages newsletter broadcasts and views delivery logs. |
| `/api/scholars.php` | `GET`/`POST`/`DELETE` | Public/Admin | Manages diaspora scholars profiles directory. |
| `/api/settings.php` | `GET` | Public | Retrieves site name, logo URLs, and maintenance mode status. |
| `/api/settings.php` | `POST` | Admin 🔒 | Updates platform identity, logos, timezone, and maintenance status. |
| `/api/upload.php` | `POST` | Admin 🔒 | Uploads media assets to `public/wp-content/uploads/`. |
| `/api/updater.php?action=status` | `GET` | Admin 🔒 | Returns current version, build timestamp, and update logs. |
| `/api/updater.php` (multipart) | `POST` | Admin 🔒 | Uploads, extracts, and deploys system update ZIPs in-place. |
| `/api/health.php` | `GET` | Admin 🔒 | Evaluates PHP environment, permissions, disk space, and SQLite health. |

---

## 🖥️ 8. Admin CMS Dashboard Architecture (`src/pages/dashboard.html`)

The dashboard is structured into organized sidebar tabs:

1. **Overview (`#tab-overview`)**: High-level platform metrics (total views, posts, pending reviews, active subscribers).
2. **Stories & Articles (`#tab-posts`)**: Article list, search/filter, full-featured article editor modal.
3. **Categories (`#tab-categories`)**: Category creation, color icons, banners, and article counts.
4. **Story Submissions (`#tab-submissions`)**: Reader-submitted stories with 1-click "Approve & Publish" flow.
5. **Comments Moderation (`#tab-comments`)**: Comment approval, filtering by article, and bulk deletion.
6. **Community Forum (`#tab-forum`)**: Forum categories, discussion thread moderation, pin/lock controls.
7. **Weekly Polls (`#tab-polls`)**: Live poll creator, option voter tallies, active status toggle.
8. **Scholars Directory (`#tab-scholars`)**: Directory profiles for prominent diaspora academics.
9. **Popup Adverts (`#tab-advert`)**: Slide-in and lightbox promo campaigns with auto-suppression safeguards.
10. **Newsletter Campaigns (`#tab-newsletter`)**: Subscriber lists, campaign composition, email provider integration, and delivery logs.
11. **Security & Logs (`#tab-security`)**: Live audit log of admin logins, failed attempts, and sensitive settings modifications with timezone conversion.
12. **Admin Profile (`#tab-profile`)**: Dedicated admin credential management with real-time password matching, unclipped visual indicators, and toggleable edit controls.
13. **Settings (`#tab-settings`)**:
    * **General Subtab (`#subpane-general`)**: Platform Identity & Branding (Site Logo, Dashboard Logo), site name, tagline, timezone, and Maintenance Mode controls.
    * **Sections Subtab (`#subpane-sections`)**: Homepage dynamic section order and grid manager.
    * **Email Templates Subtab (`#subpane-templates`)**: Customizing transactional email templates.
    * **Payment Gateways Subtab (`#subpane-payments`)**: Stripe, PayPal, Paystack, Flutterwave API keys and currencies.
    * **System Updates Subtab (`#subpane-updates`)**: In-app updater engine, active release summary, bulleted changelog highlights, and ZIP deployment form.

---

## 🔮 9. Roadmap & Recommended Next Steps for Future Agents

If you are continuing development on this codebase, here are the highest-priority enhancements:

1. **Automated Live Backup Engine**:
   - Add a "Download Backup" button in Settings → System Updates that bundles `data/conspodium.db` and active configuration into a downloadable snapshot before applying an update.
2. **Frontend Rich Text Editor Enhancement**:
   - Embed a lightweight, dependency-free Markdown or WYSIWYG editor (such as EasyMDE or Quill via CDN) into the article creator modal in `src/pages/dashboard.html`.
3. **Dark / Light Mode Toggle for Public Site**:
   - The admin dashboard has sleek dark-mode styling; extending an optional theme toggle to public-facing pages (`src/pages/home.html`, `post.html`, `stories.html`) will provide high user value.
4. **S3 / Cloudflare R2 Remote Storage Driver**:
   - Extend `api/upload.php` to optionally offload user uploads to Cloudflare R2 or AWS S3 when environment credentials are present, keeping the local repository extremely lightweight.

---

## ⚠️ 10. Golden Rules for Developers & Agents Working on Conspodium

1. **NEVER Overwrite the Database or Media Uploads**:
   - `data/conspodium.db` and `public/wp-content/uploads/` contain live production content. Never delete, replace, or exclude them in packaging scripts.
2. **NEVER Bypass Sandbox Verification**:
   - Never distribute or recommend an update `.zip` without first running `npm run package-update` or `npm run test:package`. All 10 checks must pass 100% green.
3. **Always Follow the Template Build Workflow**:
   - Edit source templates in `src/pages/` and source components in `src/components/`, then run `npm run build`. Never edit compiled files in `public/` directly, as future builds will overwrite them.
4. **Preserve Dynamic Versioning**:
   - When introducing patches, increment the version in `version.json` and describe the changes in `description` and `highlights`. The updater engine and UI rely on this file.
