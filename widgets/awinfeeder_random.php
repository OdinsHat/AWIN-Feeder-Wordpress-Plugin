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
        $sql = sprintf("SELECT * FROM %s ORDER BY RAND() LIMIT %d", $table, $limit);
        $wpdb->show_errors();
        $rows = $wpdb->get_results($sql, OBJECT_K);

        echo '<ul style="list-style:none;">';
        foreach($rows as $row){
            echo '<li>'.$row->name.'</li>';
        }
        echo '</ul>';

        echo $after_widget;
    }

    public function form($instance)
    {
        $title = 'Random Products';
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

add_action( 'widgets_init', create_function( '', 'register_widget("AwinFeeder_Random");' ) );

?>
