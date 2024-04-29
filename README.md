# PHDB
### PHDB is PHP Database Management Library
PHDB is a PHP library for managing basic database operations in an advanced yet user-friendly way. It provides methods for connecting to a database, performing CRUD operations, executing custom SQL queries, creating, dropping, altering tables, and more.

# Features
* Connect: Establish a connection to the database.
* Add: Insert a record into the database table.
* Update: Update existing records in the database table.
* Delete: Remove records from the database table.
* Select: Retrieve records from the database table.
* Specific Selection: Execute custom SQL queries and retrieve records.
* Get Value: Retrieve a single value from the database.
* Get Specific Value: Execute a custom SQL query and retrieve a single value.
* Create Table: Create a new table in the database.
* Drop Table: Drop an existing table from the database.
* Alter Table: Alter the structure of an existing table in the database.
* Truncate Table: Remove all records from a table while preserving its structure.
* Disconnect: Close the connection to the database.

# Usage
### Connecting to the Database
To connect to a database:
```
PHDB::connect('localhost', 'my_database', 'username', 'password');
```

### Adding a Record
To insert a record into a database table:
```
PHDB::add('users', ['name' => 'John', 'email' => 'john@example.com']);
```

### Updating Records
To update records in a database table:
```
PHDB::update('users', ['name' => 'Jane'], 'id = :id', ['id' => 1]);
```

### Deleting Records
To delete records from a database table:
```
PHDB::delete('users', 'id = :id', ['id' => 1]);
```

### Selecting Records
To select records from a database table:
```
$users = PHDB::select('users', '*', 'age > :age', ['age' => 18]);
```

### Performing Specific Selection
To perform a specific selection from the database:
```
$active_users = PHDB::specificSelect('SELECT * FROM users WHERE active = :active', ['active' => 1]);
```

### Getting a Single Value
To get a single value from the database:
```
$count = PHDB::getValue('users', 'COUNT(*)', 'age > :age', ['age' => 18]);
```

### Getting a Specific Value
To get a specific value from the database:
```
$avg_age = PHDB::getSpecificValue('SELECT AVG(age) FROM users');
```

### Creating a Table
To create a new table in the database:
```
PHDB::createTable('products', ['id' => 'INT AUTO_INCREMENT PRIMARY KEY', 'name' => 'VARCHAR(255)', 'price' => 'DECIMAL(10,2)']);
```

### Dropping a Table
To drop a table from the database:
```
PHDB::dropTable('products');
```

### Updating a Table
To alter a table in the database:
```
PHDB::alterTable('users', ['ADD COLUMN address VARCHAR(255)']);
```

### Disconnecting from the Database
To disconnect from the database:
```
PHDB::disconnect();
```
