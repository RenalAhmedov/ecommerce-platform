import { GraphQLScalarType, Kind } from 'graphql';

// The built-in DateTime turns strings into null, and responses served from the Redis cache carry their dates as strings.
export const DateTimeScalar = new GraphQLScalarType<Date | null, string>({
  name: 'DateTime',
  description: 'A UTC date-time string, such as 2026-10-24T09:54:33.000Z.',
  serialize(value) {
    const date = value instanceof Date ? value : new Date(String(value));

    if (Number.isNaN(date.getTime())) {
      throw new TypeError(`DateTime cannot represent ${String(value)}`);
    }

    return date.toISOString();
  },
  parseValue(value) {
    return new Date(String(value));
  },
  parseLiteral(ast) {
    return ast.kind === Kind.STRING ? new Date(ast.value) : null;
  },
});
