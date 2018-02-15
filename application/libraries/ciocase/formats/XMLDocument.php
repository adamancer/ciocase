<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Defines classes to write a BioCASe response as XML
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


class BaseNode extends DOMNode {


/**
 * Sets the value of the node
 *
 * @param mixed $value the value to set as a string, int, or float
 *
 * @return BaseNode this node
 *
 * @access public
 */
  public function setValue($value) {
    return setValue($this, $value);
  }


/**
 * Returns the qualifed name of the node
 *
 * @param string $name          the local name of a node
 * @param string $namespaceURI  the URI of the namespace of a node
 *
 * @return string the qualified name as prefix:localName
 *
 * @access public
 */
  public function getQualifiedName($name, $namespaceURI) {
    return getQualifiedName($this, $name, $namespaceURI);
  }


  public function getItems($name, $namespaceURI=NULL) {
    return getItems($this, $name, $namespaceURI);
  }


  public function setPath($path, $value, $namespaceURI=NULL) {
    return setPath($this, $path, $value, $namespaceURI);
  }


  public function setItem($name, $value=NULL, $namespaceURI=NULL) {
    return setItem($this, $name, $value, $namespaceURI);
  }


  public function setAttr($name, $value=NULL, $namespaceURI=NULL) {
    return setAttr($this, $name, $value, $namespaceURI);
  }

}


class BaseElement extends DOMElement {

  public function setValue($value) {
    return setValue($this, $value);
  }


  public function getQualifiedName($name, $namespaceURI) {
    return getQualifiedName($this, $name, $namespaceURI);
  }


  public function getItems($name, $namespaceURI=NULL) {
    return getItems($this, $name, $namespaceURI);
  }


  public function setPath($path, $value, $namespaceURI=NULL) {
    return setPath($this, $path, $value, $namespaceURI);
  }


  public function setItem($name, $value=NULL, $namespaceURI=NULL) {
    return setItem($this, $name, $value, $namespaceURI);
  }


  public function setAttr($name, $value=NULL, $namespaceURI=NULL) {
    return setAttr($this, $name, $value, $namespaceURI);
  }

}


class XMLDocument extends DOMDocument {

  public $xpath;

  public function __construct($version='1.0', $encoding='UTF-8') {
    parent::__construct($version, $encoding);
    $this->preserveWhiteSpace = FALSE;
    $this->formatOutput = FALSE;
    $this->registerNodeClass('DOMNode', 'BaseNode');
    $this->registerNodeClass('DOMElement', 'BaseElement');
    $this->schema = NULL;
    $this->delimiter = '/';
  }


  /**
   * Adds a schema definition to the document
   *
   * The schema definition is used to validate paths, assign prefixes to
   * qualified names, etc.
   *
   * @param Schema the schema definition to be used in this document
   *
   * @return void
   *
   * @access public
   */
  public function registerSchema($schema) {
    $this->schema = $schema;
    $this->delimiter = $schema->delimiter;
  }


  /**
   * Creates the root element of the document
   *
   * Also adds the specified namespace to the namespace lookup
   *
   * @param string $name the name of the root element
   * @param string $namespaceURI the URI of the namespace
   * @param string $namespacePrefix the prefix to use for this namespace
   *
   * @return void
   *
   * @access public
   */
  public function setRoot($name, $namespaceURI, $namespacePrefix) {
    $root = setChild($this, $namespacePrefix . ':' . $name, NULL, $namespaceURI);
    if ($namespacePrefix) {
      $this->setNamespace($namespaceURI, $namespacePrefix);
    }
    return $root;
  }

  /**
   * Adds a namespace to the document
   *
   * @param string $namespaceURI the URI of the namespace
   * @param string $namespacePrefix the prefix to use for this namespace
   *
   * @return void
   *
   * @access public
   */
  public function setNamespace($namespaceURI, $namespacePrefix) {
    $this->createAttributeNS($namespaceURI, $namespacePrefix . ':attr');
  }


  public function getQualifiedName($name, $namespaceURI) {
    return getQualifiedName($this, $name, $namespaceURI);
  }


  public function setItem($name, $value=NULL, $namespaceURI=NULL) {
    return setItem($this, $name, $value, $namespaceURI);
  }


  /**
   * Creates new or reuses
   *
   * @param string $path the path to be
   * @param mixed  $value the value to set the last element of the path
   * @param string $namespaceURI the default namespace URI. If a schema has
   *                             been registered, the namespace will be
   *                             determined by checking the schema definition.
   *
   * @return object
   *
   * @access public
   */
  public function setPath($path, $value, $namespaceURI=NULL) {
    return setPath($this, $path, $value, $namespaceURI);
  }


  public function getItems($name, $namespaceURI=NULL) {
    return getItems($this, $name, $namespaceURI);
  }


  public function setValue($value) {
    return setValue($this, $value);
  }


  public function setAttribute($name, $value, $namespaceURI=NULL) {
    $prefix = $this->lookupPrefix($namespaceURI);
    $qualifiedName = trim($this->lookupPrefix($namespaceURI) . ':' . $name, ':');
    $attr = $this->createAttribute($qualifiedName);
    $attr->value = $value;
    $this->documentElement->appendChild($attr);
  }


  public function setAttr($name, $value=NULL, $namespaceURI=NULL) {
    return setAttr($this, $name, $value, $namespaceURI);
  }


  /**
   * Recursively removes empty nodes from the document
   *
   * Slow for long documents
   *
   * @return void
   *
   * @access public
   */
  public function removeEmptyItems() {
    // Recursively removes empty nodes
    $xpath = new DOMXPath($this);
    foreach ($xpath->query('//*[not(*) and not(normalize-space()) and not(node())]') as $node) {
      while ($node && !$node->nodeValue && !$node->childNodes->length) {
        $parent = $node->parentNode;
        $parent->removeChild($node);
        $node = $parent;
      }
    }
  }


  public function asString() {
    $xml = new XMLDocument();
    // Brute-force trick to resolve incorrect XML formatting
    $xml->preserveWhiteSpace = FALSE;
    $xml->loadXML($this->saveXML());
    $xml->formatOutput = TRUE;
    return $xml->saveXML();
  }


  public function removeWrapper($response) {
    // Get namespaces from the original response document
    $doc = new XMLDocument();
    $node = $doc->importNode($response->content, TRUE);
    foreach ($node->childNodes as $child) {
      if ($child->nodeType == XML_ELEMENT_NODE) {
        $doc->appendChild($child);
        $xsi = 'http://www.w3.org/2001/XMLSchema-instance';
        $doc->setNamespace($xsi, 'xsi');
        if ($response->doc->schema->schema_locations) {
          $doc->setAttr('schemaLocation', $response->doc->schema->schema_locations, $xsi);
        }
        return $doc;
      }
    }
    // Create an empty root element if no records were found
    $schema = $response->doc->schema;
    $root = explode($schema->delimiter, $schema->rec_ids[0])[1];
    $doc->setItem($root);
    #$xsi = 'http://www.w3.org/2001/XMLSchema-instance';
    #$doc->setNamespace($xsi, 'xsi');
    #if ($schema->schema_locations) {
    #  $doc->setAttr('schemaLocation', $schema_locations, $xsi);
    #}
    return $doc;
  }


  public function find($path) {
    return $this->xpath->query($path);
  }


  public function findOne($path, $type=NULL) {
    $nodes = $this->find($path);
    if (count($nodes) == 1) {
      return (is_null($type)) ? $nodes[0] : settype($nodes[0], $type);
    }
  }

}




/**
 * Returns the qualifed name of the node
 *
 * @param XMLNode $node         a node including a namespace lookup
 * @param string  $name         the local name of a node
 * @param string  $namespaceURI the URI of the namespace of a node
 *
 * @return string the qualified name as prefix:localName
 *
 * @access public
 */
function getQualifiedName($node, $name, $namespaceURI) {
  return trim($node->lookupPrefix($namespaceURI) . ':' . $name, ':');
}


/**
 * Returns matching elements from the children of the node
 *
 * @param XMLNode $node         the node to search
 * @param string  $name         the local name to find. If "*", all elements
 *                              matching the given namespace will be returned.
 * @param string  $namespaceURI the URI of the namespace to find. If "*",
 *                              all elements matching the given local name
 *                              will be returned.
 *
 * @return DOMNodeList the list of matching elements
 *
 * @access public
 */
function getItems($node, $name, $namespaceURI=NULL) {
  if ($name == '*' || $namespaceURI == '*') {
    return $node->getElementsByTagNameNS($namespaceURI, $name);
  }
  elseif (!is_null($namespaceURI)) {
    $qualifiedName = getQualifiedName($node, $name, $namespaceURI);
    return $node->getElementsByTagName($qualifiedName);
  }
  else {
    return $node->getElementsByTagName($name);
  }
}


/**
 * Constructs the full path then creates and appends a child node
 *
 * @param XMLNode $node         the parent node
 * @param string  $path         the full path to the node
 * @param mixed   $value        the value to set for the node
 * @param string  $namespaceURI the URI of the namespace of the child node
 *
 * @return XMLNode the noce
 *
 * @access public
 */
function setItem($node, $path, $value=NULL, $namespaceURI=NULL) {
  $parent = setPath($node, $path, NULL, $namespaceURI);
  if (!$parent->last) {
    setValue($parent->parent, $value);
    return $parent->parent;
  }
  else {
    return setChild($parent->parent, $parent->last, $value, $namespaceURI);
  }
}


/**
 * Creates and appends a new child element under the given node
 *
 * @param XMLNode $node         the parent node
 * @param string  $name         the local name of the child node to be created
 * @param string  $value        the value to set for the child node
 * @param string  $namespaceURI the URI of the namespace of the child node
 *
 * @return XMLNode the newly created child node
 *
 * @access public
 */
function setChild($node, $name, $value=NULL, $namespaceURI=NULL) {
  // Only DOMDocument has the createElement method, so access that here
  $doc = (is_null($node->ownerDocument)) ? $node : $node->ownerDocument;
  if (!is_null($namespaceURI)) {
    $qualifiedName = getQualifiedName($node, $name, $namespaceURI);
    $item = $doc->createElementNS($namespaceURI, $qualifiedName);
  }
  else {
    $item = $doc->createElement($name);
  }
  // Set a value for the new element if the value is specified
  if (!is_null($value)) {
    $item->setValue($value);
  }
  // Append the new element
  $node->appendChild($item);
  return $item;
}


/**
 * Sets the value of a node
 *
 * @param XMLNode $node  the node
 * @param string  $value the value as a string, interger, or float
 *
 * @return XMLNode the node
 *
 * @access public
 */
function setValue($node, $value) {
  if (is_array($value)) {
    $value = implode(' | ', $value);
  }
  $value = htmlspecialchars($value);
  $node->nodeValue = $value;
  return $node;
}


/**
 * Constructs a path, reusing existing nodes where possible
 *
 * When checking for existing elements, this method ignores namespace.
 *
 * Use the plus sign (+) in the path to force a new node to be created. For
 * example, /path/to/element+ will create a new "element" node even if a node
 * with that name already exists under "to".
 *
 * If the last element on the path uses a plus sign...
 *
 * @param XMLNode $node         the parent node
 * @param string  $path         the path to be created
 * @param mixed   $value        the value for last element of the path. If
 *                              NULL, the last element will be left empty.
 * @param string  $namespaceURI the default namespace URI. If a schema has
 *                              been registered, the namespace of each node
 *                              along the path will be determined by checking
 *                              the schema definition.
 *
 * @return object parent -> the node specified by the path
 *                last -> the last element, if that element is repeatable
 *
 * @access public
 */
function setPath($node, $path, $value, $namespaceURI=NULL) {
  $doc = (is_null($node->ownerDocument)) ? $node : $node->ownerDocument;
  // Elements are typically set relative to a parent, but the full path is
  // sometimes needed to get information about the path from the schema
  // definition. If the full path is given, we need to split off the part
  // relative to the record.
  $rec_path = getRecordPath($node, $path);
  $pathToItem = [];
  if ($rec_path->rec_id) {
    $path = $rec_path->path;
    $pathToItem = [$rec_path->rec_id];
  }
  // If only the record-relative part of the path is given, we need to traverse
  // back up through the tree to reconstruct the full path to the element.
  elseif ($doc->schema) {
    $root = explode($doc->schema->delimiter, trim($doc->schema->rec_ids[0], $doc->schema->delimiter))[0];
    $parents = [$node->localName];
    $parent = $node->parentNode;
    while ($parent) {
      if ($parent) {
        array_unshift($parents, $parent->localName);
        if ($parent->localName == $root) { break; }
      }
      $parent = $parent->parentNode;
    }
    $pathToItem = ($parent && $parent->localName == $root) ? $parents : [];
  }
  $path = explode($doc->delimiter, trim($path, $doc->delimiter));
  $parent = $node;
  $last = NULL;
  foreach ($path as $key) {
    $keyInfo = getKeyInfo($node, $key, $pathToItem, $namespaceURI);
    $pathToItem[] = $keyInfo->key;
    if ($keyInfo->append && count($pathToItem) == count($path)) {
      $last = $keyInfo->key;
      break;
    }
    $items = getItems($parent, $keyInfo->key, '*');
    if ($items->length == 0 || $keyInfo->append) {
      $parent = setChild($parent, $keyInfo->key, NULL, $keyInfo->namespaceURI);
    }
    else {
      $parent = $items[count($items) - 1];
    }
    if ($keyInfo->attrs) {
      $parent->setAttr($keyInfo->attrs[0], $keyInfo->attrs[1]);
    }
  }
  return (object) ['parent' => $parent, 'last' => $last];
}


/**
 * Determines the path relative to the records unit from the full path
 *
 * Some standards (for example, Darwin Core) have multiple record units
 *
 * For example, /DataSets/DataSet/Units/Unit/UnitID would be split like so:
 *  rec_id -> /DataSets/DataSet/Units/Unit
 *  path -> UnitID
 *
 * @param XMLNode $node a node whose ownerDocument has a schema definition
 * @param string  $path the path relative to the document
 *
 * @return object rec_id -> the path to the record
 *                path -> the path relative to the record
 *
 * @access public
 */
function getRecordPath($node, $path) {
  $doc = (is_null($node->ownerDocument)) ? $node : $node->ownerDocument;
  if ($doc->schema) {
    foreach ($doc->schema->rec_ids as $rec_id) {
      if (startswith($path, $rec_id)) {
        #echo "$path => substr($path, strlen($rec_id))"; exit();
        $path = ltrim(substr($path, strlen($rec_id)), '/');
        return (object) ['rec_id' => $rec_id, 'path' => $path];
      }
    }
  }
  return (object) ['rec_id' => NULL, 'path' => $path];
}


/**
 * Gets context about a path required to place it in the document
 *
 * @param XMLNode $node         the parent node
 * @param string  $key          the name of the node being examined. If the
 *                              name ends with a plus sign (+), a new element
 *                              will be created even if an element with the
 *                              same name already exists.
 * @param array   $pathToItem   the document-relative path to the node examined
 * @param string  $namespaceURI the namespace URI of the document
 *
 * @return object key -> the clean name of the node
 *                append -> whether a new node should always be created
 *                namespaceURI -> the namespace URI for the exact path
 *                attrs -> attributes of the node from the schema definition
 *
 * @access public
 */
function getKeyInfo($node, $key, $pathToItem=NULL, $namespaceURI=NULL) {
  $doc = (is_null($node->ownerDocument)) ? $node : $node->ownerDocument;
  $append = substr($key, -1) == '+';
  $key = rtrim($key, '+');
  // Get attributes
  $attrs = [];
  if (strpos($key, '[') !== FALSE) {
    $parts = explode('[', $key);
    $key = $parts[0];
    $attrs = explode('=', trim($parts[1], '[@]'));
  }
  // Get namespace
  #echo implode('/', $pathToItem) . ' => ' . $namespaceURI . " (" . $doc->schema->base_schema . ")\n";
  if ($doc->schema && $namespaceURI != $doc->schema->base_schema) {
    $pathToItem[] = $key;
    $path = $doc->schema->delimiter . ltrim(implode($doc->schema->delimiter, $pathToItem), $doc->schema->delimiter);
    $item = array_get($doc->schema->cmf['elements'], $path);
    if ($item) {
      $namespaceURI = $item['namespace'];
    }
    #echo "$path: $namespaceURI\n";
  }
  return (object) ['key' => $key, 'append' => $append, 'namespaceURI' => $namespaceURI, 'attrs' => $attrs];
}


/**
 * Creates and sets the value of an attribute
 *
 * @param XMLNode $node         the node on which to set the attribute
 * @param string  $name         the local name of the attribute
 * @param mixed   $value        the value of the attribute
 * @param string  $namespaceURI the namespace URI for the attribute
 *
 * @return XMLNode the node modified with the new attribute
 *
 * @access public
 */
function setAttr($node, $name, $value=NULL, $namespaceURI=NULL) {
  $qualifiedName = getQualifiedName($node, $name, $namespaceURI);
  return $node->setAttribute($qualifiedName, $value);
}


/**
 * Validates an XML string against a schema
 *
 * @param string $xml_string the XML to validate
 * @param string $schema     the url of the schema file
 *
 * @return bool indicating whether the XML is valid
 *
 * @access public
 */
function validateXML($xml_string, $schema) {
  $xml = new XMLDocument();
  $xml->loadXML($xml_string);
  if (!$xml->schemaValidate($schema)) {
    show_error('Invalid XML: ' . str_replace(['<', '>'], ['&lt;', '&gt;'], $xml_string));
  }
}

?>
