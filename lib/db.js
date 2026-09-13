const { Pool } = require('pg');

let pool = null;
let poolFailed = false;

// In-memory store for fallback / mock mode when PostgreSQL is not configured
const inMemoryStore = {
  settings: new Map([
    ['general.project_name', { id: 1, setting_group: 'general', setting_key: 'project_name', setting_value: 'Gaggle' }],
    ['general.project_tagline', { id: 2, setting_group: 'general', setting_key: 'project_tagline', setting_value: 'The flock is coming.' }],
    ['general.blockchain', { id: 3, setting_group: 'general', setting_key: 'blockchain', setting_value: 'Ethereum' }],
    ['general.wl_spots', { id: 4, setting_group: 'general', setting_key: 'wl_spots', setting_value: '100' }],
    ['general.twitter_url', { id: 5, setting_group: 'general', setting_key: 'twitter_url', setting_value: 'https://x.com/itzGaggle' }],
    ['application.applications_enabled', { id: 6, setting_group: 'application', setting_key: 'applications_enabled', setting_value: '1' }],
    ['application.ip_application_limit', { id: 7, setting_group: 'application', setting_key: 'ip_application_limit', setting_value: '100' }],
    ['application.rate_limit_window_minutes', { id: 8, setting_group: 'application', setting_key: 'rate_limit_window_minutes', setting_value: '10' }],
    ['application.rate_limit_max_requests', { id: 9, setting_group: 'application', setting_key: 'rate_limit_max_requests', setting_value: '5' }],
    ['application.duplicate_wallet_protection', { id: 10, setting_group: 'application', setting_key: 'duplicate_wallet_protection', setting_value: '1' }],
    ['application.require_twitter', { id: 11, setting_group: 'application', setting_key: 'require_twitter', setting_value: '1' }],
    ['application.require_email', { id: 12, setting_group: 'application', setting_key: 'require_email', setting_value: '0' }],
    ['captcha.captcha_enabled', { id: 13, setting_group: 'captcha', setting_key: 'captcha_enabled', setting_value: '1' }],
    ['security.ip_blocking_enabled', { id: 14, setting_group: 'security', setting_key: 'ip_blocking_enabled', setting_value: '1' }]
  ]),
  tasks: [
    {
      id: 1,
      title: 'Follow @itzGaggle on X',
      description: 'Follow the official Gaggle account.',
      type: 'twitter_follow',
      url: 'https://x.com/itzGaggle',
      required: true,
      enabled: true,
      sort_order: 1,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    },
    {
      id: 2,
      title: 'Like the Gaggle post',
      description: 'Like the post linked by the admin.',
      type: 'twitter_like',
      url: 'https://x.com/itzGaggle',
      required: false,
      enabled: false,
      sort_order: 2,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    },
    {
      id: 3,
      title: 'Retweet the Gaggle post',
      description: 'Retweet the post linked by the admin.',
      type: 'twitter_retweet',
      url: 'https://x.com/itzGaggle',
      required: false,
      enabled: false,
      sort_order: 3,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    },
    {
      id: 4,
      title: 'Reply to the Gaggle post',
      description: 'Reply to the post linked by the admin.',
      type: 'twitter_reply',
      url: 'https://x.com/itzGaggle',
      required: false,
      enabled: false,
      sort_order: 4,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    }
  ],
  applications: [],
  application_tasks: [],
  admins: [],
  blacklist: [],
  blocked_ips: [],
  rate_limits: [],
  admin_logs: [],
  nextTaskId: 5,
  nextAppId: 1,
  nextAdminId: 1,
  nextLogId: 1
};

async function mockQuery(text, values = []) {
  const q = text.trim();

  // 1. SETTINGS
  if (q.includes('FROM settings')) {
    if (q.includes('setting_group=$1 AND setting_key=$2')) {
      const entry = inMemoryStore.settings.get(`${values[0]}.${values[1]}`);
      return { rows: entry ? [{ setting_value: entry.setting_value }] : [], rowCount: entry ? 1 : 0 };
    }
    if (q.includes("setting_group='general'") || q.includes("setting_group='application'") || q.includes("setting_group='captcha'")) {
      const match = q.match(/setting_group='([a-z0-9_]+)'/i);
      const group = match ? match[1] : null;
      let rows = [...inMemoryStore.settings.values()];
      if (group) rows = rows.filter(s => s.setting_group === group);
      if (q.includes("setting_key='captcha_enabled'")) rows = rows.filter(s => s.setting_key === 'captcha_enabled');
      return { rows: rows.map(r => ({ setting_key: r.setting_key, setting_value: r.setting_value })), rowCount: rows.length };
    }
    // ORDER BY setting_group,setting_key
    const rows = [...inMemoryStore.settings.values()].sort((a, b) =>
      a.setting_group.localeCompare(b.setting_group) || a.setting_key.localeCompare(b.setting_key)
    );
    return { rows, rowCount: rows.length };
  }
  if (q.includes('INSERT INTO settings')) {
    // INSERT INTO settings(setting_group,setting_key,setting_value) VALUES($1,$2,$3) ON CONFLICT...
    const [group, key, value] = values;
    const existing = inMemoryStore.settings.get(`${group}.${key}`) || { id: inMemoryStore.settings.size + 1, setting_group: group, setting_key: key };
    existing.setting_value = String(value);
    existing.updated_at = new Date().toISOString();
    inMemoryStore.settings.set(`${group}.${key}`, existing);
    return { rows: [existing], rowCount: 1 };
  }

  // 2. TASKS
  if (q.includes('FROM tasks')) {
    if (q.includes('SELECT id,required FROM tasks WHERE enabled=true')) {
      const rows = inMemoryStore.tasks.filter(t => t.enabled).map(t => ({ id: t.id, required: t.required }));
      return { rows, rowCount: rows.length };
    }
    if (q.includes('enabled=true')) {
      const rows = inMemoryStore.tasks
        .filter(t => t.enabled)
        .sort((a, b) => (a.sort_order - b.sort_order) || (a.id - b.id));
      return { rows, rowCount: rows.length };
    }
    const rows = [...inMemoryStore.tasks].sort((a, b) => (a.sort_order - b.sort_order) || (a.id - b.id));
    return { rows, rowCount: rows.length };
  }
  if (q.includes('INSERT INTO tasks')) {
    const newTask = {
      id: inMemoryStore.nextTaskId++,
      title: values[0],
      description: values[1],
      type: values[2] || 'custom',
      url: values[3],
      required: values[4] !== false,
      enabled: values[5] !== false,
      sort_order: inMemoryStore.tasks.length ? Math.max(...inMemoryStore.tasks.map(t => t.sort_order || 0)) + 1 : 1,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    };
    inMemoryStore.tasks.push(newTask);
    return { rows: [newTask], rowCount: 1 };
  }
  if (q.includes('UPDATE tasks SET')) {
    const taskId = Number(values[7]);
    const task = inMemoryStore.tasks.find(t => t.id === taskId);
    if (!task) return { rows: [], rowCount: 0 };
    task.title = values[0];
    task.description = values[1];
    task.type = values[2] || 'custom';
    task.url = values[3];
    task.required = values[4] === true;
    task.enabled = values[5] !== false;
    task.sort_order = Number.isInteger(Number(values[6])) ? Number(values[6]) : 0;
    task.updated_at = new Date().toISOString();
    return { rows: [task], rowCount: 1 };
  }
  if (q.includes('DELETE FROM tasks WHERE id=$1')) {
    const taskId = Number(values[0]);
    const idx = inMemoryStore.tasks.findIndex(t => t.id === taskId);
    if (idx >= 0) inMemoryStore.tasks.splice(idx, 1);
    return { rows: [{ id: taskId }], rowCount: idx >= 0 ? 1 : 0 };
  }

  // 3. APPLICATIONS
  if (q.includes('FROM applications WHERE wallet_normalized=$1')) {
    const found = inMemoryStore.applications.find(a => a.wallet_normalized === values[0]);
    return { rows: found ? [{ '1': 1 }] : [], rowCount: found ? 1 : 0 };
  }
  if (q.includes('FROM applications WHERE ip_address=$1')) {
    const count = inMemoryStore.applications.filter(a => a.ip_address === values[0]).length;
    return { rows: [{ count }], rowCount: 1 };
  }
  if (q.includes('FROM applications WHERE application_id=$1')) {
    const found = inMemoryStore.applications.find(a => a.application_id === values[0]);
    return { rows: found ? [found] : [], rowCount: found ? 1 : 0 };
  }
  if (q.includes('INSERT INTO applications')) {
    const appId = values[0];
    const wallet = values[1];
    const wallet_normalized = values[2];
    const twitter = values[3] || null;
    const ip = values[4];
    const user_agent = values[5] || null;
    const newApp = {
      id: inMemoryStore.nextAppId++,
      application_id: appId,
      wallet_address: wallet,
      wallet_normalized,
      twitter_username: twitter,
      email: null,
      ip_address: ip,
      user_agent,
      status: 'pending',
      admin_notes: null,
      whitelist_slot: null,
      slot_assigned_at: null,
      slot_assigned_by: null,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    };
    inMemoryStore.applications.push(newApp);
    return { rows: [newApp], rowCount: 1 };
  }
  if (q.includes('INSERT INTO application_tasks')) {
    inMemoryStore.application_tasks.push({
      application_id: values[0],
      task_id: values[1],
      created_at: new Date().toISOString()
    });
    return { rows: [], rowCount: 1 };
  }
  if (q.includes('COUNT(*)::int total') && q.includes('FILTER(WHERE status=\'pending\')')) {
    const total = inMemoryStore.applications.length;
    const pending = inMemoryStore.applications.filter(a => a.status === 'pending').length;
    const approved = inMemoryStore.applications.filter(a => a.status === 'approved').length;
    const rejected = inMemoryStore.applications.filter(a => a.status === 'rejected').length;
    const assigned = inMemoryStore.applications.filter(a => a.whitelist_slot !== null).length;
    const todayDate = new Date().toISOString().slice(0, 10);
    const today = inMemoryStore.applications.filter(a => (a.created_at || '').slice(0, 10) === todayDate).length;
    const unique_wallets = new Set(inMemoryStore.applications.map(a => a.wallet_normalized)).size;
    return { rows: [{ total, pending, approved, rejected, assigned, today, unique_wallets }], rowCount: 1 };
  }
  if (q.includes('SELECT * FROM applications')) {
    let rows = [...inMemoryStore.applications];
    // filter if needed
    for (let i = 0; i < values.length - 2; i++) {
      const val = values[i];
      if (typeof val === 'string' && ['pending', 'approved', 'rejected', 'blacklisted', 'review'].includes(val)) {
        rows = rows.filter(a => a.status === val);
      } else if (typeof val === 'string' && val.startsWith('%') && val.endsWith('%')) {
        const needle = val.slice(1, -1).toLowerCase();
        rows = rows.filter(a =>
          (a.application_id && a.application_id.toLowerCase().includes(needle)) ||
          (a.wallet_address && a.wallet_address.toLowerCase().includes(needle)) ||
          (a.twitter_username && a.twitter_username.toLowerCase().includes(needle))
        );
      }
    }
    rows.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    const limit = Number(values[values.length - 2]) || 25;
    const offset = Number(values[values.length - 1]) || 0;
    const paginated = rows.slice(offset, offset + limit);
    return { rows: paginated, rowCount: paginated.length };
  }
  if (q.includes('UPDATE applications SET status=$1')) {
    const status = values[0];
    const id = Number(values[1]);
    const app = inMemoryStore.applications.find(a => a.id === id);
    if (!app) return { rows: [], rowCount: 0 };
    app.status = status;
    app.updated_at = new Date().toISOString();
    return { rows: [{ id }], rowCount: 1 };
  }
  if (q.includes('UPDATE applications SET whitelist_slot=$1')) {
    const slot = values[0];
    const adminId = values[1];
    const id = Number(values[2]);
    if (slot !== null) {
      const conflict = inMemoryStore.applications.find(a => a.id !== id && a.whitelist_slot === slot);
      if (conflict) {
        const err = new Error('That whitelist slot is already assigned.');
        err.code = '23505';
        throw err;
      }
    }
    const app = inMemoryStore.applications.find(a => a.id === id);
    if (!app) return { rows: [], rowCount: 0 };
    app.whitelist_slot = slot;
    app.slot_assigned_at = slot ? new Date().toISOString() : null;
    app.slot_assigned_by = slot ? adminId : null;
    if (slot !== null) app.status = 'approved';
    app.updated_at = new Date().toISOString();
    return { rows: [app], rowCount: 1 };
  }

  // 4. RATE LIMITS
  if (q.includes('FROM rate_limits')) {
    return { rows: [{ count: 0 }], rowCount: 1 };
  }
  if (q.includes('INSERT INTO rate_limits')) {
    inMemoryStore.rate_limits.push({ ip: values[0], endpoint: values[1], created_at: new Date() });
    return { rows: [], rowCount: 1 };
  }

  // 5. BLACKLIST & BLOCKED IPS
  if (q.includes('FROM blocked_ips')) {
    return { rows: [], rowCount: 0 };
  }
  if (q.includes('FROM blacklist')) {
    return { rows: [], rowCount: 0 };
  }

  // 6. ADMINS
  if (q.includes('FROM admins WHERE username=$1') || q.includes('FROM admins WHERE username = $1')) {
    const found = inMemoryStore.admins.find(a => a.username === values[0]);
    return { rows: found ? [found] : [], rowCount: found ? 1 : 0 };
  }
  if (q.includes('INSERT INTO admins')) {
    const [username, password_hash, force_password_change] = values;
    let admin = inMemoryStore.admins.find(a => a.username === username);
    if (!admin) {
      admin = {
        id: inMemoryStore.nextAdminId++,
        username,
        password_hash,
        force_password_change: Boolean(force_password_change),
        login_attempts: 0,
        locked_until: null,
        last_login_at: null,
        last_login_ip: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };
      inMemoryStore.admins.push(admin);
    }
    return { rows: [admin], rowCount: 1 };
  }
  if (q.includes('UPDATE admins SET')) {
    const id = values[values.length - 1];
    const admin = inMemoryStore.admins.find(a => a.id === id);
    if (admin) {
      if (q.includes('login_attempts = 0')) {
        admin.login_attempts = 0;
        admin.locked_until = null;
        admin.last_login_at = new Date().toISOString();
        admin.last_login_ip = values[0];
      } else {
        admin.login_attempts++;
      }
    }
    return { rows: [], rowCount: 1 };
  }

  // 7. ADMIN LOGS
  if (q.includes('INSERT INTO admin_logs')) {
    inMemoryStore.admin_logs.push({
      id: inMemoryStore.nextLogId++,
      admin_id: values[0],
      admin_username: values[1],
      action: values[2],
      target_type: values[3],
      target_id: values[4],
      details: values[5],
      ip_address: values[6],
      created_at: new Date().toISOString()
    });
    return { rows: [], rowCount: 1 };
  }

  // Fallback
  return { rows: [], rowCount: 0 };
}

function isDummyConnectionString(str) {
  if (!str) return true;
  const s = String(str).toLowerCase().trim();
  return s === '' || s.includes('@host/') || s.includes('@host:') || s.includes('user:password@host') || s.includes('example.com') || s.includes('your-db-host');
}

function getPool() {
  if (poolFailed) return null;
  if (!pool) {
    const connectionString = process.env.POSTGRES_URL || process.env.DATABASE_URL || process.env.POSTGRES_PRISMA_URL || process.env.DATABASE_URL_UNPOOLED;
    if (!connectionString || isDummyConnectionString(connectionString)) {
      console.warn('[AI Studio] PostgreSQL connection unconfigured or placeholder — using in-memory mock store');
      poolFailed = true;
      return null;
    }
    try {
      pool = new Pool({
        connectionString,
        ssl: process.env.NODE_ENV === 'production' ? { rejectUnauthorized: false } : undefined,
        max: 5,
        idleTimeoutMillis: 5000,
        connectionTimeoutMillis: 2000
      });
      pool.on('error', (err) => {
        console.warn('[AI Studio] PostgreSQL pool error:', err.message);
        poolFailed = true;
      });
    } catch (e) {
      console.warn('[AI Studio] Could not create PostgreSQL pool:', e.message);
      poolFailed = true;
      return null;
    }
  }
  return pool;
}

async function query(text, values = []) {
  const p = getPool();
  if (p) {
    try {
      return await p.query(text, values);
    } catch (err) {
      console.warn('[AI Studio] PostgreSQL query failed, using in-memory fallback:', err.message);
      poolFailed = true;
      return mockQuery(text, values);
    }
  }
  return mockQuery(text, values);
}

async function transaction(callback) {
  const p = getPool();
  if (p) {
    try {
      const client = await p.connect();
      try {
        await client.query('BEGIN');
        const result = await callback(client);
        await client.query('COMMIT');
        return result;
      } catch (error) {
        await client.query('ROLLBACK');
        throw error;
      } finally {
        client.release();
      }
    } catch (err) {
      console.warn('[AI Studio] PostgreSQL transaction failed, using in-memory fallback:', err.message);
      poolFailed = true;
    }
  }
  // In-memory mock transaction
  const mockClient = {
    query: (text, values) => mockQuery(text, values)
  };
  return callback(mockClient);
}

module.exports = { getPool, query, transaction };

