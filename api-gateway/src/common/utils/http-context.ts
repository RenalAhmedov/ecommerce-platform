import type { ExecutionContext } from '@nestjs/common';
import { type GqlContextType, GqlExecutionContext } from '@nestjs/graphql';
import type { Request, Response } from 'express';

export interface HttpContext<TRequest extends Request = Request> {
  req: TRequest;
  res: Response;
}

/**
 * The HTTP request and response behind a REST call or a GraphQL operation, so
 * guards protect both the same way; null for other contexts (Redis events).
 */
export function httpContextOf<TRequest extends Request = Request>(
  context: ExecutionContext,
): HttpContext<TRequest> | null {
  switch (context.getType<GqlContextType>()) {
    case 'http':
      return {
        req: context.switchToHttp().getRequest<TRequest>(),
        res: context.switchToHttp().getResponse<Response>(),
      };
    case 'graphql':
      return GqlExecutionContext.create(context).getContext<
        HttpContext<TRequest>
      >();
    default:
      return null;
  }
}
