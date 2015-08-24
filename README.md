# mysqlez #
This PHP class extension simplifies the SQL preparation and execution process, while maintaining protection against SQL injection.

## Example INSERT ##

<pre>
// Include Database Class Extension //
require_once('Database.php');

// Get POSTed Data //
$userSubmittedData = json_decode($_POST['data']);

// Connect to Database //
$db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->error) {
  // error handling //
  // error number: $db->errno
  // error description: $db->error
}

// Insert Data into Database //
$result = $db->transaction( 'INSERT INTO `table` (`field1`,`field2`) VALUES (?,?)', 'ss', $userSubmittedData );
if ($result === false) {
  // error handling //
}
echo "New data inserted with the following IDs: " . implode(", ", $result);
</pre>

## Example UPDATE ##

<pre>
$result = $db->transaction( 'UPDATE `table` SET `field1` = ? WHERE `field2` >= ?', 'si', $userSubmittedData );
if ($result === false) {
  // error handling //
}
echo "Number of affected rows: $result";
</pre>

## Example SELECT ##

<pre>
$result = $db->transaction( 'SELECT `field1`,`field2` FROM `table` WHERE `field3` > ?', 'i', $userSubmittedValue );
if ($result === false) {
  // error handling //
}
header('Content-type: text/json');
echo json_encode( $result );
</pre>

## Example DELETE ##

<pre>
#$result = $db->transaction( 'DELETE FROM `table` WHERE `field` = ?', 'i', $userSubmittedValue );
if ($result === false) {
  // error handling //
}
echo "Number of affected rows: $result";
</pre>
