import { Injectable, Logger } from '@nestjs/common';
import { MailService } from '../../mail/mail.service.js';
import { renderOrderConfirmation } from '../mail/order-confirmation.mail.js';
import { OrdersRepository } from '../orders.repository.js';

@Injectable()
export class OrderConfirmationService {
  private readonly logger = new Logger(OrderConfirmationService.name);

  constructor(
    private readonly ordersRepository: OrdersRepository,
    private readonly mailService: MailService,
  ) {}

  // SMTP errors escape, so the queue retries the job.
  async send(orderId: number): Promise<void> {
    const order = await this.ordersRepository.findForConfirmation(orderId);

    if (!order) {
      this.logger.warn(
        `Order ${orderId} no longer exists, skipping its confirmation email`,
      );
      return;
    }

    await this.mailService.send({
      to: order.user.email,
      ...renderOrderConfirmation(order),
    });
    this.logger.log(`Confirmation email sent for order ${order.orderNumber}`);
  }
}
