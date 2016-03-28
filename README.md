# mysqlez

The mysqlez class extends the mysqli class in PHP versions 5 and 7. The primary
benefit to using mysqlez is its parameterized_query method, which uses prepared
SQL statements, parameterized input, and convenient, context-aware return
types.

The second convenience method is bulk_insert, which is designed to greatly
improve the speed of large-volume data insertion. A large number of single-row
inserts can take a very long time, especially if your SQL server is not local.
The bulk_insert method takes the an array of arrays (rows of column data),
along with the table and column names, builds and executes one large insert
statement.

## Setup

First, you will need your database connection information: host, database name,
username, and password. I recommend keeping your settings in an .ini file in
your server's /etc folder, then loading it with PHP's parse_ini_file method.

Note: mysqlez assumes the database will use the default
port 3306

When constructing a new mysqlez object, pass it either the whole configuration
array loaded from your .ini file, or the four database parameters individually.

New mysqlez database connection with hardcoded values:
<pre>
require_once('php/mysqlez.php');
$db = new mysqlez( 'localhost', 'myUserName', 'myPassword', 'myDatabase' );
</pre>

New mysqlez database connection using config.ini:
<pre>
require_once('php/mysqlez.php');
$config = parse_ini_file('../etc/config.ini', true);
$db = new mysqlez( $config['database'] );
</pre>

config.ini:
<pre>
[database]
host = localhost
user = myUserName
pass = myPassword
name = myDatabase
</pre>


## Parameterized Queries

Function Structure:
<pre>
mixed mysqlez::parameterized_query( string $sql, [ array $params ] )
</pre>

Example Usage:
<pre>
$result = $db->parameterized_query('SELECT * FROM `user` WHERE `id`=?', 'foo');
$errors = $db->errors;
</pre>

Return values vary depending on the successful query's type.
SELECT
- An array of rows, each row is an associative array wherein the column names are used as the array keys.
INSERT
- For tables with an AUTO_INCREMENT id column, the new row's id is returned.
- Otherwise it returns true when successful.
UPDATE
- The number of rows updated.
DELETE
- The number of rows deleted.
- All query failures return false, and the error details are saved in the errors array.

The errors array holds one entry for each error encountered (usually only one, but not necessarily so). Each error includes three properties:

{string} operation - The operation in which the error occurred.
{number} errno     - The mysqli error number.
{string} error     - The mysqli error description.


## Bulk Data Insert

Function Structure:
<pre>
mixed mysqlez::bulk_insert( string $table, array|string $columns, array $data, [ bool $update ] )
</pre>

Example Usage:
<pre>
$numAffectedRows = $db->bulk_insert( 'fruit', array('name','color'), $fruit );
$numAffectedRows = $db->bulk_insert( 'fruit', 'name,color', $fruit, TRUE );
</pre>

Returns the SQL-style number of affected rows on success. (That's +1 per insert and +2 per update.)
Returns FALSE on failure. (SQL errors are save in $db->errno and $db->error.)


## More Examples

SELECT
<pre>
$fruit = $db->parameterized_query(
  'SELECT * FROM `fruit` WHERE `color`=?',
  $color
);

echo "Found " . count( $fruit ) . " fruit with the color $color.\n";
</pre>

INSERT
<pre>
$my_fruit = array(
  $_POST['fruit-name'],
  $_POST['fruit-color']
);

$id = $db->parameterized_query( 'INSERT INTO `fruit` (`name`,`color`) VALUES (?,?)', $my_fruit);

if ( $id ) {
  echo "Added your fruit with ID number $id.\n";
} else {
  echo "Could not add your fruit.";
  foreach ( $db->errors as $err ) {
    echo " " . $err['error'];
  }
  echo "\n";
}
</pre>

UPDATE
<pre>
$my_fruit = array(
  $_POST['fruit-color'],
  $_POST['fruit-name']
);

$effect = $db->parameterized_query( 'UPDATE `fruit` SET `color`=? WHERE `name`=?', $my_fruit );

if ( $effect ) {
  echo "Fruit updated.\n";
} else {
  echo "Could not update your fruit.\n";
}
</pre>

DELETE
<pre>
$effect = $db->parameterized_query(
  'DELETE FROM `fruit` WHERE `color`=?',
  $_GET['disliked-color']
);

if ( $effect ) {
  echo "$dont_like fruit deleted!\n";
} else {
  if ( $effect === 0 ) {
    echo "No $dont_like fruit to delete.\n";
  } else {
    echo "Could not delete $dont_like fruit!\n";
    var_dump( $db->errors );
  }
}
</pre>

Bulk INSERT
<pre>
// Setup //
$tableName = 'fruit';
$columns = array('name','color','family','description');
$totalAffectedRows = 0;
$errors = array();
$data = array();

// Open large data file. //
if (! $fileHandle = fopen("Every Kind of Fruit in the World.csv", "r") ) {
  exit("Failed to open CSV for reading.");
}
// Read CSV data. //
while ( $row = fgetcsv( $fileHandle ) ) {
  $data[] = $row;

  // Send data to database in 1000-row blocks. //
  if ( count($data) >= 1000 ) {
    if ( $affectedRows = $db->bulk_insert( $tableName, $columns, $data ) ) {
      $totalAffectedRows += $affectedRows;
    } else {
      $errors[] = $db->error;
    }
    array_splice( $data, 0 );
  }
}

// Send any remaining data. //
if ( count($data) ) {
  if ( $affectedRows = $db->bulk_insert( $tableName, $columns, $data ) ) {
    $totalAffectedRows += $affectedRows;
  } else {
    $errors[] = $db->error;
  }
}

// Report results. //
echo "Done. $totalAffectedRows total SQL updates. " . count($errors) . " errors.\n";
foreach ( $errors as $thisError ) {
  echo "$thisError\n";
}
</pre>


## MySQLi vs. MySQLez

For comparison, here's comparable code using the PHP standard mysqli:
<pre>
if (! $db = new mysqli( $dbHost, $dbUser, $dbPass, $dbName ) {
  # Handle connection errors here...
}

$sql    = 'SELECT * FROM `user` WHERE `id`=?';
$id     = 'foo';

# Prepare the query. #
if (! $stmt = $mysqli->prepare( $sql ) ) {
  # Error handling, for example:
  echo "Failed to prepare mysqli query: " . $mysqli->errno . ' ' . $this->error;
  exit;
}

# Bind the parameters. #
if (! $stmt->bind_param( 's', $id )) {
  # More error handling...
}

# Execute the query. #
if (! $stmt->execute()) {
  # More error handling...
}

# Identify the columns in the SELECT result set. #
$fields = array();
foreach ( $stmt->result_metadata()->fetch_fields() as $field) {
  $fields[] = $field->name;
}

# Bind the results variables. #
$row = array();
$bind_results = array();
foreach ($fields as $fieldName) {
  $row[$fieldName] = null;
  $bind_results[] = &$row[$fieldName];
}
if (! call_user_func_array( array($stmt, "bind_result"), $bind_results )) {
  # Even more error handling...
}

# Compile and return the results as an associative array. #
$data = array();
while ($stmt->fetch()) {
  array_push($data, $row);
}
return $data;
</pre>

Note that the standard mysqli requires each result column to be bound to its
own variable reference. This is very inconvenient should you want to select all
the columns in a table with the '*' character. The code above is written to
adapt to any number of result columns, the same technique used in the mysqlez
extension.

If for some strange reason you didn't want to do any error checking, and always
selected a known number of columns, you could get away with as little code as
this in the standard mysqli:
<pre>
$sql = 'SELECT `every`,`desired`,`column`,`name` FROM `user` WHERE `id`=?';
$id  = 'foo';

$stmt = $mysqli->prepare( $sql );
$stmt->bind_param( 's', $id );
$stmt->execute();
$stmt->bind_result( $every, $desired, $column, $name );
$data = array();
while ($stmt->fetch()) {
  $data[] = array(
    'every'   => $every,
    'desired' => $desired,
    'column'  => $column,
    'name'    => $name
  );
}
return $data;
</pre>

But why give up flexibility and error checking when you could do everything
included in the page-length example in four lines?
<pre>
require_once('mysqlez.php');
$db = new mysqlez();
$result = $db->parameterized_query('SELECT * FROM `user` WHERE `id`=?', 'foo');
if ( $db->errors ) { error_handler(); }
</pre>