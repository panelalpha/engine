#!/usr/bin/env node
/**
 * PanelAlpha CLI helper for Umami.
 * Runs inside the `umami` container — all dependencies are already present.
 *
 * Auth bootstrap:
 *   1. pg (PostgreSQL client in /app/node_modules/pg)
 *      → find first admin user → get { id, role }
 *   2. node:crypto    →  replicate createSecureToken({ userId, role }, secret())
 *                        (AES-256-GCM encrypted HS256 JWT — stateless, Redis-safe)
 *   3. fetch          →  call official Umami API at http://localhost:3000
 */

import crypto from 'node:crypto';
import fs from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);

// ---------------------------------------------------------------------------
// Module lookup — the image's layout is not ours to rely on. Umami moved to
// pnpm, which hoists only what the app imports directly, so the hardcoded
// /app/node_modules/pg this used stopped resolving.
// ---------------------------------------------------------------------------

function loadModule(name) {
  const roots = ['/app', '/usr/src/app', process.cwd()];
  for (const path of [name, ...roots.map(r => `${r}/node_modules/${name}`)]) {
    try { return require(path); } catch {}
  }
  // pnpm and bun keep packages in an isolated store and hoist only what the
  // app imports directly, so a top-level node_modules/<name> is not there.
  for (const root of roots) {
    for (const store of ['.pnpm', '.bun', '.store']) {
      let entries;
      try { entries = fs.readdirSync(`${root}/node_modules/${store}`); } catch { continue; }
      const matches = entries.filter(e => e.startsWith(`${name}@`)).sort().reverse();
      for (const entry of matches) {
        try { return require(`${root}/node_modules/${store}/${entry}/node_modules/${name}`); } catch {}
      }
    }
  }
  throw new Error(`Cannot locate the '${name}' module inside this image`);
}

// ---------------------------------------------------------------------------
// Database helper — pg directly (Prisma generated client requires a driver
// adapter that is not easily constructed outside the app bundle)
// ---------------------------------------------------------------------------

async function dbQuery(sql, params = []) {
  const { Client } = loadModule('pg');
  const client = new Client({ connectionString: process.env.DATABASE_URL });
  await client.connect();
  try {
    const { rows } = await client.query(sql, params);
    return rows;
  } finally {
    await client.end();
  }
}

// ---------------------------------------------------------------------------
// Crypto helpers — mirror of Umami's src/lib/crypto.ts + src/lib/jwt.ts
// ---------------------------------------------------------------------------

function secret() {
  const input = process.env.APP_SECRET || process.env.DATABASE_URL || '';
  return crypto.createHash('sha512').update(input).digest('hex');
}

/** HS256 JWT (no expiry — stateless tokens are never stored server-side). */
function signJwt(payload, key) {
  const header  = Buffer.from(JSON.stringify({ alg: 'HS256', typ: 'JWT' })).toString('base64url');
  const body    = Buffer.from(JSON.stringify(payload)).toString('base64url');
  const sig     = crypto.createHmac('sha256', key).update(`${header}.${body}`).digest('base64url');
  return `${header}.${body}.${sig}`;
}

/** AES-256-GCM envelope — mirrors Umami's encrypt(). */
function encrypt(value, secretHex) {
  const SALT_LEN = 64, IV_LEN = 16, TAG_LEN = 16;
  const salt = crypto.randomBytes(SALT_LEN);
  const iv   = crypto.randomBytes(IV_LEN);
  const key  = crypto.pbkdf2Sync(secretHex, salt, 10000, 32, 'sha512');
  const cipher = crypto.createCipheriv('aes-256-gcm', key, iv);
  const enc  = Buffer.concat([cipher.update(value, 'utf8'), cipher.final()]);
  const tag  = cipher.getAuthTag();
  return Buffer.concat([salt, iv, tag, enc]).toString('base64');
}

function createSecureToken(payload, sec) {
  return encrypt(signJwt(payload, sec), sec);
}

// ---------------------------------------------------------------------------
// Auth bootstrap
// ---------------------------------------------------------------------------

async function getAdminToken() {
  const rows = await dbQuery('SELECT user_id AS id, role FROM "user" WHERE role = \'admin\' LIMIT 1');
  const admin = rows[0];
  if (!admin) throw new Error('No admin user found — has the app been installed yet?');
  return createSecureToken({ userId: admin.id, role: 'admin' }, secret());
}

// ---------------------------------------------------------------------------
// API helpers
// ---------------------------------------------------------------------------

async function api(method, path, token, body) {
  const res = await fetch(`http://localhost:3000${path}`, {
    method,
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) {
    const text = await res.text().catch(() => res.status);
    throw new Error(`API ${method} ${path} → ${res.status}: ${text}`);
  }
  // 204 No Content
  if (res.status === 204) return {};
  return res.json();
}

// ---------------------------------------------------------------------------
// Commands
// ---------------------------------------------------------------------------

const [,, command, ...args] = process.argv;

if (command === 'info') {
  console.log(JSON.stringify(['users:list','users:add','users:delete','users:reset-password','users:sso','roles:list','install']));
  process.exit(0);
}

if (command === 'roles:list') {
  console.log(JSON.stringify(['admin','user','view-only']));
  process.exit(0);
}

(async () => {
  try {
    switch (command) {

      case 'users:list': {
        const token = await getAdminToken();
        // pageSize=500 is well above any realistic user count
        const data  = await api('GET', '/api/admin/users?pageSize=500', token);
        const users = (data.data || []).map(u => ({ id: u.id, username: u.username, role: u.role }));
        console.log(JSON.stringify(users));
        break;
      }

      case 'users:add': { // <login> <email> <password> <role>
        const [login,, password, role] = args; // email ignored — no email field in Umami schema
        if (!login || !password || !role) throw new Error('Usage: users:add <login> <email> <password> <role>');
        const token  = await getAdminToken();
        const result = await api('POST', '/api/users', token, { username: login, password, role });
        console.log(JSON.stringify({ id: result.id }));
        break;
      }

      case 'users:delete': { // <userId>
        const [userId] = args;
        if (!userId) throw new Error('Usage: users:delete <userId>');
        const token = await getAdminToken();
        await api('DELETE', `/api/users/${userId}`, token);
        console.log(JSON.stringify({ success: true }));
        break;
      }

      case 'users:reset-password': { // <userId> <newPassword>
        const [userId, newPassword] = args;
        if (!userId || !newPassword) throw new Error('Usage: users:reset-password <userId> <newPassword>');
        const token = await getAdminToken();
        await api('POST', `/api/users/${userId}`, token, { password: newPassword });
        console.log(JSON.stringify({ success: true }));
        break;
      }

      case 'users:sso': { // <userId>
        const [userId] = args;
        if (!userId) throw new Error('Usage: users:sso <userId>');

        // Fetch the target user's role — the token must embed the correct role.
        const rows = await dbQuery('SELECT user_id AS id, role FROM "user" WHERE user_id = $1', [userId]);
        const targetUser = rows[0];
        if (!targetUser) throw new Error(`User not found: ${userId}`);

        const sec   = secret();
        const token = createSecureToken({ userId: targetUser.id, role: targetUser.role }, sec);

        // Return a fully-formed path so the engine only needs to prepend the domain.
        const path = `/sso?token=${encodeURIComponent(token)}&url=${encodeURIComponent('/websites')}`;
        console.log(JSON.stringify({ path }));
        break;
      }

      case 'install': { // <url> <title> <adminUser> <adminEmail> <adminPassword>
        const [appUrl,, adminUser,, adminPassword] = args; // title and email ignored
        if (!appUrl || !adminUser || !adminPassword) {
          throw new Error('Usage: install <url> <title> <adminUser> <adminEmail> <adminPassword>');
        }

        // Wait for the default admin account to be created by Prisma migrations.
        let defaultAdmin;
        for (let i = 0; i < 30; i++) {
          const rows = await dbQuery('SELECT user_id AS id, role FROM "user" WHERE role = \'admin\' LIMIT 1');
          defaultAdmin = rows[0];
          if (defaultAdmin) break;
          await new Promise(r => setTimeout(r, 2000));
        }
        if (!defaultAdmin) throw new Error('Timed out waiting for default admin user');

        // Generate a token for the default admin to call the API.
        const sec   = secret();
        const token = createSecureToken({ userId: defaultAdmin.id, role: 'admin' }, sec);

        // Rename the default admin and set the requested password.
        const updated = await api('POST', `/api/users/${defaultAdmin.id}`, token, {
          username: adminUser,
          password: adminPassword,
        });

        console.log(JSON.stringify({ id: updated.id }));
        break;
      }

      default:
        throw new Error(`Unknown command: ${command}`);
    }
  } catch (err) {
    process.stderr.write(JSON.stringify({ error: err.message }) + '\n');
    process.exit(1);
  }
})();
