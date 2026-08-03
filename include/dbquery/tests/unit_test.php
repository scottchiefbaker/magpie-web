<?php
// Report all PHP errors
error_reporting(E_ALL);

$dbq = init_db();
$dbq->show_errors = false;

assert_options(ASSERT_WARNING,  false);

///////////////////////////////////////
// Select - InfoHash
///////////////////////////////////////
$sql  = "SELECT * FROM Customer LIMIT 8;";
// We don't specify a return type
$data = $dbq->query($sql);

$last_info   = $dbq->last_info();
$return_type = $last_info['return_type'];

// Make sure the default return type is InfoHash
unit_test($return_type === 'info_hash'    , "SELECT: InfoHashDefault has correct return type");
unit_test(is_numeric_array($data) === true, "SELECT: InfoHashDefault returns numeric array");
unit_test(is_assoc($data[0])              , "SELECT: InfoHashDefault returns associate array as the first item");

$data = $dbq->query($sql,'info_hash');

$first = $data[0];
$cols  = sizeof(array_keys($first));
$rows  = sizeof($data);

// Make sure we get back the correct number of rows
unit_test($rows === 8               , "SELECT: InfoHash correct rows");
unit_test($cols === 6               , "SELECT: InfoHash correct cols");
// Make sure it's not an assoc array
unit_test(is_numeric_array($data)   , "SELECT: InfoHash returns numeric array");
unit_test(is_assoc($data[0])        , "SELECT: InfoHash returns associate array as the first item");

///////////////////////////////////////
// Select - InfoHash with Key
///////////////////////////////////////

$sql = "SELECT * FROM Customer WHERE CustID > 3 LIMIT 5;";
$data = $dbq->query($sql,'info_hash|CustID');

$last_info   = $dbq->last_info();
$return_type = $last_info['return_type'];

$count = sizeof($data);
$first = array_slice($data,0,1);
$fkey  = key($data);
$fval  = $data[$fkey];

// Make sure we get back a numeric array
unit_test(is_numeric_array($data)                 , "SELECT: InfoHashKey returns a numeric array");
// The first item should not be an array by itself
unit_test(is_array($first)                        , "SELECT: InfoHashKey first element is an array");
// Make sure we get more than 5 items
unit_test($count > 4                              , "SELECT: InfoHashKey returned valid data");
unit_test($fkey > 3                               , "SELECT: InfoHashKey first element is correct");
unit_test($return_type === 'info_hash_with_key'   , "SELECT: InfoHashKey has correct return type");

///////////////////////////////////////
// Select - InfoList
///////////////////////////////////////
print "\n";

$sql = "SELECT * FROM Customer LIMIT 4;";
$data = $dbq->query($sql,'info_list');

$first = $data[0];
$cols  = sizeof($first);
$rows  = sizeof($data);

// Make sure we get back the correct number of rows
unit_test($rows == 4                               , "SELECT: InfoList correct rows");
unit_test($cols == 6                               , "SELECT: InfoList correct cols");
// Make sure it's not an assoc array
unit_test(is_numeric_array($data) === true         , "SELECT: InfoList is numeric array");
unit_test(is_numeric_array($first) === true        , "SELECT: InfoList first element is a numeric array");

///////////////////////////////////////
// Select - KeyValue
///////////////////////////////////////
print "\n";

$sql = "SELECT Last, CustID FROM Customer LIMIT 5;";
$data = $dbq->query($sql,'key_value');

$first = array_slice($data,0,1);
$last  = array_slice($data,4,1);
$rows  = sizeof($data);
$cols  = sizeof(array_values($first));

$key   = key($first);
$value = $first[$key];

// Make sure we get right number of items back
unit_test($rows === 5                            , "SELECT: KeyValue correct rows");
unit_test($cols === 1                            , "SELECT: KeyValue correct cols");

// Make sure we get the right type of things back
unit_test(is_string($key)                        , "SELECT: KeyValue key is string");
unit_test(is_numeric($value)                     , "SELECT: KeyValue value is number");
// This should be an assoc (not numeric) array
unit_test(is_assoc($data)                        , "SELECT: KeyValue return associative array");

// Test float keys
$sql2  = "SELECT ItemCost, ItemID FROM Items LIMIT 5;";
$data2 = $dbq->query($sql2,'key_value');
$val   = $data2['15.79'] ?? "";
unit_test($val == 4, "SELECT: KeyValue on a float key");

///////////////////////////////////////
// Select - OneData
///////////////////////////////////////
print "\n";

$sql = "SELECT First FROM Customer WHERE Last = 'Doolis' ORDER BY CustID;";
$data = $dbq->query($sql,'one_data');

if (is_array($data)) {
	$count = sizeof($data);
} else {
	$count = 1;
}

// Make sure we only get ONE piece of scalar data back
unit_test($data === 'Jason', "SELECT: OneData correct return value");
unit_test($count === 1     , "SELECT: OneData only one returned item");
unit_test(is_scalar($data) , "SELECT: OneData returned item is scalar");

///////////////////////////////////////
// Select - OneRow
///////////////////////////////////////
print "\n";

$sql = "SELECT * FROM Customer;";
$data = $dbq->query($sql,'one_row');
$fkey = key($data);
$fval = $data[$fkey];

// Make sure we get back an assoc array that's one dimensional
unit_test(is_assoc($data) === true                            , "SELECT: OneRow returns an associative array");
unit_test(!is_array($fkey)                                    , "SELECT: OneRow first key is not an array");
unit_test(!is_array($fval)                                    , "SELECT: OneRow first value is not an array");

$sql = "SELECT * FROM Customer WHERE CustID = 9802413;";
$data = $dbq->query($sql, 'one_row');

unit_test(is_array($data) && empty($data)                     , "SELECT: OneRow with WHERE clause with no matches returns an empty array");

///////////////////////////////////////
// Select - OneColumn
///////////////////////////////////////
print "\n";

$sql = "SELECT * FROM Customer;";
$data = $dbq->query($sql,'one_column');

$count = sizeof($data);

// Make sure we get back a numeric array
unit_test(is_numeric_array($data)                 , "SELECT: OneColumn returns a numeric array");
// The first item should not be an array by itself
unit_test(!is_array($data[0])                     , "SELECT: OneColumn first element is not an array");
// Make sure we get more than 5 items
unit_test($count > 5                              , "SELECT: OneColumn returned at least five elements");
unit_test(isset($data[0])                         , "SELECT: OneColumn returned valid data");

///////////////////////////////////////
// Select - OneRowList
///////////////////////////////////////
print "\n";

$sql = "SELECT * FROM Customer;";
$data = $dbq->query($sql,'one_row_list');

$count = sizeof($data);

// Make sure we get back a numeric array
unit_test(is_numeric_array($data)                 , "SELECT: OneRowList returns a numeric array");
// The first item should not be an array by itself
unit_test(!is_array($data[0])                     , "SELECT: OneRowList first element is not an array");
// Make sure we get more than 5 items
unit_test(isset($data[0])                         , "SELECT: OneRowList returned valid data");

///////////////////////////////////////
// Select - Broken SQL
///////////////////////////////////////
print "\n";

$sql  = "INVALID SQL;";
$data = $dbq->query($sql,'no_error');

unit_test($data === false, "SELECT: Invalid SQL returns false");

///////////////////////////////////////
// No error testing
///////////////////////////////////////

print "\n";

$affected = $dbq->query("INSERT INTO 'items' VALUES(?,?,?);",[null,'Twinkies',3.75],"no_error");
unit_test(is_numeric($affected) && $affected > 10, "NOERROR: Returns valid data");

$affected = $dbq->query("INSERT INTO 'items' VALUES(1,'Duplicate ID',2.76);","no_error");
unit_test($affected === false,                     "NOERROR: Second param returns false");

$affected = $dbq->query("INSERT INTO 'items' VALUES(?,?,?);",[1,'Duplicate ID',3.75],"no_error");
unit_test($affected === false,                     "NOERROR: Third param returns false");

///////////////////////////////////////
// INSERT
///////////////////////////////////////
print "\n";

// Put in several orders
$id = $dbq->query("INSERT INTO orders (CustID,ItemID,ItemCount) VALUES (1,2,10);");
$id = $dbq->query("INSERT INTO orders (CustID,ItemID,ItemCount) VALUES (8,3,1);");
$id = $dbq->query("INSERT INTO orders (CustID,ItemID,ItemCount) VALUES (8,8,1);");
$id = $dbq->query("INSERT INTO orders (CustID,ItemID,ItemCount) VALUES (8,4,10);");
$id = $dbq->query("INSERT INTO orders (CustID,ItemID,ItemCount) VALUES (8,9,15);");
$id = $dbq->query("INSERT INTO orders (CustID,ItemID,ItemCount) VALUES (8,11,1000);");
$id = $dbq->query("INSERT INTO orders (CustID,ItemID,ItemCount) VALUES (10,12,5);");
$id = $dbq->query("INSERT INTO orders (CustID,ItemID,ItemCount) VALUES (10,11,500);");
$id = $dbq->query("INSERT INTO orders (CustID,ItemID,ItemCount) VALUES (10,7,5);");

unit_test($id > 3, "INSERT: Returns valid InsertID");

///////////////////////////////////////
// UPDATE
///////////////////////////////////////
print "\n";

$affected = $dbq->query("UPDATE orders SET ItemCount = ItemCount + 1 WHERE CustID = 10;");
unit_test($affected > 2,   "UPDATE: Return correct number of affected rows");

$affected = $dbq->query("UPDATE orders SET ItemCount = ItemCount + 1 WHERE CustID = 1000;");
unit_test($affected === 0, "UPDATE: Return correct number of affected rows for missing CustID");

///////////////////////////////////////
// DELETE:
///////////////////////////////////////
print "\n";

// Put in several orders
$affected = $dbq->query("DELETE FROM orders WHERE CustID = 99999");
unit_test($affected === 0, "DELETE: Removing a non-item returns 0");

$affected = $dbq->query("DELETE FROM orders WHERE CustID = 8");
unit_test($affected > 4,   "DELETE: Removing known order returns the correct amount");

$affected = $dbq->query("DELETE FROM orders WHERE CustID = 8");
unit_test($affected === 0, "DELETE: Removing the same order returns 0");

$affected = $dbq->query("DELETE FROM orders");
unit_test($affected > 0,   "DELETE: Removing everything returns more than 0");

///////////////////////////////////////
// Raw PDO
///////////////////////////////////////

print "\n";
$ok = $dbq->dbh->exec("VACUUM");
unit_test($ok > 0, "RAW PDO Command OK '$ok'");

print "\n";
$exit_code = unit_test(-1,-1);
exit($exit_code);

///////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////

function init_db() {
	$dir = dirname(__FILE__);
	$str = file_get_contents("$dir/db/test.sql");

	// Break up the SQL statements in to an array
	$sql = explode(";",$str);
	$sql = array_map('trim',$sql);
	$sql = array_filter($sql);

	require("$dir/../db_query.class.php");

	// Build an in-memory database
	$dsn = "sqlite::memory:";
	$dbq = new DBQuery($dsn);

	if (!$dbq) {
		print "Couldn't connect to the DB";
		exit;
	}

	// Execute each SQL query
	foreach ($sql as $i) {
		$dbq->query($i);
	}

	return $dbq;
}

function is_assoc($arr) {
	if (!is_array($arr)) { return false; }

    return array_keys($arr) !== range(0, count($arr) - 1);
}

function is_numeric_array($array) {
	if (!is_array($array)) { return false; }

	foreach ($array as $a=>$b) {
		if (!is_int($a)) {
			return false;
		}
	}
	return true;
}

function unit_test($code,$name = "") {
	static $count = 0;
	static $good  = 0;
	static $bad   = 0;

	$ok = !!($code);

	$color_ok    = "\033[38;5;2m";
	$color_bad   = "\033[38;5;1m";
	$color_reset = "\033[0m";

	if ($name === -1) {
		if ($bad) {
			printf("%sFail%s: %d of %d tests passed (%0.2f%% failure rate)\n",$color_bad,$color_reset,$good,$count,($bad / $count) * 100);
			$ret = $bad;
		} else {
			printf("%sPass%s: %d of %d tests passed (%0.2f%% failure rate)\n",$color_ok,$color_reset,$good,$count,($bad / $count) * 100);
			$ret = 0;
		}

		return $ret;
	}

	if ($ok) {
		printf(" %sOK%s - %s\n",$color_ok,$color_reset,$name);
		$good++;
	} else {
		printf("%sBad%s - %s\n",$color_bad,$color_reset,$name);
		$bad++;
	}

	$count++;

	return $ok;
}
