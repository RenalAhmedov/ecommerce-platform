import type { UserRole } from '../../users/enums/user-role.enum.js';

export interface JwtPayload {
  sub: number;
  email: string;
  role: UserRole;
  jti: string;
}

export interface AccessTokenClaims extends JwtPayload {
  iat: number;
  exp: number;
}
