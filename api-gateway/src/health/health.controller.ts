import {
  Controller,
  Get,
  HttpStatus,
  ServiceUnavailableException,
} from '@nestjs/common';
import { ApiOkResponse, ApiOperation, ApiTags } from '@nestjs/swagger';
import { ApiErrorResponses } from '../common/swagger/api-error-responses.decorator.js';
import { SkipThrottle } from '@nestjs/throttler';
import { Public } from '../auth/decorators/public.decorator.js';
import { HealthService } from './health.service.js';
import { HealthReportDto } from './dto/health-report.dto.js';

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
  @ApiOkResponse({ type: HealthReportDto })
  @ApiErrorResponses(HttpStatus.SERVICE_UNAVAILABLE)
  async check(): Promise<HealthReportDto> {
    const report = await this.healthService.check();

    if (report.status === 'down') {
      throw new ServiceUnavailableException('Database unavailable');
    }

    return report;
  }
}
