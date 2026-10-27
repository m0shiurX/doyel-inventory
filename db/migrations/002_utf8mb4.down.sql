-- S3.1: MySQL 8.0+ / MariaDB 10.6+, run via mysql without --force.
-- Stop application/writer traffic for the ENTIRE preflight + conversion window.
-- DDL commits implicitly; a failed ALTER requires inspection/recovery, not ROLLBACK.
-- Original latin1 tables require ASCII; Unicode tables require exact byte roundtrip.
-- Report counts/IDs first with tools/schema/charset-check.php; keep a tested backup.
DELIMITER //
DROP PROCEDURE IF EXISTS doyel_s31_charset_guard//
CREATE PROCEDURE doyel_s31_charset_guard()
BEGIN
    DECLARE finished INT DEFAULT 0;
    DECLARE table_name_value VARCHAR(64);
    DECLARE column_name_value VARCHAR(64);
    DECLARE source_charset VARCHAR(64);
    DECLARE target_charset VARCHAR(64);
    DECLARE text_columns CURSOR FOR
        SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_SET_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND CHARACTER_SET_NAME IS NOT NULL
        ORDER BY TABLE_NAME, ORDINAL_POSITION;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;
    IF DATABASE() IS NULL OR
       (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE') <> 13 OR
       (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (
         'canteen_ac_head','canteen_collection','canteen_customar','canteen_expenses','canteen_item',
         'canteen_purchase','canteen_purchase_detail','canteen_sales','canteen_sales_detail',
         'canteen_stock','canteen_stock_detail','canteen_supplier','l_user')) <> 13 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'S3.1 requires the selected 13-table Doyel schema';
    END IF;
    OPEN text_columns;
    scan_columns: LOOP
        FETCH text_columns INTO table_name_value, column_name_value, source_charset;
        IF finished = 1 THEN LEAVE scan_columns; END IF;
        IF source_charset NOT IN ('latin1','utf8','utf8mb3','utf8mb4') THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'S3.1 stopped: unsupported source charset';
        END IF;
        IF table_name_value IN ('canteen_expenses','canteen_stock','canteen_stock_detail') THEN
            -- Original latin1 data stays ASCII-only: do not guess its intended encoding.
            SET @s31_sql = CONCAT('SELECT COUNT(*) INTO @s31_invalid FROM `',
                REPLACE(table_name_value,'`','``'), '` WHERE HEX(`',
                REPLACE(column_name_value,'`','``'), '`) REGEXP ''^(..)*[89ABCDEF]''');
        ELSE
            IF source_charset = 'latin1' THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'S3.1 stopped: unexpected latin1 Unicode column';
            END IF;
            SET target_charset = 'utf8';
            -- Compare stored bytes after target conversion and conversion back to source.
            -- MySQL utf8mb3 and MariaDB utf8 aliases are accepted; charset names are allowlisted.
            SET @s31_sql = CONCAT('SELECT COUNT(*) INTO @s31_invalid FROM `',
                REPLACE(table_name_value,'`','``'), '` WHERE BINARY `',
                REPLACE(column_name_value,'`','``'), '` <> BINARY CONVERT(CONVERT(`',
                REPLACE(column_name_value,'`','``'), '` USING ', target_charset,
                ') USING ', source_charset, ')');
            IF (table_name_value = 'canteen_customar' AND column_name_value IN ('address','MemberName'))
               OR (table_name_value = 'canteen_sales' AND column_name_value = 'Note')
               OR (table_name_value = 'l_user' AND column_name_value = 'FullName') THEN
                -- Keep original TEXT byte capacity instead of CONVERT's automatic MEDIUMTEXT widening.
                SET @s31_sql = CONCAT(@s31_sql, ' OR OCTET_LENGTH(CONVERT(`',
                    REPLACE(column_name_value,'`','``'), '` USING ', target_charset, ')) > 65535');
            END IF;
        END IF;
        PREPARE s31_statement FROM @s31_sql;
        EXECUTE s31_statement;
        DEALLOCATE PREPARE s31_statement;
        IF @s31_invalid > 0 THEN
            CLOSE text_columns;
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'S3.1 stopped: unsafe charset conversion or TEXT capacity';
        END IF;
    END LOOP;
    CLOSE text_columns;
END//
CALL doyel_s31_charset_guard()//
DROP PROCEDURE doyel_s31_charset_guard//
DELIMITER ;
-- DOWN supports ASCII in original latin1 tables and exactly representable UTF8 elsewhere.
-- Four-byte Unicode in original UTF8 tables aborts before every charset ALTER.
-- Restore a tested backup if down cannot represent new writes; never strip characters.
ALTER DATABASE CHARACTER SET utf8 COLLATE utf8_unicode_ci;
ALTER TABLE `canteen_ac_head` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;
ALTER TABLE `canteen_collection` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;
ALTER TABLE `canteen_customar` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;
-- MySQL applies CONVERT widening after same-ALTER MODIFY: restore original TEXT explicitly.
ALTER TABLE `canteen_customar`
    MODIFY `address` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
    MODIFY `MemberName` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;
ALTER TABLE `canteen_expenses` CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci;
ALTER TABLE `canteen_item` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;
ALTER TABLE `canteen_purchase` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;
ALTER TABLE `canteen_purchase_detail` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;
ALTER TABLE `canteen_sales` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;
-- MySQL applies CONVERT widening after same-ALTER MODIFY: restore original TEXT explicitly.
ALTER TABLE `canteen_sales`
    MODIFY `Note` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL;
ALTER TABLE `canteen_sales_detail` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;
ALTER TABLE `canteen_stock` CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci;
ALTER TABLE `canteen_stock_detail` CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci;
ALTER TABLE `canteen_supplier` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;
ALTER TABLE `l_user` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;
-- MySQL applies CONVERT widening after same-ALTER MODIFY: restore original TEXT explicitly.
ALTER TABLE `l_user`
    MODIFY `FullName` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;
-- utf8 is the portable MySQL/MariaDB alias for the original 3-byte charset;
-- MySQL 8 metadata calls it utf8mb3; MariaDB may report utf8 instead.
