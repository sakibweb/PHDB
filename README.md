# PHDB
### PHDB is PHP Database Management Library
PHDB is a PHP library for basic database management operations. It offers convenient methods to connect to a database, perform CRUD operations, execute queries, and manage database tables. This library aims to provide ease of use and flexibility for database interaction in PHP applications.
PHDB is a lightweight PHP library that simplifies common database operations using MySQLi. It provides convenient methods for connecting, querying, inserting, updating, deleting, and managing tables, with built-in error handling and basic SQL injection protection.

# Features
* Connect: Establish a connection to the database.
* Disconnect: Close the database connection.
* Error handling :  Error handling mode.
* Blocked some popular SQL injection commands.
* Query: Execute a SQL query and return the resulting mysqli_result.
* Insert: Insert a record into the database.
* Update: Update records in the database.
* Delete: Delete records from the database.
* Select: Retrieve records from the database based on specified conditions.
* Specific Select: Perform a specific selection from the database.
* Get Value: Retrieve a single value from the database.
* Get Specific Value: Retrieve a specific value from the database.
* Create Table: Create a new table in the database.
* Drop Table: Drop an existing table from the database.
* Alter Table: Alter an existing table in the database.
* Truncate Table: Remove all records from a table.
* Find By: Find records in the database based on specific conditions.
* Paginate: Paginate results from the database.
* Delete By: Delete records from the database based on specific conditions.
- **Connection Management:** Easy connection and disconnection.
- **Error Handling:** Customizable error handling with different modes.
- **SQL Injection Protection:** Basic protection against common SQL injection patterns.
- **CRUD Operations:** Simplified methods for insert, update, delete, and select queries.
- **Table Management:** Create, drop, alter, and truncate tables.
- **Helper Functions:** Get single values, paginate results, find by conditions, etc.

## Installation

Simply include the `PHDB.php` file in your project.  No external dependencies are required.

```php
include 'PHDB.php';
```

# Usage

## Configuration

Before using the `PHDB` class, you need to configure the database credentials:

```php
PHDB::$host = "localhost";
PHDB::$username = "your_username";
PHDB::$password = "your_password";
PHDB::$dbname = "your_database_name";

// Optional: Customize error handling
// PHDB::$error = false; // Disable error reporting
// PHDB::$error = "A custom error message"; // Custom error message
```

## Connecting and Disconnecting

```php
// Connect to the database
PHDB::connect();

// Perform database operations here...

// Disconnect from the database
PHDB::disconnect(); 
```

## Querying the Database

### `query()`

Execute a raw SQL query:

```php
$result = PHDB::query("SELECT * FROM users WHERE id = ?", [1]);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Process the row
        echo $row['name'];
    }
}
```

### `select()`

Select data with various options:

```php
// Select all columns from the 'users' table
$users = PHDB::select('users');

// Select specific columns with a WHERE clause and LIMIT
$users = PHDB::select('users', 'id, name, email', ['status' => 'active'], 10, 5); // Limit 10, offset 5

// Select with ORDER BY and GROUP BY
$users = PHDB::select('users', '*', [], null, null, 'name ASC', 'city');

// Example with JOINs (assuming a 'posts' table with a 'user_id' column)
$joins = [
    "LEFT JOIN posts ON users.id = posts.user_id"
];
$usersWithPosts = PHDB::select('users', 'users.*, posts.title', [], null, null, null, null, $joins);

foreach ($usersWithPosts as $row) {
    echo $row['name'] . " - " . $row['title'] . "<br>";
}

```


### `specificSelect()`

Execute a specific SQL query:

```php
$result = PHDB::specificSelect("SELECT COUNT(*) AS total FROM products WHERE category = ?", ['electronics']);
$totalProducts = $result[0]['total'];
```

### `getValue()`

Retrieve a single value from a table:

```php
$username = PHDB::getValue('users', 'name', ['id' => 10]);
```

### `getSpecificValue()`

Retrieve a specific value with a custom query:

```php
$latestOrderId = PHDB::getSpecificValue("SELECT MAX(id) FROM orders"); 
```


## Inserting and Updating Data

### `insert()`

Insert data into a table:

```php
$data = [
    'name' => 'John Doe',
    'email' => 'john.doe@example.com',
    'status' => 'active'
];

PHDB::insert('users', $data);

// Insert with overwrite (if 'name' is a unique key, it will update the existing record)
PHDB::insert('users', $data, true); 
```


### `save()` - Simplified Insert/Update

Insert or update based on existence of a 'name' key:

```php
$setting = [
  'name' => 'site_title',
  'value' => 'My Website'
];
PHDB::save('settings', $setting); // Inserts or updates the 'site_title' setting
```

### `update()`

Update existing data:

```php
PHDB::update('users', ['status' => 'inactive'], ['id' => 5]);
```


## Deleting Data

### `delete()`

Delete data from a table:

```php
PHDB::delete('users', ['id' => 5]);
```

### `deleteBy()` - Alias for `delete()`

```php
PHDB::deleteBy('users', ['status' => 'inactive']);
```



## Table Management

### `createTable()`

Create a new table:

```php
$columns = [
    'id' => 'INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY',
    'name' => 'VARCHAR(255)',
    'email' => 'VARCHAR(255)'
];

PHDB::createTable('users', $columns);
```

### `dropTable()`

Drop an existing table:


```php
PHDB::dropTable('users');
```

### `alterTable()`

Alter a table's structure:

```php
$changes = [
    'ADD COLUMN age INT(3)',
    'DROP COLUMN email'
];

PHDB::alterTable('users', $changes);
```

### `truncateTable()`

Truncate a table (delete all data):

```php
PHDB::truncateTable('users');
```


## Finding and Counting

### `findBy()`

Find records based on conditions:

```php
$activeUsers = PHDB::findBy('users', ['status' => 'active'], 'id, name', 5, 2);  // Get id and name, limit 5, offset 2
```



### `count()`

Count records based on conditions:

```php
$totalUsers = PHDB::count('users');
$activeUsersCount = PHDB::count('users', ['status' => 'active']);
```

## Pagination

### `paginate()`

Paginate results:

```php
$currentPage = 2;
$perPage = 20;
$paginatedUsers = PHDB::paginate('users', '*', [], $currentPage, $perPage);

foreach ($paginatedUsers as $row) {
  // ... process each row on the current page
}
```

## Closing the Connection

```php
PHDB::close(); // Explicitly close the connection (also handled automatically after queries)
```

## Error Handling

The `$error` property controls error handling.
- `true` (Default): Errors are printed directly to the output, and script execution stops.  Suitable for development and debugging.
- `false`: Errors are not displayed directly, but returned as part of the result array with `status => false` and an informative `message`. Provides more control over error handling in production environments.
- `'Custom error message'`: Displays a custom error message.
Handling Errors Gracefully:
```php
PHDB::$error = false; // Suppress errors

$result = PHDB::query("SELECT * FROM invalid_table");

if ($result === false) {
    echo "Query Failed: " . PHDB::error();
} else {
    print_r($result);
}
```

## Security

PHDB provides basic protection against SQL injection by checking for common malicious patterns. **However, it is crucial to always sanitize and validate user-supplied input before using it in queries**, even with these protections.  Prepared statements (using the `query()` method with parameters) are the most effective way to prevent SQL injection. The library also now automatically adds backticks `` ` `` around table and column names to help prevent issues with reserved keywords or special characters in identifiers.

## License

This project is licensed under the MIT License.


This updated README is more comprehensive and includes examples for all the methods in your `PHDB` class, with explanations of error handling, security, and how to use prepared statements. This documentation will be much more helpful for anyone wanting to use your library.
