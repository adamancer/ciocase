<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Backend functions to format CodeIgniter Query Builder queries for MongoDB
 *
 * @package   cingo
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


class Cingo_connect
{

  public $manager;
  public $database;
  protected $CI;
  private $conn_string;


  public function __construct() {
    $this->CI =& get_instance();
    $this->write_conn_string();
    $this->manager = $this->connect();
  }


  private function write_conn_string() {
    $this->CI->config->load('cingo');
    $host	= trim($this->CI->config->item('host'));
    $port = trim($this->CI->config->item('port'));
    $user = trim($this->CI->config->item('user'));
    $pass = trim($this->CI->config->item('pass'));
    $login_db = trim($this->CI->config->item('login_db'));
    $dbname = trim($this->CI->config->item('db'));
    $query_safety = $this->CI->config->item('query_safety');
    $dbhostflag = (bool)$this->CI->config->item('db_flag');
    // Errors
    if (empty($host)){
      trigger_error('Host must be set to connect to MongoDB', E_USER_ERROR);
    }
    if (empty($login_db) || empty($dbname)){
      trigger_error('Database must be set to connect to MongoDB', E_USER_ERROR);
    }
    $params = array();
    $params[] = "mongodb://";
    if (!empty($user) && !empty($pass)){
      $params[] = $user . ':' . $pass . '@';
    }
    if (isset($port) && !empty($port)){
      $params[] = $host . ':' . $port;
    }
    else {
      $params[] = $host;
    }
    if ($dbhostflag === TRUE) {
      $params[] = '/' . $login_db;
    }
    $this->conn_string = implode('', $params);
    $this->database = $dbname;
    return $this;
  }


  public function connect() {
    return new MongoDB\Driver\Manager($this->conn_string);
  }

}
?>
