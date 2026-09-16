const SECRET_PATTERNS: [RegExp, string][] = [
  [/\bBearer\s+\S+/gi, 'Bearer [REDACTED]'],
  [/(Authorization:\s*Bearer\s+)[^\s"']+/gi, '$1[REDACTED]'],
  [/(API_TOKEN\s*=\s*)("[^"]*"|'[^']*'|[^\s]+)/gi, '$1[REDACTED]'],
  [/(ENGINE_API_TOKEN\s*=\s*)("[^"]*"|'[^']*'|[^\s]+)/gi, '$1[REDACTED]'],
  [/(ENGINE_LICENSE_KEY\s*=\s*)("[^"]*"|'[^']*'|[^\s]+)/gi, '$1[REDACTED]'],
  [/(LICENSE_KEY\s*=\s*)("[^"]*"|'[^']*'|[^\s]+)/gi, '$1[REDACTED]'],
  [/(Cookie:\s*)[^\n\r]+/gi, '$1[REDACTED]'],
  [/("Authorization"\s*:\s*"Bearer\s+)[^"]+"/gi, '$1[REDACTED]"'],
  [/("authorization"\s*:\s*"Bearer\s+)[^"]+"/gi, '$1[REDACTED]"'],
  [/("cookie"\s*:\s*")[^"]+"/gi, '$1[REDACTED]"'],
  [/("token"\s*:\s*")[^"]+"/gi, '$1[REDACTED]"'],
];

export function redactSensitiveText(value: string): string {
  return SECRET_PATTERNS.reduce(
    (redacted, [pattern, replacement]) => redacted.replace(pattern, replacement),
    value
  );
}

export function redactSensitiveValue(value: unknown): unknown {
  if (typeof value === 'string') {
    return redactSensitiveText(value);
  }
  if (Array.isArray(value)) {
    return value.map((item) => redactSensitiveValue(item));
  }
  if (value && typeof value === 'object') {
    return Object.fromEntries(
      Object.entries(value as Record<string, unknown>).map(([key, item]) => [
        key,
        redactSensitiveValue(item),
      ])
    );
  }
  return value;
}
