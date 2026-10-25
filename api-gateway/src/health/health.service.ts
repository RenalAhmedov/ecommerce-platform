import type { Client } from '@elastic/elasticsearch';
import { Inject, Injectable } from '@nestjs/common';
import type { Redis } from 'ioredis';
import { DataSource } from 'typeorm';
import { REDIS_CLIENT } from '../cache/constants/cache.constants.js';
import { ELASTICSEARCH_CLIENT } from '../elasticsearch/elasticsearch.constants.js';
import type {
  DependencyStatus,
  HealthReportDto,
} from './dto/health-report.dto.js';

@Injectable()
export class HealthService {
  constructor(
    private readonly dataSource: DataSource,
    @Inject(REDIS_CLIENT) private readonly redis: Redis,
    @Inject(ELASTICSEARCH_CLIENT) private readonly elasticsearch: Client | null,
  ) {}

  async check(): Promise<HealthReportDto> {
    const [database, redis, elasticsearch] = await Promise.all([
      this.checkDatabase(),
      this.checkRedis(),
      this.checkElasticsearch(),
    ]);
    // Only the database is required: every feature survives Redis or Elasticsearch being down.
    const degraded = redis === 'down' || elasticsearch === 'down';

    return {
      status: database === 'down' ? 'down' : degraded ? 'degraded' : 'ok',
      checks: { database, redis, elasticsearch },
    };
  }

  private async checkDatabase(): Promise<DependencyStatus> {
    try {
      await this.dataSource.query('SELECT 1');
      return 'up';
    } catch {
      return 'down';
    }
  }

  private async checkRedis(): Promise<DependencyStatus> {
    if (this.redis.status !== 'ready') {
      return 'down';
    }

    try {
      return (await this.redis.ping()) === 'PONG' ? 'up' : 'down';
    } catch {
      return 'down';
    }
  }

  private async checkElasticsearch(): Promise<DependencyStatus | 'disabled'> {
    if (!this.elasticsearch) {
      return 'disabled';
    }

    try {
      return (await this.elasticsearch.ping()) ? 'up' : 'down';
    } catch {
      return 'down';
    }
  }
}
