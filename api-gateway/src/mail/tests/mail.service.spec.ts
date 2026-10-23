import type { ConfigService } from '@nestjs/config';
import type { Transporter } from 'nodemailer';
import type { EnvironmentVariables } from '../../config/interfaces/environment-variables.interface.js';
import { MailService } from '../mail.service.js';

describe('MailService', () => {
  it('sends from the configured sender address', async () => {
    const transport = { sendMail: vi.fn().mockResolvedValue({}) };
    const service = new MailService(
      transport as unknown as Transporter,
      {
        get: () => 'Shop <no-reply@shop.local>',
      } as unknown as ConfigService<EnvironmentVariables, true>,
    );

    await service.send({
      to: 'ada@example.test',
      subject: 'Hello',
      text: 'Hi',
      html: '<p>Hi</p>',
    });

    expect(transport.sendMail).toHaveBeenCalledWith({
      from: 'Shop <no-reply@shop.local>',
      to: 'ada@example.test',
      subject: 'Hello',
      text: 'Hi',
      html: '<p>Hi</p>',
    });
  });
});
