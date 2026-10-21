import type { User } from '../entities/user.entity.js';
import type { UserRole } from '../enums/user-role.enum.js';

export class UserResponseDto {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  createdAt: Date;

  static fromEntity(user: User): UserResponseDto {
    return {
      id: user.id,
      name: user.name,
      email: user.email,
      role: user.role,
      createdAt: user.createdAt,
    };
  }
}
