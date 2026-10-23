import { Inject, Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type { Transporter } from 'nodemailer';
import type { EnvironmentVariables } from '../config/interfaces/environment-variables.interface.js';
import { MAIL_TRANSPORT } from './constants/mail.constants.js';
import type { MailMessage } from './interfaces/mail-message.interface.js';

@Injectable()
export class MailService {
  private readonly from: string;

  constructor(
    @Inject(MAIL_TRANSPORT) private readonly transport: Transporter,
    config: ConfigService<EnvironmentVariables, true>,
  ) {
    this.from = config.get('MAIL_FROM');
  }

  async send(message: MailMessage): Promise<void> {
    await this.transport.sendMail({ from: this.from, ...message });
  }
}
