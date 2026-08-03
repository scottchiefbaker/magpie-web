DBQuery
=======

DBQuery is a PHP database library to simplify fetching data from your database.
It supports any database that PDO supports, and has been tested extensively
with MySQL and SQLite. DBQuery has been tested on PHP versions: 8.1, 8.2 and 8.3.

Installation
------------

```PHP
require("include/db_query.class.php");
```

Example Usage
-------------

```PHP
// SQLite
$dsn = "sqlite://path/to/dir/database.sqlite";
$dbq = new DBQuery($dsn);

// MySQL
$dsn  = 'mysql:host=server.domain.com;dbname=my_database';
$user = 'john_smith';
$pass = 'sekrit';
$dbq  = new DBQuery($dsn, $user, $pass);

$sql  = "SELECT First, Last, City, State, Zipcode FROM Customers;";
$data = $dbq->query($sql);

foreach ($data as $rec) {
	// Output code here
}
```

DBQuery simplifies the act of sending queries and iterating over the results.
The core of DBQuery is in the `query()` function, which handles sending
queries and building an iterable recordset.

```PHP
$result = $dbq->query($sql);
$result = $dbq->query($sql, $return_hint);
$result = $dbq->query($sql, $param_array, $return_hint);
```

DBQuery does its best job to give you the datatype you want, but you can provide
hints to guide it.

Return Hints
------------

**info_hash** return an array of associative arrays (Note: this is the default return type)

```PHP
$sql  = "SELECT First, Last, City FROM Customers;";
$data = $dbq->query($sql, 'info_hash');

foreach ($data as $i) {
	print "Cust: " . $i['First'] . " " . $i['Last'];
}
```

**info_list** return an array of numeric arrays

```PHP
$sql  = "SELECT First, Last, City FROM Customers;";
$data = $dbq->query($sql, 'info_list');

foreach ($data as $i) {
	print "Cust: " . $i[0] . " " . $i[1];
}
```

**one_data** return a single scalar

```PHP
$sql = "SELECT CustID FROM Customers WHERE Last = 'Doolis';";
$id  = $dbq->query($sql, 'one_data');
```

**key_value** return an associative array key/value pair

```PHP
$sql  = "SELECT ID, Last FROM Customers;";
$data = $dbq->query($sql, 'key_value');

print "Customer #17 = " . $data[17];
print "Customer #21 = " . $data[21];
```

**one_row** return a single associtive array

```PHP
$sql  = "SELECT First, Last FROM Customers WHERE City = 'Chicago';";
$data = $dbq->query($sql, 'one_row');

print "Customer: " . $data['First'] . " " . $data['Last'];
```

**one_column** return a single numeric array

```PHP
$sql = "SELECT ID FROM Customers WHERE City = 'Chicago';";
$ids = $dbq->query($sql, 'one_column');

print "Found IDs: " . join(", ", $ids);
```

Parameter Binding
-----------------

```PHP
$sql    = "INSERT INTO Names (First, Last, Age) VALUES (?, ?, ?);";
$params = array("Jason", "Doolis", 14);

$id = $dbq->query($sql, $params);
```

Unit Tests
----------
DBQuery contains standalone unit tests which use an in-memory SQLite database
to test the functionality of DBQuery. You can run this unit tets from the
command line with:

```
php tests/unit_test.php
```
