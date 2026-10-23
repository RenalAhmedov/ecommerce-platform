import { type ExecutionContext, UnauthorizedException } from '@nestjs/common';
import type { ConfigService } from '@nestjs/config';
import type { EnvironmentVariables } from '../../../config/interfaces/environment-variables.interface.js';
import { ApiKeyGuard } from '../../guards/api-key.guard.js';

describe('ApiKeyGuard', () => {
  const apiKey = 'unit-test-internal-api-key-0123456789abcdef';
  const config = {
    get: () => apiKey,
  } as unknown as ConfigService<EnvironmentVariables, true>;
  const guard = new ApiKeyGuard(config);

  const contextWithKey = (key?: string) =>
    ({
      switchToHttp: () => ({
        getRequest: () => ({
          header: (name: string) => (name === 'x-api-key' ? key : undefined),
        }),
      }),
    }) as unknown as ExecutionContext;

  it('lets a caller with the shared key through', () => {
    expect(guard.canActivate(contextWithKey(apiKey))).toBe(true);
  });

  it.each([
    ['a missing key', undefined],
    ['an empty key', ''],
    ['a wrong key of the same length', apiKey.replace(/.$/, 'x')],
    ['a shorter key', apiKey.slice(0, 10)],
  ])('rejects %s', (_case, key) => {
    expect(() => guard.canActivate(contextWithKey(key))).toThrow(
      new UnauthorizedException('Missing or invalid API key'),
    );
  });
});
