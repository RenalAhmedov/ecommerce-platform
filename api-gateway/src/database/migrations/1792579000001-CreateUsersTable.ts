import type { MigrationInterface, QueryRunner } from 'typeorm';

export class CreateUsersTable1792579000001 implements MigrationInterface {
  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`
      CREATE TABLE users (
        id                BIGINT GENERATED ALWAYS AS IDENTITY,
        name              VARCHAR(255) NOT NULL,
        email             VARCHAR(255) NOT NULL,
        password          VARCHAR(255) NOT NULL,
        role              VARCHAR(20)  NOT NULL DEFAULT 'customer',
        email_verified_at TIMESTAMPTZ,
        remember_token    VARCHAR(100),
        created_at        TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
        updated_at        TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
        deleted_at        TIMESTAMPTZ,
        CONSTRAINT users_pkey PRIMARY KEY (id),
        CONSTRAINT users_role_check CHECK (role IN ('customer', 'admin'))
      )
    `);  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`DROP TABLE users`);
  }
}
