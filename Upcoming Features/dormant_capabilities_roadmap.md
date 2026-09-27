# Upcoming Features & Dormant Capabilities Roadmap

This document outlines backend systems, database models, and API endpoints already implemented in the Conspodium codebase that are scheduled for dedicated UI interfaces in future iterations.

---

## 1. Payment Transactions & Sponsorship Revenue Ledger

### Overview
Conspodium currently processes sponsorship and membership payments through Paystack and Stripe on `/sponsorship/`, automatically recording completed transactions into the database via `api/payments.php?action=record_transaction`.

### Existing Backend & Database Architecture
* **Table**: `payment_transactions`
  * Fields: `id`, `transaction_ref`, `gateway` (Paystack / Stripe), `amount`, `currency`, `customer_email`, `customer_name`, `tier_name`, `status`, `created_at`
* **API Handler**: `api/payments.php`
  * `action=record_transaction`: Verifies and logs payment references, amounts, and donor metadata. Automatically registers customers into the `sponsors` list segment in `subscribers`.
  * `action=public_config`: Supplies public gateway keys to frontend modals.
  * `action=settings` & `action=save_settings`: Configures gateway API keys.

### Upcoming UI Implementation Plan
- [ ] **Admin Dashboard Transactions Tab / Table**:
  - Add a dedicated "Transactions / Orders" sub-panel under App Settings or Marketing.
  - Data table displaying: Transaction Reference, Customer Name & Email, Sponsorship Tier, Amount ($/₦), Gateway badge (Paystack/Stripe), Status (`completed`), and Date.
- [ ] **Financial Summary KPIs**:
  - Total Revenue Collected ($/₦), Number of Active Sponsors, Average Contribution Size.
- [ ] **Export & Search**:
  - Date-range filter and 1-click CSV Export for financial bookkeeping.

---

## 2. Audio Podcast & Multimedia Interactive Transcripts

### Overview
Conspodium articles support multimedia enhancements including podcast audio episodes and timecoded transcripts. The database schema and article retrieval API already query and attach transcript metadata to story payloads.

### Existing Backend & Database Architecture
* **Table**: `transcripts`
  * Fields: `id`, `post_id`, `audio_url`, `duration`, `transcript_text`, `language`, `timestamps_json`, `created_at`
* **API Handler**: `api/posts.php`
  * Automatically checks `SELECT * FROM transcripts WHERE post_id = ?` when fetching any single post by slug or ID (`$post['transcript']`).

### Upcoming UI Implementation Plan
- [ ] **CMS Article Editor (`#modal-post`)**:
  - Add optional fields: `Podcast Episode Audio URL` (.mp3 link or upload), `Duration` (e.g. `24:15`), and `Full Transcript Text`.
- [ ] **Public Story Page (`/post/[slug]/`) Player**:
  - Render a sleek, glassmorphic sticky Audio Podcast Bar with Play/Pause, Progress scrub bar, and Playback Speed (1x, 1.25x, 1.5x).
  - Add a collapsible **"📜 Read Audio Transcript"** drawer with synchronized timecode highlights.

---

## 3. Newsletter Campaign Date Scheduling

### Overview
The Email Marketing system allows administrators to compose rich HTML newsletters, select target subscriber segments (All, Scholars, Sponsors, General, Attendees), and send dispatches.

### Existing Backend & Database Architecture
* **Table**: `email_campaigns`
  * Fields: `id`, `subject`, `target_list`, `sender_name`, `sender_email`, `content`, `status`, `scheduled_at`, `sent_at`, `recipients_count`, `created_at`
* **API Handler**: `api/newsletter.php`
  * `action=campaigns`: Lists email history and delivery metrics.
  * `action=duplicate_campaign`: Clones past campaigns for re-use.
  * `action=send_campaign`: Supports handling `scheduled_at` timestamps for automated background dispatch.

### Upcoming UI Implementation Plan
- [ ] **Broadcast Scheduler Modal**:
  - Add a "Schedule for Later" toggle in the campaign composer modal.
  - DateTime picker (Date & Timezone selector) saving to `scheduled_at`.
  - Display "Scheduled" status badges with an option to edit or cancel before dispatch.

---

## Summary Table

| Feature | Backend Controller | Database Table | Planned Location |
| :--- | :--- | :--- | :--- |
| **Payment Transactions Ledger** | `api/payments.php` | `payment_transactions` | Dashboard → System & Configuration → Transactions Ledger |
| **Podcast & Text Transcripts** | `api/posts.php` | `transcripts` | Post Editor Modal & Single Post Hero Player |
| **Scheduled Email Campaigns** | `api/newsletter.php` | `email_campaigns` | Dashboard → Email Marketing → Campaign Composer |
