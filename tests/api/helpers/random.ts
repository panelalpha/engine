import { randomUUID } from 'crypto';
import { faker } from '@faker-js/faker';

export function rand(prefix = ''): string {
  const randomPart = randomUUID().replace(/-/g, '').slice(0, 8);
  return `${prefix}${randomPart}`;
}

/**
 * Playwright-minted project names. Engine usernames are 3–15 `[a-z][a-z0-9]*`.
 * Stale cleanup keys off this prefix so a hand-created `shop.panelalpha.online`
 * is left alone.
 */
export const TEST_USERNAME_PREFIX = 'pw';
const TEST_USERNAME_HEX = 8;

export function randomUsername(maxLength = 14): string {
  const hex = randomUUID().replace(/-/g, '').slice(0, TEST_USERNAME_HEX);
  const username = `${TEST_USERNAME_PREFIX}${hex}`;
  return username.length > maxLength ? username.slice(0, maxLength) : username;
}

/** True for names `randomUsername()` (and specs that suffix them) produce. */
export function isTestUsername(username: string): boolean {
  const name = username.trim().toLowerCase();
  return new RegExp(`^${TEST_USERNAME_PREFIX}[0-9a-f]{${TEST_USERNAME_HEX}}`).test(name);
}

/**
 * A random subdomain of `baseDomain`.
 *
 * The base is required, and callers pass `settings.requireDomain()`: extra
 * names the test itself requests have to resolve. That parent is the engine's
 * `{dashed-ip}.panelalpha.direct` zone (or cert_domain / sites_base_domain).
 */
export function randomDomain(baseDomain: string): string {
  return `${faker.string.alphanumeric(8).toLowerCase()}.${baseDomain}`;
}

export function randomEmail(): string {
  return ensureTestEmail(faker.internet.email());
}

/** Appends `.test` to the domain so generated emails never use real TLDs. */
export function ensureTestEmail(email: string): string {
  const atIndex = email.lastIndexOf('@');
  const localPart =
    atIndex > 0
      ? email.slice(0, atIndex)
      : faker.internet.username().replace(/[^a-zA-Z0-9._+-]/g, '');
  const domainPart = atIndex >= 0 ? email.slice(atIndex + 1) : 'mail.test';

  const normalizedDomain = domainPart.trim().toLowerCase();
  if (!normalizedDomain) {
    return `${localPart}@mail.test`;
  }

  if (normalizedDomain.endsWith('.test')) {
    return `${localPart}@${normalizedDomain}`;
  }

  return `${localPart}@${normalizedDomain}.test`;
}

export function randomPassword(length = 16): string {
  return faker.internet.password({
    length,
    memorable: false,
    pattern: /[A-Za-z0-9!@#$%^&*]/,
  });
}

export function randomFileName(extension = 'txt'): string {
  const timestamp = Date.now();
  const prefix = faker.string.alphanumeric(6).toLowerCase();
  return `${prefix}_${timestamp}.${extension}`;
}

export function randomFileContent(type: 'text' | 'json' | 'html' = 'text'): string {
  switch (type) {
    case 'json':
      return JSON.stringify(
        {
          test: true,
          timestamp: Date.now(),
          content: faker.lorem.sentence(),
        },
        null,
        2
      );
    case 'html':
      return `<html><body><h1>Test File</h1><p>${faker.lorem.paragraph()}</p></body></html>`;
    default:
      return `Test file content created at ${new Date().toISOString()}\n${faker.lorem.sentences(2)}`;
  }
}

export function randomCompanyName(): string {
  return faker.company.name();
}

export function randomIpv4(): string {
  return faker.internet.ipv4();
}

export function pickRandom<T>(array: T[]): T {
  return faker.helpers.arrayElement(array);
}

export function uniqueId(prefix = ''): string {
  const timestamp = Date.now();
  const random = faker.string.alphanumeric(6).toLowerCase();
  return `${prefix}${timestamp}_${random}`;
}

export function randomSubdomain(): string {
  return faker.word
    .noun({ length: { min: 5, max: 10 } })
    .toLowerCase()
    .replace(/[^a-z]/g, '');
}

/** Strips non-alphanumerics and enforces a leading letter for Engine username rules. */
export function usernameFromDomain(domain: string, maxLength = 10): string {
  const alphanumeric = domain.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
  const prefixed = /^[a-z]/.test(alphanumeric) ? alphanumeric : `u${alphanumeric}`;
  return prefixed.substring(0, maxLength);
}
