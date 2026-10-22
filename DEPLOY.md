# Deploying to Plesk from GitHub

One-time setup. After this, every push to `main` updates hrmanagement.esrmy.com automatically.

## 1. Put this repository on GitHub

1. On GitHub, create a new **private** repository, for example `esrmy-website`. Don't add a README.
2. In a terminal, inside this folder:

```
git remote add origin https://github.com/YOUR-ACCOUNT/esrmy-website.git
git push -u origin main
```

(Or use GitHub Desktop: File > Add local repository > choose this folder > Publish.)

## 2. Connect Plesk to the repository

1. In Plesk, open **Websites & Domains** > **hrmanagement.esrmy.com** > **Git**.
2. Choose **Remote Git hosting**, and paste the repository's **SSH** address:
   `git@github.com:YOUR-ACCOUNT/esrmy-website.git`
3. Plesk shows an **SSH public key**. Copy it.
4. On GitHub: repository > **Settings** > **Deploy keys** > **Add deploy key**. Paste the key, leave "Allow write access" off, save.
5. Back in Plesk, set:
   - **Deployment mode:** Automatic
   - **Deployment path:** the subdomain's document root (Plesk suggests it, usually `hrmanagement.esrmy.com` or `httpdocs`)
   - Branch: `main`
6. Click **OK**. Plesk pulls the files and deploys them.

## 3. Deploy on every push (webhook)

1. In Plesk's Git screen for this repository, open **Repository settings** and copy the **Webhook URL**.
2. On GitHub: **Settings** > **Webhooks** > **Add webhook**.
   - Payload URL: the Plesk webhook URL
   - Content type: `application/json`
   - Event: **Just the push event**
3. Save. Push any small change and check the site updates.

## 4. HTTPS

In Plesk, **SSL/TLS Certificates** for the subdomain > issue a free **Let's Encrypt** certificate, and turn on the redirect from http to https.

## 5. Google Search Console

1. Add the property `https://hrmanagement.esrmy.com/` (URL prefix).
2. Verify with the HTML tag: paste it into the `<head>` of `index.html` and `en/index.html`, commit, push, then click Verify.
3. Sitemaps > submit `sitemap.xml`.
4. URL inspection > request indexing for each page.

## 6. CHIP payments (subscriptions)

The subscribe form sends customers to CHIP to pay by FPX. The CHIP API key must stay on the server, never in GitHub.

1. In Plesk, open **Files** and go to the folder **above** the subdomain's document root (the folder that contains
   the `hrmanagement.esrmy.com` folder, not inside it).
2. Create a file named `chip-config.php` there with:

```php
<?php
return [
    'api_key'  => 'PASTE YOUR CHIP SECRET KEY HERE',
    'brand_id' => 'PASTE YOUR CHIP BRAND ID HERE',
];
```

   (Or set `CHIP_API_KEY` and `CHIP_BRAND_ID` as environment variables in Plesk's PHP settings instead.)
3. Check PHP is on for the subdomain: **Websites & Domains** > hrmanagement.esrmy.com > **PHP Settings** (PHP 8.1 or newer, with curl).
4. Test: open https://hrmanagement.esrmy.com/langgan/, fill in the form and press **Teruskan ke bayaran**. You should
   land on CHIP's FPX page. If you get "halaman bayaran tidak dapat dibuka", the key file is missing or wrong;
   Plesk's **Logs** show the reason (lines starting `chip-checkout:`).
5. Paid subscriptions appear in the CHIP dashboard, and CHIP emails the customer a receipt. Set up the customer's
   account and contact them.

## 7. Customer accounts, the apps and your dashboard

After a CHIP payment the customer's account is created automatically, and they get an email to set their password.
They log in at https://hrmanagement.esrmy.com/app/ to use the HR and Finance apps. You watch subscribers, payments
and revenue at https://hrmanagement.esrmy.com/admin/. This needs a database and one settings file, set up once.

1. **Database.** Plesk > **Websites & Domains** > hrmanagement.esrmy.com > **Databases** > **Add Database**.
   Type MySQL/MariaDB, any name (for example `esr_app`), and create a database user with a strong password.
   The tables are created automatically on first use.
2. **Settings file.** In **Files**, in the same folder as `chip-config.php` (Home directory, *not* inside
   `hrmanagement.esrmy.com`), create `esr-config.php`:

```php
<?php
return [
    'db' => ['host' => 'localhost', 'name' => 'esr_app', 'user' => 'DATABASE USER', 'pass' => 'DATABASE PASSWORD'],
    'owner_email' => 'work@esrmy.com',        // gets an email for every new subscriber and renewal
    'mail_from' => 'work@esrmy.com',          // sender of the customer emails
    'admin_setup_token' => 'MAKE UP A LONG RANDOM CODE',   // used once, at /admin/setup.php
    'cron_token' => 'MAKE UP ANOTHER LONG RANDOM CODE',    // protects the daily job URL
    'anthropic_api_key' => 'sk-ant-...',      // AI features; leave '' to switch AI off
    'ai_monthly_limit' => 300,                // AI requests per company per month
    // Optional, if emails from the server land in spam: send through your mailbox instead
    // 'smtp' => ['host' => 'mail.esrmy.com', 'port' => 587, 'user' => 'work@esrmy.com', 'pass' => 'MAILBOX PASSWORD'],
];
```

   The CHIP key stays in `chip-config.php`; it is read from there.
3. **Deploy** (Git > Pull now / Deploy now), then open https://hrmanagement.esrmy.com/admin/setup.php, enter the
   `admin_setup_token`, and create your dashboard login. That page stops working once your login exists.
4. **Daily job.** Plesk > **Scheduled Tasks** > **Add Task** > *Fetch a URL*, daily:
   `https://hrmanagement.esrmy.com/app/cron.php?token=<cron_token>`. It re-checks payments CHIP hasn't confirmed and
   emails customers 14 days before their subscription ends.
5. **Test.** In the dashboard, Subscribers > *Add a subscriber by hand*, with your own email. Check the welcome email
   arrives (and isn't in spam), set the password, open HR and Finance. If emails don't arrive, add the `smtp` block.
6. **AI key.** Create it at console.anthropic.com > API keys, and set a monthly spend limit there too.

Uploaded files (CVs) are kept in `esr-data/` next to the settings file, outside the website folder. Back up the
database and that folder (Plesk > Backup Manager).
