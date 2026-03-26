-- Enable/disable reviews per product (seller-controlled)
-- Run this once on your existing database.

ALTER TABLE products
  ADD COLUMN reviews_enabled TINYINT(1) NOT NULL DEFAULT 1;

