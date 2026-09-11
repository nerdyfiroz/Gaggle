# Gaggle — Vercel NFT Whitelist

A Node.js/Vercel serverless API and PostgreSQL-backed frontend for the Gaggle NFT collection.

## Stack

- Vercel serverless functions
- Node.js 20+
- PostgreSQL via `pg`
- Signed HttpOnly admin sessions
- bcrypt password hashing
- Static HTML/CSS frontend
- Cloudflare Turnstile or Google reCAPTCHA

## Project structure

```text
api/index.js                 Serverless API routes
lib/db.js                    PostgreSQL pool and transactions
lib/auth.js                  Authentication, sessions, CSRF
public/index.html            Public landing page
public/admin/index.html      Admin login and dashboard
public/assets/               Images and frontend styles
database/schema.postgres.sql PostgreSQL schema
database/seed.postgres.sql   Default settings and tasks
vercel.json                  Vercel routing configuration
```

## Local setup

1. Install Node.js 20+.
2. Install dependencies:

```bash
npm install
```

3. Create a PostgreSQL database and run:

```bash
psql "$POSTGRES_URL" -f database/schema.postgres.sql
psql "$POSTGRES_URL" -f database/seed.postgres.sql
```

4. Configure environment variables:

```text
POSTGRES_URL=postgresql://user:password@host/database?sslmode=require
# Alternatively, Vercel Neon integration variables POSTGRES_PRISMA_URL or DATABASE_URL_UNPOOLED are supported.
SESSION_SECRET=replace-with-a-long-random-secret
ADMIN_USERNAME=your-admin-username
ADMIN_PASSWORD=use-a-unique-password-at-least-12-characters
NODE_ENV=development
APP_URL=http://localhost:3000
CAPTCHA_SITE_KEY=your-public-site-key
CAPTCHA_SECRET_KEY=your-server-secret
```

5. Start with Vercel CLI:

```bash
npx vercel dev
```

## Vercel deployment

1. Import this GitHub repository into Vercel.
2. Keep the project root as the repository root.
3. Add `POSTGRES_URL` (your Neon pooled connection string), `SESSION_SECRET`, `ADMIN_USERNAME`, `ADMIN_PASSWORD`, `NODE_ENV=production`, `APP_URL`, `CAPTCHA_SITE_KEY`, and `CAPTCHA_SECRET_KEY` in Vercel Project Settings. Add them to Production, Preview, and Development as needed.
4. Run `database/schema.postgres.sql` and `database/seed.postgres.sql` against the Neon database. The schema script is safe to re-run and adds the slot columns to an existing installation.
5. Deploy the `main` branch.

Vercel serves the static frontend from `public/` and the API from `/api/*`.

## API routes

Public:

- `GET /api/config`
- `GET /api/tasks`
- `GET /api/status?id=WL-XXXXXXXX`
- `POST /api/applications`

Admin:

- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/dashboard`
- `GET /api/applications`
- `PATCH /api/applications/status`
- `GET /api/tasks`
- `POST /api/tasks`
- `PATCH /api/tasks`
- `DELETE /api/tasks?id=...`
- `PATCH /api/applications/slot`
- `GET /api/settings`
- `PUT /api/settings`

Admin UI: `/admin/`

The admin task editor supports Follow, Like, Retweet, Reply, and Custom actions. Each task requires an HTTP(S) link, which appears as a clickable action link on the public application form. The Whitelist settings panel can pause or resume new submissions.

## Create the first admin

Set `ADMIN_USERNAME` and `ADMIN_PASSWORD` in Vercel. On the first admin login, the API creates that account automatically if the username does not already exist. The password is stored as a bcrypt hash; the plaintext value is never stored. If you prefer SQL provisioning, use the manual method below.

Generate a bcrypt hash locally:

```bash
node -e "console.log(require('bcryptjs').hashSync('use-a-strong-password', 12))"
```

Insert the returned hash into PostgreSQL:

```sql
INSERT INTO admins (username, email, password_hash, force_password_change)
VALUES ('admin', 'admin@example.com', 'PASTE_HASH_HERE', true);
```

Then open `/admin/` and sign in.

## Security notes

- Never commit `.env` or production secrets.
- Use a long random `SESSION_SECRET`.
- Keep CAPTCHA secrets in Vercel server-side environment variables.
- Rotate any token that has been exposed in chat, logs, or screenshots.
- Use a managed PostgreSQL provider with SSL enabled.
