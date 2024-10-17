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

## Usage

### Configuration

Set the database credentials:

```php
PHDB::$host = 'your_host';
PHDB::$username = 'your_username';
PHDB::$password = 'your_password';
PHDB::$dbname = 'your_database_name';

// Error handling mode (true for direct output, false for returning error details, or a custom error message string)
PHDB::$error = true; // or false, or 'Custom error message' 
```

### Connecting and Disconnecting

```php
$connectionResult = PHDB::connect();
if ($connectionResult['status'] === true) {
    // Connection successful
} else {
    // Handle connection error
    echo $connectionResult['message']; 
}


PHDB::disconnect(); // or PHDB::close();
```

### Querying

```php
// Simple query
$result = PHDB::query("SELECT * FROM `users`"); // Note backticks are added automatically where appropriate

if ($result['status'] === true) {
    while ($row = $result['data']->fetch_assoc()) {
        // Process data
        print_r($row);
    }
} else {
    // Handle error
    echo $result['message'];
}


// Prepared statement
$result = PHDB::query("SELECT * FROM `users` WHERE `id` = ?", [1]); // Note backticks

if ($result['status'] === true) {
    // ... process data ...
} else {
    // ... handle error ...
}

// Custom error message
PHDB::$error = "A database error occurred.";
$result = PHDB::query("SELECT * FROM non_existent_table");
if ($result['status'] === false) {
    echo $result['message']; // Outputs "A database error occurred."
}


```

### Inserting Data

```php
$data = ['name' => 'Alice', 'email' => 'alice@example.com'];

// Insert (or update if duplicate key exists)
$result = PHDB::insert('users', $data, true); // $overwrite = true (updates existing if duplicate 'name')



if ($result['status'] === true) {
  echo $result['message'];

} else {
  // ... error handling ...
}


// Insert without overwrite (will fail on duplicate key):
$result = PHDB::insert('users', $data);  // $overwrite defaults to false

if ($result['status'] === true) {
   // ... success ...
} else {
   // ... error handling ...
}

```

### Updating Data

```php
$data = ['email' => 'bob.updated@example.com'];
$where = ['id' => 2];  // Condition

$result = PHDB::update('users', $data, $where);
if ($result['status'] === true) {
    // Update successful
} else {
    // Handle error
}
```

### Deleting Data

```php
$where = ['id' => 3];
$result = PHDB::delete('users', $where);
if ($result['status'] === true) {
    // Deletion successful
} else {
    // Handle error
}
```

### Selecting Data


```php
// Selecting all columns
$result = PHDB::select('users'); 
if ($result['status'] === true) {

    while ($row = $result['data']->fetch_assoc()) {
        // Process rows
    }

} else {
  // Error handling
}


// Selecting specific columns with WHERE clause and LIMIT
$result = PHDB::select('users', '`name`, `email`', ['`status`' => 'active'], 10, 5); // Backticks added automatically to WHERE and ORDER BY
if ($result['status'] === true) {
    // Process the result
}


// Using joins:
$joins = [
  "JOIN table2 ON users.id = table2.user_id"
];

$result = PHDB::select('users', '*', [], null, null, null, null, $joins);


```

### Other Methods

```php
// Get a single value
$email = PHDB::getValue('users', 'email', ['id' => 1]);

// Get a specific value using a custom query
$name = PHDB::getSpecificValue("SELECT name FROM users WHERE id = ?", [2]);

// Create a table
$columns = [
    'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
    'name' => 'VARCHAR(255)',
    // ... more columns
];
PHDB::createTable('my_table', $columns);

// Drop a table
PHDB::dropTable('my_table');

// Alter a table
$changes = ['ADD COLUMN phone VARCHAR(20)', 'DROP COLUMN address'];
PHDB::alterTable('my_table', $changes);

// Truncate a table
PHDB::truncateTable('my_table');

// Find by conditions (same as select with conditions)
$users = PHDB::findBy('users', ['status' => 'active']);


// Paginate results
$perPage = 10;
$page = 2; // Get the second page
$results = PHDB::paginate('users', '*', [], $page, $perPage);


// Count records
$count = PHDB::count('users', ['status' => 'active']);


```


## Error Handling

The `$error` property controls error handling.
- `true` (Default): Errors are printed directly to the output, and script execution stops.  Suitable for development and debugging.
- `false`: Errors are not displayed directly, but returned as part of the result array with `status => false` and an informative `message`. Provides more control over error handling in production environments.
- `'Custom error message'`: Displays a custom error message.

## Security

PHDB provides basic protection against SQL injection by checking for common malicious patterns. **However, it is crucial to always sanitize and validate user-supplied input before using it in queries**, even with these protections.  Prepared statements (using the `query()` method with parameters) are the most effective way to prevent SQL injection. The library also now automatically adds backticks `` ` `` around table and column names to help prevent issues with reserved keywords or special characters in identifiers.

## License

This project is licensed under the MIT License.


This updated README is more comprehensive and includes examples for all the methods in your `PHDB` class, with explanations of error handling, security, and how to use prepared statements. This documentation will be much more helpful for anyone wanting to use your library.
