import type { Client } from '@elastic/elasticsearch';
import type { Redis } from 'ioredis';
import type { DataSource } from 'typeorm';
import { HealthService } from '../health.service.js';

describe('HealthService', () => {
  let dataSource: { query: ReturnType<typeof vi.fn> };
  let redis: { status: string; ping: ReturnType<typeof vi.fn> };
  let elasticsearch: { ping: ReturnType<typeof vi.fn> };

  const service = (search: typeof elasticsearch | null = elasticsearch) =>
    new HealthService(
      dataSource as unknown as DataSource,
      redis as unknown as Redis,
      search as unknown as Client | null,
    );

  beforeEach(() => {
    dataSource = { query: vi.fn().mockResolvedValue([{ '?column?': 1 }]) };
    redis = { status: 'ready', ping: vi.fn().mockResolvedValue('PONG') };
    elasticsearch = { ping: vi.fn().mockResolvedValue(true) };
  });

  it('is ok when every dependency answers', async () => {
    await expect(service().check()).resolves.toEqual({
      status: 'ok',
      checks: { database: 'up', redis: 'up', elasticsearch: 'up' },
    });
  });

  it('reports Elasticsearch as disabled when it is not configured', async () => {
    await expect(service(null).check()).resolves.toMatchObject({
      status: 'ok',
      checks: { elasticsearch: 'disabled' },
    });
  });

  it('is only degraded without Redis or Elasticsearch, which every feature can live without', async () => {
    redis.status = 'reconnecting';
    elasticsearch.ping.mockRejectedValue(new Error('ECONNREFUSED'));

    await expect(service().check()).resolves.toEqual({
      status: 'degraded',
      checks: { database: 'up', redis: 'down', elasticsearch: 'down' },
    });
    expect(redis.ping).not.toHaveBeenCalled();
  });

  it('is down without the database', async () => {
    dataSource.query.mockRejectedValue(new Error('ECONNREFUSED'));

    await expect(service().check()).resolves.toMatchObject({
      status: 'down',
      checks: { database: 'down' },
    });
  });
});
