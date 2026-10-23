export interface OrderPaidEvent {
  orderId: number;
  orderNumber: string;
  userId: number;
  total: string;
  paymentReference: string;
}
