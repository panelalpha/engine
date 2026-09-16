import { z } from 'zod';

const userLimitsSchema = z
  .object({
    home_dir: z.string().optional(),
    mysql_prefix: z.string().optional(),
    disk_space_limit: z.union([z.number(), z.string()]).optional(),
    UID: z.union([z.number(), z.string()]).optional(),
    GID: z.union([z.number(), z.string()]).optional(),
  })
  .passthrough();

export const userSchema = z
  .object({
    id: z.number(),
    username: z.string(),
    domain: z.string(),
    name: z.union([z.string(), z.null()]).optional(),
    email: z.union([z.string().email(), z.literal(''), z.null(), z.string()]),
    email_verified_at: z.union([z.string(), z.null()]).optional(),
    status: z.string(),
    created_at: z.string().optional(),
    updated_at: z.string().optional(),
    details: userLimitsSchema.optional(),
    config: userLimitsSchema.optional(),
  })
  .passthrough();

export const userListSchema = z.object({
  data: z.array(userSchema),
});

/** Loose probe for `GET /projects` in beforeAll (Engine field variants). */
export const userListProbeSchema = z.object({
  data: z.array(z.record(z.string(), z.unknown())),
});

export const userResponseSchema = z.object({
  data: userSchema,
});

export const createUserRequestSchema = z.object({
  username: z.string().min(1),
  domain: z.string().min(1),
  name: z.string().optional(),
  email: z.string().email().optional(),
});
