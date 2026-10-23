import { describeError } from '../../utils/describe-error.js';

describe('describeError', () => {
  it('prefers the error code, which is what tells connection errors apart', () => {
    const error = Object.assign(new Error('connect failed'), {
      code: 'ECONNREFUSED',
    });

    expect(describeError(error)).toBe('ECONNREFUSED');
  });

  it('falls back to the message, then the name', () => {
    expect(describeError(new Error('Command timed out'))).toBe(
      'Command timed out',
    );
    expect(describeError(new TypeError())).toBe('TypeError');
  });

  it('describes values that are not errors', () => {
    expect(describeError('boom')).toBe('boom');
  });
});
