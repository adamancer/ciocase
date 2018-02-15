<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Container allowing echoing and re-use of compiled MongoDB searches
 *
 * @package   cingo
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


class Cingo_compiled
{

  private $query;

  public function __construct($query) {
		$this->query = $query;
	}

  public function __get($name) {
    return $this->query;
  }

  function __toString() {
    return '<pre>' . print_r($this->query, TRUE) . '</pre>';
  }

}
?>
