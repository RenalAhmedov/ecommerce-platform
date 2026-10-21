CREATE TABLE users (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name              VARCHAR(255) NOT NULL,
    email             VARCHAR(255) NOT NULL,
    password          VARCHAR(255) NOT NULL,              
    role              VARCHAR(20)  NOT NULL DEFAULT 'customer'
                      CHECK (role IN ('customer', 'admin')),
    email_verified_at TIMESTAMPTZ,
    remember_token    VARCHAR(100),                    
    created_at        TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at        TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at        TIMESTAMPTZ
);
