import { Logger } from '@nestjs/common';
import { ClientRedis, ServerRedis } from '@nestjs/microservices';
import type { Redis } from 'ioredis';
import { ThrottledWarning } from '../common/logging/throttled-warning.js';
import { describeError } from '../common/utils/describe-error.js';

export class RedisEventsServer extends ServerRedis {
  private readonly connectionWarning = new ThrottledWarning(
    new Logger(RedisEventsServer.name),
  );

  override registerErrorListener(client: Redis): void {
    client.on('error', (error: Error) =>
      this.connectionWarning.warn(
        `Event listener cannot reach Redis (${describeError(error)})`,
      ),
    );
  }
}

export class RedisEventsClient extends ClientRedis {
  private readonly connectionWarning = new ThrottledWarning(
    new Logger(RedisEventsClient.name),
  );

  override registerErrorListener(client: Redis): void {
    client.on('error', (error: Error) =>
      this.connectionWarning.warn(
        `Event publisher cannot reach Redis (${describeError(error)})`,
      ),
    );
  }
}
