<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Defines methods to parse and respond to requests made through the portal
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


 /**
  * Defines methods to parse and respond to requests made through the portal
  *
  * @package   ciocase
  */
class Portal extends CI_Controller {


	/**
	  * An associate array of the available query parameters
	  *
	  * @var array
	  */
	private $params;


	/**
	 * Displays the default page
	 *
	 * @access public
	 */
	public function index() {
		$this->route_request();
	}


	/**
	 * Displays the phpinfo page (development environment only)
	 *
	 * @access public
	 */
	public function info() {
		echo phpinfo();
	}


	/**
	 * Displays a list of available datasets
	 *
	 * @access public
	 */
	public function datasets() {
		$data = [
			'title' => 'Available Datasets',
			'anchors' => [anchor(site_url('portal?dsa=nmnh_geology_collections_data'), 'nmnh_geology_collections_data')],
			'pages' => NULL
		];

		$this->load->view('portal/header', $data);
		$this->load->view('portal/datasets', $data);
		$this->load->view('portal/footer');
	}


	/**
	 * Routes a request based on whether it contains GET or POST data
	 *
	 * @access public
	 */
	private function route_request() {
		// Check POST for BioCASe
		$request = $this->input->post('query');
		if ($request) {
			// Sometimes the BioCASe monitor splits out the request and filter
			// params, so check for filter
			$filter = $this->input->post('filter');
			if (!$filter) {
				$filter = $this->input->get('filter');
			}
			// Requests sent out by the BioCASe monitor service do not comply
			// with the BioCASe protocol schema, so rewrite them here
			$request = $this->build_request($request, $filter);
			return $this->from_biocase($request);
		}
		// Check GET for BioCASe monitor params
		$filter = $this->input->get('filter');
		if ($filter) {
			$request = $this->build_request(NULL, $filter);
			return $this->from_biocase($request);
		}
		// Check GET
		$query_string = $_SERVER['QUERY_STRING'];
		// pywrapper defaults to a capabilities search, so mimic that here
		if (array_key_exists('dsa', $_GET) && !substr_count($query_string, '&')) {
			return $this->from_biocase(NULL);
		}
		elseif ($query_string) {
			return $this->from_query();
		}
		// No query found, so load the homepage
    $this->params = $this->config->item('query_params');
		$schemas = [];
		foreach ($this->ciocase->schemas as $url => $val) {
			foreach ($val->namespaces as $url => $prefix) {
				if (in_array($prefix, $this->params['schema']['options'])) {
					$schemas[$url] = $prefix;
				}
			}
		}
		$data = [
			'title' => NULL,
			'params' => $this->params,
			'schemas' => $schemas
		];
		$this->load->view('portal/header', $data);
		$this->load->view('portal/index', $data);
		$this->load->view('portal/footer');
	}


	/**
	 * Responds to a BioCASE Protocol POST request
	 *
	 * @param string $request a BioCASE Protocol XML request file
	 *
	 * @access private
	 */
	private function from_biocase($request) {
		$result = (object) [
			'response' => $this->ciocase->from_request($request),
			'query' => (object) ['format' => 'xml', 'bcp' => 'true'],
			'errors' => []
		];
		$this->finalize($result);
		$this->route_result($result);
	}


	/**
	 * Responds to a query specified in the URL
	 *
	 * @access private
	 */
	private function from_query() {
		$this->load->model($this->ciocase->backend);
		$result = $this->{$this->ciocase->backend}->search();
		$result->response = $this->ciocase->from_result($result);
		$this->finalize($result);
		$this->route_result($result);
	}


	/**
	 * Routes a response to the appropriate view
	 *
	 * @param object $result the result object returned by a ciocase query
	 *
	 * @access private
	 */
	private function route_result($result) {
		// Route result
		switch ($result->query->format) {
			case 'html':
				$page = $this->ciocase->make_page('', $result->query);
				$navbar = $page->get_navbars(['format' => $result->query->format,
				                              'schema' => $result->query->schema]);
				// Add keyword to result page search bar if the original query
				// used default parameters
				$limit = array_get($result->query->kwargs, 'limit', [0])[0];
				$keyword = array_get($result->query->kwargs, 'keyword');
				$val = NULL;
				if ($keyword && $limit == 10) {
					$val = implode(' ', $keyword);
				}
				// Create the payload object
				$data = [
					'response' => ($result->errors) ? $page->get_errors($result->errors) : $result->response->doc->asString(),
					'title' => $page->get_title(),
					'range' => $page->get_range(),
					'navbar' => $navbar,
					'pages' => $page->get_pages(),
					'value' => $val,
					'errors' => $result->errors
				];
				$this->load->view('portal/header', $data);
				$this->load->view('portal/html', $data);
				$this->load->view('portal/footer');
				break;
			case 'json':
				$data['response'] = $result->response->doc->asString();
				$this->load->view('portal/json', $data);
				break;
			case 'mongo':
				$data['response'] = json_encode($result->records, JSON_PRETTY_PRINT);
				$this->load->view('portal/json', $data);
				break;
			case 'xml':
				$data['response'] = $result->response->doc->asString();
				$this->load->view('portal/xml', $data);
				break;
			default:
				echo $format;
		}
	}


	/**
	 * Displays the advanced search page
	 *
	 * @access public
	 */
	public function search() {
		$this->load->model($this->ciocase->backend);

		$page = $this->ciocase->make_page('search', NULL);
		$data = [
			'title' => 'Advanced Search',
			'inputs' => $page->get_inputs()
		];

		$this->load->view('portal/header', $data);
		$this->load->view('portal/search', $data);
		$this->load->view('portal/footer');
	}


	/**
	 * Displays the specimen list
	 *
	 * @access public
	 */
	public function list_specimens() {
		$this->load->model($this->ciocase->backend);

		$result = $this->{$this->ciocase->backend}->list_specimens();
		$page = $this->ciocase->make_page('list', $result->query);

		$data = [
			'title' => 'Specimen List',
			'anchors' => $page->get_anchors($result->records),
			'pages' => $page->get_pages()
		];

		$this->load->view('portal/header', $data);
		$this->load->view('portal/list', $data);
		$this->load->view('portal/footer');
	}


	private function preg_find_one($pattern, $subject, $default = NULL) {
		$matches = [];
		preg_match($pattern, $subject, $matches);
		if ($matches) {
			return $matches[1];
		}
		return $default;
	}


	private function build_request($request = '', $filter = NULL) {
		if ($request) {
			validateXML($request, 'http://www.bgbm.org/biodivinf/Schema/protocol_1_31.xsd');
		}
		if ($filter) {
			validateXML($filter, 'http://minsci.local/share/mansur/filter_1_31.xsd');
		}
		# Timezone seems to be set incorrectly on the server - AM, 2018-02-09
		date_default_timezone_set('America/New_York');
		// Set defaults or pull values from request for required fields
		$send_time = $this->preg_find_one('/<sendTime>(.*?)<\/sendTime>/', $request, date('c'));
	  $req_format = $this->preg_find_one('/<requestFormat>(.*?)<\/requestFormat>/', $request, 'http://www.tdwg.org/schemas/abcd/2.06');
		$rsp_format = $this->preg_find_one('/<responseFormat>(.*?)<\/responseFormat>/', $request, 'http://www.tdwg.org/schemas/abcd/2.06');
		$concept = $this->preg_find_one('/<concept>(.*?)<\/concept>/', $request);
		$count = $this->preg_find_one('/<count>(.*?)<\/count>/', $request, 'false');
		$limit = $this->preg_find_one('/limit\s?=\s?"(\d+?)"/', $request, '100');
		$start = $this->preg_find_one('/start\s?=\s?"(\d+?)"/', $request, '0');
		// Validate filter
		if (!$filter) {
			$filter = $this->preg_find_one('/<filter>(.*?)<\/filter>/s', $request);
		}
		$type = $this->preg_find_one('/<type>(.*?)<\/type>/', $request, 'search');
		// FIXME: Build as a DOMDocument
		$request = '
<?xml version="1.0" encoding="UTF-8"?>
<request xmlns="http://www.biocase.org/schemas/protocol/1.3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.biocase.org/schemas/protocol/1.3 http://www.bgbm.org/biodivinf/Schema/protocol_1_3.xsd">
  <header>
	  <version />
	  <sendTime>' . $send_time . '</sendTime>
	  <source />
	  <destination />
	  <type>' . $type . '</type>
  </header>{}
</request>';
    // Construct the sub-request
    if ($type == 'search') {
			$repl = '
  <search>
    <requestFormat>' . $req_format . '</requestFormat>
    <responseFormat start="' . $start . '" limit="' . $limit . '">' . $rsp_format . '</responseFormat>
    <filter>' . $filter . '</filter>
    <count>' . $count . '</count>
  </search>';
		}
		elseif ($type == 'scan') {
			$repl = '
  <scan>
    <requestFormat>' . $req_format . '</requestFormat>
    <concept>'. $concept . '</concept>
		<filter>' . $filter . '</filter>
  </scan>';
		}
		else {
			$repl = NULL;
		}
		$request = trim(str_replace("{}", $repl, $request));
		#validateXML($request, 'http://www.bgbm.org/biodivinf/Schema/protocol_1_31.xsd');
		#header('Content-Type: text/xml'); echo $request; exit();
		return $request;
	}


	/**
	 * Displays the list of concepts implemented in the application
	 *
	 * @access public
	 */
	public function list_concepts() {
		foreach ($this->ciocase->schemas as $schema) {
			foreach (array_keys($schema->conceptLookup) as $path) {
				echo $path . "\n";
			}
		}
	}


	/**
	 * Displays the list of database keys used in the application
	 *
	 * @access public
	 */
	public function list_keys() {
		foreach ($this->ciocase->schemas as $schema) {
			foreach (array_keys($schema->conceptLookup) as $path) {
				echo $path . "\n";
			}
		}
	}


	/**
	 * Finalizes the response, adding errors, stripping unwanted elements, etc.
	 *
	 * @param object $result the result object returned by a ciocase query
	 *
	 * @access private
	 */
	private function finalize($result) {
		foreach ($result->errors as $error) {
			$result->response->createDiagnostic($error);
		}
		if (property_exists($result->query, 'compiled')) {
			$compiled = json_encode($result->query->compiled->query);
			$result->response->createDiagnostic('Compiled search: ' . $compiled, 'DEBUG');
		}
		// Remove BioCASE wrapper if no errors and bcp is set to false
		if (!$result->errors
		    && property_exists($result->query, 'bcp')
		    && $result->query->bcp == 'false'
				&& property_exists($result->query, 'format')
				&& $result->query->format != 'html') {
			$result->response->doc = $result->response->doc->removeWrapper($result->response);
		}

	}


}

if (ENVIRONMENT == 'production') {
	register_shutdown_function(function() {
		$error = error_get_last();
    if ($error['type'] === E_ERROR) {
			echo 'Something went horribly wrong';
		}
	});
}
