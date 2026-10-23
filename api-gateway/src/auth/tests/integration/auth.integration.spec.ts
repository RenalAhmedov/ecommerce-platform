import { TestApp } from '../../../common/tests/integration/test-app.js';

describe('Auth (integration)', () => {
  let testApp: TestApp;

  beforeAll(async () => {
    testApp = await TestApp.create();
  });

  afterAll(() => testApp.close());

  beforeEach(() => testApp.reset());

  it('registers, logs in and returns the profile for the token', async () => {
    const registered = await testApp.request('POST', '/auth/register', {
      body: {
        name: 'Ada',
        email: ' Ada@Example.TEST ',
        password: 'password123',
      },
    });
    const login = await testApp.request<{ accessToken: string }>(
      'POST',
      '/auth/login',
      { body: { email: 'ada@example.test', password: 'password123' } },
    );
    const me = await testApp.request('GET', '/auth/me', {
      token: login.body.accessToken,
    });

    expect(registered.status).toBe(201);
    expect(login.status).toBe(200);
    expect(me.body).toMatchObject({
      email: 'ada@example.test',
      role: 'customer',
    });
    expect(me.body).not.toHaveProperty('password');

    const [user] = await testApp.query<{ password: string }>(
      'SELECT password FROM users',
    );
    expect(user.password).toMatch(/^\$2y\$12\$/);
  });

  it('answers 401 for a wrong password, an unknown email and a bad token', async () => {
    await testApp.signUp('ada@example.test');

    const wrongPassword = await testApp.request('POST', '/auth/login', {
      body: { email: 'ada@example.test', password: 'wrong-password' },
    });
    const unknownEmail = await testApp.request('POST', '/auth/login', {
      body: { email: 'nobody@example.test', password: 'password123' },
    });
    const badToken = await testApp.request('GET', '/auth/me', {
      token: 'not-a-jwt',
    });

    expect([
      wrongPassword.status,
      unknownEmail.status,
      badToken.status,
    ]).toEqual([401, 401, 401]);
  });

  it('creates exactly one user for 10 concurrent registrations of the same email', async () => {
    const responses = await Promise.all(
      Array.from({ length: 10 }, () =>
        testApp.request('POST', '/auth/register', {
          body: {
            name: 'Ada',
            email: 'ada@example.test',
            password: 'password123',
          },
        }),
      ),
    );

    const statuses = responses
      .map((response) => response.status)
      .sort((a, b) => a - b);
    expect(statuses).toEqual([201, ...Array<number>(9).fill(409)]);
    expect(await testApp.query('SELECT id FROM users')).toHaveLength(1);
  });

  it('keeps the email of a soft-deleted user reserved so it can be restored', async () => {
    await testApp.signUp('ada@example.test');
    await testApp.query('UPDATE users SET deleted_at = now()');

    const login = await testApp.request('POST', '/auth/login', {
      body: { email: 'ada@example.test', password: 'password123' },
    });
    const registerAgain = await testApp.request('POST', '/auth/register', {
      body: { name: 'Ada', email: 'ADA@example.test', password: 'password123' },
    });

    expect(login.status).toBe(401);
    expect(registerAgain.status).toBe(409);
  });

  it('revokes only the logged-out token, until it would have expired', async () => {
    const { token: phone } = await testApp.signUp('ada@example.test');
    const { body: laptop } = await testApp.request<{ accessToken: string }>(
      'POST',
      '/auth/login',
      { body: { email: 'ada@example.test', password: 'password123' } },
    );

    const logout = await testApp.request('POST', '/auth/logout', {
      token: phone,
    });
    const phoneAfter = await testApp.request('GET', '/auth/me', {
      token: phone,
    });
    const laptopAfter = await testApp.request('GET', '/auth/me', {
      token: laptop.accessToken,
    });
    const revokedKeys = await testApp.redis.keys('auth:revoked-token:*');
    const ttl = await testApp.redis.ttl(revokedKeys[0]);

    expect(logout.status).toBe(204);
    expect(phoneAfter.status).toBe(401);
    expect(phoneAfter.body).toMatchObject({
      message: 'Access token has been revoked',
    });
    expect(laptopAfter.status).toBe(200);
    expect(revokedKeys).toHaveLength(1);
    expect(ttl).toBeGreaterThan(3_500);
    expect(ttl).toBeLessThanOrEqual(3_600);
  });

  it('refuses the tokens a user held before the admin service cut them off', async () => {
    const { id, token } = await testApp.signUp('ada@example.test');
    const issuedAt = Math.floor(Date.now() / 1000);
    const cutOffAt = async (unixTime: number) => {
      await testApp.redis.set(
        `auth:user-tokens-valid-after:${id}`,
        String(unixTime),
      );
      return testApp.request('GET', '/auth/me', { token });
    };

    const cutOffBefore = await cutOffAt(issuedAt - 60);
    const cutOffAfter = await cutOffAt(issuedAt + 60);

    expect(cutOffBefore.status).toBe(200);
    expect(cutOffAfter.status).toBe(401);
    expect(cutOffAfter.body).toMatchObject({
      message: 'Access token has been revoked',
    });
  });

  it('cannot log out twice with the same token', async () => {
    const { token } = await testApp.signUp('ada@example.test');

    await testApp.request('POST', '/auth/logout', { token });
    const again = await testApp.request('POST', '/auth/logout', { token });

    expect(again.status).toBe(401);
  });
});
