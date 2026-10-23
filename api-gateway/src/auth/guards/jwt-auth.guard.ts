import {
  CanActivate,
  ExecutionContext,
  Injectable,
  Logger,
  UnauthorizedException,
} from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { JwtService } from '@nestjs/jwt';
import type { Request } from 'express';
import { IS_PUBLIC_KEY } from '../decorators/public.decorator.js';
import type { AuthenticatedRequest } from '../interfaces/authenticated-request.interface.js';
import type { AccessTokenClaims } from '../interfaces/jwt-payload.interface.js';
import { RevokedTokenStore } from '../revoked-token.store.js';

@Injectable()
export class JwtAuthGuard implements CanActivate {
  private readonly logger = new Logger(JwtAuthGuard.name);

  constructor(
    private readonly jwtService: JwtService,
    private readonly reflector: Reflector,
    private readonly revokedTokenStore: RevokedTokenStore,
  ) {}

  async canActivate(context: ExecutionContext): Promise<boolean> {
    if (context.getType() !== 'http') {
      return true;
    }

    const isPublic = this.reflector.getAllAndOverride<boolean>(IS_PUBLIC_KEY, [
      context.getHandler(),
      context.getClass(),
    ]);

    if (isPublic) {
      return true;
    }

    const request = context.switchToHttp().getRequest<AuthenticatedRequest>();
    const claims = await this.verify(this.extractBearerToken(request));

    if (await this.revokedTokenStore.isRevoked(claims)) {
      // A revoked token being reused can mean it was stolen.
      this.logger.warn(
        `Revoked access token used: user ${claims.sub}, token ${claims.jti}`,
      );
      throw new UnauthorizedException('Access token has been revoked');
    }

    request.user = claims;
    return true;
  }

  private async verify(token: string | undefined): Promise<AccessTokenClaims> {
    if (!token) {
      throw new UnauthorizedException('Missing access token');
    }

    let claims: AccessTokenClaims;
    try {
      claims = await this.jwtService.verifyAsync<AccessTokenClaims>(token);
    } catch {
      throw new UnauthorizedException('Invalid or expired access token');
    }

    if (!claims.jti || typeof claims.iat !== 'number') {
      throw new UnauthorizedException('Invalid or expired access token');
    }

    return claims;
  }

  private extractBearerToken(request: Request): string | undefined {
    const [scheme, token] = request.headers.authorization?.split(' ') ?? [];

    return scheme?.toLowerCase() === 'bearer' ? token : undefined;
  }
}
