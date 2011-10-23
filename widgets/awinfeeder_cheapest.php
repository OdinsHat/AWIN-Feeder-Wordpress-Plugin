<?php

class AwinFeeder_Cheapest extends WP_Widget
{
    function __construct()
    {
        parent::WP_Widget('awinfeeder_cheapest', 'AWIN Feeder Cheapest', 'Display cheapest products from plugin');
    }

    public function widget($args, $instance)
    {
        global $wpdb;
        $limit = 5;

        extract($args);
        $title = apply_filters('widget_title', $instance['title']);
        echo $before_widget;
        if($title){
            echo $before_title.$title.$after_title;
        }
        if($instance['count'] > 0){
            $limit = $instance['count'];
        }
        $table = $wpdb->prefix.'afeeder_products';
        $sql = sprintf("
            SELECT * 
            FROM %s 
            WHERE aw_thumb NOT LIKE '%%nothumb%%' 
                AND brand != '' 
                AND name != ''
            ORDER BY price ASC 
            LIMIT %d", $table, $limit
        );
        $wpdb->show_errors();
        $rows = $wpdb->get_results($sql, OBJECT_K);

        include_once ABSPATH.'/wp-content/plugins/awin-feeder/templates/widget_cheapest.php';

        echo $after_widget;
    }

    public function form($instance)
    {
        $title = 'Cheapest Products';
        $count = 5;
        if($instance){
            $title = esc_attr($instance['title']);
            $count = sprintf("%d", $instance['count']);
        }
        ?>
        <p>
        <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> 
        <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
        <label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('Count:'); ?></label> 
        <input class="widefat" id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>" type="text" value="<?php echo $count; ?>" />
        </p>
        <?php 
    }

}

add_action( 'widgets_init', create_function( '', 'register_widget("AwinFeeder_Cheapest");' ) );

?>