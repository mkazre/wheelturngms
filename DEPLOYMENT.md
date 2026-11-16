# Deploying Wheelsturn GMS to https://app.wheelsturn.co.za (cPanel, no Terminal)

Everything below is done in cPanel's web interface. Replace **CPANELUSER** with your cPanel username (shown in the cPanel sidebar under *General Information → Current User*).

Do the steps **in this order**. The subdomain is created *after* the code is cloned, because cPanel will not clone into a folder that already exists.

---

## 1. Clone the code (Git™ Version Control)
cPanel → **Files → Git™ Version Control** → **Create**
- **Clone a Repository:** on
- **Clone URL:** `https://github.com/mkazre/wheelturngms.git`
- **Repository Path:** `repositories/wheelturngms`
- **Repository Name:** `wheelturngms`
- Click **Create** and wait until it finishes.

## 2. Create the subdomain
cPanel → **Domains → Domains** → **Create A New Domain**
- **Domain:** `app.wheelsturn.co.za`
- **Untick** "Share document root with …"
- **Document Root:** `repositories/wheelturngms/backend/public`
- Click **Submit**.

> Only the `public` folder is reachable from the internet. The code, the `.env` file with your passwords, uploads and backups stay private.

**DNS:** if wheelsturn.co.za's DNS is managed by this hosting account, cPanel adds the record automatically. If your DNS is somewhere else (e.g. your domain registrar or Cloudflare), add an **A record**: name `app`, value = the server IP shown in cPanel's sidebar (*Shared IP Address*).

## 3. Set the PHP version
cPanel → **Software → MultiPHP Manager** → tick `app.wheelsturn.co.za` → choose **PHP 8.2** (or 8.3) → **Apply**.

If your host has **Select PHP Version** (CloudLinux), make sure these extensions are ticked: `intl`, `mbstring`, `gd`, `curl`, `zip`, `mysqli`, `fileinfo`, `openssl`.

Also in **MultiPHP INI Editor** (select `app.wheelsturn.co.za`):
- `upload_max_filesize` = `64M`
- `post_max_size` = `70M`
- `max_execution_time` = `120`
- `memory_limit` = `256M`

## 4. Create the database
cPanel → **Databases → MySQL® Databases**
1. **Create New Database:** `wheelsturn` → it becomes `CPANELUSER_wheelsturn`
2. **Add New User:** `wtapp` with a strong password (use the generator, and **copy the password**) → it becomes `CPANELUSER_wtapp`
3. **Add User To Database:** user `CPANELUSER_wtapp` → database `CPANELUSER_wheelsturn` → tick **ALL PRIVILEGES** → **Make Changes**

## 5. Create the settings file (.env)
cPanel → **Files → File Manager**
1. Top-right **Settings** → tick **Show Hidden Files (dotfiles)** → Save.
2. Open `repositories/wheelturngms/backend`.
3. Right-click `env.production.example` → **Copy** → change the destination to `/repositories/wheelturngms/backend/.env` → **Copy File(s)**.
4. Right-click `.env` → **Edit**. Change only these lines:
   ```
   database.default.database = CPANELUSER_wheelsturn
   database.default.username = CPANELUSER_wtapp
   database.default.password = 'the password from step 4'
   ```
   Leave `encryption.key =` and `jwt.secret =` empty: the installer fills them in. **Save Changes**.
5. Right-click `.env` → **Change Permissions** → `640`.

## 6. Run the installer (Cron Jobs instead of Terminal)
cPanel → **Advanced → Cron Jobs**
1. **Cron Email:** enter your email address → **Update Email** (so you receive the installer's output).
2. **Add New Cron Job:** Common Settings = **Once Per Minute (\* \* \* \* \*)**, Command:
   ```
   /opt/cpanel/ea-php82/root/usr/bin/php /home/CPANELUSER/repositories/wheelturngms/backend/spark gms:install
   ```
3. Wait **2–3 minutes**. The first run creates security keys; the second installs the database.
4. In File Manager open `repositories/wheelturngms/backend/writable/logs/INSTALL-RESULT.txt`. It ends with **SUCCESS** and shows your **admin email and random password**. Write the password down, then **delete this file**.
5. **Delete the install cron job** (it is safe if it runs again, but it is no longer needed).
6. **Add the permanent cron job:** Common Settings = **Once Per Fifteen Minutes (\*/15 \* \* \* \*)**, Command:
   ```
   /opt/cpanel/ea-php82/root/usr/bin/php /home/CPANELUSER/repositories/wheelturngms/backend/spark gms:cron >/dev/null 2>&1
   ```
   This sends the Monday report, reminders, feedback requests and nightly backups, and applies database updates automatically after each code update.

> **Nothing happened?** Check the cron email. If it says the PHP path does not exist, use `/usr/local/bin/php` (or `/opt/alt/php82/usr/bin/php` on CloudLinux) instead of `/opt/cpanel/ea-php82/root/usr/bin/php`. If it says "could not connect", re-check the three database lines in `.env`.

## 7. Turn on HTTPS
cPanel → **Security → SSL/TLS Status** → tick `app.wheelsturn.co.za` → **Run AutoSSL**. Wait until it shows a valid certificate (needs the DNS from step 2 to be working).

## 8. First sign-in
Open **https://app.wheelsturn.co.za**, sign in with the admin email and password from step 6, then:
1. **My profile** (top-right): change the password and switch on **two-factor authentication**.
2. **Settings → Company profile:** legal name, VAT number, CIPC number, address, banking details.
3. **Settings → Email & SMTP:** create a mailbox in cPanel (*Email Accounts*, e.g. `noreply@wheelsturn.co.za`), then host `mail.wheelsturn.co.za`, port `465`, encryption `SSL`, the mailbox address and password. Click **Send test**.
4. **Settings → Online payments:** PayFast and/or Ozow details (start in sandbox/test mode).
5. **Settings → Notifications:** Monday report recipients.
6. **Team:** add your staff.

---

## Updating to a new version
When new code is pushed to GitHub:
cPanel → **Git™ Version Control** → **Manage** (wheelturngms) → **Pull or Deploy** → **Update from Remote**.
Database changes apply automatically on the next 15-minute cron run.

## Making the GitHub repository private later
cPanel can only pull a private repository over SSH. When you are ready: cPanel → **SSH Access → Manage SSH Keys** → generate a key, then add its *public* key in GitHub → repo **Settings → Deploy keys**. The repository then needs to be re-cloned with the SSH address (`git@github.com:mkazre/wheelturngms.git`). Ask for help when you get there; your `.env`, uploads and database are kept.

## Backups
Nightly database backups are kept in `backend/writable/backups` (last 14 days) and can be downloaded from **Admin → Backups**. Uploaded photos and slips are in `backend/writable/uploads` and are included in cPanel's own account backups.
