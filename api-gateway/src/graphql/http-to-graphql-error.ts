import { HttpStatus, type HttpException } from '@nestjs/common';
import { GraphQLError } from 'graphql';

const CODES: Partial<Record<HttpStatus, string>> = {
  [HttpStatus.BAD_REQUEST]: 'BAD_REQUEST',
  [HttpStatus.UNAUTHORIZED]: 'UNAUTHENTICATED',
  [HttpStatus.FORBIDDEN]: 'FORBIDDEN',
  [HttpStatus.NOT_FOUND]: 'NOT_FOUND',
  [HttpStatus.CONFLICT]: 'CONFLICT',
  [HttpStatus.TOO_MANY_REQUESTS]: 'TOO_MANY_REQUESTS',
  [HttpStatus.SERVICE_UNAVAILABLE]: 'SERVICE_UNAVAILABLE',
};

export function httpToGraphqlError(exception: HttpException): GraphQLError {
  const status = exception.getStatus();
  const response = exception.getResponse();
  const message =
    typeof response === 'object' && 'message' in response
      ? (response.message as string | string[])
      : exception.message;
  const details = Array.isArray(message) ? message : [message];

  return new GraphQLError(details.join('; '), {
    extensions: {
      code: CODES[status as HttpStatus] ?? 'INTERNAL_SERVER_ERROR',
      status,
      details,
    },
  });
}
