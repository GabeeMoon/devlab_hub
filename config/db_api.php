<?php
// API Backend para Dashboard & SQL Studio (DDL + CRUD de Registros) - PHP 5.6
require_once __DIR__ . '/db.php';

function sendJson($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function sanitizeIdentifier($name) {
    $clean = trim($name);
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $clean)) {
        return false;
    }
    return $clean;
}

function getTableColumnsMap(PDO $pdo, $tableName) {
    $stmt = $pdo->query("SHOW FULL COLUMNS FROM `{$tableName}`");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map = array();
    foreach ($cols as $col) {
        $map[$col['Field']] = $col;
    }
    return $map;
}

function buildWhereFromKeys(array $colsMap, array $keyData, array &$params) {
    $conditions = array();
    $idx = 0;

    foreach ($keyData as $col => $val) {
        if (!isset($colsMap[$col])) {
            continue;
        }
        $paramName = ':w_' . $idx++;
        if ($val === null) {
            $conditions[] = "`{$col}` IS NULL";
        } else {
            $conditions[] = "`{$col}` = {$paramName}";
            $params[$paramName] = $val;
        }
    }

    if (count($conditions) === 0) {
        return false;
    }

    return implode(' AND ', $conditions);
}

function buildColumnDefinition($name, $type, $length, $nullable, $defaultVal, $extra) {
    $sql = '`' . $name . '` ' . strtoupper($type);

    $typesWithLength = array('VARCHAR', 'CHAR', 'INT', 'BIGINT', 'TINYINT', 'DECIMAL');
    if (in_array(strtoupper($type), $typesWithLength) && trim($length) !== '') {
        $safeLen = preg_replace('/[^0-9,]/', '', $length);
        if ($safeLen !== '') {
            $sql .= '(' . $safeLen . ')';
        }
    }

    $sql .= ($nullable === '1' || $nullable === 'true') ? ' NULL' : ' NOT NULL';

    if (trim($defaultVal) !== '' && strtoupper($extra) !== 'AUTO_INCREMENT') {
        if (strtoupper(trim($defaultVal)) === 'CURRENT_TIMESTAMP' || strtoupper(trim($defaultVal)) === 'NULL') {
            $sql .= ' DEFAULT ' . strtoupper(trim($defaultVal));
        } else {
            $sql .= " DEFAULT '" . addslashes($defaultVal) . "'";
        }
    }

    if (strtoupper($extra) === 'AUTO_INCREMENT') {
        $sql .= ' AUTO_INCREMENT';
    }

    return $sql;
}

if (isset($_GET['db_action'])) {
    $action = $_GET['db_action'];

    try {
        $pdo = getDbConnection();

        // 1. Resumo para o Dashboard
        if ($action === 'dashboard_stats') {
            $info = $pdo->query("SELECT VERSION() AS version, DATABASE() AS db_name, NOW() AS server_time")->fetch();
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

            $stmtProc = $pdo->prepare("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = :db");
            $stmtProc->execute(array(':db' => DB_NAME));
            $routinesCount = (int) $stmtProc->fetchColumn();

            sendJson(array(
                'success' => true,
                'data' => array(
                    'version'        => $info['version'],
                    'db_name'        => $info['db_name'],
                    'server_time'    => $info['server_time'],
                    'tables_count'   => count($tables),
                    'tables_list'    => $tables,
                    'routines_count' => $routinesCount
                )
            ));
        }

        // 2. Listar Tabelas
        if ($action === 'list_tables') {
            $stmt = $pdo->query("SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_ROWS AS rows_est, CREATE_TIME AS created_at FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . addslashes(DB_NAME) . "' ORDER BY TABLE_NAME ASC");
            sendJson(array('success' => true, 'data' => $stmt->fetchAll()));
        }

        // 3. Criar Nova Tabela
        if ($action === 'create_table') {
            $tableName = sanitizeIdentifier(isset($_POST['table_name']) ? $_POST['table_name'] : '');
            $colsJson = isset($_POST['columns']) ? $_POST['columns'] : '[]';
            $columns = json_decode($colsJson, true);

            if (!$tableName) {
                sendJson(array('success' => false, 'message' => 'Nome de tabela inválido. Use apenas letras, números e underline.'));
            }
            if (!is_array($columns) || count($columns) === 0) {
                sendJson(array('success' => false, 'message' => 'Adicione pelo menos uma coluna à tabela.'));
            }

            $colDefs = array();
            $primaryKeys = array();

            foreach ($columns as $col) {
                $cName = sanitizeIdentifier(isset($col['name']) ? $col['name'] : '');
                if (!$cName) {
                    sendJson(array('success' => false, 'message' => 'Nome de coluna inválido: ' . (isset($col['name']) ? $col['name'] : '')));
                }
                $cType = isset($col['type']) ? $col['type'] : 'VARCHAR';
                $cLen  = isset($col['length']) ? $col['length'] : '';
                $cNull = isset($col['nullable']) ? $col['nullable'] : '0';
                $cDef  = isset($col['default_val']) ? $col['default_val'] : '';
                $cExt  = isset($col['extra']) ? $col['extra'] : '';
                $cPk   = isset($col['primary']) && ($col['primary'] === '1' || $col['primary'] === true);

                $colDefs[] = buildColumnDefinition($cName, $cType, $cLen, $cNull, $cDef, $cExt);
                if ($cPk) {
                    $primaryKeys[] = '`' . $cName . '`';
                }
            }

            if (count($primaryKeys) > 0) {
                $colDefs[] = 'PRIMARY KEY (' . implode(', ', $primaryKeys) . ')';
            }

            $sql = "CREATE TABLE `{$tableName}` (\n  " . implode(",\n  ", $colDefs) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $pdo->exec($sql);

            sendJson(array('success' => true, 'message' => "Tabela '{$tableName}' criada com sucesso!", 'sql' => $sql));
        }

        // 4. Renomear Tabela
        if ($action === 'rename_table') {
            $oldName = sanitizeIdentifier(isset($_POST['old_name']) ? $_POST['old_name'] : '');
            $newName = sanitizeIdentifier(isset($_POST['new_name']) ? $_POST['new_name'] : '');

            if (!$oldName || !$newName) {
                sendJson(array('success' => false, 'message' => 'Nome de tabela inválido.'));
            }

            $pdo->exec("RENAME TABLE `{$oldName}` TO `{$newName}`");
            sendJson(array('success' => true, 'message' => "Tabela renomeada de '{$oldName}' para '{$newName}'."));
        }

        // 5. Excluir Tabela
        if ($action === 'drop_table') {
            $tableName = sanitizeIdentifier(isset($_POST['table_name']) ? $_POST['table_name'] : '');
            if (!$tableName) {
                sendJson(array('success' => false, 'message' => 'Nome de tabela inválido.'));
            }

            $pdo->exec("DROP TABLE `{$tableName}`");
            sendJson(array('success' => true, 'message' => "Tabela '{$tableName}' excluída permanentemente."));
        }

        // 6. Detalhes da Tabela (Colunas + Registros)
        if ($action === 'describe_table') {
            $tableName = sanitizeIdentifier(isset($_GET['table']) ? $_GET['table'] : '');
            if (!$tableName) {
                sendJson(array('success' => false, 'message' => 'Tabela inválida.'));
            }

            $cols = $pdo->query("SHOW FULL COLUMNS FROM `{$tableName}`")->fetchAll(PDO::FETCH_ASSOC);

            // Ordena pela primeira coluna PK descendente se existir, para mostrar os registros mais recentes no topo
            $orderClause = '';
            foreach ($cols as $c) {
                if ($c['Key'] === 'PRI') {
                    $orderClause = " ORDER BY `{$c['Field']}` DESC";
                    break;
                }
            }

            $rows = $pdo->query("SELECT * FROM `{$tableName}`{$orderClause} LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

            sendJson(array(
                'success' => true,
                'columns' => $cols,
                'rows'    => $rows
            ));
        }

        // 7. Adicionar Coluna
        if ($action === 'add_column') {
            $tableName = sanitizeIdentifier(isset($_POST['table_name']) ? $_POST['table_name'] : '');
            $colName   = sanitizeIdentifier(isset($_POST['col_name']) ? $_POST['col_name'] : '');
            $type      = isset($_POST['type']) ? $_POST['type'] : 'VARCHAR';
            $length    = isset($_POST['length']) ? $_POST['length'] : '';
            $nullable  = isset($_POST['nullable']) ? $_POST['nullable'] : '1';
            $default   = isset($_POST['default_val']) ? $_POST['default_val'] : '';
            $extra     = isset($_POST['extra']) ? $_POST['extra'] : '';

            if (!$tableName || !$colName) {
                sendJson(array('success' => false, 'message' => 'Tabela ou nome de coluna inválido.'));
            }

            $def = buildColumnDefinition($colName, $type, $length, $nullable, $default, $extra);
            $pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN {$def}");

            sendJson(array('success' => true, 'message' => "Coluna '{$colName}' adicionada em '{$tableName}'."));
        }

        // 8. Editar / Alterar Coluna
        if ($action === 'edit_column') {
            $tableName = sanitizeIdentifier(isset($_POST['table_name']) ? $_POST['table_name'] : '');
            $oldCol    = sanitizeIdentifier(isset($_POST['old_col_name']) ? $_POST['old_col_name'] : '');
            $newCol    = sanitizeIdentifier(isset($_POST['new_col_name']) ? $_POST['new_col_name'] : '');
            $type      = isset($_POST['type']) ? $_POST['type'] : 'VARCHAR';
            $length    = isset($_POST['length']) ? $_POST['length'] : '';
            $nullable  = isset($_POST['nullable']) ? $_POST['nullable'] : '1';
            $default   = isset($_POST['default_val']) ? $_POST['default_val'] : '';
            $extra     = isset($_POST['extra']) ? $_POST['extra'] : '';

            if (!$tableName || !$oldCol || !$newCol) {
                sendJson(array('success' => false, 'message' => 'Identificador de tabela ou coluna inválido.'));
            }

            $def = buildColumnDefinition($newCol, $type, $length, $nullable, $default, $extra);
            $pdo->exec("ALTER TABLE `{$tableName}` CHANGE COLUMN `{$oldCol}` {$def}");

            sendJson(array('success' => true, 'message' => "Coluna '{$oldCol}' atualizada com sucesso."));
        }

        // 9. Excluir Coluna
        if ($action === 'drop_column') {
            $tableName = sanitizeIdentifier(isset($_POST['table_name']) ? $_POST['table_name'] : '');
            $colName   = sanitizeIdentifier(isset($_POST['col_name']) ? $_POST['col_name'] : '');

            if (!$tableName || !$colName) {
                sendJson(array('success' => false, 'message' => 'Identificador inválido.'));
            }

            $pdo->exec("ALTER TABLE `{$tableName}` DROP COLUMN `{$colName}`");
            sendJson(array('success' => true, 'message' => "Coluna '{$colName}' removida de '{$tableName}'."));
        }

        // 10. CRUD DE REGISTROS - Inserir Nova Linha
        if ($action === 'insert_row') {
            $tableName = sanitizeIdentifier(isset($_POST['table_name']) ? $_POST['table_name'] : '');
            $values = json_decode(isset($_POST['values']) ? $_POST['values'] : '{}', true);

            if (!$tableName || !is_array($values)) {
                sendJson(array('success' => false, 'message' => 'Dados inválidos para inserção.'));
            }

            $colsMap = getTableColumnsMap($pdo, $tableName);
            $fields = array();
            $placeholders = array();
            $params = array();
            $idx = 0;

            foreach ($values as $col => $val) {
                if (!isset($colsMap[$col])) {
                    continue;
                }
                $meta = $colsMap[$col];
                $isAutoInc = (stripos($meta['Extra'], 'auto_increment') !== false);
                $hasDefaultTimestamp = (stripos((string) $meta['Default'], 'CURRENT_TIMESTAMP') !== false);

                // Se for AUTO_INCREMENT ou TIMESTAMP automático e vier vazio, deixa o MySQL preencher
                if (($isAutoInc || $hasDefaultTimestamp) && ($val === '' || $val === null)) {
                    continue;
                }

                $fields[] = "`{$col}`";
                $pName = ':v_' . $idx++;
                $placeholders[] = $pName;
                $params[$pName] = $val;
            }

            if (count($fields) === 0) {
                $sql = "INSERT INTO `{$tableName}` () VALUES ()";
                $pdo->exec($sql);
            } else {
                $sql = "INSERT INTO `{$tableName}` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            sendJson(array('success' => true, 'message' => 'Registro inserido com sucesso!'));
        }

        // 11. CRUD DE REGISTROS - Atualizar Linha Existente
        if ($action === 'update_row') {
            $tableName = sanitizeIdentifier(isset($_POST['table_name']) ? $_POST['table_name'] : '');
            $values = json_decode(isset($_POST['values']) ? $_POST['values'] : '{}', true);
            $keys = json_decode(isset($_POST['keys']) ? $_POST['keys'] : '{}', true);

            if (!$tableName || !is_array($values) || !is_array($keys)) {
                sendJson(array('success' => false, 'message' => 'Dados inválidos para atualização.'));
            }

            $colsMap = getTableColumnsMap($pdo, $tableName);
            $setParts = array();
            $params = array();
            $idx = 0;

            foreach ($values as $col => $val) {
                if (!isset($colsMap[$col])) {
                    continue;
                }
                $pName = ':u_' . $idx++;
                $setParts[] = "`{$col}` = {$pName}";
                $params[$pName] = $val;
            }

            if (count($setParts) === 0) {
                sendJson(array('success' => false, 'message' => 'Nenhuma coluna válida informada para atualizar.'));
            }

            $whereSql = buildWhereFromKeys($colsMap, $keys, $params);
            if (!$whereSql) {
                sendJson(array('success' => false, 'message' => 'Não foi possível identificar a linha original para atualização.'));
            }

            $sql = "UPDATE `{$tableName}` SET " . implode(', ', $setParts) . " WHERE {$whereSql} LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            sendJson(array('success' => true, 'message' => 'Registro atualizado com sucesso!'));
        }

        // 12. CRUD DE REGISTROS - Excluir Linha
        if ($action === 'delete_row') {
            $tableName = sanitizeIdentifier(isset($_POST['table_name']) ? $_POST['table_name'] : '');
            $keys = json_decode(isset($_POST['keys']) ? $_POST['keys'] : '{}', true);

            if (!$tableName || !is_array($keys)) {
                sendJson(array('success' => false, 'message' => 'Identificador da linha inválido.'));
            }

            $colsMap = getTableColumnsMap($pdo, $tableName);
            $params = array();
            $whereSql = buildWhereFromKeys($colsMap, $keys, $params);

            if (!$whereSql) {
                sendJson(array('success' => false, 'message' => 'Não foi possível montar a condição WHERE para excluir a linha.'));
            }

            $sql = "DELETE FROM `{$tableName}` WHERE {$whereSql} LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            sendJson(array('success' => true, 'message' => 'Registro excluído com sucesso!'));
        }

        // 13. Listar Functions & Procedures
        if ($action === 'list_routines') {
            $stmt = $pdo->prepare("SELECT ROUTINE_NAME AS name, ROUTINE_TYPE AS type, DTD_IDENTIFIER AS returns, CREATED AS created_at FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = :db ORDER BY ROUTINE_TYPE, ROUTINE_NAME");
            $stmt->execute(array(':db' => DB_NAME));
            sendJson(array('success' => true, 'data' => $stmt->fetchAll()));
        }

        // 14. Ver Código de uma Function ou Procedure
        if ($action === 'get_routine_code') {
            $name = sanitizeIdentifier(isset($_GET['name']) ? $_GET['name'] : '');
            $type = strtoupper(isset($_GET['type']) ? $_GET['type'] : 'PROCEDURE');

            if (!$name || !in_array($type, array('PROCEDURE', 'FUNCTION'))) {
                sendJson(array('success' => false, 'message' => 'Routine inválida.'));
            }

            $row = $pdo->query("SHOW CREATE {$type} `{$name}`")->fetch(PDO::FETCH_ASSOC);
            $createKey = ($type === 'PROCEDURE') ? 'Create Procedure' : 'Create Function';
            $code = isset($row[$createKey]) ? $row[$createKey] : '';

            sendJson(array('success' => true, 'code' => $code));
        }

        // 15. Excluir Function ou Procedure
        if ($action === 'drop_routine') {
            $name = sanitizeIdentifier(isset($_POST['name']) ? $_POST['name'] : '');
            $type = strtoupper(isset($_POST['type']) ? $_POST['type'] : 'PROCEDURE');

            if (!$name || !in_array($type, array('PROCEDURE', 'FUNCTION'))) {
                sendJson(array('success' => false, 'message' => 'Routine inválida.'));
            }

            $pdo->exec("DROP {$type} IF EXISTS `{$name}`");
            sendJson(array('success' => true, 'message' => "{$type} '{$name}' excluída com sucesso."));
        }

        // 16. Executor Livre de SQL
        if ($action === 'run_sql') {
            $rawSql = isset($_POST['sql']) ? trim($_POST['sql']) : '';
            if ($rawSql === '') {
                sendJson(array('success' => false, 'message' => 'Digite um comando SQL antes de executar.'));
            }

            $cleanedSql = preg_replace('/^\s*DELIMITER\s+\S+\s*$/mi', '', $rawSql);
            $cleanedSql = preg_replace('/END\s*\$\$/i', 'END', $cleanedSql);
            $cleanedSql = trim($cleanedSql, " \t\n\r\0\x0B$");

            $startTime = microtime(true);
            $stmt = $pdo->query($cleanedSql);
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            $rows = array();
            if ($stmt->columnCount() > 0) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            $affected = $stmt->rowCount();
            $stmt->closeCursor();

            sendJson(array(
                'success'       => true,
                'message'       => "Comando executado em {$elapsedMs} ms.",
                'has_resultset' => ($stmt->columnCount() > 0),
                'rows'          => $rows,
                'affected_rows' => $affected
            ));
        }

    } catch (Exception $e) {
        sendJson(array('success' => false, 'message' => $e->getMessage()));
    }
}