import type { ApolloDriverConfig } from '@nestjs/apollo';
import { ApolloDriver } from '@nestjs/apollo';
import type { ConfigService } from '@nestjs/config';
import type { Request, Response } from 'express';
import { join } from 'node:path';
import type { EnvironmentVariables } from '../config/interfaces/environment-variables.interface.js';
import { DateTimeScalar } from './date-time.scalar.js';

export function createGraphqlOptions(
  config: ConfigService<EnvironmentVariables, true>,
): ApolloDriverConfig {
  const isProduction = config.get('NODE_ENV') === 'production';

  return {
    driver: ApolloDriver,
    path: '/graphql',
    useGlobalPrefix: true,
    // Generated from the decorated classes. Outside production it is also written
    // to the repository, so the API's shape (and every change to it) can be reviewed.
    autoSchemaFile: isProduction
      ? true
      : join(process.cwd(), 'src/graphql/schema.gql'),
    sortSchema: true,
    buildSchemaOptions: {
      scalarsMap: [{ type: Date, scalar: DateTimeScalar }],
    },
    introspection: !isProduction,
    playground: false,
    context: ({ req, res }: { req: Request; res: Response }) => ({ req, res }),
  };
}
