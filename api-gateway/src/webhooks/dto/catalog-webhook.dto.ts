import { ApiProperty } from '@nestjs/swagger';
import { IsEnum, IsISO8601, IsUUID } from 'class-validator';
import { CatalogEventType } from '../enums/catalog-event-type.enum.js';

export class CatalogWebhookDto {
  @ApiProperty({
    description: 'Unique per event; a redelivered event is recognised by it',
    format: 'uuid',
  })
  @IsUUID()
  eventId: string;

  @ApiProperty({ enum: CatalogEventType })
  @IsEnum(CatalogEventType)
  type: CatalogEventType;

  @ApiProperty({ example: '2026-10-23T12:00:00.000Z' })
  @IsISO8601()
  occurredAt: string;
}
