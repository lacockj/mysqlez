# mysqlez #
This PHP class extension simplifies the SQL preparation and execution process, while maintaining protection against SQL injection.

## Example INSERT ##

// Include Database Class Extension //
require_once('Database.php');

// Get POSTed Data //
$dataToInsert = json_decode($_POST['data']);

// Connect to Database //
$db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->error) {
 // error handling //
}

// Insert Data into Database //
$result = $db->transaction( 'INSERT INTO `table` (`field1`,`field2`) VALUES (?,?)', 'ss', $dataToInsert );
if ($result === false) {
  // error handling //
}
echo "New data inserted with the following IDs: " . implode(", ", $result);
