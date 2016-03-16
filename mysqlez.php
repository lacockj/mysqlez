<?php class mysqlez extends mysqli {

# Properties #

public $errors = array();

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
  $sql = trim( $sql );
  if (! is_string($sql) ) {
    $this->errors[] = array(
      'operation' => 'mysqlez sql is_string',
      'errno' => null,
      'error' => "Expecting first parameter to be an SQL string."
    );
    return false;
  }
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
  echo $verb;
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

} ?>