<?php

class AwinFeeder_Random extends WP_Widget
{
    function __construct()
    {
        parent::WP_Widget('awinfeeder_random', 'AWIN Feeder Random', 'Display random products from the AWIN Feeder plugin');
    }

    public function widget($args, $instance)
    {
        global $wpdb;
        $limit = 5;
        $wheres = array();
        $where_stirng = '';

        extract($args);
        $title = apply_filters('widget_title', $instance['title']);
        echo $before_widget;
        if($title){
            echo $before_title.$title.$after_title;
        }
        if($instance['count'] > 0){
            $limit = $instance['count'];
        }
        if($instance['brand']){
            $wheres[] = sprintf('brand = "%s"', $instance['brand']);
        }
        if(count($wheres) > 0){
            $where_string = ' AND '.implode(' AND ', $wheres);
        }
        $table = $wpdb->prefix.'afeeder_products';
        $sql = sprintf("SELECT name, id, price, brand FROM %s WHERE name != '' %s GROUP BY name ORDER BY RAND() LIMIT %d", $table, $where_string, $limit);
        $wpdb->show_errors();
        $rows = $wpdb->get_results($sql, OBJECT_K);

        include_once ABSPATH.'/wp-content/plugins/awin-feeder/templates/widget_random.php';

        echo $after_widget;
    }

    public function form($instance)
    {
        $title = 'Random Products';
        $count = 5;
        if($instance){
            $title = esc_attr($instance['title']);
            $count = sprintf("%d", $instance['count']);
            $brand = esc_attr($instance['brand']);
        }
        ?>
        <p>
        <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> 
        <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
        <label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('Count:'); ?></label> 
        <input class="widefat" id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>" type="text" value="<?php echo $count; ?>" />
        <label for="<?php echo $this->get_field_id('brand'); ?>"><?php _e('Brand:'); ?></label> 
        <input class="widefat" id="<?php echo $this->get_field_id('brand'); ?>" name="<?php echo $this->get_field_name('brand'); ?>" type="text" value="<?php echo $brand; ?>" />
        </p>
        <?php 
    }

}

add_action( 'widgets_init', create_function( '', 'register_widget("AwinFeeder_Random");' ) );

?>
