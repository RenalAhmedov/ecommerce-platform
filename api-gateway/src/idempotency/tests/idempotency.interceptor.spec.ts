import {
  BadRequestException,
  type CallHandler,
  ConflictException,
  type ExecutionContext,
  HttpException,
  HttpStatus,
  InternalServerErrorException,
  ServiceUnavailableException,
  UnprocessableEntityException,
} from '@nestjs/common';
import { Test } from '@nestjs/testing';
import { createHash } from 'node:crypto';
import { lastValueFrom, of, throwError } from 'rxjs';
import { IdempotencyInterceptor } from '../idempotency.interceptor.js';
import { IdempotencyStore } from '../idempotency.store.js';

class OrdersController {}
function create(): void {}

describe('IdempotencyInterceptor', () => {
  let interceptor: IdempotencyInterceptor;
  let store: {
    claim: ReturnType<typeof vi.fn>;
    complete: ReturnType<typeof vi.fn>;
    release: ReturnType<typeof vi.fn>;
  };
  let response: { setHeader: ReturnType<typeof vi.fn> };
  let headers: Record<string, string>;

  const body = { items: [{ productId: 1, quantity: 1 }] };
  const fingerprint = createHash('sha256')
    .update(JSON.stringify(body))
    .digest('hex');
  const key = 'idempotency:OrdersController.create:9:abc-123';

  const context = () =>
    ({
      switchToHttp: () => ({
        getRequest: () => ({
          header: (name: string) => headers[name],
          user: { sub: 9 },
          body,
        }),
        getResponse: () => response,
      }),
      getClass: () => OrdersController,
      getHandler: () => create,
    }) as unknown as ExecutionContext;

  const handler = (result: unknown = { id: 10 }) => {
    const handle = vi.fn(() => of(result));
    return { handle } as CallHandler & { handle: typeof handle };
  };
  const failingHandler = (error: Error) => {
    const handle = vi.fn(() => throwError(() => error));
    return { handle } as CallHandler & { handle: typeof handle };
  };
  const run = async (next: CallHandler) =>
    lastValueFrom(await interceptor.intercept(context(), next));

  beforeEach(async () => {
    headers = { 'idempotency-key': 'abc-123' };
    response = { setHeader: vi.fn() };
    store = {
      claim: vi.fn().mockResolvedValue(null),
      complete: vi.fn(),
      release: vi.fn(),
    };

    const moduleRef = await Test.createTestingModule({
      providers: [
        IdempotencyInterceptor,
        { provide: IdempotencyStore, useValue: store },
      ],
    }).compile();

    interceptor = moduleRef.get(IdempotencyInterceptor);
  });

  it('passes requests without the header straight through', async () => {
    headers = {};
    const next = handler();

    await expect(run(next)).resolves.toEqual({ id: 10 });
    expect(store.claim).not.toHaveBeenCalled();
  });

  it.each(['has space', 'x'.repeat(256), ''])(
    'rejects the invalid key %#',
    async (invalidKey) => {
      headers = { 'idempotency-key': invalidKey };

      await expect(run(handler())).rejects.toThrow(BadRequestException);
      expect(store.claim).not.toHaveBeenCalled();
    },
  );

  it('claims a key scoped to handler and user, then stores the result', async () => {
    await expect(run(handler())).resolves.toEqual({ id: 10 });

    expect(store.claim).toHaveBeenCalledWith(key, fingerprint);
    expect(store.complete).toHaveBeenCalledWith(key, {
      state: 'succeeded',
      fingerprint,
      body: { id: 10 },
    });
  });

  it('replays a stored success without running the handler', async () => {
    store.claim.mockResolvedValue({
      state: 'succeeded',
      fingerprint,
      body: { id: 10 },
    });
    const next = handler();

    await expect(run(next)).resolves.toEqual({ id: 10 });
    expect(next.handle).not.toHaveBeenCalled();
    expect(response.setHeader).toHaveBeenCalledWith(
      'idempotent-replayed',
      'true',
    );
  });

  it('replays a stored client error with the same status', async () => {
    store.claim.mockResolvedValue({
      state: 'failed',
      fingerprint,
      statusCode: 402,
      response: 'Payment was declined',
    });

    const error = await run(handler()).catch((caught: unknown) => caught);

    expect(error).toBeInstanceOf(HttpException);
    expect((error as HttpException).getStatus()).toBe(402);
  });

  it('rejects a reused key with a different body', async () => {
    store.claim.mockResolvedValue({
      state: 'succeeded',
      fingerprint: 'other',
      body: {},
    });

    await expect(run(handler())).rejects.toThrow(UnprocessableEntityException);
  });

  it('rejects a duplicate while the first request is still running', async () => {
    store.claim.mockResolvedValue({ state: 'processing', fingerprint });

    await expect(run(handler())).rejects.toThrow(ConflictException);
  });

  it('stores 4xx outcomes so retries get the same answer', async () => {
    const declined = new HttpException(
      'Payment was declined',
      HttpStatus.PAYMENT_REQUIRED,
    );

    await expect(run(failingHandler(declined))).rejects.toBe(declined);
    expect(store.complete).toHaveBeenCalledWith(key, {
      state: 'failed',
      fingerprint,
      statusCode: 402,
      response: declined.getResponse(),
    });
  });

  it.each([new InternalServerErrorException(), new Error('crash')])(
    'releases the key after a server error so the client can retry (%#)',
    async (serverError) => {
      await expect(run(failingHandler(serverError))).rejects.toBe(serverError);

      expect(store.release).toHaveBeenCalledWith(key);
      expect(store.complete).not.toHaveBeenCalled();
    },
  );

  it('answers 503 when the store is unreachable', async () => {
    store.claim.mockRejectedValue(new Error('ECONNREFUSED'));
    const next = handler();

    await expect(run(next)).rejects.toThrow(ServiceUnavailableException);
    expect(next.handle).not.toHaveBeenCalled();
  });

  it('still returns the response if saving it fails', async () => {
    store.complete.mockRejectedValue(new Error('ECONNREFUSED'));

    await expect(run(handler())).resolves.toEqual({ id: 10 });
  });
});
