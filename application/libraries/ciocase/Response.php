<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Defines a class to create BioCASe responses 
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


require_once('Request.php');
require_once('formats/HTMLDocument.php');
require_once('formats/JSONDocument.php');
require_once('formats/XMLDocument.php');


class Response {

  protected $CI;
  public $response;
  public $content;
  public $diagnostics;
  public $xmlns;
  public $protocolPrefix;
  public $responsePrefix;
  public $schema;


  public function __construct($format, $schema=NULL, $unwrap=FALSE) {
    // Identify the backend
    $this->CI =& get_instance();
    $this->CI->config->load('ciocase');
    $this->backend = $this->CI->config->item('backend');
    $this->mark = $this->CI->timer->mark('libraries/ciocase/Response.php');

    switch ($format) {
      case 'html':
      case 'mongo':
        $this->doc = new HTMLDocument();
        break;
      case 'json':
        $this->doc = new JSONDocument();
        break;
      case 'xml':
        $this->doc = new XMLDocument('1.0', 'UTF-8');
        break;
      default:
        $format = $this->CI->security->xss_clean($format);
        show_error("Invalid format: $format");
    }

    $this->schema = $schema;
    $this->protocolPrefix = 'biocase';
    $this->xmlns = 'http://www.biocase.org/schemas/protocol/1.3';
  }


  public function populateFromRecords($records, $queryinfo) {
    $this->request = new Request;
    $this->request->fromQuery($queryinfo->format,
                              $queryinfo->limit,
                              $queryinfo->offset);
    $this->populateResponse($records);
    $this->content->setAttr('recordCount', count($records));
    $this->content->setAttr('recordDropped', 0);
    $this->content->setAttr('recordStart', $queryinfo->offset);
    // FIXME: Improve calculation of totals
    #$total = ($queryinfo->total == -1) ? count($records) + 1 : $queryinfo->total;
    $this->content->setAttr('totalSearchHits', $queryinfo->total);
    $this->header->setItem('count', $queryinfo->total, $this->xmlns);
  }


  public function populateFromRequest($request) {
    // Determine namespaces based on the content of the request. Searches
    // differentiate between the namespace of the protocol and the response.
    $this->request = new Request;
    @ $this->request->fromXML($request);  # parsing errors handled in populateResponse()
    if (!$this->request->errors) {
      // Get schema based on request/responseFormat
      foreach (['responseFormat', 'requestFormat'] as $key) {
        if (array_key_exists($key, $this->request->params)) {
          $this->schema = $this->CI->ciocase->schemas[$this->request->params[$key]];
          break;
        }
      }
    }
    $this->populateResponse();
  }


  private function populateResponse($records=NULL) {
    $xsi = 'http://www.w3.org/2001/XMLSchema-instance';
    $schemaLocation = 'http://www.biocase.org/schemas/protocol/1.3 http://www.bgbm.org/biodivinf/schema/protocol_1_3.xsd';
    if ($this->request->type == 'search' && !$this->request->errors) {
      $this->doc->registerSchema($this->schema);
      #$this->response = $this->doc->setItem($this->protocolPrefix . ':response', NULL, $this->xmlns);
      $this->response = $this->doc->setRoot('response', $this->xmlns, $this->protocolPrefix);
      $this->doc->setNamespace($this->xmlns, $this->protocolPrefix);
      // Get the namespaces as defined in the schema definition
      $namespaces = $this->schema->namespaces;
      foreach ($namespaces as $url => $prefix) {
        $this->doc->setNamespace($url, $prefix);
      }
    }
    else {
      $this->response = $this->doc->setRoot('response', $this->xmlns, NULL);
      $this->doc->setNamespace($this->xmlns, 'xmlns');
    }
    $this->doc->setNamespace($xsi, 'xsi');
    $this->doc->setAttr('schemaLocation', $schemaLocation, $xsi);
    // Create major subelements
    $this->header = $this->createHeaderFromRequest();
    $this->content = $this->response->setItem('content', NULL, $this->xmlns);
    $this->diagnostics = $this->response->setItem('diagnostics', NULL, $this->xmlns);
    // Check for errors parsing the request file
    if (!$this->request->params) {
      $this->createDiagnostic('Could not parse request', 'ERROR');
      return;
    }
    // Check for errors parsing the search filter
    if ($this->request->errors) {
      foreach ($this->request->errors as $error) {
        $this->createDiagnostic($error);
      }
      return;
    }
    #if ($this->request->params['count'] == 'true') {
    #  $this->createDiagnostic('WARNING: count not supported');
    #  return;
    #}
    // Check for errors specific to each request type then execute the request
    switch ($this->request->type) {
        case 'capabilities':
          $this->writeCapabilities();
          break;
        case 'scan':
          $this->writeScan();
          break;
        case 'search':
          $this->writeSearch($records);
          break;
    }
  }


  private function createHeaderFromRequest() {
    $header = $this->response->setItem('header', NULL, $this->xmlns);
		$software = [
			#'PHP Version' => PHP_VERSION,
			'ciocase' => '0.2',
			#'Mongo Version' => '2.0'
		];
		foreach ($software as $key => $val) {
			$node = $header->setItem('version', $val, $this->xmlns);
			$node->setAttr('software', $key);
		}
		$header->setItem('sendTime', gmdate('Y-m-d\TH:i:sO'), $this->xmlns);
		$header->setItem('source', $_SERVER['SERVER_ADDR'], $this->xmlns);
		$header->setItem('destination', NULL, $this->xmlns);
		$header->setItem('type', $this->request->params['type'], $this->xmlns);
    return $header;
  }


  public function writeCapabilities() {
    $capabilities = $this->content->setItem('capabilities');
    foreach ($this->CI->ciocase->schemas as $namespace => $schema) {
      // Split into request and response and response-only
      $request_and_response = [];
      $response_only = [];
      foreach ($schema->conceptLookup as $conceptPath => $fields) {
        foreach ($fields as $field) {
          if ($field->verbatim) {
            $response_only[] = $conceptPath;
          }
          else {
            $request_and_response[] = $conceptPath;
          }
        }
			}
      if ($request_and_response) {
        $request_and_response = array_unique($request_and_response);
        #sort($request_and_response);
			  $elem = $capabilities->setItem('supportedSchemas');
			  $elem->setAttr('request', 'true');
			  $elem->setAttr('namespace', $namespace);
			  $elem->setAttr('response', 'true');
			  foreach ($request_and_response as $conceptPath) {
          $child = $elem->setItem('Concept', $conceptPath);
          $child->setAttr('searchable', '1');
			  }
      }
      if ($response_only) {
        $response_only = array_unique($response_only);
        #sort($response_only);
			  $elem = $capabilities->setItem('supportedSchemas');
			  $elem->setAttr('request', 'false');
			  $elem->setAttr('namespace', $namespace);
			  $elem->setAttr('response', 'true');
			  foreach ($response_only as $conceptPath) {
          $elem->setItem('Concept', $conceptPath);
			  }
      }
		}
    // Function completed successfully
    $this->createDiagnostic('Request completed successfully');
  }


  public function writeScan() {
    $filter = $this->request->params['filter'];
    $responseFormat = $this->request->params['requestFormat'];
    $conceptPath = $this->request->params['concept'];
    // Check for errors
    $schema = $this->schema;
    if (is_null($schema)) {
			$this->createDiagnostic('Unrecognized responseFormat: ' . $responseFormat, 'ERROR');
			return;
		}
		$scan = $this->content->setItem('scan');
    $keys = [];
    $vals = [];
    foreach ($schema->conceptLookup as $schema_clean_key => $fields) {
      if ($schema_clean_key == $conceptPath) {
        foreach ($fields as $field) {
          // Add database keys to the list of keys to check
          if ($field->db_key) {
            $keys[] = $field->db_key;
          }
          // Add verbatim values directly to the value list
          else {
            $vals[] = $field->verbatim;
          }
        }
      }
    }
    if ($keys) {
      $this->CI->load->model($this->backend);
      $this->CI->{$this->backend}->add_schema($this->schema);
      $result = $this->CI->{$this->backend}->scan($keys, $filter);
      $vals = array_merge($vals, $result->vals);
      if ($result->errors) {
        $this->createDiagnostic($result->errors[0] . $conceptPath);
      }
      if ($result->query->compiled) {
        foreach ($result->query->compiled as $compiled) {
          $this->createDiagnostic('Compiled search: ' . json_encode($compiled->query), 'DEBUG');
        }
      }
    }
    $vals = array_unique($vals);
    sort($vals);
    foreach ($vals as $val) {
      $scan->setItem('value', htmlspecialchars($val));
    }
    // Add content
		$this->content->setAttr('recordDropped', 0);
		$this->content->setAttr('recordStart', 0);
		$this->content->setAttr('recordCount', count($vals));
    $this->header->setItem('count', count($vals));
    if (!$vals) {
      $this->createDiagnostic('No values found: ' . $conceptPath);
    }
    else {
      // Function completed successfully
      $this->createDiagnostic('Request completed successfully');
    }
  }


  public function writeSearch($records) {
    $filter = $this->request->params['filter'];
    $responseFormat = $this->request->params['responseFormat'];
    #if (strtoupper($this->request->params['count']) != 'FALSE') {
    #  $this->createDiagnostic('Count not implemented', 'WARNING');
    #}
    // Check for errors
    $schema = $this->schema;
    if (is_null($schema)) {
			$this->createDiagnostic('Unrecognized responseFormat: ' . $responseFormat, 'ERROR');
			return;
		}
    // Check for warnings
    #if ($this->request->params['limit'] < 0 || $this->request->params['limit'] > 1000) {
    #  $this->createDiagnostic('Limit must be a positive integer <= 100', 'ERROR');
    #  return;
    #}
    if ($this->request->params['start'] < 0) {
      $this->createDiagnostic('Offset must be a positive integer', 'ERROR');
      return;
    }
    if (is_null($records)) {
      $this->createDiagnostic('No records provided, so building query from scratch');
      $this->CI->load->model($this->backend);
      $this->CI->{$this->backend}->add_schema($this->schema);
      $results = $this->CI->{$this->backend}->search_from_request($filter, $this->request->params['limit'], $this->request->params['start'], $this->request->params['count']);
      if ($results->errors) {
        foreach ($results->errors as $error) {
          $this->createDiagnostic($error, 'ERROR');
        }
        return;
      }
      if ($results->query->compiled) {
        $this->createDiagnostic('Compiled search: ' . json_encode($results->query->compiled->query), 'DEBUG');
      }
      $records = $results->records;
      $this->content->setAttr('recordCount', count($records));
      $this->content->setAttr('recordDropped', 0);
      $this->content->setAttr('recordStart', $this->request->params['start']);
      // FIXME: Improve calculation of totals
      #$total = ($results->query->total == -1) ? count($records) + 1 : $results->query->total;
      $this->content->setAttr('totalSearchHits', $results->query->total);
      $this->header->setItem('count', $results->query->total, $this->xmlns);
    }
    if ($records) {
      if (!$schema->rec_ids) {
        $this->createDiagnostic('Error reading schema!');
      }
      // Add record path
      foreach ($schema->rec_ids as $rec_id) {
        $this->createDiagnostic('Record path is ' . $rec_id);
      }
      $this->schema->mapMetadata([], $this->content);
      // Create unit container, then add units
      $segments = explode($schema->delimiter, trim($schema->rec_ids[0], $schema->delimiter));
      array_pop($segments);
      $path = implode($schema->delimiter, $segments);
      $parent = $this->content->setPath($path, NULL, $schema->base_schema);
      $this->mark->start();
      foreach ($records as $rec) {
        $this->schema->mapRecord($rec, $parent->parent);
      }
      $n = count($records);
      $kind = get_class($this->doc);
      $this->mark->log("Mapped $n records to $kind");
      $this->createDiagnostic('Request completed successfully');
    }
    else {
      $this->createDiagnostic('No records returned');
    }
  }


  public function createDiagnostic($msg, $level='INFO') {
    $elem = $this->diagnostics->setItem('diagnostic+', $msg, $this->xmlns);
    $elem->setAttr('severity', $level);
  }

}

?>
