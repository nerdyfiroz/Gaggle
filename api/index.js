const crypto = require('crypto');
const { query, transaction } = require('../lib/db');
const { authenticate, issueSession, getSession, requireAdmin, clearCookie, sendJson, SESSION_COOKIE } = require('../lib/auth');

const json = (res, code, body) => sendJson(res, code, body);
const nowIp = req => String(req.headers['x-forwarded-for'] || req.headers['x-real-ip'] || req.socket?.remoteAddress || '0.0.0.0').split(',')[0].trim();
const clean = value => typeof value === 'string' ? value.trim() : '';
const setting = async (group, key, fallback = null) => { const r = await query('SELECT setting_value FROM settings WHERE setting_group=$1 AND setting_key=$2', [group, key]); return r.rows[0]?.setting_value ?? fallback; };
const id = () => `WL-${crypto.randomBytes(4).toString('hex').toUpperCase()}`;
const normalizeWallet = value => clean(value).toLowerCase();
const validWallet = async wallet => {
  const chain = String(await setting('general', 'blockchain', 'ethereum')).toLowerCase();
  if (['solana', 'sol'].includes(chain)) return /^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(wallet);
  if (['bitcoin', 'btc'].includes(chain)) return /^(1|3)[1-9A-HJ-NP-Za-km-z]{25,34}$/.test(wallet) || /^bc1[a-zA-HJ-NP-Z0-9]{25,87}$/.test(wallet);
  return /^0x[0-9a-fA-F]{40}$/.test(wallet);
};
const readBody = req => new Promise((resolve, reject) => { let data=''; req.on('data', chunk => { data += chunk; if (data.length > 1e6) reject(new Error('Payload too large')); }); req.on('end', () => { try { resolve(data ? JSON.parse(data) : {}); } catch { resolve({}); } }); req.on('error', reject); });
const audit = (session, req, action, targetType = null, targetId = null, details = null) => query('INSERT INTO admin_logs(admin_id,admin_username,action,target_type,target_id,details,ip_address) VALUES($1,$2,$3,$4,$5,$6,$7)', [session?.adminId, session?.username, action, targetType, targetId == null ? null : String(targetId), details, nowIp(req)]);

async function publicRoute(req, res, path, body) {
  if (path === '/config' && req.method === 'GET') {
    const general = await query("SELECT setting_key, setting_value FROM settings WHERE setting_group='general'");
    const application = await query("SELECT setting_key, setting_value FROM settings WHERE setting_group='application'");
    const values = Object.fromEntries([...general.rows, ...application.rows].map(row => [row.setting_key, row.setting_value]));
    return json(res, 200, { success: true, settings: values });
  }
  if (path === '/tasks' && req.method === 'GET') {
    const result = await query('SELECT id,title,description,type,url,required,sort_order FROM tasks WHERE enabled=true ORDER BY sort_order,id');
    return json(res, 200, { success: true, tasks: result.rows });
  }
  if (path === '/status' && req.method === 'GET') {
    const ip = nowIp(req);
    const limited = await query("SELECT COUNT(*)::int count FROM rate_limits WHERE ip_address=$1 AND endpoint='status_lookup' AND created_at >= NOW()-INTERVAL '10 minutes'", [ip]);
    if (limited.rows[0].count >= 30) return json(res, 429, { success:false, message:'Too many status checks. Please try again later.' });
    await query("INSERT INTO rate_limits(ip_address,endpoint) VALUES($1,'status_lookup')", [ip]);
    const appId = clean(req.query?.id || '').toUpperCase();
    if (!/^WL-[A-F0-9]{8}$/.test(appId)) return json(res, 404, { success:false, message:'Application not found.' });
    const result = await query('SELECT application_id,wallet_address,created_at,status FROM applications WHERE application_id=$1', [appId]);
    if (!result.rows[0]) return json(res, 404, { success:false, message:'Application not found.' });
    const app = result.rows[0];
    return json(res, 200, { success:true, application:{ application_id:app.application_id, wallet:`${app.wallet_address.slice(0,6)}...${app.wallet_address.slice(-4)}`, created_at:app.created_at, status:app.status } });
  }
  if (path === '/applications' && req.method === 'POST') {
    if (clean(body.website_url)) return json(res, 422, { success:false, message:'Submission rejected.', errors:{} });
    const ip = nowIp(req);
    const enabled = await setting('application', 'applications_enabled', '1');
    if (enabled !== '1') return json(res, 403, { success:false, message:'Whitelist applications are currently closed.', errors:{} });
    const blocked = await query('SELECT 1 FROM blocked_ips WHERE ip_address=$1', [ip]);
    if (blocked.rowCount) return json(res, 403, { success:false, message:'Your IP address has been blocked from submitting applications.', errors:{} });
    const wallet = clean(body.wallet_address); const errors = {};
    if (!wallet) errors.wallet_address = 'Wallet address is required.'; else if (!(await validWallet(wallet))) errors.wallet_address = 'Please enter a valid wallet address.';
    const twitter = clean(body.twitter_username).replace(/^@/, '').toLowerCase(); const discord = clean(body.discord_username); const telegram = clean(body.telegram_username).replace(/^@/, '').toLowerCase(); const email = clean(body.email).toLowerCase();
    if (await setting('application','require_twitter','1') === '1' && !twitter) errors.twitter_username='Twitter/X username is required.';
    if (await setting('application','require_discord','1') === '1' && !discord) errors.discord_username='Discord username is required.';
    if (await setting('application','require_telegram','0') === '1' && !telegram) errors.telegram_username='Telegram username is required.';
    if (await setting('application','require_email','0') === '1' && !email) errors.email='Email address is required.';
    if (email && !/^\S+@\S+\.\S+$/.test(email)) errors.email='Please enter a valid email address.';
    if (Object.keys(errors).length) return json(res, 422, { success:false, message:'Please fix the errors below.', errors });
    const captchaEnabled = await setting('captcha','captcha_enabled','0');
    if (captchaEnabled === '1') { const token = body.captcha_token || body['cf-turnstile-response'] || body['g-recaptcha-response']; const secret = process.env.CAPTCHA_SECRET_KEY; if (!token || !secret) return json(res, 422, {success:false,message:'CAPTCHA verification failed.',errors:{}}); const endpoint = (await setting('captcha','captcha_provider','turnstile')) === 'recaptcha' ? 'https://www.google.com/recaptcha/api/siteverify' : 'https://challenges.cloudflare.com/turnstile/v0/siteverify'; const response = await fetch(endpoint,{method:'POST',headers:{'content-type':'application/x-www-form-urlencoded'},body:new URLSearchParams({secret,response:token,remoteip:ip})}); if (!(await response.json()).success) return json(res,422,{success:false,message:'CAPTCHA verification failed.',errors:{}}); }
    const rateWindow = Number(await setting('application','rate_limit_window_minutes','10')); const rateMax = Number(await setting('application','rate_limit_max_requests','5')); const rate = await query("SELECT COUNT(*)::int count FROM rate_limits WHERE ip_address=$1 AND endpoint='apply' AND created_at >= NOW() - ($2 || ' minutes')::interval", [ip, rateWindow]); if (rate.rows[0].count >= rateMax) return json(res,429,{success:false,message:`Too many submissions. Please wait ${rateWindow} minutes before trying again.`,errors:{}});
    const lifetime = Number(await setting('application','ip_application_limit','100')); const total = await query('SELECT COUNT(*)::int count FROM applications WHERE ip_address=$1',[ip]); if (lifetime > 0 && total.rows[0].count >= lifetime) return json(res,429,{success:false,message:'Maximum application limit reached for this IP address.',errors:{}});
    const blacklist = await query("SELECT type FROM blacklist WHERE (type='wallet' AND value=$1) OR (type='ip' AND value=$2) OR (type='twitter' AND value=$3) OR (type='discord' AND value=$4)",[normalizeWallet(wallet),ip,twitter,discord]); if (blacklist.rowCount) return json(res,403,{success:false,message:'This submission is not eligible.',errors:{}});
    const duplicateWallet = await setting('application','duplicate_wallet_protection','1'); if (duplicateWallet === '1' && (await query('SELECT 1 FROM applications WHERE wallet_normalized=$1',[normalizeWallet(wallet)])).rowCount) return json(res,409,{success:false,message:'This wallet address has already been submitted.',errors:{}});
    const tasks = [...new Set((Array.isArray(body.tasks) ? body.tasks : []).map(Number).filter(Number.isInteger))]; const taskResult = await query('SELECT id,required FROM tasks WHERE enabled=true'); const allowed = taskResult.rows.map(row=>Number(row.id)); if (tasks.some(task=>!allowed.includes(task)) || taskResult.rows.some(row=>row.required && !tasks.includes(Number(row.id)))) return json(res,422,{success:false,message:'Please complete all required tasks.',errors:{}});
    try { const application = await transaction(async client => { const appId = id(); const inserted = await client.query('INSERT INTO applications(application_id,wallet_address,wallet_normalized,twitter_username,discord_username,telegram_username,email,ip_address,user_agent) VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9) RETURNING id,application_id,created_at,status',[appId,wallet,normalizeWallet(wallet),twitter||null,discord||null,telegram||null,email||null,ip,req.headers['user-agent']||null]); for (const task of tasks) await client.query('INSERT INTO application_tasks(application_id,task_id) VALUES($1,$2)',[inserted.rows[0].id,task]); await client.query("INSERT INTO rate_limits(ip_address,endpoint) VALUES($1,'apply')",[ip]); return inserted.rows[0]; }); return json(res,201,{success:true,message:'Application submitted successfully!',application_id:application.application_id,redirect:`/success?id=${application.application_id}`}); } catch (error) { if (error.code === '23505') return json(res,409,{success:false,message:'This wallet address has already been submitted.',errors:{}}); throw error; }
  }
  return false;
}

async function adminRoute(req,res,path,body) {
  if (path === '/auth/login' && req.method === 'POST') { const result = await authenticate(clean(body.username), String(body.password||''), nowIp(req)); if (result.error) return json(res,401,{success:false,message:result.error}); issueSession(res,result.admin); await audit({adminId:result.admin.id,username:result.admin.username},req,'login','admin',result.admin.id); return json(res,200,{success:true,admin:{id:result.admin.id,username:result.admin.username,force_password_change:result.admin.force_password_change}}); }
  if (path === '/auth/logout' && req.method === 'POST') { const session = getSession(req); if (session) await audit(session,req,'logout','admin',session.adminId); clearCookie(res,SESSION_COOKIE); return json(res,200,{success:true}); }
  const session = requireAdmin(req,res); if (!session) return true;
  if (path === '/dashboard' && req.method === 'GET') { const result=await query("SELECT COUNT(*)::int total, COUNT(*) FILTER(WHERE status='pending')::int pending, COUNT(*) FILTER(WHERE status='approved')::int approved, COUNT(*) FILTER(WHERE status='rejected')::int rejected, COUNT(*) FILTER(WHERE created_at::date=CURRENT_DATE)::int today, COUNT(DISTINCT wallet_normalized)::int unique_wallets FROM applications"); return json(res,200,{success:true,stats:result.rows[0]}); }
  if (path === '/applications' && req.method === 'GET') { const page=Math.max(1,Number(req.query?.page||1)), limit=Math.min(100,Math.max(1,Number(req.query?.limit||25))), offset=(page-1)*limit; const values=[]; const where=[]; if(req.query?.status){values.push(req.query.status);where.push(`status=$${values.length}`);} if(req.query?.search){values.push(`%${req.query.search}%`);where.push(`(application_id ILIKE $${values.length} OR wallet_address ILIKE $${values.length} OR twitter_username ILIKE $${values.length} OR discord_username ILIKE $${values.length} OR ip_address::text ILIKE $${values.length})`);} const clause=where.length?`WHERE ${where.join(' AND ')}`:''; values.push(limit,offset); const result=await query(`SELECT * FROM applications ${clause} ORDER BY created_at DESC LIMIT $${values.length-1} OFFSET $${values.length}`,values); return json(res,200,{success:true,applications:result.rows,page,limit}); }
  if (path === '/applications/status' && req.method === 'PATCH') { const statuses=['pending','approved','rejected','blacklisted','review']; if(!statuses.includes(body.status))return json(res,422,{success:false,message:'Invalid status.'}); await query('UPDATE applications SET status=$1,updated_at=NOW() WHERE id=$2',[body.status,Number(body.id)]); await audit(session,req,'status_change','application',body.id,body.status); return json(res,200,{success:true}); }
  if (path === '/tasks' && req.method === 'GET') { const result=await query('SELECT * FROM tasks ORDER BY sort_order,id'); return json(res,200,{success:true,tasks:result.rows}); }
  if (path === '/tasks' && req.method === 'POST') { const result=await query('INSERT INTO tasks(title,description,type,url,required,enabled,sort_order) VALUES($1,$2,$3,$4,$5,$6,COALESCE((SELECT MAX(sort_order)+1 FROM tasks),1)) RETURNING *',[clean(body.title),clean(body.description),clean(body.type)||'custom',clean(body.url)||null,body.required!==false,body.enabled!==false]); await audit(session,req,'create_task','task',result.rows[0].id); return json(res,201,{success:true,task:result.rows[0]}); }
  if (path === '/settings' && req.method === 'GET') { const result=await query('SELECT setting_group,setting_key,setting_value FROM settings ORDER BY setting_group,setting_key'); return json(res,200,{success:true,settings:result.rows}); }
  if (path === '/settings' && req.method === 'PUT') { const allowed=['general','application','captcha','security']; if(!allowed.includes(body.group)||!body.settings||typeof body.settings!=='object')return json(res,422,{success:false,message:'Invalid settings.'}); for(const [key,value] of Object.entries(body.settings)) await query('INSERT INTO settings(setting_group,setting_key,setting_value) VALUES($1,$2,$3) ON CONFLICT(setting_group,setting_key) DO UPDATE SET setting_value=EXCLUDED.setting_value,updated_at=NOW()',[body.group,key,String(value)]); await audit(session,req,'update_settings','settings',body.group); return json(res,200,{success:true}); }
  return false;
}

module.exports = async function handler(req,res) { try { const url = new URL(req.url, `https://${req.headers.host || 'localhost'}`); req.query=Object.fromEntries(url.searchParams); const body = ['POST','PUT','PATCH'].includes(req.method) ? await readBody(req) : {}; const path=url.pathname.replace(/^\/api/,'') || '/'; const result=await publicRoute(req,res,path,body); if(result!==false)return; const adminResult=await adminRoute(req,res,path,body); if(adminResult!==false)return; json(res,404,{success:false,message:'Not found.'}); } catch(error) { console.error(error); json(res,500,{success:false,message:'Internal server error.'}); } };
