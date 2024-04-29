# PHDB
### PHDB is a PHP Database Management Library
PHDB is a library for basic database management operations in PHP applications. It provides simple and convenient methods for connecting to a database, performing CRUD operations, executing queries, and managing database tables.

# Features
* Connect: Establish a connection to the database.
* Add: Insert a new record into the database.
* Update: Update records in the database.
* Delete: Delete records from the database.
* Select: Retrieve records from the database.
* Specific Select: Perform a specific selection from the database.
* Get Value: Retrieve a single value from the database.
* Get Specific Value: Retrieve a specific value from the database.
* Create Table: Create a new table in the database.
* Drop Table: Drop an existing table from the database.
* Alter Table: Alter an existing table in the database.
* Truncate Table: Remove all records from a table.
* Disconnect: Close the database connection.

# Usage
### Connect to the Database
To connect to the database:
```
PHDB::$host = 'localhost';
PHDB::$username = 'username';
PHDB::$password = 'password';
PHDB::$dbname = 'database_name';
PHDB::connect();
```

### Add a Record
To insert a new record into the database:
```
$data = ['name' => 'John', 'email' => 'john@example.com'];
PHDB::insert('users', $data);
```

### Update Records
To update records in the database:
```
$data = ['name' => 'Jane', 'email' => 'jane@example.com'];
PHDB::update('users', $data, 'id = 1');
```

### Delete Records
To delete records from the database:
```
PHDB::delete('users', 'id = 1');
```

### Select Records
To retrieve records from the database:
```
$result = PHDB::select('users', '*', 'id = 1');
while ($row = $result->fetch_assoc()) {
// Process the fetched row
}
```

### Specific Selection
To perform a specific selection from the database:
```
$result = PHDB::specificSelect('SELECT * FROM users WHERE id = 1');
while ($row = $result->fetch_assoc()) {
// Process the fetched row
}
```

### Get Value
To retrieve a single value from the database:
```
$value = PHDB::getValue('users', 'name', 'id = 1');
```

### Get Specific Value
To retrieve a specific value from the database:
```
$value = PHDB::getSpecificValue('SELECT name FROM users WHERE id = 1');
```

### Create Table
To create a new table in the database:
```
$columns = ['id' => 'INT AUTO_INCREMENT PRIMARY KEY', 'name' => 'VARCHAR(255)', 'email' => 'VARCHAR(255)'];
PHDB::createTable('users', $columns);
```

### Drop Table
To drop an existing table from the database:
```
PHDB::dropTable('users');
```

### Alter Table
To alter an existing table in the database:
```
$changes = ['ADD COLUMN age INT', 'DROP COLUMN address'];
PHDB::alterTable('users', $changes);
```

### Truncate Table
To remove all records from a table:
```
PHDB::truncateTable('users');
```

### Disconnect from the Database
To close the database connection:
```
PHDB::disconnect();
```
