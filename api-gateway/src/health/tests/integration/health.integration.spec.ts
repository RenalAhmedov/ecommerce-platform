import { TestApp } from '../../../common/tests/integration/test-app.js';

describe('Health (integration)', () => {
  let testApp: TestApp;

  beforeAll(async () => {
    testApp = await TestApp.create();
  });

  afterAll(() => testApp.close());

  it('reports the database and Redis without needing a token', async () => {
    const response = await testApp.request('GET', '/health');

    expect(response).toMatchObject({
      status: 200,
      body: {
        status: 'ok',
        checks: { database: 'up', redis: 'up', elasticsearch: 'disabled' },
      },
    });
  });
});
