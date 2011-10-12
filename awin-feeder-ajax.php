<?php
add_action('wp_ajax_awin_products_datatable', 'awin_products_datatable');

function awin_products_datatable() {
    global $wpdb;
    //check_ajax_referer
    $whatever = intval( $_POST['whatever'] );
    $whatever += 10;
    echo $whatever;
    die(); // this is required to return a proper result
}

?>
