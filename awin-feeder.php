<?php
/**
 * Plugin Name: AWIN Feeder
 * Plugin URI: https://github.com/OdinsHat/AWIN-Feeder
 * Description: Build Wordpress posts based on products from an Affiliate Window datafeed
 * Version: 0.8
 * Author: Doug Bromley <doug@tintophat.com>
 * Copyright: Doug Bromley <doug@tintophat.com>
 * Author URI: http://www.tintophat.com.
 *
 * @category OdinsHat
 *
 * @author  Doug Bromley <doug@tintophat.com>
 * @copyright 2021 Doug Bromley <doug@tintophat.com>
 * @license BSD
 */
global $awinfeeder_db_version;
$awinfeeder_db_version = '1.1';

/**
 * Installs the AWIN Feeder plugin.
 *
 * Creates the product table if it doesn't
 * already exist.
 */
function awinfeeder_install()
{
    global $wpdb;
    global $awinfeeder_db_version;
    $table = $wpdb->prefix.'afeeder_products';

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
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

    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

if (!class_exists('AwinFeeder')) {
    /**
     * Main AWIN Feeder class.
     *
     * Contains all the major methods to make the plugin run.
     */
    class AwinFeeder
    {
        public $adminOptionsName = 'awinFeederOptions';

        /**
         * Constructor method - currently does nothing.
         */
        public function __construct()
        {
        }

        /**
         * Initialises the plugin options.
         */
        public function init()
        {
            $this->getPluginOptions();
        }

        /**
         * Called by the init() method to set all plugin options in the
         * AwinFeeder class.
         *
         * @return array plugin options
         */
        public function getPluginOptions()
        {
            $awin_feeder_options = [
                'author_link' => 'true',
                'awin_user_id' => '',
                'api_key' => '',
                'api_md5_hash' => '',
                'use_local_images' => false,
            ];
            $savedOptions = get_option($this->adminOptionsName);
            if (!empty($savedOptions)) {
                foreach ($savedOptions as $key => $val) {
                    $awin_feeder_options[$key] = $val;
                }
            }
            update_option($this->adminOptionsName, $awin_feeder_options);

            return $awin_feeder_options;
        }

        /**
         * Short code handler for [aw-prodblock].
         *
         * Short code will output a single product with picture, description
         * and price given a product id.
         *
         * Example usage:
         * [aw-prodblock id="999"][/aw-prodblock]
         *
         * Example alternative:
         * [aw-prodblock id="999"]Alternative description[/aw-prodblock]
         *
         * @param array  $atts    Short code attributes ("id" in this case)
         * @param string $content Content between the tags will become
         *                        the product description if not null
         *
         * @return string Text to be output in place of the shortcode
         */
        public function scProductBlock($atts, $content = null)
        {
            global $wpdb;
            $awin_feeder_options = $this->getPluginOptions();
            $table = $wpdb->prefix.'afeeder_products';
            $sql = "SELECT id, name, aw_thumb, description FROM {$table}";
            $conditions = [];
            $output = '';

            if (isset($atts['id'])) {
                $sql .= sprintf(' WHERE id=%d', $atts['id']);
            } else {
                foreach ($atts as $key => $val) {
                    if ('name' === $key) {
                        $conditions[] = sprintf("%s LIKE '%%%s%%'", $key, $val);
                    } elseif ('brand' === $key || 'merchant' === $key) {
                        $conditions[] = sprintf("%s = '%s'", $key, $val);
                    }
                }
                if (count($conditions) > 0) {
                    $sql .= ' WHERE '.implode(' AND ', $conditions);
                }
            }

            if (isset($atts['offset'])) {
                $sql .= sprintf(' LIMIT %d,1', $atts['offset']);
            } else {
                $sql .= ' LIMIT 1';
            }

            $product = $wpdb->get_row($sql, OBJECT);

            $description = $product->description;
            if (strlen($content) > 0) {
                $description = $content;
            }

            $thumb_image = $product->aw_thumb;

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

        /**
         * Short code handler for [aw-prodgrid] shortcode.
         *
         * Will select products from the table for sending to the grid generator.
         * {@see AwinFeeder::_buildScOutput()}
         * The $atts will change the output layout, ordering of products by
         * price, name, etc. Also can assign click refs to product links.
         *
         * @param array  $atts    Change the structure of the grid based on these options:
         *                        cols = number of product columns to display
         *                        limit = total number of products to display
         *                        cref = the click reference to be assigned to all links in the grid
         *                        orderby = what to order the product selection by (e.g. price)
         *                        dir = direction ASC/DESC
         *                        name = basic LIKE filter applied to "name" field of DB
         * @param string $content Unused in this shortcode
         *
         * @return string Content the be output in place of shortcode
         */
        public function scProductGrid($atts, $content = null)
        {
            global $wpdb;
            $table = $wpdb->prefix.'afeeder_products';
            $sql = "SELECT * FROM {$table}";

            $col_count = 3;
            $limit = 6;
            $cref = '';

            if (isset($atts['cols'])) {
                $col_count = $atts['cols'];
            }

            if (isset($atts['limit'])) {
                $limit = $atts['limit'];
            }

            if (isset($atts['cref'])) {
                $cref = $atts['cref'];
            }

            $conditions = [];
            $output = '';

            foreach ($atts as $key => $val) {
                if ('name' === $key) {
                    $conditions[] = sprintf("%s LIKE '%%%s%%'", $key, $val);
                } elseif ('brand' === $key || 'merchant' === $key) {
                    $conditions[] = sprintf("%s = '%s'", $key, $val);
                }
            }
            if (count($conditions) > 0) {
                $sql .= ' WHERE '.implode(' AND ', $conditions);
            }
            if (isset($atts['orderby'])) {
                $sql .= sprintf(' ORDER BY %s', $atts['orderby']);
            }

            if (isset($atts['dir'])) {
                $sql .= sprintf(' %s', $atts['dir']);
            }

            $sql .= sprintf(' LIMIT %d', $limit);
            $rows = $wpdb->get_results($sql, OBJECT_K);

            $output = $this->_buildScOutput($rows, $col_count, $cref);

            return $output;
        }

        /**
         * Inserts a single product into the product table.
         *
         * Imports the data sent from the {@see AWINFeeder::printUploadForm()}
         *
         * @param array $data single product data
         */
        public function insertProduct($data)
        {
            global $wpdb;
            $table = $wpdb->prefix.'afeeder_products';
            $mapped_data = [
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
                'warranty' => $data[38],
            ];
            $wpdb->show_errors();
            echo $wpdb->insert($table, $mapped_data);
        }

        /**
         * Display listing of products.
         *
         * @todo use better table
         * @todo use better formatting
         * @todo provide more functionality from the table:
         *       e.g. view, edit and delete confirmation.
         */
        public function printProductsList(): void
        {
            echo '<table class="display" id="products-table"><thead><tr><th>ID</th><th>Image</th><th>Name</th><th>Merchant</th><th>Brand</th><th>Price</th><th>Actions</th></tr></thead><tbody>';
            echo '<tr><td colspan="4">Loading</td></tr>';
            echo '</table>';
        }

        /**
         * Prints the main (very sparse) admin page.
         *
         * Simply enables user to put their AWIN id in for use in links.
         */
        public function printAdminPage()
        {
            $awin_feeder_options = $this->getPluginOptions();
            // If admin page has been submitted then...
            if (isset($_POST['update_awinfeeder'])) {
                $awin_feeder_options['api_key'] = $_POST['awin_api_key'];
                $awin_feeder_options['awin_user_id'] = $_POST['awin_user_id'];
                update_option($this->adminOptionsName, $awin_feeder_options);
            } ?>

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

        /**
         * Print upload form.
         *
         * The upload form gives a description of the file format to upload
         * with a file form element.
         */
        public function printUploadForm()
        {
            if (isset($_POST['upload_data'])) {
                $target_path = ABSPATH.'wp-content/uploads/';
                $target_path = $target_path.basename($_FILES['productdata']['name']);

                if (move_uploaded_file($_FILES['productdata']['tmp_name'], $target_path)) {
                    echo 'The file '.basename($_FILES['productdata']['name']).
                        ' has been uploaded';
                } else {
                    echo 'There was an error uploading the file, please try again!'.$target_path;
                    print_r($_FILES);
                }
                if (($handle = fopen($target_path, 'r')) !== false) {
                    while (($data = fgetcsv($handle, 1000, '|')) !== false) {
                        $this->insertProduct($data);
                    }
                    fclose($handle);
                }
            } ?>

            <div class="wrap">
                <h2>Upload Feed</h2>
                <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>" enctype="multipart/form-data">
                    <p>When downloading a feed from Affiliate Window choose the following settings:</p>
                        <ul>
                            <li>Recommended fields</li>
                            <li>CSV format</li>
                            <li>Any compression type (you'll need to decompress before upload</li>
                            <li>Use "|" as separator for CSV file</li>
                        </ul>
                    <input type="hidden" name="upload_data" value="1" />
                    <label>File (csv)</label>
                    <input type="file" name="productdata" />
                    <input type="submit" value="Upload" />
                </form>
            </div>

            <?php
        }

        /**
         * Handles the redirection of a unique product link to its correct
         * affiliate link.
         */
        public function handleHop(): void
        {
            global $wpdb;

            $request = $_SERVER['REQUEST_URI'];
            $parts = explode('/', $request);

            if ('hopo' === $parts[1]) {
                $id = $parts[2];
                $table = $wpdb->prefix.'afeeder_products';
                $sql = sprintf('SELECT aw_link FROM %s WHERE id=%d LIMIT 1', $table, $id);
                $rec = $wpdb->get_row($sql, OBJECT);
                header('X-Robots-Tag: noindex, nofollow', true);
                $follow = $rec->aw_link;
                if (isset($parts[3])) {
                    $follow .= '&clickref='.$parts[3];
                }
                wp_redirect($follow, 301);

                exit();
            }
        }

        /**
         * Called via ajax call to delete a product from the database.
         */
        public function delProduct()
        {
            global $wpdb;

            $id = $_POST['id'];
            $table = $wpdb->prefix.'afeeder_products';

            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id=%d", $id));
        }

        public function jsonProducts(): void
        {
            global $wpdb;

            $columns = ['id', 'aw_thumb', 'name', 'merchant', 'brand', 'price'];
            $column_count = count($columns);

            $sIndexColumn = 'id';

            // DB table to use
            $table = $wpdb->prefix.'afeeder_products';

            // Paging
            $sLimit = '';
            if (isset($_GET['iDisplayStart']) && '-1' !== $_GET['iDisplayLength']) {
                $sLimit = 'LIMIT '.mysql_real_escape_string($_GET['iDisplayStart']).', '.
                mysql_real_escape_string($_GET['iDisplayLength']);
            }

            // Ordering
            if (isset($_GET['iSortCol_0'])) {
                $sOrder = 'ORDER BY  ';
                for ($i = 0; $i < $_GET['iSortingCols']; ++$i) {
                    if ('true' === $_GET['bSortable_'.(int) ($_GET['iSortCol_'.$i])]) {
                        $sOrder .= $columns[$_GET['iSortCol_'.$i]].' '.mysql_real_escape_string($_GET['sSortDir_'.$i]).', ';
                    }
                }

                $sOrder = substr_replace($sOrder, '', -2);
                if ('ORDER BY' === $sOrder) {
                    $sOrder = '';
                }
            }

            /*
            * Filtering
            * NOTE this does not match the built-in DataTables filtering which does it
            * word by word on any field. It's possible to do here, but concerned about efficiency
            * on very large tables, and MySQL's regex functionality is very limited
            */
            $sWhere = '';
            if ('' !== $_GET['sSearch']) {
                $sWhere = 'WHERE (';
                for ($i = 0; $i < $column_count; ++$i) {
                    $sWhere .= $columns[$i]." LIKE '%".mysql_real_escape_string($_GET['sSearch'])."%' OR ";
                }
                $sWhere = substr_replace($sWhere, '', -3);
                $sWhere .= ')';
            }

            // Individual column filtering
            for ($i = 0; $i < $column_count; ++$i) {
                if ('true' === $_GET['bSearchable_'.$i] && '' !== $_GET['sSearch_'.$i]) {
                    if ('' === $sWhere) {
                        $sWhere = 'WHERE ';
                    } else {
                        $sWhere .= ' AND ';
                    }
                    $sWhere .= $columns[$i]." LIKE '%".mysql_real_escape_string($_GET['sSearch_'.$i])."%' ";
                }
            }

            /*
            * SQL queries
            * Get data to display
            */
            $sQuery = '
                SELECT SQL_CALC_FOUND_ROWS '.str_replace(' , ', ' ', implode(', ', $columns))."
                FROM {$table}
                {$sWhere}
                {$sOrder}
                {$sLimit}
            ";
            $wpdb->show_errors();
            $rResult = $wpdb->get_results($sQuery, ARRAY_A);

            // Data set length after filtering
            $sQuery = 'SELECT FOUND_ROWS()';
            $iFilteredTotal = $wpdb->get_var($sQuery);

            // Total data set length
            $sQuery = '
                SELECT COUNT('.$sIndexColumn.")
                FROM  {$table}
            ";
            $iTotal = $wpdb->get_var($sQuery);

            // Output
            $output = [
                'sEcho' => (int) ($_GET['sEcho']),
                'iTotalRecords' => $iTotal,
                'iTotalDisplayRecords' => $iFilteredTotal,
                'aaData' => [],
            ];

            foreach ($rResult as $aRow) {
                $row = [];
                for ($i = 0; $i < $column_count; ++$i) {
                    if ('aw_thumb' === $columns[$i]) {
                        $row[] = '<img src="'.$aRow[$columns[$i]].'" />';
                    } else {
                        $row[] = $aRow[$columns[$i]];
                    }
                }
                $row[] = "<a href='#' id='{$aRow['id']}' class='aw-prod-del'>Del</a>";
                $row['DT_RowId'] = 'row_'.$aRow['id'];
                $output['aaData'][] = $row;
            }

            echo json_encode($output);

            exit();
        }

        public function plugin_scripts(): void
        {
            wp_enqueue_script('jquery_datatables', plugins_url('js/jquery.dataTables.min.js', __FILE__));
            wp_enqueue_script('products_js', plugins_url('js/products.js', __FILE__));
        }

        public function plugin_styles(): void
        {
            wp_enqueue_style('products_css', plugins_url('css/products.css', __FILE__));
        }

        /**
         * Takes the arranged products from the grid shortcode
         * handler {@see AWINFeeder::scProductGrid()} and outputs the grid
         * showing the product, price and product name.
         *
         * @param int    $rows      number of rows to use
         * @param int    $col_count number of columns to use
         * @param string $cref      click ref to add to outgoing links
         *
         * @return string The full HTML grid for the shortcode
         *                handler to return to Wordpress for output
         */
        private function _buildScOutput($rows, $col_count, $cref)
        {
            if (strlen($cref) > 0) {
                $cref = '/'.$cref;
            }
            $awin_feeder_options = $this->getPluginOptions();

            switch ($col_count) {
                case 2:$width = '50%';

break;

                case 3:$width = '33%';

break;

                case 4:$width = '25%';

break;

                default:$width = '33%';
            }
            $output = '';
            $output .= '<table class="awf-prod-grid">';
            $output .= '<tr>';
            $i = 0;
            foreach ($rows as $row) {
                $thumb_image = $row->aw_thumb;

                $output .= sprintf('
                <td style="vertical-align:top;width:%s">
                    <a rel="nofollow" href="/hopo/%d%s"><img src="%s" alt="%s" /></a><br />
                    <a href="/hopo/%d%s" rel="nofollow">%s</a>
                </td>
                ', $width, $row->id, $cref, $thumb_image, $row->name, $row->id, $cref, $row->name);
                ++$i;
                if (0 === $i % $col_count) {
                    $output .= '</tr><tr>';
                }
            }
            $output .= '</tr>';
            $output .= '</table>';

            return $output;
        }
    }
}

register_activation_hook(__FILE__, 'awinfeeder_install');

if (class_exists('AwinFeeder')) {
    $awin_feeder = new AwinFeeder();
}

if (!function_exists('SetupAwinFeeder')) {
    /**
     * Initialises the admin menu.
     *
     * This is called after the "admin_menu" action is fired.
     */
    function SetupAwinFeeder(): void
    {
        global $awin_feeder;
        if (!isset($awin_feeder)) {
            return;
        }
        if (function_exists('add_menu_page')) {
            add_menu_page('AWIN Feeder', 'AWIN Feeder', 8, 'awin-feeder', [&$awin_feeder, 'printAdminPage']);
            $products_page = add_submenu_page('awin-feeder', 'Products', 'Products', 8, 'awin-products', [&$awin_feeder, 'printProductsList']);
            add_submenu_page('awin-feeder', 'Upload', 'Upload', 8, 'awin-upload', [&$awin_feeder, 'printUploadForm']);
        }
    }
}

if (isset($awin_feeder)) {
    add_action('admin_menu', 'SetupAwinFeeder');
    add_action('activate_awin-feeder/awin-feeder.php', [&$awin_feeder, 'init']);
    add_action('admin_print_scripts', [&$awin_feeder, 'plugin_scripts']);
    add_action('admin_print_styles', [&$awin_feeder, 'plugin_styles']);
    add_action('wp_ajax_aw_json_prod', [&$awin_feeder, 'jsonProducts']);
    add_action('wp_ajax_aw_del_prod', [&$awin_feeder, 'delProduct']);
    add_action('init', [&$awin_feeder, 'handleHop']);

    add_shortcode('aw-prodgrid', [&$awin_feeder, 'scProductGrid']);
    add_shortcode('aw-prodblock', [&$awin_feeder, 'scProductBlock']);
}

include_once __DIR__.'/widgets/awinfeeder_random.php';

include_once __DIR__.'/widgets/awinfeeder_cheapdear.php';
