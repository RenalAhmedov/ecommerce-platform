import {
  ArgumentsHost,
  Catch,
  ExceptionFilter,
  HttpException,
  HttpStatus,
  Logger,
} from '@nestjs/common';
import { HttpAdapterHost } from '@nestjs/core';
import { STATUS_CODES } from 'node:http';
import type { ErrorResponse } from '../interfaces/error-response.interface.js';

@Catch()
export class AllExceptionsFilter implements ExceptionFilter {
  private readonly logger = new Logger(AllExceptionsFilter.name);

  constructor(private readonly httpAdapterHost: HttpAdapterHost) {}

  catch(exception: unknown, host: ArgumentsHost): void {
    if (host.getType() !== 'http') {
      this.logger.error(
        `Unhandled ${host.getType()} error`,
        exception instanceof Error ? exception.stack : String(exception),
      );
      return;
    }

    const { httpAdapter } = this.httpAdapterHost;
    const ctx = host.switchToHttp();
    const request: unknown = ctx.getRequest();

    const body: ErrorResponse = {
      ...this.toStatusAndMessage(exception),
      path: httpAdapter.getRequestUrl(request) as string,
      timestamp: new Date().toISOString(),
    };

    if (body.statusCode >= 500) {
      this.logger.error(
        `${body.statusCode} ${body.path}`,
        exception instanceof Error ? exception.stack : String(exception),
      );
    }

    httpAdapter.reply(ctx.getResponse(), body, body.statusCode);
  }

  private toStatusAndMessage(
    exception: unknown,
  ): Pick<ErrorResponse, 'statusCode' | 'error' | 'message'> {
    if (isClientHttpError(exception)) {
      return {
        statusCode: exception.status,
        error: STATUS_CODES[exception.status] ?? 'Error',
        message: exception.message,
      };
    }

    if (!(exception instanceof HttpException)) {
      return {
        statusCode: HttpStatus.INTERNAL_SERVER_ERROR,
        error: 'Internal Server Error',
        message: 'An unexpected error occurred',
      };
    }

    const statusCode = exception.getStatus();
    const response = exception.getResponse();
    const statusText = STATUS_CODES[statusCode] ?? 'Error';

    if (typeof response === 'string') {
      return { statusCode, error: statusText, message: response };
    }

    const { error, message } = response as {
      error?: string;
      message?: string | string[];
    };

    return {
      statusCode,
      error: error ?? statusText,
      message: message ?? exception.message,
    };
  }
}

function isClientHttpError(
  exception: unknown,
): exception is Error & { status: number } {
  return (
    exception instanceof Error &&
    'expose' in exception &&
    exception.expose === true &&
    'status' in exception &&
    typeof exception.status === 'number'
  );
}
