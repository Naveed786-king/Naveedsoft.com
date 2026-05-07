# Spectacular Eyewear Platform

A comprehensive WordPress plugin for managing an eyewear business with competition voting, affiliate tracking, and product showcase.

## Features

### Public Frontend (Shortcodes)
- **`[sep_showcase]`** — Eyewear showcase with 3D carousel, filters (price, style, size, category), and product grid
- **`[sep_competition]`** — Competition voting page with contestant grid, leaderboard, and countdown timer
- **`[sep_affiliate_dashboard]`** — Affiliate dashboard with stats, referral link, earnings, and withdrawal requests
- **`[sep_product_detail id="123"]`** — Single product detail view with gallery, color variants, and WhatsApp ordering
- **`[sep_leaderboard limit="10"]`** — Standalone leaderboard widget

### Admin Dashboard
- **Dashboard** — Overview with stats (products, contestants, votes, earnings, pending withdrawals), recent activity feed, and recent votes
- **Competition Control** — Add/edit/delete contestants, upload images, toggle voting open/closed, set countdown timer, reset votes
- **Affiliates** — Add/manage affiliates, track clicks, sign-ups, earnings, and referral codes. Export to CSV
- **Withdrawals** — View pending requests, approve/reject with notes, full history log
- **Inventory** — Add/edit/delete products with brand, price, category, style, size, stock, images, and featured flag
- **Reports** — Monthly votes, monthly earnings, top affiliates, full activity log with CSV export
- **Settings** — Currency, WhatsApp number, affiliate commission %, votes per user per day

### Business Logic
- **Voting System** — 1 vote per user per day (tracked by IP + cookie), real-time leaderboard updates
- **Affiliate Tracking** — Referral links (`?ref=CODE`), click deduplication (24h), cookie-based conversion tracking (30 days)
- **Withdrawal Processing** — Affiliates request withdrawals, admin approves/rejects, balance auto-updated
- **Activity Logging** — All actions logged for audit trail

## Installation

1. Upload the `spectacular-eyewear-platform` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress Plugins menu
3. Database tables are created automatically on activation
4. Go to **Spectacular → Settings** to configure currency, WhatsApp number, etc.
5. Create pages and add shortcodes to display the frontend

## Database Tables

The plugin creates 8 custom tables:
- `sep_products` — Product inventory
- `sep_contestants` — Competition contestants
- `sep_votes` — Vote records
- `sep_affiliates` — Affiliate accounts
- `sep_affiliate_clicks` — Click tracking
- `sep_affiliate_conversions` — Conversion/earnings records
- `sep_withdrawals` — Withdrawal requests
- `sep_activity_log` — System activity log

## Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+

## Currency

Default currency is UGX (Ugandan Shilling). Change in Settings.
