<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Defines a wrapper to create timers to log execution times within PHP scripts
 *
 * @package   timer
 * @author    Unknown
 * @copyright Unknown
 * @license   Unknown
 */


require_once('Mark.php');


/**
 * Wraps method for creating timer objects
 *
 * @package   timer
 */
class Timer {


  /**
   * Creates a new Mark object
   *
   * @param string $name  the name of the file or feature being benchmarked
   * @param bool   $delay whether to delay the start of the timer. If FALSE,
   *                      the timer starts immediately.
   *
   * @return Mark a new timer
   *
   * @access public
   */
   public function mark($name=NULL, $delay=FALSE) {
     return new Mark($name, $delay);
   }

 }

?>
