-- S1.2: metadata only; compatible with MySQL/MariaDB 5.5+.
-- One tab-separated row: state, version, database bytes, server data directory.
-- Benign unrelated indexes are retained; equivalent/covering indexes under
-- other names require manual review, avoiding duplicate or conflicting DDL.
SET @stock_id_count=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='canteen_stock_detail' AND COLUMN_NAME='ID');
SET @stock_id_valid=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='canteen_stock_detail' AND COLUMN_NAME='ID' AND DATA_TYPE='int' AND COLUMN_TYPE NOT LIKE '%unsigned%' AND IS_NULLABLE='NO' AND EXTRA LIKE '%auto_increment%' AND ORDINAL_POSITION=1);
SET @target_tables=(SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('canteen_collection','canteen_expenses','canteen_purchase','canteen_purchase_detail','canteen_sales','canteen_sales_detail','canteen_stock_detail'));
SELECT CASE
    WHEN @target_tables=7 AND counts.base_matches=6 AND counts.new_present=0
         AND @stock_id_count=0 AND counts.overlaps=0 THEN 'BASELINE'
    WHEN @target_tables=7 AND counts.all_matches=26 AND @stock_id_valid=1
         AND counts.overlaps=0 THEN 'APPLIED'
    ELSE 'PARTIAL_OR_CONFLICT' END AS migration_state,
    VERSION() AS server_version,
    (SELECT CAST(COALESCE(SUM(DATA_LENGTH+INDEX_LENGTH),0) AS UNSIGNED) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()) AS database_bytes,
    @@datadir AS data_directory
FROM (
SELECT COUNT(DISTINCT CASE WHEN e.is_new=0 AND a.cols=e.cols AND a.nonunique=e.nonunique AND a.prefixes=0 AND a.kind='BTREE' THEN CONCAT(e.tbl,'/',e.idx) END) AS base_matches,
    COUNT(DISTINCT CASE WHEN e.is_new=1 AND a.idx IS NOT NULL THEN CONCAT(e.tbl,'/',e.idx) END) AS new_present,
    COUNT(DISTINCT CASE WHEN a.cols=e.cols AND a.nonunique=e.nonunique AND a.prefixes=0 AND a.kind='BTREE' THEN CONCAT(e.tbl,'/',e.idx) END) AS all_matches,
    COUNT(DISTINCT CONCAT(o.tbl,'/',o.idx)) AS overlaps
FROM (
    SELECT 'canteen_collection' AS tbl, 'PRIMARY' AS idx, 'PaymentID' AS cols, 0 AS nonunique, 0 AS is_new
    UNION ALL
    SELECT 'canteen_expenses' AS tbl, 'PRIMARY' AS idx, 'PaymentID' AS cols, 0 AS nonunique, 0 AS is_new
    UNION ALL
    SELECT 'canteen_purchase' AS tbl, 'PRIMARY' AS idx, 'BillNo' AS cols, 0 AS nonunique, 0 AS is_new
    UNION ALL
    SELECT 'canteen_purchase_detail' AS tbl, 'PRIMARY' AS idx, 'ID' AS cols, 0 AS nonunique, 0 AS is_new
    UNION ALL
    SELECT 'canteen_sales' AS tbl, 'PRIMARY' AS idx, 'SalesID' AS cols, 0 AS nonunique, 0 AS is_new
    UNION ALL
    SELECT 'canteen_sales_detail' AS tbl, 'PRIMARY' AS idx, 'ID' AS cols, 0 AS nonunique, 0 AS is_new
    UNION ALL
    SELECT 'canteen_sales' AS tbl, 'idx_sales_customer' AS idx, 'CustomerName' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_sales' AS tbl, 'idx_sales_user_post_date' AS idx, 'UserName,Post,Date' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_sales' AS tbl, 'idx_sales_post_date' AS idx, 'Post,Date' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_sales' AS tbl, 'idx_sales_date' AS idx, 'Date' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_sales_detail' AS tbl, 'idx_sales_detail_bill' AS idx, 'SalesID' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_sales_detail' AS tbl, 'idx_sales_detail_item_date' AS idx, 'ItemID,Date' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_sales_detail' AS tbl, 'idx_sales_detail_date' AS idx, 'Date' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_purchase' AS tbl, 'idx_purchase_user_post_date' AS idx, 'UserName,Post,Date' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_purchase' AS tbl, 'idx_purchase_post_date' AS idx, 'Post,Date' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_purchase' AS tbl, 'idx_purchase_supplier_date' AS idx, 'Supplier,Date' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_purchase_detail' AS tbl, 'idx_purchase_detail_bill' AS idx, 'BillNo' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_purchase_detail' AS tbl, 'idx_purchase_detail_item_date' AS idx, 'ItemID,Date' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_collection' AS tbl, 'idx_collection_description' AS idx, 'Description' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_collection' AS tbl, 'idx_collection_date' AS idx, 'Date' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_collection' AS tbl, 'idx_collection_head_date' AS idx, 'HeadOfAccount,Date' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_expenses' AS tbl, 'idx_expenses_date' AS idx, 'Date' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_expenses' AS tbl, 'idx_expenses_head_date' AS idx, 'HeadOfAccount,Date' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_expenses' AS tbl, 'idx_expenses_description_date' AS idx, 'Description,Date' AS cols, 1 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_stock_detail' AS tbl, 'PRIMARY' AS idx, 'ID' AS cols, 0 AS nonunique, 1 AS is_new
    UNION ALL
    SELECT 'canteen_stock_detail' AS tbl, 'idx_stock_snapshot_supplier' AS idx, 'SlNo,SupplierName' AS cols, 1 AS nonunique, 1 AS is_new
) e
LEFT JOIN (
    SELECT TABLE_NAME AS tbl,INDEX_NAME AS idx,
        GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS cols,
        MIN(NON_UNIQUE) AS nonunique,
        SUM(SUB_PART IS NOT NULL) AS prefixes,
        MIN(INDEX_TYPE) AS kind
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('canteen_collection','canteen_expenses','canteen_purchase','canteen_purchase_detail','canteen_sales','canteen_sales_detail','canteen_stock_detail')
    GROUP BY TABLE_NAME,INDEX_NAME
) a ON a.tbl=e.tbl AND a.idx=e.idx
LEFT JOIN (
    SELECT TABLE_NAME AS tbl,INDEX_NAME AS idx,
        GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS cols,
        MIN(NON_UNIQUE) AS nonunique,
        SUM(SUB_PART IS NOT NULL) AS prefixes,
        MIN(INDEX_TYPE) AS kind
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('canteen_collection','canteen_expenses','canteen_purchase','canteen_purchase_detail','canteen_sales','canteen_sales_detail','canteen_stock_detail')
    GROUP BY TABLE_NAME,INDEX_NAME
) o ON e.is_new=1 AND e.nonunique=1 AND o.tbl=e.tbl AND o.idx<>e.idx
    AND (o.cols=e.cols OR o.cols LIKE CONCAT(e.cols,',%'))
) counts;
