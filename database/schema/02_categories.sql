CREATE TABLE categories (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    parent_id   BIGINT REFERENCES categories (id) ON DELETE SET NULL,
    name        VARCHAR(255) NOT NULL,
    slug        VARCHAR(255) NOT NULL,
    description TEXT,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at  TIMESTAMPTZ,
    CHECK (parent_id IS NULL OR parent_id <> id)
);

CREATE UNIQUE INDEX categories_slug_unique ON categories (slug) WHERE deleted_at IS NULL;
CREATE INDEX categories_parent_id_index ON categories (parent_id);
