<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Defines a class to handle BioCASe concepts
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


class Concept {

	/**
    * The url of the schema containing this concept
    * @var string
    */
	public $schema;


	/**
    * The path in the schema
		*
	  * Segments with a trailing + will append a new node on write. The
		* default behavior is to overwrite the existing node.
		*
    * @var string
    */
	public $schema_key;


	/**
    * The path at which to find this concept in the schema
		*
	  * Equivalent to $this->schema_key with the append markers stripped out
		*
    * @var string
    */
	public $schema_clean_key;


	/**
    * The database key or keys associated with this concept
    * @var mixed
    */
	public $mappings;


	/**
    * Additional fields in the database that must be matched
    * @var array
    */
	public $db_filter;
	public $rec_filter;
	public $row_filter;


	/**
    * A formatting string used to search/write values for this concept
    * @var mixed
    */
	public $mask;
	public $mask_stub;


	/**
   * Constructs a new Concept
   *
   * @param string $schema        the url for the schema
	 * @param string $schema_key    the path to the field in the schema
	 * @param string $db_key        the path to the corresponding field in MongoDB
	 * @param array $db_filter      additional logic required to map schema to MongoDB
	 * @param array $criteria
   *
   * @return Concept
   */
	public function __construct($schema, $schema_key, $db_keys, $db_filter=NULL, $mask=NULL, $base_schema=NULL) {
		$this->schema = $schema;
		$this->schema_key = $schema_key;
		$this->schema_clean_key = $this->cleanKey($schema_key);
		$this->base_schema = (is_null($base_schema)) ? $schema : $base_schema;
		$this->mask = $mask;
		$this->atomic = TRUE;
		$this->orig_db_keys = $db_keys;  // needed for copyTo
		// Handle masks
		$this->mask_stub = NULL;
		if (!is_array($db_keys) && strpos($db_keys, '{') !== FALSE) {
			$this->mask = $db_keys;
			$matches = [];
			preg_match_all('/{([a-z_\.]+?)}/', $db_keys, $matches);
			$db_keys = [];
			foreach ($matches[1] as $match) {
				$db_keys[] = $match;
			}
			$blanks = array_fill(0, count($db_keys), NULL);
			$this->mask_stub = trim(str_replace($db_keys, $blanks, $this->mask), ' :-{}');
		}
		// Handle complex mappings
		if (!is_array($db_keys)) {
			$db_keys = [$db_keys];
		}
		$this->db_keys = $db_keys;
		$this->mappings = [];
		#$kinds = ['mapping' => 0, 'verbatim' => 0];
		foreach ($db_keys as $key => $val) {
			if (!is_a($val, 'Value')) {
				$val = new Value($val);
			}
			if (!is_int($key)) {
				$this->mappings[$key][] = $val;
			}
			else {
				$this->mappings[NULL][] = $val;
			}
			#$kinds[($this->isVerbatim($val)) ? 'verbatim' : 'mapping'] += 1;
		}
		# Split the filter object into record- and row-level filters
		$this->db_filter = $db_filter;
		$this->rec_filter = [];
		$this->row_filter = [];
		if (!is_null($db_filter)) {
			foreach ($db_filter as $key => $val) {
				if (strpos($key, '.') !== FALSE) {
					$this->row_filter[$key] = $val;
				}
				else {
					$this->rec_filter[$key] = $val;
				}
			}
		}
	}


	private function cleanKey() {
		$parts = explode('/', $this->schema_key);
		foreach ($parts as $i => $part) {
			if (strpos($part, '[') !== FALSE) {
				$part = explode('[', $part)[0];
			}
			$parts[$i] = trim($part, '+');
		}
		return implode('/', $parts);
	}


	public function isVerbatim($val) {
    return (is_string($val) && substr($val, 2) == '[[' && substr($val, -2) == ']]');
  }


	/**
	 * Maps complex concepts to the database
	 *
	 * @return array
	 */
	public function createLookups() {
		$mappings = [(object) ['kind' => 'concept', 'concept' => &$this]];
		foreach ($this->mappings as $key => $vals) {
			foreach ($vals as $val) {
				$verbatim = ($val->is_verbatim) ? $val->val : NULL;
				$db_key = (!$val->is_verbatim) ? $val->val : NULL;
				$item = ($val->is_verbatim) ? "[[$val->val]]" : $val;
				if (!is_null($val)) {
					$path = rtrim($this->schema_clean_key . '/' . $key, '/');
					// Construct a filter. How portable is the and/or stuff?
					$rec_filter = ($this->rec_filter) ? $this->rec_filter : [];
					$data_filter = [];
					foreach ($this->mappings as $key => $vals) {
						foreach ($vals as $val) {
							if (!$val->is_verbatim) {
								$data_filter[] = [$val->val . ' is not null' => NULL];
							}
						}
					}
					$mappings[] = (object) ['kind' => 'search',
					                        'path' => $path,
																	'db_key' => $db_key,
																	'verbatim' => $verbatim,
																	'rec_filter' => $rec_filter,
																	'data_filter' => $data_filter,
																	#'attrs' => $val->attrs,
																	#'mask' => $val->mask,
																  'concept' => &$this
																 ];
				}
			}
		}
		return $mappings;
	}


	/**
   * Copies this concept to another schema
   *
   * @param string $name          the name of the new schema
	 * @param string $replacements  search-replace to update field name
   *
   * @return Concept
   */
	public function copyTo($name, $replacements = NULL) {
		$schema_key = $this->schema_key;
		if ($replacements) {
			foreach ($replacements as $search => $replace) {
				$schema_key = preg_replace($search, $replace, $schema_key);
			}
		}
		return new Concept($name, $schema_key, $this->orig_db_keys,
		                   $this->db_filter, $this->mask, $this->base_schema);
	}

}

?>
