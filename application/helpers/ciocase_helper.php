<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Defines helper functions used by the ciocase library
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


/**
 * Inserts data into a sequential array
 *
 * @param array $arr a sequential array
 * @param int   $i   the index at which to insert the data
 * @param mixed $val the data to insert
 *
 * @return array the merged array
 *
 * @access public
 */
if (!function_exists('array_insert')) {
	function array_insert($arr, $i, $val) {
		if (!is_array($val)) { $val = [$val]; }
		$before = $arr.slice(0, $i);
		$after = $arr.slice($i);
		$merged = array_merge($before, $val, $after);
		return $merged;
	}
}


/**
 * Inserts data into an array at the given path
 *
 * @param array $arr    an array
 * @param int   $path   a delimited path
 * @param mixed $val    the value to set
 * @param string $delim the delimiter used in the path
 *
 * @return array the updated array
 *
 * @access public
 */
if (!function_exists('array_set')) {
	function array_set($arr, $path, $val, $delim='.') {
		if (!is_array($path)) {
			$path = explode($delim, $path);
		}
		$aux = &$arr;
		foreach ($path as $key) {
			$append = FALSE;
			if (is_string($key) && substr($key, -1) == '+') {
				$key = substr($key, 0, -1);
				$append = TRUE;
			}
			elseif (is_int($key)) {
				$key = (int) $key;
			}
			if (!array_key_exists($key, $aux)) {
				$aux[$key] = [];
			}
			#elseif ($append) {
			#	$aux[$key][] = [];
			#	$aux = &$aux[$key][-1];
			#}
			$aux = &$aux[$key];
		}
		$aux[] = $val;
		return $arr;
	}
}


if (!function_exists('array_get')) {
	function array_get($arr, $path, $default=NULL, $delim='.') {
		if (is_int($path) && is_sequential($arr)) {
			$orig = $path;
			if ($path < 0) {
				$path = count($arr) + $path;
				#echo $orig . " => " . $path . ', length=' . count($arr) . "\n"; exit();
			}
			if ($path < count($arr)) {
				return $arr[$path];
			}
			else {
				return $default;
			}
		}
		if (!is_array($path)) {
			if (is_null($path)) {
				$path = [$path];
			}
			else {
				$path = explode($delim, $path);
			}
		}
		$result = $arr;
		foreach ($path as $key) {
			if (array_key_exists($key, $result)) {
				$result = $result[$key];
			}
			else {
				return $default;
			}
		}
		return $result;
	}
}


if (!function_exists('array_remove')) {
	function array_remove(&$arr, $val) {
		if (is_array($arr) && ($key = array_search($val, $arr)) !== FALSE) {
	    unset($arr[$key]);
		}
	}
}


if (!function_exists('is_associative')) {
	function is_associative($arr) {
	    if (!is_array($arr) || array() === $arr) {
				return FALSE;
			}
	    return array_keys($arr) !== range(0, count($arr) - 1);
	}
}


if (!function_exists('is_sequential')) {
	function is_sequential($arr) {
	    if (!is_array($arr) || array() === $arr) {
				return FALSE;
			}
	    return array_keys($arr) === range(0, count($arr) - 1);
	}
}


if (!function_exists('oxford_comma')) {
	function oxford_comma($arr, $conjunction='and', $delimiter=', ') {
		if (count($arr) == 1) {
			return $arr[0];
		}
		$last = array_pop($arr);
		$last_delim = (count($arr) == 1) ? ' ' : $delimiter;
		return rtrim(implode($delimiter, $arr)) . $last_delim . trim($conjunction) . ' ' . $last;
	}
}


if (!function_exists('startswith')) {
	function startswith($haystack, $needle) {
	  return substr($haystack, 0, strlen($needle)) == $needle;
	}
}


if (!function_exists('endswith')) {
	function endswith($haystack, $needle) {
	  return substr($haystack, -strlen($needle)) == $needle;
	}
}


if (!function_exists('get_common_prefix')) {
	function get_common_prefix($arr) {
		if (!$arr) {
			return '';
		}
		if (count($arr) == 1) {
			return $arr[0];
		}
		$common = array_pop($arr);
		while ($arr && $common) {
			$val = array_pop($arr);
			$temp = '';
			for ($i = 1; $i < strlen($val); $i++) {
				if (substr($common, 0, $i) == substr($val, 0, $i)) {
					$temp = substr($common, 0, $i);
				}
				else {
					break;
				}
			}
			$common = $temp;
		}
		return $common;
	}
}


/*if (!function_exists('array_match')) {
	function array_match($arr, $path, $match=NULL, $default=NULL, $delim='.') {
		$prefix = get_common_prefix([$path, array_keys($match)[0]]);
		$path = explode($delim, $prefix);
		$rows



		if (!is_array($path)) {
			if (is_null($path)) {
				$path = [$path];
			}
			else {
				$path = explode($delim, $path);
			}
		}
		$result = $arr;
		foreach ($path as $key) {
			if (array_key_exists($key, $result)) {
				$result = $result[$key];
			}
			elseif (is_sequential($result[$key])) {
				$rows = $result[$key];
			}
			else {
				return $default;
			}
		}
		return $result;
	}
}*/


?>
