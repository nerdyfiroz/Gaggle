const crypto = require('crypto');
const { query, transaction } = require('../lib/db');
const { authenticate, issueSession, getSession, requireAdmin, clearCookie, sendJson, safeIp, SESSION_COOKIE } = require('../lib/auth');

const json = (res, code, body) => sendJson(res, code, body);
const nowIp = req => safeIp(req.headers['x-forwarded-for'] || req.headers['x-real-ip'] || req.socket?.remoteAddress);
const clean = value => typeof value === 'string' ? value.trim() : '';
const setting = async (group, key, fallback = null) => { const r = await query('SELECT setting_value FROM settings WHERE setting_group=$1 AND setting_key=$2', [group, key]); return r.rows[0]?.setting_value ?? fallback; };
const id = () => `WL-${crypto.randomBytes(4).toString('hex').toUpperCase()}`;
const normalizeWallet = value => clean(value).toLowerCase();
const validActionUrl = value => { try { const url = new URL(clean(value)); return ['http:', 'https:'].includes(url.protocol); } catch { return false; } };
const captchaSecret = () => process.env.SESSION_SECRET || process.env.AUTH_SECRET || 'gaggle-dev-session-secret-fallback-key-2026';
const signCaptcha = payload => crypto.createHmac('sha256', captchaSecret()).update(payload).digest('base64url');
const createCaptcha = () => { const left = crypto.randomInt(1, 10); const right = crypto.randomInt(1, 10); const payload = Buffer.from(JSON.stringify({ answer: left + right, exp: Math.floor(Date.now() / 1000) + 60, nonce: crypto.randomBytes(8).toString('hex') })).toString('base64url'); return { question: `${left} + ${right} = ?`, token: `${payload}.${signCaptcha(payload)}` }; };
const verifyCaptcha = (token, answer) => { try { if (!captchaSecret()) return false; const [payload, signature] = String(token || '').split('.'); const expected = signCaptcha(payload); if (!payload || !signature || signature.length !== expected.length || !crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))) return false; const challenge = JSON.parse(Buffer.from(payload, 'base64url').toString('utf8')); return challenge.exp >= Math.floor(Date.now() / 1000) && Number(answer) === challenge.answer; } catch { return false; } };
const validWallet = async wallet => {
  const chain = String(await setting('general', 'blockchain', 'ethereum')).toLowerCase();
  if (['solana', 'sol'].includes(chain)) return /^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(wallet);
  if (['bitcoin', 'btc'].includes(chain)) return /^(1|3)[1-9A-HJ-NP-Za-km-z]{25,34}$/.test(wallet) || /^bc1[a-zA-HJ-NP-Z0-9]{25,87}$/.test(wallet);
  return /^0x[0-9a-fA-F]{40}$/.test(wallet);
};
const readBody = req => {
  if (req.body && typeof req.body === 'object' && Object.keys(req.body).length > 0) {
    return Promise.resolve(req.body);
  }
  return new Promise((resolve, reject) => {
    let data='';
    req.on('data', chunk => { data += chunk; if (data.length > 1e6) reject(new Error('Payload too large')); });
    req.on('end', () => { try { resolve(data ? JSON.parse(data) : (req.body || {})); } catch { resolve(req.body || {}); } });
    req.on('error', reject);
  });
};
const audit = (session, req, action, targetType = null, targetId = null, details = null) => query('INSERT INTO admin_logs(admin_id,admin_username,action,target_type,target_id,details,ip_address) VALUES($1,$2,$3,$4,$5,$6,$7)', [session?.adminId, session?.username, action, targetType, targetId == null ? null : String(targetId), details, nowIp(req)]);

async function publicRoute(req, res, path, body) {
  if (path === '/captcha' && req.method === 'GET') {
    if (!captchaSecret()) return json(res, 500, { success:false, message:'Captcha is not configured.' });
    return json(res, 200, { success:true, captcha:createCaptcha() });
  }
  if (path === '/config' && req.method === 'GET') {
    const general = await query("SELECT setting_key, setting_value FROM settings WHERE setting_group='general'");
    const application = await query("SELECT setting_key, setting_value FROM settings WHERE setting_group='application'");
    const captcha = await query("SELECT setting_key, setting_value FROM settings WHERE setting_group='captcha' AND setting_key='captcha_enabled'");
    const values = Object.fromEntries([...general.rows, ...application.rows, ...captcha.rows].map(row => [row.setting_key, row.setting_value]));
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
    let twitter = clean(body.twitter_username || '').replace(/^@/, '').toLowerCase();
    if (!twitter) {
      for (const [k, v] of Object.entries(body)) {
        if (k.startsWith('task_proof_') || k.startsWith('proof_')) {
          const val = clean(v || '');
          const handleMatch = val.match(/^(?:https?:\/\/(?:www\.|mobile\.)?(?:x|twitter)\.com\/)?@?([a-zA-Z0-9_]{1,25})\/?$/i);
          if (handleMatch && handleMatch[1] && !['status', 'home', 'explore', 'i'].includes(handleMatch[1].toLowerCase())) {
            twitter = handleMatch[1].toLowerCase();
            break;
          }
          const statusMatch = val.match(/^(?:https?:\/\/(?:www\.|mobile\.)?(?:x|twitter)\.com\/)([a-zA-Z0-9_]{1,25})\/status\/\d+/i);
          if (statusMatch && statusMatch[1] && !['i'].includes(statusMatch[1].toLowerCase())) {
            twitter = twitter || statusMatch[1].toLowerCase();
          }
        }
      }
    }
    if (await setting('application','require_twitter','1') === '1' && !twitter) errors.twitter_username='Twitter/X username or link is required.';
    if (Object.keys(errors).length) return json(res, 422, { success:false, message:'Please fix the errors below.', errors });
    const rateWindow = Number(await setting('application','rate_limit_window_minutes','10')); const rateMax = Number(await setting('application','rate_limit_max_requests','5')); const rate = await query("SELECT COUNT(*)::int count FROM rate_limits WHERE ip_address=$1 AND endpoint='apply' AND created_at >= NOW() - ($2 || ' minutes')::interval", [ip, rateWindow]); if (rate.rows[0].count >= rateMax) return json(res,429,{success:false,message:`Too many submissions. Please wait ${rateWindow} minutes before trying again.`,errors:{}});
    const lifetime = Number(await setting('application','ip_application_limit','100')); const total = await query('SELECT COUNT(*)::int count FROM applications WHERE ip_address=$1',[ip]); if (lifetime > 0 && total.rows[0].count >= lifetime) return json(res,429,{success:false,message:'Maximum application limit reached for this IP address.',errors:{}});
    const blacklist = await query("SELECT type FROM blacklist WHERE (type='wallet' AND value=$1) OR (type='ip' AND value=$2) OR (type='twitter' AND value=$3)",[normalizeWallet(wallet),ip,twitter]); if (blacklist.rowCount) return json(res,403,{success:false,message:'This submission is not eligible.',errors:{}});
    const duplicateWallet = await setting('application','duplicate_wallet_protection','1'); if (duplicateWallet === '1' && (await query('SELECT 1 FROM applications WHERE wallet_normalized=$1',[normalizeWallet(wallet)])).rowCount) return json(res,409,{success:false,message:'This wallet address has already been submitted.',errors:{}});
    const tasks = [...new Set((Array.isArray(body.tasks) ? body.tasks : []).map(Number).filter(Number.isInteger))];
    const taskResult = await query('SELECT id,title,description,type,url,required FROM tasks WHERE enabled=true');
    const allowed = taskResult.rows.map(row=>Number(row.id));
    if (tasks.some(task=>!allowed.includes(task)) || taskResult.rows.some(row=>row.required && !tasks.includes(Number(row.id)))) return json(res,422,{success:false,message:'Please complete all required tasks.',errors:{}});
    const taskProofs = {};
    const tweetStatusRegex = /^(https?:\/\/)?([a-zA-Z0-9_\-\.]+\.)?(x|twitter)\.com\/([a-zA-Z0-9_]{1,50}|i)\/status\/(\d+)/i;
    for (const taskRow of taskResult.rows) {
      const taskId = Number(taskRow.id);
      const isRequired = Boolean(taskRow.required);
      let proof = clean(body[`task_proof_${taskId}`] || body[`proof_${taskId}`] || (body.task_proofs && body.task_proofs[taskId]) || '');
      const isTweetTask = ['twitter_reply', 'twitter_like', 'twitter_retweet'].includes(taskRow.type) ||
                          /(reply|comment|retweet|pinned|post|tweet)/i.test(taskRow.title || '') ||
                          /(reply|comment|retweet|pinned|post|tweet)/i.test(taskRow.description || '');
      if (isRequired && !proof) {
        return json(res, 422, {
          success: false,
          message: isTweetTask ? 'Please submit your tweet comment or reply link.' : `Please complete the required task: "${taskRow.title}".`,
          errors: { [`task_proof_${taskId}`]: 'This task proof is required.' }
        });
      }
      if (proof) {
        if (isTweetTask) {
          if (!tweetStatusRegex.test(proof)) {
            return json(res, 422, {
              success: false,
              message: 'Please submit a valid tweet comment or reply link (e.g. https://x.com/username/status/1234567890).',
              errors: { [`task_proof_${taskId}`]: 'Must be a valid x.com or twitter.com status link.' }
            });
          }
          if (!/^https?:\/\//i.test(proof)) proof = `https://${proof}`;
          if (taskRow.url && /status\/(\d+)/i.test(taskRow.url)) {
            const pinnedId = taskRow.url.match(/status\/(\d+)/i)?.[1];
            const submittedId = proof.match(/status\/(\d+)/i)?.[1];
            if (pinnedId && submittedId && pinnedId === submittedId) {
              return json(res, 422, {
                success: false,
                message: 'Please submit your own reply or comment link, not the pinned post link.',
                errors: { [`task_proof_${taskId}`]: 'Must be your own comment or reply link.' }
              });
            }
          }
        }
        taskProofs[taskId] = proof;
      }
    }
    try { const application = await transaction(async client => { const appId = id(); const inserted = await client.query('INSERT INTO applications(application_id,wallet_address,wallet_normalized,twitter_username,email,ip_address,user_agent) VALUES($1,$2,$3,$4,NULL,$5,$6) RETURNING id,application_id,created_at,status',[appId,wallet,normalizeWallet(wallet),twitter||null,ip,req.headers['user-agent']||null]); for (const task of tasks) await client.query('INSERT INTO application_tasks(application_id,task_id,proof) VALUES($1,$2,$3)',[inserted.rows[0].id,task,taskProofs[task]||null]); await client.query("INSERT INTO rate_limits(ip_address,endpoint) VALUES($1,'apply')",[ip]); return inserted.rows[0]; }); return json(res,201,{success:true,message:'Application submitted successfully!',application_id:application.application_id,redirect:`/success?id=${application.application_id}`}); } catch (error) { if (error.code === '23505') return json(res,409,{success:false,message:'This wallet address has already been submitted.',errors:{}}); throw error; }
  }
  return false;
}

async function adminRoute(req,res,path,body) {
  if (path === '/auth/login' && req.method === 'POST') { const result = await authenticate(clean(body.username), String(body.password||''), nowIp(req)); if (result.error) return json(res,401,{success:false,message:result.error}); const csrf = issueSession(res,result.admin); await audit({adminId:result.admin.id,username:result.admin.username},req,'login','admin',result.admin.id); return json(res,200,{success:true,csrf,admin:{id:result.admin.id,username:result.admin.username,force_password_change:result.admin.force_password_change}}); }
  if (path === '/auth/logout' && req.method === 'POST') { const session = getSession(req); if (session) await audit(session,req,'logout','admin',session.adminId); clearCookie(res,SESSION_COOKIE); return json(res,200,{success:true}); }
  const session = requireAdmin(req,res); if (!session) return true;
  if (path === '/dashboard' && req.method === 'GET') { const result=await query("SELECT COUNT(*)::int total, COUNT(*) FILTER(WHERE status='pending')::int pending, COUNT(*) FILTER(WHERE status='approved')::int approved, COUNT(*) FILTER(WHERE status='rejected')::int rejected, COUNT(*) FILTER(WHERE whitelist_slot IS NOT NULL)::int assigned, COUNT(*) FILTER(WHERE created_at::date=CURRENT_DATE)::int today, COUNT(DISTINCT wallet_normalized)::int unique_wallets FROM applications"); return json(res,200,{success:true,stats:result.rows[0]}); }
  if (path === '/applications' && req.method === 'GET') { const page=Math.max(1,Number(req.query?.page||1)), limit=Math.min(100,Math.max(1,Number(req.query?.limit||25))), offset=(page-1)*limit; const values=[]; const where=[]; if(req.query?.status){values.push(req.query.status);where.push(`status=$${values.length}`);} if(req.query?.search){values.push(`%${req.query.search}%`);where.push(`(application_id ILIKE $${values.length} OR wallet_address ILIKE $${values.length} OR twitter_username ILIKE $${values.length} OR ip_address::text ILIKE $${values.length})`);} const clause=where.length?`WHERE ${where.join(' AND ')}`:''; values.push(limit,offset); const result=await query(`SELECT * FROM applications ${clause} ORDER BY created_at DESC LIMIT $${values.length-1} OFFSET $${values.length}`,values); return json(res,200,{success:true,applications:result.rows,page,limit}); }
  if (path === '/applications/status' && req.method === 'PATCH') { const statuses=['pending','approved','rejected','blacklisted','review']; if(!statuses.includes(body.status))return json(res,422,{success:false,message:'Invalid status.'}); const result=await query('UPDATE applications SET status=$1,updated_at=NOW() WHERE id=$2 RETURNING id',[body.status,Number(body.id)]); if(!result.rowCount)return json(res,404,{success:false,message:'Application not found.'}); await audit(session,req,'status_change','application',body.id,body.status); return json(res,200,{success:true}); }
  if (path === '/applications/slot' && req.method === 'PATCH') { const applicationId=Number(body.id); const slot=body.slot === '' || body.slot == null ? null : Number(body.slot); if(!Number.isInteger(applicationId)||(!Number.isInteger(slot)&&slot!==null)||slot!==null&&slot<1)return json(res,422,{success:false,message:'Enter a valid positive slot number or leave it blank to unassign.'}); try { const result=await query('UPDATE applications SET whitelist_slot=$1,slot_assigned_at=CASE WHEN $1 IS NULL THEN NULL ELSE NOW() END,slot_assigned_by=CASE WHEN $1 IS NULL THEN NULL ELSE $2 END,status=CASE WHEN $1 IS NULL THEN status ELSE \'approved\' END,updated_at=NOW() WHERE id=$3 RETURNING id,application_id,whitelist_slot,status',[slot,session.adminId,applicationId]); if(!result.rowCount)return json(res,404,{success:false,message:'Application not found.'}); await audit(session,req,slot===null?'unassign_slot':'assign_slot','application',applicationId,slot===null?'':String(slot)); return json(res,200,{success:true,application:result.rows[0]}); } catch(error) { if(error.code==='23505')return json(res,409,{success:false,message:'That whitelist slot is already assigned.'}); throw error; } }
  if (path === '/tasks' && req.method === 'GET') { const result=await query('SELECT * FROM tasks ORDER BY sort_order,id'); return json(res,200,{success:true,tasks:result.rows}); }
  if (path === '/tasks' && req.method === 'POST') { if(!clean(body.title)||!validActionUrl(body.url))return json(res,422,{success:false,message:'Task title and a valid http(s) action link are required.'}); const result=await query('INSERT INTO tasks(title,description,type,url,required,enabled,sort_order) VALUES($1,$2,$3,$4,$5,$6,COALESCE((SELECT MAX(sort_order)+1 FROM tasks),1)) RETURNING *',[clean(body.title),clean(body.description),clean(body.type)||'custom',clean(body.url),body.required!==false,body.enabled!==false]); await audit(session,req,'create_task','task',result.rows[0].id); return json(res,201,{success:true,task:result.rows[0]}); }
  if (path === '/tasks' && req.method === 'PATCH') { if(!Number.isInteger(Number(body.id))||!clean(body.title)||!validActionUrl(body.url))return json(res,422,{success:false,message:'Task id, title, and a valid http(s) action link are required.'}); const result=await query('UPDATE tasks SET title=$1,description=$2,type=$3,url=$4,required=$5,enabled=$6,sort_order=$7,updated_at=NOW() WHERE id=$8 RETURNING *',[clean(body.title),clean(body.description),clean(body.type)||'custom',clean(body.url),body.required===true,body.enabled!==false,Number.isInteger(Number(body.sort_order))?Number(body.sort_order):0,Number(body.id)]); if(!result.rowCount)return json(res,404,{success:false,message:'Task not found.'}); await audit(session,req,'update_task','task',body.id); return json(res,200,{success:true,task:result.rows[0]}); }
  if (path === '/tasks' && req.method === 'DELETE') { const taskId=Number(req.query?.id||body.id); if(!Number.isInteger(taskId))return json(res,422,{success:false,message:'Task id is required.'}); const result=await query('DELETE FROM tasks WHERE id=$1 RETURNING id',[taskId]); if(!result.rowCount)return json(res,404,{success:false,message:'Task not found.'}); await audit(session,req,'delete_task','task',taskId); return json(res,200,{success:true}); }
  if (path === '/settings' && req.method === 'GET') { const result=await query('SELECT setting_group,setting_key,setting_value FROM settings ORDER BY setting_group,setting_key'); return json(res,200,{success:true,settings:result.rows}); }
  if (path === '/settings' && req.method === 'PUT') { const allowed=['general','application','captcha','security']; if(!allowed.includes(body.group)||!body.settings||typeof body.settings!=='object')return json(res,422,{success:false,message:'Invalid settings.'}); for(const [key,value] of Object.entries(body.settings)) await query('INSERT INTO settings(setting_group,setting_key,setting_value) VALUES($1,$2,$3) ON CONFLICT(setting_group,setting_key) DO UPDATE SET setting_value=EXCLUDED.setting_value,updated_at=NOW()',[body.group,key,String(value)]); await audit(session,req,'update_settings','settings',body.group); return json(res,200,{success:true}); }
  return false;
}

module.exports = async function handler(req, res) {
  try {
    const rawUrl = req.originalUrl || req.url;
    const url = new URL(rawUrl, `https://${req.headers.host || 'localhost'}`);
    req.query = { ...(req.query || {}), ...Object.fromEntries(url.searchParams) };
    const body = ['POST', 'PUT', 'PATCH'].includes(req.method) ? await readBody(req) : {};
    let path = url.pathname;
    if (path.startsWith('/api')) {
      path = path.slice(4) || '/';
    }
    if (path.startsWith('/admin/auth')) {
      path = path.slice(6) || '/';
    }
    if (!path.startsWith('/')) path = '/' + path;
    path = path.replace(/\/+$/, '') || '/';
    const result = await publicRoute(req, res, path, body);
    if (result !== false) return;
    const adminResult = await adminRoute(req, res, path, body);
    if (adminResult !== false) return;
    json(res, 404, { success: false, message: 'Not found.' });
  } catch (error) {
    console.error(error);
    json(res, 500, { success: false, message: 'Internal server error.' });
  }
};
