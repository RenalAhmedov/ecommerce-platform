import { TestApp } from './test-app.js';

vi.hoisted(() => {
  process.env.CORS_ORIGINS = 'http://shop.example';
});

describe('HTTP security headers and CORS (integration)', () => {
  let testApp: TestApp;

  beforeAll(async () => {
    testApp = await TestApp.create();
  });

  afterAll(() => testApp.close());

  const preflight = (origin: string) =>
    fetch(testApp.url('/orders'), {
      method: 'OPTIONS',
      headers: {
        origin,
        'access-control-request-method': 'POST',
        'access-control-request-headers': 'authorization,idempotency-key',
      },
    });

  it('sends helmet security headers and hides the framework', async () => {
    const { headers } = await testApp.request('GET', '/products');

    expect(headers.get('x-content-type-options')).toBe('nosniff');
    expect(headers.get('x-frame-options')).toBe('SAMEORIGIN');
    expect(headers.get('strict-transport-security')).toContain('max-age=');
    expect(headers.get('content-security-policy')).toContain(
      "default-src 'self'",
    );
    expect(headers.get('x-powered-by')).toBeNull();
  });

  it('allows a configured origin and exposes the custom headers', async () => {
    const response = await preflight('http://shop.example');
    const { headers } = await testApp.request('GET', '/products', {
      headers: { origin: 'http://shop.example' },
    });

    expect(response.headers.get('access-control-allow-origin')).toBe(
      'http://shop.example',
    );
    expect(headers.get('access-control-expose-headers')).toContain(
      'Idempotent-Replayed',
    );
  });

  it('gives an unknown origin no CORS permission', async () => {
    const response = await preflight('http://evil.example');

    expect(response.headers.get('access-control-allow-origin')).toBeNull();
  });
});
