<?php

class DatabaseController
{
    private $schema;

    public function __construct(SchemaModel $schema)
    {
        $this->schema = $schema;
    }

    public function handle($action)
    {
        try {
            switch ($action) {
                case 'dashboard_stats':
                    Response::json(['success' => true, 'data' => $this->schema->stats()]);
                    break;

                case 'list_tables':
                    Response::json(['success' => true, 'data' => $this->schema->listTables()]);
                    break;

                case 'inspect_table':
                    $table = isset($_GET['table']) ? $_GET['table'] : '';
                    Response::json(['success' => true, 'data' => $this->schema->inspectTable($table)]);
                    break;

                case 'create_table':
                    $table = isset($_POST['table_name']) ? $_POST['table_name'] : '';
                    $cols = json_decode(isset($_POST['columns']) ? $_POST['columns'] : '[]', true);
                    $this->schema->createTable($table, is_array($cols) ? $cols : []);
                    Response::json(['success' => true, 'message' => "Tabela '{$table}' criada."]);
                    break;

                case 'rename_table':
                    $this->schema->renameTable(
                        isset($_POST['old_name']) ? $_POST['old_name'] : '',
                        isset($_POST['new_name']) ? $_POST['new_name'] : ''
                    );
                    Response::json(['success' => true, 'message' => 'Tabela renomeada.']);
                    break;

                case 'drop_table':
                    $table = isset($_POST['table_name']) ? $_POST['table_name'] : '';
                    $this->schema->dropTable($table);
                    Response::json(['success' => true, 'message' => "Tabela '{$table}' excluída."]);
                    break;

                case 'add_column':
                    $this->schema->addColumn(isset($_POST['table_name']) ? $_POST['table_name'] : '', $_POST);
                    Response::json(['success' => true, 'message' => 'Coluna adicionada.']);
                    break;

                case 'edit_column':
                    $this->schema->editColumn(isset($_POST['table_name']) ? $_POST['table_name'] : '', $_POST);
                    Response::json(['success' => true, 'message' => 'Coluna atualizada.']);
                    break;

                case 'drop_column':
                    $this->schema->dropColumn(
                        isset($_POST['table_name']) ? $_POST['table_name'] : '',
                        isset($_POST['col_name']) ? $_POST['col_name'] : ''
                    );
                    Response::json(['success' => true, 'message' => 'Coluna removida.']);
                    break;

                case 'insert_row':
                    $values = json_decode(isset($_POST['values']) ? $_POST['values'] : '{}', true);
                    $this->schema->insertRow(
                        isset($_POST['table_name']) ? $_POST['table_name'] : '',
                        is_array($values) ? $values : []
                    );
                    Response::json(['success' => true, 'message' => 'Registro inserido.']);
                    break;

                case 'update_row':
                    $values = json_decode(isset($_POST['values']) ? $_POST['values'] : '{}', true);
                    $keys = json_decode(isset($_POST['keys']) ? $_POST['keys'] : '{}', true);
                    $this->schema->updateRow(
                        isset($_POST['table_name']) ? $_POST['table_name'] : '',
                        is_array($values) ? $values : [],
                        is_array($keys) ? $keys : []
                    );
                    Response::json(['success' => true, 'message' => 'Registro atualizado.']);
                    break;

                case 'delete_row':
                    $keys = json_decode(isset($_POST['keys']) ? $_POST['keys'] : '{}', true);
                    $this->schema->deleteRow(
                        isset($_POST['table_name']) ? $_POST['table_name'] : '',
                        is_array($keys) ? $keys : []
                    );
                    Response::json(['success' => true, 'message' => 'Registro excluído.']);
                    break;

                case 'list_routines':
                    Response::json(['success' => true, 'data' => $this->schema->listRoutines()]);
                    break;

                case 'get_routine_code':
                    $code = $this->schema->getRoutineCode(
                        isset($_GET['name']) ? $_GET['name'] : '',
                        isset($_GET['type']) ? $_GET['type'] : ''
                    );
                    Response::json(['success' => true, 'code' => $code]);
                    break;

                case 'drop_routine':
                    $this->schema->dropRoutine(
                        isset($_POST['name']) ? $_POST['name'] : '',
                        isset($_POST['type']) ? $_POST['type'] : ''
                    );
                    Response::json(['success' => true, 'message' => 'Routine excluída.']);
                    break;

                case 'run_sql':
                    $result = $this->schema->executeSql(isset($_POST['sql']) ? $_POST['sql'] : '');
                    Response::json(['success' => true, 'data' => $result]);
                    break;

                default:
                    Response::json(['success' => false, 'message' => 'Endpoint inválido.'], 404);
            }
        } catch (Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}