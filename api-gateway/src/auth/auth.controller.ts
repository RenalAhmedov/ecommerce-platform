import {
  Body,
  Controller,
  Get,
  HttpCode,
  HttpStatus,
  Post,
  Req,
} from '@nestjs/common';
import {
  ApiBearerAuth,
  ApiNoContentResponse,
  ApiOperation,
  ApiTags,
} from '@nestjs/swagger';
import { Throttle } from '@nestjs/throttler';
import { ApiErrorResponses } from '../common/swagger/api-error-responses.decorator.js';
import { RATE_LIMITS } from '../rate-limit/constants/rate-limit.constants.js';
import type { UserResponseDto } from '../users/dto/user-response.dto.js';
import { AuthService } from './auth.service.js';
import { Public } from './decorators/public.decorator.js';
import type { AuthResponseDto } from './dto/auth-response.dto.js';
import { LoginDto } from './dto/login.dto.js';
import { RegisterDto } from './dto/register.dto.js';
import type { AuthenticatedRequest } from './interfaces/authenticated-request.interface.js';

@ApiTags('Auth')
@Controller('auth')
export class AuthController {
  constructor(private readonly authService: AuthService) {}

  @Public()
  @Post('register')
  @Throttle({ default: RATE_LIMITS.register })
  @ApiOperation({ summary: 'Register a customer account' })
  @ApiErrorResponses(
    HttpStatus.BAD_REQUEST,
    HttpStatus.CONFLICT,
    HttpStatus.TOO_MANY_REQUESTS,
  )
  register(@Body() dto: RegisterDto): Promise<AuthResponseDto> {
    return this.authService.register(dto);
  }

  @Public()
  @Post('login')
  @HttpCode(HttpStatus.OK)
  @Throttle({ default: RATE_LIMITS.login })
  @ApiOperation({ summary: 'Log in and receive a JWT access token' })
  @ApiErrorResponses(
    HttpStatus.BAD_REQUEST,
    HttpStatus.UNAUTHORIZED,
    HttpStatus.TOO_MANY_REQUESTS,
  )
  login(@Body() dto: LoginDto): Promise<AuthResponseDto> {
    return this.authService.login(dto);
  }

  @Post('logout')
  @HttpCode(HttpStatus.NO_CONTENT)
  @ApiBearerAuth()
  @ApiOperation({
    summary: 'Log out by revoking the current access token',
    description:
      'The token is rejected from now on, in this service and (via the shared Redis denylist) in the admin panel.',
  })
  @ApiNoContentResponse({ description: 'Token revoked' })
  @ApiErrorResponses(HttpStatus.UNAUTHORIZED, HttpStatus.SERVICE_UNAVAILABLE)
  logout(@Req() req: AuthenticatedRequest): Promise<void> {
    return this.authService.logout(req.user);
  }

  @Get('me')
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Get the profile of the logged-in user' })
  @ApiErrorResponses(HttpStatus.UNAUTHORIZED)
  me(@Req() req: AuthenticatedRequest): Promise<UserResponseDto> {
    return this.authService.getProfile(req.user.sub);
  }
}
