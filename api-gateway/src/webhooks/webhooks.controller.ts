import {
  Body,
  Controller,
  HttpCode,
  HttpStatus,
  Post,
  UseGuards,
} from '@nestjs/common';
import {
  ApiAcceptedResponse,
  ApiOperation,
  ApiSecurity,
  ApiTags,
} from '@nestjs/swagger';
import { Public } from '../auth/decorators/public.decorator.js';
import { ApiErrorResponses } from '../common/swagger/api-error-responses.decorator.js';
import { CatalogWebhookDto } from './dto/catalog-webhook.dto.js';
import { CatalogWebhookResultDto } from './dto/catalog-webhook-result.dto.js';
import { ApiKeyGuard } from './guards/api-key.guard.js';
import { CatalogSyncService } from './services/catalog-sync.service.js';

@ApiTags('Internal')
@ApiSecurity('api-key')
@Public()
@UseGuards(ApiKeyGuard)
@Controller('internal/webhooks')
export class WebhooksController {
  constructor(private readonly catalogSyncService: CatalogSyncService) {}

  @Post('catalog')
  @HttpCode(HttpStatus.ACCEPTED)
  @ApiOperation({
    summary: 'Catalogue changed in the admin service',
    description:
      'Invalidates the cached product responses. Redelivering the same eventId is harmless.',
  })
  @ApiAcceptedResponse({ type: CatalogWebhookResultDto })
  @ApiErrorResponses(HttpStatus.BAD_REQUEST, HttpStatus.UNAUTHORIZED)
  async catalogChanged(
    @Body() event: CatalogWebhookDto,
  ): Promise<CatalogWebhookResultDto> {
    return { status: await this.catalogSyncService.handle(event) };
  }
}
