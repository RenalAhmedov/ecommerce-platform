import { applyDecorators, HttpStatus } from '@nestjs/common';
import { ApiResponse } from '@nestjs/swagger';
import { ErrorResponseDto } from '../dto/error-response.dto.js';

const DESCRIPTIONS: Partial<Record<HttpStatus, string>> = {
  [HttpStatus.BAD_REQUEST]: 'Invalid input',
  [HttpStatus.UNAUTHORIZED]: 'Missing, invalid or expired access token',
  [HttpStatus.PAYMENT_REQUIRED]: 'Payment was declined',
  [HttpStatus.FORBIDDEN]: 'Requires the admin role',
  [HttpStatus.NOT_FOUND]: 'Not found',
  [HttpStatus.CONFLICT]: 'Conflicts with existing data',
  [HttpStatus.UNPROCESSABLE_ENTITY]:
    'Idempotency-Key was already used with a different body',
  [HttpStatus.TOO_MANY_REQUESTS]:
    'Rate limit exceeded, see the Retry-After header',
  [HttpStatus.SERVICE_UNAVAILABLE]: 'A dependency is temporarily unavailable',
};

export function ApiErrorResponses(...statuses: HttpStatus[]) {
  return applyDecorators(
    ...statuses.map((status) =>
      ApiResponse({
        status,
        description: DESCRIPTIONS[status],
        type: ErrorResponseDto,
      }),
    ),
  );
}
