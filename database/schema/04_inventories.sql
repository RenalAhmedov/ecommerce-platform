CREATE TABLE inventories (
    id                  BIGINT GENERATED ALWAYS AS IDENTITY,
    product_id          BIGINT      NOT NULL,
    quantity            INTEGER     NOT NULL DEFAULT 0,
    low_stock_threshold INTEGER     NOT NULL DEFAULT 5,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT inventories_pkey PRIMARY KEY (id),
    CONSTRAINT inventories_product_id_unique UNIQUE (product_id),
    CONSTRAINT inventories_product_id_foreign FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT inventories_quantity_check CHECK (quantity >= 0),
    CONSTRAINT inventories_low_stock_threshold_check CHECK (low_stock_threshold >= 0)
);
