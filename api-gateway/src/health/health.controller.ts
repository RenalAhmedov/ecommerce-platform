import { Controller, Get, ServiceUnavailableException } from '@nestjs/common';
import { ApiOperation, ApiTags } from '@nestjs/swagger';
import { SkipThrottle } from '@nestjs/throttler';
import { Public } from '../auth/decorators/public.decorator.js';
import { HealthService } from './health.service.js';
import type { HealthReport } from './interfaces/health-report.interface.js';

@ApiTags('Health')
@Public()
@SkipThrottle()
@Controller('health')
export class HealthController {
  constructor(private readonly healthService: HealthService) {}

  @Get()
  @ApiOperation({
    summary: 'Liveness and readiness check for Docker and Nginx',
    description:
      '200 while the database is reachable ("degraded" if only Redis is down), otherwise 503.',
  })
  async check(): Promise<HealthReport> {
    const report = await this.healthService.check();

    if (report.status === 'down') {
      throw new ServiceUnavailableException('Database unavailable');
    }

    return report;
  }
}
