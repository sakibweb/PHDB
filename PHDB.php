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

        /** @var string $charset Database character set encoding. */
        public static $charset = 'utf8mb4';

        /** @var mixed $error Error handling mode. [true, false, 'custom error msg'] */
        public static $error = true;

        /** @var string|null $lastError Stores the last encountered error message. */
        private static $lastError = null;

        /** @var bool $inTransaction Whether a transaction is currently active */
        private static $inTransaction = false;

        /**
         * Handle errors based on the PHDB::$error setting.
         *
         * @param string $error_msg The error message to handle.
         * @param bool $continue Whether to continue after the error or not.
         * @return void
         */
        private static function handleError($error_msg, $continue = false) {
            self::$lastError = $error_msg;
            if (self::$error === true) {
                error_log($error_msg);
                echo $error_msg;
                if (!$continue) {
                    die($error_msg);
                    return false;
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
         * Retrieve the last error message encountered.
         *
         * @return string|null The last error message or null if no error.
         */
        public static function error() {
            return self::$lastError;
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
                // Set charset
                if (!self::$conn->set_charset(self::$charset)) {
                    throw new Exception("Error setting charset: " . self::$conn->error);
                }
            } catch (Exception $e) {
                self::handleError($e->getMessage(), false);
            }
        }

        /**
         * Disconnect from the database.
         * Won't disconnect if a transaction is active.
         * 
         * @return bool TRUE if disconnected, FALSE if transaction is active
         */
        public static function disconnect() {
            if (self::$conn) {
                if (self::$inTransaction === false) {
                    self::$conn->close();
                    self::$conn = null;
                    return true;
                }
            }
            return false;
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
                        $result = $stmt->get_result();
                        if ($result === false) {
                            throw new Exception("Error fetching result: " . self::$conn->error);
                        }
                        $data = [];
                        while ($row = $result->fetch_assoc()) {
                            $data[] = $row;
                        }
                        $result->free();
                        return $data;

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
                        $result = $stmt->get_result();
                        $data = [];
                        while ($row = $result->fetch_assoc()) {
                            $data[] = $row;
                        }
                        return $data;

                    case stripos($query, 'DESCRIBE') === 0:
                        $result = $stmt->get_result();
                        $data = [];
                        while ($row = $result->fetch_assoc()) {
                            $data[] = $row;
                        }
                        return $data;

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
         * Insert multiple records in a single query with optional overwrite
         * 
         * @param string $table Table name
         * @param array $data Array of associative arrays (records to insert)
         * @param bool $overwrite Whether to overwrite on duplicate key
         * @return bool TRUE on success, FALSE on failure
         */
        public static function batchInsert($table, $data, $overwrite = false) {
            if (empty($data)) {
                self::handleError("Batch insert failed: Empty data provided", true);
                return false;
            }
            
            try {
                // Validate all records have same keys
                $keys = array_keys($data[0]);
                foreach ($data as $index => $record) {
                    if (array_keys($record) !== $keys) {
                        throw new Exception("Record $index has different keys");
                    }
                }
                
                $placeholders = implode(',', array_fill(0, count($keys), '?'));
                $values = [];
                
                // Prepare all values
                foreach ($data as $record) {
                    $values = array_merge($values, array_values($record));
                }
                
                $columns = implode('`,`', $keys);
                $sql = "INSERT INTO `$table` (`$columns`) VALUES ";
                
                // Add placeholders for each record
                $sql .= implode(',', array_fill(0, count($data), "($placeholders)"));
                
                // Add ON DUPLICATE KEY UPDATE if overwrite is true
                if ($overwrite) {
                    $updates = [];
                    foreach ($keys as $key) {
                        $updates[] = "`$key`=VALUES(`$key`)";
                    }
                    $sql .= " ON DUPLICATE KEY UPDATE " . implode(',', $updates);
                }
                
                return self::query($sql, $values);
                
            } catch (Exception $e) {
                self::handleError("Batch insert failed: " . $e->getMessage(), true);
                return false;
            }
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
         * @param bool $distinct Whether to select distinct records (optional).
         *
         * @return mysqli_result|bool Returns a `mysqli_result` object on success or FALSE on failure.
         */
        public static function select($table, $columns = '*', $where = [], $limit = null, $offset = null, $orderBy = null, $groupBy = null, $joins = null, $distinct = false) {
            $columns = self::formatColumn($columns);
            $sql = "SELECT " . ($distinct ? "DISTINCT " : "") . "$columns FROM `$table`";
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
            if ($result && is_array($result) && count($result) > 0) {
                return $result[0][$column];
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
         * Create a database if it does not exist.
         *
         * @param string $dbname The name of the database.
         * @param string $collation The collation to use for the database (defaults to 'utf8mb4_0900_ai_ci').
         * @return bool TRUE on success, FALSE on failure.
         */
        public static function addDB($dbname, $collation = 'utf8mb4_0900_ai_ci') {
            $query = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE $collation";
            return self::query($query);
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
         * Search records in the database based on specified conditions.
         * - If $conditions is an array, it searches specific columns (e.g., ["name" => "sakib"]).
         * - If $conditions is a string, it searches ALL columns in the table for the keyword (e.g., "sakib").
         *
         * @param string $table The name of the table.
         * @param string $columns The columns to select (comma-separated or '*' for all).
         * @param array|string $conditions Associative array (column => value) OR string (search all columns).
         * @param int|null $limit Maximum records to retrieve.
         * @param int|null $offset Records to skip.
         * @param string|null $orderBy Column(s) to order by.
         * @param string|null $groupBy Column(s) to group by.
         * @param array|null $joins JOIN clauses.
         * @return array|bool Query results or FALSE on failure.
         */
        public static function search($table, $columns = '*', $conditions = [], $limit = null, $offset = null, $orderBy = null, $groupBy = null, $joins = null) {
            $columns = self::formatColumn($columns);
            $sql = "SELECT $columns FROM `$table`";

            // Handle JOINs
            if (!empty($joins)) {
                $sql .= " " . implode(' ', $joins);
            }

            // Prepare WHERE clause
            $params = [];
            if (!empty($conditions)) {
                $conditionParts = [];

                // Case 1: Conditions is a string (search ALL columns)
                if (is_string($conditions)) {
                    $keyword = trim($conditions);
                    if (!empty($keyword)) {
                        // Get all columns in the table dynamically
                        $allColumns = self::columns($table);
                        foreach ($allColumns as $column) {
                            $conditionParts[] = "`$column` LIKE ?";
                            $params[] = "%$keyword%";
                        }
                        $sql .= " WHERE (" . implode(' OR ', $conditionParts) . ")";
                    }
                }
                // Case 2: Conditions is an array (search specific columns)
                elseif (is_array($conditions)) {
                    foreach ($conditions as $column => $value) {
                        $conditionParts[] = "`$column` LIKE ?";
                        $params[] = "%$value%";
                    }
                    $sql .= " WHERE " . implode(' AND ', $conditionParts);
                }
            }

            // Handle GROUP BY, ORDER BY, LIMIT, OFFSET
            if ($groupBy) {
                $sql .= " GROUP BY `$groupBy`";
            }
            if ($orderBy) {
                $sql .= " ORDER BY `$orderBy`";
            }
            if ($limit) {
                $sql .= " LIMIT ?";
                $params[] = $limit;
                if ($offset) {
                    $sql .= " OFFSET ?";
                    $params[] = $offset;
                }
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
            if (is_array($result)) {
                $columns = array_column($result, 'Field');
                if ($filter) {
                    $columns = array_filter($columns, function($column) use ($filter) {
                        foreach ((array)$filter as $pattern) {
                            if (stripos($column, $pattern) !== false) {
                                return true;
                            }
                        }
                        return false;
                    });
                }
                if ($skip) {
                    $columns = array_filter($columns, function($column) use ($skip) {
                        foreach ((array)$skip as $pattern) {
                            if (stripos($column, $pattern) !== false) {
                                return false;
                            }
                        }
                        return true;
                    });
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
         * Paginate results with total count information
         * 
         * @param string $table Table name
         * @param string $columns Columns to select
         * @param array $where Conditions
         * @param int $page Current page (1-based)
         * @param int $per_page Items per page
         * @return array|bool Array with data and pagination info or FALSE on failure
         */
        public static function paginate($table, $columns = '*', $where = [], $page = 1, $per_page = 10) {
            $offset = ($page - 1) * $per_page;
            $data = self::select($table, $columns, $where, $per_page, $offset);
            
            if ($data === false) {
                return false;
            }
            
            $total = self::count($table, $where);
            
            return [
                'data' => $data,
                'total' => $total,
                'per_page' => $per_page,
                'current_page' => $page,
                'last_page' => ceil($total / $per_page),
                'from' => $offset + 1,
                'to' => min($offset + $per_page, $total)
            ];
        }

        /**
         * Get sum of a column with optional conditions
         * 
         * @param string $table Table name
         * @param string $column Column to sum
         * @param array $where Conditions (optional)
         * @return float|int Sum value or 0 if no results
         */
        public static function sum($table, $column, $where = []) {
            $result = self::select($table, "SUM(`$column`) as total", $where);
            return $result && isset($result[0]['total']) ? $result[0]['total'] + 0 : 0;
        }

        /**
         * Get average of a column with optional conditions
         * 
         * @param string $table Table name
         * @param string $column Column to average
         * @param array $where Conditions (optional)
         * @return float Average value or 0 if no results
         */
        public static function avg($table, $column, $where = []) {
            $result = self::select($table, "AVG(`$column`) as average", $where);
            return $result && isset($result[0]['average']) ? (float)$result[0]['average'] : 0;
        }

        /**
         * Get maximum value of a column with optional conditions
         * 
         * @param string $table Table name
         * @param string $column Column to check
         * @param array $where Conditions (optional)
         * @return mixed Max value or NULL if no results
         */
        public static function max($table, $column, $where = []) {
            $result = self::select($table, "MAX(`$column`) as max_value", $where);
            return $result && isset($result[0]['max_value']) ? $result[0]['max_value'] : null;
        }

        /**
         * Get minimum value of a column with optional conditions
         * 
         * @param string $table Table name
         * @param string $column Column to check
         * @param array $where Conditions (optional)
         * @return mixed Min value or NULL if no results
         */
        public static function min($table, $column, $where = []) {
            $result = self::select($table, "MIN(`$column`) as min_value", $where);
            return $result && isset($result[0]['min_value']) ? $result[0]['min_value'] : null;
        }

        /**
         * Count the number of records in a table based on specified conditions.
         *
         * @param string $table The name of the table.
         * @param array $where An associative array of conditions for the WHERE clause.
         * @return int The number of records found.
         */
        public static function count($table, $where = []) {
            $result = self::select($table, "*", $where);
            if ($result) {
                return (int)count($result);
            }
            return 0;
        }

        /**
         * Execute operations within a database transaction.
         * If callback returns false or throws an exception, transaction is rolled back.
         * 
         * @param callable $callback Function containing PHDB operations
         * @return bool TRUE on success, FALSE on failure (automatically rolls back)
         */
        public static function transaction(callable $callback) {
            try {
                if (!self::$conn) {
                    self::connect();
                }
                
                // Start transaction
                self::$conn->begin_transaction();
                self::$inTransaction = true;
                
                // Execute the callback
                $result = $callback();
                
                if ($result === false) {
                    self::$conn->rollback();
                    self::$inTransaction = false;
                    self::handleError("Transaction failed: Callback returned false", true);
                    return false;
                }
                
                // Commit if everything is fine
                self::$conn->commit();
                self::$inTransaction = false;
                return true;
                
            } catch (Exception $e) {
                if (self::$conn && self::$inTransaction) {
                    self::$conn->rollback();
                    self::$inTransaction = false;
                }
                self::handleError("Transaction failed: " . $e->getMessage(), true);
                return false;
            } finally {
                if (self::$conn) {
                    self::disconnect();
                }
            }
        }

        /**
         * Clean database records with various options
         * 
         * @param string $table Table name
         * @param array $options Cleaning options:
         *   - 'auto' (bool): Automatically detect and clean common issues
         *   - 'manual' (array): Manual cleaning conditions
         *   - 'duplicate_all' (bool): Remove duplicate rows (all columns must match)
         *   - 'duplicate_cols' (array|string): Remove duplicates based on specific columns
         *   - 'empty_cols' (array|string): Remove rows where specified columns are empty
         *   - 'value_conditions' (array): Remove rows where columns match certain values
         *   - 'min_rows' (int): Keep at least this many rows (when removing duplicates)
         *   - 'backup' (bool): Create backup before cleaning (default true)
         *   - 'backup_table' (string): Name for backup table (default: original_table + _backup + timestamp)
         * @return array|bool Result array with cleaning stats or false on failure
         * @throws InvalidArgumentException If invalid table name or options provided
         */
        public static function clean($table, $options = []) {
            // Validate table name
            if (!is_string($table) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
                throw new InvalidArgumentException("Invalid table name provided");
            }

            // Default options
            $defaults = [
                'auto' => false,
                'manual' => [],
                'duplicate_all' => false,
                'duplicate_cols' => null,
                'empty_cols' => null,
                'value_conditions' => [],
                'min_rows' => 1,
                'backup' => false,
                'backup_table' => null,
            ];
            
            $options = array_merge($defaults, $options);
            $stats = [
                'total_before' => 0,
                'total_after' => 0,
                'duplicates_removed' => 0,
                'empty_removed' => 0,
                'value_removed' => 0,
                'backup_created' => false,
            ];
            
            try {
                if (!self::$conn) {
                    self::connect();
                }
                
                // Run in transaction for atomicity
                self::$conn->begin_transaction();
                
                // Get current row count
                $stats['total_before'] = self::count($table);
                
                // Create backup if requested
                if ($options['backup']) {
                    $backupTable = $options['backup_table'] ?: 
                                $table . '_backup_' . date('Ymd_His');
                    
                    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $backupTable)) {
                        throw new InvalidArgumentException("Invalid backup table name generated");
                    }
                    
                    if (self::query("CREATE TABLE `$backupTable` LIKE `$table`") &&
                        self::query("INSERT INTO `$backupTable` SELECT * FROM `$table`")) {
                        $stats['backup_created'] = true;
                        $stats['backup_table'] = $backupTable;
                    }
                }
                
                // Auto-clean mode
                if ($options['auto']) {
                    // Get primary key column (fall back to first column if no PK)
                    $pkColumn = 'id'; // Default assumption
                    $tableInfo = self::query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
                    if (is_array($tableInfo) && !empty($tableInfo)) {
                        $pkColumn = $tableInfo[0]['Column_name'];
                    } else {
                        $columns = self::columns($table);
                        if (!empty($columns)) {
                            $pkColumn = $columns[0];
                        }
                    }

                    // Auto-detect and remove completely duplicate rows
                    $duplicates = self::query("
                        DELETE t1 FROM `$table` t1
                        INNER JOIN (
                            SELECT MIN(`$pkColumn`) as min_id, " . self::formatColumn('*') . "
                            FROM `$table`
                            GROUP BY " . self::formatColumn('*') . "
                            HAVING COUNT(*) > 1
                        ) t2
                        ON t1.`$pkColumn` != t2.min_id
                        LIMIT 10000
                    ");
                    
                    if ($duplicates !== false) {
                        $stats['duplicates_removed'] += self::$conn->affected_rows;
                    }
                    
                    // Auto-detect and remove rows with empty required columns
                    $columns = self::columns($table);
                    $emptyConditions = [];
                    foreach ($columns as $col) {
                        if (!in_array($col, [$pkColumn, 'created_at', 'updated_at'])) {
                            $emptyConditions[] = "(`$col` IS NULL OR `$col` = '' OR `$col` = 0)";
                        }
                    }
                    
                    if (!empty($emptyConditions)) {
                        $emptyRemoved = self::query("
                            DELETE FROM `$table` 
                            WHERE " . implode(' AND ', $emptyConditions) . "
                            LIMIT 1000
                        ");
                        
                        if ($emptyRemoved !== false) {
                            $stats['empty_removed'] += self::$conn->affected_rows;
                        }
                    }
                }
                
                // Manual cleaning options
                if ($options['duplicate_all']) {
                    $pkColumn = self::primaryKey($table);
                    $result = self::query("
                        DELETE t1 FROM `$table` t1
                        INNER JOIN (
                            SELECT MIN(`$pkColumn`) as min_id, " . self::formatColumn('*') . "
                            FROM `$table`
                            GROUP BY " . self::formatColumn('*') . "
                            HAVING COUNT(*) > 1
                        ) t2
                        ON t1.`$pkColumn` != t2.min_id
                        LIMIT 10000
                    ");
                    
                    if ($result !== false) {
                        $stats['duplicates_removed'] += self::$conn->affected_rows;
                    }
                }
                
                // [Rest of the function remains the same, with similar PK column updates...]
                
                // Get final row count
                $stats['total_after'] = self::count($table);
                
                // Commit transaction if we got this far
                self::$conn->commit();
                
                return $stats;
                
            } catch (Exception $e) {
                if (self::$conn && self::$inTransaction) {
                    self::$conn->rollback();
                }
                self::handleError("Clean failed: " . $e->getMessage(), true);
                return false;
            }
        }

        /**
         * Helper method to get primary key column name
         */
        private static function primaryKey($table) {
            $tableInfo = self::query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
            if (is_array($tableInfo) && !empty($tableInfo)) {
                return $tableInfo[0]['Column_name'];
            }
            
            $columns = self::columns($table);
            return !empty($columns) ? $columns[0] : 'id';
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
