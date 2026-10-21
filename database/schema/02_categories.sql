CREATE TABLE categories (
    id          BIGINT GENERATED ALWAYS AS IDENTITY,
    parent_id   BIGINT,
    name        VARCHAR(255) NOT NULL,
    slug        VARCHAR(255) NOT NULL,
    description TEXT,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at  TIMESTAMPTZ,
    CONSTRAINT categories_pkey PRIMARY KEY (id),
    CONSTRAINT categories_parent_id_foreign FOREIGN KEY (parent_id)
        REFERENCES categories (id) ON DELETE SET NULL,
    CONSTRAINT categories_parent_check CHECK (parent_id IS NULL OR parent_id <> id)
);
