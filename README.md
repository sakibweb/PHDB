# PHDB - PHP Database Management Library

**PHDB** is a lightweight PHP library designed to simplify common database interactions using the MySQLi extension. It provides a straightforward, static class interface for connecting to a MySQL database, performing CRUD (Create, Read, Update, Delete) operations, managing tables, executing raw queries, handling transactions, and more, with built-in error handling and basic SQL injection protection mechanisms.

## Key Features

*   **Connection Management:** Easily connect to and disconnect from your MySQL database using static configuration.
*   **Error Handling:** Configurable error handling modes (die on error, return false, custom message) with access to the last error message.
*   **Security Basics:**
    *   Basic checks against common SQL injection patterns in raw queries.
    *   Automatic escaping and backtick quoting of table/column identifiers.
    *   Strong recommendation and support for **prepared statements** via the `query()` method for optimal security.
*   **CRUD Operations:** Simplified methods for `insert()`, `update()`, `delete()`, and flexible `select()` queries.
*   **Advanced Querying:**
    *   Execute raw SQL with prepared statements (`query()`).
    *   Fetch specific result sets (`specificSelect()`).
    *   Retrieve single scalar values (`getValue()`, `getSpecificValue()`).
    *   Search across multiple columns (`search()`).
    *   Find records by specific criteria (`findBy()`).
*   **Data Aggregation:** Calculate `count()`, `sum()`, `avg()`, `max()`, and `min()` directly.
*   **Table Management:** Methods to `createTable()`, `dropTable()`, `alterTable()`, `truncateTable()`, and `addDB()`.
*   **Schema Introspection:** Get table column names (`columns()`).
*   **Batch Operations:** Efficiently insert multiple records (`batchInsert()`).
*   **Transactions:** Execute multiple operations atomically using `transaction()`.
*   **Utility Functions:**
    *   Paginate query results easily (`paginate()`).
    *   Clean database tables by removing duplicates or based on conditions (`clean()`).
    *   Simplified insert/update logic (`save()`).

## Installation

PHDB is designed to be simple. No package manager is required.

1.  Download the `PHDB.php` file.
2.  Include it in your PHP project:

```php
<?php
require_once 'PHDB.php';
?>
```

## Usage

### Configuration

Before using any database methods, configure your database connection details using the static properties. This typically happens once in your application's bootstrap or configuration file.

```php
<?php
require_once 'PHDB.php';

// Database Credentials
PHDB::$host = "localhost";         // Or your DB host IP/domain
PHDB::$username = "your_db_user";
PHDB::$password = "your_db_password";
PHDB::$dbname = "your_database_name";

// Optional: Set Character Set (Defaults to utf8mb4)
// PHDB::$charset = 'utf8';

// Optional: Customize Error Handling (Defaults to true)
// PHDB::$error = true;  // Default: Prints error and dies (Good for development)
// PHDB::$error = false; // Disables direct output, methods return false/specific error structure on failure. Use PHDB::error() to get the message. (Good for production)
// PHDB::$error = "Oops! A database error occurred."; // Shows a custom message and dies.
?>
```

### Connecting and Disconnecting

Connection is usually handled automatically when you call a database method. However, you can explicitly connect or disconnect.

```php
// Explicitly connect (optional, done automatically by most methods if needed)
PHDB::connect();

// ... perform database operations ...

// Explicitly disconnect (optional, done automatically after most queries unless in a transaction)
// Note: Will not disconnect if a transaction is active.
$disconnected = PHDB::disconnect();

// Explicitly close the connection (alternative to disconnect, forces closure even if auto-disconnect failed)
PHDB::close();
```

### Executing Raw Queries (`query()`)

Use `query()` for executing any SQL statement, especially useful for complex queries or operations not covered by helper methods. **Use parameterized queries to prevent SQL injection.**

**Return Values:**
*   **SELECT, SHOW, DESCRIBE:** Returns an array of associative arrays representing the rows, or `false` on failure.
*   **INSERT, UPDATE, DELETE, CREATE, ALTER, DROP:** Returns `true` on success, `false` on failure.
*   **On Potential Injection / Error:** Returns `false` or an array `['status' => false, 'message' => '...']` if `PHDB::$error` is not `true`.

```php
// SELECT using Prepared Statement (Recommended for Security)
$userId = 1;
$status = 'active';
$results = PHDB::query("SELECT id, name FROM users WHERE id = ? AND status = ?", [$userId, $status]);

if ($results === false) {
    echo "Query failed: " . PHDB::error();
} elseif (empty($results)) {
    echo "No users found.";
} else {
    echo "Users Found:<br>";
    foreach ($results as $row) {
        echo "ID: " . $row['id'] . ", Name: " . $row['name'] . "<br>";
        // Output: e.g., ID: 1, Name: John Doe
    }
}

// UPDATE using Prepared Statement
$newStatus = 'inactive';
$userIdToUpdate = 5;
$success = PHDB::query("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $userIdToUpdate]);

if ($success) {
    echo "User updated successfully.";
} else {
    echo "Update failed: " . PHDB::error();
}

// CREATE TABLE (DDL)
$success = PHDB::query("CREATE TABLE IF NOT EXISTS logs (id INT AUTO_INCREMENT PRIMARY KEY, message TEXT, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP)");
if ($success) {
    echo "Table 'logs' created or already exists.";
} else {
    echo "Failed to create table: " . PHDB::error();
}
```

### Selecting Data (`select()`)

A flexible method to build `SELECT` queries.

**Return Value:** An array of associative arrays, or `false` on failure.

```php
// Select all columns from 'users'
$allUsers = PHDB::select('users');

// Select specific columns with a WHERE clause
$activeUsers = PHDB::select('users', 'id, email', ['status' => 'active']);

// Select with WHERE, ORDER BY, LIMIT, and OFFSET
$usersPage2 = PHDB::select(
    'users',                    // Table
    'name, signup_date',        // Columns
    ['status' => 'active'],     // Where conditions
    10,                         // Limit
    10,                         // Offset (10 * (page 2 - 1))
    'signup_date DESC'          // Order By
);

// Select with a JOIN
$joins = [
    "INNER JOIN profiles ON users.id = profiles.user_id"
];
$usersWithProfiles = PHDB::select(
    'users',                                    // Main table
    'users.id, users.name, profiles.avatar',    // Columns (specify table for clarity)
    ['users.status' => 'active'],               // Where
    null, null, null, null,                     // Limit, Offset, OrderBy, GroupBy
    $joins                                      // Joins array
);

if ($usersWithProfiles) {
    foreach ($usersWithProfiles as $user) {
        echo $user['name'] . ' - Avatar: ' . $user['avatar'] . '<br>';
    }
}

// Select distinct cities
$distinctCities = PHDB::select('users', 'city', [], null, null, null, null, null, true); // Last arg true for DISTINCT
```

### Specific Select (`specificSelect()`)

Similar to `query()` but specifically intended for `SELECT` statements, often used when `select()` doesn't fit the query structure easily. Uses prepared statements.

**Return Value:** An array of associative arrays, or `false` on failure.

```php
$minAge = 18;
$query = "SELECT name, email FROM users WHERE age > ? ORDER BY name ASC";
$results = PHDB::specificSelect($query, [$minAge]);

if ($results) {
    foreach ($results as $row) {
        echo $row['name'] . " (" . $row['email'] . ")<br>";
    }
}
```

### Getting Single Values

#### `getValue()`

Retrieves the value of a single column from the first matching row.

**Return Value:** The specific value (mixed type) or `null` if not found or on error.

```php
$userId = 10;
$username = PHDB::getValue('users', 'name', ['id' => $userId]);

if ($username !== null) {
    echo "Username for ID $userId is: " . $username; // e.g., Username for ID 10 is: JaneDoe
} else {
    echo "User not found or error occurred.";
}
```

#### `getSpecificValue()`

Retrieves a single scalar value using a custom query. Useful for aggregation results like COUNT, MAX etc., when you only need the single computed value.

**Return Value:** The specific value (mixed type) or `null` if not found or on error.

```php
$totalUsers = PHDB::getSpecificValue("SELECT COUNT(*) FROM users");
echo "Total users: " . ($totalUsers ?? 0); // e.g., Total users: 150

$maxPrice = PHDB::getSpecificValue("SELECT MAX(price) FROM products WHERE category = ?", ['electronics']);
echo "Max electronics price: " . ($maxPrice ?? 'N/A'); // e.g., Max electronics price: 1999.99
```

### Inserting Data

#### `insert()`

Inserts a single record. Uses `ON DUPLICATE KEY UPDATE` implicitly, meaning if a unique key constraint (like a `PRIMARY KEY` or `UNIQUE` index) is violated, it will update the existing row instead of throwing an error. The `$overwrite` parameter's behavior in the current code might be less predictable due to the `ON DUPLICATE KEY` clause always being present; relying on the implicit update is generally recommended.

**Return Value:** `true` on successful insert/update, `false` on failure.

```php
$userData = [
    'name' => 'Peter Jones',
    'email' => 'peter.jones@example.com',
    'status' => 'pending',
    'signup_date' => date('Y-m-d H:i:s') // Use current timestamp
];

$success = PHDB::insert('users', $userData);

if ($success) {
    echo "User 'Peter Jones' inserted or updated.";
} else {
    echo "Failed to insert/update user: " . PHDB::error();
}
```

#### `batchInsert()`

Inserts multiple records efficiently in a single query.

**Return Value:** `true` on success, `false` on failure.

```php
$newUsers = [
    ['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active'],
    ['name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'active'],
    ['name' => 'Charlie', 'email' => 'charlie@example.com', 'status' => 'pending']
];

// Insert new records, fail if duplicates exist based on unique keys
$success = PHDB::batchInsert('users', $newUsers);

// Insert records, update rows if unique keys conflict
// $success = PHDB::batchInsert('users', $newUsers, true); // Set $overwrite to true

if ($success) {
    echo count($newUsers) . " users processed successfully.";
} else {
    echo "Batch insert failed: " . PHDB::error();
}
```

#### `save()`

A convenience method to insert or update a record based on the presence of specific key(s) in the data. Checks if a record exists based on `$uniqueKey` (or `$check` if provided) and performs an UPDATE if found, otherwise an INSERT.

**Return Value:** `true` on success, `false` on failure.

```php
// Example: Saving application settings (assuming 'name' is a unique key)
$settingData = [
    'name' => 'website_url',
    'value' => 'https://new.example.com',
    'description' => 'The main URL for the site'
];

// Will insert if 'website_url' doesn't exist, or update it if it does.
// It checks for existence based on the 'name' field because it's provided in $data.
// If 'id' was the primary key and included, it would likely check based on 'id'.
// Specify the key explicitly for clarity:
$success = PHDB::save('settings', $settingData, null, 'name'); // Check based on 'name'

if ($success) {
    echo "Setting 'website_url' saved successfully.";
} else {
    echo "Failed to save setting: " . PHDB::error();
}
```

### Updating Data (`update()`)

Updates existing records based on a `WHERE` clause.

**Return Value:** `true` on success (even if no rows were affected), `false` on failure.

```php
// Update status for a specific user
$userId = 15;
$updateData = ['status' => 'verified', 'last_login' => date('Y-m-d H:i:s')];
$success = PHDB::update('users', $updateData, ['id' => $userId]);

if ($success) {
    echo "User ID $userId updated.";
} else {
    echo "Failed to update user: " . PHDB::error();
}

// Update multiple users
$success = PHDB::update('users', ['status' => 'archived'], ['signup_date' < '2023-01-01']);
// Note: The above condition `signup_date < '...'` won't work directly with the current
// implementation which only supports `=` comparisons with prepared statements.
// For complex WHERE clauses, use `query()`:
$dateThreshold = '2023-01-01';
$success = PHDB::query("UPDATE users SET status = ? WHERE signup_date < ?", ['archived', $dateThreshold]);

```

### Deleting Data

#### `delete()` / `deleteBy()`

Deletes records matching the `WHERE` conditions. `deleteBy()` is an alias for `delete()`.

**Return Value:** `true` on success (even if no rows were affected), `false` on failure.

```php
// Delete a specific user by ID
$userIdToDelete = 25;
$success = PHDB::delete('users', ['id' => $userIdToDelete]);
// $success = PHDB::deleteBy('users', ['id' => $userIdToDelete]); // Alias

if ($success) {
    echo "Attempted to delete user ID $userIdToDelete.";
} else {
    echo "Failed to delete user: " . PHDB::error();
}

// Delete all inactive users
$success = PHDB::delete('users', ['status' => 'inactive']);

// Delete ALL records from a table (use truncateTable for efficiency if needed)
// $success = PHDB::delete('logs'); // Deletes all rows
```

### Table Management

#### `createTable()`

Creates a new table if it doesn't exist.

**Return Value:** `true` on success, `false` on failure.

```php
$columns = [
    'id' => 'INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY',
    'product_name' => 'VARCHAR(255) NOT NULL',
    'sku' => 'VARCHAR(100) UNIQUE',
    'price' => 'DECIMAL(10, 2) DEFAULT 0.00',
    'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
];

$success = PHDB::createTable('products', $columns);

if ($success) {
    echo "Table 'products' created or already exists.";
} else {
    echo "Failed to create table 'products': " . PHDB::error();
}
```

#### `dropTable()`

Drops an existing table. **Use with caution!**

**Return Value:** `true` on success, `false` on failure.

```php
// $success = PHDB::dropTable('old_logs');
// if ($success) {
//     echo "Table 'old_logs' dropped.";
// } else {
//     echo "Failed to drop table 'old_logs': " . PHDB::error();
// }
```

#### `alterTable()`

Modifies an existing table structure.

**Return Value:** `true` on success, `false` on failure.

```php
$changes = [
    'ADD COLUMN category VARCHAR(100) AFTER product_name',
    'MODIFY COLUMN price DECIMAL(12, 2) DEFAULT 0.00',
    'ADD INDEX idx_category (category)'
];

$success = PHDB::alterTable('products', $changes);

if ($success) {
    echo "Table 'products' altered successfully.";
} else {
    echo "Failed to alter table 'products': " . PHDB::error();
}
```

#### `truncateTable()`

Removes all data from a table quickly. Resets AUTO_INCREMENT counter. **Use with caution!**

**Return Value:** `true` on success, `false` on failure.

```php
// $success = PHDB::truncateTable('temporary_data');
// if ($success) {
//     echo "Table 'temporary_data' truncated.";
// } else {
//     echo "Failed to truncate table: " . PHDB::error();
// }
```

#### `addDB()`

Creates a new database if it doesn't exist. Requires appropriate database server privileges.

**Return Value:** `true` on success, `false` on failure.

```php
// $success = PHDB::addDB('my_new_app_db');
// if ($success) {
//     echo "Database 'my_new_app_db' created or already exists.";
// } else {
//     echo "Failed to create database: " . PHDB::error();
// }
```

### Schema Introspection (`columns()`)

Retrieves a list of column names for a given table.

**Return Value:** An array of column name strings, or an empty array on failure.

```php
$userColumns = PHDB::columns('users');
echo "Columns in 'users' table: " . implode(', ', $userColumns);
// Output: e.g., Columns in 'users' table: id, name, email, status, signup_date, last_login

// Get columns containing 'date'
$dateColumns = PHDB::columns('users', 'date');
echo "Date-related columns: " . implode(', ', $dateColumns);
// Output: e.g., Date-related columns: signup_date

// Get columns *except* primary key ('id') and timestamps
$dataColumns = PHDB::columns('users', null, ['id', 'created_at', 'updated_at']);
echo "Main data columns: " . implode(', ', $dataColumns);
// Output: e.g., Main data columns: name, email, status, signup_date, last_login
```

### Finding and Searching

#### `findBy()`

An alias for `select()` often used semantically for finding records based on specific criteria.

**Return Value:** An array of associative arrays, or `false` on failure.

```php
$pendingUsers = PHDB::findBy('users', '*', ['status' => 'pending']); // Same as select('users', '*', ['status' => 'pending'])

if ($pendingUsers) {
    echo count($pendingUsers) . " pending users found.";
}
```

#### `search()`

Performs a `LIKE` search. Can search specific columns (if `$conditions` is an array) or *all* table columns (if `$conditions` is a string).

**Return Value:** An array of associative arrays, or `false` on failure.

```php
// Search for users where name LIKE '%john%' AND email LIKE '%example.com%'
$searchResults = PHDB::search('users', 'id, name', ['name' => 'john', 'email' => 'example.com']);

// Search for users where *any* column contains the word 'admin'
$keyword = 'admin';
$adminRelatedUsers = PHDB::search('users', '*', $keyword);

if ($adminRelatedUsers) {
    echo "Found " . count($adminRelatedUsers) . " records matching '$keyword' in any column.<br>";
    foreach ($adminRelatedUsers as $user) {
        // process results
    }
}
```

### Aggregation Functions

These functions compute aggregate values directly.

**Return Value:** Numeric value (int/float) or `null` (for MIN/MAX if no rows match), or 0 (for SUM/AVG/COUNT if no rows match).

```php
// Count total users
$totalUsers = PHDB::count('users');
echo "Total Users: $totalUsers<br>"; // e.g., Total Users: 150

// Count active users
$activeCount = PHDB::count('users', ['status' => 'active']);
echo "Active Users: $activeCount<br>"; // e.g., Active Users: 125

// Sum of prices for electronics
$totalElecValue = PHDB::sum('products', 'price', ['category' => 'electronics']);
echo "Total Electronics Value: $" . number_format($totalElecValue, 2) . "<br>"; // e.g., Total Electronics Value: $25430.50

// Average price of all products
$avgPrice = PHDB::avg('products', 'price');
echo "Average Product Price: $" . number_format($avgPrice, 2) . "<br>"; // e.g., Average Product Price: $45.99

// Highest price
$maxPrice = PHDB::max('products', 'price');
echo "Maximum Price: $" . number_format($maxPrice ?? 0, 2) . "<br>"; // e.g., Maximum Price: $1999.99

// Lowest price for 'books'
$minBookPrice = PHDB::min('products', 'price', ['category' => 'books']);
echo "Minimum Book Price: $" . number_format($minBookPrice ?? 0, 2) . "<br>"; // e.g., Minimum Book Price: $9.95
```

### Pagination (`paginate()`)

Retrieves a slice of data suitable for pagination, along with metadata.

**Return Value:** An array containing pagination info and data, or `false` on failure.
*   `data`: Array of associative arrays for the current page.
*   `total`: Total number of records matching the criteria.
*   `per_page`: Records per page requested.
*   `current_page`: The current page number.
*   `last_page`: The total number of pages.
*   `from`: The record number of the first item on the current page.
*   `to`: The record number of the last item on the current page.

```php
$currentPage = $_GET['page'] ?? 1; // Get current page from request, default to 1
$perPage = 15;

$paginationResult = PHDB::paginate(
    'products',                 // Table
    'id, product_name, price',  // Columns
    ['status' => 'available'],  // Where condition (optional)
    $currentPage,               // Current page number
    $perPage                    // Items per page
);

if ($paginationResult === false) {
    echo "Failed to retrieve products: " . PHDB::error();
} elseif ($paginationResult['total'] > 0) {
    echo "<h2>Products (Page " . $paginationResult['current_page'] . " of " . $paginationResult['last_page'] . ")</h2>";
    echo "<p>Showing records " . $paginationResult['from'] . " to " . $paginationResult['to'] . " of " . $paginationResult['total'] . " total.</p>";

    echo "<ul>";
    foreach ($paginationResult['data'] as $product) {
        echo "<li>" . $product['product_name'] . " - $" . $product['price'] . "</li>";
    }
    echo "</ul>";

    // Add pagination links (logic not shown, use the returned variables)
    // Example: if ($paginationResult['current_page'] < $paginationResult['last_page']) { echo "<a href='?page=".($currentPage+1)."'>Next</a>"; }

} else {
    echo "No products found.";
}
```

### Transactions (`transaction()`)

Execute a series of database operations atomically. If any operation fails within the callback (returns `false` or throws an Exception), all changes are rolled back. Otherwise, all changes are committed.

**Return Value:** `true` if the transaction committed successfully, `false` if it rolled back.

```php
$userId = 5;
$orderAmount = 100.50;
$productId = 101;
$quantity = 2;

$success = PHDB::transaction(function() use ($userId, $orderAmount, $productId, $quantity) {

    // 1. Create the order
    $orderData = ['user_id' => $userId, 'total_amount' => $orderAmount, 'order_date' => date('Y-m-d H:i:s')];
    if (!PHDB::insert('orders', $orderData)) {
        return false; // Rollback on failure
    }

    // Assuming insert doesn't easily return the ID here, let's get it (simplistic example)
    $lastOrderId = PHDB::getSpecificValue("SELECT LAST_INSERT_ID()");
    if (!$lastOrderId) return false; // Rollback

    // 2. Add order item
    $itemData = ['order_id' => $lastOrderId, 'product_id' => $productId, 'quantity' => $quantity];
    if (!PHDB::insert('order_items', $itemData)) {
        return false; // Rollback on failure
    }

    // 3. Decrease stock (Example - requires stock table)
    $stockUpdate = PHDB::query(
        "UPDATE product_stock SET quantity = quantity - ? WHERE product_id = ? AND quantity >= ?",
        [$quantity, $productId, $quantity]
    );
    // Check if update succeeded AND affected a row (meaning stock was sufficient)
    if (!$stockUpdate || PHDB::$conn->affected_rows === 0) { // Access internal conn property *within transaction*
        PHDB::handleError("Insufficient stock for product ID $productId", true); // Log error, continue to rollback
        return false; // Rollback
    }

    // If all steps succeeded
    return true; // Commit
});

if ($success) {
    echo "Order placed successfully!";
} else {
    echo "Order failed. Transaction rolled back. Error: " . PHDB::error();
}
```

### Cleaning Data (`clean()`)

Provides various options to clean up data in a table, such as removing duplicate rows or rows with empty values. **Use with extreme caution, especially on production data. Backup is highly recommended.**

**Return Value:** An array containing cleaning statistics, or `false` on failure.
*   `total_before`: Row count before cleaning.
*   `total_after`: Row count after cleaning.
*   `duplicates_removed`: Rows removed due to duplication rules.
*   `empty_removed`: Rows removed due to empty column rules.
*   `value_removed`: Rows removed due to specific value conditions.
*   `backup_created`: Boolean indicating if backup was successful.
*   `backup_table`: Name of the backup table created (if `backup` was true).

```php
// Example: Clean the 'temp_imports' table
// - Create a backup table first
// - Remove rows that are complete duplicates across all columns
// - Remove rows where 'email' or 'product_sku' is empty or null

$options = [
    'backup' => true, // Create a backup table (e.g., temp_imports_backup_YYYYMMDD_HHMMSS)
    // 'backup_table' => 'my_custom_backup_name', // Optional: Specify custom backup name
    'duplicate_all' => true, // Remove rows where all column values match another row
    'empty_cols' => ['email', 'product_sku'], // Remove if 'email' OR 'product_sku' is empty/null
    // 'duplicate_cols' => ['email'], // Alternative: Remove rows with duplicate emails, keeping one
    // 'value_conditions' => ['status' => 'error'], // Remove rows where status is 'error'
    'min_rows' => 1 // Keep at least one row when removing duplicates based on columns
];

$cleanResult = PHDB::clean('temp_imports', $options);

if ($cleanResult === false) {
    echo "Table cleaning failed: " . PHDB::error();
} else {
    echo "<h2>Cleaning Results for 'temp_imports':</h2>";
    echo "Backup Created: " . ($cleanResult['backup_created'] ? 'Yes (' . $cleanResult['backup_table'] . ')' : 'No') . "<br>";
    echo "Rows Before: " . $cleanResult['total_before'] . "<br>";
    echo "Duplicate Rows Removed: " . $cleanResult['duplicates_removed'] . "<br>";
    echo "Empty Value Rows Removed: " . $cleanResult['empty_removed'] . "<br>";
    echo "Value Condition Rows Removed: " . $cleanResult['value_removed'] . "<br>";
    echo "Rows After: " . $cleanResult['total_after'] . "<br>";
}

```

## Error Handling

The `PHDB::$error` static property controls how errors are handled:

*   **`true` (Default):** When a database error occurs, the error message is printed directly to the output (and logged via `error_log`), and the script execution stops (`die()`). This is useful during development and debugging.
*   **`false`:** Errors are suppressed from direct output. Methods that encounter an error will return `false` (or a specific error structure like `['status' => false, 'message' => '...']` for `query` injection check). You should check the return value of PHDB methods and can retrieve the last error message using `PHDB::error()`. This mode gives you more control in production environments.
*   **`'Custom Message'` (string):** If you set `$error` to a string, that custom message will be printed (and logged via `error_log`), and the script will stop (`die()`).

### Handling Errors Gracefully (when `PHDB::$error = false;`)

```php
<?php
require_once 'PHDB.php';

// Configure...
PHDB::$host = "localhost";
PHDB::$username = "user";
PHDB::$password = "pass";
PHDB::$dbname = "test_db";

// Set error mode to return false on failure
PHDB::$error = false;

// Try a query that might fail (e.g., non-existent table)
$result = PHDB::select('non_existent_table', '*');

if ($result === false) {
    // An error occurred
    $errorMessage = PHDB::error(); // Get the actual MySQLi error message
    error_log("Database Error: " . $errorMessage); // Log the detailed error
    echo "Sorry, we couldn't retrieve the data at this time. Please try again later."; // Show user-friendly message
    // Potentially redirect or display an error page
} else {
    // Query succeeded, process $result (which is an array of rows)
    if (empty($result)) {
        echo "No data found.";
    } else {
        print_r($result);
    }
}

// Example checking query() return
$updateSuccess = PHDB::query("UPDATE users SET name = ? WHERE id = ?", ["Test", 9999]);
if ($updateSuccess === false) {
    $errorMessage = PHDB::error();
    error_log("Update Failed: " . $errorMessage);
    // handle failure...
}
?>
```

## Security Considerations

*   **SQL Injection:** PHDB provides basic protection by checking raw queries passed to `query()` against a list of common malicious patterns (`isPotentiallyMalicious()`). However, **this is not foolproof**.
*   **Prepared Statements:** **The most effective way to prevent SQL injection is to use prepared statements.** Use the `query()` method with `?` placeholders and the `$params` array whenever dealing with user-supplied input. Methods like `select()`, `insert()`, `update()`, `delete()`, `getValue()`, etc., *already use prepared statements internally* for their conditions and data, making them inherently safer for those specific operations.
*   **Input Sanitization:** Always validate and sanitize user input *before* passing it to any PHDB method, especially if constructing parts of queries dynamically (which should be avoided if possible). Use functions like `filter_input()`, `filter_var()`, or custom validation logic.
*   **Identifier Quoting:** The library automatically adds backticks (`` ` ``) around table and column names in most helper methods (`select`, `insert`, `update`, `delete`, etc.) to prevent issues with reserved keywords or special characters in identifiers.
*   **Least Privilege:** Ensure the database user configured (`PHDB::$username`) has only the necessary permissions required for the application to function. Avoid using root or administrative accounts for regular application operations.

## License

This project is licensed under the **MIT License**. See the LICENSE file for details.
