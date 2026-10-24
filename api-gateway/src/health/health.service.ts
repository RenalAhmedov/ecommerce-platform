import { Inject, Injectable } from '@nestjs/common';
import type { Redis } from 'ioredis';
import { DataSource } from 'typeorm';
import { REDIS_CLIENT } from '../cache/constants/cache.constants.js';
import type { HealthReport } from './interfaces/health-report.interface.js';

/**
 * The database is required: without it the instance is unhealthy and should
 * get no traffic. Redis is not: every Redis feature degrades gracefully, so
 * an outage is reported but the instance stays healthy.
 */
@Injectable()
export class HealthService {
  constructor(
    private readonly dataSource: DataSource,
    @Inject(REDIS_CLIENT) private readonly redis: Redis,
  ) {}

  async check(): Promise<HealthReport> {
    const [database, redis] = await Promise.all([
      this.isDatabaseUp(),
      this.isRedisUp(),
    ]);

    return {
      status: !database ? 'down' : redis ? 'ok' : 'degraded',
      checks: {
        database: database ? 'up' : 'down',
        redis: redis ? 'up' : 'down',
      },
    };
  }

  private async isDatabaseUp(): Promise<boolean> {
    try {
      await this.dataSource.query('SELECT 1');
      return true;
    } catch {
      return false;
    }
  }

  private async isRedisUp(): Promise<boolean> {
    if (this.redis.status !== 'ready') {
      return false;
    }

    try {
      return (await this.redis.ping()) === 'PONG';
    } catch {
      return false;
    }
  }
}
