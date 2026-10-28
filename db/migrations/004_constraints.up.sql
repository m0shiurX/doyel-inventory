-- S3.4: stop writers and take a tested private backup; DDL commits implicitly.
-- MySQL 8.0 / MariaDB 10.6: common SIGNAL/routine and RESTRICT syntax.
-- Run without --force. Run reviewed users.php dedup first; this file deletes no data.
DROP PROCEDURE IF EXISTS doyel_004_preflight;
DELIMITER //
CREATE PROCEDURE doyel_004_preflight()
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('l_user','canteen_sales','canteen_purchase','canteen_item','canteen_sales_detail','canteen_purchase_detail') AND ENGINE='InnoDB') <> 6 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='004 requires six InnoDB constraint tables';
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='canteen_sales_detail' AND COLUMN_NAME IN ('SalesID','ItemID')) OR (TABLE_NAME='canteen_purchase_detail' AND COLUMN_NAME IN ('BillNo','ItemID')) OR (TABLE_NAME='canteen_item' AND COLUMN_NAME='ItemID') OR (TABLE_NAME='canteen_sales' AND COLUMN_NAME='SalesID') OR (TABLE_NAME='canteen_purchase' AND COLUMN_NAME='BillNo')) AND DATA_TYPE='int' AND COLUMN_TYPE NOT LIKE '%unsigned%') <> 7 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='004 requires matching signed INT reference keys';
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='l_user' AND COLUMN_NAME='UserName' AND COLLATION_NAME='utf8mb4_unicode_ci' AND IS_NULLABLE='NO') <> 1 OR (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='canteen_item' AND COLUMN_NAME='Qnty' AND COLUMN_TYPE='decimal(12,3)') <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='004 requires 002/003 charset/type prerequisites';
  END IF;
  IF @@FOREIGN_KEY_CHECKS <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='004 requires FOREIGN_KEY_CHECKS=1';
  END IF;
  IF EXISTS (SELECT 1 FROM l_user GROUP BY UserName HAVING COUNT(*)>1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='004 duplicate usernames: review private users.php preview before dedup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales_detail` d LEFT JOIN `canteen_sales` p ON p.`SalesID`=d.`SalesID` WHERE p.`SalesID` IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='004 dangling canteen_sales_detail.SalesID; review cleanup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase_detail` d LEFT JOIN `canteen_purchase` p ON p.`BillNo`=d.`BillNo` WHERE p.`BillNo` IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='004 dangling canteen_purchase_detail.BillNo; review cleanup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales_detail` d LEFT JOIN `canteen_item` p ON p.`ItemID`=d.`ItemID` WHERE p.`ItemID` IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='004 dangling canteen_sales_detail.ItemID; review cleanup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase_detail` d LEFT JOIN `canteen_item` p ON p.`ItemID`=d.`ItemID` WHERE p.`ItemID` IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='004 dangling canteen_purchase_detail.ItemID; review cleanup';
  END IF;
END//
DELIMITER ;
CALL doyel_004_preflight();
DROP PROCEDURE doyel_004_preflight;
ALTER TABLE l_user ADD CONSTRAINT uq_l_user_username UNIQUE (UserName);
ALTER TABLE canteen_sales_detail
  ADD CONSTRAINT fk_sales_detail_sale FOREIGN KEY (SalesID) REFERENCES canteen_sales (SalesID) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_sales_detail_item FOREIGN KEY (ItemID) REFERENCES canteen_item (ItemID) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE canteen_purchase_detail
  ADD CONSTRAINT fk_purchase_detail_bill FOREIGN KEY (BillNo) REFERENCES canteen_purchase (BillNo) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT fk_purchase_detail_item FOREIGN KEY (ItemID) REFERENCES canteen_item (ItemID) ON DELETE RESTRICT ON UPDATE RESTRICT;
