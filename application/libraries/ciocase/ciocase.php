<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Retrieves and formats natural history data following the BioCASE protocol
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


require_once('Concept.php');
require_once('Page.php');
require_once('Response.php');
require_once('Schema.php');
require_once('Value.php');


/**
 * Retrieves and formats natural history data following the BioCASE protocol
 *
 * @package   ciocase
 */
class Ciocase {

  /**
	  * The current CodeIgniter instance
	  *
	  * @var CodeIgniter
	  */
  protected $CI;


  /**
	  * The name of the current backend
	  *
	  * @var string
	  */
  public $backend;


  /**
	  * A sequential array of all concepts defined for the endpoint
	  *
	  * @var array
	  */
  public $concepts;


  /**
	  * An associative array of all schemas defined for the endpoint
	  *
	  * @var array
	  */
  public $schemas;


  /**
	  * An associative array of all namespaces and namespace prefixes
	  *
	  * @var array
	  */
  public $namespaces;


  /**
	  * Constructs a new Ciocase object
	  *
	  * The concepts, schemas, and namespaces properties are all based on
    * data in /application/config/ciocase.php.
		*
		* @return void
		*
		* @access public
	  */
  public function __construct() {
    $this->CI =& get_instance();
    $this->CI->load->helper('ciocase');
    $this->CI->config->load('ciocase');

    $this->CI->load->library('timer/Timer');
    $this->mark = $this->CI->timer->mark('libraries/ciocase/Ciocase.php');

    $this->mark->start();
    $this->concepts = $this->read_concepts();
    $this->schemas = $this->read_schemas();
    $this->namespaces = $this->read_namespaces();
    $this->mark->log('Read concept mappings');

    $this->backend = $this->CI->config->item('backend');
  }


  /**
	  * Formats a BioCASE response from a result object
	  *
	  * @param object $result the result of a database search. Must include
    *                       records, query, and error properties.
		*
		* @return mixed a Response object constructed using the result object
		*
		* @access public
	  */
  public function from_result($result) {
    $schema = $this->schemas[$this->namespaces[$result->query->schema]];
    $response = new Response($result->query->format, $schema);
    $response->populateFromRecords($result->records, $result->query);
    return $response;
  }


  /**
	  * Formats a BioCASE response from a BioCASE request document
	  *
	  * @param string $request a BioCASE request as XML
		*
		* @return mixed a Response object constructed based one the request
		*
		* @access public
	  */
  public function from_request($request) {
    $response = new Response('xml');
    $response->populateFromRequest($request);
    return $response;
  }


  /**
	  * Creates a new Page object
	  *
	  * @param string $page   the name of the page
    * @param array  $kwargs the query parameters used in the initial search
		*
		* @return Page
		*
		* @access public
	  */
  public function make_page($page, $kwargs=[]) {
    return new Page($page, $kwargs);
  }


  /**
	  * Reads and organizes concept definitions based on the settings file
		*
		* @return array a sequential array of concept mappings
		*
		* @access private
	  */
  private function read_concepts() {
    $bequests = $this->CI->config->item('bequests');
    $concepts = $this->CI->config->item('concepts');
    $inherited = [];
    foreach ($concepts as $concept) {
    	if (array_key_exists($concept->schema, $bequests)) {
        $beq = $bequests[$concept->schema];
        $inherited[] = $concept->copyTo($beq->url, $beq->replacements);
    	}
    }
    $concepts = array_merge($concepts, $inherited);
    return $concepts;
  }


  /**
	  * Reads and organizes schema objects based on the settings file
		*
		* @return array an associative array mapping urls to schemas
		*
		* @access private
	  */
  private function read_schemas() {
    foreach ($this->CI->config->item('concept_mapping_files') as $cmf) {
      $schema = new Schema($cmf, $this->concepts);
    	$schemas[$schema->url] = $schema;
    }
    return $schemas;
  }


  /**
	  * Maps namespaces to namespace prefixes defined in the schemas
		*
		* @return array an associative array mapping prefixes to namespaces
		*
		* @access private
	  */
  private function read_namespaces() {
    $namespaces = [];
    foreach ($this->schemas as $schema) {
      foreach ($schema->namespaces as $url => $prefix) {
        $namespaces[$prefix] = $url;
      }
    }
    return $namespaces;
  }




}

?>
