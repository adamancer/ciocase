<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Defines a class to handle schemas supported by this application
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */

class Schema {

	public $CI;
	public $rec_ids;
	public $metadata_id;
	public $delimiter;
	public $search_concepts;
	public $write_concepts;
	public $metadata;
	public $base_schema;


	public function __construct($fp, &$concepts) {
		# Originally this was configured to use file_get_contents
		#$f = file_get_contents($fp);
		$segments = explode('/', $fp);
		$fn = str_replace('.json', '.php', array_pop($segments));
		@ include "files/$fn";
		if (ENVIRONMENT == 'development' && !isset($cmf)) {
			echo "New file generated: $fn<br />";
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, 'http:' . $fp);
			curl_setopt($curl, CURLOPT_HEADER, 0);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			$f = curl_exec($curl);
			curl_close($curl);
			$cmf = json_decode($f, TRUE);
			// Write to file
			file_put_contents(dirname(__FILE__) . "/files/$fn", '<?php $cmf = ' . var_export($cmf, TRUE) . '?>', FILE_USE_INCLUDE_PATH);
		}
		// The CMF property includes relevant data from the concept mapping,
		// including an associative array of all fields that appear in the
		// application. The keys in this array include the full path, including
		// the leading slash.
		$this->cmf = $cmf;
		$this->name = $cmf['name'];
		$this->url = $cmf['schema'];
		$this->rec_ids = $cmf['rec_ids'];
		$this->namespaces = $cmf['namespaces'];
		$this->base_schema = array_keys($this->namespaces)[0];
		$this->schema_locations = $cmf['schema_locations'];
		$this->delimiter = '/';
		// Create lookup arrays for concepts
		$unsortedMetadata = [];
		$unsortedConcepts = [];
		$conceptLookup = [];
		foreach ($concepts as $concept) {
			if ($concept->schema == $this->url
			    && array_key_exists($concept->schema_clean_key, $cmf['elements'])) {
				// Update concept with metadata attributes
				#$concept->updateAttributes($cmf['elements'][$concept->schema_clean_key]);
				// Identify unit/record concepts
				$record_concept = FALSE;
				foreach ($this->rec_ids as $rec_id) {
					if (substr($concept->schema_clean_key, 0, strlen($rec_id)) === $rec_id) {
						$record_concept = TRUE;
						break;
					}
				}
				$lookups = $concept->createLookups();
				foreach ($lookups as $lookup) {
					if ($record_concept && $lookup->kind == 'concept') {
						$unsortedConcepts[$lookup->concept->schema_clean_key][] = $lookup->concept;
					}
					elseif ($lookup->kind == 'concept') {
						$unsortedMetadata[$lookup->concept->schema_clean_key][] = $lookup->concept;
					}
					else {
						$conceptLookup[$lookup->path][] = $lookup;
					}
				}
			}
		}
		// Add record-relative paths to lookup
		$this->conceptLookup = $conceptLookup;
		// Sort the concept list based on the schema file
		$this->scan = [];
		$this->metadata = [];
		$this->conceptList = [];
		foreach ($this->cmf['elements'] as $path => $elem) {
			if (array_key_exists($path, $unsortedMetadata)) {
				foreach($unsortedMetadata[$path] as $val) {
					$this->metadata[] = $val;
				}
				// Store verbatim values about the dataset so they can be queried on
				foreach ($val->mappings as $key => $val) {
					$this->scan["$path/$key"][] = $val[0]->val;
				}
			}
			elseif (array_key_exists($path, $unsortedConcepts)) {
				foreach($unsortedConcepts[$path] as $val) {
					$this->conceptList[] = $val;
				}
				#echo "$key\n";
			}
		}
		// Load the database backend for use in this class
		$this->CI =& get_instance();
    $this->backend = $this->CI->config->item('backend');
		$this->CI->load->model($this->backend);
	}


	public function getAttr($path) {
		$path = str_replace('+', '', $path);
		$root = explode($this->delimiter, $this->rec_ids[0])[1];
		if ($path && $root) {
			$key = '/' . substr($path, strpos($path, $root));
			#echo "\nRoot: $root\n";
			#echo "Key: $key\n\n";
			if (array_key_exists($key, $this->cmf['elements'])) {
				return $this->cmf['elements'][$key];
			}
		}
	}


	public function mapMetadata($rec, $recordset) {
    foreach ($this->metadata as $concept) {
      $this->mapConcept($rec, $concept, $recordset);
    }
  }


  public function mapRecord($rec, $parent) {
    # Create an associative array mapping containers to paths
    $containers = [];
    foreach ($this->rec_ids as $rec_id) {
      $container_id = array_pop((explode($this->delimiter, $rec_id)));
      #$container = $parent->setPath($container_id . '+', NULL, $this->base_schema);
      #$container = $container->parent->setItem($container->last, NULL, $this->base_schema);
      $namespaceURI = $this->getAttr($rec_id)['namespace'];
      $container = $parent->setItem($container_id . '+', NULL, $namespaceURI);
      $containers[$rec_id] = $container;
    }
    # Limit the keys to a minimum length to simplify lookups
    $lengths = [];
    foreach (array_keys($containers) as $key) {
      $lengths[] = strlen($key);
    }
    $len = min($lengths);
    $lookup = [];
    foreach ($containers as $key => $val) {
      $lookup[substr($key, 0, $len)] = (object) ['container' => $val, 'rec_id' => $key];
    }
    foreach ($this->conceptList as $concept) {
      # Match each concept to the proper container
      $match = $lookup[substr($concept->schema_clean_key, 0, $len)];
      #echo $concept->schema_clean_key . '<br />';
      #echo $match->rec_id . '<br />';
      $this->mapConcept($rec, $concept, $match->container, $match->rec_id);
    }
  }


  public function mapConcept($rec, $concept, $container, $prefix=NULL) {
    // Construct the path to the concept
    $rec_path = trim(substr($concept->schema_key, strlen($prefix)), $this->delimiter);
    $rec_path = $concept->schema_key;
    // Process the record-level filter
    foreach ($concept->rec_filter as $key => $val) {
      if (array_get($rec, $key) != $val) {
        return $container;
      }
    }
    // Handles sequential lists of database keys/verbatim values
    if (array_key_exists(NULL, $concept->mappings)) {
      $vals = [];
			$mask = $concept->mask;
      foreach ($concept->mappings[NULL] as $i => $db_key) {
        if (!$db_key->is_verbatim) {
          $val = $this->CI->{$this->backend}->db_get($rec, $db_key->val, NULL, $concept->row_filter, $db_key->mask);
					// Do not print if required fields not populated
					if ($val && !$db_key->checkPrintCriteria($rec)) {
						$val = NULL;
					}
        }
        else {
          $val = $db_key->val;
        }
				if ($mask) {
					$val = ($val && is_array($val)) ? $val[0] : '';
					$mask = trim(str_replace('{' . $db_key . '}', $val, $mask), ':- ');
					$vals = ($mask != $concept->mask_stub) ? [$mask] : NULL;
				}
        elseif ($val) {
          if (is_array($val)) {
						$vals = array_merge($vals, $val);
          }
          else {
            $vals[] = $val;
          }
        }
      }
      if ($vals) {
        $parent = $container->setPath($rec_path, NULL, $concept->base_schema);
        $last = ($parent->last) ? $parent->last . '+' : NULL;
        $parent = $parent->parent;
        if (!$last) {
          $parent->setValue(implode(' | ', $vals));
        }
        else {
          foreach ($vals as $val) {
            $parent->setItem($last, $val, $concept->base_schema);
          }
        }
      }
    }
    // Handle associative arrays mapping keys to fields
    else {
      #echo $concept->schema_key . "\n  Associative, unknown\n";
      $mappings = [];
      // Test if database keys are part of a group
      $db_group = NULL;
      if (is_associative($concept->mappings)) {
        $arr = [];
        foreach ($concept->mappings as $schema_key => $db_item) {
          foreach ($db_item as $k => $v) {
            if ($v->is_path) {
              $arr[] = $v;
            }
          }
        }
        if ($arr) {
          if (count($arr) == 1) {
            $parts = explode('.', $arr[0]);
            array_pop($parts);
          }
          else {
            $db_group = trim(get_common_prefix($arr, '.'), '.');
            $parts = explode('.', $db_group);
            $last_part = array_pop($parts);
            if (strlen($last_part) == 5) {
              $parts[] = $last_part;
            }
          }
          $db_group = implode('.', $parts);
        }
      }
      // Get the group, if it exists
      $rows = (!is_null($db_group)) ? array_get($rec, $db_group, []) : [$rec];
      $dataFound = $this->allVerbatim($concept->mappings);
      foreach ($rows as $row) {
        // Confirm that this row contains data
        // FIXME: This is terrible
        foreach ($concept->mappings as $schema_key => $db_item) {
          foreach ($db_item as $k => $v) {
            if (!$v->is_verbatim) {
              $key = $v->val;
              if (!is_null($db_group)) {
                $key = trim(substr($key, strlen($db_group)), '.');
              }
              $val = $this->CI->{$this->backend}->db_get($row, $key, NULL, $concept->row_filter, $v->mask);
              if ($val) {
                $dataFound = TRUE;
                break;
              }
            }
          }
          if ($dataFound) {
            break;
          }
        }
        foreach ($concept->mappings as $schema_key => $db_item) {
          // Populate the data
          foreach ($db_item as $k => $v) {
            $node = NULL;
            if (!$v->is_verbatim) {
              $key = $v->val;
              if (!is_null($db_group)) {
                $key = trim(substr($key, strlen($db_group)), '.');
              }
              $val = $this->CI->{$this->backend}->db_get($row, $key, NULL, $concept->row_filter, $v->mask);
              if ($val) {
                $root = (isset($parentage)) ? $parentage->root : NULL;
                $parentage = $this->getParentage($container, $root, $concept, $rec_path, $schema_key);
                $val = $v->copyNew($val);
                if ($parentage->schema_key) {
                  $node = $parentage->parent->setItem($parentage->schema_key, $val, $concept->base_schema);
                }
                else {
                  $node = $parentage->parent->setValue($val);
                }
                #$dataFound = TRUE;
              }
            }
            elseif ($dataFound) {
              $root = (isset($parentage)) ? $parentage->root : NULL;
              $parentage = $this->getParentage($container, $root, $concept, $rec_path, $schema_key);
              if ($parentage->schema_key) {
                $node = $parentage->parent->setItem($parentage->schema_key, $v->val, $concept->base_schema);
              }
              else {
                $node = $parentage->parent->setValue($v->val);
              }
            }
            // Add attributes
            if (!is_null($node) && $v->attrs) {
              foreach ($v->attrs as $key => $val) {
                $node->setAttr($key, $val);
                #$node->setAttr($key, $val, $concept->base_schema);
              }
            }
          }
        }
        # Reset the grouping
        unset($parentage);
      }
    }
    return $container;
  }


	private function getParentage($container, $root, $concept, $rec_path, $schema_key) {
    if (is_null($root)) {
      $root = $container->setPath($rec_path, NULL, $concept->base_schema);
      $root = ($root->last) ? $root->parent->setItem($root->last . '+', NULL, $concept->base_schema) : $root->parent;
    }
    // Handle deep paths in associative arrays
    $parent = $root;
    if (strpos($schema_key, $this->delimiter) !== FALSE) {
      $parent = $parent->setPath($schema_key, NULL, $concept->base_schema);
      $schema_key = ($parent->last) ? $parent->last . '+' : NULL;
      $parent = $parent->parent;
    }
    return (object) ['root' => $root, 'parent' => $parent, 'schema_key' => $schema_key];
  }


	private function isVerbatimCallback($item, $key, $result) {
    if (!$item->is_verbatim) {
      $result->result = FALSE;
    }
  }


  private function allVerbatim($arr) {
    $result = (object) ['result' => TRUE];
    array_walk_recursive($arr, [$this, 'isVerbatimCallback'], $result);
    return $result->result;
  }

}

?>
