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
    expect(user.password).toMatch(/^\$2[aby]\$12\$/);
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
});
