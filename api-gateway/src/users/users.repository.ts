import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { User } from './entities/user.entity.js';

@Injectable()
export class UsersRepository {
  constructor(
    @InjectRepository(User)
    private readonly repository: Repository<User>,
  ) {}

  transaction<T>(work: (users: UsersRepository) => Promise<T>): Promise<T> {
    return this.repository.manager.transaction((manager) =>
      work(new UsersRepository(manager.getRepository(User))),
    );
  }

  async lockEmail(email: string): Promise<void> {
    await this.repository.query('SELECT pg_advisory_xact_lock(hashtext($1))', [
      `users.email:${email.toLowerCase()}`,
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
      .where('LOWER(user.email) = LOWER(:email)', { email })
      .getExists();
  }

  create(
    data: Pick<User, 'name' | 'email' | 'password' | 'role'>,
  ): Promise<User> {
    return this.repository.save(this.repository.create(data));
  }
}
