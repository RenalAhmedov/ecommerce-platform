CREATE TABLE inventory_movements (
    id         BIGINT GENERATED ALWAYS AS IDENTITY,
    product_id BIGINT      NOT NULL,
    order_id   BIGINT,
    user_id    BIGINT,
    change     INTEGER     NOT NULL,
    reason     VARCHAR(20) NOT NULL,
    note       VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT inventory_movements_pkey PRIMARY KEY (id),
    CONSTRAINT inventory_movements_product_id_foreign FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT inventory_movements_order_id_foreign FOREIGN KEY (order_id)
        REFERENCES orders (id) ON DELETE SET NULL,
    CONSTRAINT inventory_movements_user_id_foreign FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT inventory_movements_change_check CHECK (change <> 0),
    CONSTRAINT inventory_movements_reason_check CHECK (reason IN ('initial', 'restock', 'sale',
                                                                  'cancellation', 'adjustment'))
);
