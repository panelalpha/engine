import fs from 'node:fs';
import path from 'node:path';
import { engineTemplatesRoot } from '@/helpers/real-ip-helpers';

/**
 * The engine's vhost templates, read straight from the repository this suite
 * lives in.
 *
 * Several behaviours — the Cloudflare real-IP block, the ACME challenge
 * exception, the custom error pages — cannot be observed over HTTP on every
 * stack, so the template that produces them is asserted directly. That catches
 * a regression in the shipped configuration even where the running webserver
 * hides it.
 */
export const TEMPLATES_ROOT = engineTemplatesRoot;

export function templatesAvailable(): boolean {
  return fs.existsSync(TEMPLATES_ROOT);
}

export function readTemplate(name: string): string {
  const file = path.join(TEMPLATES_ROOT, name);
  if (!fs.existsSync(file)) {
    throw new Error(`Template ${name} is not in ${TEMPLATES_ROOT}`);
  }
  return fs.readFileSync(file, 'utf8');
}

export function templateExists(name: string): boolean {
  return fs.existsSync(path.join(TEMPLATES_ROOT, name));
}
