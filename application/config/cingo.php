<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Configuration parameters for the cingo library for CodeIgniter
 *
 * @package   cingo
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */

/**
  * A list of connection parameters required to connect to mongo
  *
  * Each concept corresponds to a data element defined in one of the schema
  * files. The path must appear in the associated concept mapping file.
  *
  * @var array
  */
$config['host'] = NULL;
$config['port'] = NULL;
$config['login_db'] = NULL;
$config['db'] = NULL;
$config['user'] = NULL;
$config['pass'] = NULL;


/**
 * The maximum time in milliseconds allowed to execute a database query
 *
 * @var integer
 */
$config['max_time_ms'] = 15000;


/**
  * The maximum time in milliseconds allowed to execute a count query
  *
  * Count can be a brutally inefficient operation in Mongo, so the default
  * for this field is low.
  *
  * @var integer
  */
$config['max_time_ms_count'] = 500;



/**
  * A list of the available indexes
  *
  * The following keys are available for each index definition:
  *   string name   the name of a database key
  *   string prefix the prefix applied to this field in the index, if any
  *   array  repl   an associative array mapping search-replace. Used to
  *                 format a string for an index.
  *
  * @var array
  */
$config['indexed'] = [
  # 'index' => (object) ['name' => 'field', 'prefix' => 'field']
];


/**
  * Maps terms to a representative database query
  *
  * Used to allow users to search for terms that are not represented
  * exactly in the database.
  *
  * @var array
  */
$config['magic'] = [
  # 'term' => ['field' => 'val']
];


/**
  * List of fields forbidden for distinct queries
  *
  * @var array
  */
$config['forbid_distinct'] = [];
