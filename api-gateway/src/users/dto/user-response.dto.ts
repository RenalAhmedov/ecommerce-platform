import { Field, Int, ObjectType } from '@nestjs/graphql';
import type { User } from '../entities/user.entity.js';
import { UserRole } from '../enums/user-role.enum.js';

@ObjectType('User')
export class UserResponseDto {
  @Field(() => Int)
  id: number;

  @Field()
  name: string;

  @Field()
  email: string;

  @Field(() => UserRole)
  role: UserRole;

  @Field()
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
