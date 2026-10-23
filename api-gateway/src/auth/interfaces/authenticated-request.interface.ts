import type { Request } from 'express';
import type { AccessTokenClaims } from './jwt-payload.interface.js';

export interface AuthenticatedRequest extends Request {
  user: AccessTokenClaims;
}
