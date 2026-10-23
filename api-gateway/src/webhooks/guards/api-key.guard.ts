import {
  CanActivate,
  ExecutionContext,
  Injectable,
  UnauthorizedException,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { createHash, timingSafeEqual } from 'node:crypto';
import type { Request } from 'express';
import type { EnvironmentVariables } from '../../config/interfaces/environment-variables.interface.js';
import { API_KEY_HEADER } from '../constants/webhooks.constants.js';

/**
 * Service-to-service authentication: the caller must send the shared
 * INTERNAL_API_KEY in the X-Api-Key header. Both values are hashed before the
 * constant-time comparison, so neither the key nor its length leaks through
 * response timing.
 */
@Injectable()
export class ApiKeyGuard implements CanActivate {
  private readonly expectedDigest: Buffer;

  constructor(config: ConfigService<EnvironmentVariables, true>) {
    this.expectedDigest = digest(config.get('INTERNAL_API_KEY'));
  }

  canActivate(context: ExecutionContext): boolean {
    const provided = context
      .switchToHttp()
      .getRequest<Request>()
      .header(API_KEY_HEADER);

    if (!provided || !timingSafeEqual(digest(provided), this.expectedDigest)) {
      throw new UnauthorizedException('Missing or invalid API key');
    }

    return true;
  }
}

function digest(value: string): Buffer {
  return createHash('sha256').update(value).digest();
}
