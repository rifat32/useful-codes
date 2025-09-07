  public function downloadDatabase()
    {
        DB::beginTransaction();
        try {


              // Get DB config
        $connection = config('database.default');
        $db_config = config("database.connections.$connection");
        $db_name = $db_config['database'];

        $pdo = DB::connection()->getPdo();

        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

        $sql_dump = "-- Database Backup for `$db_name`\n\n";
        $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $batch_size = 100; // number of rows per INSERT

        foreach ($tables as $table) {
            // CREATE TABLE statement
            $create_stmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(\PDO::FETCH_ASSOC);
            $sql_dump .= $create_stmt['Create Table'] . ";\n\n";

            // Fetch all rows
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $columns = array_map(fn($col) => "`$col`", array_keys($rows[0]));
                $columns_list = implode(", ", $columns);

                // Batch insert to avoid memory / MySQL issues
                for ($i = 0; $i < count($rows); $i += $batch_size) {
                    $chunk = array_slice($rows, $i, $batch_size);
                    $values_arr = [];
                    foreach ($chunk as $row) {
                        $escaped = array_map(fn($val) => $val === null ? "NULL" : "'" . addslashes($val) . "'", $row);
                        $values_arr[] = "(" . implode(", ", $escaped) . ")";
                    }
                    $sql_dump .= "INSERT INTO `$table` ($columns_list) VALUES \n" . implode(",\n", $values_arr) . ";\n\n";
                }
            }
        }

        $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // File name
        $file_name = $db_name . '_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $file_path = storage_path("app/$file_name");
        file_put_contents($file_path, $sql_dump);

     
            DB::commit();
            // Return download response
            return response()->download($file_path)->deleteFileAfterSend(true);
        } catch (Exception $e) {
            DB::rollBack();
            return $e->getMessage();
        }
    }
