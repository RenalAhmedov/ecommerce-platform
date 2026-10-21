import type { MigrationInterface, QueryRunner } from 'typeorm';

export class CreateOrdersTable1792579000005 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      CREATE TABLE orders (
        id                BIGINT GENERATED ALWAYS AS IDENTITY,
        order_number      VARCHAR(32)   NOT NULL,
        user_id           BIGINT        NOT NULL,
        status            VARCHAR(20)   NOT NULL DEFAULT 'pending',
        subtotal          NUMERIC(12,2) NOT NULL,
        total             NUMERIC(12,2) NOT NULL,
        payment_reference VARCHAR(100),
        shipping_address  JSONB,
        paid_at           TIMESTAMPTZ,
        created_at        TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
        updated_at        TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
        CONSTRAINT orders_pkey PRIMARY KEY (id),
        CONSTRAINT orders_order_number_unique UNIQUE (order_number),
        CONSTRAINT orders_user_id_foreign FOREIGN KEY (user_id)
          REFERENCES users (id) ON DELETE RESTRICT,
        CONSTRAINT orders_status_check CHECK (status IN ('pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'payment_failed')),
        CONSTRAINT orders_subtotal_check CHECK (subtotal >= 0),
        CONSTRAINT orders_total_check CHECK (total >= 0)
      )
    `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`DROP TABLE orders`);
  }
}
