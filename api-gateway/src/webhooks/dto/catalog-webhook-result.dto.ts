import { ApiProperty } from '@nestjs/swagger';
import type { CatalogSyncResult } from '../services/catalog-sync.service.js';

export class CatalogWebhookResultDto {
  @ApiProperty({
    enum: ['processed', 'duplicate'],
    description:
      '`duplicate`: this eventId was already handled, nothing was done again.',
  })
  status: CatalogSyncResult;
}
