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

    /**
     * Connect to the database.
     *
     * @return void
     */
    public static function connect() {
        try {
            self::$conn = new mysqli(self::$host, self::$username, self::$password, self::$dbname);
            if (self::$conn->connect_error) {
                throw new Exception("Connection failed: " . self::$conn->connect_error);
            }
        } catch (Exception $e) {
            die($e->getMessage());
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
     * Execute a SQL query and return the resulting mysqli_result.
     *
     * @param string $query The SQL query to execute.
     * @param array $params An associative array of parameters for prepared statement (optional).
     * @return mysqli_result|bool The resulting mysqli_result object or FALSE on failure.
     */
    public static function query($query, $params = []) {
        try {
            if (!self::$conn) {
                self::connect();
            }
            $stmt = self::$conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Query preparation failed: " . self::$conn->error);
            }
            if (!empty($params)) {
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
            }
            $result = $stmt->execute();
            if (!$result) {
                throw new Exception("Query execution failed: " . $stmt->error);
            }
            $out = $stmt->get_result();
			self::disconnect();
            return $out;
        } catch (Exception $e) {
            die($e->getMessage());
        } finally {
            if (self::$conn) {
                self::disconnect();
            }
        }
    }

    /**
     * Insert or update a record into the database based on whether the key already exists.
     *
     * @param string $table The name of the table.
     * @param array $data An associative array of column names and values to insert or update.
     * @return bool TRUE on success, FALSE on failure.
     */
    public static function save($table, $data) {
        $keys = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($keys), '?');
        $result = self::select($table, '*', ['name' => $data['name']]);
        if ($result && $result->num_rows > 0) {
            // Key exists, update the value
            $sql = "UPDATE $table SET value = ? WHERE name = ?";
            return self::query($sql, [$data['value'], $data['name']]);
        } else {
            // Key does not exist, insert a new record
            $sql = "INSERT INTO $table (name, value) VALUES (?, ?)";
            return self::query($sql, $values);
        }
    }

    /**
     * Insert a record into the database.
     *
     * @param string $table The name of the table.
     * @param array $data An associative array of column names and values to insert.
     * @return bool TRUE on success, FALSE on failure.
     */
    public static function insert($table, $data) {
        $keys = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($keys), '?');
        $sql = "INSERT INTO $table (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $placeholders) . ") ";
        $sql .= "ON DUPLICATE KEY UPDATE ";
        foreach ($keys as $key) {
            $sql .= "$key = VALUES($key), ";
        }
        $sql = rtrim($sql, ', ');
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
            $set[] = "$key = ?";
        }
        $set = implode(', ', $set);
        $sql = "UPDATE $table SET $set";
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "$key = ?";
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
        $sql = "DELETE FROM $table";
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "$key = ?";
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        $params = array_values($where);
        return self::query($sql, $params);
    }

    /**
     * Select records from the database based on specified conditions.
     *
     * @param string $table The name of the table.
     * @param string $columns The columns to select (comma separated).
     * @param array $where An associative array of conditions for the WHERE clause.
     * @return mysqli_result|bool The resulting mysqli_result object or FALSE on failure.
     */
    public static function select($table, $columns = '*', $where = [], $limit = null, $offset = null) {
        $sql = "SELECT $columns FROM $table";
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "$key = ?";
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
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
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (";
        foreach ($columns as $column_name => $column_definition) {
            $sql .= "$column_name $column_definition, ";
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
        $sql = "DROP TABLE IF EXISTS $table_name";
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
        $sql = "ALTER TABLE $table_name ";
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
        $sql = "TRUNCATE TABLE $table_name";
        return self::query($sql);
    }

    /**
     * Find records in the database based on specific conditions.
     *
     * @param string $table The name of the table.
     * @param array $conditions An associative array of conditions for the WHERE clause.
     * @param string $columns The columns to select (comma separated).
     * @return mysqli_result|bool The resulting mysqli_result object or FALSE on failure.
     */
    public static function findBy($table, $conditions, $columns = '*', $limit = null, $offset = null) {
        return self::select($table, $columns, $conditions, $limit, $offset);
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
