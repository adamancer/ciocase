<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Defines classes to write a BioCASe response as HTML
 *
 * @package   ciocase
 * @author    Adam Mansur <mansura@si.edu>
 * @copyright (c) 2017-2018 Smithsonian Institution
 * @license   MIT License
 */


require_once('JSONDocument.php');


class HTMLDocument extends JSONDocument {

    private function recurseArray($item, $path=[], $result=[]) {
      if (is_array($item)) {
        foreach ($item as $key => $val) {
          $path[] = $key;
          $result = $this->recurseArray($item[$key], $path, $result);
          array_pop($path);
        }
      }
      else {
        $localNames = array_map([$this, 'getLocalName'], $path);
        $key = '/' . implode('/', $localNames);
        $result[$key][] = $item;
      }
      return $result;
    }


    public function asString() {
      // Get records
      $records = array_get($this->root, 'content', []);
      $path = explode($this->schema->delimiter, ltrim($this->schema->rec_ids[0], '/'));
      $unit_id = array_pop($path);
      foreach ($path as $key) {
        if (is_sequential($records) and count($records) == 1) {
          $records = $records[0];
        }
        if (array_key_exists($key, $records)) {
          $records = $records[$key];
        }
      }
      $rec_path = implode($this->schema->delimiter, $path);
      if (!$records) {
        return '<p class="clear">No records were found!</p>';
      }
      // Add records
      $html = [];
      foreach ($records as $i => $rec) {
        $i++;
        $rec_num = $i + $this->doc->root['attributes']['recordStart'];
        #$html[] = "<h2>Record</h2>";
        $table = [];
        $table[] = '<table>';
        $result = $this->recurseArray($rec);
        foreach ($result as $key => $vals) {
          $table[] = "<tr><th>/$rec_path$key</th><td>";
          if (count($vals) == 1) {
            $val = $vals[0];
            $vals = explode(' | ', $val);
            $is_image = FALSE;
            foreach ($vals as $j => $val) {
              if (filter_var($val, FILTER_VALIDATE_URL)) {
                $url = $val;
                // Show thumbnails in the HTML view
                if (!isset($thumb) && startswith($val, 'https://collections.nmnh.si.edu/media/?irn=')) {
                  $thumb = anchor($url, '<img src="' . $url . '&w=90&h=90" />');
                  $table[] = $thumb;
                }
                $vals[$j] = anchor($url, $val);
              }
            }
            if (count($vals) == 1) {
              $table[] = $vals[0];
            }
            #elseif ($is_image) {
            #  $table[] = implode(' ', $vals);
            #}
            else {
              $table[] = '<ul><li>'. implode('</li><li>', $vals) . '</li></ul>';
            }
          }
          else {
            $items = implode('</li><li>', $vals);
            $table[] = "<ul><li>$items</li></ul>";
          }
          $table[] = '</td></tr>';
        }
        if (isset($thumb)) {
        #  array_splice($table, 1, 0, $thumb);
          unset($thumb);
        }
        if ($table[count($table) - 1] != '<table>') {
          $table[] = '</table>';
        }
        else {
          array_pop($table);
          array_pop($table);
        }
        $html = array_merge($html, $table);
      }
      return implode('', $html);
    }

}

?>
