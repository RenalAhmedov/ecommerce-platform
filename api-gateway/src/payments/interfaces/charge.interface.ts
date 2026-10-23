export interface ChargeRequest {
  reference: string;
  amount: string;
  token: string;
}

export type ChargeResult =
  | { status: 'succeeded'; transactionId: string }
  | { status: 'declined'; reason: string };
