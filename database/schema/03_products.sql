CREATE TABLE products (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    category_id BIGINT REFERENCES categories (id) ON DELETE SET NULL,
    name        VARCHAR(255)  NOT NULL,
    slug        VARCHAR(255)  NOT NULL,
    sku         VARCHAR(64)   NOT NULL,
    description TEXT,
    price       NUMERIC(12,2) NOT NULL CHECK (price >= 0),
    image_path  VARCHAR(500),
    is_active   BOOLEAN       NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    deleted_at  TIMESTAMPTZ
);

CREATE UNIQUE INDEX products_slug_unique ON products (slug) WHERE deleted_at IS NULL;
CREATE UNIQUE INDEX products_sku_unique  ON products (sku)  WHERE deleted_at IS NULL;
CREATE INDEX products_category_id_index ON products (category_id);
CREATE INDEX products_active_price_index ON products (is_active, price) WHERE deleted_at IS NULL;
