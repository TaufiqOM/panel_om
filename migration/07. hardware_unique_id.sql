-- Add unique key on id for hardware table
ALTER TABLE hardware ADD UNIQUE KEY unique_id (id);
