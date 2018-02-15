<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Defines a class to handle BioCASe requests
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */
 

require_once('formats/XMLDocument.php');


class Request extends XMLDocument {

  protected $CI;


  /**
    * An associative array of the parameters defined in the request
    * @var array
    */
  public $params;

  /**
    * A sequential array of errors encountered while parasing the documents
    *
    * If errors are present, the response will log them in the diagnostics
    * node and stop processing the request
    *
    * @var array
    */
  public $errors;


  /**
    * A sequential array of valid node names for use a search filter
    *
    * @var array
    */
  private $filterNodes;


  public function __construct($version='1.0', $encoding='UTF-8') {
    // Create the XML document
    parent::__construct($version, $encoding);
    $this->preserveWhiteSpace = FALSE;
    $this->formatOutput = TRUE;

    $this->CI =& get_instance();

    $this->filterNodes = [
      'equals',
      'notEquals',
      'lessThan',
      'lessThanOrEquals',
      'greaterThan',
      'greaterThanOrEquals',
      'like',
      'isNull',
      'isNotNull',
      'in',
      'not',
      'and',
      'or',
      'filter'
    ];
    $this->errors = [];
  }


  public function fromQuery($responseFormat, $limit=100, $offset=0) {
    $this->type = 'search';
    $this->params = [
      'responseFormat' => $responseFormat,
      'limit' => $limit,
      'start' => $offset,
      'count' => 'false',
      'filter' => NULL,
      'type' => 'search'
    ];
  }


  public function fromXML($request) {
    #$this->mark->note('Parsing request using Request->fromXML()');
    $this->type = 'capabilities';
    if ($request) {
      $this->loadXML($request);
      // Validate post request against schema
      $valid = $this->schemaValidate('http://www.bgbm.org/biodivinf/Schema/protocol_1_31.xsd');
      if (!$valid) {
        $this->errors[] = 'Could not validate request';
      }
      $this->xpath = new DOMXPath($this);
      $this->xpath->registerNamespace('x', 'http://www.biocase.org/schemas/protocol/1.3');
      // Determine and parse the parameters for the given request type
      $this->type = $this->findOne('/x:request/x:header/x:type')->nodeValue;
    }
    switch ($this->type) {
        case 'capabilities':
          $this->params = $this->parseCapabilities();
          break;
        case 'scan':
          $this->params = $this->parseScan();
          break;
        case 'search':
          $this->params = $this->parseSearch();
          break;
        default:
          $this->errors[] = 'Could not parse request';
    }
    $this->validateFormats();
    #$this->mark->log('Parsed XML request');
  }


  /**
	 * Parses a BioCASE capabilities request
	 *
	 * @return array
	 */
  private function parseCapabilities() {
    $params = [
      'type' => 'capabilities'
    ];
    return $params;
  }


  /**
	 * Parses a BioCASE scan request
	 *
	 * @return array
	 */
  private function parseScan() {
    $params = [
      'concept' => trim($this->findOne('/x:request/x:scan/x:concept')->nodeValue),
      'filter' => $this->parseFilter($this->findOne('/x:request/x:scan/x:filter')),
      'requestFormat' => trim($this->findOne('/x:request/x:scan/x:requestFormat')->nodeValue),
      'type' => 'scan'
    ];
    return $params;
  }


  /**
	 * Parses a BioCASE search request
	 *
	 * @return array
	 */
  private function parseSearch() {
    $params = [
      'count' => $this->findOne('/x:request/x:search/x:count')->nodeValue,
      'filter' => $this->parseFilter($this->findOne('/x:request/x:search/x:filter')),
      'requestFormat' => trim($this->findOne('/x:request/x:search/x:requestFormat')->nodeValue),
      'type' => 'search'
    ];
    $responseFormat = $this->findOne('/x:request/x:search/x:responseFormat');
    foreach ($responseFormat->attributes as $attr) {
      $params[$attr->nodeName] = (int) trim($attr->nodeValue);
    }
    $params['responseFormat'] = trim($responseFormat->nodeValue);
    return $params;
  }


  /**
	 * Parses the filter parameter from a BioCASE search request
	 *
	 * @return array
	 */
  public function parseFilter($filter) {
    return $this->recurseFilter($filter)['filter'];
  }


  /**
	 * Recurses through the filter parameter from a BioCASE search request
	 *
   * @param DOMNode $node the current node in the document
   * @param array $path   the path to the current node
   * @param array $tree   the tree constructed thus far
   *
	 * @return array
	 */
  private function recurseFilter($node, $path=[], $tree=[]) {
    // If node names are invalid, add an error
    if (!in_array($node->nodeName, $this->filterNodes)) {
      $this->errors[] = 'Unrecognized node name: ' . $node->nodeName;
    }
  	$children = $node->childNodes;
    $path[] = $node->nodeName;
    $schema_clean_key = $node->attributes['path'];
    if ($schema_clean_key) {
      $schema_clean_key = str_replace(' ', '', $schema_clean_key->nodeValue);
      $path[] = $schema_clean_key . '+';
      // Values provided via XML are never mirrored on the page served to
      // the user, so it is not necessary to sanitize them
      $tree = array_set($tree, $path, $node->nodeValue);
    }
    else {
      $children = $node->childNodes;
      if (count($children)) {
    		foreach ($children as $child) {
    			$tree = $this->recurseFilter($child, $path, $tree);
        }
      }
    }
    return $tree;
  }


  private function validateFormats() {
    foreach (['requestFormat', 'responseFormat'] as $key) {
      if (array_key_exists($key, $this->params)) {
        $format = $this->params[$key];
        if (!array_key_exists($format, $this->CI->ciocase->schemas)) {
          $this->errors[] = 'Unrecognized ' . $key . ': '. $format;
        }
      }
    }
  }

}

?>
