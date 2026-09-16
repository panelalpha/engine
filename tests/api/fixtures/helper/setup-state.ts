import fs from 'node:fs';
import path from 'node:path';
import type { SetupTestData } from '@/types/user.types';

/**
 * State handed from the `setup` project to every other project.
 *
 * Replaces the previous `playwright-relay` cache. The mechanism is the same one
 * Playwright uses for `storageState`: the setup project writes a JSON file, the
 * dependent projects read it. It is a plain file you can `cat` when a run
 * misbehaves, and it survives `--no-deps` reruns of a single spec.
 */
export interface SetupState {
  /** Shared test user provisioned once per suite run, with WordPress installed. */
  user: SetupTestData;
  /** Webserver slug active when the user was provisioned (nginx, litespeed, …). */
  webserver: string;
  /** Epoch millis — used only for operator diagnostics. */
  createdAt: number;
}

export interface SetupDindState {
  username: string;
  domain: string;
  url: string;
  git_repo: string;
  createdAt: number;
}

const STATE_DIR = path.resolve(process.cwd(), '.playwright/state');

/** One state file per TEST_ENV so webserver profiles never read each other's user. */
export function setupStatePath(testEnv = process.env.TEST_ENV?.trim()): string {
  const suffix = testEnv ? `-${testEnv}` : '';
  return path.join(STATE_DIR, `setup${suffix}.json`);
}

export function writeSetupState(state: SetupState): void {
  fs.mkdirSync(STATE_DIR, { recursive: true });
  fs.writeFileSync(setupStatePath(), JSON.stringify(state, null, 2), 'utf-8');
}

/** Returns the stored state, or `undefined` when setup has not run (or wrote garbage). */
export function readSetupState(): SetupState | undefined {
  const file = setupStatePath();
  if (!fs.existsSync(file)) {
    return undefined;
  }

  try {
    const parsed = JSON.parse(fs.readFileSync(file, 'utf-8')) as SetupState;
    return parsed.user?.username ? parsed : undefined;
  } catch {
    return undefined;
  }
}

/** Drops the state file so the next `setup` run provisions a fresh user. */
export function clearSetupState(): void {
  fs.rmSync(setupStatePath(), { force: true });
}

export function setupDindStatePath(testEnv = process.env.TEST_ENV?.trim()): string {
  const suffix = testEnv ? `-${testEnv}` : '';
  return path.join(STATE_DIR, `setup-dind${suffix}.json`);
}

export function writeSetupDindState(state: SetupDindState): void {
  fs.mkdirSync(STATE_DIR, { recursive: true });
  fs.writeFileSync(setupDindStatePath(), JSON.stringify(state, null, 2), 'utf-8');
}

export function readSetupDindState(): SetupDindState | undefined {
  const file = setupDindStatePath();
  if (!fs.existsSync(file)) {
    return undefined;
  }

  try {
    const parsed = JSON.parse(fs.readFileSync(file, 'utf-8')) as SetupDindState;
    return parsed.username ? parsed : undefined;
  } catch {
    return undefined;
  }
}

export function clearSetupDindState(): void {
  fs.rmSync(setupDindStatePath(), { force: true });
}
