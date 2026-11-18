<?php

class SchemaModel
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function stats()
    {
        $meta = $this->pdo->query("SELECT VERSION() AS version, DATABASE() AS db_name")->fetch(PDO::FETCH_ASSOC);
        $tables = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = :db");
        $stmt->execute([':db' => DB_NAME]);

        return [
            'version'        => $meta['version'],
            'db_name'        => $meta['db_name'],
            'tables_count'   => count($tables),
            'tables_list'    => $tables,
            'routines_count' => (int) $stmt->fetchColumn()
        ];
    }

    public function listTables()
    {
        $stmt = $this->pdo->prepare(
            "SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_ROWS AS rows_est
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :db
             ORDER BY TABLE_NAME ASC"
        );
        $stmt->execute([':db' => DB_NAME]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function inspectTable($tableName)
    {
        $table = $this->identifier($tableName);
        $columns = $this->pdo->query("SHOW FULL COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);

        $orderSql = '';
        foreach ($columns as $col) {
            if ($col['Key'] === 'PRI') {
                $orderSql = " ORDER BY `{$col['Field']}` DESC";
                break;
            }
        }

        $rows = $this->pdo->query("SELECT * FROM `{$table}`{$orderSql} LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'columns' => $columns,
            'rows'    => $rows
        ];
    }

    public function createTable($tableName, array $columns)
    {
        $table = $this->identifier($tableName);
        if (count($columns) === 0) {
            throw new InvalidArgumentException('Defina ao menos uma coluna.');
        }

        $defs = [];
        $pks = [];

        foreach ($columns as $col) {
            $name = $this->identifier(isset($col['name']) ? $col['name'] : '');
            $defs[] = $this->columnSql(
                $name,
                isset($col['type']) ? $col['type'] : 'VARCHAR',
                isset($col['length']) ? $col['length'] : '',
                isset($col['nullable']) ? $col['nullable'] : '0',
                isset($col['default_val']) ? $col['default_val'] : '',
                isset($col['extra']) ? $col['extra'] : ''
            );

            if (!empty($col['primary'])) {
                $pks[] = "`{$name}`";
            }
        }

        if (count($pks) > 0) {
            $defs[] = 'PRIMARY KEY (' . implode(', ', $pks) . ')';
        }

        $sql = "CREATE TABLE `{$table}` (\n  " . implode(",\n  ", $defs) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }

    public function renameTable($oldName, $newName)
    {
        $old = $this->identifier($oldName);
        $new = $this->identifier($newName);
        $this->pdo->exec("RENAME TABLE `{$old}` TO `{$new}`");
    }

    public function dropTable($tableName)
    {
        $table = $this->identifier($tableName);
        $this->pdo->exec("DROP TABLE `{$table}`");
    }

    public function addColumn($tableName, array $spec)
    {
        $table = $this->identifier($tableName);
        $col = $this->identifier(isset($spec['col_name']) ? $spec['col_name'] : '');

        $def = $this->columnSql(
            $col,
            isset($spec['type']) ? $spec['type'] : 'VARCHAR',
            isset($spec['length']) ? $spec['length'] : '',
            isset($spec['nullable']) ? $spec['nullable'] : '1',
            isset($spec['default_val']) ? $spec['default_val'] : '',
            isset($spec['extra']) ? $spec['extra'] : ''
        );

        $this->pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$def}");
    }

    public function editColumn($tableName, array $spec)
    {
        $table = $this->identifier($tableName);
        $oldCol = $this->identifier(isset($spec['old_col_name']) ? $spec['old_col_name'] : '');
        $newCol = $this->identifier(isset($spec['new_col_name']) ? $spec['new_col_name'] : '');

        $def = $this->columnSql(
            $newCol,
            isset($spec['type']) ? $spec['type'] : 'VARCHAR',
            isset($spec['length']) ? $spec['length'] : '',
            isset($spec['nullable']) ? $spec['nullable'] : '1',
            isset($spec['default_val']) ? $spec['default_val'] : '',
            isset($spec['extra']) ? $spec['extra'] : ''
        );

        $this->pdo->exec("ALTER TABLE `{$table}` CHANGE COLUMN `{$oldCol}` {$def}");
    }

    public function dropColumn($tableName, $colName)
    {
        $table = $this->identifier($tableName);
        $col = $this->identifier($colName);
        $this->pdo->exec("ALTER TABLE `{$table}` DROP COLUMN `{$col}`");
    }

    public function insertRow($tableName, array $values)
    {
        $table = $this->identifier($tableName);
        $colsMap = $this->columnsMap($table);

        $fields = [];
        $placeholders = [];
        $params = [];
        $i = 0;

        foreach ($values as $col => $val) {
            if (!isset($colsMap[$col])) {
                continue;
            }
            $meta = $colsMap[$col];
            $autoInc = stripos($meta['Extra'], 'auto_increment') !== false;
            $autoTs = stripos((string) $meta['Default'], 'CURRENT_TIMESTAMP') !== false;

            if (($autoInc || $autoTs) && ($val === '' || $val === null)) {
                continue;
            }

            $param = ':v_' . $i++;
            $fields[] = "`{$col}`";
            $placeholders[] = $param;
            $params[$param] = $val;
        }

        if (count($fields) === 0) {
            $this->pdo->exec("INSERT INTO `{$table}` () VALUES ()");
            return;
        }

        $sql = "INSERT INTO `{$table}` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function updateRow($tableName, array $values, array $keys)
    {
        $table = $this->identifier($tableName);
        $colsMap = $this->columnsMap($table);

        $sets = [];
        $params = [];
        $i = 0;

        foreach ($values as $col => $val) {
            if (!isset($colsMap[$col])) {
                continue;
            }
            $param = ':u_' . $i++;
            $sets[] = "`{$col}` = {$param}";
            $params[$param] = $val;
        }

        if (count($sets) === 0) {
            throw new InvalidArgumentException('Nenhum campo válido para atualizar.');
        }

        $where = $this->buildWhere($colsMap, $keys, $params);
        $stmt = $this->pdo->prepare("UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE {$where} LIMIT 1");
        $stmt->execute($params);
    }

    public function deleteRow($tableName, array $keys)
    {
        $table = $this->identifier($tableName);
        $colsMap = $this->columnsMap($table);
        $params = [];
        $where = $this->buildWhere($colsMap, $keys, $params);

        $stmt = $this->pdo->prepare("DELETE FROM `{$table}` WHERE {$where} LIMIT 1");
        $stmt->execute($params);
    }

    public function listRoutines()
    {
        $stmt = $this->pdo->prepare(
            "SELECT ROUTINE_NAME AS name, ROUTINE_TYPE AS type, DTD_IDENTIFIER AS returns, CREATED AS created_at
             FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = :db
             ORDER BY ROUTINE_TYPE, ROUTINE_NAME"
        );
        $stmt->execute([':db' => DB_NAME]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRoutineCode($name, $type)
    {
        $routine = $this->identifier($name);
        $kind = strtoupper($type);
        if (!in_array($kind, ['PROCEDURE', 'FUNCTION'], true)) {
            throw new InvalidArgumentException('Tipo de routine inválido.');
        }

        $row = $this->pdo->query("SHOW CREATE {$kind} `{$routine}`")->fetch(PDO::FETCH_ASSOC);
        $key = ($kind === 'PROCEDURE') ? 'Create Procedure' : 'Create Function';
        return isset($row[$key]) ? $row[$key] : '';
    }

    public function dropRoutine($name, $type)
    {
        $routine = $this->identifier($name);
        $kind = strtoupper($type);
        if (!in_array($kind, ['PROCEDURE', 'FUNCTION'], true)) {
            throw new InvalidArgumentException('Tipo de routine inválido.');
        }
        $this->pdo->exec("DROP {$kind} IF EXISTS `{$routine}`");
    }

    public function executeSql($rawSql)
    {
        $sql = preg_replace('/^\s*DELIMITER\s+\S+\s*$/mi', '', trim($rawSql));
        $sql = preg_replace('/END\s*\$\$/i', 'END', $sql);
        $sql = trim($sql, " \t\n\r\0\x0B$");

        if ($sql === '') {
            throw new InvalidArgumentException('Informe uma instrução SQL.');
        }

        $start = microtime(true);
        $stmt = $this->pdo->query($sql);
        $elapsed = round((microtime(true) - $start) * 1000, 2);

        $rows = ($stmt->columnCount() > 0) ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $affected = $stmt->rowCount();
        $stmt->closeCursor();

        return [
            'elapsed_ms'    => $elapsed,
            'rows'          => $rows,
            'affected_rows' => $affected
        ];
    }

    private function columnsMap($table)
    {
        $cols = $this->pdo->query("SHOW FULL COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($cols as $col) {
            $map[$col['Field']] = $col;
        }
        return $map;
    }

    private function buildWhere(array $colsMap, array $keys, array &$params)
    {
        $parts = [];
        $i = 0;
        foreach ($keys as $col => $val) {
            if (!isset($colsMap[$col])) {
                continue;
            }
            if ($val === null) {
                $parts[] = "`{$col}` IS NULL";
            } else {
                $param = ':w_' . $i++;
                $parts[] = "`{$col}` = {$param}";
                $params[$param] = $val;
            }
        }
        if (count($parts) === 0) {
            throw new InvalidArgumentException('Chave de identificação ausente.');
        }
        return implode(' AND ', $parts);
    }

    private function identifier($name)
    {
        $clean = trim((string) $name);
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $clean)) {
            throw new InvalidArgumentException("Identificador inválido: '{$clean}'.");
        }
        return $clean;
    }

    private function columnSql($name, $type, $length, $nullable, $defaultVal, $extra)
    {
        $upperType = strtoupper(trim($type));
        $allowedTypes = ['INT', 'BIGINT', 'TINYINT', 'VARCHAR', 'CHAR', 'TEXT', 'DECIMAL', 'DATE', 'DATETIME', 'TIMESTAMP'];
        if (!in_array($upperType, $allowedTypes, true)) {
            throw new InvalidArgumentException("Tipo de dado não suportado: '{$upperType}'.");
        }

        $sql = "`{$name}` {$upperType}";
        $withLen = ['VARCHAR', 'CHAR', 'INT', 'BIGINT', 'TINYINT', 'DECIMAL'];

        if (in_array($upperType, $withLen, true) && trim((string) $length) !== '') {
            $len = preg_replace('/[^0-9,]/', '', (string) $length);
            if ($len !== '') {
                $sql .= "({$len})";
            }
        }

        $sql .= ($nullable === '1' || $nullable === true) ? ' NULL' : ' NOT NULL';

        if (trim((string) $defaultVal) !== '' && strtoupper($extra) !== 'AUTO_INCREMENT') {
            $defUpper = strtoupper(trim((string) $defaultVal));
            if ($defUpper === 'CURRENT_TIMESTAMP' || $defUpper === 'NULL') {
                $sql .= " DEFAULT {$defUpper}";
            } else {
                $sql .= " DEFAULT " . $this->pdo->quote($defaultVal);
            }
        }

        if (strtoupper(trim((string) $extra)) === 'AUTO_INCREMENT') {
            $sql .= ' AUTO_INCREMENT';
        }

        return $sql;
    }
}