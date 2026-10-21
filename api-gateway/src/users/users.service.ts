import {
  ConflictException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import type { User } from './entities/user.entity.js';
import { UserRole } from './enums/user-role.enum.js';
import { UsersRepository } from './users.repository.js';

@Injectable()
export class UsersService {
  constructor(private readonly usersRepository: UsersRepository) {}

  async getById(id: number): Promise<User> {
    const user = await this.usersRepository.findById(id);

    if (!user) {
      throw new NotFoundException('User not found');
    }

    return user;
  }

  findByEmailWithPassword(email: string): Promise<User | null> {
    return this.usersRepository.findByEmailWithPassword(email);
  }

  async createCustomer(
    data: Pick<User, 'name' | 'email' | 'password'>,
  ): Promise<User> {
    if (await this.usersRepository.existsByEmail(data.email)) {
      throw new ConflictException('Email is already registered');
    }

    return this.usersRepository.create({ ...data, role: UserRole.Customer });
  }
}
