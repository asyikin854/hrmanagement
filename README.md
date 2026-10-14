# hrmanagement.esrmy.com

Website for Excellent Synergy Resources: HR, payroll and finance system, with the free PCB calculator and EA form guide, in Bahasa Malaysia and English.

Plain static HTML. No build step: what is in this repository is exactly what the server serves.

## Pages

| Page | Bahasa Malaysia | English |
|---|---|---|
| Home | `/` (`index.html`) | `/en/` |
| PCB calculator | `/kalkulator-pcb/` | `/en/pcb-calculator/` |
| EA form guide | `/panduan-borang-ea/` | `/en/ea-form-guide/` |

Shared files: `assets/css/style.css`, `assets/js/main.js` (calculator and WhatsApp/email forms), `assets/img/`.

## Demo

`/demo/hr/` and `/demo/finance/` are the HR and Finance apps running with a made-up sample company. Both pages switch between BM and EN themselves (`?lang=ms` or `?lang=en`).

- `demo/demo.js`: demo banner, language from the link, the "book a demo" message shown by every AI button, and browser downloads.
- `demo/hr/seed.js`, `demo/finance/seed.js`: the sample data. Everything in them is fictional. Never put real staff, customer or company data here: this repository and the website are public.
- `demo/hr/index.html`, `demo/finance/index.html`: copies of the apps with small hooks that load the sample data. Nothing is saved or sent anywhere; a refresh resets the demo.

The demo pages are marked `noindex` and are not in `sitemap.xml`.

## Making a change

1. Edit the file (both the Bahasa Malaysia and English page if the text appears in both).
2. Update `<lastmod>` for that page in `sitemap.xml`.
3. Commit and push to `main`. Plesk deploys it automatically.

## Deployment (Plesk Git)

See `DEPLOY.md`.
