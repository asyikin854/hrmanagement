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
