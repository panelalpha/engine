#!/usr/bin/env node
/**
 * PanelAlpha CLI helper for Cal.diy (calcom/cal.diy).
 * Runs inside the `calcom` container via:
 *   docker compose exec -T calcom node --no-warnings /calcom/panelalpha/panelalpha-cli.mjs <command> [args...]
 *
 * User management: direct SQL via pg (avoids @prisma/client code-generation path issues in the monorepo image).
 * SSO: Pattern B — mints a next-auth v4 JWE session cookie using jose + Node.js built-in hkdfSync.
 *   Key derivation mirrors next-auth v4.24 encode():
 *     hkdf('sha256', NEXTAUTH_SECRET, cookieName, `NextAuth.js Generated Encryption Key (${cookieName})`, 32)
 *
 * Table names follow the Prisma schema (@@map where present):
 *   users       ← User model (@@map: "users")
 *   UserPassword ← UserPassword model (no @@map, stored quoted)
 */

import { hkdfSync, randomUUID } from 'node:crypto';
import pgPkg from 'pg';
const { Client } = pgPkg;
import { EncryptJWT } from 'jose';
import bcrypt from 'bcryptjs';

// ---------------------------------------------------------------------------
// DB helper — creates a fresh client per call; reads DATABASE_URL from env
// ---------------------------------------------------------------------------

async function withDb(fn) {
  const client = new Client({ connectionString: process.env.DATABASE_URL });
  await client.connect();
  try {
    return await fn(client);
  } finally {
    await client.end();
  }
}

// ---------------------------------------------------------------------------
// SSO — mint a next-auth v4 JWE session cookie (Pattern B)
// ---------------------------------------------------------------------------

async function mintSessionToken(user) {
  const secret = process.env.NEXTAUTH_SECRET;
  if (!secret) throw new Error('NEXTAUTH_SECRET not set in container env');
  const cookieName = 'next-auth.session-token';
  const keyBytes = hkdfSync(
    'sha256',
    secret,
    cookieName,
    `NextAuth.js Generated Encryption Key (${cookieName})`,
    32
  );
  return new EncryptJWT({
    name:    user.name || user.email,
    email:   user.email,
    picture: null,
    sub:     String(user.id),
  })
    .setProtectedHeader({ alg: 'dir', enc: 'A256GCM' })
    .setIssuedAt()
    .setExpirationTime(Math.floor(Date.now() / 1000) + 30 * 24 * 60 * 60)
    .setJti(randomUUID())
    .encrypt(new Uint8Array(keyBytes));
}

// ---------------------------------------------------------------------------
// Commands
// ---------------------------------------------------------------------------

const [,, command, ...args] = process.argv;

if (command === 'info') {
  console.log(JSON.stringify(['install', 'roles:list', 'users:list', 'users:add', 'users:delete', 'users:reset-password', 'users:sso']));
  process.exit(0);
}

if (command === 'roles:list') {
  console.log(JSON.stringify(['admin', 'user']));
  process.exit(0);
}

(async () => {
  try {
    switch (command) {

      case 'install': { // <url> <title> <adminUser> <adminEmail> <adminPassword>
        const [, , adminUser, adminEmail, adminPassword] = args;
        if (!adminUser || !adminEmail || !adminPassword) {
          throw new Error('Usage: install <url> <title> <adminUser> <adminEmail> <adminPassword>');
        }
        const hash = await bcrypt.hash(adminPassword, 12);
        const id = await withDb(async (db) => {
          // Idempotent — return existing admin if already installed
          const existing = await db.query(
            `SELECT id FROM users WHERE role = 'ADMIN' ORDER BY id LIMIT 1`
          );
          if (existing.rows.length > 0) return String(existing.rows[0].id);

          const res = await db.query(
            `INSERT INTO users (uuid, email, name, "emailVerified", role, "identityProvider")
             VALUES (gen_random_uuid(), $1, $2, NOW(), 'ADMIN', 'CAL') RETURNING id`,
            [adminEmail, adminUser]
          );
          const userId = res.rows[0].id;
          await db.query(
            `INSERT INTO "UserPassword" ("userId", hash) VALUES ($1, $2)`,
            [userId, hash]
          );
          return String(userId);
        });
        console.log(JSON.stringify({ id }));
        break;
      }

      case 'users:list': {
        const rows = await withDb(db =>
          db.query(`SELECT id, username, email, role FROM users ORDER BY id`)
        );
        console.log(JSON.stringify(rows.rows.map(u => ({
          id:       String(u.id),
          username: u.username || u.email.split('@')[0],
          email:    u.email,
          role:     u.role.toLowerCase(),
        }))));
        break;
      }

      case 'users:add': { // <login> <email> <password> <role>
        const [login, email, password, role] = args;
        if (!login || !email || !password || !role) {
          throw new Error('Usage: users:add <login> <email> <password> <role>');
        }
        const hash = await bcrypt.hash(password, 12);
        const pgRole = role === 'admin' ? 'ADMIN' : 'USER';
        const id = await withDb(async (db) => {
          const res = await db.query(
            `INSERT INTO users (uuid, email, name, "emailVerified", role, "identityProvider")
             VALUES (gen_random_uuid(), $1, $2, NOW(), $3, 'CAL') RETURNING id`,
            [email, login, pgRole]
          );
          const userId = res.rows[0].id;
          await db.query(
            `INSERT INTO "UserPassword" ("userId", hash) VALUES ($1, $2)`,
            [userId, hash]
          );
          return String(userId);
        });
        console.log(JSON.stringify({ id }));
        break;
      }

      case 'users:delete': { // <userId>
        const [userId] = args;
        if (!userId) throw new Error('Usage: users:delete <userId>');
        // FK cascade (onDelete: Cascade in Prisma schema) handles UserPassword and other relations
        await withDb(db =>
          db.query(`DELETE FROM users WHERE id = $1`, [parseInt(userId, 10)])
        );
        console.log(JSON.stringify({ success: true }));
        break;
      }

      case 'users:reset-password': { // <userId> <newPassword>
        const [userId, newPassword] = args;
        if (!userId || !newPassword) throw new Error('Usage: users:reset-password <userId> <newPassword>');
        const hash = await bcrypt.hash(newPassword, 12);
        await withDb(db =>
          db.query(
            `INSERT INTO "UserPassword" ("userId", hash) VALUES ($1, $2)
             ON CONFLICT ("userId") DO UPDATE SET hash = EXCLUDED.hash`,
            [parseInt(userId, 10), hash]
          )
        );
        console.log(JSON.stringify({ success: true }));
        break;
      }

      case 'users:sso': { // <userId>
        const [userId] = args;
        if (!userId) throw new Error('Usage: users:sso <userId>');
        const user = await withDb(async (db) => {
          const res = await db.query(
            `SELECT id, email, name FROM users WHERE id = $1`, [parseInt(userId, 10)]
          );
          if (res.rows.length === 0) throw new Error(`User not found: ${userId}`);
          return res.rows[0];
        });
        const token = await mintSessionToken(user);
        console.log(JSON.stringify({ cookie: 'next-auth.session-token', value: token, redirect: '/' }));
        break;
      }

      default:
        throw new Error(`Unknown command: ${command}`);
    }
  } catch (err) {
    process.stderr.write(JSON.stringify({ error: err.message ?? String(err) }) + '\n');
    process.exit(1);
  }
})();
