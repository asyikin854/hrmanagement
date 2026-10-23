# Project context for Claude Code

This repository is the public marketing website for **Excellent Synergy Resources** (ESR), served at
**https://hrmanagement.esrmy.com**. Remote: `git@github.com:asyikin854/hrmanagement.git`, branch `main`.
Plesk (Git extension, automatic deployment via GitHub webhook) deploys every push to `main` straight to the
subdomain's web root, so **everything in this repo is public**. Never commit secrets, customer data or drafts.

## What the business sells
An HR, payroll and finance system for SMEs in Malaysia and Singapore, with AI built in. Price: **RM1,999 a year** or **RM550 every 3 months** (yearly saves RM201).
Contact: WhatsApp/phone **011-1632 0694** (wa.me/601116320694), email **work@esrmy.com**.
Features to promote (keep claims factual; don't invent security certifications, customer counts or free trials):
- HR: employee profiles, org chart, CSV import, leave with balances and team calendar, recruitment board with a
  public careers page, onboarding/offboarding checklists, goals and review cycles.
- Attendance: staff clock in on their phone with location; late/absent marked automatically.
- Payroll: Malaysia (EPF, SOCSO, EIS, PCB) and Singapore (CPF); payslips; claims paid in payroll.
- Finance: invoices and bills with GST, bank statement upload (PDF/photo/CSV) read by AI and matched to the books,
  bank reconciliation, P&L, balance sheet, cash flow, aged receivables, GST summary.
- LHDN: PCB/CP39 data, EA forms, Form E (C.P.8D list), CP204 instalments and Form C tracking.
- AI: CV summaries and fit scores, retention risk reads, job description writer, review drafts, payroll checks,
  bank transaction categorising, "Ask HR" and "Ask Finance" chat.
The HR system ("Roster") and Finance app run as Claude artifacts. Public demo copies with fictional sample data live in
`demo/` (see README). The demo HTML is the artifact code plus small hooks (seed data, demo banner, AI buttons show a
"book a demo" message); when the apps change, rebuild the demo copies from the new artifact code and re-apply the hooks.
Never copy data from the real artifacts' databases into this repo: it holds real staff records and clock-in locations.
The real HR artifact's payroll still uses Singapore CPF. The demo copy replaces it with Malaysian KWSP (EPF), PERKESO
(SOCSO), SIP (EIS) and PCB using the same rules as the PCB calculator below (resident, individual relief only), and
renames the CPF labels, settings and onboarding steps. Keep it that way when rebuilding the demo.

## Site structure (plain static HTML, no build step)
| Page | Bahasa Malaysia (default, x-default) | English |
|---|---|---|
| Home | `index.html` (`/`) | `en/index.html` (`/en/`) |
| Free PCB calculator | `kalkulator-pcb/index.html` | `en/pcb-calculator/index.html` |
| Free EA form guide | `panduan-borang-ea/index.html` | `en/ea-form-guide/index.html` |
Shared: `assets/css/style.css`, `assets/js/main.js` (PCB calculator + lead forms), `assets/img/` (screenshots as
`<name>-light.webp` / `<name>-dark.webp` per language folder `ms/` and `en/`, `logo.png`, `og-ms.png`, `og-en.png`,
icons), `sitemap.xml`, `robots.txt`.

## Rules when editing
- **Both languages, always.** Any text change goes into the BM page and its English counterpart. Bahasa Malaysia uses
  standard Malaysian HR/tax terms: KWSP (EPF), PERKESO (SOCSO), SIP (EIS), PCB, LHDN, Borang EA, Borang E, cuti,
  gaji, daftar masuk, pengambilan pekerja.
- **SEO must stay intact:** each page keeps its own `<title>` (≤60 chars), meta description (≤160), canonical,
  hreflang alternates (ms-MY, en, x-default → BM), Open Graph/Twitter tags and JSON-LD (Organization,
  SoftwareApplication + FAQPage on home, WebApplication on the calculator, Article on the EA guide, BreadcrumbList on
  tool pages). FAQ JSON-LD must match the visible FAQ text exactly. Text must be in the HTML, not injected by JS.
- New page → add it to `sitemap.xml` with hreflang links; update `<lastmod>` whenever a page changes.
- Subscriptions (CHIP, FPX): the Pricing section on both home pages has a 3-month (RM550) and a yearly (RM1,999)
  plan card. "Subscribe" links go to the form at `/langgan/` (BM) or `/en/subscribe/` (EN) with `?plan=quarterly|yearly`.
  The form posts to `api/chip-checkout.php`, which sets the price on the server, records the payment, creates a CHIP
  purchase and redirects to CHIP's payment page. CHIP returns customers to `/langganan-berjaya/` or `/en/subscription-confirmed/` on success,
  or back to the form with `?status=failed|cancelled` (`invalid`/`error` come from the PHP). These four pages are
  noindex and not in the sitemap. **The CHIP API key must never be committed** (this repo is public): the PHP reads
  `CHIP_API_KEY`/`CHIP_BRAND_ID` env vars or `chip-config.php` one folder above the web root (DEPLOY.md). If prices
  change, update `PLANS` in `app/lib/bootstrap.php`, the plan cards, both form pages, meta descriptions, FAQ (visible and JSON-LD)
  and the JSON-LD offers on both home pages.
- Customer accounts (PHP + MySQL, no build step): `app/lib/` (bootstrap: config, schema, sessions, mail; billing: CHIP
  verification and provisioning; api; layout), `api/chip-callback.php` (CHIP success_callback; re-fetches the purchase
  from CHIP, only "paid" counts), `app/` (customer login, account, team, `cron.php`), `app/api/` (data, session, ai,
  assets), `app/runtime.js` (gives the apps the `window.claude.use` interface backed by app/api), `admin/` (owner
  dashboard). Secrets live in `esr-config.php` and `chip-config.php` one folder above the web root, never here.
  Roles: owner/admin (full apps), staff (HR clock-in only), superadmin (dashboard). Every company's data is keyed by
  tenant_id; every query in app/api must stay scoped to the session user's tenant.
- `app/hr/app.html` and `app/finance/app.html` are generated from the demo copies by `python3 tools/build_app.py`;
  rebuild them whenever the demos are rebuilt. Plan prices live in `PLANS` in `app/lib/bootstrap.php`.
- Root `.htaccess`: www→main host and /index.html→/ redirects, blocks .md/.py, security headers (HSTS, nosniff), caching,
  `404.html`. A broken .htaccess takes the whole site down: test changes on Apache before pushing.
- Security rules that must hold: staff-written clock records are sanitised in `app/api/data.php` and escaped in the HR
  app's `whereCell` (the build checks this); AI calls reserve their slot before calling (`app/api/ai.php`) and are
  capped by requests and US$ per company; `process_purchase` checks our payments table before calling CHIP.
- HR is the single source of employees and payroll for customers. HR employees carry the PCB inputs (IC, tax no., EPF/
  SOCSO no., tax status, marital status, children, monthly zakat, TP3 earlier-income fields) and HR payroll (with a
  per-month bonus on draft runs, `run.bonuses`; KWSP on salary+bonus, PERKESO/SIP on salary only) uses the
  LHDN computerised method month by month (`pcbCalc`, same as Finance's). `app/api/data.php` gives Finance read-only
  copies as `hr_employees`/`hr_payroll`; Finance's `MYE()`/`MYP()` map them into its LHDN shapes (EA, CP39, Form E) and
  `postings()` posts each Finalized HR month (Dr 5000/5020/5010, Cr 2200 EPF+SOCSO+EIS, Cr 2300 PCB+zakat, Cr 1000 net)
  unless a journal with ref "Payroll YYYY-MM" exists. Finance keeps only PCB/statutory payment records in `my_payroll`.
  Without HR data (the public Finance demo) Finance falls back to its own `my_employees`/`my_payroll`.
- Lead capture has no backend: the forms open WhatsApp or email with the visitor's details filled in. Don't add
  scripts that send data elsewhere without the owner's agreement.
- Images: WebP, max ~1400px wide, with width/height and alt text in the page language; light and dark versions via
  `<picture>` + `prefers-color-scheme`.
- Design: fonts Bricolage Grotesque (headings) and Public Sans (body); colours pine `#2F6F62`, gold `#E8A52A`,
  ink `#16201E`, paper `#F6F4EE`, with dark-mode tokens in `style.css`. Logo red/gold is used only in the logo.
- Keep pages fast and mobile-friendly (no horizontal scroll at 390px).

## PCB calculator logic (assets/js/main.js) — keep accurate
Resident, normal monthly remuneration, full year, LHDN computerised method:
P = (Y−K1) + (Y−K2)×11 − reliefs; K1 = employee EPF (relief capped at RM4,000/yr), K2 = min(K1, (4000−K1)/11).
Reliefs: individual RM9,000; spouse RM4,000 if spouse not working (category K2); RM2,000 per child.
YA2025/2026 bands: 0–5k 0%, 5–20k 1%, 20–35k 3%, 35–50k 6%, 50–70k 11%, 70–100k 19%, 100–400k 25%,
400–600k 26%, 600k–2M 28%, >2M 30%; rebate RM400 (K1/K3) or RM800 (K2) if P ≤ RM35,000.
Monthly PCB = annual tax ÷ 12, truncated to 2dp then rounded up to 5 sen; under RM10 → 0; zakat deducted from PCB.
Non-residents: flat 30%. EPF employee 11%, employer 13% (≤RM5,000) / 12% (above); foreign workers 2%/2% (from
Oct 2025), EPF rounded up to the ringgit. SOCSO 0.5% ee / 1.75% er (foreign: 0 / 1.25%), EIS 0.2%/0.2%
(Malaysians only), both on wages capped at RM6,000. Check: RM6,000 single resident → PCB RM207.50.
If rates change (Budget announcements), update main.js, the explanation text on both calculator pages, and lastmod.

## EA / Form E facts used on the guide
EA (Form C.P.8A) to every employee, including leavers, by end of February; Form E (with C.P.8D) to LHDN by
31 March, usually 30 April via e-Filing. Present as general guidance and point readers to LHDN or a tax agent.

## Deploying
Commit and push to `main`; Plesk deploys automatically (see DEPLOY.md). Check https://hrmanagement.esrmy.com after.
