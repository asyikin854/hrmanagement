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

## Making a change

1. Edit the file (both the Bahasa Malaysia and English page if the text appears in both).
2. Update `<lastmod>` for that page in `sitemap.xml`.
3. Commit and push to `main`. Plesk deploys it automatically.

## Deployment (Plesk Git)

See `DEPLOY.md`.
