import bcrypt from 'bcryptjs';
import { BCRYPT_ROUNDS } from '../constants/auth.constants.js';

export async function hashPassword(password: string): Promise<string> {
  const hash = await bcrypt.hash(password, BCRYPT_ROUNDS);

  return hash.replace(/^\$2b\$/, '$2y$');
}
