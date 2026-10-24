import { Context, Query, Resolver } from '@nestjs/graphql';
import { UserResponseDto } from '../users/dto/user-response.dto.js';
import { AuthService } from './auth.service.js';
import type { AuthenticatedRequest } from './interfaces/authenticated-request.interface.js';

@Resolver(() => UserResponseDto)
export class AuthResolver {
  constructor(private readonly authService: AuthService) {}

  @Query(() => UserResponseDto, { description: 'The logged-in user.' })
  me(@Context('req') req: AuthenticatedRequest): Promise<UserResponseDto> {
    return this.authService.getProfile(req.user.sub);
  }
}
