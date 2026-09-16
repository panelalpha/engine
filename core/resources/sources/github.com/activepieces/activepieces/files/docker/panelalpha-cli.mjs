#!/usr/bin/env node
/**
 * PanelAlpha CLI helper for ActivePieces.
 * Runs inside the `app` container — all dependencies are already present.
 *
 * Auth bootstrap (no stored credentials beyond what's already in .env):
 *   1. Read AP_JWT_SECRET from the container environment (set by env_file: .env)
 *   2. Query PostgreSQL (AP_POSTGRES_* env vars) for the platform admin's tokenVersion
 *   3. Mint a short-lived HS256 JWT and call the official /v1/ REST API
 *
 * Password reset goes directly to PostgreSQL because no admin REST endpoint exists.
 *
 * users:add uses the invitation flow so no SMTP is required:
 *   create PENDING invitation → mint invitation JWT locally → accept → sign-up
 */

import crypto from 'node:crypto'
import fs from 'node:fs'
import { createRequire } from 'node:module'

// Load dependencies from the container's own node_modules
const require = createRequire('/usr/src/app/package.json')
// Module lookup — the image's layout is not ours to rely on. Activepieces
// moved to bun, which keeps packages in .bun/<name>@<version>/node_modules/
// and hoists only what the app imports directly, so a plain require('bcrypt')
// stopped resolving.
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

const jwt     = loadModule('jsonwebtoken')
const bcrypt  = loadModule('bcrypt')
const { Client } = loadModule('pg')

// ---------------------------------------------------------------------------
// PostgreSQL helper
// ---------------------------------------------------------------------------

function pgConfig() {
    return {
        host:     process.env.AP_POSTGRES_HOST     ?? 'postgres',
        port:     Number(process.env.AP_POSTGRES_PORT ?? 5432),
        database: process.env.AP_POSTGRES_DATABASE ?? 'activepieces',
        user:     process.env.AP_POSTGRES_USERNAME ?? 'postgres',
        password: process.env.AP_POSTGRES_PASSWORD ?? '',
    }
}

async function dbQuery(sql, params = []) {
    const client = new Client(pgConfig())
    await client.connect()
    try {
        const { rows } = await client.query(sql, params)
        return rows
    } finally {
        await client.end()
    }
}

// ---------------------------------------------------------------------------
// Auth bootstrap
// ---------------------------------------------------------------------------

function getJwtSecret() {
    const secret = process.env.AP_JWT_SECRET
    if (!secret) throw new Error('AP_JWT_SECRET is not set in the container environment')
    return secret
}

async function getAdminUser() {
    // Find the platform owner — the oldest platform's ownerId
    const rows = await dbQuery(`
        SELECT u.id, u."identityId", u."platformId", ui."tokenVersion"
        FROM   "user"          u
        JOIN   "user_identity" ui ON ui.id = u."identityId"
        JOIN   "platform"      p  ON p."ownerId" = u.id
        ORDER  BY p.created ASC
        LIMIT  1
    `)
    if (!rows.length) throw new Error('No platform admin found — has the app been installed yet?')
    return rows[0]
}

async function mintAdminJwt() {
    const admin  = await getAdminUser()
    const secret = getJwtSecret()
    const payload = {
        id:           admin.id,
        type:         'USER',
        platform:     { id: admin.platformId },
        tokenVersion: admin.tokenVersion,
    }
    return jwt.sign(payload, secret, {
        algorithm:  'HS256',
        keyid:      '1',
        issuer:     'activepieces',
        expiresIn:  '15m',
    })
}

// ---------------------------------------------------------------------------
// API helpers
// ---------------------------------------------------------------------------

// Discover the internal API base URL once
let _apiBase = 'http://localhost:80/api/v1';
async function apiBase() {
    if (_apiBase) return _apiBase
    const candidates = [
        'http://localhost:3000/v1',
        'http://localhost:80/api/v1',
        'http://localhost:80/v1',
    ]
    for (const url of candidates) {
        try {
            const r = await fetch(`${url}/flags`, { signal: AbortSignal.timeout(3000) })
            if (r.ok || r.status === 401 || r.status === 403) { _apiBase = url; return url }
        } catch {}
    }
    throw new Error('Cannot reach ActivePieces API on any known port')
}

async function api(method, path, token, body) {
    const base = await apiBase()
    const headers = {}
    if (body !== undefined) headers['Content-Type'] = 'application/json'
    if (token) headers['Authorization'] = `Bearer ${token}`
    const res = await fetch(`${base}${path}`, {
        method,
        headers,
        body: body !== undefined ? JSON.stringify(body) : undefined,
    })
    if (res.status === 204) return {}
    const text = await res.text()
    let data
    try { data = JSON.parse(text) } catch { data = text }
    if (!res.ok) throw new Error(`API ${method} ${path} → ${res.status}: ${text}`)
    return data
}

// ---------------------------------------------------------------------------
// Commands
// ---------------------------------------------------------------------------

const [,, command, ...args] = process.argv

if (command === 'info') {
    console.log(JSON.stringify(['install', 'roles:list', 'users:list', 'users:add', 'users:delete', 'users:reset-password']))
    process.exit(0)
}

if (command === 'roles:list') {
    console.log(JSON.stringify(['ADMIN', 'MEMBER']))
    process.exit(0)
}

;(async () => {
    try {
        switch (command) {

            case 'install': { // <url> <title> <adminUser> <adminEmail> <adminPassword>
                const [url, title, adminUser, adminEmail, adminPassword] = args
                if (!url || !adminUser || !adminEmail || !adminPassword) {
                    throw new Error('Usage: install <url> <title> <adminUser> <adminEmail> <adminPassword>')
                }

                // Wait for the app to be ready
                const base = await (async () => {
                    for (let i = 0; i < 90; i++) {
                        try {
                            const r = await fetch(`${await apiBase()}/flags`, { signal: AbortSignal.timeout(3000) })
                            if (r.ok) return await apiBase()
                        } catch {}
                        await new Promise(r => setTimeout(r, 2000))
                        if (i === 89) throw new Error('Timed out waiting for ActivePieces to start')
                    }
                })()

                // Check if a platform already exists
                const existingPlatforms = await dbQuery('SELECT id FROM "platform" LIMIT 1')
                if (existingPlatforms.length > 0) {
                    // Platform already set up — return existing admin id
                    const admin = await getAdminUser()
                    console.log(JSON.stringify({ id: admin.id }))
                    break
                }

                const [firstName, ...rest] = adminUser.split(' ')
                const lastName = rest.join(' ') || '-'

                // Sign up the first user — COMMUNITY edition auto-verifies and returns an onboarding token
                const signUpRes = await api('POST', '/authentication/sign-up', null, {
                    email:        adminEmail,
                    password:     adminPassword,
                    firstName,
                    lastName,
                    trackEvents:  false,
                    newsLetter:   false,
                    provider:     'EMAIL',
                })

                // signUpRes.token is an ONBOARDING token; use it to create the platform
                const onboardingToken = signUpRes.token
                if (!onboardingToken) throw new Error(`Sign-up failed: ${JSON.stringify(signUpRes)}`)

                // Community editions from 0.5x on create the platform during
                // sign-up and answer this with 403 AUTHORIZATION ("not in non
                // cloud editions"); older ones need the call. Either way the
                // platform exists afterwards.
                let platformRes = {}
                try {
                    platformRes = await api('POST', '/platforms', onboardingToken, {
                        name: title || adminUser,
                    })
                } catch (e) {
                    if (!/→ 40[13]:/.test(String(e.message))) throw e
                }

                let userId = platformRes.id ?? signUpRes.id
                if (!userId) userId = (await getAdminUser().catch(() => null))?.id
                if (!userId) throw new Error(`Platform creation failed: ${JSON.stringify(platformRes)}`)

                // Set the frontend URL so the instance knows its public address
                try {
                    const adminJwt = await mintAdminJwt()
                    const platform = await dbQuery('SELECT id FROM "platform" LIMIT 1')
                    if (platform.length > 0) {
                        await api('POST', `/platforms/${platform[0].id}`, adminJwt, {
                            frontendUrl: url,
                        })
                    }
                } catch {
                    // Non-fatal — the app still works without this
                }

                console.log(JSON.stringify({ id: userId }))
                break
            }

            case 'users:list': {
                const token = await mintAdminJwt()
                const data  = await api('GET', '/users?limit=500', token)
                const users = (data.data ?? []).map(u => ({
                    id:       u.id,
                    username: u.firstName + (u.lastName ? ' ' + u.lastName : ''),
                    email:    u.email ?? '',
                    role:     u.platformRole ?? 'MEMBER',
                }))
                console.log(JSON.stringify(users))
                break
            }

            case 'users:add': { // <login> <email> <password> <role>
                const [login, email, password, role] = args
                if (!email || !password || !role) {
                    throw new Error('Usage: users:add <login> <email> <password> <role>')
                }
                const validRoles = ['ADMIN', 'MEMBER']
                if (!validRoles.includes(role)) {
                    throw new Error(`Invalid role '${role}'. Valid roles: ${validRoles.join(', ')}`)
                }

                const adminToken = await mintAdminJwt()

                // Step 1: Create a PENDING platform invitation
                const inviteRes = await api('POST', '/user-invitations', adminToken, {
                    type:         'PLATFORM',
                    email,
                    platformRole: role,
                })
                const invitationId = inviteRes.id
                if (!invitationId) throw new Error(`Invitation creation failed: ${JSON.stringify(inviteRes)}`)

                // Step 2: Mint the invitation JWT locally — avoids SMTP dependency
                const invitationToken = jwt.sign(
                    { id: invitationId },
                    getJwtSecret(),
                    { algorithm: 'HS256', issuer: 'activepieces', audience: 'USER_INVITATION', expiresIn: 3600 }
                )

                // Step 3: Accept the invitation
                await api('POST', '/user-invitations/accept', null, { invitationToken })

                // Step 4: Sign up with the invited email
                const [firstName, ...nameParts] = (login || email.split('@')[0]).split(' ')
                const lastName = nameParts.join(' ') || '-'
                const signUpRes = await api('POST', '/authentication/sign-up', null, {
                    email,
                    password,
                    firstName,
                    lastName,
                    trackEvents:  false,
                    newsLetter:   false,
                    provider:     'EMAIL',
                })

                const newUserId = signUpRes.id
                if (!newUserId) throw new Error(`Sign-up failed: ${JSON.stringify(signUpRes)}`)

                console.log(JSON.stringify({ id: newUserId }))
                break
            }

            case 'users:delete': { // <userId>
                const [userId] = args
                if (!userId) throw new Error('Usage: users:delete <userId>')

                const userRows = await dbQuery(
                    'SELECT "identityId" FROM "user" WHERE id = $1',
                    [userId]
                )
                if (!userRows.length) throw new Error(`User not found: ${userId}`)
                const identityId = userRows[0].identityId

                // CE bug: deletePersonalProjectForUser only soft-deletes (sets deletedAt) but
                // leaves ownerId intact, so the subsequent hard-delete of the user row hits the
                // FK constraint "fk_project_owner_id".  Pre-clean owned projects in the DB first.
                const ownedProjects = await dbQuery(
                    'SELECT id FROM project WHERE "ownerId" = $1',
                    [userId]
                )
                if (ownedProjects.length > 0) {
                    const ids = ownedProjects.map(r => r.id)
                    const ph  = ids.map((_, i) => `$${i + 1}`).join(', ')
                    // Remove memberships that reference these projects
                    await dbQuery(`DELETE FROM project_member WHERE "projectId" IN (${ph})`, ids)
                    // Hard-delete the projects themselves
                    await dbQuery(`DELETE FROM project WHERE id IN (${ph})`, ids)
                }
                // Also remove this user from any other projects they are a member of
                await dbQuery('DELETE FROM project_member WHERE "userId" = $1', [userId])

                // Now the FK is cleared — call the API to finish the rest of the cleanup
                const token = await mintAdminJwt()
                await api('DELETE', `/users/${userId}`, token)
                console.log(JSON.stringify({ success: true }))
                break
            }

            case 'users:reset-password': { // <userId> <newPassword>
                const [userId, newPassword] = args
                if (!userId || !newPassword) throw new Error('Usage: users:reset-password <userId> <newPassword>')

                // Look up the identity ID for this user
                const rows = await dbQuery('SELECT "identityId" FROM "user" WHERE id = $1', [userId])
                if (!rows.length) throw new Error(`User not found: ${userId}`)
                const identityId = rows[0].identityId

                // Hash with bcrypt (10 rounds — same as the app)
                const hashed       = await bcrypt.hash(newPassword, 10)
                const tokenVersion = crypto.randomBytes(16).toString('hex')

                await dbQuery(
                    `UPDATE user_identity SET password = $1, "tokenVersion" = $2, updated = NOW() WHERE id = $3`,
                    [hashed, tokenVersion, identityId]
                )

                console.log(JSON.stringify({ success: true }))
                break
            }

            default:
                throw new Error(`Unknown command: ${command}`)
        }
    } catch (err) {
        process.stderr.write(JSON.stringify({ error: err.message }) + '\n')
        process.exit(1)
    }
})()
