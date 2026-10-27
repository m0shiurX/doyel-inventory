<?php
function db(): PDO
{
    static $connection;
    if ($connection === null) {
        $config = app_config();
        $connection = new PDO('mysql:host=' . $config['db_host'] . ';port=' . $config['db_port'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4', $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_STRINGIFY_FETCHES => true,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        ]);
    }
    return $connection;
}
function db_all(string $sql, array $params = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}
function db_one(string $sql, array $params = []): ?object
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->fetch() ?: null;
}
function db_value(string $sql, array $params = []): mixed
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $value = $statement->fetchColumn();
    return $value === false ? null : $value;
}
function db_exec(string $sql, array $params = []): int
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement->rowCount();
}
function db_insert_id(): string { return db()->lastInsertId(); }
function db_tx(callable $fn): mixed
{
    $connection = db();
    $connection->beginTransaction();
    try {
        $result = $fn();
        $connection->commit();
        return $result;
    } catch (Throwable $error) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $error;
    }
}
function now_sql(): string { return date('Y-m-d H:i:s'); }
