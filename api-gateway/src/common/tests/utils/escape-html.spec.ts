import { escapeHtml } from '../../utils/escape-html.js';

describe('escapeHtml', () => {
  it('escapes every character that could open markup or an attribute', () => {
    expect(escapeHtml(`<a href="x" title='y'>Tom & Jerry</a>`)).toBe(
      '&lt;a href=&quot;x&quot; title=&#39;y&#39;&gt;Tom &amp; Jerry&lt;/a&gt;',
    );
  });
});
