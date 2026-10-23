import { escapeLikePattern } from '../../utils/escape-like-pattern.js';

describe('escapeLikePattern', () => {
  it('makes LIKE wildcards and the escape character match literally', () => {
    expect(escapeLikePattern('50%_off\\')).toBe('50\\%\\_off\\\\');
  });

  it('leaves ordinary text alone', () => {
    expect(escapeLikePattern('Desk lamp')).toBe('Desk lamp');
  });
});
