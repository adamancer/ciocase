<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Backend functions to format CodeIgniter Query Builder queries for MongoDB
 *
 * @package   cingo
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


require_once('Cingo_compiled.php');
require_once('Cingo_connect.php');
require_once('Cingo_fake_cursor.php');
require_once('Cingo_result.php');


class Cingo_base
{

  /**
    *
    * @var Cingo_connect
    */
  protected $connection;

  /**
    * Alias for the MongoDB connection manager defined by $connection
    * @var MongoDB\Driver\Manager
    */
  protected $manager;

  /**
    * Name of the current database
    * @var string
    */
  protected $database;

  /**
    * Name of the current collection
    * @var string
    */
  protected $_collection;

  /**
    * List of projected fields
    * @var array
    */
  protected $_projection;

  /**
    * A Mongo query
    * @var array
    */
  protected $_filter;

  /**
    * List of sort criteria
    * @var array
    */
  protected $_sort;

  /**
    * Number of records to return
    * @var integer
    */
  protected $_limit;

  /**
    * Number of records to skip
    * @var integer
    */
  protected $_skip;

  /**
    * Specifies whether to run a distinct query
    * @var bool
    */
  protected $_distinct;

  /**
    * Specifies the maximum time in milliseconds to allow a query to run
    * @var bool
    */
  public $_max_time_ms;

  /**
    * Specifies the maximum time in milliseconds to allow a county to run
    * @var bool
    */
  public $_max_time_ms_count;

  /**
    * Specifies whether this is the first statement added to the filter
    * @var bool
    */
  protected $_first;

  /**
    * List of query statements
    * @var array
    */
  protected $_groups;

  /**
    * Current group index. Used to group query statements.
    * @var integer
    */
  protected $_group_index;
  protected $_group_indexes;

  /**
    * List of valid operators in MongoDB
    * @var array
    */
  protected $operators = array(
    '$all',
    '$and',
    '$eq',
    '$exists',
    '$in',
    '$gt',
    '$gte',
    '$lt',
    '$lte',
    '$ne',
    '$nin',
    '$nor',
    '$not',
    '$or',
    '$size',
    '$type'
  );

  /**
    * Associative array mapping SQL to MongoDB syntax
    * @var array
    */
  protected $operator_map = array(
    ' is null' => array('operator' => '$exists', 'value' => 0),
    ' is not null' => array('operator' => '$exists', 'value' => 1),
    ' !=' => array('operator' => '$ne', 'value' => NULL),
    ' >' => array('operator' => '$gt', 'value' => NULL),
    ' >=' => array('operator' => '$gte', 'value' => NULL),
    ' <' => array('operator' => '$lt', 'value' => NULL),
    ' <=' => array('operator' => '$lte', 'value' => NULL)
  );

  public function __construct() {
    $this->connection = new Cingo_connect();
    $this->manager = $this->connection->manager;
    $this->database = $this->connection->database;
    $this->reset_query();

    $CI =& get_instance();
    $CI->config->load('cingo');
    $this->_max_time_ms = (int) trim($CI->config->item('max_time_ms'));
    $this->_max_time_ms_count = (int) trim($CI->config->item('max_time_ms_count'));
  }

  /**
   * Pretty print an object
   *
   * @param mixed $obj    the object to print
   * @param bool  $return if TRUE, returns the pretty-printed string instead
   *                      of printing it
   *
   * @return mixed pretty-printed string or void
   */
  public function pprint($obj, $return=FALSE) {
    if ($return) {
      return '<pre>' . print_r($obj, $return) . '</pre>';
    }
    else {
      echo '<pre>'; print_r($obj); echo '</pre>';
    }
  }


  /**
   * Create a regex pattern
   *
   * Does not need to be used with the $regex operator
   *
   * @param string $val   the value to format
   * @param string $kind  specifies type of wildcard search. One of
   *                      'before', 'after', 'both', or 'none'.
   * @param string $flags regex flags
   *
   * @return MongoDB\BSON\Regex
   */
  public function create_pattern($val, $kind='both', $flags='i') {
    $before = '';
    $after = '';
    // Check if string is already formatted with wildcards
    if (strpos($val, '*') !== FALSE) {
      if (substr($val, 0, 1) != '*') {
        $before = '^';
      }
      if (substr($val, -1) != '*') {
        $after = '$';
      }
      $val = trim($val, '*');
    }
    switch ($kind) {
      case 'before':
        $after = '$';
        break;
      case 'after':
        $before = '^';
        break;
      case 'both':
        break;
      case 'none':
        // e.g., to make a case-insensitive search
        $before = '^';
        $after = '$';
        break;
      default:
        trigger_error('Invalid wildcard: ' . $kind, E_USER_WARNING);
    }
    return new MongoDB\BSON\Regex($before . $val . $after, $flags);
  }


  protected function _compile($collection=NULL) {
    if (!is_null($collection)) {
      $this->from($collection);
    }
    if (!$this->_filter) {
      $this->_create_filter();
    }
    $query = array(
      'collection' => $this->_collection,
      'projection' => $this->_projection,
      'filter' => $this->_filter,
      'sort' => $this->_sort,
      'limit' => $this->_limit,
      'skip' => $this->_skip,
      'distinct' => $this->_distinct
    );
    return new Cingo_compiled($query);
  }


  /**
   * Explain a query
   *
   * @param string $verbosity
   *
   * @return string
   */
  public function explain($verbosity='queryPlanner') {
    if (!in_array($verbosity, array('queryPlanner', 'executionStats', 'allPlansExecution'))) {
      trigger_error('Invalid verbosity: ' . $verbosity, E_USER_WARNING);
    }
    // <3.0
    if (FALSE) {
      $options = array('explain' => TRUE);
      $query = $this->_create_query($collection, $where, $limit, $offset, $options);
      $this->pprint($query);
      $datasource = $this->database . '.' . $this->_collection;
      $cursor = $this->manager->executeQuery($datasource, $query);
      foreach ($cursor as $doc) {
        return $this->pprint($doc, TRUE);
      }
    }
    else {
      $compiled = $this->_compile();
      $find = array('find' => $this->_collection);
      foreach ($compiled->query as $key => $val) {
        if ($val && !in_array($key, array('distinct', 'collection'))) {
          $find[$key] = $val;
        }
      }
      $args = array('explain' => $find, 'verbosity' => $verbosity);
      #print_r($args); exit();
      $cmd = new MongoDB\Driver\Command($args);
      $cursor = $this->manager->executeCommand($this->database, $cmd);
      foreach ($cursor as $doc) {
        return $doc;
      }
    }
  }


  public function list_indexes() {
    $cmd = new MongoDB\Driver\Command([
      'listIndexes' => $this->_collection
    ]);
    $cursor = $this->manager->executeCommand($this->database, $cmd);
    $docs = array();
    foreach ($cursor as $doc) {
      $docs[] = $this->pprint($doc, TRUE);
    }
    return implode('', $docs);
  }


  protected function _create_filter() {
    // Check for default group
    $i = end($this->_group_indexes);
    if (!$this->_groups[$i]['group'] && !$this->_groups[$i]['children']) {
      $this->_filter = array();
      return;
    }
    // Loop through groups until no children remain
    $groups = $this->_groups;
    #print_r($groups); exit();
    while (TRUE) {
      $nulls = [];
      foreach ($this->_groups as $i => $group) {
        $group = $groups[$i];
        if (is_null($group)) {
          $nulls[] = $i;
        }
        elseif (!array_key_exists('children', $group) || !$group['children']) {
          $parent = $group['parent'];
          $simplified = $this->simplify_filter($group['group']);
          $groups[$parent]['group'][] = $this->simplify_filter([$group['conj'] => $simplified]);
          #if ($group['conj'] == '$not') {
          #  $groups[$parent]['group'][] = ['$not' => $group['group'][0]];
          #}
          #else {
          #  $groups[$parent]['group'][] = [$group['conj'] => $group['group']];
          #}
          #array_remove($groups[$parent]['children'], $i);
          if (array_key_exists('children', $groups[$parent])
              && ($key = array_search($i, $groups[$parent]['children'])) !== FALSE) {
            unset($groups[$parent]['children'][$key]);
          }
          $groups[$i] = NULL;
        }
      }
      if (count($nulls) == count($groups) - 1) {
        break;
      }
    }
    $this->_filter = $groups[NULL]['group'][0];
  }


  protected function simplify_filter($filter) {
    #$child = array_values($filter);
    #while (count($filter) == 1
    #       && is_array($child[0])
    #       && ($this->is_zero_or_conjunction(array_keys($child[0])[0])
    #           || $this->is_zero_or_conjunction(array_keys($filter)[0]))) {
    #echo array_keys($filter)[0] . ', ' . count($filter) . "\n";
    if ($filter) {
      $last_good = array_keys($filter)[0];
      while (count($filter) == 1
             && $this->is_zero_or_conjunction(array_keys($filter)[0])) {
        #echo array_keys($filter)[0] . ', ' . count($filter) . "\n";
        $last = array_keys($filter)[0];
        $filter = $filter[$last];
        if ($last) {
          $last_good = $last;
        }
      }
      if (count($filter) > 1) {
        $filter = [$last_good => $filter];
      }
    }
    return $filter;
  }


  protected function is_zero_or_conjunction($val) {
    return (is_null($val) || $val === 0 || in_array($val, ['$and', '$or']));
  }


  protected function _create_group($conj, $parent=NULL) {
    #echo 'Creating ' . $conj . ' group...' . "\n";
    $group = array(
      'conj' => $conj,
      'parent' => $parent,
      'group' => array(),
    );
    $this->_groups[] = $group;
    $i = count($this->_groups) - 1;
    if (is_null($parent) && count($this->_group_indexes)) {
      $parent = end($this->_group_indexes);
    }
    $this->_group_indexes[] = $i;
    if (!is_null($parent)) {
      $this->_groups[$i]['parent'] = $parent;
      $this->_groups[$parent]['children'][] = $i;
    }
    return $i;
  }


  protected function _create_query($collection=NULL, $where=NULL, $limit=NULL,
                                   $offset=NULL, $options=NULL) {
    if (!is_null($collection)) { $this->from($collection); }
    if (!is_null($where)) { $this->where($where); }
    if (!is_null($limit) || !is_null($offset)) { $this->limit($limit, $offset); }
    // Verify that collection is set. This is the only required parameter.
    if (empty($this->_collection)) {
      trigger_error('No collection specified', E_USER_ERROR);
    }
    // Create filter from groups of query statements
    if (!$this->_filter) {
      $this->_create_filter();
    }
    // Set options
    if (!is_array($options)) {
      $options = array();
    }
    if (!empty($this->_limit)) { $options['limit'] = $this->_limit; }
    if (!empty($this->_projection)) { $options['projection'] = $this->_projection; }
    if (!empty($this->_skip)) { $options['skip'] = $this->_skip; }
    if (!empty($this->_sort)) { $options['sort'] = $this->_sort; }
    $options['maxTimeMS'] = $this->_max_time_ms;
    // Return the formatted query
    return new MongoDB\Driver\Query($this->_filter, $options);
  }


  protected function _filter_statements($operator='$and', $statements=NULL) {
    // The first statement is always treated as an $or
    #if ($this->_first) {
    #  $operator = '$or';
    #}
    #elseif ($operator == '$and') {
    #  $this->_create_group('$or', $this->_groups[$i]['parent']);
    #}
    // Identify the current group, creating one if none exist
    if (!$statements) {
      return $this;
    }
    if (!$this->_groups) {
      $this->_create_group($operator);
    }
    $i = end($this->_group_indexes);
    // Force the operator of the current group to match this one. Combinations
    // of $and and $or may therefore have unpredictable results.
    #$this->_groups[$i]['conj'] = $operator;
    foreach ($statements as $statement) {
      if (!in_array($statement, $this->_groups[$i]['group'])) {
        $this->_groups[$i]['group'][] = $statement;
      }
    }
    return $this;
  }


  protected function _find_operators($field, $value) {
    $lc_field = strtolower($field);
    foreach ($this->operator_map as $op => $retval) {
      if (substr($lc_field, -strlen($op)) == $op) {
        return array('operator' => $retval['operator'],
                     'field' => substr($field, 0, strlen($field) - strlen($op)),
                     'value' => is_null($retval['value']) ? $value : $retval['value']);
      }
    }
    return array('operator' => NULL,
                 'field' => $field,
                 'value' => $value);
  }


  protected function _group_to_filter($group) {
    // Check for substatements created from children of this group
    if (array_key_exists('subgroup', $group)) {
      $this->pprint($group);
      if (!array_key_exists('group', $group) || !$group['group']) {
        $group['group'] = $group['subgroup'];
      }
      else {
        $group['group'][] = $group['subgroup'];
      }
    }
    // Strip the outermost conjunction if only one statement
    if (is_string(array_keys($group['group'])[0])) {
      return $group['group'];
    }
    else {
      return array($group['conj'] => $group['group']);
    }
  }


  protected function _prep_select($var, $val, $operator='', $wildcard='none') {
    // Reset filter whenever changes are made
    $this->_filter = NULL;
    // Validate parameters to confirm that query is legal
    $operator = $this->_validate_operator($operator);
    // Parse $var and $val to determine how keys/values have been passed
    if (is_array($var) && !is_null($val)) {
      trigger_error('$val should be null if $var is an array', E_USER_WARNING);
    }
    elseif (!is_array($var)) {
      if (strpos($var, ' ') !== FALSE) {
        // If $var is not an array, it is treated as a field name
        $retval = $this->_find_operators($var, $val);
        if (!is_null($retval['operator'])) {
          $operator = $retval['operator'];
        }
        $var = $retval['field'];
        $val = $retval['value'];
      }
      $var = array($var => $val);
      $val = NULL;
    }
    $query = array();
    foreach ($var as $key => $val) {
      // Check for precompiled searches
      if (in_array($key, $this->operators)) {
        return $var;
      }
      // Create regex patterns if $wildcard is specified
      if ($wildcard && $wildcard != 'none') {
        if (is_array($val)) {
          foreach ($val as $i => $v) {
            $val[$i] = $this->create_pattern($v, $wildcard, 'i');
          }
        }
        else {
          $val = $this->create_pattern($val, $wildcard, 'i');
        }
      }
      if (!empty($operator)) {
        $query[] = array($key => array($operator => $val));
      }
      else {
        $query[] = array($key => $val);
      }
    }
    return $query;
  }


  public function _query($collection=NULL, $where=NULL, $limit=NULL, $offset=NULL) {
    $query = $this->_create_query($collection, $where, $limit, $offset);
    #echo $this->_compile();
    #echo $this->explain();
    #exit();
    if ($this->_distinct) {
      // Break the result of a distinct query into documents (instead of returning
      // a single document with multiple values)
      if (count($this->_projection) != 1) {
        trigger_error('Multiple fields specified for a MongoDB distinct query', E_USER_WARNING);
      }
      if ($this->_limit || $this->_skip) {
        trigger_error('Limit or skip parameter set for a MongoDB distinct query', E_USER_WARNING);
      }
      $field = array_keys($this->_projection)[0];
      $cmd = new MongoDB\Driver\Command([
        'distinct' => $this->_collection,
        'key' => $field,
        'query' => (object) $this->_filter
      ]);
      $cursor = $this->manager->executeCommand($this->database, $cmd);
      // Recast result as an array
      $results = array();
      foreach ($cursor as $doc) {
        foreach ($doc->values as $val) {
          $obj = new stdClass();
          $obj->$field = $val;
          $results[] = $obj;
        }
      }
      // Sort results by value, if projected and sort fields are the same
      if ($this->_sort && $field == array_keys($this->_sort)[0]) {
        sort($results);
      }
      $cursor = new Cingo_fake_cursor($results);
      $num_rows = count($results);
    }
    else {
      $datasource = $this->database . '.' . $this->_collection;
      try {
        $cursor = $this->manager->executeQuery($datasource, $query);
      } catch (Exception $e) {
        #show_error($e->getMessage() . ' (' . $e->getCode() . ')');
        #print_r($query); exit();
        return NULL;
      }
      // Reset limit and skip so we get the full count
      $this->_limit = NULL;
      $this->_skip = NULL;
      // $num_rows is -1 if this query fails
      $num_rows = $this->count_all_results($this->_collection, TRUE);
      $cursor->setTypeMap(array('array' => 'array'));
    }
    $this->reset_query();
    return new Cingo_result($cursor, $num_rows);
  }


  protected function _validate_operator($operator) {
    if (!empty($operator)) {
      if (substr($operator, 0, 1) != '$') {
        $operator = '$' . $operator;
      }
      if (!in_array($operator, $this->operators)) {
        trigger_error($operator . ' is not a valid operator', E_USER_ERROR);
      }
    }
    return $operator;
  }

}
?>
