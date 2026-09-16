import { expect, test } from '@/fixtures/test-options';
import {
  formatHttpHost,
  guessReportHost,
  hostnameFromUrl,
  isUnusableReportHost,
  reportListenUrl,
  rewriteLocalReportUrls,
} from '@/scripts/lib/report-host';

test.describe('report host guessing', () => {
  test('skips loopback and bind-all names', () => {
    expect(isUnusableReportHost('localhost')).toBe(true);
    expect(isUnusableReportHost('127.0.0.1')).toBe(true);
    expect(isUnusableReportHost('127.0.1.1')).toBe(true);
    expect(isUnusableReportHost('0.0.0.0')).toBe(true);
    expect(isUnusableReportHost('::1')).toBe(true);
    expect(isUnusableReportHost('panel-damian.mg-test2.com')).toBe(false);
    expect(isUnusableReportHost('95.217.155.125')).toBe(false);
  });

  test('REPORT_HOST wins over a loopback API_BASE_URL', () => {
    expect(
      guessReportHost([
        'panel-damian.mg-test2.com',
        hostnameFromUrl('https://127.0.0.1:2011/api/'),
        '127.0.0.1',
      ])
    ).toBe('panel-damian.mg-test2.com');
  });

  test('falls through a loopback API host to hostname -f', () => {
    expect(
      guessReportHost([
        undefined,
        hostnameFromUrl('https://localhost:2011/api/'),
        'panel-damian.mg-test2.com',
        '172.17.0.1',
      ])
    ).toBe('panel-damian.mg-test2.com');
  });

  test('reads the hostname out of API_BASE_URL', () => {
    expect(hostnameFromUrl('https://panel-damian.mg-test2.com:2011/api/')).toBe(
      'panel-damian.mg-test2.com'
    );
  });
});

test.describe('Playwright serving-line rewrite', () => {
  test('turns localhost into the VPS host, including ANSI colour', () => {
    const line =
      '\u001b[36m  Serving HTML report at http://localhost:9323. Press Ctrl+C to quit.\u001b[39m';
    expect(rewriteLocalReportUrls(line, 'panel-damian.mg-test2.com')).toBe(
      '\u001b[36m  Serving HTML report at http://panel-damian.mg-test2.com:9323. Press Ctrl+C to quit.\u001b[39m'
    );
  });

  test('rewrites 0.0.0.0 and 127.0.0.1 the same way', () => {
    expect(rewriteLocalReportUrls('http://0.0.0.0:9323', '203.0.113.10')).toBe(
      'http://203.0.113.10:9323'
    );
    expect(rewriteLocalReportUrls('http://127.0.0.1:9323', '203.0.113.10')).toBe(
      'http://203.0.113.10:9323'
    );
  });

  test('brackets an IPv6 public host', () => {
    expect(formatHttpHost('2001:db8::1')).toBe('[2001:db8::1]');
    expect(reportListenUrl('2001:db8::1', '9323')).toBe('http://[2001:db8::1]:9323');
  });
});
