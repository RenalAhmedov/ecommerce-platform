import { ConflictException, NotFoundException } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import { UserRole } from '../enums/user-role.enum.js';
import { UsersRepository } from '../users.repository.js';
import { UsersService } from '../users.service.js';

describe('UsersService', () => {
  let service: UsersService;
  let transactionUsers: {
    lockEmail: ReturnType<typeof vi.fn>;
    existsByEmail: ReturnType<typeof vi.fn>;
    create: ReturnType<typeof vi.fn>;
  };
  let repository: {
    transaction: ReturnType<typeof vi.fn>;
    findById: ReturnType<typeof vi.fn>;
  };

  const data = {
    name: 'Ada',
    email: 'ada@example.test',
    password: '$2b$12$hash',
  };

  beforeEach(async () => {
    transactionUsers = {
      lockEmail: vi.fn(),
      existsByEmail: vi.fn().mockResolvedValue(false),
      create: vi.fn().mockResolvedValue({ id: 1 }),
    };
    repository = {
      transaction: vi.fn((work: (users: unknown) => Promise<unknown>) =>
        work(transactionUsers),
      ),
      findById: vi.fn(),
    };

    const moduleRef = await Test.createTestingModule({
      providers: [
        UsersService,
        { provide: UsersRepository, useValue: repository },
      ],
    }).compile();

    service = moduleRef.get(UsersService);
  });

  describe('createCustomer', () => {
    it('locks the email before checking it, inside one transaction', async () => {
      await service.createCustomer(data);

      expect(transactionUsers.lockEmail).toHaveBeenCalledWith(
        'ada@example.test',
      );
      expect(
        transactionUsers.lockEmail.mock.invocationCallOrder[0],
      ).toBeLessThan(
        transactionUsers.existsByEmail.mock.invocationCallOrder[0],
      );
    });

    it('always creates the account as a customer', async () => {
      await service.createCustomer(data);

      expect(transactionUsers.create).toHaveBeenCalledWith({
        ...data,
        role: UserRole.Customer,
      });
    });

    it('rejects an email that is already registered', async () => {
      transactionUsers.existsByEmail.mockResolvedValue(true);

      await expect(service.createCustomer(data)).rejects.toThrow(
        ConflictException,
      );
      expect(transactionUsers.create).not.toHaveBeenCalled();
    });
  });

  describe('getById', () => {
    it('returns the user', async () => {
      repository.findById.mockResolvedValue({ id: 7 });

      await expect(service.getById(7)).resolves.toEqual({ id: 7 });
    });

    it('throws 404 for a missing user', async () => {
      repository.findById.mockResolvedValue(null);

      await expect(service.getById(7)).rejects.toThrow(NotFoundException);
    });
  });
});
