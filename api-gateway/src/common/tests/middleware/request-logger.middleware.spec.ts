import { Logger } from '@nestjs/common';
import type { NextFunction, Request, Response } from 'express';
import { EventEmitter } from 'node:events';
import { RequestLoggerMiddleware } from '../../middleware/request-logger.middleware.js';

describe('RequestLoggerMiddleware', () => {
  const middleware = new RequestLoggerMiddleware();
  let log: ReturnType<typeof vi.spyOn>;
  let warn: ReturnType<typeof vi.spyOn>;
  let response: EventEmitter & {
    statusCode: number;
    setHeader: ReturnType<typeof vi.fn>;
  };

  const request = (headers: Record<string, string> = {}, user?: object) =>
    ({
      method: 'GET',
      originalUrl: '/api/products',
      header: (name: string) => headers[name.toLowerCase()],
      user,
    }) as unknown as Request;

  const handle = (req: Request) => {
    const next = vi.fn();
    middleware.use(req, response as unknown as Response, next as NextFunction);
    return next;
  };

  beforeEach(() => {
    response = Object.assign(new EventEmitter(), {
      statusCode: 200,
      setHeader: vi.fn(),
    });
    log = vi.spyOn(Logger.prototype, 'log').mockImplementation(() => undefined);
    warn = vi
      .spyOn(Logger.prototype, 'warn')
      .mockImplementation(() => undefined);
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('keeps a valid request id from the caller and logs it with the user', () => {
    const next = handle(
      request({ 'x-request-id': 'admin-request-0001' }, { sub: 7 }),
    );
    response.emit('finish');

    expect(next).toHaveBeenCalled();
    expect(response.setHeader).toHaveBeenCalledWith(
      'X-Request-Id',
      'admin-request-0001',
    );
    expect(log).toHaveBeenCalledWith(
      expect.stringMatching(
        /^GET \/api\/products 200 \d+ms request=admin-request-0001 user=7$/,
      ),
    );
  });

  it('replaces a missing or malformed id with a new one', () => {
    handle(request({ 'x-request-id': 'bad id\nwith newline' }));

    const [, id] = response.setHeader.mock.calls[0] as [string, string];
    expect(id).toMatch(/^[0-9a-f-]{36}$/);
  });

  it('logs client errors as warnings', () => {
    handle(request());
    response.statusCode = 404;
    response.emit('finish');

    expect(warn).toHaveBeenCalledWith(expect.stringContaining(' 404 '));
  });
});
