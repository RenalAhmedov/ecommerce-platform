import type { Redis } from 'ioredis';
import type { DataSource } from 'typeorm';
import { HealthService } from '../health.service.js';

describe('HealthService', () => {
  let dataSource: { query: ReturnType<typeof vi.fn> };
  let redis: { status: string; ping: ReturnType<typeof vi.fn> };
  let service: HealthService;

  beforeEach(() => {
    dataSource = { query: vi.fn().mockResolvedValue([{ '?column?': 1 }]) };
    redis = { status: 'ready', ping: vi.fn().mockResolvedValue('PONG') };
    service = new HealthService(
      dataSource as unknown as DataSource,
      redis as unknown as Redis,
    );
  });

  it('is ok when the database and Redis answer', async () => {
    await expect(service.check()).resolves.toEqual({
      status: 'ok',
      checks: { database: 'up', redis: 'up' },
    });
  });

  it('is only degraded without Redis, which every feature can live without', async () => {
    redis.status = 'reconnecting';

    await expect(service.check()).resolves.toMatchObject({
      status: 'degraded',
      checks: { redis: 'down' },
    });
    expect(redis.ping).not.toHaveBeenCalled();
  });

  it('is down without the database', async () => {
    dataSource.query.mockRejectedValue(new Error('ECONNREFUSED'));

    await expect(service.check()).resolves.toMatchObject({
      status: 'down',
      checks: { database: 'down' },
    });
  });
});
