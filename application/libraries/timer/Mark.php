<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Defines methods to log execution times within PHP scripts
 *
 * The code here was adapted from a StackOverflow post that I can no longer
 * find :(
 *
 * @package   timer
 * @author    Unknown
 * @copyright Unknown
 * @license   Unknown
 */


 /**
  * Defines methods to log execution times within PHP scripts
  *
  * @package   timer
  */
class Mark {

  /**
	  * The time at which tracking started
    *
    * This time can be reset using $this->start()
	  *
	  * @var float
	  */
   private $startTime;


   /**
 	  * The time at which tracking stopped
 	  *
 	  * @var float
 	  */
   private $endTime;


   /**
    * Constructs a new Mark object
    *
    * @param string $name  the name of the file or feature being benchmarked
    * @param bool   $delay whether to delay the start of the timer. If FALSE,
    *                      the timer starts immediately.
    *
    * @return void
    *
    * @access public
    */
	 public function __construct($name=NULL, $delay=FALSE) {
     $this->name = $name;
		 if (!$delay) {
			 $this->start();
		 }
	 }


   /**
    * Starts or resets the timer
    *
    * @return void
    *
    * @access public
    */
   public function start() {
       $this->startTime = microtime(TRUE);
   }


   /**
    * Stops the timer
    *
    * @return void
    *
    * @access public
    */
   public function end() {
       $this->endTime =  microtime(TRUE);
   }


   /**
    * Calculates the elapsed time
    *
    * @return void
    *
    * @access public
    */
   public function diff() {
       return $this->endTime - $this->startTime;
   }


   /**
    * Logs the elapsed time to /application/logs
    *
    * @param string $msg   a descriptive message
    * @param string $level the mimumum log level at which to report the
    *                      results of this timer
    *
    * @return void
    *
    * @access public
    */
	 public function log($msg, $level='debug') {
		 $this->end();
     if ($this->name) {
       $msg = $this->name . ': ' . $msg;
     }
		 $msg = trim($msg) . ' (dt=' . round($this->diff(), 4) . ')';
		 log_message($level, $msg);
	 }


   /**
    * Logs the current time time to /application/logs
    *
    * @param string $msg   a descriptive message
    * @param string $level the mimumum log level at which to report the
    *                      results of this timer
    *
    * @return void
    *
    * @access public
    */
	 public function note($msg, $level='debug') {
     if ($this->name) {
       $msg = $this->name . ': ' . $msg;
     }
     log_message($level, $msg);
	 }

 }

?>
