-- S3.3: stop writers; keep a tested backup. DDL commits implicitly. Run without --force.
-- MySQL 8.0 / MariaDB 10.6; no platform-specific numeric syntax.

-- FLOAT cannot represent most new decimal writes exactly. Refuse unsupported rollback.
DROP PROCEDURE IF EXISTS doyel_003_down_preflight;
DELIMITER //
CREATE PROCEDURE doyel_003_down_preflight()
BEGIN
  IF EXISTS (SELECT 1 FROM `canteen_collection` WHERE `amount` <> TRUNCATE(`amount`,0) OR ABS(`amount`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_collection.amount: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_expenses` WHERE `amount` <> TRUNCATE(`amount`,0) OR ABS(`amount`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_expenses.amount: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_item` WHERE `PurPrice` <> TRUNCATE(`PurPrice`,0) OR ABS(`PurPrice`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_item.PurPrice: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_item` WHERE `SalesPrice` <> TRUNCATE(`SalesPrice`,0) OR ABS(`SalesPrice`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_item.SalesPrice: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_item` WHERE `Qnty` <> TRUNCATE(`Qnty`,0) OR ABS(`Qnty`) > 2147483647) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_item.Qnty: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase` WHERE `Amount` <> TRUNCATE(`Amount`,0) OR ABS(`Amount`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_purchase.Amount: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase` WHERE `Discount` <> TRUNCATE(`Discount`,0) OR ABS(`Discount`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_purchase.Discount: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase` WHERE `Total` <> TRUNCATE(`Total`,0) OR ABS(`Total`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_purchase.Total: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase_detail` WHERE `Qnty` <> TRUNCATE(`Qnty`,0) OR ABS(`Qnty`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_purchase_detail.Qnty: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase_detail` WHERE `QT` <> TRUNCATE(`QT`,0) OR ABS(`QT`) > 127) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_purchase_detail.QT: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase_detail` WHERE `Rate` <> TRUNCATE(`Rate`,0) OR ABS(`Rate`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_purchase_detail.Rate: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase_detail` WHERE `Amt` <> TRUNCATE(`Amt`,0) OR ABS(`Amt`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_purchase_detail.Amt: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales` WHERE `Amount` <> TRUNCATE(`Amount`,0) OR ABS(`Amount`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales.Amount: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales` WHERE `Discount` <> TRUNCATE(`Discount`,0) OR ABS(`Discount`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales.Discount: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales` WHERE `Total` <> TRUNCATE(`Total`,0) OR ABS(`Total`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales.Total: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales_detail` WHERE `QT` <> TRUNCATE(`QT`,0) OR ABS(`QT`) > 127) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales_detail.QT: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales_detail` WHERE `Qnty` <> TRUNCATE(`Qnty`,0) OR ABS(`Qnty`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales_detail.Qnty: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales_detail` WHERE `PurAmt` <> TRUNCATE(`PurAmt`,0) OR ABS(`PurAmt`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales_detail.PurAmt: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales_detail` WHERE `Rate` <> TRUNCATE(`Rate`,0) OR ABS(`Rate`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales_detail.Rate: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales_detail` WHERE `Amt` <> TRUNCATE(`Amt`,0) OR ABS(`Amt`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales_detail.Amt: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_stock_detail` WHERE `PurPrice` <> TRUNCATE(`PurPrice`,0) OR ABS(`PurPrice`) > 16777216) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_stock_detail.PurPrice: unsupported lossy rollback; restore backup';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_stock_detail` WHERE `Qnty` <> TRUNCATE(`Qnty`,0) OR ABS(`Qnty`) > 2147483647) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_stock_detail.Qnty: unsupported lossy rollback; restore backup';
  END IF;
END//
DELIMITER ;
CALL doyel_003_down_preflight();
DROP PROCEDURE doyel_003_down_preflight;
ALTER TABLE `canteen_ac_head`
  MODIFY COLUMN `conCode` varchar(20) NOT NULL;
ALTER TABLE `canteen_collection`
  MODIFY COLUMN `amount` float NOT NULL,
  MODIFY COLUMN `Remarks` varchar(100) NOT NULL;
ALTER TABLE `canteen_customar`
  MODIFY COLUMN `mobile` varchar(15) NOT NULL;
ALTER TABLE `canteen_expenses`
  MODIFY COLUMN `amount` float NOT NULL,
  MODIFY COLUMN `Remarks` varchar(20) NOT NULL;
ALTER TABLE `canteen_item`
  MODIFY COLUMN `PurPrice` float NOT NULL,
  MODIFY COLUMN `SalesPrice` float NOT NULL,
  MODIFY COLUMN `GenericName` varchar(25) NOT NULL,
  MODIFY COLUMN `MinimumQuantity` int NOT NULL,
  MODIFY COLUMN `MaximumQuantity` int NOT NULL,
  MODIFY COLUMN `Qnty` int NOT NULL,
  MODIFY COLUMN `D` tinyint NOT NULL,
  MODIFY COLUMN `ItemType` varchar(2) NOT NULL;
ALTER TABLE `canteen_purchase`
  MODIFY COLUMN `PR` varchar(1) NOT NULL,
  MODIFY COLUMN `Amount` float NOT NULL,
  MODIFY COLUMN `Discount` float NOT NULL DEFAULT '0',
  MODIFY COLUMN `Total` float NOT NULL;
ALTER TABLE `canteen_purchase_detail`
  MODIFY COLUMN `Qnty` float NOT NULL,
  MODIFY COLUMN `QT` tinyint(1) NULL DEFAULT NULL,
  MODIFY COLUMN `Rate` float NOT NULL,
  MODIFY COLUMN `Amt` float NOT NULL;
ALTER TABLE `canteen_sales`
  MODIFY COLUMN `Amount` float NOT NULL,
  MODIFY COLUMN `Discount` float NOT NULL,
  MODIFY COLUMN `Total` float NOT NULL,
  MODIFY COLUMN `SR` varchar(1) NOT NULL,
  MODIFY COLUMN `Credit` int NOT NULL;
ALTER TABLE `canteen_sales_detail`
  MODIFY COLUMN `QT` tinyint(1) NULL DEFAULT NULL,
  MODIFY COLUMN `Qnty` float NOT NULL,
  MODIFY COLUMN `PurAmt` float NOT NULL,
  MODIFY COLUMN `Rate` float NOT NULL,
  MODIFY COLUMN `Amt` float NOT NULL;
ALTER TABLE `canteen_stock_detail`
  MODIFY COLUMN `PurPrice` float NOT NULL,
  MODIFY COLUMN `Qnty` int NOT NULL;
ALTER TABLE `canteen_supplier`
  MODIFY COLUMN `D` tinyint(1) NOT NULL;
ALTER TABLE `l_user`
  MODIFY COLUMN `address` varchar(50) NOT NULL,
  MODIFY COLUMN `mobile` varchar(15) NOT NULL,
  MODIFY COLUMN `userAtho` int NOT NULL;
