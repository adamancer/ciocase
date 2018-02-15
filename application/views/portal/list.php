<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
    <h1>NMNH Mineral Sciences Specimen List</h1>
    <ul class="bar float-right"><li><a href="<?php echo site_url('portal'); ?>">home</a></li><li><a href="<?php echo site_url('portal/search'); ?>">search</a></li></ul>
    <div class="clear"></div>
    <?php echo $pages ?>
    <ul class="list"><li><?php echo implode('</li><li>', $anchors); ?></li></ul>
    <?php echo $pages ?>
