-- This schema defines the tables for the Mutual Consent Blind Time Capsule.
-- It should be executed once to set up the necessary database structure.

-- Create the lock table that controls the global gate state.
-- It is constrained to have exactly one row.
CREATE TABLE IF NOT EXISTS capsule_lock (
    id INT PRIMARY KEY DEFAULT 1,
    mj_key BOOLEAN NOT NULL DEFAULT false,
    kaye_key BOOLEAN NOT NULL DEFAULT false,
    CONSTRAINT single_row_check CHECK (id = 1)
);

-- Seed the lock table with its one and only row if it's empty. This ensures the lock always exists.
INSERT INTO capsule_lock (id, mj_key, kaye_key)
VALUES (1, false, false)
ON CONFLICT (id) DO NOTHING;

-- Create the messages table for storing the time capsule entries.
CREATE TABLE IF NOT EXISTS capsule_messages (
    id SERIAL PRIMARY KEY,
    writer VARCHAR(10) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    spotify_track_id VARCHAR(100) NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);