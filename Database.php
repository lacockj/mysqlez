<?php
class Database extends mysqli {
  function __construct ($dbHost, $dbUser, $dbPass, $dbName) {
    parent::__construct($dbHost, $dbUser, $dbPass, $dbName);
    # Change character set to utf8 #
    if (!$this->set_charset("utf8")) {
      $this->error = "Error loading character set utf8: " . $this->error;
    }
  }

  function __destruct () {
    $this->close();
  }

  /**
   *
   * Prepare an SQL query, bind the parameters, compile and execute the transaction,
   * fetch and return the results, all in one operation!
   *
   * @param string $query SQL query with '?' parameter placeholders.
   * @param string $types Parameter types: 'i': integer, 'd': decimal, 's': string, 'b': blob
   * @param array  $data  Array of arrays: Outer: one for each row to insert, Inner: one for each parameter.
   * @return mixed SELECT: associative array of results
   *               INSERT: insert ID, or array of insert IDs
   *               UPDATE: number of affected rows
   *               DELETE: number of affected rows
   */
  public function transaction ($query, $types=null, $data=null) {

    # Number of parameters, and placeholder array of parameters for assignment. #
    $num = (is_string($types)) ? strlen($types) : 0;
    $params = ($num > 0) ? array_fill(0, $num, null) : array();

    # Array of references to the parameters array for binding. #
    $paramRefs = array();
    foreach ($params as &$param) {
      $paramRefs[] = &$param;
    }

    # Prepend parameters with types. #
    array_unshift($paramRefs, $types);

    # Data should be two-dimensional array, or null. #
    if ($data !== null) {
      if (is_array($data)) {
        if (! is_array($data[0])) {
          if ($num == 1) {
            foreach ($data as &$val) {
              $val = array($val);
            }
            unset($val);
          } else {
            $data = array($data);
          }
        }
      } else {
        $data = array(array($data));
      }
    }

    # Prepare query. #
    if ( $stmt = $this->prepare( $query ) ) {
      $insertIDs = array();

      if ($types == null) {
        $stmt->execute();
        if ($stmt->insert_id) $insertIDs[] = $stmt->insert_id;

      } else {
        # Bind parameter references. #
        call_user_func_array(array($stmt, 'bind_param'), $paramRefs);
  
        # Execute query for each row of parameters in data. #
        $this->query("START TRANSACTION");
        foreach ($data as $values) {
          for ($i=0; $i<$num; $i++) {
            $params[$i] = $values[$i];
          }
          $stmt->execute();
          if ($stmt->insert_id) $insertIDs[] = $stmt->insert_id;
        }
        $this->query("COMMIT");
      }

      # When inserting, return insertion IDs. #
      $insertions = count($insertIDs);
      if ($insertions === 1) {
        return $insertIDs[0];
      } elseif ($insertions > 1) {
        return $insertIDs;
      }

      # When selecting, return results. #
      elseif ($meta = $stmt->result_metadata()) {
        $meta = $stmt->result_metadata();
        $row = array();
        $resultBindings = array();
        while ($field = $meta->fetch_field()) {
          $row[$field->name] = null;
          $resultBindings[] = &$row[$field->name];
        }
        $meta->free_result();
        call_user_func_array(array($stmt, 'bind_result'), $resultBindings);
  
        # Fetch results. #
        $results = array();
        while ($stmt->fetch()) {
          $x = array();
          foreach($row as $key => $val) {
            $x[$key] = $val;
          }
          unset($val);
          $results[] = $x;
        }
  
        # Return results as associateve array. #
        return $results;
      }

      # All other statements, return number of affected rows. #
      else {
        return $stmt->affected_rows;
      }

      $stmt->close();

    # When query preparation fails. #
    } else {
      return false;
    }
  }

  public function compile_query ($params) {
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
        $sql[] = $this->compile_columns($columns);
        $sql[] = "VALUES (" . implode(',', array_fill(0, count($columns), "?")) . ")";
        break;
    }

    return implode('', $sql);
  }

  public function compile_columns ($columns) {
    if (count($columns) == 1 && $columns[0] === '*') throw new Exception("Cannot compile '*' as column list.");
    foreach ($columns as &$column) {
      $column = "`$column`";
    }
    return "(" . implode(',', $columns) . ")";
  }

}
?>