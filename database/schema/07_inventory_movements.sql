CREATE TABLE inventory_movements (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    product_id BIGINT      NOT NULL REFERENCES products (id) ON DELETE CASCADE,
    order_id   BIGINT      REFERENCES orders (id) ON DELETE SET NULL,
    user_id    BIGINT      REFERENCES users (id) ON DELETE SET NULL,
    change     INTEGER     NOT NULL CHECK (change <> 0),
    reason     VARCHAR(20) NOT NULL
               CHECK (reason IN ('initial', 'restock', 'sale', 'cancellation', 'adjustment')),
    note       VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
