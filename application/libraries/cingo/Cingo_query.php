<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Rejiggers public methods from CodeIgniter's Query Builder for MongoDB
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


require_once('Cingo_base.php');
require_once('Cingo_result.php');


class Cingo_query extends Cingo_base
{

  public function __construct() {
    parent::__construct();
    $this->reset_query();
  }


  public function query($compiled) {
    $this->reset_query();
    $this->_collection = $compiled->query['collection'];
    $this->_projection = $compiled->query['projection'];
    $this->_filter = $compiled->query['filter'];
    $this->_sort = $compiled->query['sort'];
    $this->_limit = $compiled->query['limit'];
    $this->_skip = $compiled->query['skip'];
    $this->_distinct = $compiled->query['distinct'];
    return $this->_query();
  }


  public function get($collection=NULL, $limit=NULL, $offset=NULL) {
    return $this->_query($collection, NULL, $limit, $offset);
  }


  public function get_compiled_select($collection=NULL) {
    return $this->_compile($collection);
  }


  public function get_where($collection=NULL, $where=NULL, $limit=NULL, $offset=NULL) {
    return $this->_query($collection, $where, $limit, $offset);
  }

/**
 * Adds a projection parameter to the query
 *
 * @link http://www.codeigniter.com/userguide3/database/query_builder.html#selecting-data
 *       this->db->select() (CI Query Builder)
 *
 * @param mixed $fields a list of fields as a comma-delimited string or array
 *
 * @return $this
 */
  public function select($fields) {
    if (!is_array($fields)) {
      $fields = explode(',', $fields);
      $fields = array_map('trim', $fields);
    }
    $fields = array_filter($fields);
    if (!empty($fields)) {
      foreach ($fields as $field) {
        $this->_projection[$field] = 1;
      }
    }
    return $this;
  }


  /**
   * Builds query to return the highest value from a field
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#selecting-data
   *       this->db->select_max() (CI Query Builder)
   *
   * @param string $field   the name of a field
   * @param string $renamed a new name for the resulting field. Not
   *                        implemented here.
   *
   * @return $this
   */
  public function select_max($field, $renamed=NULL) {
    if (!is_null($renamed)) {
      trigger_error('The $renamed parameter is currently ignored', E_USER_WARNING);
    }
    $this->select($field)->limit(1)->order_by($field, -1);
    return $this;
  }


  /**
   * Builds query to return the lowest value from a field
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#selecting-data
   *       this->db->select_min() (CI Query Builder)
   *
   * @param string $field   the name of a field
   * @param string $renamed a new name for the resulting field. Not
   *                        implemented here.
   *
   * @return $this
   */
  public function select_min($field, $renamed=NULL) {
    if (!is_null($renamed)) {
      trigger_error('The $renamed parameter is currently ignored', E_USER_WARNING);
    }
    $this->select($field)->limit(1)->order_by($field, 1);
    return $this;
  }


  /**
   * Not implemented
   *
   */
  public function select_avg($field, $renamed=NULL) {
    trigger_error('The select_avg() method has not been implemented in Cingo', E_USER_ERROR);
  }


  /**
   * Not implemented
   *
   */
  public function select_sum($field, $renamed=NULL) {
    trigger_error('The select_sum() method has not been implemented in Cingo', E_USER_ERROR);
  }


  /**
   * Adds collection parameter to the query
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#selecting-data
   *       this->db->from() (CI Query Builder)
   *
   * @param string $collection the name of a collection
   *
   * @return $this
   */
  public function from($collection) {
    if (empty($collection)) {
      trigger_error('Collection is blank', E_USER_ERROR);
    }
    $this->_collection = $collection;
    return $this;
  }


  /**
   * Not implemented
   *
   */
  public function join($colleciton, $join) {
    trigger_error('The join() method has not been implemented in Cingo', E_USER_ERROR);
  }


  /**
   * Add statements to the query filter using $and
   *
   * The custom string option from the CodeIgniter Query Builder has not
   * been implemented, but the various key/value options should work.
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#looking-for-specific-data
   *       this->db->where() (CI Query Builder)
   *
   * @param mixed $var     a field name (optionally including an operator) or
   *                       associative array
   * @param mixed $val     a value (if $var is a field name) or NULL (if $var
   *                       is an associative array)
   * @param bool  $protect specifies whether to backtick field names in a SQL
   *                       query. Not implemented but kept for consistency.
   *
   * @return $this
   */
  public function where($var, $val=NULL, $protect=TRUE) {
    $statements = $this->_prep_select($var, $val);
    return $this->_filter_statements('$and', $statements);
  }


  public function not_where($var, $val=NULL, $protect=TRUE) {
    $statements = $this->_prep_select($var, $val, '$not');
    return $this->_filter_statements('$and', $statements);
  }


  /**
   * Add statements to the query filter using $or
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#looking-for-specific-data
   *       this->db->or_where() (CI Query Builder)
   *
   * @param mixed $var     a field name (optionally including an operator) or
   *                       associative array
   * @param mixed $val     a value (if $var is a field name) or NULL (if $var
   *                       is an associative array)
   * @param bool  $protect specifies whether to backtick field names in a SQL
   *                       query. Not implemented but kept for consistency.
   *
   * @return $this
   */
  public function or_where($var='', $val=NULL, $protect=TRUE) {
    $statements = $this->_prep_select($var, $val);
    return $this->_filter_statements('$or', $statements);
  }


  /**
   * Add $in statement to query filter with $and
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#looking-for-specific-data
   *       this->db->where_in() (CI Query Builder)
   *
   * @param mixed $field  name of a field
   * @param array $in     list of values to match
   *
   * @return $this
   */
  public function where_in($field, array $in) {
    $statements = $this->_prep_select($field, $in, '$in');
    return $this->_filter_statements('$and', $statements);
  }


  /**
   * Add $in statement to query filter using $or
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#looking-for-specific-data
   *       this->db->or_where_in() (CI Query Builder)
   *
   * @param mixed $field  name of a field
   * @param array $in     list of values to match
   *
   * @return $this
   */
  public function or_where_in($field, array $in) {
    $statements = $this->_prep_select($field, $in, '$in');
    return $this->_filter_statements('$or', $statements);
  }


  /**
   * Add $nin statement to query filter using $and
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#looking-for-specific-data
   *       this->db->or_where_not_in() (CI Query Builder)
   *
   * @param mixed $field  name of a field
   * @param array $nin    list of values to exclude
   *
   * @return $this
   */
  public function where_not_in($field, array $nin) {
    $statements = $this->_prep_select($field, $nin, '$nin');
    return $this->_filter_statements('$and', $statements);
  }


  /**
   * Add $nin statement to query filter using $or
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#looking-for-specific-data
   *       this->db->where_not_in() (CI Query Builder)
   *
   * @param mixed $field  name of a field
   * @param array $nin    list of values to exclude
   *
   * @return $this
   */
  public function or_where_not_in($field, array $nin) {
    $statements = $this->_prep_select($field, $nin, '$nin');
    return $this->_filter_statements('$or', $statements);
  }


  /**
   * Add statements with wildcards to the query filter using $and
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#looking-for-specific-data
   *       this->db->like() (CI Query Builder)
   *
   * @param mixed $var      a field name (optionally including an operator) or
   *                        associative array
   * @param mixed $val      a value (if $var is a field name) or NULL (if $var
   *                        is an associative array)
   * @param bool  $wildcard specifies type of wildcard search. One of
   *                        'before', 'after', 'both', or 'none'.
   *
   * @return $this
   */
  public function like($var, $val=NULL, $wildcard='both') {
    $statements = $this->_prep_select($var, $val, '', $wildcard);
    return $this->_filter_statements('$and', $statements);
  }


  /**
   * Add statements with wildcards to the query filter using $or
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#looking-for-specific-data
   *       this->db->or_like() (CI Query Builder)
   *
   * @param mixed $var      a field name (optionally including an operator) or
   *                        associative array
   * @param mixed $val      a value (if $var is a field name) or NULL (if $var
   *                        is an associative array)
   * @param bool  $wildcard specifies type of wildcard search. One of
   *                        'before', 'after', 'both', or 'none'.
   *
   * @return $this
   */
  public function or_like($var, $val=NULL, $wildcard='both') {
    $statements = $this->_prep_select($var, $val, '', $wildcard);
    return $this->_filter_statements('$or', $statements);
  }


  /**
   * Add statements with wildcards to the query filter using $and and $not
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#looking-for-specific-data
   *       this->db->not_like() (CI Query Builder)
   *
   * @param mixed $var      a field name (optionally including an operator) or
   *                        associative array
   * @param mixed $val      a value (if $var is a field name) or NULL (if $var
   *                        is an associative array)
   * @param bool  $wildcard specifies type of wildcard search. One of
   *                        'before', 'after', 'both', or 'none'.
   *
   * @return $this
   */
  public function not_like($var, $val=NULL, $wildcard='both') {
    $statements = $this->_prep_select($var, $val, '$not', $wildcard);
    return $this->_filter_statements('$and', $statements);
  }


  /**
   * Add statements with wildcards to the query filter using $or and $not
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#looking-for-specific-data
   *       this->db->or_not_like() (CI Query Builder)
   *
   * @param mixed $var      a field name (optionally including an operator) or
   *                        associative array
   * @param mixed $val      a value (if $var is a field name) or NULL (if $var
   *                        is an associative array)
   * @param bool  $wildcard specifies type of wildcard search. One of
   *                        'before', 'after', 'both', or 'none'.
   *
   * @return $this
   */
  public function or_not_like($var, $val=NULL, $wildcard='both') {
    $statements = $this->_prep_select($var, $val, '$not', $wildcard);
    return $this->_filter_statements('$or', $statements);
  }


  /**
   * Not implemented
   *
   */
  public function group_by($fields) {
    trigger_error('The group_by() method has not been implemented in Cingo', E_USER_ERROR);
  }


  /**
   * Converts query to return distinct values only
   *
   * In MongoDB, a simple distinct search is run using a command, not a
   * query, and can only be run on a single field at a time. Cingo converts
   * the returned document to a series of documents, one per value, so that
   * these result sets can be processed the same as a query.
   *
   * A multi-field distinct search using the aggregate functions is possible,
   * but has not been implemented.
   *
   * FIXME: Implement aggregate search for multiple fields
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#looking-for-specific-data
   *       this->db->distinct() (CI Query Builder)
   *
   * @return $this
   */
  public function distinct() {
    $this->_distinct = TRUE;
  }


  /**
   * Not implemented
   *
   */
  public function having($var, $val=NULL) {
    trigger_error('The having() method has not been implemented in Cingo', E_USER_ERROR);
  }


  /**
   * Not implemented
   *
   */
  public function not_having($var, $val=NULL) {
    trigger_error('The having() method has not been implemented in Cingo', E_USER_ERROR);
  }


  /**
   * Adds a sort parameter to the query
   *
   * Multiple sort paremeters can be added by using this method multiple times
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#ordering-results
   *       this->db->order_by() (CI Query Builder)
   *
   * @param string $field     the field to sort on
   * @param string $direction the direction of the sort. One of 'ASC', 'DESC',
   *                          'RANDOM', 1, or -1.
   *
   * @return $this
   */
  public function order_by($field, $direction='DESC') {
    switch ($direction) {
      case 'ASC':
        $direction = 1;
        break;
      case 'DESC':
        $direction = -1;
        break;
      case 'RANDOM':
        $direction = NULL;
        break;
      case 1:
        break;
      case -1:
        break;
      default:
        trigger_error('Sort must be one of 1, -1, ASC, DESC, or RANDOM', E_USER_ERROR);
    }
    if (!is_null($direction)) {
      $this->_sort[$field] = $direction;
    }
  }


  /**
   * Adds limit and skip parameters to the query
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#limiting-or-counting-results
   *       this->db->limit() (CI Query Builder)
   *
   * @param integer $limit  number of records to return
   * @param integer $offset number of records to skip
   *
   * @return $this
   */
  public function limit($limit, $offset=NULL) {
    if (!is_int($limit)) {
      trigger_error('Limit must be an integer', E_USER_ERROR);
    }
    elseif ($limit < 1) {
      trigger_error('Limit must be greater than or equal to 1', E_USER_ERROR);
    }
    $this->_limit = $limit;
    if (!is_null($offset)) {
      if (!is_int($offset)) {
        trigger_error('Offset must be an integer', E_USER_ERROR);
      }
      elseif ($offset < 0) {
        trigger_error('Offset must be greater than 0', E_USER_ERROR);
      }
      $this->_skip = $offset;
    }
    return $this;
  }


  /**
   * Returns the number of records matching the stored query
   *
   * @link http://www.codeigniter.com/userguide3/database/query_builder.html#limiting-or-counting-results
   *       this->db->count_all_results() (CI Query Builder)
   *
   * @param mixed $collection  the name of a collection
   * @param bool  $keep_select specifies whether to keep the projection
   *                           parameter
   *
   * @return integer number of matching documents
   */
  public function count_all_results($collection=NULL, $keep_select=FALSE) {
    // In the original CI function, select fields are cleared when this
    // function is triggered
    if (!$keep_select) {
      $this->_projection = array();
    }
    $args = array(
      'count' => $this->_collection,
      'query' => $this->_filter,
      'maxTimeMS' => $this->_max_time_ms_count
    );
    if (!empty($this->_limit)) { $args['limit'] = $this->_limit; }
    if (!empty($this->_skip)) { $args['skip'] = $this->_skip; }
    $cmd = new MongoDB\Driver\Command($args);
    try {
      $cursor = $this->manager->executeCommand($this->database, $cmd);
    } catch (Exception $e) {
      #show_404($e->getMessage() . ' (' . $e->getCode() . ')');
      #log_message('error', 'Cingo_query: ' . $e->getMessage() . ' (' . $e->getCode() . ')');
      return NULL;
    }
    foreach ($cursor as $doc) {
      return $doc->n;
    }
  }


  public function count_all($collection) {
    $args = array('count' => $this->_collection);
    $cmd = new MongoDB\Driver\Command($args);
    $cursor = $this->manager->executeCommand($this->database, $cmd);
    foreach ($cursor as $doc) {
      return $doc->n;
    }
  }


  /**
   * Not implemented
   *
   */
  public function group_start($parent=NULL) {
    $this->_create_group('$and', $parent);
  }


  /**
   * Not implemented
   *
   */
  public function or_group_start($parent=NULL) {
    $this->_create_group('$or', $parent);
  }


  /**
   * Not implemented
   *
   */
  public function not_group_start($parent=NULL) {
    $this->_create_group('$not', $parent);
  }


  /**
   * Not implemented
   *
   */
  public function group_end() {
    array_pop($this->_group_indexes);
  }


  public function reset_query() {
    $this->_collection = NULL;
    $this->_projection = array();
    $this->_filter = array();
    $this->_sort = array();
    $this->_limit = NULL;
    $this->_skip = NULL;
    $this->_distinct = FALSE;

    #$this->_first = TRUE;
    $this->_groups = array();
    $this->_group_indexes = array();
    #$this->_create_group('$and');
    #$this->_create_group('$or', 0);
  }


  /**
   * Not implemented
   *
   */
  public function start_cache() {
    trigger_error('The start_cache() method has not been implemented in Cingo', E_USER_ERROR);
  }


  /**
   * Not implemented
   *
   */
  public function stop_cache() {
    trigger_error('The stop_cache() method has not been implemented in Cingo', E_USER_ERROR);
  }


  /**
   * Not implemented
   *
   */
  public function flush_cache() {
    trigger_error('The flush_cache() method has not been implemented in Cingo', E_USER_ERROR);
  }

}
?>
