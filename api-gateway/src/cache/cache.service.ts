import { Inject, Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type { Redis } from 'ioredis';
import { describeError } from '../common/utils/describe-error.js';
import type { EnvironmentVariables } from '../config/interfaces/environment-variables.interface.js';
import { REDIS_CLIENT } from './constants/cache.constants.js';

@Injectable()
export class CacheService {
  private readonly logger = new Logger(CacheService.name);
  private readonly ttlSeconds: number;

  constructor(
    @Inject(REDIS_CLIENT) private readonly redis: Redis,
    config: ConfigService<EnvironmentVariables, true>,
  ) {
    this.ttlSeconds = config.get('CACHE_TTL_SECONDS');
  }

  async wrap<T>(key: string, load: () => Promise<T>): Promise<T> {
    const cached = await this.get<T>(key);

    if (cached !== null) {
      return cached;
    }

    const value = await load();
    await this.set(key, value);

    return value;
  }

  async get<T>(key: string): Promise<T | null> {
    if (!this.isAvailable()) {
      return null;
    }

    try {
      const raw = await this.redis.get(key);

      return raw === null ? null : (JSON.parse(raw) as T);
    } catch (error) {
      this.logFailure('read', key, error);
      return null;
    }
  }

  async set(key: string, value: unknown): Promise<void> {
    if (!this.isAvailable()) {
      return;
    }

    try {
      await this.redis.set(key, JSON.stringify(value), 'EX', this.ttlSeconds);
    } catch (error) {
      this.logFailure('write', key, error);
    }
  }

  async increment(key: string): Promise<boolean> {
    if (!this.isAvailable()) {
      return false;
    }

    try {
      await this.redis.incr(key);
      return true;
    } catch (error) {
      this.logFailure('increment', key, error);
      return false;
    }
  }

  private isAvailable(): boolean {
    return this.redis.status === 'ready';
  }

  private logFailure(action: string, key: string, error: unknown): void {
    this.logger.warn(
      `Cache ${action} failed for "${key}": ${describeError(error)}`,
    );
  }
}
