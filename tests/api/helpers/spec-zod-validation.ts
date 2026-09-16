import { expect } from '@playwright/test';
import type { ApiTransport } from '@/clients/api-transport';
import { type z } from 'zod';
import {
  cronJobListSchema,
  csfConfigResponseSchema,
  csfRulesResponseSchema,
  eximConfigResponseSchema,
  ftpAccountListSchema,
  ipamSubnetListSchema,
  modSecurityConfigResponseSchema,
  mysqlDatabaseListSchema,
  phpVersionsListSchema,
  testConnectionSchema,
  unknownArrayDataEnvelopeSchema,
  unknownDataEnvelopeSchema,
} from '@/schemas';
import { domainListSchema } from '@/schemas/domain.schemas';
import { parseApiJson } from '@/schemas/parse-api-json';
import { systemInfoResponseSchema, systemMetricsResponseSchema } from '@/schemas/system.schemas';
import { userListProbeSchema } from '@/schemas/user.schemas';

type PathResolver = (username: string | undefined) => string;

interface SpecZodEntry {
  /** Relative API path (no leading slash). */
  path: string | PathResolver;
  schema: z.ZodType;
  /** When true, skip validation if username is required but missing. */
  optionalUsername?: boolean;
}

function projectPath(suffix: string): PathResolver {
  return (username) => {
    if (!username) {
      throw new Error(`Spec Zod validation requires setup username for path: ${suffix}`);
    }
    return `projects/${username}/${suffix}`;
  };
}

/**
 * Maps spec file basename (without `.spec.ts`) to a representative GET endpoint and schema.
 * Used by per-spec `test.beforeAll` hooks to ensure Zod coverage in every spec file.
 */
export const SPEC_ZOD_REGISTRY: Record<string, SpecZodEntry> = {
  'auth-guards': { path: 'test-connection', schema: testConnectionSchema },
  cleanup: { path: 'test-connection', schema: testConnectionSchema },
  'cron-crud': { path: projectPath('cron-jobs'), schema: cronJobListSchema, optionalUsername: true },
  'cron-validation': {
    path: projectPath('cron-jobs'),
    schema: cronJobListSchema,
    optionalUsername: true,
  },
  'csf-config': { path: 'csf/status', schema: csfConfigResponseSchema },
  'csf-rules': { path: 'csf/rules', schema: csfRulesResponseSchema },
  'domains-crud': { path: projectPath('domains'), schema: domainListSchema, optionalUsername: true },
  'domains-logs-fields': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'domains-logs-lifecycle': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'domains-logs-list': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'domains-ssl-addon': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'domains-validation': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'domains-alias': { path: projectPath('domains'), schema: domainListSchema, optionalUsername: true },
  'domains-global': { path: projectPath('domains'), schema: domainListSchema, optionalUsername: true },
  'domains-normalization-edge': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'domains-normalization-user': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'domains-ssl': { path: projectPath('domains'), schema: domainListSchema, optionalUsername: true },
  'domains-ssl-tls': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'exim-config-read': { path: 'system/exim-config', schema: eximConfigResponseSchema },
  'exim-config-smtp': { path: 'system/exim-config', schema: eximConfigResponseSchema },
  'exim-config-update': { path: 'system/exim-config', schema: eximConfigResponseSchema },
  'exim-security': { path: 'system/exim-config', schema: eximConfigResponseSchema },
  'files-archive': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'files-crud': { path: projectPath('domains'), schema: domainListSchema, optionalUsername: true },
  'files-http': { path: projectPath('domains'), schema: domainListSchema, optionalUsername: true },
  'files-security-advanced': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'files-security-traversal': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'files-upload-basic': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'files-upload-edge': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'ftp-crud': {
    path: projectPath('ftp-accounts'),
    schema: ftpAccountListSchema,
    optionalUsername: true,
  },
  'ftp-2-connection': {
    path: projectPath('ftp-accounts'),
    schema: ftpAccountListSchema,
    optionalUsername: true,
  },
  'ftp-3-crud': {
    path: projectPath('ftp-accounts'),
    schema: ftpAccountListSchema,
    optionalUsername: true,
  },
  'ftp-quota': {
    path: projectPath('ftp-accounts'),
    schema: ftpAccountListSchema,
    optionalUsername: true,
  },
  health: { path: 'test-connection', schema: testConnectionSchema },
  'htaccess-reload-create': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'htaccess-reload-headers': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'htaccess-reload-modify': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'htaccess-reload-redirect': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  idempotency: { path: 'system/info', schema: systemInfoResponseSchema },
  'ioncube-install': { path: 'system/info', schema: systemInfoResponseSchema },
  'ioncube-verify': { path: 'system/info', schema: systemInfoResponseSchema },
  'ipam-subnets-crud': { path: 'ip/subnets', schema: ipamSubnetListSchema },
  'ipam-subnets-validation': { path: 'ip/subnets', schema: ipamSubnetListSchema },
  'ipam-assignments-ipv4-assign': {
    path: 'ip/subnets',
    schema: ipamSubnetListSchema,
  },
  'ipam-assignments-ipv4-validation': {
    path: 'ip/subnets',
    schema: ipamSubnetListSchema,
  },
  'ipam-assignments-ipv6-assign': {
    path: 'ip/subnets',
    schema: ipamSubnetListSchema,
  },
  'ipam-assignments-ipv6-validation': {
    path: 'ip/subnets',
    schema: ipamSubnetListSchema,
  },
  'ipam-cleanup': { path: 'ip/subnets', schema: ipamSubnetListSchema },
  'isolation-cross-user': { path: 'system/info', schema: systemInfoResponseSchema },
  lighthouse: { path: 'system/info', schema: systemInfoResponseSchema },
  'lscache-directory': { path: 'system/info', schema: systemInfoResponseSchema },
  'lscache-headers-disabled': { path: 'system/info', schema: systemInfoResponseSchema },
  'lscache-headers-enabled': { path: 'system/info', schema: systemInfoResponseSchema },
  'lscache-plugin': { path: 'system/info', schema: systemInfoResponseSchema },
  'lscache-purge-api': { path: 'system/info', schema: systemInfoResponseSchema },
  'lscache-purge-http': { path: 'system/info', schema: systemInfoResponseSchema },
  'modsec-cleanup': { path: 'modsec/mode', schema: modSecurityConfigResponseSchema },
  'modsec-mode': { path: 'modsec/mode', schema: modSecurityConfigResponseSchema },
  'modsec-rulesets': {
    path: 'modsec/mode',
    schema: modSecurityConfigResponseSchema,
  },
  'modsec-audit': {
    path: 'modsec/mode',
    schema: modSecurityConfigResponseSchema,
  },
  'modsec-waf-blocking-ajax': {
    path: 'modsec/mode',
    schema: modSecurityConfigResponseSchema,
  },
  'modsec-waf-blocking-urls': {
    path: 'modsec/mode',
    schema: modSecurityConfigResponseSchema,
  },
  'modsec-config-apply': {
    path: 'modsec/mode',
    schema: modSecurityConfigResponseSchema,
  },
  'mysql-databases': {
    path: projectPath('mysql/databases'),
    schema: mysqlDatabaseListSchema,
    optionalUsername: true,
  },
  'mysql-privileges': {
    path: projectPath('mysql/databases'),
    schema: mysqlDatabaseListSchema,
    optionalUsername: true,
  },
  'mysql-sso': {
    path: projectPath('mysql/databases'),
    schema: mysqlDatabaseListSchema,
    optionalUsername: true,
  },
  'mysql-users': {
    path: projectPath('mysql/databases'),
    schema: mysqlDatabaseListSchema,
    optionalUsername: true,
  },
  'mysql-sso-security': {
    path: projectPath('mysql/databases'),
    schema: mysqlDatabaseListSchema,
    optionalUsername: true,
  },
  'mysql-validation-crud': {
    path: projectPath('mysql/databases'),
    schema: mysqlDatabaseListSchema,
    optionalUsername: true,
  },
  'mysql-validation-errors': {
    path: projectPath('mysql/databases'),
    schema: mysqlDatabaseListSchema,
    optionalUsername: true,
  },
  'mysql-validation-errors-security': {
    path: projectPath('mysql/databases'),
    schema: mysqlDatabaseListSchema,
    optionalUsername: true,
  },
  'mysql-validation-sso-flow': {
    path: projectPath('mysql/databases'),
    schema: mysqlDatabaseListSchema,
    optionalUsername: true,
  },
  'mysql-validation-sso-session': {
    path: projectPath('mysql/databases'),
    schema: mysqlDatabaseListSchema,
    optionalUsername: true,
  },
  'mysql-validation-sso-tokens': {
    path: projectPath('mysql/databases'),
    schema: mysqlDatabaseListSchema,
    optionalUsername: true,
  },
  owasp: { path: 'system/info', schema: systemInfoResponseSchema },
  'permalinks-rewrite': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'permalinks-structures': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'permalinks-http-custom': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'permalinks-http-numeric-plain': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'permalinks-http-structured-day': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'permalinks-http-structured-month': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'permalinks-http-structured-postname': {
    path: projectPath('domains'),
    schema: domainListSchema,
    optionalUsername: true,
  },
  'php-ini': { path: 'php/available-versions', schema: phpVersionsListSchema },
  'php-validation-input': { path: 'php/available-versions', schema: phpVersionsListSchema },
  'php-validation-version-switch': {
    path: 'php/available-versions',
    schema: phpVersionsListSchema,
  },
  'php-versions': { path: 'php/available-versions', schema: phpVersionsListSchema },
  'php-workers': { path: 'php/available-versions', schema: phpVersionsListSchema },
  setup: { path: 'system/info', schema: systemInfoResponseSchema },
  'sftp-crud': {
    path: projectPath('sftp-accounts'),
    schema: ftpAccountListSchema,
    optionalUsername: true,
  },
  'sftp-public-key': {
    path: projectPath('sftp-accounts'),
    schema: ftpAccountListSchema,
    optionalUsername: true,
  },
  'system-info': { path: 'system/info', schema: systemInfoResponseSchema },
  'system-metrics': { path: 'metrics/current', schema: systemMetricsResponseSchema },
  'network-config': { path: 'system/info', schema: systemInfoResponseSchema },
  update: { path: 'system/info', schema: systemInfoResponseSchema },
  'uid-ownership-api': { path: 'system/info', schema: systemInfoResponseSchema },
  'uid-ownership-cron': { path: 'system/info', schema: systemInfoResponseSchema },
  'uid-ownership-php': { path: 'system/info', schema: systemInfoResponseSchema },
  'uid-ownership-wpcli': { path: 'system/info', schema: systemInfoResponseSchema },
  'uid-ownership-transfer': { path: 'system/info', schema: systemInfoResponseSchema },
  'users-create': { path: 'projects', schema: userListProbeSchema },
  'users-lifecycle': { path: 'projects', schema: userListProbeSchema },
  'users-all': { path: 'projects', schema: userListProbeSchema },
  'users-domain-change': { path: 'projects', schema: userListProbeSchema },
  'users-rebuild': { path: 'projects', schema: userListProbeSchema },
  'users-suspend-effects': { path: 'projects', schema: userListProbeSchema },
  'users-usage-domains': { path: 'projects', schema: userListProbeSchema },
  'users-usage-ftp': { path: 'projects', schema: userListProbeSchema },
  'users-usage-mysql': { path: 'projects', schema: userListProbeSchema },
  'users-usage-sftp': { path: 'projects', schema: userListProbeSchema },
  'users-usage-storage': { path: 'projects', schema: userListProbeSchema },
  'users-validation-create': { path: 'projects', schema: userListProbeSchema },
  'users-validation-security': { path: 'projects', schema: userListProbeSchema },
  'webserver-config': { path: 'system/info', schema: systemInfoResponseSchema },
  'webserver-info': { path: 'system/info', schema: systemInfoResponseSchema },
  'webserver-panel': { path: 'system/info', schema: systemInfoResponseSchema },
  'webserver-validation': { path: 'system/info', schema: systemInfoResponseSchema },
  'webserver-change': { path: 'system/info', schema: systemInfoResponseSchema },
  'webserver-http-404': { path: 'system/info', schema: systemInfoResponseSchema },
  'webserver-http-runtime': { path: 'system/info', schema: systemInfoResponseSchema },
  'webserver-http-www': { path: 'system/info', schema: systemInfoResponseSchema },
  'wp-cli-core': { path: 'system/info', schema: systemInfoResponseSchema },
  'wp-cli-extended': { path: 'system/info', schema: systemInfoResponseSchema },
  'wp-cli-users': { path: 'system/info', schema: systemInfoResponseSchema },
  'wp-cli-plugins': { path: 'system/info', schema: systemInfoResponseSchema },
  'wp-cli-themes': { path: 'system/info', schema: systemInfoResponseSchema },
  'wp-cli-posts': { path: 'system/info', schema: systemInfoResponseSchema },
  'wp-cli-installation': { path: 'system/info', schema: systemInfoResponseSchema },
  'wp-cli-quota': { path: 'system/info', schema: systemInfoResponseSchema },
  'wp-cli-soap': { path: 'system/info', schema: systemInfoResponseSchema },
  'wp-cli-security-local-ip': { path: 'system/info', schema: systemInfoResponseSchema },
  'wp-cli-security-local-ip-http': { path: 'system/info', schema: systemInfoResponseSchema },
  'wp-cli-security-wordfence': { path: 'system/info', schema: systemInfoResponseSchema },
  'wp-cli-validation': { path: 'system/info', schema: systemInfoResponseSchema },
};

const DEFAULT_ENTRY: SpecZodEntry = {
  path: 'system/info',
  schema: systemInfoResponseSchema,
};

const METRICS_SPECS = new Set<string>();

/** When `VALIDATE_API_SHAPE=false`, skip all Zod response probes. */
export function isSpecZodValidationEnabled(): boolean {
  return process.env.VALIDATE_API_SHAPE?.trim().toLowerCase() !== 'false';
}

/** Lifecycle features and env opt-out — no representative GET probe. */
export function shouldSkipSpecZodValidation(featureUri: string): boolean {
  if (!isSpecZodValidationEnabled()) {
    return true;
  }
  const normalized = featureUri.replace(/\\/g, '/');
  return normalized.includes('_lifecycle/');
}

export function resolveSpecZodEntry(specBasename: string): SpecZodEntry {
  return SPEC_ZOD_REGISTRY[specBasename] ?? DEFAULT_ENTRY;
}

/** User-scoped probes need `setupData.username`; global probes do not. */
export function canRunSpecZodProbe(entry: SpecZodEntry, username?: string): boolean {
  if (typeof entry.path === 'function' && !username) {
    return false;
  }
  return true;
}

/**
 * Validates a representative API response for the given feature basename
 * (filename without `.feature`, e.g. `wp-cli-core`).
 */
export async function validateSpecApiShape(
  apiPlugin: ApiTransport,
  specBasename: string,
  username?: string
): Promise<void> {
  const entry = resolveSpecZodEntry(specBasename);

  if (!canRunSpecZodProbe(entry, username)) {
    return;
  }

  const path = typeof entry.path === 'function' ? entry.path(username) : entry.path;

  const response = await apiPlugin.get(path, { failOnStatusCode: false });
  expect(
    response.ok(),
    `Spec Zod probe failed for ${specBasename}: GET ${path} → ${response.status()}`
  ).toBeTruthy();

  await parseApiJson(response, entry.schema);

  if (METRICS_SPECS.has(specBasename)) {
    const metrics = await apiPlugin.get('metrics/current', { failOnStatusCode: false });
    if (metrics.ok()) {
      await parseApiJson(metrics, systemMetricsResponseSchema);
    }
  }
}

/** Basename from a feature file path, e.g. `features/users/sftp/sftp.feature` → `sftp`. */
export function specBasenameFromPath(filePath: string): string {
  const normalized = filePath.replace(/\\/g, '/');
  const name = normalized.split('/').pop() ?? normalized;
  return name.replace(/\.feature$/, '').replace(/\.spec\.ts$/, '');
}

export { unknownArrayDataEnvelopeSchema, unknownDataEnvelopeSchema };
