import type { INestApplication } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import type { Redis } from 'ioredis';
import type { AddressInfo } from 'node:net';
import { setTimeout as sleep } from 'node:timers/promises';
import { DataSource } from 'typeorm';
import { AppModule } from '../../../app.module.js';
import { configureApp, connectEventListener } from '../../../app.setup.js';
import { REDIS_CLIENT } from '../../../cache/constants/cache.constants.js';
import { MAIL_TRANSPORT } from '../../../mail/constants/mail.constants.js';

const TABLES = [
  'order_items',
  'orders',
  'inventory_movements',
  'inventories',
  'products',
  'categories',
  'users',
];

export interface ApiResponse<T = Record<string, unknown>> {
  status: number;
  body: T;
  headers: Headers;
}

interface RequestOptions {
  body?: unknown;
  token?: string;
  headers?: Record<string, string>;
}

export class TestApp {
  private constructor(
    readonly app: INestApplication,
    private readonly baseUrl: string,
    readonly sendMail: ReturnType<typeof vi.fn>,
  ) {}

  static async create(): Promise<TestApp> {
    const sendMail = vi.fn().mockResolvedValue({});

    const moduleRef = await Test.createTestingModule({ imports: [AppModule] })
      .overrideProvider(MAIL_TRANSPORT)
      .useValue({ sendMail, close: vi.fn() })
      .compile();

    const app = moduleRef.createNestApplication({ logger: false });
    configureApp(app);
    connectEventListener(app);
    await app.startAllMicroservices();
    await app.listen(0);

    const { port } = app.getHttpServer().address() as AddressInfo;

    return new TestApp(app, `http://127.0.0.1:${port}/api`, sendMail);
  }

  get dataSource(): DataSource {
    return this.app.get(DataSource);
  }

  get redis(): Redis {
    return this.app.get<Redis>(REDIS_CLIENT);
  }

  async request<T = Record<string, unknown>>(
    method: string,
    path: string,
    { body, token, headers = {} }: RequestOptions = {},
  ): Promise<ApiResponse<T>> {
    const response = await fetch(this.baseUrl + path, {
      method,
      headers: {
        'content-type': 'application/json',
        ...(token && { authorization: `Bearer ${token}` }),
        ...headers,
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });

    return {
      status: response.status,
      body: (await response.json()) as T,
      headers: response.headers,
    };
  }

  async signUp(
    email: string,
    role: 'customer' | 'admin' = 'customer',
  ): Promise<{ id: number; token: string }> {
    const password = 'password123';
    await this.request('POST', '/auth/register', {
      body: { name: email.split('@')[0], email, password },
    });

    if (role === 'admin') {
      await this.query('UPDATE users SET role = $1 WHERE email = $2', [
        role,
        email,
      ]);
    }

    const { body } = await this.request<{
      accessToken: string;
      user: { id: number };
    }>('POST', '/auth/login', { body: { email, password } });

    return { id: body.user.id, token: body.accessToken };
  }

  query<T = Record<string, unknown>>(
    sql: string,
    parameters: unknown[] = [],
  ): Promise<T[]> {
    return this.dataSource.query<T[]>(sql, parameters);
  }

  async reset(): Promise<void> {
    await this.query(`TRUNCATE ${TABLES.join(', ')} RESTART IDENTITY CASCADE`);
    await this.redis.flushdb();
    this.sendMail.mockClear();
  }

  close(): Promise<void> {
    return this.app.close();
  }
}

export async function waitFor(
  condition: () => boolean | Promise<boolean>,
  timeoutMs = 5_000,
): Promise<void> {
  const deadline = Date.now() + timeoutMs;

  while (!(await condition())) {
    if (Date.now() > deadline) {
      throw new Error(`Condition not met within ${timeoutMs}ms`);
    }
    await sleep(50);
  }
}
