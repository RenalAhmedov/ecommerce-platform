import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { type EntityManager, Repository } from 'typeorm';
import {
  acquireAdvisoryLocks,
  advisoryLockKeys,
} from '../database/advisory-locks.js';
import { User } from './entities/user.entity.js';

@Injectable()
export class UsersRepository {
  constructor(
    @InjectRepository(User)
    private readonly repository: Repository<User>,
  ) {}

  withManager(manager: EntityManager): UsersRepository {
    return new UsersRepository(manager.getRepository(User));
  }

  transaction<T>(work: (users: UsersRepository) => Promise<T>): Promise<T> {
    return this.repository.manager.transaction((manager) =>
      work(this.withManager(manager)),
    );
  }

  lockEmail(email: string): Promise<void> {
    return acquireAdvisoryLocks(this.repository, [
      advisoryLockKeys.userEmail(email),
    ]);
  }

  findById(id: number): Promise<User | null> {
    return this.repository.findOneBy({ id });
  }

  findByEmailWithPassword(email: string): Promise<User | null> {
    return this.repository
      .createQueryBuilder('user')
      .addSelect('user.password')
      .where('LOWER(user.email) = LOWER(:email)', { email })
      .getOne();
  }

  existsByEmail(email: string): Promise<boolean> {
    return this.repository
      .createQueryBuilder('user')
      .withDeleted()
      .where('LOWER(user.email) = LOWER(:email)', { email })
      .getExists();
  }

  create(
    data: Pick<User, 'name' | 'email' | 'password' | 'role'>,
  ): Promise<User> {
    return this.repository.save(this.repository.create(data));
  }
}
