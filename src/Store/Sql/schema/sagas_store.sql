CREATE TABLE IF NOT EXISTS sagas_store (
    id UUID,
    identifier_class VARCHAR NOT NULL,
    saga_class VARCHAR NOT NULL,
    payload BYTEA NOT NULL,
    state_id VARCHAR NOT NULL,
    created_at TIMESTAMP NOT NULL,
    expiration_date TIMESTAMP NOT NULL,
    closed_at TIMESTAMP,
    CONSTRAINT saga_identifier PRIMARY KEY (id, identifier_class)
);

CREATE TABLE IF NOT EXISTS sagas_association
(
    id  UUID,
    saga_id          uuid         not null,
    identifier_class varchar(255) not null,
    saga_class       varchar(255) not null,
    property_name    varchar(255) not null,
    property_value   varchar(255) not null,
    CONSTRAINT sagas_association_pk PRIMARY KEY (id)
);
