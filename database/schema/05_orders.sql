CREATE TABLE orders (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_number      VARCHAR(32)   NOT NULL UNIQUE,
    user_id           BIGINT        NOT NULL REFERENCES users (id) ON DELETE RESTRICT,
    status            VARCHAR(20)   NOT NULL DEFAULT 'pending'
                      CHECK (status IN ('pending', 'paid', 'processing', 'shipped',
                                        'delivered', 'cancelled', 'payment_failed')),
    subtotal          NUMERIC(12,2) NOT NULL CHECK (subtotal >= 0),
    total             NUMERIC(12,2) NOT NULL CHECK (total >= 0),
    payment_reference VARCHAR(100),
    shipping_address  JSONB,
    paid_at           TIMESTAMPTZ,
    created_at        TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at        TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);
