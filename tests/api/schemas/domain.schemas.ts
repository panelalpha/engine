import { z } from 'zod';

export const sslCertificateSchema = z.object({
  certificate: z.string().optional(),
  cabundle: z.string().optional(),
  common_name: z.string(),
  issuer_name: z.string(),
  issuer_common_name: z.string().optional(),
  not_before: z.union([z.string(), z.number()]).optional(),
  not_after: z.union([z.string(), z.number()]).optional(),
  domains: z.array(z.string()).optional(),
});

export const sslCertificateListSchema = z.object({
  data: z.array(sslCertificateSchema),
});

export const logFileSchema = z.object({
  file: z.string(),
  path: z.string().optional(),
  size: z.number().optional(),
  modified: z.union([z.string(), z.number()]).optional(),
  mtime: z.union([z.string(), z.number()]).optional(),
});

export const logFileListSchema = z.object({
  data: z.array(logFileSchema),
});

export const domainSchema = z.object({
  domain: z.string(),
  type: z.enum(['main', 'addon', 'alias', 'sub']),
  details: z
    .object({
      document_root: z.string().optional(),
      aliases: z.array(z.string()).optional(),
      force_https_redirect: z.boolean().optional(),
      redirect_enabled: z.boolean().optional(),
      redirect_url: z.string().nullable().optional(),
    })
    .optional(),
});

export const domainListSchema = z.object({
  data: z.array(domainSchema),
});

export const domainResponseSchema = z.object({
  data: domainSchema,
});
