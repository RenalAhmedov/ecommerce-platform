CREATE TABLE order_items (
    id           BIGINT GENERATED ALWAYS AS IDENTITY,
    order_id     BIGINT        NOT NULL,
    product_id   BIGINT        NOT NULL,
    product_name VARCHAR(255)  NOT NULL,
    unit_price   NUMERIC(12,2) NOT NULL,
    quantity     INTEGER       NOT NULL,
    line_total   NUMERIC(12,2) NOT NULL,
    created_at   TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    CONSTRAINT order_items_pkey PRIMARY KEY (id),
    CONSTRAINT order_items_order_id_product_id_unique UNIQUE (order_id, product_id),
    CONSTRAINT order_items_order_id_foreign FOREIGN KEY (order_id)
        REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT order_items_product_id_foreign FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE RESTRICT,
    CONSTRAINT order_items_unit_price_check CHECK (unit_price >= 0),
    CONSTRAINT order_items_quantity_check CHECK (quantity > 0),
    CONSTRAINT order_items_line_total_check CHECK (line_total >= 0)
);
