# 🏛️ Conspodium — Project Handoff & Technical Documentation

> **Last Updated**: August 10, 2026  
> **Repository**: `https://github.com/Stevingfelix/Conspodium-Crawled.git`  
> **Branch**: `main` (Fully Synchronized)

---

## 📌 Executive Summary

**Conspodium** is a premium digital media platform dedicated to storytelling, culture, innovation, and community across the African diaspora.

The platform has been completely converted from a Node.js/Express prototype into a **high-performance, native PHP PDO + SQLite architecture**. It features zero production npm dependencies, making it 100% compatible with any shared hosting environment (cPanel, LiteSpeed, Apache, Nginx) while maintaining a dynamic Admin CMS Dashboard, public-facing APIs, dynamic categories, full blog post pages, and real-time reader comments.

---

## ✨ Recent Major Accomplishments (August 10, 2026)

1. **Dedicated Full Blog Post Pages (`/post/<slug>/`) & Reader Comments Engine**:
   - Replaced modal popups with dedicated full blog post pages (`src/pages/post.html`) compiled to `/post/<slug>/` matching the original WordPress single article layout.
   - Features Category badge, Author meta, Date, Reading Time, Views Counter, Featured Image, Excerpt box, and Social Sharing buttons (Facebook, Twitter/X, LinkedIn, WhatsApp).
   - Related stories sidebar displays 4 related articles from the active category.
   - Added `comments` SQLite table and REST endpoints (`/api/posts.php?resource=comments`) allowing readers to post responses directly on articles.

2. **Dynamic Database Categories (`/category/<slug>/`) & Admin CMS Category Management**:
   - Created Light Theme Category page template (`src/pages/category.html`) compiled to `/category/<slug>/`.
   - Admin CMS Dashboard now supports creating dynamic categories with custom Icon, Description, and Banner Image upload/URL.
   - Safe category deletion unassigns associated posts (`UPDATE posts SET category_id = NULL WHERE category_id = ?`).

3. **Hero Slider Slow Zoom & Continuous Auto-Rotation**:
   - Restored dynamic database featured articles fetch handler in `src/pages/home.html`.
   - Updated Slide 1 featured photo to high-res `African-Diasporans-1536x864-1.jpg`.
   - Implemented continuous CSS `@keyframes csp-kenburns` zoom animation (`scale(1)` → `scale(1.12)` over 14s infinite alternate).
   - Removed mouse hover pause trap on the 100vh hero section so auto-advance rotates reliably every 6s.

4. **URL Routing & Navigation Path Resolution**:
   - Added wildcard rewrite rules in `vercel.json` for `/category/(.*)`, `/post/(.*)`, and `/story/(.*)`.
   - Fixed relative path nesting errors across `category.html`, `post.html`, `stories.html`, and `home.html` by standardizing on `getRootUrl(...)` absolute paths.
   - Added immediate script guards in `category.html` and `post.html` to intercept invalid paths (e.g. `/category/stories/` or `/post/stories/`) and redirect to `/stories/`.

---

## 🛠️ Technology Stack & System Architecture

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                FRONTEND USER INTERFACE                                 │
│  - HTML5 Semantic Architecture (src/pages/*.html compiled to public/)                 │
│  - CSS3 (Vanilla Glassmorphism, HSL dark mode, Merienda gradient typography)            │
│  - Vanilla JavaScript (ES6+ fetch API, zero client JS frameworks)                      │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                              BACKEND PHP PDO ENGINE (`api/`)                           │
│  - PHP 8+ PDO Database Engine (db.php)                                                 │
│  - Route Security Guard (auth_guard.php)                                               │
│  - Bcrypt Password Hashing (password_hash / password_verify)                           │
│  - Standalone REST Endpoints (posts.php, polls.php, submissions.php, upload.php, etc.) │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                DATABASE & MEDIA STORAGE                                │
│  - SQLite 3 Database: `data/conspodium.db` (Auto-migrated 8 relational tables)         │
│  - Image Upload Directory: `public/uploads/`                                           │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 📁 Repository Directory Structure

```
conspodium-clone/
├── api/                             # PHP PDO REST API Engine
│   ├── auth.php                     # Admin login, session check & logout API
│   ├── auth_guard.php               # Security middleware (401 Unauthorized guard)
│   ├── dashboard.php                # Admin CMS overview metrics API
│   ├── db.php                       # PDO SQLite connection, migration & seed data
│   ├── polls.php                    # Weekly poll fetching, voting & poll management
│   ├── posts.php                    # Articles, Categories & Comments CRUD API
│   ├── submissions.php              # Story submission & Admin Approve/Publish flow
│   └── upload.php                   # Multipart image upload handler
├── backup_express_node/             # ⚠️ Express Node.js Prototype Backup
├── data/                            # Database Persistence Folder
│   ├── conspodium.db                # SQLite 3 Database File
│   └── install.lock                 # Security installer lock file (gitignored)
├── public/                          # Production Build Target Directory (web root)
│   ├── api/                         # Production PHP API scripts
│   ├── category/index.html          # Dynamic Category Filter Page
│   ├── dashboard/index.html         # Admin CMS Dashboard Interface
│   ├── index.html                   # Website Homepage
│   ├── post/index.html              # Dedicated Full Blog Post Page
│   ├── stories/index.html           # Stories Feed Page
│   ├── submit-story/index.html      # User Story Submission Form
│   ├── install.php                  # Web Installation Wizard (404 locked)
│   └── uploads/                     # Uploaded media assets
├── src/                             # Source Templates & Assets
│   ├── assets/                      # WP Content media & styling assets
│   ├── components/                  # Shared HTML header fixes
│   ├── pages/                       # Raw source HTML pages (category.html, post.html, etc.)
│   └── scripts/                     # Build scripts (build.js)
├── install.php                      # 1-Click Shared Hosting Web Installer
├── package.json                     # Development scripts & server launchers
├── vercel.json                      # Serverless rewrite rules & routing configuration
└── PROJECT_HANDOFF.md               # 📖 THIS HANDOFF DOCUMENTATION
```

---

## 🔑 Admin CMS & Credentials

To access the Admin CMS Dashboard locally:
- **URL**: `http://localhost:8080/dashboard/`

### Valid Admin Credentials:
| Username | Password | Full Name | Account Type |
|---|---|---|---|
| **`admin`** | `conspodium2026` | Editor Admin | Default Fallback Admin |
| **`steving`** | `conspodium2026` | Steving Felix | Custom Installer Admin |

---

## 🚀 API Endpoint Reference (`api/`)

| Method | Endpoint | Access Level | Description |
|---|---|---|---|
| `POST` | `/api/auth.php?action=login` | Public | Authenticates admin credentials, sets `$_SESSION['admin_user']` |
| `GET` | `/api/auth.php?action=me` | Public | Returns currently logged-in admin user info |
| `POST` | `/api/auth.php?action=logout` | Admin | Destroys PHP session |
| `GET` | `/api/posts.php` | Public | Fetches articles (filter by `category`, `search`, `featured`, `slug`, `id`) |
| `POST` | `/api/posts.php` | Admin 🔒 | Creates a new article |
| `PUT` | `/api/posts.php?id={id}` | Admin 🔒 | Updates an existing article |
| `DELETE` | `/api/posts.php?id={id}` | Admin 🔒 | Deletes an article |
| `GET` | `/api/posts.php?resource=categories` | Public | Lists categories with live post counts |
| `POST` | `/api/posts.php?resource=categories` | Admin 🔒 | Creates a new category (supports icon, description & banner image) |
| `PUT` | `/api/posts.php?resource=categories&id={id}` | Admin 🔒 | Updates an existing category |
| `DELETE` | `/api/posts.php?resource=categories&id={id}` | Admin 🔒 | Deletes a category safely |
| `GET` | `/api/posts.php?resource=comments&post_id={id}` | Public | Lists approved comments for an article |
| `POST` | `/api/posts.php?resource=comments` | Public | Posts a new reader comment |
| `GET` | `/api/polls.php?action=active` | Public | Retrieves active weekly poll & voting options |
| `POST` | `/api/polls.php?action=vote` | Public | Casts a vote (IP deduplicated) |
| `POST` | `/api/polls.php?action=create` | Admin 🔒 | Creates a new weekly poll |
| `POST` | `/api/polls.php?action=toggle_status` | Admin 🔒 | Activates or deactivates a poll |
| `POST` | `/api/submissions.php` | Public | Submits a story draft for editorial review |
| `GET` | `/api/submissions.php` | Admin 🔒 | Lists all story submissions |
| `POST` | `/api/submissions.php?action=approve&id={id}` | Admin 🔒 | **Approve & Publish**: Converts story submission into a published article |
| `POST` | `/api/upload.php` | Admin 🔒 | Uploads image file to `/uploads/` |
| `GET` | `/api/dashboard.php` | Admin 🔒 | Returns total posts, total views, and pending submission counts |

---

## 🛡️ Security Architecture

1. **401 Unauthorized Protection (`api/auth_guard.php`)**:
   Calling any write/admin API without a valid PHP session returns `HTTP/1.1 401 Unauthorized`.
2. **Bcrypt Password Hashing**:
   All admin passwords are encrypted with PHP's native `password_hash(..., PASSWORD_DEFAULT)`.
3. **1-Click Web Installer (`install.php`) Hardening**:
   After installation, `install.php` creates `data/install.lock`. On subsequent visits, `install.php` responds with `HTTP/1.1 404 Not Found`, hiding the installer completely from web scanners.

---

## 💻 Local Development Commands

To run or rebuild the project locally:

```bash
# 1. Start PHP Development Server on http://localhost:8080
npm start
# OR
php -S localhost:8080 -t public

# 2. Rebuild static HTML pages from src/pages into public/
npm run build
```

---

## 🚢 Shared Hosting Deployment (cPanel / LiteSpeed / Apache)

1. Upload the contents of `public/` (or the entire project) to your web server (`public_html`).
2. Ensure directory write permissions on `data/` and `uploads/` (`chmod 775` or `777`).
3. Open `http://your-domain.com/install.php` in a web browser.
4. Enter your custom Admin credentials and click **Install Conspodium Now**.
5. Once complete, the installer locks automatically (`404 Not Found`), and your platform is live!

---

## 🔮 Suggested Future Enhancements for Next AI/Developer

If you are continuing work on this codebase, here are recommended next steps:
1. **Email Notifications**: Add `mail()` or PHPMailer integration in `api/submissions.php` to email the admin when a new story draft is submitted.
2. **Comment Moderation**: Add an Admin CMS tab in `src/pages/dashboard.html` to approve or delete reader comments.
3. **Rich Text Formatting**: Enhance the Article Editor textarea in `src/pages/dashboard.html` with a lightweight WYSIWYG editor (e.g. Quill or SimpleMDE) if rich HTML formatting is desired.

