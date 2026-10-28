-- S3.4: constraint rollback only. This never recreates deleted user records.
-- Restore reviewed private users.php backup separately after down, with writers stopped.
ALTER TABLE canteen_purchase_detail DROP FOREIGN KEY fk_purchase_detail_bill, DROP FOREIGN KEY fk_purchase_detail_item;
ALTER TABLE canteen_sales_detail DROP FOREIGN KEY fk_sales_detail_sale, DROP FOREIGN KEY fk_sales_detail_item;
ALTER TABLE l_user DROP INDEX uq_l_user_username;
-- Existing 001 child indexes remain; no index was added for these foreign keys.
