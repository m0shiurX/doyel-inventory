<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
// Staff may save daily restore packages; raw SQL exports remain administrator-only.
if (($_GET['format'] ?? '') !== 'restore') require_admin();

// Downloads must never contain a PHP warning or the application's HTML error page.
ini_set('display_errors', '0');
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Restore packages are completed privately before any download headers are sent.
if (($_GET['format'] ?? '') === 'restore') {
    require_once __DIR__ . '/lib/backup_package.php';
    $packagePath = null;
    try {
        session_write_close();
        $config = app_config();
        $directory = $config['backup_dir'] ?? '';
        if (!is_string($directory)) throw new RuntimeException('Private backup directory unavailable');
        if ($directory === '') {
            if (PHP_OS_FAMILY === 'Windows' || ($config['env'] ?? '') !== 'development') throw new RuntimeException('Configured private backup directory required');
            $directory = sys_get_temp_dir() . '/doyel-backup-' . substr(hash('sha256', __DIR__), 0, 16);
        }
        // Windows ACLs are provisioned by the owner installer; POSIX mode bits do
        // not establish that boundary. Both platforms still reject public paths.
        if (is_link($directory)) throw new RuntimeException('Private backup directory unavailable');
        $directory = app_errors_private_directory($directory);
        if (PHP_OS_FAMILY !== 'Windows' && (fileperms($directory) & 0077) !== 0) throw new RuntimeException('Private backup directory permissions required');
        $packagePath = $directory . '/' . bin2hex(random_bytes(24)) . '.doyel.gz';
        doyel_backup_package_write(db(), $packagePath);
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="doyel-backup-' . gmdate('Y-m-d-Hi') . '.doyel.gz"');
        header('Content-Length: ' . filesize($packagePath));
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        $download = fopen($packagePath, 'rb');
        if ($download === false) throw new RuntimeException('Backup download unavailable');
        fpassthru($download);
        fclose($download);
    } catch (Throwable $error) {
        // Do not pass exception text to logs: database errors can contain business values.
        $reference = app_errors_report(new RuntimeException('Restore package download failed.'));
        if (!headers_sent()) {
            while (ob_get_level() > 0) ob_end_clean();
            header_remove('Content-Disposition');
            header_remove('Content-Length');
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Backup could not be completed. Reference: ' . $reference . "\n";
        }
    } finally {
        if ($packagePath !== null && is_file($packagePath)) unlink($packagePath);
    }
    exit;
}

$connection = null;
$statement = null;
try {
    session_write_close();
    $connection = db();
    // Use matching source/dump escape rules; retain explicit zero auto-increment IDs.
    $connection->exec("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
    $connection->exec("SET SESSION time_zone = '+00:00'");
    $tables = $connection->query('SHOW TABLE STATUS')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tables as $table) {
        if (strcasecmp((string)$table['Engine'], 'InnoDB') !== 0) {
            throw new RuntimeException('Backup requires InnoDB base tables');
        }
    }
    $connection->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $connection->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');

    // Names come only from server metadata. Quote identifiers even if they contain `.
    // Acquire metadata locks and gather every CREATE before opening a row cursor.
    $metadata = [];
    foreach ($tables as $table) {
        $name = '`' . str_replace('`', '``', $table['Name']) . '`';
        $connection->query('SELECT * FROM ' . $name . ' LIMIT 0')->closeCursor();
        $create = $connection->query('SHOW CREATE TABLE ' . $name)->fetch(PDO::FETCH_NUM);
        if ($create === false || !isset($create[1])) throw new RuntimeException('Missing table definition');
        $metadata[] = ['name' => $name, 'create' => $create[1]];
    }
    unset($tables, $table, $create);
    $connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

    // Bootstrap normally buffers HTML. Discard every buffer before streaming SQL.
    while (ob_get_level() > 0) {
        if (!ob_end_clean()) throw new RuntimeException('Cannot disable output buffering');
    }
    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="doyel-backup-' . date('Y-m-d-Hi') . '.sql"');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo "-- Doyel database backup; only complete when the final completion marker is present.\n";
    echo "SET NAMES utf8mb4;\nSET @DOYEL_OLD_SQL_MODE=@@SQL_MODE;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
    echo "SET @DOYEL_OLD_TIME_ZONE=@@TIME_ZONE;\nSET TIME_ZONE='+00:00';\n";
    echo "SET @DOYEL_OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($metadata as $table) {
        echo 'DROP TABLE IF EXISTS ' . $table['name'] . ";\n" . $table['create'] . ";\n";
        $statement = $connection->query('SELECT * FROM ' . $table['name']);
        $prefix = 'INSERT INTO ' . $table['name'] . ' VALUES ';
        $batch = '';
        $rows = 0;
        while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
            // Quote strings, including numeric strings, without PHP numeric conversion.
            // PDO escapes NULs, quotes and backslashes using the connection charset.
            $values = [];
            foreach ($row as $value) {
                $quoted = $value === null ? 'NULL' : $connection->quote($value);
                if ($quoted === false) throw new RuntimeException('Cannot quote backup value');
                $values[] = $quoted;
            }
            $tuple = '(' . implode(',', $values) . ')';
            // A single row is indivisible; otherwise cap batches at 256 KiB / 200 rows.
            if ($rows > 0 && ($rows >= 200 || strlen($batch) + strlen($tuple) + strlen($prefix) + 3 > 262144)) {
                echo $prefix . $batch . ";\n";
                $batch = '';
                $rows = 0;
            }
            $batch .= ($rows > 0 ? ",\n" : '') . $tuple;
            $rows++;
        }
        $statement->closeCursor();
        $statement = null;
        if ($rows > 0) echo $prefix . $batch . ";\n";
        echo "\n";
        unset($batch, $row, $values, $tuple);
    }
    $connection->commit();
    echo "SET FOREIGN_KEY_CHECKS=@DOYEL_OLD_FOREIGN_KEY_CHECKS;\nSET TIME_ZONE=@DOYEL_OLD_TIME_ZONE;\nSET SQL_MODE=@DOYEL_OLD_SQL_MODE;\n";
    echo "-- Doyel backup complete.\n";
} catch (Throwable $error) {
    // Close an unbuffered cursor before rollback; never run another query mid-cursor.
    try {
        if ($statement !== null) $statement->closeCursor();
        if ($connection !== null && $connection->inTransaction()) $connection->rollBack();
    } catch (Throwable $cleanupError) {
        if (function_exists('app_errors_report')) {
            app_errors_report($cleanupError);
        } else {
            error_log('Backup cleanup failed: ' . $cleanupError->getMessage());
        }
    }
    if (function_exists('app_errors_report')) {
        $reference = app_errors_report($error);
    } else {
        $reference = bin2hex(random_bytes(6));
        error_log('Backup failed [' . $reference . ']: ' . $error->getMessage());
    }
    if (!headers_sent()) {
        while (ob_get_level() > 0) ob_end_clean();
        header_remove('Content-Disposition');
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Backup could not be completed. Reference: ' . $reference . "\n";
    }
    // A partial response has no completion marker and must be discarded.
}
exit;
