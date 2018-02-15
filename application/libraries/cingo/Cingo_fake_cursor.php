<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Creates a cursor-like object to use for document expansions
 *
 * Used to create documents from the unique values returned by a distinct
 * query, for example.
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


class Cingo_fake_cursor implements Iterator
{

  private $cursor;


  public function __construct($cursor) {
		$this->cursor = $cursor;
	}


  public function setTypeMap($map) {
    // Convert root
    if (array_key_exists('root', $map)) {
      switch ($map['root']) {
        case 'array':
          $this->cursor = $this->toArray($this->cursor);
          break;
        case 'object':
          $this->cursor = $this->toObject($this->cursor);
          break;
        default:
          throw new Exception('Illegal type: "' . $map['root'] . '"');
      }
    }
    // Convert documents (parent is root)
    if (array_key_exists('document', $map)) {
      switch ($map['root']) {
        case 'array':
          foreach ($this->cursor as &$doc) {
            $doc = $this->toArray($doc);
          }
          break;
        case 'object':
          foreach ($this->cursor as &$doc) {
            $doc = $this->toObject($doc);
          }
          break;
        default:
          throw new Exception('Illegal type: "' . $map['root'] . '"');
      }
    }
    // Arrays are NOT converted
    if (array_key_exists('array', $map)) {
      throw new Exception('Illegal key: "array"');
    }
  }

  private function toArray($obj) {
    if (!is_array($obj)) {
      return (array) $obj;
    }
    return $obj;
  }

  private function toObject ($obj) {
    if (!is_object($obj)) {
      return (object) $obj;
    }
    return $obj;
  }

  function rewind() {
    reset($this->cursor);
  }

  function current() {
    return current($this->cursor);
  }

  function key() {
    return key($this->cursor);
  }

  function next() {
    return next($this->cursor);
  }

  function valid() {
    $key = key($this->cursor);
    return ($key !== NULL && $key !== FALSE);
  }

}
?>
