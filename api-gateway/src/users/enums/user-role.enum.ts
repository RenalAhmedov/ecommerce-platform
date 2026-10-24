import { registerEnumType } from '@nestjs/graphql';

export enum UserRole {
  Customer = 'customer',
  Admin = 'admin',
}

registerEnumType(UserRole, { name: 'UserRole' });
