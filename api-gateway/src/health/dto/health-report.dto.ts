import { ApiProperty } from '@nestjs/swagger';

export type DependencyStatus = 'up' | 'down';

class HealthChecksDto {
  @ApiProperty({ enum: ['up', 'down'] })
  database: DependencyStatus;

  @ApiProperty({ enum: ['up', 'down'] })
  redis: DependencyStatus;

  @ApiProperty({ enum: ['up', 'down', 'disabled'] })
  elasticsearch: DependencyStatus | 'disabled';
}

export class HealthReportDto {
  @ApiProperty({
    enum: ['ok', 'degraded', 'down'],
    description:
      '`degraded`: Redis or Elasticsearch is down, which every feature survives.',
  })
  status: 'ok' | 'degraded' | 'down';

  @ApiProperty({ type: HealthChecksDto })
  checks: HealthChecksDto;
}
