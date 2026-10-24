import type { ExecutionContext } from '@nestjs/common';
import { httpContextOf } from '../../utils/http-context.js';

describe('httpContextOf', () => {
  const req = { headers: {} };
  const res = { setHeader: vi.fn() };

  const context = (type: string) =>
    ({
      getType: () => type,
      // GraphQL resolvers receive (root, args, context, info); Nest keeps them as the "args".
      getArgs: () => [{}, {}, { req, res }, {}],
      getClass: () => undefined,
      getHandler: () => undefined,
      switchToHttp: () => ({ getRequest: () => req, getResponse: () => res }),
    }) as unknown as ExecutionContext;

  it('finds the request behind a REST call and behind a GraphQL operation', () => {
    expect(httpContextOf(context('http'))).toEqual({ req, res });
    expect(httpContextOf(context('graphql'))).toEqual({ req, res });
  });

  it('has none for Redis events', () => {
    expect(httpContextOf(context('rpc'))).toBeNull();
  });
});
