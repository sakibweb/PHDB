# PHDB
### PHDB is PHP Database Management Library
PHDB is a PHP library for basic database management operations. It offers convenient methods to connect to a database, perform CRUD operations, execute queries, and manage database tables. This library aims to provide ease of use and flexibility for database interaction in PHP applications.

# Features
* Connect: Establish a connection to the database.
* Disconnect: Close the database connection.
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

### Insert a Record
To insert a record into the database:
```
$data = ['name' => 'John', 'email' => 'john@example.com'];
PHDB::insert('users', $data);
```

### Update Records
To update records in the database:
```
$data = ['email' => 'jane@example.com'];
PHDB::update('users', $data, ['name' => 'Jane']);
```
sql
Copy code

### Delete Records
To delete records from the database:
```
PHDB::delete('users', ['name' => 'John']);
```

### Select Records
To retrieve records from the database:
```
$result = PHDB::select('users', '*', ['name' => 'John']);
while ($row = $result->fetch_assoc()) {
// Process the fetched row
}
```

### Specific Select
To perform a specific selection from the database:
```
$result = PHDB::specificSelect('SELECT * FROM users WHERE name = ?', ['John']);
while ($row = $result->fetch_assoc()) {
// Process the fetched row
}
```

### Get Value
To retrieve a single value from the database:
```
$value = PHDB::getValue('users', 'email', ['name' => 'John']);
```

### Get Specific Value
To retrieve a specific value from the database:
```
$value = PHDB::getSpecificValue('SELECT email FROM users WHERE name = ?', ['John']);
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

### Find By
To find records in the database based on specific conditions:
```
$result = PHDB::findBy('users', ['name' => 'John'], 'name, email');
while ($row = $result->fetch_assoc()) {
// Process the fetched row
}
```

### Delete By
To delete records from the database based on specific conditions:
```
PHDB::deleteBy('users', ['name' => 'John']);
```

### Paginate results from the Database
To paginate results from database:
```
paginate($table, $columns = '*', $where = [], $page = 1, $per_page = 10)
```

### Disconnect from the Database
To close the database connection:
```
PHDB::disconnect();
```
OR
```
PHDB::close();
```
