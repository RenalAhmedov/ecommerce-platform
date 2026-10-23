import { SLUG_PATTERN, slugify } from '../../utils/slugify.js';

describe('slugify', () => {
  it.each([
    ['Mechanical Keyboard', 'mechanical-keyboard'],
    ['  Café Crème  ', 'cafe-creme'],
    ['USB-C -- Cable!!', 'usb-c-cable'],
    ['100% Cotton T-Shirt', '100-cotton-t-shirt'],
    ['***', ''],
  ])('turns "%s" into "%s"', (input, expected) => {
    expect(slugify(input)).toBe(expected);
  });

  it('never exceeds 255 characters or ends with a hyphen', () => {
    const slug = slugify(`${'a'.repeat(254)} b`);

    expect(slug.length).toBeLessThanOrEqual(255);
    expect(slug.endsWith('-')).toBe(false);
  });

  it('always produces values the slug pattern accepts', () => {
    expect(SLUG_PATTERN.test(slugify('Ünïcödé & Spaces'))).toBe(true);
  });
});
