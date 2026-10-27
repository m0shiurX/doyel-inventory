-- S1.1: compatible with MySQL/MariaDB 5.5+; run once on the legacy schema.
-- All indexes for one table share one ALTER to avoid repeated table rebuilds.
ALTER TABLE canteen_sales
    ADD INDEX idx_sales_customer (CustomerName),
    ADD INDEX idx_sales_user_post_date (UserName, Post, Date),
    ADD INDEX idx_sales_post_date (Post, Date),
    ADD INDEX idx_sales_date (Date);
ALTER TABLE canteen_sales_detail
    ADD INDEX idx_sales_detail_bill (SalesID),
    ADD INDEX idx_sales_detail_item_date (ItemID, Date),
    ADD INDEX idx_sales_detail_date (Date);
ALTER TABLE canteen_purchase
    ADD INDEX idx_purchase_user_post_date (UserName, Post, Date),
    ADD INDEX idx_purchase_post_date (Post, Date),
    ADD INDEX idx_purchase_supplier_date (Supplier, Date);
ALTER TABLE canteen_purchase_detail
    ADD INDEX idx_purchase_detail_bill (BillNo),
    ADD INDEX idx_purchase_detail_item_date (ItemID, Date);
ALTER TABLE canteen_collection
    ADD INDEX idx_collection_description (Description),
    ADD INDEX idx_collection_date (Date),
    ADD INDEX idx_collection_head_date (HeadOfAccount, Date);
ALTER TABLE canteen_expenses
    ADD INDEX idx_expenses_date (Date),
    ADD INDEX idx_expenses_head_date (HeadOfAccount, Date),
    ADD INDEX idx_expenses_description_date (Description, Date);
ALTER TABLE canteen_stock_detail
    ADD COLUMN ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST,
    ADD INDEX idx_stock_snapshot_supplier (SlNo, SupplierName);
