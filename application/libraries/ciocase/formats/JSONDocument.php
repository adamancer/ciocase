<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Defines classes to write a BioCASe response as JSON
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


class JSONItemList extends ArrayObject {

  public function __construct(&$items) {
    parent::__construct([$items]);
    $this->length = count($items);
  }

}


class JSONItem {

  public function __construct(&$parent, &$item, $path) {
    $this->delimiter = '.';
    if (!$parent) {
      $this->doc = &$this;
    }
    else {
      $this->doc = &$parent;
      $this->schema = $this->doc->schema;
      $this->delimiter = $this->schema->delimiter;
    }
    $this->item = &$item;
    $this->path = str_replace('+', '', $path);  # $ this->path is an ARRAY
  }


  protected function getLocalName($name) {
    $segments = explode(':', $name);
    return array_pop($segments);
  }


  protected function getQualifiedName($name, $namespaceURI) {
    return $name;
    if (strpos($name, ':') !== FALSE) {
      return $name;
    }
    return trim($this->lookupPrefix[$namespaceURI] . ':' . $name, ':');
  }


  function getRecordPath($path) {
    if ($this->schema) {
      foreach ($this->schema->rec_ids as $rec_id) {
        if (startswith($path, $rec_id)) {
          #echo "$path => substr($path, strlen($rec_id))"; exit();
          $path = ltrim(substr($path, strlen($rec_id)), '/');
          return (object) ['rec_id' => $rec_id, 'path' => $path];
        }
      }
    }
    return (object) ['rec_id' => NULL, 'path' => $path];
  }


  public function getKeyInfo($key, $pathToItem, $namespaceURI) {
    $append = substr($key, -1) == '+';
    $key = rtrim($key, '+');
    // Get attributes
    if (strpos($key, '[') !== FALSE) {
      $parts = explode('[', $key);
      $key = $parts[0];
    }
    $atomic = TRUE;
    // Get namespace and cardinality
    #if ($this->schema && $namespaceURI != $this->schema->base_schema) {
    if ($this->schema) {
      $merged = array_merge($this->path, $pathToItem, [$key]);
      // Get rid of BioCASE-specific bits
      $merged = array_slice($merged, 2);
      $path = '/' . trim(implode($this->schema->delimiter, $merged), '/');
      #echo '================' . "\n";
      #print_r($this->path);
      #print_r($pathToItem);
      #echo $path . "\n";
      $item = $this->schema->getAttr($path);
      if ($item) {
        $namespaceURI = $item['namespace'];
        $atomic = $item['max'] != -1;
      }
    }
    $key = $this->getQualifiedName($key, $namespaceURI);
    return (object) ['key' => $key, 'append' => $append, 'atomic' => $atomic];
  }


  public function getItem($path, $namespaceURI=NULL, $default=NULL) {
    if (!is_array($path)) {
      $path = explode($this->delimiter, $path);
    }
    $item = &$this->item;
    foreach ($path as $key) {
      if ($namespaceURI == '*') {
        $keys = array_keys($item);
        $localNames = array_map([$this, 'getLocalName'], $keys);
        $i = array_search($key, $localNames);
        if ($i) {
          $key = $localNames[$i];
        }
      }
      if (array_key_exists($key, $item)) {
        $item = &$item[$key];
      }
      else {
        return $default;
      }
    }
    return new JSONItem($this->doc, $item, array_merge($this->path, $path));
  }


  public function getItems($path, $namespaceURI=NULL) {
    $item = $this->getItem($path, $namespaceURI=NULL, $default=NULL);
    return new JSONItemList($item);
  }


  public function setItem($path, $value, $namespaceURI=NULL) {
    if (!$path) {
      $this->item = $value;
      return new JSONItem($this->doc, $this->item, $this->path);
    }
    if (is_array($path)) {
      $path = implode($this->delimiter, $path);
    }
    // TODO: This would benefit from making sure the way that paths are given is consistent
    $rec_path = $this->getRecordPath($path);
    $path = explode($this->schema->delimiter, $rec_path->path);
    $pathToItem = explode($this->schema->delimiter, $rec_path->rec_id);
    #$pathToItem = [];
    $item = &$this->item;
    foreach ($path as $key) {
      $keyInfo = $this->getKeyInfo($key, $pathToItem, $namespaceURI);
      #print_r($keyInfo);
      $key = $keyInfo->key;
      if (!$key) {
        #print_r($path);;
        #print_r($keyInfo);
        continue;
      }
      #print_r($item);
      #if ($key == 'NamedArea') { print_r($this->doc->root); }
      $p = implode('/', array_merge($this->path, $pathToItem));
      if (is_null($item)) {
        #echo "item is null: $p\n";
        $item = [];
      }
      $pathToItem[] = $key;
      if (is_sequential($item)) {
        #echo "parent is sequential: $key\n";
        if ($keyInfo->append) {
          $item[] = [];
        }
        $item = &$item[count($item) - 1];
        if (array_key_exists($key, $item)) {
          $item = &$item[$key];
        }
        else {
          $item[$key] = [];
          $item = &$item[$key];
        }
      }
      elseif ($keyInfo->append) {
        $item[] = [$key => []];
        #$n = count($item);
        #echo "append: $key (n=$n)\n";
        $item = &$item[count($item) - 1][$key];
      }
      elseif (!$keyInfo->atomic) {
        #echo "create list: $key ($p)\n";
        if ($item) {
            #print_r($item);
            $item[$key] = [];
            $item = &$item[$key];
        }
        else {
          $item = [[$key => []]];
          $item = &$item[0][$key];
        }
      }
      elseif (!array_key_exists($key, $item)) {
        #echo "create dict: $key\n";
        $item[$key] = [];
        $item = &$item[$key];
      }
      else {
        #echo "use existing: $key\n";
        $item = &$item[$key];
        // Remove the last segment to prevent duplication
        array_pop($pathToItem);
      }
      #if ($key == 'NamedArea') { print_r($this->doc->root); }
    }
    #$item = $value;
    $json_item = new JSONItem($this->doc, $item, array_merge($this->path, $path));
    if (!is_null($value)) {
      $json_item->setValue($value);
      #print_r($json_item->doc->root);
    }
    return $json_item;
  }


  public function setPath($path, $namespaceURI=NULL) {
    #echo "full path: $path\n";
    if (!is_array($path)) {
      $path = explode($this->delimiter, $path);
    }
    #$last = (substr($path, -1) == '+') ? rtrim(array_pop($segments), '+') : NULL;
    $last = array_pop($path);
    if (substr($last, -1) != '+') {
      $path[] = $last;
      $last = NULL;
    }
    $item = $this->setItem($path, NULL, $namespaceURI);
    return (object) ['parent' => &$item, 'last' => $last];
  }


  public function setValue($value) {
    if (is_object($value)) {
      $value = $value->val;
    }
    if (is_array($value)) {
      $value = implode(' | ', $value);
    }
    while (strpos($value, '  ') !== FALSE) {
      $value = str_replace('  ', ' ', $value);
    }
    $this->item = $value;
  }


  public function setAttribute($name, $value, $namespaceURI=NULL) {
    return setAttr($name, $value, $namespaceURI);
  }


  public function setAttr($name, $value, $namespaceURI=NULL) {
    if (!array_key_exists('attributes', $this->doc->root)) {
      $this->doc->root['attributes'] = [];
    }
    $this->doc->root['attributes'][$name] = $value;
  }


  public function removeEmptyItems(&$arr=NULL, $parents=NULL, $path=NULL) {
    if (is_null($arr) && is_null($parents) & is_null($path)) {
      $arr = &$this->item;
      $parents = [];
      $path = [];
    }
    if (is_array($arr) && $arr) {
      foreach ($arr as $key => &$child) {
        $parents[] = &$arr;
        $path[] = $key;
        $p = implode('/', $path);
        #echo "Digging into $p...\n";
        $this->removeEmptyItems($child, $parents, $path);
        array_pop($parents);
        array_pop($path);
      }
    }
    else {
      for ($i = count($parents) - 1; $i >= 0; $i--) {
        $parent = &$parents[$i];
        $sequential = is_sequential($parent);
        $changed = FALSE;
        foreach ($parent as $key => $val) {
          $p = implode('/', $path);
          #echo "Checking $key in $p...\n";
          if (!$val) {
            #echo "Unsetting $key in $p...\n";
            unset($parent[$key]);
            $changed = TRUE;
          }
        }
        // Reindex lists if elements were deleted
        if ($sequential && $changed) {
          $parent = array_values($parent);
        }
        array_pop($path);
        #$arr = &$parent;
      }
    }
  }


  public function asString() {
    return json_encode($this->item, JSON_PRETTY_PRINT);
  }

}


class JSONDocument extends JSONItem {

  public function __construct() {
    $item = [];
    parent::__construct($item, $item, []);
    $this->registerItemClass('JSONItem');
    $this->namespaces = [];
    $this->schema = NULL;
  }


  protected function registerItemClass($itemClass) {
    $this->itemClass = $itemClass;
  }


  public function registerSchema($schema) {
    $this->schema = $schema;
    $this->delimiter = $schema->delimiter;
  }


  public function setRoot($name, $namespaceURI, $namespacePrefix) {
    $this->item[$name] = [];
    $this->root = &$this->item[$name];
    # This code namespaces the JSON, which may not be a great idea
    #$this->arr[$namespacePrefix . ':' . $name] = [];
    #$this->root = &$this->arr[$namespacePrefix . ':' . $name];
    #$this->setNamespace(NULL, NULL);
    $this->setNamespace($namespaceURI, $namespacePrefix);
    return new $this->itemClass($this, $this->root, [$name]);
  }


  public function setNamespace($namespaceURI, $namespacePrefix) {
    $this->lookupNamespace[$namespacePrefix] = $namespaceURI;
    $this->lookupPrefix[$namespaceURI] = $namespacePrefix;
    $this->namespaces[$namespacePrefix] = $namespaceURI;
  }


  public function removeWrapper($response) {
    $doc = new JSONDocument();
    $content = $response->doc->item['response']['content'];
    foreach ($content as $key => $val) {
      $doc->item[$key] = $val;
      $doc->root = &$doc->item;
      return $doc;
    }
    return $doc;
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


?>
