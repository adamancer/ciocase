<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Defines methods to query the NMNH Mongo database
 *
 * @package    ciocase
 * @author     Adam Mansur <mansura@si.edu>
 * @copyright  2017 Smithsonian Institution
 * @license    MIT License
 */



 /**
  * Defines methods to query the NMNH Mongo database
  *
  * @package    ciocase
  */
class Mongo_model extends CI_Model {

  /**
	  * An associate array defining the default query statement
	  *
    * All returned records must match this statement.
    *
	  * @var array
	  */
  public $defaults;


  /**
	  * An associate array defining fields that should not use the default query
	  *
    * All returned records must match this statement.
    *
	  * @var array
	  */
  public $ignore_defaults;
  private $use_defaults;


  /**
	  * Contains information about available indexes
    *
    * Indexes are defined in the ciocase.php configuration file.
    *
	  * @var object
	  */
  public $indexing;


  /**
    * Contains information about the available query parameters
    *
    * Query parameters are defined in the ciocase.php configuration file.
    *
    * @var object
    */
  public $query_params;


  /**
	  * Logs information about the query
    *
	  * @var object
	  */
  public $query;


  /**
	  * Logs errors encountered when generating or running the query
    *
	  * @var array
	  */
  public $errors;


  /**
	  * Maps CodeIgniter Active Record operators to MongoDB equivalents
    *
	  * @var array
	  */
  public $operators;


  /**
	  * Constructs a new Mongo model object
	  *
		* @return void
		*
		* @access public
	  */
  public function __construct() {
    parent::__construct();
    $this->load->library('cingo');
    $this->load->helper('ciocase');

    $this->mark = $this->timer->mark('models/Mongo.php');

    // Read settings for implementing various schemas in this backend
    $this->config->load('ciocase');
    $this->defaults = $this->config->item('defaults');
    $this->ignore_defaults = $this->config->item('ignore_defaults');
    $this->use_defaults = TRUE;
    $this->collection = $this->config->item('collection');
    $this->query_params = $this->config->item('query_params');

    $this->ignore = [];
    foreach ($this->config->item('map_or_ignore') as $key => $val) {
      if ($val) {
        $this->query_params[$key] = $this->query_params[$val];
      }
      else {
        $this->ignore[] = $key;
      }
    }

    // Read backend settings
    $this->config->load('cingo');
    $this->indexing = (object) [
      'forbid_distinct' => $this->config->item('forbid_distinct'),
      'indexed' => $this->config->item('indexed'),
      'magic' => $this->config->item('magic')
    ];

    $this->operators = [
      'equals' => '',
      'notEquals' => '!=',
      'lessThan' => '<',
      'lessThanOrEquals' => '<=',
      'greaterThan' => '>',
      'greaterThanOrEquals' => '>=',
      'like' => '',
      'isNull' => 'is null',
      'isNotNull' => 'is not null',
      'in' => '$in',
      'not' => '$not',
      'and' => '$and',
      'or' => '$or'
    ];

    $this->schema = NULL;
    $this->query = ['total' => 0, 'count' => 0];
    $this->count = FALSE;
    $this->errors = [];
    $this->warnings = [];
  }


  /**
	 * Adds a schema object to the backend
	 *
	 * @param Schema $schema
	 *
	 * @access public
	 */
  function add_schema($schema) {
    $this->schema = $schema;
  }



  /**
	 * Executes and provides information about a query
	 *
	 * @param function $callback a function to apply to each returned row
   * @param array    $params   params to pass to the callback function
   *
   * @return array a list of records
	 *
	 * @access public
	 */
  function execute_query($callback=NULL, $params=NULL) {
    if (ENVIRONMENT == 'development') {
      $this->query['compiled'] = $this->cingo->get_compiled_select();
      // Change the condition to TRUE to show the compiled query
      if (FALSE) {
        header("Content-type: application/json");
        echo json_encode($this->cingo->explain(), JSON_PRETTY_PRINT);
        exit();
      }
      if (FALSE) {
        header("Content-type: application/json");
        echo json_encode($this->query['compiled']->query, JSON_PRETTY_PRINT);
        exit();
      }
    }
    // Allow longer count time if count is specified
    $max_time_ms_count = $this->cingo->_max_time_ms_count;
    if ($this->count) {
      $this->cingo->_max_time_ms_count = 0;
    }
    $this->mark->start();
    $query = $this->cingo->get();
    if (is_null($query)) {
      $this->mark->log('Query failed to execute');
      $this->errors[] = 'Query failed to execute';
      $this->errors[] = json_encode($this->query['compiled']->query);
      $this->query['total'] = 0;
      $this->query['count'] = 0;
      return [];
    }
    $this->cingo->_max_time_ms_count = $max_time_ms_count;  // reset timeout
    $this->mark->log('Query succeeded');
    $records = [];
    foreach ($query->result() as $row) {
      $records[] = ($callback) ? call_user_func($callback, $row, $params) : (array) $row;
    }
    $this->query['total'] = $query->num_rows();
    $this->query['count'] = count($records);
    return $records;
  }


  /**
	 * Organizes and executes one or more distinct queries
	 *
	 * @param array $keys       a list of database keys
   * @param bool  $sortValues specifies whether to sort the returned values
	 *
   * @return array a list of values for each of the keys
	 *
	 * @access public
	 */
  function scan($keys, $filter=NULL, $sortValues=TRUE) {
    if (!is_array($keys)) {
      $keys = [$keys];
    }
    $vals = [];
    $compiled = [];
    foreach ($keys as $key) {
      if (!$filter && in_array($key, $this->indexing->forbid_distinct)) {
        $this->errors[] = 'Unfiltered scans are not supported for this path: ';
        continue;
      }
      $result = @$this->scan_one($key, $filter);
      $vals = array_merge($vals, $result->vals);
      if ($result->compiled) {
        $compiled[] = $result->compiled;
      }
    }
    $vals = array_unique($vals);
    if ($sortValues) {
      sort($vals);
    }
    $results = [
      'vals' => $vals,
      'query' => (object) ['compiled' => $compiled],
      'errors' => $this->errors
    ];
    return (object) $results;
  }


  /**
	 * Executes a single distinct query
	 *
	 * @param array $scankey    a database key
   * @param bool  $sortValues specifies whether to sort the returned values
	 *
   * @return object a result object included a list of values and the compiled search
	 *
	 * @access public
	 */
  private function scan_one($scankey, $filter=NULL, $sortValues=FALSE) {
    $this->cingo->select($scankey);
    $this->cingo->distinct();
    $this->cingo->from($this->collection);
    // Add a search filter
    $this->cingo->group_start();
    $this->recurse_filter($filter);
    foreach ($this->defaults as $key => $val) {
      $this->cingo->where($key, $val);
    }
    $this->cingo->group_end();
    $vals = (!$this->errors) ? $this->execute_query([$this, 'get_val'], $scankey) : [];
    if ($sortValues) {
      sort($vals);
    }
    $result = [
      'vals' => $vals,
      'compiled' => isset($compiled) ? $compiled : NULL
    ];
    return (object) $result;
  }


  /**
	 * Retrives the value of a key from a row of data
	 *
	 * @param array   $row a row retrieved from the backend
   * @param string  $key the key to return
	 *
   * @return mixed the value of the key in the row
	 *
	 * @access private
	 */
  private function get_val($row, $key) {
    return $row->{$key};
  }



  /**
	 * Executes a database query based on a query string
	 *
   * @param function $callback a function to apply to each returned row
   * @param array    $params   params to pass to the callback function
   *
   * @return array a result object
	 *
	 * @access public
	 */
  public function search($callback=NULL, $params=NULL) {
    // Get database parameters from query string
    $kwargs = $this->parse_query_string();
    $this->query['kwargs'] = $kwargs;
    // Get non-database parameters
    $format = $this->get_key('format');
    // Force queries using the pywrapper entry point to XML
    if (array_key_exists('dsa', $kwargs)) {
      $this->query['format'] = 'xml';
    }
    $schema = $this->get_key('schema');
    $bcp = $this->get_key('bcp');
    $limit = $this->get_limit();
    $offset = $this->get_offset();
    // Construct the query
    $this->cingo->from($this->collection);
    $this->cingo->group_start();
    $this->map_to_query($kwargs);
    $this->map_defaults($limit, $offset);
    $this->cingo->group_end();
    $records = (!$this->errors) ? $this->execute_query($callback, $params) : [];
    return (object) [
      'records' => $records,
      'query' => (object) $this->query,
      'errors' => $this->errors
    ];
  }


  /**
	 * Executes a database query based on a BioCASE Protocol request
	 *
   * @param array $filter a search filter parsed from the original request
   * @param int   $limit  the maximum number of records to return
   * @param int   $offset the index of the first record to return
   *
   * @return array a result object
	 *
	 * @access public
	 */
  public function search_from_request($filter, $limit, $offset, $count) {
    // Parse and validate query string params
    $offset = $this->get_offset($offset);
    $limit = $this->get_limit($limit);
    $this->count = strtolower($count) == 'true';
    // Construct and execute query
    $this->cingo->from($this->collection);
    $this->cingo->group_start();
    $this->recurse_filter($filter);
    $this->map_defaults($limit, $offset);
    $this->cingo->group_end();
    $records = (!$this->errors) ? $this->execute_query() : [];
    $this->query['format'] = 'xml';
    return (object) [
      'records' => $records,
      'query' => (object) $this->query,
      'errors' => $this->errors
    ];
  }


  /**
	 * Lists all records returned by the default search parameters
	 *
   * @param function $callback a function to apply to each returned row
   * @param array    $params   params to pass to the callback function
   *
   * @return array a result object
	 *
	 * @access public
	 */
  public function list_specimens($callback=NULL, $params=NULL) {
    // Parse and validate query string params
    $offset = $this->get_offset();
    $limit = $this->get_limit(1000, 1000);
    // Construct and execute query
		$this->cingo->from($this->collection);
    $this->map_defaults($limit, $offset);
    $records = (!$this->errors) ? $this->execute_query($callback, $params) : [];
    $this->query['kwargs'] = [];
    return (object) [
      'records' => $records,
      'query' => (object) $this->query,
      'errors' => $this->errors
    ];
  }


  private function modified_since($date) {
    $date = DateTime::createFromFormat('Ymd', $modified_since);
		$this->cingo->where('mdate', ['$gt' => new MongoDB\BSON\UTCDateTime($modified_since)]);
  }


  /**
	 * Parses and sanitizes the query string
	 *
   * @return array an associative array of keys and lists of values
	 *
	 * @access public
	 */
  private function parse_query_string() {
		$query_string = $_SERVER['QUERY_STRING'];
		$params = explode('&', $query_string);
		$kwargs = [];
		foreach ($params as $param) {
			$key_val = explode('=', $param, 2);
			if (count($key_val) == 2) {
				if ($key_val[1]) {
					// These values will be mapped onto the page served to the user,
					// so sanitize them here
					$key = $this->security->xss_clean($key_val[0]);
					$val = $this->security->xss_clean($key_val[1]);
					$key = (urldecode($key) !== urldecode($key_val[0])) ? '[removed]' : $key;
					$val = (urldecode($val) !== urldecode($key_val[1])) ? '[removed]' : $val;
					if ($key == '[removed]' || $val == '[removed]') {
						$this->errors[] = "Illegal parameter: $key=$val";
					}
					elseif (!array_key_exists($key, $this->query_params)
                  && !in_array($key, $this->ignore)) {
						$this->errors[] = 'Invalid parameter name: ' . $key;
					}
					elseif ($val) {
						$kwargs[$key][] = urldecode($val);
					}
				}
			}
			else {
				$key = $this->security->xss_clean($key_val[0]);
				if ($key != $key_val[0]) {
					$key = '[removed]';
				}
				$errors[] = 'Incorrectly fomatted parameter: ' . $key;
			}
		}
		return $kwargs;
  }


  /**
	 * Maps parameters from the query string to a database query
	 *
   * @param array kwargs an associative array of keys and lists of values
   *
   * @return void
	 *
	 * @access public
	 */
  private function map_to_query($kwargs) {
    foreach ($kwargs as $key => $vals) {
			switch ($key) {
        case 'dsa':
				case 'schema':
				case 'limit':
				case 'offset':
				case 'format':
				case 'bcp':
				case 'modified_since':
        case in_array($key, $this->ignore):
				  break;
				default:
					$db_keys = $this->query_params[$key]['keys'];
					if (is_string($db_keys)) {
						$db_keys = [$db_keys];
					}
					// Map values
					if (array_key_exists('mapping', $this->query_params[$key])) {
						$_vals = [];
						foreach ($vals as $i => $val) {
							if (array_key_exists(strtolower($val), $this->query_params[$key]['mapping'])) {
								$_vals[] = $this->query_params[$key]['mapping'][strtolower($val)];
							}
							else {
								$this->errors[] = 'Invalid parameter value: ' . $key . ' must be one of ' . oxford_comma($this->query_params[$key]['options'], 'or');
							}
						}
						$vals = $_vals;
					}
					if (count($vals) > 1 || count($db_keys) > 1) {
						$this->cingo->or_group_start();
					}
          $grouped = [];
					foreach ($vals as $val) {
						foreach ($db_keys as $db_key) {
							$stmt = $this->index_key($db_key, $val);
              if ($stmt) {
							  foreach ($stmt as $k => $v) {
                  $grouped[$k][] = $v;
							  }
              }
            }
					}
          foreach ($grouped as $k => $v) {
            if (count($v) > 1) {
              $this->cingo->where_in($k, $v);
            }
            else {
              $this->cingo->where($k, $v[0]);
            }
          }
					if (count($vals) > 1 || count($db_keys) > 1) {
						$this->cingo->group_end();
					}
			}
		}
  }


  /**
	 * Converts a BioCASE filter to a database query
	 *
   * @param mixed $item a value or container returned from the filter
   * @param array $path the full path to the current item
   *
   * @return void
	 *
	 * @access public
	 */
  function recurse_filter($item, $path=[]) {
    if (is_array($item)) {
      foreach ($item as $key => $val) {
        // Identify and handle conjunctions, which are handled differently
        // than other operators
        switch ((string) $key) {
          case 'and':
            $this->cingo->group_start();
            break;
          case 'or':
            $this->cingo->or_group_start();
            break;
          case 'not':
            $this->cingo->not_group_start();
            break;
          default:
            $path[] = $key;
        }
        $this->recurse_filter($val, $path);
        if (in_array($key, ['and', 'or', 'not'], TRUE)) {
          $this->cingo->group_end();
        }
        else {
          array_pop($path);
        }
      }
    }
    else {
      // Check the operator
      $i = -1;
      $schema_clean_key = array_get($path, $i);
      while (is_int($schema_clean_key)) {
        $i--;
        $schema_clean_key = array_get($path, $i);
      }
      $operator = array_get($path, $i - 1);
      $this->filter_key($schema_clean_key, $item, $operator);
    }
  }


  /**
	 * Maps a value from a BioCASE filter to a database query statement
	 *
   * @param string $key      a path from the schema being mapped
   * @param mixed  $val      the value to search for
   * @param string $operator an operator to use with the search
   *
   * @return void
	 *
	 * @access public
	 */
  function filter_key($key, $val, $operator) {
    if (!$key) {
      return;
    }
    if (!array_key_exists($key, $this->schema->conceptLookup)) {
      $this->errors[] = 'Unrecognized path: ' . $key;
    }
    // Errors may result in bad requests, so kill everything if an error occurs
    if ($this->errors) {
      return;
    }
    $keys = [];
    $filters = [];
    foreach ($this->schema->conceptLookup[$key] as $field) {
      if ($field->db_key) {
        $keys[] = $field->db_key;
      }
      elseif (strtolower($field->verbatim) == strtolower($val)) {
        // Does this filter match the given value?
        $filters[] = [$field->rec_filter, $field->data_filter];
      }
    }
    // Support for BioCASE queries on verbatim values is very limited, so
    // warn users when they hit one of those paths
    if (!$keys && !$filters && $operator == 'equals') {
      $this->errors[] = 'No records found';
      return;
    }
    if (!$keys && $operator != 'equals') {
      // FIXME: Hacky fix to allow the BioCASe consistency check to work
      if ($key != '/DataSets/DataSet/Metadata/Description/Representation/Title') {
        $this->errors[] = $operator . ' operator not supported for this path: ' . $key;
      }
      return;
    }
    // Map query for verbatim values
    if ($filters) {
      if (count($filters) > 1) {
        $this->cingo->or_group_start();
      }
      foreach ($filters as $filter) {
        $rec_filter = $filter[0];
        $data_filter = $filter[1];
        if ($rec_filter && $data_filter) {
          $this->cingo->group_start();
        }
        foreach ($rec_filter as $filter_key => $filter_val) {
          $this->cingo->where($filter_key, $filter_val, !is_null($filter_val));
        }
        if (count($data_filter) > 1) {
          $this->cingo->or_group_start();
        }
        foreach ($data_filter as $filter) {
          foreach ($filter as $filter_key => $filter_val) {
            $this->cingo->where($filter_key, $filter_val, !is_null($filter_val));
          }
        }
        if (count($data_filter) > 1) {
          $this->cingo->group_end();
        }
        if ($rec_filter && $data_filter) {
          $this->cingo->group_end();
        }
      }
      if (count($filters) > 1) {
        $this->cingo->group_end();
      }
    }
    // Map query for database lookups
    $query = [];
    $symbol = $this->operators[$operator];
    if (!$symbol) {
      foreach ($keys as $key) {
        $query[] = $this->index_key($key, $val);
      }
    }
    else {
      if (substr($operator, -4) == 'null') {
        $val = NULL;
      }
      foreach ($keys as $key) {
        $pattern = $this->cingo->create_pattern(strtolower($val));
        $query[] = [trim($key . ' ' . $symbol) => $pattern];
      }
    }
    // If multiple keys match, apply an or filter
    if ($query && count($keys) > 1) {
      $this->cingo->or_group_start();
    }
    foreach ($query as $stmt) {
      if ($stmt) {
        foreach ($stmt as $key => $val) {
          $this->cingo->where($key, $val, !is_null($val));
        }
      }
    }
    if ($query && count($keys) > 1) {
      $this->cingo->group_end();
    }
  }


  /**
	 * Sets and validates the limit parameter
	 *
   * @param int $limit the maximum number of records to return
   * @param int $max   the highest allowable limit
   *
   * @return int the value for limit
	 *
	 * @access public
	 */
  private function get_limit($limit=NULL, $max=1000) {
    $limit = (is_null($limit)) ? $this->input->get('limit') : $limit;
    // Return default if not specified
    if (is_null($limit)) {
      $limit = 10;
    }
    if ($limit > $max) {
      $limit = $max;
    }
    if (!is_numeric($limit) || (int) $limit <= 0 || (int) $limit > $max) {
			$this->errors[] = 'limit must be a postive integer <= ' . $max;
		}
    $limit = (int) $limit;
    $this->query['limit'] = $limit;
    return $limit;
  }


  /**
	 * Sets and validates the offset parameter
	 *
   * @param int $offset the index of the first record to return
   *
   * @return int the value for offset
	 *
	 * @access public
	 */
  private function get_offset($offset=NULL) {
    $offset = (is_null($offset)) ? $this->input->get('offset') : $offset;
    // Return default if not specified
    if (is_null($offset)) {
      $offset = 0;
    }
    if (!is_numeric($offset) || (int) $offset < 0) {
			$this->errors[] = 'offset must be an integer >= 0';
		}
    $offset = (int) $offset;
    $this->query['offset'] = $offset;
    return $offset;
  }


  /**
	 * Sets and validates parameters defined in $this->query_params
	 *
   * @param string $key the name of the parameter
   * @param string $val the value of the parameter
   *
   * @return string the value for this parameter
	 *
	 * @access public
	 */
  private function get_key($key, $val=NULL) {
    $val = (is_null($val)) ? $this->input->get($key) : $val;
    // Return default if not specified
    if (is_null($val)) {
      $val = $this->query_params[$key]['default'];
    }
    if (in_array(strtolower($val), $this->query_params[$key]['options'])) {
      $val = strtolower($val);
    }
    else {
      $options = oxford_comma($this->query_params[$key]['options'], 'or');
      $this->errors[] = 'Invalid parameter value: ' . $key . ' must be one of ' . $options;
      $val = $this->query_params[$key]['default'];
    }
    $this->query[$key] = $val;
    return $val;
  }


  /**
	 * Maps a key and value to a query statement, using indexes where possible
   *
   * Also checks for sample identifiers and manually defined query statements
   * defined in $config['magic'].
   *
   * FIXME: This funcition is HIGHLY SPECIFIC to Mineral Sciences and may be
   * difficult to expand.
	 *
   * @param string $key a database key
   * @param string $val the value for the key
   *
   * @return string the query statement as a string
	 *
	 * @access public
	 */
  public function index_key($key, $val) {
    // Check for special handling for a keyword
    if (array_key_exists(strtolower($val), $this->indexing->magic)) {
      return $this->indexing->magic[strtolower($val)];
    }
    // Check to see if defaults should be used for this query
    if (in_array($key, $this->ignore_defaults)) {
      $this->use_defaults = FALSE;
      // Unique integers are assumed to be integers
      if (is_numeric($val)) {
        return [$key => (int) $val];
      }
      return [$key => strtolower($val)];
    }
    // If key is not indexed, use a normal search instead
    $index = array_get($this->indexing->indexed, [$key]);
    if (!$index) {
      if (is_numeric($val)) {
        return [$key => (double) $val];
      }
      return [$key => $this->cingo->create_pattern($val)];
    }
    $indexed = [];
    $val = strtolower(trim($val, '*'));
    // Format value to match index
    if (property_exists($index, 'repl')) {
      $vals = [str_replace(array_keys($index->repl), array_values($index->repl), $val)];
    }
    else {
      $vals = preg_split('/[\s]+/', $val);
    }
    foreach ($vals as $val) {
      $val = trim($val, '.');
      // Check for catalog and meteorite numbers. If found, they are
      // returned as an array and can be distinguished from non-ids.
      if ($val == '0' || $val == '1' || strlen($val) >= 3) {
        $prefix = (property_exists($index, 'prefix')) ? $index->prefix : NULL;
        $indexed[] = ltrim($prefix . ':' . $val, ':');
      }
      else {
        $this->errors[] = 'Could not index "' . $val . '". Search terms must be three characters or longer.';
      }
    }
    if (count($indexed) == 1) {
      return [$index->name => $indexed[0]];
    }
    foreach ($indexed as $stmt) {
      $query['$and'][] = [$index->name => $stmt];
    }
    return (isset($query)) ? [$query] : NULL;
    #return [$index->name => ['$all' => $indexed]];
  }


  /**
	 * Retrieves and formats data for a key
   *
   * @param array  $rec     a sample record as an associative array
   * @param string $key     the database key to retrieve. May be a dot-delimited path.
   * @param mixed  $default the value to return if the key does not exist
   * @param array  $filter  an associative array of additional criteria used
   *                        to filter the values to return
   * @param string $mask    a formatting mask to apply to the value
   *
   * @return mixed a string or array containing the matching values
	 *
	 * @access public
	 */
  public function db_get($rec, $key, $default=NULL, $filter=NULL, $mask=NULL) {
    if ($filter) {
      #echo "Matched using filter\n";
      $val = $this->db_match_row($rec, $key, $filter);
    }
    elseif (strpos($key, '.') !== FALSE) {
      #echo "Matched array\n";
      $group = $this->db_get_group($key);
      $rows = array_get($rec, $group->group, []);
      if ($rows && !is_sequential($rows)) {
        $rows = [$rows];
      }
      $val = [];
      foreach ($rows as $row) {
        $v = array_get($row, $group->key);
        if ($v) {
          $val[] = $v;
        }
      }
    }
    else {
      #echo "Matched atomic: $key \n";
      $val = array_get($rec, $key, $default);
    }
    // Apply formats
    if (!is_array($val)) {
      $val = [$val];
    }
    $vals = [];
    foreach ($val as $i => $v) {
      $v = trim($v);
      if ($v) {
        if (!is_null($mask)) {
          $v = str_replace('{}', $v, $mask);
        }
        $vals[] = $v;
      }
    }
    return $vals ? $vals : $default;
  }


  /**
	 * Retrieves data from the rows in a grid matching the given filter
   *
   * @param array  $rec        a sample record as an associative array
   * @param string $db_key     the database key to retrieve. May be a dot-delimited path.
   * @param array  $db_filter  an associative array of additional criteria used
   *                           to filter the rows to return data from
   *
   * @return array a sequential containing the matching values
	 *
	 * @access public
	 */
  private function db_match_row($rec, $db_key, $db_filter) {
    $group = $this->db_get_group($db_key);
    $rows = array_get($rec, $group->group, []);
    $vals = [];
    foreach ($rows as $row) {
      $match = TRUE;
      foreach ($db_filter as $key => $val) {
        $match = array_get($row, $this->db_get_group($key)->key) == $val;
      }
      if ($match) {
        $vals[] = $row[$group->key];
      }
    }
    return $vals;
  }


  /**
	 * Splits the path to a group
   *
   * @param string $key a databse key specifying a group. May be a dot-delimited path.
   *
   * @return object key -> the path to a field within the group
   *                group -> the path to the group within the parent record
	 *
	 * @access public
	 */
  private function db_get_group($key) {
    $segs = explode('.', $key);
    $key = array_pop($segs);
    return (object) ['key' => $key, 'group' => implode('.', $segs)];
  }


  /**
	 * Gets the name of a sample
   *
   * @param array  $rec a sample record as an associative array
   *
   * @return string the name of the sample
	 *
	 * @access public
	 */
  public function get_name($rec) {
    show_error('Mongo_model.get_name() not defined');
	}


  /**
	 * Gets the preferred sample identifier
   *
   * @param array  $rec a sample record as an associative array
   *
   * @return string the sample identifier
	 *
	 * @access public
	 */
	public function get_identifier($rec) {
		show_error('Mongo_model.get_identifier() not defined');
	}


  private function map_defaults($limit, $offset) {
    if ($this->use_defaults) {
      foreach ($this->defaults as $key => $val) {
        $this->cingo->where($key, $val);
      }
      $this->cingo->limit($limit, $offset);
    }
    else {
      $this->cingo->limit(1, 0);
    }
  }

}

?>
