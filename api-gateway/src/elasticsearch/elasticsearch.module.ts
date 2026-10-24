import { Client } from '@elastic/elasticsearch';
import {
  Global,
  Inject,
  Module,
  type OnApplicationShutdown,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type { EnvironmentVariables } from '../config/interfaces/environment-variables.interface.js';
import { ELASTICSEARCH_CLIENT } from './elasticsearch.constants.js';

@Global()
@Module({
  providers: [
    {
      provide: ELASTICSEARCH_CLIENT,
      inject: [ConfigService],
      useFactory: (config: ConfigService<EnvironmentVariables, true>) => {
        const node = config.get('ELASTICSEARCH_URL');

        return node
          ? new Client({ node, requestTimeout: 2_000, maxRetries: 1 })
          : null;
      },
    },
  ],
  exports: [ELASTICSEARCH_CLIENT],
})
export class ElasticsearchModule implements OnApplicationShutdown {
  constructor(
    @Inject(ELASTICSEARCH_CLIENT) private readonly client: Client | null,
  ) {}

  async onApplicationShutdown(): Promise<void> {
    await this.client?.close();
  }
}
