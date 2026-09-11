const crypto = require('crypto');
const bcrypt = require('bcryptjs');
const { query } = require('./db');

const SESSION_COOKIE = 'gaggle_session';
const CSRF_COOKIE = 'gaggle_csrf';
const SESSION_SECONDS = Number(process.env.SESSION_LIFETIME || 3600);
const secret = () => process.env.SESSION_SECRET || process.env.AUTH_SECRET;

function requireSecret() {
  if (!secret()) throw new Error('SESSION_SECRET is not configured');
}

function sign(value) {
  return crypto.createHmac('sha256', secret()).update(value).digest('base64url');
}

function encodeSession(session) {
  requireSecret();
  const payload = Buffer.from(JSON.stringify({ ...session, exp: Math.floor(Date.now() / 1000) + SESSION_SECONDS })).toString('base64url');
  return `${payload}.${sign(payload)}`;
}

function decodeSession(value) {
  try {
    requireSecret();
    const [payload, signature] = String(value || '').split('.');
    if (!payload || !signature || !crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(sign(payload)))) return null;
    const session = JSON.parse(Buffer.from(payload, 'base64url').toString('utf8'));
    return session.exp > Math.floor(Date.now() / 1000) ? session : null;
  } catch (_) { return null; }
}

function parseCookies(req) {
  return Object.fromEntries((req.headers.cookie || '').split(';').filter(Boolean).map(pair => {
    const index = pair.indexOf('=');
    return [pair.slice(0, index).trim(), decodeURIComponent(pair.slice(index + 1).trim())];
  }));
}

function setCookie(res, name, value, options = {}) {
  const parts = [`${name}=${encodeURIComponent(value)}`, 'Path=/', 'SameSite=Strict'];
  if (options.httpOnly !== false) parts.push('HttpOnly');
  if (process.env.NODE_ENV === 'production') parts.push('Secure');
  if (options.maxAge !== undefined) parts.push(`Max-Age=${options.maxAge}`);
  res.setHeader('Set-Cookie', [...(res.getHeader('Set-Cookie') || []), parts.join('; ') ]);
}

function clearCookie(res, name) { setCookie(res, name, '', { maxAge: 0 }); }

function issueSession(res, admin) {
  const csrf = crypto.randomBytes(32).toString('hex');
  setCookie(res, SESSION_COOKIE, encodeSession({ adminId: admin.id, username: admin.username, csrf }));
  setCookie(res, CSRF_COOKIE, csrf, { httpOnly: false });
  return csrf;
}

function getSession(req) { return decodeSession(parseCookies(req)[SESSION_COOKIE]); }

function requireAdmin(req, res) {
  const session = getSession(req);
  if (!session) { sendJson(res, 401, { success: false, message: 'Authentication required.' }); return null; }
  if (req.method !== 'GET' && req.headers['x-csrf-token'] !== session.csrf) {
    sendJson(res, 403, { success: false, message: 'Invalid security token.' }); return null;
  }
  return session;
}

function sendJson(res, status, body) {
  res.statusCode = status;
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.end(JSON.stringify(body));
}

async function authenticate(username, password, ip) {
  try {
    await bootstrapAdmin();
  } catch (error) {
    console.error('Admin bootstrap failed:', error.message);
    return { error: 'Admin setup is incomplete. Check the database schema and ADMIN_USERNAME/ADMIN_PASSWORD environment variables.' };
  }
  const limited = await query("SELECT COUNT(*)::int AS count FROM rate_limits WHERE ip_address = $1 AND endpoint = 'admin_login' AND created_at >= NOW() - INTERVAL '15 minutes'", [ip]);
  if (limited.rows[0].count >= 10) return { error: 'Too many login attempts. Please wait and try again.' };
  const result = await query('SELECT * FROM admins WHERE username = $1 LIMIT 1', [username]);
  if (!result.rows[0] || !(await bcrypt.compare(password, result.rows[0].password_hash))) {
    await query("INSERT INTO rate_limits (ip_address, endpoint) VALUES ($1, 'admin_login')", [ip]);
    if (result.rows[0]) await query('UPDATE admins SET login_attempts = login_attempts + 1, locked_until = CASE WHEN login_attempts + 1 >= $1 THEN NOW() + ($2 || \' minutes\')::interval ELSE locked_until END WHERE id = $3', [Number(process.env.ADMIN_LOGIN_MAX_ATTEMPTS || 5), String(Number(process.env.ADMIN_LOGIN_LOCKOUT_MINUTES || 15)), result.rows[0].id]);
    return { error: 'Invalid username or password.' };
  }
  if (result.rows[0].locked_until && new Date(result.rows[0].locked_until) > new Date()) return { error: 'Account is temporarily locked.' };
  await query('UPDATE admins SET login_attempts = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = $1 WHERE id = $2', [ip, result.rows[0].id]);
  return { admin: result.rows[0] };
}

async function bootstrapAdmin() {
  const adminUsername = String(process.env.ADMIN_USERNAME || '').trim();
  const adminPassword = String(process.env.ADMIN_PASSWORD || '');
  if (!adminUsername || !adminPassword) return;
  if (adminUsername.length > 50 || adminPassword.length < 12) throw new Error('ADMIN_USERNAME must be 50 characters or fewer and ADMIN_PASSWORD must be at least 12 characters.');
  const existing = await query('SELECT 1 FROM admins WHERE username=$1 LIMIT 1', [adminUsername]);
  if (existing.rowCount) return;
  const passwordHash = await bcrypt.hash(adminPassword, 12);
  await query('INSERT INTO admins(username,password_hash,force_password_change) VALUES($1,$2,false) ON CONFLICT(username) DO NOTHING', [adminUsername, passwordHash]);
}

module.exports = { authenticate, issueSession, getSession, requireAdmin, clearCookie, sendJson, SESSION_COOKIE, CSRF_COOKIE };
