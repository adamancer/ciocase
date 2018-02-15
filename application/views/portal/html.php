<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
    <div class="header">
      <h1><?php echo $title; ?></h1>
      <div class="float-right">
        <ul class="bar"><li><a href="<?php echo site_url('portal'); ?>">home</a></li><li><a href="<?php echo site_url('portal/search'); ?>">search</a></li></ul><?php echo $navbar; ?>
        <form id="navsearch" class="clear" action="<?php echo site_url('portal'); ?>" method="get">
          <input name="keyword" placeholder="Search the collection by keyword" value="<?php echo $value ?>"/><input type="submit" value="Submit" />
          <input name="limit" type="hidden" value="10" />
        </form>
      </div>
      <div class="clear"></div>
    </div>
    <?php echo $range; echo $pages; echo '<div id="results">' . $response . '</div>'; echo $pages; ?>
