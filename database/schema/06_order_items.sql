CREATE TABLE order_items (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id     BIGINT        NOT NULL REFERENCES orders (id) ON DELETE CASCADE,
    product_id   BIGINT        NOT NULL REFERENCES products (id) ON DELETE RESTRICT,
    product_name VARCHAR(255)  NOT NULL,
    unit_price   NUMERIC(12,2) NOT NULL CHECK (unit_price >= 0),
    quantity     INTEGER       NOT NULL CHECK (quantity > 0),
    line_total   NUMERIC(12,2) NOT NULL CHECK (line_total >= 0),
    created_at   TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    UNIQUE (order_id, product_id)
);

CREATE INDEX order_items_product_id_index ON order_items (product_id);
