# 🏛️ Conspodium — Project Handoff & Technical Documentation

> **Last Updated**: August 9, 2026  
> **Repository**: `https://github.com/Stevingfelix/Conspodium-Crawled.git`  
> **Branch**: `main` (Fully Synchronized)

---

## 📌 Executive Summary

**Conspodium** is a premium digital media platform dedicated to storytelling, culture, innovation, and community across the African diaspora.

The platform has been completely converted from a Node.js/Express prototype into a **high-performance, native PHP PDO + SQLite architecture**. It features zero production npm dependencies, making it 100% compatible with any shared hosting environment (cPanel, LiteSpeed, Apache, Nginx) while maintaining a dynamic Admin CMS Dashboard and public-facing APIs.

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
│  - SQLite 3 Database: `data/conspodium.db` (Auto-migrated 7 relational tables)         │
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
│   ├── posts.php                    # Articles & Categories CRUD API
│   ├── submissions.php              # Story submission & Admin Approve/Publish flow
│   └── upload.php                   # Multipart image upload handler
├── backup_express_node/             # ⚠️ Express Node.js Prototype Backup
│   ├── backend/                     # Original Express route handlers & db setup
│   ├── serve.js                     # Original Node server
│   ├── build.js                     # Original build script
│   └── package.json                 # Original Node dependencies
├── data/                            # Database Persistence Folder
│   ├── conspodium.db                # SQLite 3 Database File
│   └── install.lock                 # Security installer lock file (gitignored)
├── public/                          # Production Build Target Directory (web root)
│   ├── api/                         # Production PHP API scripts
│   ├── dashboard/index.html         # Admin CMS Dashboard Interface
│   ├── index.html                   # Website Homepage
│   ├── stories/index.html           # Stories Feed Page
│   ├── submit-story/index.html      # User Story Submission Form
│   ├── install.php                  # Web Installation Wizard (404 locked)
│   └── uploads/                     # Uploaded media assets
├── src/                             # Source Templates & Assets
│   ├── assets/                      # WP Content media & styling assets
│   ├── components/                  # Shared HTML header fixes
│   ├── pages/                       # Raw source HTML pages
│   └── scripts/                     # Build scripts (build.js)
├── install.php                      # 1-Click Shared Hosting Web Installer
├── package.json                     # Development scripts & server launchers
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
| `GET` | `/api/posts.php` | Public | Fetches articles (filter by `category`, `search`, `featured`) |
| `POST` | `/api/posts.php` | Admin 🔒 | Creates a new article |
| `PUT` | `/api/posts.php?id={id}` | Admin 🔒 | Updates an existing article |
| `DELETE` | `/api/posts.php?id={id}` | Admin 🔒 | Deletes an article |
| `GET` | `/api/posts.php?resource=categories` | Public | Lists categories with live post counts |
| `POST` | `/api/posts.php?resource=categories` | Admin 🔒 | Creates a new category |
| `GET` | `/api/polls.php?action=active` | Public | Retrieves active weekly poll & voting options |
| `POST` | `/api/polls.php?action=vote` | Public | Casts a vote (IP deduplicated) |
| `POST` | `/api/polls.php?action=create` | Admin 🔒 | Creates a new weekly poll |
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
2. **Pagination UI**: Add page number controls to `src/pages/stories.html` for navigating large article lists.
3. **Rich Text Formatting**: Enhance the Article Editor textarea in `src/pages/dashboard.html` with a lightweight WYSIWYG editor (e.g. Quill or SimpleMDE) if rich HTML formatting is desired.
