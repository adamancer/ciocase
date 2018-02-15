<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
    <h1>NMNH Mineral Sciences Advanced Search</h1>
    <ul class="bar float-right"><li><a href="<?php echo site_url('portal'); ?>">home</a></li><li><a href="<?php echo site_url('portal/search'); ?>">search</a></li></ul>
    <div class="clear"></div>
    <form id="advanced" action="<?php echo site_url('portal'); ?>" method="get">
      <?php foreach ($inputs as $input) { echo $input . "\n"; } ?>
      <input type="submit" value="Submit" />
    </form>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script>
      $(document).ready(function() {
        $("#advanced").submit(function() {
          $(this).find("input").each(function(index) {
            $this = $(this);
            $this.removeAttr("name");
            if ($this.val()) {
              $this.attr("name", $this.attr("id"));
            }
          });
          return true;
        });
      });
    </script>
