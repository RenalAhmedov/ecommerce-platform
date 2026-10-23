import bcrypt from 'bcryptjs';
import { hashPassword } from '../../utils/password.js';

describe('hashPassword', () => {
  it("uses PHP's $2y$ bcrypt label so Laravel accepts the hash", async () => {
    await expect(hashPassword('password123')).resolves.toMatch(/^\$2y\$12\$/);
  }, 15_000);

  it('still verifies with bcryptjs', async () => {
    const hash = await hashPassword('password123');

    await expect(bcrypt.compare('password123', hash)).resolves.toBe(true);
    await expect(bcrypt.compare('wrong-password', hash)).resolves.toBe(false);
  }, 15_000);
});
