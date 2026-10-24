import {
  Injectable,
  Logger,
  ServiceUnavailableException,
  UnauthorizedException,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { JwtService } from '@nestjs/jwt';
import bcrypt from 'bcryptjs';
import { randomUUID } from 'node:crypto';
import { describeError } from '../common/utils/describe-error.js';
import type { EnvironmentVariables } from '../config/interfaces/environment-variables.interface.js';
import { UserResponseDto } from '../users/dto/user-response.dto.js';
import type { User } from '../users/entities/user.entity.js';
import { UsersService } from '../users/users.service.js';
import { UNKNOWN_USER_PASSWORD_HASH } from './constants/auth.constants.js';
import type { AuthResponseDto } from './dto/auth-response.dto.js';
import type { LoginDto } from './dto/login.dto.js';
import type { RegisterDto } from './dto/register.dto.js';
import type {
  AccessTokenClaims,
  JwtPayload,
} from './interfaces/jwt-payload.interface.js';
import { RevokedTokenStore } from './revoked-token.store.js';
import { hashPassword } from './utils/password.js';

@Injectable()
export class AuthService {
  private readonly logger = new Logger(AuthService.name);

  constructor(
    private readonly usersService: UsersService,
    private readonly jwtService: JwtService,
    private readonly revokedTokenStore: RevokedTokenStore,
    private readonly config: ConfigService<EnvironmentVariables, true>,
  ) {}

  async register(dto: RegisterDto): Promise<AuthResponseDto> {
    const user = await this.usersService.createCustomer({
      name: dto.name,
      email: dto.email,
      password: await hashPassword(dto.password),
    });
    this.logger.log(`Customer registered: user ${user.id}`);

    return this.buildAuthResponse(user);
  }

  async login(dto: LoginDto): Promise<AuthResponseDto> {
    const user = await this.usersService.findByEmailWithPassword(dto.email);
    const passwordMatches = await bcrypt.compare(
      dto.password,
      user?.password ?? UNKNOWN_USER_PASSWORD_HASH,
    );

    if (!user || !passwordMatches) {
      this.logger.warn(`Login failed for ${dto.email}`);
      throw new UnauthorizedException('Invalid credentials');
    }

    this.logger.log(`User ${user.id} logged in`);

    return this.buildAuthResponse(user);
  }

  async logout({ sub, jti, exp }: AccessTokenClaims): Promise<void> {
    try {
      await this.revokedTokenStore.revoke(jti, exp);
    } catch (error) {
      this.logger.error(
        `Logout of user ${sub} failed, Redis unavailable (${describeError(error)})`,
      );
      throw new ServiceUnavailableException(
        'Could not log out right now, please retry',
        { cause: error },
      );
    }

    this.logger.log(`User ${sub} logged out (token ${jti} revoked)`);
  }

  async getProfile(userId: number): Promise<UserResponseDto> {
    const user = await this.usersService.getById(userId);

    return UserResponseDto.fromEntity(user);
  }

  private async buildAuthResponse(user: User): Promise<AuthResponseDto> {
    const payload: JwtPayload = {
      sub: user.id,
      email: user.email,
      role: user.role,
      jti: randomUUID(),
    };

    return {
      accessToken: await this.jwtService.signAsync(payload),
      tokenType: 'Bearer',
      expiresIn: this.config.get('JWT_EXPIRES_IN'),
      user: UserResponseDto.fromEntity(user),
    };
  }
}
