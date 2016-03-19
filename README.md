# mysqlez

The mysqlez class extends the mysqli class in PHP versions 5 and 7. The primary
benefit to using mysqlez is its added parameterized_query method, which uses
prepared SQL statements, parameterized input, and convenient, context-aware
return types.

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
mixed mysqlez::parameterized_query( string $sql, [ array $params ] );
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


## SQL Builder

Easily compile an SQL string compatible with parameterized queries.

Function Structure:
<pre>
string mysqlez::compile_sql( array $params );
</pre>

Example Usage:
<pre>
$propertiesOfFruit = array( 'name', 'family', 'color', 'calories', 'fiber' );

$sqlString = $db->compile_sql( array(
  'op'      => "INSERT",
  'table'   => "fruit",
  'columns` => $propertiesOfFruit,
  'update'  => true
));

/*
Value of $sqlString (reformatted for ease of human reading)
INSERT INTO `fruit`
(
  `name`,
  `family`,
  `color`,
  `calories`,
  `fiber`
)
VALUES (?,?,?,?,?)
ON DUPLICATE KEY UPDATE
  `name`=VALUES(`name`),
  `family`=VALUES(`family`),
  `color`=VALUES(`color`),
  `calories`=VALUES(`calories`),
  `fiber`=VALUES(`fiber`)
*/
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


## Examples

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
  'name'  => 'apple',
  'color' => 'red'
);

$id = $db->parameterized_query(
  'INSERT INTO `fruit` (`name`,`color`) VALUES (?,?)',
  $my_fruit
);

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
  'name'  => 'apple',
  'color' => 'green'
);

$effect = $db->parameterized_query(
  'UPDATE `fruit` SET `color`=? WHERE `name`=?',
  array( $my_fruit['color'], $my_fruit['name'] )
);

if ( $effect ) {
  echo "Fruit updated.\n";
} else {
  echo "Could not update your fruit.\n";
}
</pre>

DELETE
<pre>
$dont_like = 'green';

$effect = $db->parameterized_query(
  'DELETE FROM `fruit` WHERE `color`=?',
  $dont_like
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


## Comparison with MySQL

For comparison, here's the equivalent code using the PHP standard mysqli:
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
adapt to any number of result columns. The same technique is included in the
mysqlez extension.

If for some strange reason you didn't want to do any error checking, and always
selected a known number of columns, you could get away with as little code as
this:
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

But why give up flexibility and error checking when you could do everything included in the page-length example in four lines?
<pre>
require_once('mysqlez.php');
$db = new mysqlez();
$result = $db->parameterized_query('SELECT * FROM `user` WHERE `id`=?', 'foo');
$errors = $db->errors;
</pre>
