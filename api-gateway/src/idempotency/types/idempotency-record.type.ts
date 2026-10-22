export type IdempotencyRecord =
  | { state: 'processing'; fingerprint: string }
  | { state: 'succeeded'; fingerprint: string; body: unknown }
  | {
      state: 'failed';
      fingerprint: string;
      statusCode: number;
      response: string | object;
    };

export type CompletedIdempotencyRecord = Exclude<
  IdempotencyRecord,
  { state: 'processing' }
>;
