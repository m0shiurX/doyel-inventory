-- S3.3: stop writers; keep a tested backup. DDL commits implicitly. Run without --force.
-- MySQL 8.0 / MariaDB 10.6; no platform-specific numeric syntax.

DROP PROCEDURE IF EXISTS doyel_003_preflight;
DELIMITER //
CREATE PROCEDURE doyel_003_preflight()
BEGIN
  IF EXISTS (SELECT 1 FROM `canteen_collection` WHERE ABS(`amount`) >= 1000000000000 OR ABS(CAST(CAST(`amount` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`amount` AS CHAR) AS DECIMAL(40,12)),2)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_collection.amount: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_expenses` WHERE ABS(`amount`) >= 1000000000000 OR ABS(CAST(CAST(`amount` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`amount` AS CHAR) AS DECIMAL(40,12)),2)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_expenses.amount: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_item` WHERE ABS(`PurPrice`) >= 100000000000 OR ABS(CAST(CAST(`PurPrice` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`PurPrice` AS CHAR) AS DECIMAL(40,12)),3)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_item.PurPrice: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_item` WHERE ABS(`SalesPrice`) >= 100000000000 OR ABS(CAST(CAST(`SalesPrice` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`SalesPrice` AS CHAR) AS DECIMAL(40,12)),3)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_item.SalesPrice: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_item` WHERE ABS(`Qnty`) >= 1000000000 OR ABS(CAST(CAST(`Qnty` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`Qnty` AS CHAR) AS DECIMAL(40,12)),3)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_item.Qnty: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase` WHERE ABS(`Amount`) >= 1000000000000 OR ABS(CAST(CAST(`Amount` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`Amount` AS CHAR) AS DECIMAL(40,12)),2)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_purchase.Amount: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase` WHERE ABS(`Discount`) >= 1000000000000 OR ABS(CAST(CAST(`Discount` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`Discount` AS CHAR) AS DECIMAL(40,12)),2)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_purchase.Discount: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase` WHERE ABS(`Total`) >= 1000000000000 OR ABS(CAST(CAST(`Total` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`Total` AS CHAR) AS DECIMAL(40,12)),2)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_purchase.Total: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase_detail` WHERE ABS(`Qnty`) >= 1000000000 OR ABS(CAST(CAST(`Qnty` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`Qnty` AS CHAR) AS DECIMAL(40,12)),3)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_purchase_detail.Qnty: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase_detail` WHERE ABS(`QT`) >= 1000000000 OR ABS(CAST(CAST(`QT` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`QT` AS CHAR) AS DECIMAL(40,12)),3)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_purchase_detail.QT: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase_detail` WHERE ABS(`Rate`) >= 100000000000 OR ABS(CAST(CAST(`Rate` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`Rate` AS CHAR) AS DECIMAL(40,12)),3)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_purchase_detail.Rate: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_purchase_detail` WHERE ABS(`Amt`) >= 1000000000000 OR ABS(CAST(CAST(`Amt` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`Amt` AS CHAR) AS DECIMAL(40,12)),2)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_purchase_detail.Amt: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales` WHERE ABS(`Amount`) >= 1000000000000 OR ABS(CAST(CAST(`Amount` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`Amount` AS CHAR) AS DECIMAL(40,12)),2)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales.Amount: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales` WHERE ABS(`Discount`) >= 1000000000000 OR ABS(CAST(CAST(`Discount` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`Discount` AS CHAR) AS DECIMAL(40,12)),2)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales.Discount: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales` WHERE ABS(`Total`) >= 1000000000000 OR ABS(CAST(CAST(`Total` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`Total` AS CHAR) AS DECIMAL(40,12)),2)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales.Total: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales_detail` WHERE ABS(`QT`) >= 1000000000 OR ABS(CAST(CAST(`QT` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`QT` AS CHAR) AS DECIMAL(40,12)),3)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales_detail.QT: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales_detail` WHERE ABS(`Qnty`) >= 1000000000 OR ABS(CAST(CAST(`Qnty` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`Qnty` AS CHAR) AS DECIMAL(40,12)),3)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales_detail.Qnty: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales_detail` WHERE ABS(`PurAmt`) >= 1000000000000 OR ABS(CAST(CAST(`PurAmt` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`PurAmt` AS CHAR) AS DECIMAL(40,12)),2)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales_detail.PurAmt: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales_detail` WHERE ABS(`Rate`) >= 100000000000 OR ABS(CAST(CAST(`Rate` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`Rate` AS CHAR) AS DECIMAL(40,12)),3)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales_detail.Rate: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_sales_detail` WHERE ABS(`Amt`) >= 1000000000000 OR ABS(CAST(CAST(`Amt` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`Amt` AS CHAR) AS DECIMAL(40,12)),2)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_sales_detail.Amt: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_stock_detail` WHERE ABS(`PurPrice`) >= 100000000000 OR ABS(CAST(CAST(`PurPrice` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`PurPrice` AS CHAR) AS DECIMAL(40,12)),3)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_stock_detail.PurPrice: range or precision loss; inspect before ALTER';
  END IF;
  IF EXISTS (SELECT 1 FROM `canteen_stock_detail` WHERE ABS(`Qnty`) >= 1000000000 OR ABS(CAST(CAST(`Qnty` AS CHAR) AS DECIMAL(40,12)) - ROUND(CAST(CAST(`Qnty` AS CHAR) AS DECIMAL(40,12)),3)) > 0.000001) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='canteen_stock_detail.Qnty: range or precision loss; inspect before ALTER';
  END IF;
END//
DELIMITER ;
CALL doyel_003_preflight();
DROP PROCEDURE doyel_003_preflight;
ALTER TABLE `canteen_ac_head`
  MODIFY COLUMN `conCode` varchar(20) NOT NULL DEFAULT '';
ALTER TABLE `canteen_collection`
  MODIFY COLUMN `amount` DECIMAL(14,2) NOT NULL DEFAULT '0',
  MODIFY COLUMN `Remarks` varchar(100) NOT NULL DEFAULT '';
ALTER TABLE `canteen_customar`
  MODIFY COLUMN `mobile` varchar(15) NOT NULL DEFAULT '';
ALTER TABLE `canteen_expenses`
  MODIFY COLUMN `amount` DECIMAL(14,2) NOT NULL DEFAULT '0',
  MODIFY COLUMN `Remarks` varchar(20) NOT NULL DEFAULT '';
ALTER TABLE `canteen_item`
  MODIFY COLUMN `PurPrice` DECIMAL(14,3) NOT NULL DEFAULT '0',
  MODIFY COLUMN `SalesPrice` DECIMAL(14,3) NOT NULL DEFAULT '0',
  MODIFY COLUMN `GenericName` varchar(25) NOT NULL DEFAULT '',
  MODIFY COLUMN `MinimumQuantity` int NOT NULL DEFAULT '0',
  MODIFY COLUMN `MaximumQuantity` int NOT NULL DEFAULT '0',
  MODIFY COLUMN `Qnty` DECIMAL(12,3) NOT NULL DEFAULT '0',
  MODIFY COLUMN `D` tinyint NOT NULL DEFAULT '0',
  MODIFY COLUMN `ItemType` varchar(2) NOT NULL DEFAULT '';
ALTER TABLE `canteen_purchase`
  MODIFY COLUMN `PR` varchar(1) NOT NULL DEFAULT '',
  MODIFY COLUMN `Amount` DECIMAL(14,2) NOT NULL DEFAULT '0',
  MODIFY COLUMN `Discount` DECIMAL(14,2) NOT NULL DEFAULT '0',
  MODIFY COLUMN `Total` DECIMAL(14,2) NOT NULL DEFAULT '0';
ALTER TABLE `canteen_purchase_detail`
  MODIFY COLUMN `Qnty` DECIMAL(12,3) NOT NULL DEFAULT '0',
  MODIFY COLUMN `QT` DECIMAL(12,3) NULL DEFAULT NULL,
  MODIFY COLUMN `Rate` DECIMAL(14,3) NOT NULL DEFAULT '0',
  MODIFY COLUMN `Amt` DECIMAL(14,2) NOT NULL DEFAULT '0';
ALTER TABLE `canteen_sales`
  MODIFY COLUMN `Amount` DECIMAL(14,2) NOT NULL DEFAULT '0',
  MODIFY COLUMN `Discount` DECIMAL(14,2) NOT NULL DEFAULT '0',
  MODIFY COLUMN `Total` DECIMAL(14,2) NOT NULL DEFAULT '0',
  MODIFY COLUMN `SR` varchar(1) NOT NULL DEFAULT '',
  MODIFY COLUMN `Credit` int NOT NULL DEFAULT '0';
ALTER TABLE `canteen_sales_detail`
  MODIFY COLUMN `QT` DECIMAL(12,3) NULL DEFAULT NULL,
  MODIFY COLUMN `Qnty` DECIMAL(12,3) NOT NULL DEFAULT '0',
  MODIFY COLUMN `PurAmt` DECIMAL(14,2) NOT NULL DEFAULT '0',
  MODIFY COLUMN `Rate` DECIMAL(14,3) NOT NULL DEFAULT '0',
  MODIFY COLUMN `Amt` DECIMAL(14,2) NOT NULL DEFAULT '0';
ALTER TABLE `canteen_stock_detail`
  MODIFY COLUMN `PurPrice` DECIMAL(14,3) NOT NULL DEFAULT '0',
  MODIFY COLUMN `Qnty` DECIMAL(12,3) NOT NULL DEFAULT '0';
ALTER TABLE `canteen_supplier`
  MODIFY COLUMN `D` tinyint(1) NOT NULL DEFAULT '0';
ALTER TABLE `l_user`
  MODIFY COLUMN `address` varchar(50) NOT NULL DEFAULT '',
  MODIFY COLUMN `mobile` varchar(15) NOT NULL DEFAULT '',
  MODIFY COLUMN `userAtho` int NOT NULL DEFAULT '0';
