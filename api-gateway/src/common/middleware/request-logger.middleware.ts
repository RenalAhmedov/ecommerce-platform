import { Injectable, Logger, NestMiddleware } from '@nestjs/common';
import type { NextFunction, Request, Response } from 'express';
import { randomUUID } from 'node:crypto';
import { performance } from 'node:perf_hooks';
import type { JwtPayload } from '../../auth/interfaces/jwt-payload.interface.js';
import {
  REQUEST_ID_HEADER,
  REQUEST_ID_PATTERN,
} from '../constants/request-id.constants.js';

/** Polled by Docker and Nginx every few seconds; logging it would only add noise. */
const SILENT_PATHS = new Set(['/api/health']);

/**
 * Logs one line per request with its status, duration, user and request id.
 * The id is kept from X-Request-Id when the caller sent a valid one (the admin
 * service does, so one action can be followed through both services' logs),
 * otherwise generated, and is returned in the same header.
 */
@Injectable()
export class RequestLoggerMiddleware implements NestMiddleware {
  private readonly logger = new Logger('HTTP');

  use(
    req: Request & { user?: JwtPayload },
    res: Response,
    next: NextFunction,
  ): void {
    const start = performance.now();
    const incomingId = req.header(REQUEST_ID_HEADER);
    const requestId =
      incomingId && REQUEST_ID_PATTERN.test(incomingId)
        ? incomingId
        : randomUUID();

    res.setHeader(REQUEST_ID_HEADER, requestId);

    if (SILENT_PATHS.has(req.originalUrl)) {
      next();
      return;
    }

    res.on('finish', () => {
      const duration = Math.round(performance.now() - start);
      const user = req.user ? ` user=${req.user.sub}` : '';
      const message = `${req.method} ${req.originalUrl} ${res.statusCode} ${duration}ms request=${requestId}${user}`;

      if (res.statusCode >= 500) {
        this.logger.error(message);
      } else if (res.statusCode >= 400) {
        this.logger.warn(message);
      } else {
        this.logger.log(message);
      }
    });

    next();
  }
}
