<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
    <h1>NMNH Geology Collections Data Portal</h1>
    <ul class="bar float-right"><li><a href="<?php echo site_url('portal'); ?>">home</a></li><li><a href="<?php echo site_url('portal/search'); ?>">search</a></li></ul>

    <p>Returns standardized data about the geology collections held by the National Museum of Natural History at the Smithsonian Institution</p>

    <h2>Keyword Search</h2>
    <form id="search" action="<?php echo site_url('portal'); ?>" method="get">
      <input name="keyword" placeholder="Search the collection by keyword"/>
      <input name="limit" type="hidden" value="10" />
      <input type="submit" value="Submit" />
    </form>
    <p style="margin-left: 0.5em;"><a href=<?php echo site_url('portal/list'); ?>>View specimen list</a></p>

    <h2>Query paramaters</h2>
    <p>Request data via a GET request using the parameters defined below:</p>
    <table id="definitions" class="top-header">
      <tr><th>Parameter</th><th>Definition</th></tr>
      <?php
        foreach ($params as $key => $val) {
          $definition = $val['definition'];
          if (array_key_exists('options', $val)) {
            $definition = rtrim($definition, '. ') . '. One of ' . oxford_comma($val['options'], 'or') . '.';
          }
          echo "<tr><td>$key</td><td>$definition</td></tr>\n";
        }
      ?>
    </table>
    <p>Use the <?php echo anchor(site_url('portal/search'), 'advanced search'); ?> to try more specific queries.</p>

    <h3>Examples</h3>
    <ul>
      <li>Return basalts from Hawaii as ABCDEFG: <?php echo anchor(site_url('portal?schema=abcdefg&classification=basalt&state=hawaii')); ?></li>
      <li>Return the first ten granites from France as XML: <?php echo anchor(site_url('portal?classification=granite&country=france&limit=10&format=xml')); ?></li>
      <li>Return basalts from Iceland or India: <?php echo anchor(site_url('portal?classification=basalt&country=iceland&country=india')); ?></li>
      <li>Return specimen by name: <?php echo anchor(site_url('portal?keyword=hope+diamond')); ?></li>
      <li>Return gastropods from the Kinzers formation as ABCD and formatted in JSON: <?php echo anchor(site_url('portal?classification=gastropoda&unit=kinzers&schema=abcd&format=json')); ?></li>
    </ul>
    <h3>Supported schemas</h2>
    <p>The following schemas are currently supported:</p>
    <table id="schemas" class="top-header">
      <tr><th>Abbreviation</th><th>URL</th></tr>
      <?php
        foreach ($schemas as $url => $prefix) {
          $url = anchor($url);
          echo "<tr><td>$prefix</td><td>$url</td></tr>\n";
        }
      ?>
    </table>

    <?php #if (ENVIRONMENT == 'production') { echo '<br /><br /><br /><br /><br /><br /><br /><br /><!--'; }?>
    <h2>BioCASE Protocol</h2>
    <p>More complex queries can be made using the <?php echo anchor('http://www.biocase.org/products/protocols/', 'BioCASE Protocol'); ?>. To submit a request using this protocol, use the form below or submit a POST request containing {'query': xml}.</p>
    <form id="sandbox" action="<?php echo site_url('portal'); ?>" method="post">
      <textarea name="query"></textarea>
      <input type="submit" value="Submit" />
    </form>

    <h2>Limitations and incongruities</h2>

    <h3>Webservice and BioCASE search requests</h3>
    <ul>
      <li>Some fields are not indexed optimally and complex queries may time out</li>
      <li>Darwin Core XML does not validate because of an issue with the dcterms namespace, but it looks okay otherwise</li>
    </ul>

    <h3>BioCASE capabilities requests</h3>
    <ul>
      <li>Requests for fields with hundreds of thousands of unique values have been disabled</li>
      <li>Capabilities requests return data directly from the collections database. Some values (inluding all URIs) are formatted on output during other queries, so values returned for a capabilities request will not always exactly match those returned by a search.</li>
    </ul>
    <?php #if (ENVIRONMENT == 'production') { echo '-->'; }?>
