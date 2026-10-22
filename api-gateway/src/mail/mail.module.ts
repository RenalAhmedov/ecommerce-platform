import { Inject, Module, type OnApplicationShutdown } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import nodemailer, { type Transporter } from 'nodemailer';
import type { EnvironmentVariables } from '../config/interfaces/environment-variables.interface.js';
import { MAIL_TRANSPORT } from './constants/mail.constants.js';
import { MailService } from './mail.service.js';

@Module({
  providers: [
    {
      provide: MAIL_TRANSPORT,
      inject: [ConfigService],
      useFactory: (config: ConfigService<EnvironmentVariables, true>) =>
        nodemailer.createTransport({
          host: config.get('MAIL_HOST'),
          port: config.get('MAIL_PORT'),
          secure: false,
        }),
    },
    MailService,
  ],
  exports: [MailService],
})
export class MailModule implements OnApplicationShutdown {
  constructor(
    @Inject(MAIL_TRANSPORT) private readonly transport: Transporter,
  ) {}

  onApplicationShutdown(): void {
    this.transport.close();
  }
}
