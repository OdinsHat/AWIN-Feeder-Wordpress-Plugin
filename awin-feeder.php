<?php
/*
Plugin Name: AWIN Feeder
Plugin URI: http://www.tintophat.com/products/wp/awin-feeder
Description: Build Wordpress posts based on products from an AWIN datafeed
Version: 0.8
Author: Doug Bromley
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
        aw_link         VARCHAR(255)
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
                'api_key' => '',
                'api_md5_hash' => ''
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

        public function insertProduct($data)
        {
            //product name,description,promotext,merchant,awin image,awin thumb,price,model_no,merchant category,awin category,awin deeplink</p>
            global $wpdb;
            $table = $wpdb->prefix.'afeeder_products';
            $mapped_data = array(
                'name' => $data[0],
                'description' => $data[1],
                'promotext' => $data[2],
                'merchant' => $data[3],
                'aw_image' => $data[4],
                'aw_thumb' => $data[5],
                'price' => $data[6],
                'model_no' => $data[7],
                'm_cat' => $data[8],
                'a_cat' => $data[9],
                'aw_link' => $data[10],
                'brand' => $data[11]
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
            global $wpdb;
            $table = $wpdb->prefix.'afeeder_products';
            $sql = "SELECT * FROM $table";

            $rows = $wpdb->get_results($sql, OBJECT_K);
            echo '<table><thead><tr><th>Name</th><th>Merchant</th><th>Brand</th><th>Price</th></tr></thead><tbody>';
            foreach($rows as $row){
                echo "<tr><td>$row->name</td><td>$row->merchant</td><td>$row->brand</td><td>$row->price</td></tr>";
            }
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
                update_option($this->adminOptionsName, $awin_feeder_options);
            }


            ?>

            <script type="text/javascript">
                function runSearch() {
                    jQuery('#products').load('/wp-content/plugins/awin-feeder/awin-feeder-ajax.php');
                }
            </script>

            <div class="wrap">
                <h2>AWIN Feeder Management</h2>
                <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                    <input type="hidden" name="update_awinfeeder" value="1" />
                    <h3>Add Feed</h3>
                    <p>Be careful that you don't import a feed of several hundred 
                    thousand. Althoguh its theoretically possible its better to keep
                    the numbers lower.</p>
                    <label for="awin-api-key">AWIN API Key</label>
                    <input name="awin_api_key" type="text" id="awin-api-key" value="<?php echo $awin_feeder_options['api_key']; ?>" />
                    <input type="submit" value="Save" />
                </form>
                <form>
                    <h3>Import Products</h3>
                    <p>Narrow your preferences to a certain type of product then press "Search"</p>
                    <label for="search-term">Search Term</label>
                    <input type="text" name="search_term" id="search_term" />
                    <input type="button" onclick="runSearch()" value="Search" />
                    <div id="products">

                    </div>
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
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
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

        public function printDatatableJs()
        {
            ?>

            <script type="text/javascript">
            jQuery(document).ready(function($) {
                var data = {
                    action: 'my_action',
                    whatever: 1234
                };

                // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
                jQuery.post(ajaxurl, data, function(response) {
                    alert('Got this from the server: ' + response);
                });
            });
            </script>

            <?php
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
            add_submenu_page('awin-feeder', 'Products', 'Products', 8, 'awin-products', array(&$awin_feeder, 'printProductsList'));
            add_submenu_page('awin-feeder', 'Upload', 'Upload', 8, 'awin-upload', array(&$awin_feeder, 'printUploadForm'));
        }
    }     
}

if(isset($awin_feeder)){
    //Actions & Filters
    add_action('admin_menu', 'SetupAwinFeeder');
    add_action('activate_awin-feeder/awin-feeder.php', array(&$awin_feeder, 'init'));
    add_action('admin_head', array(&$awin_feeder, 'printDatatableJs'));
}
include_once dirname( __FILE__ ) . '/widgets/awinfeeder_random.php';

?>
