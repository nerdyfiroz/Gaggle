# Gaggle NFT Collection — Website

A complete, production-ready PHP/MySQL website for the **Gaggle** PFP NFT collection featuring a premium Web3 landing page, whitelist application system with anti-bot protection, and a full admin dashboard.

## ✨ Features

### Public Website
- **Premium Landing Page** — Hero, collection grid, about/lore, roadmap, FAQ, team
- **Whitelist Application** — Multi-step form with task verification
- **Application Status Check** — Lookup by application ID
- **Responsive Design** — Mobile-first, works on all devices

### Whitelist System
- Task-based verification (Twitter follow, Discord join, etc.)
- Cloudflare Turnstile CAPTCHA
- Server-side validation for all inputs
- Ethereum wallet address validation
- Rate limiting (configurable per IP)
- IP application limit (default: 100 per IP)
- Duplicate wallet detection
- Blacklist checking (wallet, IP, Twitter, Discord)
- Honeypot anti-bot field
- CSRF protection

### Admin Panel
- **Dashboard** — Statistics, charts, recent applications
- **Application Management** — Search, filter, bulk actions, CSV export
- **Task Management** — CRUD, drag-and-drop reorder, enable/disable
- **Blacklist** — Block wallets, IPs, social accounts
- **IP Management** — Statistics, block/unblock
- **Settings** — General, application, CAPTCHA, security
- **Audit Logs** — Full admin action trail
- **CSV Export** — Filtered export with Excel compatibility

### Security
- PDO prepared statements (SQL injection prevention)
- XSS output escaping
- CSRF tokens on all forms
- Secure sessions (HttpOnly, SameSite, Secure)
- Password hashing (bcrypt, cost 12)
- Login throttling (5 attempts → 15-minute lockout)
- Rate limiting (database-backed)
- Honeypot field
- Security headers (X-Frame-Options, X-Content-Type-Options, etc.)
- Sensitive file access denied via .htaccess

---

## 🛠 Requirements

- PHP 8.2+
- MySQL 8.0+
- Apache with mod_rewrite
- cURL extension (for CAPTCHA verification)
- PDO MySQL extension

---

## 📦 Installation

### 1. Upload Files
Upload the entire project to your web hosting. The `public/` directory should be your document root, or configure Apache accordingly.

### 2. Create MySQL Database
Create a new MySQL database (e.g., `gaggle_nft`) via cPanel or your hosting panel.

### 3. Import Schema
```bash
mysql -u username -p gaggle_nft < database/schema.sql
```

For an existing installation created from an earlier schema, review duplicate
wallets first and then run `database/migration_001_hardening.sql`. This adds the
normalized wallet uniqueness constraint and prevents duplicate task records.

### 4. Import Seed Data (Recommended)
```bash
mysql -u username -p gaggle_nft < database/seed.sql
```
This creates:
- Default admin account: `admin` / `ChangeMe123!`
- Default settings
- 5 example whitelist tasks

### 5. Configure Environment
```bash
cp .env.example .env
```
Edit `.env` with your database credentials:
```
DB_HOST=localhost
DB_NAME=gaggle_nft
DB_USER=your_db_user
DB_PASSWORD=your_db_password
```

### 6. Configure CAPTCHA (Optional)
1. Go to [Cloudflare Turnstile](https://developers.cloudflare.com/turnstile/)
2. Create a site and get your site key + secret key
3. Either add to `.env` or configure in Admin → Settings → CAPTCHA

### 7. Set Permissions
```bash
chmod 755 storage/
chmod 755 storage/logs/
chmod 644 .env
```

### 8. Apache Configuration
If `public/` is not your document root, configure your virtual host:
```apache
DocumentRoot /path/to/gaggle/public
<Directory /path/to/gaggle/public>
    AllowOverride All
    Require all granted
</Directory>
```

For shared hosting (cPanel), you may need to:
1. Upload everything to `public_html/`
2. Move the contents of `public/` to `public_html/`
3. Update the paths in `public/index.php` (change `dirname(__DIR__)` accordingly)

### 9. First Login
1. Go to `https://yourdomain.com/admin/login.php`
2. Login with `admin` / `ChangeMe123!`
3. You will be forced to change your password
4. Configure settings, create/edit tasks
5. Enable applications

### 10. Test
1. Visit the homepage
2. Click "Apply for Whitelist"
3. Complete tasks, fill in form, submit
4. Verify the application appears in the admin panel

---

## 📁 Project Structure

```
gaggle/
├── .env                     # Environment config (git-ignored)
├── .env.example             # Environment template
├── .htaccess                # Root security rules
├── .gitignore
├── app/
│   ├── config/
│   │   ├── config.php       # Core configuration
│   │   └── database.php     # PDO connection
│   ├── helpers/
│   │   └── functions.php    # Global helper functions
│   ├── middleware/
│   │   └── AuthMiddleware.php
│   ├── models/
│   │   ├── Admin.php
│   │   ├── AdminLog.php
│   │   ├── Application.php
│   │   ├── Blacklist.php
│   │   ├── BlockedIp.php
│   │   ├── Setting.php
│   │   └── Task.php
│   ├── services/
│   │   ├── ApplicationService.php
│   │   ├── CaptchaService.php
│   │   ├── RateLimitService.php
│   │   └── ValidationService.php
│   └── views/
│       └── layouts/
│           ├── admin.php
│           └── public.php
├── admin/
│   ├── ajax/
│   │   ├── bulk-action.php
│   │   ├── reorder-tasks.php
│   │   ├── status-change.php
│   │   └── toggle-task.php
│   ├── application-view.php
│   ├── applications.php
│   ├── blacklist.php
│   ├── change-password.php
│   ├── export.php
│   ├── index.php (Dashboard)
│   ├── ips.php
│   ├── login.php
│   ├── logout.php
│   ├── logs.php
│   ├── settings.php
│   └── tasks.php
├── database/
│   ├── schema.sql
│   └── seed.sql
├── public/
│   ├── .htaccess
│   ├── apply.php
│   ├── index.php (Landing Page)
│   ├── status.php
│   ├── success.php
│   └── assets/
│       ├── css/
│       │   ├── admin.css
│       │   └── style.css
│       ├── images/
│       │   ├── hero-pfp.jpg
│       │   └── logo-banner.jpg
│       └── js/
│           ├── admin.js
│           ├── apply.js
│           └── main.js
├── storage/
│   └── logs/
└── README.md
```

---

## ⚙️ Configuration

All project settings are configurable from the admin panel (Settings page). Key settings:

| Setting | Default | Description |
|---------|---------|-------------|
| IP Application Limit | 100 | Max applications per IP address |
| Rate Limit Window | 10 min | Short-term rate limit window |
| Rate Limit Max | 5 | Max submissions in window |
| Duplicate Wallet | Enabled | Prevent same wallet twice |
| CAPTCHA | Turnstile | Cloudflare Turnstile provider |
| Session Timeout | 3600s | Admin session timeout |

---

## 🎨 Customization

### Branding
Replace images in `public/assets/images/`:
- `hero-pfp.jpg` — Main PFP artwork
- `logo-banner.jpg` — Logo banner

### Colors
Edit CSS custom properties in `public/assets/css/style.css`:
```css
:root {
    --gaggle-green: #d8edcb;
    --gaggle-gold: #f0c040;
    --gaggle-crimson: #8b1a2b;
    /* ... */
}
```

### Content
All text content (project name, description, lore, supply, etc.) is configurable from Admin → Settings → General.

---

## 📄 License

All rights reserved. This project is proprietary.

## Vercel Node/PostgreSQL API

The Vercel migration is API-first and lives in `api/index.js`:

- `POST /api/applications` — submit a whitelist application
- `GET /api/config` — public settings
- `GET /api/tasks` — enabled public tasks
- `GET /api/status?id=WL-XXXXXXXX` — status lookup
- `POST /api/auth/login` and `POST /api/auth/logout` — admin sessions
- `GET /api/dashboard` — admin dashboard statistics
- `GET/PATCH /api/applications` — admin application management
- `GET/POST /api/tasks` — admin task management
- `GET/PUT /api/settings` — admin settings

### Vercel setup

1. Create a PostgreSQL database through Neon, Supabase, or another managed provider.
2. Run `database/schema.postgres.sql`, then `database/seed.postgres.sql`.
3. Install dependencies with `npm install` and deploy this repository to Vercel.
4. Configure these Vercel environment variables:
    - `POSTGRES_URL` or `DATABASE_URL`
    - `SESSION_SECRET` (a long random value)
    - `APP_URL`
    - `CAPTCHA_SITE_KEY`
    - `CAPTCHA_SECRET_KEY`
    - `NODE_ENV=production`
5. Create the first admin using a bcrypt hash in PostgreSQL. Generate a hash with `node -e "console.log(require('bcryptjs').hashSync('replace-this-password', 12))"` and set `force_password_change` to `true`.

The original PHP-rendered pages and `.php` URLs are not executed by Vercel. The frontend must call the JSON API above, or the PHP runtime must remain deployed on Render/cPanel.
