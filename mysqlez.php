<?php class mysqlez extends mysqli {

# Properties #

public $errors = array();

protected $safeFunctions = array("CURRENT_TIMESTAMP()", "NOW()");

/**
 * Connect to a database and use functions to SELECT, UPDATE and INSERT data.
 * @param {string} $dbHost - Database host address.
 * @param {string} $dbUser - Database username.
 * @param {string} $dbPass - Database password.
 * @param {string} $dbName - Database name.
 * @return {object} - Your connection to the database and it's methods.
 */
function __construct () {

  # Accept either one config array argument, or four separate arguments. #
  switch ( func_num_args() ) {
    case 1:
      $config = func_get_arg(0);
      if ( is_array( $config ) && array_key_exists('host', $config) && array_key_exists('user', $config) && array_key_exists('pass', $config) && array_key_exists('name', $config) ) {
        $dbHost = $config['host'];
        $dbUser = $config['user'];
        $dbPass = $config['pass'];
        $dbName = $config['name'];
      }
      break;
    case 4:
      $config = func_get_args();
      $dbHost = $config[0];
      $dbUser = $config[1];
      $dbPass = $config[2];
      $dbName = $config[3];
      break;
  }

  # Connect to the MySQL server. #
  parent::__construct($dbHost, $dbUser, $dbPass, $dbName);
  if ($this->connect_error) {
    $this->errors[] = $this->connect_errno.": ".$this->connect_error;
    return false;
  }

  # Change character set to utf8 #
  if (!parent::set_charset("utf8")) {
    $this->errors[] = "Error loading character set utf8: " . $this->error;
  }
}

/**
 * Prepare and execute a query on the database.
 * @param {string} $sql      - The SQL query to execute, with '?' placeholders for parameters.
 * @param {array}  [$params] - The parameters to safely fill into the query.
 * @param {string} [$types]  - Data types of the parameters, one character per parameter. ('s':string, 'i':integer, 'd':double, 'b':blob)
 * @return {array} - The results from the query.
 */
public function parameterized_query ( $sql, $params=null, $types=null ) {

  # Prepare the query. #
  if (! is_string($sql) ) {
    $this->errors[] = array(
      'operation' => 'mysqlez sql is_string',
      'errno' => null,
      'error' => "Expecting first parameter to be an SQL string."
    );
    return false;
  }
  $sql = trim( $sql );
  if (! $stmt = parent::prepare($sql) ) {
    $this->errors[] = array(
      'operation' => 'mysqli prepare',
      'errno' => $this->errno,
      'error' => $this->error
    );
    return false;
  }

  # Bind the parameters. #
  if ( $params ) {
    if ( is_scalar( $params ) ) $params = array( $params ); # Recast single, scalar param as single-element array. #
    if (! $types) $types = str_repeat('s', count($params)); # Default to string parameters. #
    $bind_params = array();
    foreach ( $params as &$p ) $bind_params[] = &$p; # Bound parameters must be passed by reference. #
    array_unshift( $bind_params, $types );
    if (! call_user_func_array( array($stmt, "bind_param"), $bind_params )) {
      $this->errors[] = array(
        'operation' => 'mysqli bind_params',
        'errno' => $this->errno,
        'error' => $this->error
      );
      return false;
    }
  }

  # Execute the query. #
  if (! $stmt->execute()) {
    $this->errors[] = array(
      'operation' => 'mysqli execute',
      'errno' => $this->errno,
      'error' => $this->error
    );
    return false;
  }

  # Return results based on query type. #
  $verb = strtoupper( preg_replace( '/^(\w+).*$/s', '$1', $sql ) );
  if ( $verb === "SELECT" || $verb === "DESCRIBE" ) {

    # Identify the columns in the SELECT result set. #
    $fields = array();
    foreach ( $stmt->result_metadata()->fetch_fields() as $field) {
      $fields[] = $field->name;
    }
    $row = array();
    $bind_results = array();
    foreach ($fields as $fieldName) {
      $row[$fieldName] = null;
      $bind_results[] = &$row[$fieldName];
    }
    if (! call_user_func_array( array($stmt, "bind_result"), $bind_results )) {
      $this->errors[] = array(
        'operation' => 'mysqli bind_result',
        'errno' => $this->errno,
        'error' => $this->error
      );
      return false;
    }

    # Compile and return SELECT results as associative array. #
    $data = array();
    while ($stmt->fetch()) {
      $thisRow = array();
      foreach ( $row as $key => $value ) {
        $thisRow[$key] = $value;
      }
      array_push($data, $thisRow);
    }
    return $data;

  } elseif ( $verb === "INSERT" ) {
    # Return the ID of the inserted row. #
    if ( $stmt->insert_id ) {
      return $stmt->insert_id;
    } elseif ( $stmt->affected_rows ) {
      return true;
    }

  } else {
    # Return the number of rows affected by UPDATE, or DELETE. #
    return $stmt->affected_rows;
  }

}

/**
 * Create an SQL string from an array of fields.
 * @param  {array}  $params            - The SQL query parameters.
 * @param  {string} $params['op']      - The operation to perform, i.e. "SELECT" or "INSERT".
 * @param  {string} $params['table']   - The table in which to perform the operation.
 * @param  {array}  $params['columns'] - The table columns (fields) to include in the SQL.
 * @param  {bool}   $params['update']  - SQL includes "ON DUPLICATE KEY UPDATE..." commands when set to true.
 * @param  {bool}   $params['ignore']  - SQL includes "IGNORE" duplicate keys command in INSERT operations when set to true.
 * @return {string} - The resulting SQL.
 */
public function compile_sql ($params) {
  $op      = isset($params['op'])      ? $params['op']      : 'SELECT';
  $table   = isset($params['table'])   ? $params['table']   : null;
  $columns = isset($params['columns']) ? $params['columns'] : null;
  $update  = isset($params['update'])  ? $params['update']  : false;
  $ignore  = isset($params['ignore'])  ? $params['ignore']  : false;

  if (! is_string($op)) throw new Exception("'op' must be a string.");
  $op = strtoupper($op);

  if (! $table) throw new Exception("You must provide a 'table' name.");

  # Column List #
  if (is_string($columns)) {
    if (strpos($columns, ',')) {
      $columns = explode(',', $columns);
    } else {
      $columns = array($columns);
    }
  }

  $sql = array();
  switch ($op) {
    case 'INSERT':
      if ($ignore) {
        $sql[] = "INSERT IGNORE INTO `$table`";
      } else {
        $sql[] = "INSERT INTO `$table`";
      }
      if (! is_array($columns)) throw new Exception("Expecting array of 'columns' for INSERT operation.");
      $sql[] = "(" . $this->compile_columns($columns) . ")";
      $sql[] = "VALUES (" . implode(',', array_fill(0, count($columns), "?")) . ")";
      if ($update) {
        $sql[] = "ON DUPLICATE KEY UPDATE " . $this->compile_columns($columns, true);
      }
      break;
  }

  return implode(' ', $sql);
}

/**
 * Create an SQL-compatible list of columns, optionally as a list of value updates for duplicate keys.
 * @param  {array} $columns  - The columns.
 * @param  {bool}  $asUpdate - When true, return in format of "`column_name`=VALUES(`column_name`)".
 * @return {string} - The resulting SQL-compatible string.
 */
public function compile_columns ($columns, $asUpdate=false) {
  if (count($columns) == 1 && $columns[0] === '*') throw new Exception("Cannot compile '*' as column list.");
  foreach ($columns as &$column) {
    $column = ($asUpdate) ? "`$column`=VALUES(`$column`)" : "`$column`";
  }
  return implode(',', $columns);
}


# Convenience Methods (not to include user input) #

public function table_fields ( $table ) {
  $rows = $this->parameterized_query("DESCRIBE $table");
  $fields = array();
  if ( is_array($rows) ) {
    foreach ( $rows as $row ) {
      $fields[] = $row['Field'];
    }
  }
  return $fields;
}

/**
 * UPDATE data in the database.
 * @param {string}       $table   - The table to insert into.
 * @param {array|string} $columns - The list of columns to insert into.
 * @param {array}        $data    - Two-dementional array of rows and the values for each row.
 * @param {bool}         $update  - Whether to update preexisting rows or not.
 * @return integer The number of affected rows.
 */
public function bulk_insert ( $table, $columns, $data, $update=false ) {

  # Set the table. #
  if (! ( is_string($table) && $table ) ) {
    $this->errors[] = "You must provide a table to insert into.";
    return false;
  }
  $sql = array("INSERT INTO `$table`");

  # Set the columns. #
  if (! $columns ) {
    $this->errors[] = "You must provide a list of columns to insert into.";
    return false;
  }
  if (! is_array($columns)) {
    if (strpos($columns, ',')) {
      $columns = explode(',', $columns);
    } else {
      $columns = array($columns);
    }
  }
  foreach ($columns as &$column) {
    $column = ($column === '*') ? '*' : "`$column`";
  }
  $sql[] = "(" . implode(',', $columns) . ")";

  # Set the data. #
  if (! ( $data && is_array($data) && isset($data[0]) && is_array($data[0]) ) ) {
    $this->error = "You must provide a two-dimentional array of data to insert into the table.";
    return false;
  }
  foreach ($data as &$row) {
    $row = "(" . $this->_list($row) . ")";
  }
  $sql[] = "VALUES " . implode(',', $data);

  # Optionally, update preexisting rows. #
  if ( $update ) {
    $sql[] = "ON DUPLICATE KEY UPDATE " . self::_update_values( $columns );
  }

  # Execute the query. #
  $sql = implode(' ', $sql);
  if ( $this->query($sql) ) {
    return $this->affected_rows;
  }
  return false;
}

protected function _list ($data) {
  $list = array();
  foreach ($data as &$value) {
    if ($value === null) {
      $list[] = "NULL";
    } elseif (in_array($value, $this->safeFunctions)) {
      $list[] = "$value";
    } else {
      $value = parent::real_escape_string($value);
      $list[] = "'$value'";
    }
  }
  return implode(',', $list);
}

protected function _update_values ($columns) {
  foreach ( $columns as &$col ) {
    $col = "$col=VALUES($col)";
  }
  return implode(',', $columns);
}

} ?>