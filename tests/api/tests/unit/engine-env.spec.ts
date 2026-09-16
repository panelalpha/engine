import { expect, test } from '@/fixtures/test-options';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {
  envFileHasCredentials,
  isLocalEngine,
  parseEnvCredentials,
  parseSanctumToken,
} from '@/scripts/lib/engine-env';

test.describe('parseSanctumToken', () => {
  test('picks the id|plaintext line out of artisan noise', () => {
    expect(parseSanctumToken('3|abcdefghijklmnopqrstuvwxyz012345')).toBe(
      '3|abcdefghijklmnopqrstuvwxyz012345'
    );
    expect(
      parseSanctumToken('Api token created.\n4|plain-token-here\n=============================')
    ).toBe('4|plain-token-here');
  });

  test('ignores lines that are not a Sanctum token', () => {
    expect(parseSanctumToken('time="…" level=warning msg="…"')).toBeUndefined();
    expect(parseSanctumToken('')).toBeUndefined();
  });
});

test.describe('parseEnvCredentials', () => {
  test('reads both values', () => {
    expect(
      parseEnvCredentials('API_BASE_URL=https://engine.example.com:2011/api/\nAPI_TOKEN=1|secret\n')
    ).toEqual({
      apiBaseUrl: 'https://engine.example.com:2011/api/',
      token: '1|secret',
    });
  });

  test('treats an empty API_TOKEN as missing — the copied .env.example case', () => {
    const parsed = parseEnvCredentials(
      'API_BASE_URL=https://engine.example.com:2011/api/\nAPI_TOKEN=\n'
    );
    expect(parsed.apiBaseUrl).toBe('https://engine.example.com:2011/api/');
    expect(parsed.token).toBeUndefined();
  });
});

test.describe('envFileHasCredentials', () => {
  test('is false when the file is missing or the token is blank', () => {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'engine-env-'));
    const missing = path.join(dir, 'nope.env');
    const blank = path.join(dir, 'blank.env');
    fs.writeFileSync(blank, 'API_BASE_URL=https://engine.example.com:2011/api/\nAPI_TOKEN=\n');

    expect(envFileHasCredentials(missing)).toBe(false);
    expect(envFileHasCredentials(blank)).toBe(false);

    fs.rmSync(dir, { recursive: true, force: true });
  });

  test('is true when both keys have values', () => {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'engine-env-'));
    const file = path.join(dir, '.env');
    fs.writeFileSync(
      file,
      'API_BASE_URL=https://engine.example.com:2011/api/\nAPI_TOKEN=1|secret\n'
    );

    expect(envFileHasCredentials(file)).toBe(true);

    fs.rmSync(dir, { recursive: true, force: true });
  });
});

test.describe('isLocalEngine', () => {
  test('is the compose file on this machine, not a hardcoded true', () => {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'engine-env-'));
    const compose = path.join(dir, 'docker-compose.yml');
    expect(isLocalEngine(compose)).toBe(false);
    fs.writeFileSync(compose, 'services: {}\n');
    expect(isLocalEngine(compose)).toBe(true);
    fs.rmSync(dir, { recursive: true, force: true });
  });
});
