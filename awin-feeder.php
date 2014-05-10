<?php
/*
Plugin Name: AWIN Feeder
Plugin URI: https://github.com/OdinsHat/AWIN-Feeder
Description: Build Wordpress posts based on products from an Affiliate Window datafeed
Version: 0.8
Author: Doug Bromley <doug@tintophat.com>
Copyright: Doug Bromley <doug@tintophat.com>
Author URI: http://www.tintophat.com
*/
global $awinfeeder_db_version;
$awinfeeder_db_version = "1.0";

function awinfeeder_install()
{
    global $wpdb;
    global $awinfeeder_db_version;
    $table = $wpdb->prefix.'afeeder_products';

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id              INT(10) auto_increment PRIMARY KEY,
        name            VARCHAR(255),
        description     TEXT,
        promotext       TEXT,
        merchant        VARCHAR(255),
        aw_image        VARCHAR(255),
        aw_thumb        VARCHAR(255),
        m_image         VARCHAR(255),
        m_thumb         VARCHAR(255),
        price           DECIMAL(10,2),
        model_no        VARCHAR(128),
        m_cat           VARCHAR(128),
        a_cat           VARCHAR(128),
        aw_link         VARCHAR(255),
        brand           VARCHAR(128),
        mid             INT(16),
        local_image     VARCHAR(128),
        warranty        VARCHAR(255),
        ean             VARCHAR(32),
        upc             VARCHAR(32),
        mpn             VARCHAR(128)
    )
    ";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}


if(!class_exists("AwinFeeder")){
    class AwinFeeder {
        public $adminOptionsName = 'awinFeederOptions';
        function __construct()
        {
            
        }

        public function init()
        {
            $this->getPluginOptions();
        }

        /**
         * Basic options method.
         */
        public function getPluginOptions()
        {
            $awin_feeder_options = array(
                'author_link' => 'true',
                'awin_user_id' => '',
                'api_key' => '',
                'api_md5_hash' => '',
                'use_local_images' => false
            );
            $savedOptions = get_option($this->adminOptionsName);
            if(!empty($savedOptions)){
                foreach($savedOptions as $key => $val){
                    $awin_feeder_options[$key] = $val;
                }
            }
            update_option($this->adminOptionsName, $awin_feeder_options);
            return $awin_feeder_options;
        }

        public function scProductBlock($atts, $content = null)
        {
            global $wpdb;
            $awin_feeder_options = $this->getPluginOptions();
            $table = $wpdb->prefix.'afeeder_products';
            $sql = "SELECT * FROM $table";
            $conditions = array();
            $output = '';

            if(isset($atts['id'])){
                $sql .= sprintf(' WHERE id=%d', $atts['id']);
            }else{
                foreach($atts as $key => $val){
                    if($key == 'name'){
                        $conditions[] = sprintf("%s LIKE '%%%s%%'", $key, $val);
                    }else if($key == 'brand' || $key == 'merchant'){
                        $conditions[] = sprintf("%s = '%s'", $key, $val);
                    }
                }
                if(count($conditions) > 0){
                    $sql .= ' WHERE '.implode(' AND ', $conditions);
                }
            }
            
            if(isset($atts['offset'])){
                $sql .= sprintf(' LIMIT %d,1', $atts['offset']);
            }else{
                $sql .= ' LIMIT 1';
            }
            
            $product = $wpdb->get_row($sql, OBJECT);

            $description = $product->description;
            if(strlen($content) > 0){
                $description = $content;
            }

            $thumb_image = $product->aw_thumb;

            if($awin_feeder_options['use_local_images']){
                $thumb_image = '/wp-content/uploads/prodimgs/thumbs/'.$product->local_image;
            }

            $output = sprintf('
            <div id="awf-prod-%d class="aw-prod" style="padding:10px;">
                <h4 class="prod-title">%s</h4>
                <a href="/hopo/%d" rel="nofollow"><img src="%s" alt="%s" class="alignleft" /></a>
                <div class="prod-desc">%s</div>
                <a rel="nofollow" href="/hopo/%d"><img src="/wp-content/plugins/awin-feeder/images/shop-button.png" class="alignright" /></a>
                <br style="clear:both;" />
            </div>', $product->id, $product->name, $product->id, $thumb_image, $product->name, $description, $product->id);

            return $output;
        }

        public function scProductGrid($atts, $content = null)
        {
            global $wpdb;
            $table = $wpdb->prefix.'afeeder_products';
            $sql = "SELECT * FROM $table";

            $col_count = 3;
            $limit = 6;
            $cref = '';

            if(isset($atts['cols'])){
                $col_count = $atts['cols'];
            }

            if(isset($atts['limit'])){
                $limit = $atts['limit'];
            }

            if(isset($atts['cref'])){
                $cref = $atts['cref'];
            }

            $conditions = array();
            $output = '';
            
            foreach($atts as $key => $val){
                if($key == 'name'){
                    $conditions[] = sprintf("%s LIKE '%%%s%%'", $key, $val);
                }else if($key == 'brand' || $key == 'merchant'){
                    $conditions[] = sprintf("%s = '%s'", $key, $val);
                }
            }
            if(count($conditions) > 0){
                $sql .= ' WHERE '.implode(' AND ', $conditions);
            }
            if(isset($atts['orderby'])){
                $sql .= sprintf(' ORDER BY %s', $atts['orderby']);
            }

            if(isset($atts['dir'])){
                $sql .= sprintf(' %s', $atts['dir']);
            }

            $sql .= sprintf(' LIMIT %d', $limit);
            $rows = $wpdb->get_results($sql, OBJECT_K);

            $output = $this->_buildScOutput($rows, $col_count, $cref);

            return $output;
        }

        private function _buildScOutput($rows, $col_count, $cref)
        {
            if(strlen($cref) > 0){
                $cref = '/'.$cref;
            }
            $awin_feeder_options = $this->getPluginOptions();
            switch($col_count){
                case(2):$width='50%';break;
                case(3):$width='33%';break;
                case(4):$width='25%';break;
                default:$width='33%';
            }
            $output = '';
            $output .= '<table class="awf-prod-grid">';
            $output .= '<tr>';
            $i = 0;
            foreach($rows as $row){
                $thumb_image = $row->aw_thumb;

                if($awin_feeder_options['use_local_images']){
                    $thumb_image = '/wp-content/uploads/prodimgs/thumbs/'.$row->local_image;
                }
                $output .= sprintf('
                <td style="vertical-align:top;width:%s">
                    <a rel="nofollow" href="/hopo/%d%s"><img src="%s" alt="%s" /></a><br />
                    <a href="/hopo/%d%s" rel="nofollow">%s</a>
                </td>
                ', $width, $row->id, $cref, $thumb_image, $row->name, $row->id, $cref, $row->name);
                $i++;
                if($i % $col_count == 0){
                    $output .= '</tr><tr>';
                }
            }
            $output .= '</tr>';
            $output .= '</table>';
            return $output;
        }

        private function _grabImage($target_path, $image_url, $name, $type = 'full')
        {
            $name_parts = explode(' ', $name);
            $new_filename = $name_parts[0].'-'.$name_parts[1].hash('crc32', $name).rand(1,99).'.jpg';
            $local_image_file  = $target_path.$new_filename;
            $ch = curl_init($image_url);
            $fp = fopen($local_image_file, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);

            return $new_filename;
        }

        public function fetchImages()
        {
            global $wpdb;
            $table = $wpdb->prefix.'afeeder_products';
            $sql = "SELECT id, m_image, aw_thumb, name FROM $table WHERE local_image = ''";
            $wpdb->show_errors();
            $rows = $wpdb->get_results($sql, OBJECT_K);
            
            $target_path = ABSPATH.'wp-content/uploads/prodimgs/';
            if($type == 'thumb'){
                $target_path .= 'thumbs/';
            }

            if(!file_exists($target_path)){
                if(!mkdir($target_path, 0777, true)){
                    die('Coud not produce target directory - please create manually');
                }
            }
            foreach($rows as $row){
                $local_image = $this->_grabImage($target_path, $row->m_image, $row->name, 'full');
                $wpdb->update($table, array('local_image' => $local_image), array('id' => $row->id), array('%s'), array('%d'));
            }      
        }

        public function insertProduct($data)
        {
            global $wpdb;
            $table = $wpdb->prefix.'afeeder_products';
            $mapped_data = array(
                'mid' => $data[0],
                'merchant' => $data[1],
                'model_no' => $data[8],
                'upc' => $data[4],
                'ean' => $data[5],
                'mpn' => $data[6],
                'name' => $data[9],
                'description' => $data[10],
                'promotext' => $data[12],
                'aw_image' => $data[23],
                'aw_thumb' => $data[22],
                'price' => $data[28],
                'm_cat' => $data[13],
                'a_cat' => $data[15],
                'aw_link' => $data[21],
                'brand' => $data[17],
                'm_image' => $data[20],
                'm_thumb' => $data[19],
                'warranty' => $data[38]
            );
            $wpdb->show_errors();
            echo $wpdb->insert($table, $mapped_data);

        }

        /**
         * Display listing of products.
         * @todo use jQuery datatable
         */
        public function printProductsList()
        {
            echo '<table class="display" id="products-table"><thead><tr><th>ID</th><th>Image</th><th>Name</th><th>Merchant</th><th>Brand</th><th>Price</th><th>Actions</th></tr></thead><tbody>';
            echo '<tr><td colspan="4">Loading</td></tr>';
            echo "</table>";
        }

        /**
         * Print the main admin page
         */
        public function printAdminPage()
        {
            $awin_feeder_options = $this->getPluginOptions();
            // If admin page has been submitted then...
            if(isset($_POST['update_awinfeeder'])){
                $awin_feeder_options['api_key'] = $_POST['awin_api_key'];
                $awin_feeder_options['awin_user_id'] = $_POST['awin_user_id'];
                $awin_feeder_options['use_local_images'] = $_POST['use_local_images'];
                update_option($this->adminOptionsName, $awin_feeder_options);
            }


            ?>

            <div class="wrap">
                <h2>General Options</h2>
                <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                    <input type="hidden" name="update_awinfeeder" value="1" />
                    <label for="awin-user-id">AWIN User ID (used for merchant links)</label>
                    <input name="awin_user_id" type="text" id="awin-user-id" value="<?php echo $awin_feeder_options['awin_user_id']; ?>" />
                    <br/>
                    <input type="submit" value="Save" />
                </form>
            </div>

            <?php
        }

        public function printUploadForm()
        {
            if(isset($_POST['upload_data'])){
                $target_path = ABSPATH.'wp-content/uploads/';
                $target_path = $target_path.basename($_FILES['productdata']['name']); 

                if(move_uploaded_file($_FILES['productdata']['tmp_name'], $target_path)) {
                    echo "The file ".  basename( $_FILES['productdata']['name']). 
                        " has been uploaded";
                }else{
                    echo "There was an error uploading the file, please try again!".$target_path;
                    print_r($_FILES);
                }
                if (($handle = fopen($target_path, "r")) !== FALSE) {
                    while (($data = fgetcsv($handle, 1000, "|")) !== FALSE) {
                        $this->insertProduct($data);
                    }
                    fclose($handle);
                }
            }

            ?>

            <div class="wrap">
                <h2>Upload Feed</h2>
                <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>" enctype="multipart/form-data">
                    <p>Upload a CSV with the following columns (the order is important):<br />
                    product name,description,promotext,merchant,awin image,awin thumb,price,model_no,merchant category,awin category,awin deeplink</p>
                    <input type="hidden" name="upload_data" value="1" />
                    <label>File (csv)</label>
                    <input type="file" name="productdata" />
                    <input type="submit" value="Upload" />
                </form>
            </div>

            <?php

        }

        public function handleHop()
        {
            global $wpdb;

            $request = $_SERVER['REQUEST_URI'];
            $parts = explode('/', $request);

            if($parts[1] == 'hopo'){
                $id = $parts[2];
                $table = $wpdb->prefix.'afeeder_products';
                $sql = sprintf('SELECT * FROM %s WHERE id=%d LIMIT 1', $table, $id);
                $rec = $wpdb->get_row($sql, OBJECT);
                header("X-Robots-Tag: noindex, nofollow", true);
                $follow = $rec->aw_link;
                if(isset($parts[3])){
                    $follow .= '&clickref='.$parts[3];
                }
                wp_redirect($follow, 301);
                die();
            }
        }

        public function delProduct()
        {
            global $wpdb;

            $id = $_POST['id'];
            $table = $wpdb->prefix.'afeeder_products';

            $wpdb->query(sprintf('DELETE FROM %s WHERE id=%d', $table, $id));
        }
        
        /**
         * Output JSON encoded listing of products.
         * 
         * This function is called via Ajax to display JSON encoded 
         * listing of all products for the datatable.
         */
        public function jsonProducts()
        {
            global $wpdb;

            $columns = array('id', 'aw_thumb', 'name', 'merchant', 'brand', 'price');
            $column_count = count($columns);

            /* Indexed column (used for fast and accurate table cardinality) */
            $sIndexColumn = "id";

            /* DB table to use */
            $table = $wpdb->prefix.'afeeder_products';

            /* 
            * Paging
            */
            $sLimit = "";
            if(isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1'){
                $sLimit = "LIMIT ".mysql_real_escape_string($_GET['iDisplayStart']).", ".
                mysql_real_escape_string( $_GET['iDisplayLength'] );
            }


            /*
            * Ordering
            */
            if(isset($_GET['iSortCol_0'])){
                $sOrder = "ORDER BY  ";
                for($i=0;$i<$_GET['iSortingCols'];$i++){
                    if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" ){
                        $sOrder .= $columns[$_GET['iSortCol_'.$i]]." ".mysql_real_escape_string( $_GET['sSortDir_'.$i] ) .", ";
                    }
                }

                $sOrder = substr_replace( $sOrder, "", -2 );
                if ( $sOrder == "ORDER BY" ){
                    $sOrder = "";
                }
            }

            /* 
            * Filtering
            * NOTE this does not match the built-in DataTables filtering which does it
            * word by word on any field. It's possible to do here, but concerned about efficiency
            * on very large tables, and MySQL's regex functionality is very limited
            */
            $sWhere = "";
            if ( $_GET['sSearch'] != "" ){
                $sWhere = "WHERE (";
                for($i = 0; $i < $column_count; $i++){
                    $sWhere .= $columns[$i]." LIKE '%".mysql_real_escape_string( $_GET['sSearch'] )."%' OR ";
                }
                $sWhere = substr_replace( $sWhere, "", -3 );
                $sWhere .= ')';
            }

            /* Individual column filtering */
            for($i = 0; $i < $column_count; $i++){
                if ( $_GET['bSearchable_'.$i] == "true" && $_GET['sSearch_'.$i] != '' ){
                    if ( $sWhere == "" ){
                        $sWhere = "WHERE ";
                    }else{
                        $sWhere .= " AND ";
                    }
                    $sWhere .= $columns[$i]." LIKE '%".mysql_real_escape_string($_GET['sSearch_'.$i])."%' ";
                }
            }

            /*
            * SQL queries
            * Get data to display
            */
            $sQuery = "
                SELECT SQL_CALC_FOUND_ROWS ".str_replace(" , ", " ", implode(", ", $columns))."
                FROM $table
                $sWhere
                $sOrder
                $sLimit
            ";
            $wpdb->show_errors();
            $rResult = $wpdb->get_results($sQuery, ARRAY_A);

            /* Data set length after filtering */
            $sQuery = "SELECT FOUND_ROWS()";
            $iFilteredTotal = $wpdb->get_var($sQuery);

            /* Total data set length */
            $sQuery = "
                SELECT COUNT(".$sIndexColumn.")
                FROM  $table
            ";
            $iTotal = $wpdb->get_var($sQuery);

            /*
            * Output
            */
            $output = array(
                "sEcho" => intval($_GET['sEcho']),
                "iTotalRecords" => $iTotal,
                "iTotalDisplayRecords" => $iFilteredTotal,
                "aaData" => array()
            );

            foreach($rResult as $aRow){
                $row = array();
                for($i = 0; $i < $column_count; $i++){
                    if($columns[$i] == 'aw_thumb'){
                        $row[] = '<img src="'.$aRow[$columns[$i]].'" />';
                    }else{
                        $row[] = $aRow[$columns[$i]];
                    }
                }
                $row[] = "<a href='#' id='{$aRow['id']}' class='aw-prod-del'>Del</a>";
                $row['DT_RowId'] = 'row_'.$aRow['id'];
                $output['aaData'][] = $row;
            }

            echo json_encode( $output );
            die();
        }

        public function plugin_scripts()
        {
            wp_enqueue_script('jquery_datatables', plugins_url('js/jquery.dataTables.min.js',__FILE__));
            wp_enqueue_script('products_js', plugins_url('js/products.js',__FILE__));
        }

        public function plugin_styles()
        {
            wp_enqueue_style('products_css', plugins_url('css/products.css',__FILE__));
        }

    }
}

register_activation_hook(__FILE__,'awinfeeder_install');

if(class_exists("AwinFeeder")){
    $awin_feeder = new AwinFeeder();
}

//Initialize the admin panel
if (!function_exists("SetupAwinFeeder")) {
    function SetupAwinFeeder() {
        global $awin_feeder;
        if (!isset($awin_feeder)) {
            return;
        }
        if (function_exists('add_menu_page')) {
            add_menu_page('AWIN Feeder', 'AWIN Feeder', 8, 'awin-feeder', array(&$awin_feeder, 'printAdminPage'));
            $products_page = add_submenu_page('awin-feeder', 'Products', 'Products', 8, 'awin-products', array(&$awin_feeder, 'printProductsList'));
            add_submenu_page('awin-feeder', 'Upload', 'Upload', 8, 'awin-upload', array(&$awin_feeder, 'printUploadForm'));
        }
    }
}

if(isset($awin_feeder)){
    //Actions & Filters
    add_action('admin_menu', 'SetupAwinFeeder');
    add_action('activate_awin-feeder/awin-feeder.php', array(&$awin_feeder, 'init'));
    add_action('admin_print_scripts', array(&$awin_feeder, 'plugin_scripts'));
    add_action('admin_print_styles', array(&$awin_feeder, 'plugin_styles'));
    add_action('wp_ajax_aw_json_prod', array(&$awin_feeder, 'jsonProducts'));
    add_action('wp_ajax_aw_del_prod', array(&$awin_feeder, 'delProduct'));
    add_action('init', array(&$awin_feeder, 'handleHop'));

    add_shortcode('aw-prodgrid', array(&$awin_feeder, 'scProductGrid'));
    add_shortcode('aw-prodblock', array(&$awin_feeder, 'scProductBlock'));
}
include_once dirname( __FILE__ ) . '/widgets/awinfeeder_random.php';
include_once dirname( __FILE__ ) . '/widgets/awinfeeder_cheapdear.php';

?>
