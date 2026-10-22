import { BadRequestException } from '@nestjs/common';
import { ParseIdPipe } from '../../pipes/parse-id.pipe.js';

describe('ParseIdPipe', () => {
  const pipe = new ParseIdPipe();

  it('turns a numeric string into a number', () => {
    expect(pipe.transform('42')).toBe(42);
  });

  it.each(['0', '-1', '1.5', 'abc', '', '99999999999999999999'])(
    'rejects "%s"',
    (value) => {
      expect(() => pipe.transform(value)).toThrow(
        new BadRequestException('id must be a positive integer'),
      );
    },
  );
});
