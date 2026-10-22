import {
  type ArgumentsHost,
  BadRequestException,
  Logger,
  NotFoundException,
} from '@nestjs/common';
import type { HttpAdapterHost } from '@nestjs/core';
import { AllExceptionsFilter } from '../../filters/all-exceptions.filter.js';

describe('AllExceptionsFilter', () => {
  let reply: ReturnType<typeof vi.fn>;
  let filter: AllExceptionsFilter;

  const host = (type = 'http') =>
    ({
      getType: () => type,
      switchToHttp: () => ({
        getRequest: () => ({}),
        getResponse: () => ({}),
      }),
    }) as unknown as ArgumentsHost;

  afterEach(() => {
    vi.restoreAllMocks();
  });

  const replied = () => reply.mock.calls[0] as [unknown, object, number];

  beforeEach(() => {
    vi.spyOn(Logger.prototype, 'error').mockImplementation(() => undefined);
    reply = vi.fn();
    filter = new AllExceptionsFilter({
      httpAdapter: { getRequestUrl: () => '/api/orders', reply },
    } as unknown as HttpAdapterHost);
  });

  it('formats an HTTP exception with status, label, message and path', () => {
    filter.catch(new NotFoundException('Order not found'), host());

    const [, body, status] = replied();
    expect(status).toBe(404);
    expect(body).toMatchObject({
      statusCode: 404,
      error: 'Not Found',
      message: 'Order not found',
      path: '/api/orders',
    });
    expect(body).toHaveProperty('timestamp');
  });

  it('keeps the list of validation messages', () => {
    filter.catch(
      new BadRequestException(['name should not be empty', 'price too high']),
      host(),
    );

    expect(replied()[1]).toMatchObject({
      statusCode: 400,
      message: ['name should not be empty', 'price too high'],
    });
  });

  it('hides the details of unexpected errors behind a generic 500', () => {
    filter.catch(new Error('password for db is hunter2'), host());

    const [, body, status] = replied();
    expect(status).toBe(500);
    expect(body).toMatchObject({ message: 'An unexpected error occurred' });
    expect(JSON.stringify(body)).not.toContain('hunter2');
  });

  it('keeps client errors raised before Nest, like an oversized body', () => {
    const tooLarge = Object.assign(new Error('request entity too large'), {
      status: 413,
      expose: true,
    });

    filter.catch(tooLarge, host());

    expect(replied()[2]).toBe(413);
    expect(replied()[1]).toMatchObject({ error: 'Payload Too Large' });
  });

  it('does not try to answer non-HTTP contexts', () => {
    filter.catch(new Error('event handler failed'), host('rpc'));

    expect(reply).not.toHaveBeenCalled();
  });
});
