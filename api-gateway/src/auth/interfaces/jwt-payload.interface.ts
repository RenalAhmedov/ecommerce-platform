import type { UserRole } from '../../users/enums/user-role.enum.js';

export interface JwtPayload {
  sub: number;
  email: string;
  role: UserRole;
}
