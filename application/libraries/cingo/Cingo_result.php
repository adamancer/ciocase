<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Reproduces public methods of CodeIgniter Result object for MongoDB cursor
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


class Cingo_result
{

  protected $cursor;
  protected $_num_rows;


  public function __construct($cursor, $num_rows=0) {
		$this->cursor	= $cursor;
    // Counting all matches can be prohibitively slow for large collections,
    // so by default the count query is kept on a pretty short leash. If the
    // count fails but the cursor contains records, $this->num_rows() return
    // -1 so that result sets with no count can be distinguished but still
    // evaluate as TRUE.
    if (is_null($num_rows)) {
      $num_rows = (count($this->result())) ? -1 : 0;
      $this->result_array();
    }
    $this->_num_rows = $num_rows;
	}


  public function result() {
    $this->cursor->setTypeMap(array('root' => 'object', 'document' => 'array'));
    return $this->cursor;
  }


  public function result_array() {
    $this->cursor->setTypeMap(array('root' => 'array', 'document' => 'array'));
    return $this->cursor;
  }


  public function row() {
    foreach ($this->cursor as $row) {
      return (object) $row;
    }
    trigger_error('Cannot retrieve row from empty cursor', E_USER_WARNING);
  }


  public function row_array() {
    foreach ($this->cursor as $row) {
      return (array) $row;
    }
    trigger_error('Cannot retrieve row from empty cursor', E_USER_WARNING);
  }


  public function unbuffered_row() {
    trigger_error('The unbuffered_row() method has not been implemented in Cingo', E_USER_ERROR);
  }


  public function num_rows() {
    return $this->_num_rows;
  }


  public function pprint($obj) {
    echo '<pre>'; print_r($obj); echo '</pre>';
  }

  private function to_object($arr) {
    return (object) $arr;
  }

}
?>
