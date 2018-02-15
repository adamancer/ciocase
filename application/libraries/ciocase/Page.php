<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Generates HTML to describe the records being displayed
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017 Smithsonian Institution
 * @license   MIT License
 */


 /**
  * Generates HTML to describe the records being displayed
  *
  * @package   ciocase
  */
class Page {

	/**
    * The namespaces available on the portal
    * @var array
    */
	public $namespaces;


	/**
	  * The current CodeIgniter instance
	  *
	  * @var CodeIgniter
	  */
	protected $CI;


	/**
	  * Constructs a new Page object
	  *
	  * @param string $page      the name of the page
		* @param object $queryinfo information about the query used to generate
		*                          the page
		*
		* @return void
		*
		* @access public
	  */
	public function __construct($page, $queryinfo=NULL) {
		$this->CI =& get_instance();
    $this->params = $this->CI->config->item('query_params');
		if ($queryinfo) {
			// Rebuild the query string for this page
			$this->page = $page;
			$this->query_string = [];
			foreach ($queryinfo->kwargs as $key => $vals) {
				if ($key != 'format') {
					foreach ($vals as $val) {
						if (strlen($val)) {
							$this->query_string[] = $key . '=' . $val;
						}
					}
				}
			}
			$this->offset = $queryinfo->offset;
			$this->limit = $queryinfo->limit;
			$this->count = $queryinfo->count;
			$this->total = $queryinfo->total;
		}
	}



	/**
	  * Sets a title for the page
	  *
	  * @param array $records a list of records
		*
		* @return string the title of the page
		*
		* @access public
	  */
	public function get_title($records=NULL) {
		return 'Search Results';
	}


	/**
	  * Describes the current page in the context of the full record set
	  *
	  * @param int    $offset the index of the first record to return
		* @param int    $limit  the maximum number of records to return
		* @param int    $count  the number of records displayed on the page
		* @param int    $total  the number of records matching the search
		* @param string $class  the name of a class to apply to the range tag
		*
		* @return string a brief description of the records being shown as HTML
		*
		* @access public
	  */
	public function get_range($offset=NULL, $limit=NULL, $count=NULL, $total=NULL, $class='clear') {
		$offset = (is_null($offset)) ? $this->offset : $offset;
		$count = (is_null($count)) ? $this->count : $count;
		$total = (is_null($total)) ? $this->total: $total;

		$o = number_format($offset + 1);
		$l = number_format($count + $offset);

		if (!$count) {
			return NULL;
		}
		if ($o == $l) {
			return "<p class=\"$class\">Showing record $o (1 total records)</p>";
		}
		elseif ($total == -1) {
			$t = number_format($offset + $count);
			return "<p class=\"$class\">Showing records $o - $l (>$l total records)</p>";
		}
		else {
			$t = number_format($total);
  		return "<p class=\"$class\">Showing records $o - $l ($t total records)</p>";
		}
	}



	/**
	  * Creates navigation bars linking to alternative formats and schemas
	  *
	  * @param string $key the name of the key
		* @param string $val the value corresponding to the key for this page.
		*                    Used to highlight the current page.
		* @param string $class  the name of a class to apply to the range tag
		*
		* @return string the navigation bar as HTML
		*
		* @access public
	  */
	public function get_navbars($key, $val=NULL, $class='bar') {
		$items = (!is_null($val)) ? [$key => $val] : $key;
		foreach ($items as $key => $val) {
			// Filter the current key from the param list
			$params = [];
			foreach ($this->query_string as $param) {
				if (substr($param, 0, strlen($key)) != $key) {
					$params[] = $param;
				}
			}
			// Construct the navigation bar
			$bar[] = '<ul class="' . $class . '">';
			if (array_key_exists($key, $this->params)) {
				foreach ($this->params[$key]['options'] as $option) {
					$params[] = $key . '=' . $option;
					$anchor = anchor(site_url('portal/' . $this->page . '?' . implode('&', $params)), $option);
					if ($option == $val) {
						$bar[] = '<li class="selected">' . $anchor . '</li>';
					}
					else {
						$bar[] = '<li>' . $anchor . '</li>';
					}
					array_pop($params);
				}
			}
			else {
				$anchor = anchor(site_url(['portal', $val]), $key);
				$bar[] = '<li>' . $anchor . '</li>';
			}
			$bar[] = '</ul>';
		}
		return implode('', $bar);
	}


	/**
	  * Creates a navigation bar with numbered pages
	  *
	  * @param int    $offset the index of the first record to return
		* @param int    $limit  the maximum number of records to return
		* @param int    $total  the number of records matching the search
		* @param string $class  the name of a class to apply to the range tag
		*
		* @return string the pagination bar as HTML
		*
		* @access public
	  */
	public function get_pages($offset=NULL, $limit=NULL, $total=NULL, $class='bar') {
		$offset = (is_null($offset)) ? $this->offset : $offset;
		$limit = (is_null($limit)) ? $this->limit : $limit;
		$total = (is_null($total)) ? $this->total : $total;

		if ($total == -1) {
			$total = $offset + $limit + 1;
		}

		$params = [];
		foreach ($this->query_string as $param) {
			if (!startswith($param, 'offset')) {
				$params[] = $param;
			}
		}

		$bar = ['<ul class="' . $class . '">'];
		if ($total > $limit) {
			$num_pages = ceil($total / $limit);
			$last = NULL;
			for ($i = 0; $i < $num_pages; $i++) {
				// Limit large result sets to first, last, and neighbors
				if ($num_pages < 5 || $i == 0 || abs($i * $limit - $offset) < $limit * 3 - 1  || $i == $num_pages - 1) {
					if (!is_null($last) && $i != $last + 1) {
						$bar[] = '<li>...</li>';
					}
					$params[] = 'offset=' . $i * $limit;
					$link = anchor(site_url('portal/' . $this->page . '?' . implode('&', $params)), number_format($i + 1));
					if ($limit == 1 && $offset == $i + 1 || $limit > 1 && floor($offset / $limit) * $limit == $i * $limit) {
						$bar[] = '<li class="selected">' . $link . '</li>';
					}
					else {
						$bar[] = '<li>' . $link . '</li>';
					}
					array_pop($params);
					$last = $i;
				}
			}
		}
		if ($total == $offset + $limit + 1) {
			$bar[] = '<li>...</li>';
		}
		$bar[] = '</ul>';
		return implode('', $bar);
	}


	/**
	  * Creates anchors defining a unique URL for an object
	  *
		* @param array $records a list of records
		*
		* @return array a list of the anchors as HTML
		*
		* @access public
	  */
	public function get_anchors($records) {
		$this->CI->load->model($this->CI->ciocase->backend);
		$anchors = [];
		foreach ($records as $rec) {
			$name = $this->CI->{$this->CI->ciocase->backend}->get_name($rec);
			$anchors[] = anchor(site_url('portal?guid=' . $rec['guid']), $name);
		}
		return $anchors;
	}


	/**
	  * Creates form inputs based on the available query parameters
		*
		* Query parameters are defined in the ciocase.php configuration file
		*
		* @return array a list of the inputs as HTML
		*
		* @access public
	  */
	public function get_inputs() {
		$inputs = [];
		foreach ($this->params as $key => $settings) {
			$definition = $settings['definition'];
			$pattern = '';
			if (array_key_exists('pattern', $settings)) {
				$pattern = 'pattern="' . $settings['pattern'] . '" ';
			}
			elseif (array_key_exists('options', $settings)) {
				$options = $this->prep_pattern(implode('|', $settings['options']));
				$pattern = "pattern=\"($options)\" ";
			}
			$inputs[] = "<div><label for=\"#$key\">$key</label><input name=\"$key\" id=\"$key\" $pattern/><span>$definition</span></div>";
		}
		return $inputs;
	}


	private function prep_pattern($val) {
		$chars = [];
		foreach (str_split($val) as $char) {
			if (ctype_alpha($char)) {
				$chars[] = '[' . strtoupper($char) . strtolower($char) . ']';
			}
			else {
				$chars[] = $char;
			}
		}
		return implode($chars);
	}


	/**
	  * Creates a list with the errors encountered processing a request
	  *
		* @param array $errors a list of errors
		*
		* @return string the errors as an HTML unordered list
		*
		* @access public
	  */
	public function get_errors($errors) {
		$list = ['The operation failed because of the following errors:<ul>'];
		foreach ($errors as $error) {
			if ($error && $error != 'null') {
				$list[] = '<li>' . $error . '</li>';
			}
		}
		$list[] = '</ul>';
		return implode('', $list);
	}

}

?>
