CREATE TABLE products (
    id          BIGINT GENERATED ALWAYS AS IDENTITY,
    category_id BIGINT,
    name        VARCHAR(255)  NOT NULL,
    slug        VARCHAR(255)  NOT NULL,
    sku         VARCHAR(64)   NOT NULL,
    description TEXT,
    price       NUMERIC(12,2) NOT NULL,
    image_path  VARCHAR(500),
    is_active   BOOLEAN       NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    deleted_at  TIMESTAMPTZ,
    CONSTRAINT products_pkey PRIMARY KEY (id),
    CONSTRAINT products_category_id_foreign FOREIGN KEY (category_id)
        REFERENCES categories (id) ON DELETE SET NULL,
    CONSTRAINT products_price_check CHECK (price >= 0)
);
