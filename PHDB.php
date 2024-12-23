<?php
/**
 * Class PHDB
 * Author: Sakibur Rahman @sakibweb
 * The PHDB class provides methods for basic database management operations.
 */
class PHDB {
    
    /** @var mysqli|null $conn The mysqli instance for database connection. */
    private static $conn = null;

    /** @var string|null $host Database host. */
    public static $host = null;

    /** @var string|null $username Database username. */
    public static $username = null;

    /** @var string|null $password Database password. */
    public static $password = null;

    /** @var string|null $dbname Database name. */
    public static $dbname = null;

    /** @var mixed $error Error handling mode. [true, false, 'custom error msg'] */
    public static $error = true;

    /**
     * Handle errors based on the PHDB::$error setting.
     *
     * @param string $error_msg The error message to handle.
     * @param bool $continue Whether to continue after the error or not.
     * @return void
     */
    private static function handleError($error_msg, $continue = false) {
        if (self::$error === true) {
            error_log($error_msg);
            echo $error_msg;
            if (!$continue) {
                die($error_msg);
                return [
                    'status' => false,
                    'message' => $error_msg,
                ];
            }
        } elseif (self::$error !== false) {
            $custom_msg = is_string(self::$error) ? self::$error : '[An error occurred] ';
            error_log($custom_msg);
            return [
                'status' => false,
                'message' => $custom_msg,
            ];
        }
    }

    /**
     * Connect to the database.
     *
     * @return void
     */
    public static function connect() {
        try {
            self::$conn = new mysqli(self::$host, self::$username, self::$password, self::$dbname);
            if (self::$conn->connect_error) {
                throw new Exception("Error: " . self::$conn->connect_error . "]");
            }
        } catch (Exception $e) {
            self::handleError($e->getMessage(), false);
        }
    }

    /**
     * Disconnect from the database.
     *
     * @return void
     */
    public static function disconnect() {
        if (self::$conn) {
            self::$conn->close();
            self::$conn = null;
        }
    }

    /**
     * Check if a string contains potentially malicious SQL injection patterns.
     *
     * @param string $input The input string to check.
     * @return bool True if the input is potentially malicious, false otherwise.
     */
    private static function isPotentiallyMalicious($input) {
        $patterns = [
            '/--/',                         // SQL comment
            '/;/',                          // SQL command terminator
            '/\/\*/',                       // SQL comment start
            '/union\s+select/i',            // UNION SELECT (commonly used in SQL injection)
            '/sleep\(\d+\)/i',              // SLEEP() function (time-based injection)
            '/benchmark\(/i',               // BENCHMARK() function (time-based injection)
            '/\bOR\b\s+\d+\s*=\s*\d+/i',    // OR 1=1 or similar
            '/exec\s+xp_/i'                 // Executing stored procedures in SQL Server
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                error_log('Potential SQL injection attempt detected.');
                return true;
            }
        }
        return false;
    }

    /**
     * Format the columns for the SQL query by adding backticks where necessary.
     *
     * @param string $columns The columns to format, specified as a comma-separated string.
     * @return string The formatted columns string with backticks added.
     */
    private static function formatColumn($columns) {
        if ($columns !== '*' && !empty($columns)) {
            $columns = str_replace('`', '', $columns);
            $columnsArray = preg_split('/\s*,\s*/', $columns);
            return implode(', ', array_map(function($column) {
                return "`$column`";
            }, $columnsArray));
        }
        return $columns;
    }

    /**
     * Execute a SQL query and return the resulting mysqli_result.
     *
     * @param string $query The SQL query to execute.
     * @param array $params An associative array of parameters for prepared statement (optional).
     * @return mysqli_result|bool The resulting mysqli_result object or FALSE on failure.
     */
    public static function query($query, $params = []) {
        if (self::isPotentiallyMalicious($query)) {
            return [
                'status' => false,
                'message' => 'Potential SQL injection attempt detected.',
            ];
        } else {
            // $query = @filter_var($query, FILTER_SANITIZE_STRING);
            $query = @htmlspecialchars(trim($query), ENT_QUOTES, 'UTF-8');
            $query = @htmlspecialchars($query, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        try {
            if (!self::$conn) {
                self::connect();
            }
            $stmt = self::$conn->prepare($query);
            if (!$stmt) {
                throw new Exception("[Error: " . self::$conn->error . "] ");
            }
            if (!empty($params)) {
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
            }
            $result = $stmt->execute();
            if (!$result) {
                throw new Exception("Error: " . $stmt->error . "] ");
            }
            switch (true) {
                case stripos($query, 'SELECT') === 0:
                    return $stmt->get_result();
    
                case stripos($query, 'INSERT') === 0:
                    return true;
                    
                case stripos($query, 'UPDATE') === 0:
                    return true;
    
                case stripos($query, 'DELETE') === 0:
                    return true;
    
                case stripos($query, 'CREATE') === 0:
                case stripos($query, 'ALTER') === 0:
                case stripos($query, 'DROP') === 0:
                    return true;

                case stripos($query, 'SHOW COLUMNS') === 0:
                case stripos($query, 'SHOW TABLES') === 0:
                case stripos($query, 'SHOW DATABASES') === 0:
                    return $stmt->get_result();
    
                case stripos($query, 'DESCRIBE') === 0:
                    return $stmt->get_result();

                default:
                    return false;
            }
        } catch (Exception $e) {
            self::handleError($e->getMessage(), true);
            return false;
        } finally {
            if (self::$conn) {
                self::disconnect();
            }
        }
    }

    /**
     * Insert or update a record into the database based on whether the specified unique key or keys exist.
     *
     * @param string $table The name of the table.
     * @param array $data An associative array of column names and values to insert or update.
     * @param array|null $check An array of column names to check for existing records.
     * @param string|array|null $uniqueKey The column names to check for uniqueness (defaults to 'id').
     * @return bool TRUE on success, FALSE on failure.
     */
    public static function save($table, $data, $check = null, $uniqueKey = null) {
        // Ensure that the unique key is set
        if ($uniqueKey !== null) {
            $uniqueKeys = (array) $uniqueKey;
            foreach ($uniqueKeys as $key) {
                if (!isset($data[$key])) {
                    throw new InvalidArgumentException("The data array must contain the '$key' key.");
                }
            }
            $whereConditions = implode(' AND ', array_map(fn($key) => "`$key` = ?", $uniqueKeys));
            $uniqueValues = array_intersect_key($data, array_flip($uniqueKeys));
            $values = array_values($data);
        } else {
            $whereConditions = '1=0';
            $uniqueValues = [];
            $values = array_values($data);
        }

        // Check for existing records based on the $check parameter
        if ($check !== null) {
            $checkConditions = implode(' AND ', array_map(fn($key) => "`$key` = ?", $check));
            $checkValues = array_intersect_key($data, array_flip($check));
            $result = self::select($table, '*', $checkValues);
        } else {
            $result = self::select($table, '*', $uniqueValues);
        }

        if ($result && $result->num_rows > 0) {
            // Update existing record
            $sql = "UPDATE `$table` SET " . implode(',', array_map(fn($key) => "`$key` = ?", array_keys($data))) . " WHERE $whereConditions";
            $values = array_merge(array_values($uniqueValues), $values);
        } else {
            // Insert new record
            $sql = "INSERT INTO `$table` (" . implode(',', array_map(fn($key) => "`$key`", array_keys($data))) . ") 
                    VALUES (" . implode(',', array_fill(0, count($data), '?')) . ")";
        }
        
        return self::query($sql, $values);
    }

    /**
     * Insert a record into the database.
     *
     * This method inserts a new record into the specified table. If an entry with the same unique key
     * (like 'name') already exists and the $overwrite parameter is set to true, it will update the existing
     * record instead of inserting a new one. If $overwrite is false, it will insert a new record or update
     * the existing record based on the unique key using ON DUPLICATE KEY UPDATE.
     *
     * @param string $table The name of the table.
     * @param array $data An associative array of column names and values to insert.
     * @param bool $overwrite Whether to overwrite existing records (default is false).
     * @return bool TRUE on success, FALSE on failure.
     */
    public static function insert($table, $data, $overwrite = false) {
        $keys = array_map(function($key) { return "`$key`"; }, array_keys($data));
        $values = array_values($data);
        $placeholders = array_fill(0, count($keys), '?');
        if ($overwrite) {
            $result = self::select($table, '*', ['name' => $data['name']]);
            if ($result && $result->num_rows > 0) {
                $sql = "UPDATE `$table` SET " . implode(', ', array_map(function($key) { return "`$key` = ?"; }, array_keys($data))) . " WHERE `name` = ?";
                return self::query($sql, array_merge($values, [$data['name']]));
            }
        }
        $sql = "INSERT INTO `$table` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $placeholders) . ") ";
        $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', array_map(function($key) { return "`$key` = VALUES(`$key`)"; }, array_keys($data)));        
        return self::query($sql, $values);
    }
	
    /**
     * Update records in the database based on specified conditions.
     *
     * @param string $table The name of the table.
     * @param array $data An associative array of column names and values to update.
     * @param array $where An associative array of conditions for the WHERE clause.
     * @return bool TRUE on success, FALSE on failure.
     */
    public static function update($table, $data, $where = []) {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "`$key` = ?";
        }
        $set = implode(', ', $set);
        $sql = "UPDATE `$table` SET $set";
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "`$key` = ?";
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        $params = array_merge(array_values($data), array_values($where));
        return self::query($sql, $params);
    }

    /**
     * Delete records from the database based on specified conditions.
     *
     * @param string $table The name of the table.
     * @param array $where An associative array of conditions for the WHERE clause.
     * @return bool TRUE on success, FALSE on failure.
     */
    public static function delete($table, $where = []) {
        $sql = "DELETE FROM `$table`";
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "`$key` = ?";
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        $params = array_values($where);
        return self::query($sql, $params);
    }

    /**
     * Select records from the database based on specified conditions.
     *
     * @param string $table The name of the table from which to select records.
     * @param string $columns The columns to select, specified as a comma-separated string (defaults to '*').
     * @param array $where An associative array of conditions for the WHERE clause. The key is the column name, and the value is the condition's value.
     * @param int|null $limit The maximum number of records to retrieve (optional).
     * @param int|null $offset The number of records to skip before starting to retrieve records (optional).
     * @param string|null $orderBy The column(s) by which to order the result set, optionally including ASC/DESC (optional).
     * @param string|null $groupBy The column(s) by which to group the result set (optional).
     * @param array|null $joins An array of JOIN clauses to be included in the query (optional).
     * 
     * @return mysqli_result|bool Returns a `mysqli_result` object on success or FALSE on failure.
     */
    public static function select($table, $columns = '*', $where = [], $limit = null, $offset = null, $orderBy = null, $groupBy = null, $joins = null) {
        $columns = self::formatColumn($columns);
        $sql = "SELECT $columns FROM `$table`";
        if (!empty($joins)) {
            $sql .= " " . implode(' ', $joins);
        }
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "`$key` = ?";
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        if ($groupBy) {
            $sql .= " GROUP BY `$groupBy`";
        }
        if ($orderBy) {
            $sql .= " ORDER BY `$orderBy`";
        }
        if ($limit) {
            $sql .= " LIMIT ?";
            if ($offset) {
                $sql .= " OFFSET ?";
                $params = array_merge(array_values($where), [$limit, $offset]);
            } else {
                $params = array_merge(array_values($where), [$limit]);
            }
        } else {
            $params = array_values($where);
        }
        return self::query($sql, $params);
    }

    /**
     * Perform a specific selection from the database.
     *
     * @param string $query The SQL query to execute.
     * @param array $params An associative array of parameters for prepared statement (optional).
     * @return mysqli_result|bool The resulting mysqli_result object or FALSE on failure.
     */
    public static function specificSelect($query, $params = []) {
        return self::query($query, $params);
    }

    /**
     * Get a single value from the database.
     *
     * @param string $table The name of the table.
     * @param string $column The column to select.
     * @param array $where An associative array of conditions for the WHERE clause.
     * @return mixed The value selected from the database or NULL if not found.
     */
    public static function getValue($table, $column, $where = []) {
        $result = self::select($table, $column, $where);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row[$column];
        } else {
            return null;
        }
    }

    /**
     * Get a specific value from the database.
     *
     * @param string $query The SQL query to execute.
     * @param array $params An associative array of parameters for prepared statement (optional).
     * @return mixed The value selected from the database or NULL if not found.
     */
    public static function getSpecificValue($query, $params = []) {
        $result = self::specificSelect($query, $params);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return array_values($row)[0];
        } else {
            return null;
        }
    }

    /**
     * Create a new table in the database.
     *
     * @param string $table_name The name of the table to create.
     * @param array $columns An associative array defining the columns of the table.
     * @return bool TRUE on success, FALSE on failure.
     */
    public static function createTable($table_name, $columns) {
        $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (";
        foreach ($columns as $column_name => $column_definition) {
            $sql .= "`$column_name` $column_definition, ";
        }
        $sql = rtrim($sql, ', ') . ")";
        return self::query($sql);
    }

    /**
     * Drop a table from the database.
     *
     * @param string $table_name The name of the table to drop.
     * @return bool TRUE on success, FALSE on failure.
     */
    public static function dropTable($table_name) {
        $sql = "DROP TABLE IF EXISTS `$table_name`";
        return self::query($sql);
    }

    /**
     * Alter a table in the database.
     *
     * @param string $table_name The name of the table to alter.
     * @param array $changes An array of SQL statements for alterations.
     * @return bool TRUE on success, FALSE on failure.
     */
    public static function alterTable($table_name, $changes) {
        $sql = "ALTER TABLE `$table_name` ";
        $sql .= implode(', ', $changes);
        return self::query($sql);
    }

    /**
     * Truncate a table in the database.
     *
     * @param string $table_name The name of the table to truncate.
     * @return bool TRUE on success, FALSE on failure.
     */
    public static function truncateTable($table_name) {
        $sql = "TRUNCATE TABLE `$table_name`";
        return self::query($sql);
    }

    /**
     * Find records in the database based on specific conditions.
     *
     * @param string $table The name of the table.
     * @param string $columns The columns to select (comma separated).
     * @param array $conditions An associative array of conditions for the WHERE clause.
     * @return mysqli_result|bool The resulting mysqli_result object or FALSE on failure.
     */
    public static function findBy($table, $columns = '*', $conditions, $limit = null, $offset = null) {
        return self::select($table, $columns, $conditions, $limit, $offset);
    }

    /**
     * Search records in the database based on specified conditions using LIKE.
     *
     * @param string $table The name of the table from which to select records.
     * @param string $columns The columns to select, specified as a comma-separated string.
     * @param array $conditions An associative array of conditions for the WHERE clause. The key is the column name, and the value is the condition's value.
     * @param int|null $limit The maximum number of records to retrieve (optional).
     * @param int|null $offset The number of records to skip before starting to retrieve records (optional).
     * @param string|null $orderBy The column(s) by which to order the result set (optional).
     * @param string|null $groupBy The column(s) by which to group the result set (optional).
     * @param array|null $joins An array of JOIN clauses to be included in the query (optional).
     * 
     * @return mysqli_result|bool Returns a `mysqli_result` object on success or FALSE on failure.
     */
    public static function search($table, $columns, $conditions, $limit = null, $offset = null, $orderBy = null, $groupBy = null, $joins = null) {
        $columns = self::formatColumn($columns);
        $sql = "SELECT $columns FROM `$table`";
        
        if (!empty($joins)) {
            $sql .= " " . implode(' ', $joins);
        }

        if (!empty($conditions)) {
            $conditionParts = [];
            foreach ($conditions as $column => $value) {
                $conditionParts[] = "`$column` LIKE ?";
            }
            $sql .= " WHERE " . implode(' AND ', $conditionParts);
        }

        if ($groupBy) {
            $sql .= " GROUP BY `$groupBy`";
        }
        if ($orderBy) {
            $sql .= " ORDER BY `$orderBy`";
        }
        
        if ($limit) {
            $sql .= " LIMIT ?";
            if ($offset) {
                $sql .= " OFFSET ?";
                $params = array_merge(array_map(function($v) { return "%$v%"; }, array_values($conditions)), [$limit, $offset]);
            } else {
                $params = array_merge(array_map(function($v) { return "%$v%"; }, array_values($conditions)), [$limit]);
            }
        } else {
            $params = array_map(function($v) { return "%$v%"; }, array_values($conditions));
        }
        
        return self::query($sql, $params);
    }

    /**
     * Get the available columns from a specified table in the database.
     *
     * @param string $table The name of the table.
     * @param string|array|null $filter Optional. A pattern or array of patterns to filter column names using 'LIKE'.
     * @param string|array|null $skip Optional. A pattern or array of patterns to exclude column names using 'LIKE'.
     * @return array Returns an array of column names on success or an empty array on failure.
     */
    public static function columns($table, $filter = null, $skip = null) {
        $sql = "SHOW COLUMNS FROM `$table`";
        $result = self::query($sql);
        if ($result instanceof mysqli_result) {
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
            $filterColumns = function($columns, $patterns) {
                return array_filter($columns, function($column) use ($patterns) {
                    foreach ((array)$patterns as $pattern) {
                        if (strpos(strtolower($column), strtolower($pattern)) !== false) {
                            return true;
                        }
                    }
                    return false;
                });
            };
            if ($filter) {
                $columns = $filterColumns($columns, $filter);
            }
            if ($skip) {
                $skipColumns = function($columns, $patterns) {
                    return array_filter($columns, function($column) use ($patterns) {
                        foreach ((array)$patterns as $pattern) {
                            if (strpos(strtolower($column), strtolower($pattern)) !== false) {
                                return false;
                            }
                        }
                        return true;
                    });
                };
                $columns = $skipColumns($columns, $skip);
            }
            return array_values($columns);
        }
        return [];
    }

    /**
     * Delete records from the database based on specific conditions.
     *
     * @param string $table The name of the table.
     * @param array $conditions An associative array of conditions for the WHERE clause.
     * @return bool TRUE on success, FALSE on failure.
     */
    public static function deleteBy($table, $conditions) {
        return self::delete($table, $conditions);
    }

    /**
     * Paginate results from the database.
     *
     * @param string $table The name of the table.
     * @param string $columns The columns to select (comma separated).
     * @param array $where An associative array of conditions for the WHERE clause.
     * @param int $page The page number for pagination (1-based).
     * @param int $per_page The number of records per page.
     * @return mysqli_result|bool The resulting mysqli_result object or FALSE on failure.
     */
    public static function paginate($table, $columns = '*', $where = [], $page = 1, $per_page = 10) {
        $offset = ($page - 1) * $per_page;
        return self::select($table, $columns, $where, $per_page, $offset);
    }

    /**
     * Count the number of records in a table based on specified conditions.
     *
     * @param string $table The name of the table.
     * @param array $where An associative array of conditions for the WHERE clause.
     * @return int The number of records found.
     */
    public static function count($table, $where = []) {
        $result = self::select($table, "COUNT(*) as count", $where);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return (int)$row['count'];
        }
        return 0;
    }

    /**
     * Close the database connection.
     *
     * @return void
     */
    public static function close() {
        if (self::$conn) {
            self::$conn->close();
            self::$conn = null;
        }
    }
}
?>
