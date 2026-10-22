import {
  ServiceUnavailableException,
  UnauthorizedException,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { JwtService } from '@nestjs/jwt';
import { Test } from '@nestjs/testing';
import bcrypt from 'bcryptjs';
import type { User } from '../../users/entities/user.entity.js';
import { UserRole } from '../../users/enums/user-role.enum.js';
import { UsersService } from '../../users/users.service.js';
import { AuthService } from '../auth.service.js';
import { RevokedTokenStore } from '../revoked-token.store.js';

describe('AuthService', () => {
  let service: AuthService;
  let usersService: {
    createCustomer: ReturnType<typeof vi.fn>;
    findByEmailWithPassword: ReturnType<typeof vi.fn>;
    getById: ReturnType<typeof vi.fn>;
  };
  let jwtService: { signAsync: ReturnType<typeof vi.fn> };
  let revokedTokenStore: { revoke: ReturnType<typeof vi.fn> };
  let storedUser: User;

  beforeAll(async () => {
    storedUser = {
      id: 7,
      name: 'Ada',
      email: 'ada@example.test',
      password: await bcrypt.hash('password123', 4),
      role: UserRole.Customer,
      createdAt: new Date('2026-01-01T00:00:00Z'),
    } as User;
  });

  beforeEach(async () => {
    usersService = {
      createCustomer: vi.fn((data: Partial<User>) =>
        Promise.resolve(Object.assign({}, storedUser, data)),
      ),
      findByEmailWithPassword: vi.fn().mockResolvedValue(storedUser),
      getById: vi.fn().mockResolvedValue(storedUser),
    };
    jwtService = { signAsync: vi.fn().mockResolvedValue('signed.jwt.token') };
    revokedTokenStore = { revoke: vi.fn() };

    const moduleRef = await Test.createTestingModule({
      providers: [
        AuthService,
        { provide: UsersService, useValue: usersService },
        { provide: JwtService, useValue: jwtService },
        { provide: RevokedTokenStore, useValue: revokedTokenStore },
        { provide: ConfigService, useValue: { get: () => 3600 } },
      ],
    }).compile();

    service = moduleRef.get(AuthService);
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('register', () => {
    it('stores a bcrypt hash, never the plain password', async () => {
      await service.register({
        name: 'Ada',
        email: 'ada@example.test',
        password: 'password123',
      });

      const [{ password }] = usersService.createCustomer.mock.calls[0] as [
        { password: string },
      ];
      expect(password).not.toBe('password123');
      expect(password).toMatch(/^\$2[aby]\$12\$/);
      await expect(bcrypt.compare('password123', password)).resolves.toBe(true);
    });

    it('returns a bearer token and the profile without the password', async () => {
      const response = await service.register({
        name: 'Ada',
        email: 'ada@example.test',
        password: 'password123',
      });

      expect(response).toMatchObject({
        accessToken: 'signed.jwt.token',
        tokenType: 'Bearer',
        expiresIn: 3600,
        user: { id: 7, email: 'ada@example.test', role: 'customer' },
      });
      expect(response.user).not.toHaveProperty('password');
    });
  });

  describe('login', () => {
    it('signs a token with the user id, email, role and a unique token id', async () => {
      const credentials = {
        email: 'ada@example.test',
        password: 'password123',
      };

      await service.login(credentials);
      await service.login(credentials);

      const [[first], [second]] = jwtService.signAsync.mock.calls as [
        [{ jti: string }],
        [{ jti: string }],
      ];
      expect(first).toEqual({
        sub: 7,
        email: 'ada@example.test',
        role: UserRole.Customer,
        jti: expect.stringMatching(/^[0-9a-f-]{36}$/),
      });
      expect(first.jti).not.toBe(second.jti);
    });

    it('rejects a wrong password', async () => {
      await expect(
        service.login({ email: 'ada@example.test', password: 'wrong-pass' }),
      ).rejects.toThrow(UnauthorizedException);
      expect(jwtService.signAsync).not.toHaveBeenCalled();
    });

    it('rejects an unknown email after still running bcrypt (no timing leak)', async () => {
      usersService.findByEmailWithPassword.mockResolvedValue(null);
      const compare = vi.spyOn(bcrypt, 'compare');

      await expect(
        service.login({ email: 'nobody@example.test', password: 'x' }),
      ).rejects.toThrow(new UnauthorizedException('Invalid credentials'));
      expect(compare).toHaveBeenCalledTimes(1);
    });
  });

  describe('logout', () => {
    const claims = {
      sub: 7,
      email: 'ada@example.test',
      role: UserRole.Customer,
      jti: 'token-1',
      iat: 1_700_000_000,
      exp: 1_700_003_600,
    };

    it('revokes the token id until the token expires', async () => {
      await service.logout(claims);

      expect(revokedTokenStore.revoke).toHaveBeenCalledWith(
        'token-1',
        1_700_003_600,
      );
    });

    it('answers 503 instead of pretending to log out when Redis is down', async () => {
      revokedTokenStore.revoke.mockRejectedValue(new Error('ECONNREFUSED'));

      await expect(service.logout(claims)).rejects.toThrow(
        ServiceUnavailableException,
      );
    });
  });

  it('returns the profile for the token subject', async () => {
    await expect(service.getProfile(7)).resolves.toEqual({
      id: 7,
      name: 'Ada',
      email: 'ada@example.test',
      role: UserRole.Customer,
      createdAt: storedUser.createdAt,
    });
    expect(usersService.getById).toHaveBeenCalledWith(7);
  });
});
