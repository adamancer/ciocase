<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Defines methods for displaying database and verbatim values
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */

/**
 * Formatting mask for sensitive data that exists but should not be shown
 */
define('SENSITIVE_DATA', 'Data available upon request');


/**
 * Defines methods for displaying database and verbatim values
 *
 * @package   ciocase
 */
class Value {

	/**
	  * Whether this value was provided verbatim
	  *
	  * @var bool
	  */
	public $is_verbatim;


	/**
	  * Whether this value is a dot-delimited database path
	  *
	  * @var bool
	  */
	public $is_path;


	/**
	  * A database key or verbatim value
		*
		* Verbatim values are signified using [[double square brackets]]. All
		* other values are treated as database keys or paths.
	  *
	  * @var string
	  */
	public $val;


	/**
	  * A formatting mask to use when outputting the value
	  *
		* Use an empty set of curly braces to specify where the value should
		* go. (e.g., 'https://dx.doi.org/{}'). If no braces exist, the mask
		* will be returned instead of the value in the database. This can
		* be useful if you want to display a different value or if the data
		* is too sensitive to display.
		*
	  * @var string
	  */
	public $mask;


	/**
	  * The delimiter to use on output if multiple values are returned
	  *
	  * @var string
	  */
	public $delimiter;


	/**
	  * An associative arrays of attribute-value pairs
	  *
	  * @var array
	  */
	public $attrs;


	/**
	  * Constructs a new Value object
	  *
	  * @param mixed  $val       the value for the new object. May be a
		*                          database key or [[verbatim value]]
		* @param string $mask      a formatting mask to use on output
		* @param string $delimiter the delimiter to use on output if multiple
		*                          values are returned
		* @param array  $attrs     an associative array of attribute-value pairs
		* @param array  $criteria  an associative array of criteria for display
		*
		* @return void
		*
		* @access public
	  */
	public function __construct($val, $mask=NULL, $delimiter=NULL, $attrs=NULL, $criteria=NULL) {
		if (is_array($val)) {
			$this->is_verbatim = FALSE;
	    $this->is_path = FALSE;
			$this->val = array_map([$this, 'cleanVerbatim'], $val);
		}
		else {
			$this->is_verbatim = (is_string($val) && substr($val, 0, 2) == '[[');
	    $this->is_path = !$this->is_verbatim && strpos($val, '.') !== FALSE;
    	$this->val = $this->cleanVerbatim($val);
		}
		$this->mask = $mask;
		$this->delimiter = $delimiter;
		$this->attrs = (!is_null($attrs)) ? $attrs : [];
		$this->criteria = (!is_null($criteria)) ? $criteria : [];
	}



	/**
	  * Formats the Value object as a string
		*
		* @return string representing the value object
		*
		* @access public
	  */
  public function __toString() {
		if (is_array($this->val)) {
			return implode(' | ', $this->val);
		}
    return $this->val;
  }



	/**
	 * Creates a new Value object with identical properties but a new value
	 *
	 * @param mixed $val the value for the new object
   *
   * @return Value
	 *
	 * @access public
	 */
  public function copyNew($val) {
    return new Value($val, $this->mask, $this->delimiter, $this->attrs);
  }


	/**
	 * Tests whethers a value is verbatim
	 *
	 * @param mixed $val the value to test. If no value is provided,
	 *                   $this->val is used instead.
   *
   * @return bool specifying whether the given value is verbatim
	 *
	 * @access public
	 */
	public function isVerbatim($val=NULL) {
		if (is_null($val)) {
			$val = $this->val;
		}
	  return (is_string($val) && substr($val, 0, 2) == '[[');
	}


	/**
	 * Formats a verbatim value for display
	 *
	 * @param mixed $val the value to format. If no value is provided, the
	*                    $this->val is used instead.
   *
   * @return string trimmed of double brackets
	 *
	 * @access public
	 */
	private function cleanVerbatim($val) {
		if ($this->isVerbatim($val)) {
			return trim($val, '[]');
		}
		return $val;
	}


	/**
	 * Checks if
	 *
	 */
	public function checkPrintCriteria($rec) {
		if ($this->criteria) {
			foreach ($this->criteria as $key) {
				if (array_get($rec, $key)) {
					return TRUE;
				}
			}
			return FALSE;
		}
		return TRUE;
	}

}

?>
