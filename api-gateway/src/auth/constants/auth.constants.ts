export const BCRYPT_ROUNDS = 12;

export const UNKNOWN_USER_PASSWORD_HASH =
  '$2b$12$SrBe8r37aBtFdLoLw9JwFeuMVboLs3lO2HAx43SNDHhwmY78aSBFm';

export const REVOKED_TOKEN_KEY_PREFIX = 'auth:revoked-token';

/** Unix time written by the admin service when a user is deleted, changes role or gets a new password. */
export const USER_TOKENS_VALID_AFTER_KEY_PREFIX =
  'auth:user-tokens-valid-after';
