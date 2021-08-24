<?php declare(strict_types=1);

class awinfeeder_cheapdear extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('awinfeeder_cheapest', 'AWIN Feeder Cheapest', 'Display cheapest products from plugin');
    }

    /**
     * The extended widget method that plls together the data for the Cheap/Dear
     * Widget before outputing it by inclusion.
     *
     * @param array $args     Array arguments
     * @param array $instance Instance
     */
    public function widget($args, $instance): void
    {
        global $wpdb;
        $limit = 5;
        $order = 'ASC';

        extract($args);
        $title = apply_filters('widget_title', $instance['title']);
        echo $before_widget;
        if ($title) {
            echo $before_title.$title.$after_title;
        }
        if ($instance['count'] > 0) {
            $limit = $instance['count'];
        }
        if (isset($instance['order'])) {
            $order = $instance['order'];
        }

        $table = $wpdb->prefix.'afeeder_products';
        $sql = sprintf(
            "SELECT price, name, brand, id, aw_thumb
            FROM %s
            WHERE aw_thumb NOT LIKE '%%nothumb%%'
                AND brand != ''
                AND name != ''
            GROUP BY name
            ORDER BY price %s
            LIMIT %d",
            $table,
            $order,
            $limit
        );
        $wpdb->show_errors();
        $rows = $wpdb->get_results($sql, OBJECT_K);

        include_once ABSPATH.'/wp-content/plugins/awin-feeder/templates/widget_cheapest.php';

        echo $after_widget;
    }

    /**
     * Outputs the form for the confirguration of the widget in the Wordpress backend.
     *
     * @param array $instance instance
     */
    public function form($instance): void
    {
        $title = 'Cheapest Products';
        $count = 5;
        $order = 'ASC';
        if ($instance) {
            $title = esc_attr($instance['title']);
            $count = sprintf('%d', $instance['count']);
            $order = esc_attr($instance['order']);
        } ?>
        <p>
        <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
        <label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('Count:'); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>" type="text" value="<?php echo $count; ?>" />
        <label for="<?php echo $this->get_field_id('order'); ?>"><?php _e('Order:'); ?></label>
        <select id="<?php echo $this->get_field_id('order'); ?>" name="<?php echo $this->get_field_name('order'); ?>">
            <option value="ASC"<?php echo ('ASC' === $order) ? ' selected' : ''; ?>>Cheapest</option>
            <option value="DESC"<?php echo ('DESC' === $order) ? ' selected' : ''; ?>>Dearest</option>
        </select>
        </p>
        <?php
    }
}

add_action('widgets_init', create_function('', 'register_widget("AwinFeeder_Cheapest");'));

?>
