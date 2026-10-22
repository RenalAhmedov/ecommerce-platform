import {
  BadRequestException,
  type CallHandler,
  ConflictException,
  type ExecutionContext,
  HttpException,
  Injectable,
  Logger,
  type NestInterceptor,
  ServiceUnavailableException,
  UnprocessableEntityException,
} from '@nestjs/common';
import type { Response } from 'express';
import { createHash } from 'node:crypto';
import { catchError, mergeMap, type Observable, of } from 'rxjs';
import type { AuthenticatedRequest } from '../auth/interfaces/authenticated-request.interface.js';
import {
  IDEMPOTENCY_HEADER,
  IDEMPOTENCY_KEY_PATTERN,
  IDEMPOTENCY_REPLAYED_HEADER,
} from './constants/idempotency.constants.js';
import { IdempotencyStore } from './idempotency.store.js';
import type {
  CompletedIdempotencyRecord,
  IdempotencyRecord,
} from './types/idempotency-record.type.js';

@Injectable()
export class IdempotencyInterceptor implements NestInterceptor {
  private readonly logger = new Logger(IdempotencyInterceptor.name);

  constructor(private readonly idempotencyStore: IdempotencyStore) {}

  async intercept(
    context: ExecutionContext,
    next: CallHandler,
  ): Promise<Observable<unknown>> {
    const request = context.switchToHttp().getRequest<AuthenticatedRequest>();
    const idempotencyKey = request.header(IDEMPOTENCY_HEADER);

    if (idempotencyKey === undefined) {
      return next.handle();
    }

    if (!IDEMPOTENCY_KEY_PATTERN.test(idempotencyKey)) {
      throw new BadRequestException(
        'Idempotency-Key must be 1-255 characters of letters, digits, "-", "_", ":" or "."',
      );
    }

    const key = `idempotency:${context.getClass().name}.${context.getHandler().name}:${request.user.sub}:${idempotencyKey}`;
    const fingerprint = createHash('sha256')
      .update(JSON.stringify(request.body ?? null))
      .digest('hex');
    const existing = await this.claim(key, fingerprint);

    if (existing) {
      return of(this.replay(existing, fingerprint, context));
    }

    return next.handle().pipe(
      mergeMap(async (body: unknown) => {
        await this.save(key, { state: 'succeeded', fingerprint, body });
        return body;
      }),
      catchError(async (error: unknown) => {
        await this.saveFailure(key, fingerprint, error);
        throw error;
      }),
    );
  }

  private async claim(
    key: string,
    fingerprint: string,
  ): Promise<IdempotencyRecord | null> {
    try {
      return await this.idempotencyStore.claim(key, fingerprint);
    } catch (error) {
      this.logger.warn(`Idempotency store unavailable: ${describe(error)}`);
      throw new ServiceUnavailableException(
        'Idempotent requests are temporarily unavailable, please retry shortly',
      );
    }
  }

  private replay(
    record: IdempotencyRecord,
    fingerprint: string,
    context: ExecutionContext,
  ): unknown {
    if (record.fingerprint !== fingerprint) {
      throw new UnprocessableEntityException(
        'Idempotency-Key was already used with a different request body',
      );
    }

    if (record.state === 'processing') {
      throw new ConflictException(
        'A request with this Idempotency-Key is still being processed',
      );
    }

    if (record.state === 'failed') {
      throw new HttpException(record.response, record.statusCode);
    }

    context
      .switchToHttp()
      .getResponse<Response>()
      .setHeader(IDEMPOTENCY_REPLAYED_HEADER, 'true');

    return record.body;
  }

  private async saveFailure(
    key: string,
    fingerprint: string,
    error: unknown,
  ): Promise<void> {
    if (error instanceof HttpException && error.getStatus() < 500) {
      await this.save(key, {
        state: 'failed',
        fingerprint,
        statusCode: error.getStatus(),
        response: error.getResponse(),
      });
      return;
    }

    try {
      await this.idempotencyStore.release(key);
    } catch (releaseError) {
      this.logger.warn(
        `Could not release idempotency key ${key}: ${describe(releaseError)}`,
      );
    }
  }

  private async save(
    key: string,
    record: CompletedIdempotencyRecord,
  ): Promise<void> {
    try {
      await this.idempotencyStore.complete(key, record);
    } catch (error) {
      this.logger.warn(
        `Could not store idempotent result for ${key}: ${describe(error)}`,
      );
    }
  }
}

function describe(error: unknown): string {
  return error instanceof Error ? error.message : String(error);
}
