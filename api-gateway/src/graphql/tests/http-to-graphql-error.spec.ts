import {
  BadRequestException,
  NotFoundException,
  ServiceUnavailableException,
} from '@nestjs/common';
import { httpToGraphqlError } from '../http-to-graphql-error.js';

describe('httpToGraphqlError', () => {
  it('keeps the message and gives a code clients can switch on', () => {
    const error = httpToGraphqlError(
      new NotFoundException('Product not found'),
    );

    expect(error.message).toBe('Product not found');
    expect(error.extensions).toEqual({
      code: 'NOT_FOUND',
      status: 404,
      details: ['Product not found'],
    });
  });

  it('lists every validation message', () => {
    const error = httpToGraphqlError(
      new BadRequestException([
        'limit must not be greater than 100',
        'page must be an integer number',
      ]),
    );

    expect(error.message).toBe(
      'limit must not be greater than 100; page must be an integer number',
    );
    expect(error.extensions.code).toBe('BAD_REQUEST');
  });

  it('falls back to a generic code for statuses without one', () => {
    expect(
      httpToGraphqlError(new ServiceUnavailableException('Down')).extensions
        .code,
    ).toBe('SERVICE_UNAVAILABLE');
  });
});
