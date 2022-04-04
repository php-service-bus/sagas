CREATE INDEX IF NOT EXISTS sagas_state ON sagas_store (state_id);
CREATE INDEX IF NOT EXISTS saga_closed_index ON sagas_store (state_id, closed_at);
CREATE UNIQUE INDEX IF NOT EXISTS  sagas_association_property ON sagas_association (saga_id, saga_class, property_name);
CREATE INDEX IF NOT EXISTS  sagas_association_property_value ON sagas_association (identifier_class, saga_class, property_name, property_value);