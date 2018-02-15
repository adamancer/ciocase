<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Formats and runs MongoDB queries using CodeIgniter QueryBuilder syntax
 *
 * Includes equivalents for all methods from CI Query Builder class, as well
 * as a handful of Mongo-specific methods. Methods that have not been
 * implemented return an error. Only select methods have been implemented
 * so far.
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


require_once('Cingo_query.php');


class Cingo extends Cingo_query
{

  public function __construct() {
    if (!class_exists('MongoDB\Driver\Manager')){
			show_error('The MongoDB PECL extension has not been installed or enabled', 500);
		}
    parent::__construct();
    $this->reset_query();
  }


  public function mongo_where($var, $val=NULL, $operator=NULL) {
    $statements = $this->_prep_select($var, $val, $operator);
    return $this->_filter_statements('$and', $statements);
  }


  public function mongo_or_where($var, $val=NULL, $operator=NULL) {
    $statements = $this->_prep_select($var, $val, $operator);
    return $this->_filter_statements('$or', $statements);
  }


  public function mongo_like($var, $val=NULL, $operator=NULL, $wildcard='both') {
    $statements = $this->_prep_select($var, $val, $operator, $wildcard);
    return $this->_filter_statements('$and', $statements);
  }


  public function mongo_or_like($var, $val=NULL, $operator=NULL, $wildcard='both') {
    $statements = $this->_prep_select($var, $val, $operator, $wildcard);
    return $this->_filter_statements('$or', $statements);
  }

}

?>
