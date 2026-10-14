# Conspodium System Update & Packaging Guidelines

> **CRITICAL RULE**: Never package unnecessary media, videos, or unrelated files into an update package. Every update must be lean, intentional, and scoped strictly to the modified code files.

---

## 1. Core Principles

1. **Lightweight First (Target: < 1 MB)**
   - Routine code/UI updates, bug fixes, and feature additions must **never** drag along large media files.
   - All video files (`*.mp4`, `*.webm`, `*.mov`), image dumps, and raw assets must be excluded by default.
   - Code updates are typically between **100 KB and 500 KB**. If a patch exceeds 2 MB, inspect the contents immediately.

2. **Every Update Carries a Distinct Incrementing Version & Plain-English Notes**
   - Every update package must increment `version.json` (e.g. `2.1.2` → `2.1.3`).
   - The package must include a human-readable `highlights` or `notes` array in `version.json`.
   - The updater UI must display plain-English bullet points explaining what changed, rather than raw technical file paths (`public/index.html`).
   - The left-side **System Status & Integrity** card must dynamically update with the new version and release notes.

3. **Maintenance Mode Integrity: Never Show Marketing Popups**
   - Marketing popup adverts, newsletter modals, and floating promotions must be **strictly suppressed** when maintenance mode is active.
   - Visitors on the maintenance screen must only see the clean maintenance notice.

4. **Never Overwrite User Data or Credentials**
   - The following are strictly forbidden in any update package and are protected by `api/updater.php`:
     - Database files (`data/conspodium.db`, `*.sqlite`, `*.db`)
     - Database configuration (`api/config.php`, `data/config.php`, `config.php`, `.env`)
     - User media uploads (`public/wp-content/uploads/`, `public/uploads/`)
     - System backups (`data/backups/`)

5. **Codebase Integrity During Live Testing**
   - When preparing a test update package to verify the in-app updater, **do not alter the active local/production files on disk beforehand**.
   - Always build the target package in an isolated staging location (`/tmp/.../`) so the user's active screen remains in the baseline state until they click **"Deploy & Apply Update"**.

6. **Mandatory Pre-Package JavaScript Syntax Validation**
   - Every build and update package generation script (`package-update.mjs`) must automatically validate the JavaScript syntax of all `<script>` tags across built pages before packaging.
   - If any syntax error is detected, the packager will abort immediately with an error and block zip creation, guaranteeing that no broken script can ever be shipped in an update.

---

## 2. version.json Schema Specification

Every update package must include a `version.json` structured as follows:
```json
{
  "version": "2.1.3",
  "build_timestamp": "YYYY-MM-DD HH:MM:SS",
  "description": "Short one-sentence summary of the update",
  "author": "Conspodium Core Team",
  "type": "lightweight_code_patch",
  "highlights": [
    "Plain-English point 1 describing what changed",
    "Plain-English point 2 describing what changed",
    "Plain-English point 3 describing what changed"
  ]
}
```

---

## 2. Packaging Modes

### Mode A: Lightweight Incremental Patch (Default)
Used for bug fixes, styling improvements, and feature updates.
- Package contains **only** the modified files maintaining their relative paths:
  ```text
  ├── public/
  │   └── dashboard/index.html   (or whichever pages were modified)
  ├── api/
  │   └── auth.php              (or whichever controllers were modified)
  └── version.json              (incremented build version & timestamp)
  ```
- **Size:** Typically 100 KB – 500 KB.
- **Upload Speed:** Sub-second deployment.

### Mode B: Full System Release (Only When Requested)
Used for complete new major version releases (e.g. migrating to a new server).
- Excludes:
  - `*.mp4`, `*.webm`, `*.mov`
  - `public/wp-content/uploads/*`
  - `data/*.db`, `api/config.php`, `.env`
  - `node_modules/*`, `.git/*`, `.DS_Store`

---

## 3. How the In-App Updater Applies Changes

```
1. Admin Uploads ZIP (Dashboard → System Updates)
        │
        ▼
2. api/updater.php Authenticates Admin Session (requireAdmin)
        │
        ▼
3. ZipArchive Validates Package Integrity
        │
        ▼
4. isProtectedPath() Audits Every Entry
   ├─ If entry is database, .env, or user upload → SKIPPED
   └─ If entry is code file (HTML, PHP, JS, CSS) → APPROVED
        │
        ▼
5. In-Place Stream Extraction
   Target files are written directly to disk ($rootDir/...)
        │
        ▼
6. version.json Log Updated
   Records version, timestamp, updated count, and skipped count
        │
        ▼
7. Browser Refresh → Web server immediately serves updated files
```

---

### Rule 7: Dynamic Release Notes & Version Sync
- Every update package must carry an incremented semantic version (e.g., `2.1.3` → `2.1.4`) with plain-English release notes in `version.json`:
  ```json
  {
    "version": "2.1.4",
    "description": "Plain-English summary of changes",
    "highlights": [
      "Key improvement 1",
      "Key improvement 2"
    ]
  }
  ```
- Both `api/updater.php` and the frontend UI (`sys-update-summary-card`, `sys-update-highlights-list`) dynamically display these notes so administrators can immediately see what changed.
- `src/scripts/package-update.mjs` must never hardcode a static version; it must dynamically read `version.json` and generate `conspodium-update-v<version>.zip`.

---

### Rule 8: Mandatory Pre-Deployment Sandbox Testing

Every update `.zip` package must be tested and verified inside an **isolated sandbox test environment** before it is ever handed to an administrator or uploaded to live hosting.

#### How the Automated Sandbox Testing Environment Works:
When `npm run package-update` or `npm run test:package` runs, the automated test harness (`src/scripts/test-update-package.mjs`):
1. **Creates an Isolated Temporary Sandbox Directory** in the OS temp directory (`/tmp/csp-update-test-<timestamp>/`).
2. **Seeds Baseline Production Safeguards:**
   - Populates mock database (`data/conspodium.db`)
   - Populates mock credentials (`api/config.php`)
   - Populates mock user uploads (`public/wp-content/uploads/avatar.png`)
3. **Simulates In-App Unpacking via PHP `ZipArchive`:**
   - Runs the identical extraction routine used by `api/updater.php`.
   - Audits that forbidden database, credentials, and uploads are never overwritten.
4. **Executes 10 Strict Automated Verifications in Sandbox:**
   - **Safeguard Integrity:** Confirms SQLite database, database credentials, and media uploads remain 100% untouched.
   - **version.json Schema:** Validates semantic version syntax, non-empty description, and bulleted highlights.
   - **Backend PHP Linting (`php -l`):** Lints every extracted `.php` file to guarantee 0 syntax errors.
   - **Frontend JavaScript & JSON Validation:** Parses all `<script>` tags across all HTML pages (using `new Function` for JS and `JSON.parse` for JSON-LD/importmap/speculationrules) to ensure zero syntax or parse errors.
   - **Static Asset Existence & 404 Detection:** Scans all extracted HTML DOM elements for local asset links (`<img src="...">`, `<link href="...">`) and asserts that every referenced file exists on disk. Any 404 asset aborts and deletes the package.
   - **Form Pre-fill & Value Verification:** Audits required prefilled inputs (`prof-tab-current-pass`, `gen-maintenance-message`) to guarantee they carry actual values/inner text (not empty placeholders) and enforces security attributes (`autocomplete="new-password"`).
   - **DOM Hierarchy & Anti-Duplication Audit:** Verifies that no stray cards exist in unauthorized tabs (e.g. asserts that App Settings contains no duplicate Admin Profile form) and checks that action buttons reside within their correct forms.
   - **Visual Layout Flow & Clipping Auditing:** Verifies that dynamic indicators (such as real-time password matching `prof-pass-match-msg`) are rendered in normal document flow rather than negative offsets that cause overflow clipping.
   - **Interactive State Cleanup:** Confirms conflicting elements (e.g. redundant Cancel buttons on dynamic edit/save toggles) are eliminated.
   - **Admin Status Hooks:** Asserts that required UI element IDs (`sys-version-badge`, `sys-update-summary-desc`, `sys-update-highlights-list`) exist and render properly.
5. **Self-Defense Deletion on Failure:**
   - If **any** test fails, the zip package is **automatically deleted from disk** to prevent accidental distribution.
6. **Cleans Up Sandbox:**
   - Temporary test directories are completely removed after execution.

#### Commands:
- To build, package, and auto-test:
  ```bash
  npm run package-update
  ```
- To re-test an existing update zip package at any time:
  ```bash
  npm run test:package [optional-path-to-zip]
  ```

---

## 4. Pre-Flight Checklist Before Delivering Any Update Package
- [ ] Did I inspect the zip with `unzip -l`?
- [ ] Are all `.mp4`, `.webm`, and heavy user uploads excluded?
- [ ] Did I increment the version number and write plain-English release highlights in `version.json`?
- [ ] Did automated JavaScript syntax checks pass on all templates before packaging?
- [ ] Did static asset checks confirm 0 broken images / 0 404 references?
- [ ] Did form audits verify that pre-filled inputs have actual non-empty values?
- [ ] Did DOM hierarchy audits confirm no duplicate or misplaced cards?
- [ ] Did the package pass the isolated sandbox test runner (`npm run test:package`) with 100% green checks across all 10 suites?
- [ ] Did I ensure the left-hand status card and right-hand deployment logger display release notes clearly?
