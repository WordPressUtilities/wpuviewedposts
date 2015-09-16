<?php

/*
Plugin Name: WPU Post views
Plugin URI: http://github.com/Darklg/WPUtilities
Description: Track most viewed posts
Version: 0.4
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUPostViews {
    public $options;
    function __construct() {
        add_action('init', array(&$this,
            'load_plugin_textdomain'
        ));
        add_action('init', array(&$this,
            'set_options'
        ));
        add_action('wp_enqueue_scripts', array(&$this,
            'js_callback'
        ));
        add_action('wp_footer', array(&$this,
            'image_callback'
        ));
        add_action('wp_ajax_wpupostviews_track_view', array(&$this,
            'track_view'
        ));
        add_action('wp_ajax_nopriv_wpupostviews_track_view', array(&$this,
            'track_view'
        ));
        add_action('admin_menu', array(&$this,
            'admin_menu'
        ));
        add_action('admin_init', array(&$this,
            'add_settings'
        ));
        add_filter("plugin_action_links_" . plugin_basename(__FILE__) , array(&$this,
            'add_settings_link'
        ));
        add_action('add_meta_boxes', array(&$this,
            'add_meta_box'
        ));
        add_action('save_post', array(&$this,
            'save_meta_box_data'
        ));
    }

    function load_plugin_textdomain() {
        load_plugin_textdomain('wpupostviews', false, dirname(plugin_basename(__FILE__)) . '/lang/');
    }

    function set_options() {
        $this->options = array(
            'plugin_publicname' => 'Post views',
            'plugin_name' => 'Post views',
            'plugin_userlevel' => 'manage_options',
            'plugin_id' => 'wpupostviews',
            'plugin_pageslug' => 'wpupostviews',
        );
        $this->settings_details = array(
            'option_id' => 'wpupostviews_options',
            'sections' => array(
                'cookie' => array(
                    'name' => __('Cookie', 'wpupostviews')
                ) ,
                'tracking' => array(
                    'name' => __('Tracking', 'wpupostviews')
                )
            )
        );
        $this->settings = array(
            'use_cookie' => array(
                'label' => __('Use a cookie', 'wpupostviews') ,
                'label_check' => __('Use a cookie to count only one view per visitor per post.', 'wpupostviews') ,
                'type' => 'checkbox'
            ) ,
            'cookie_days' => array(
                'label' => __('Expiration (in days)', 'wpupostviews') ,
                'type' => 'number'
            ) ,
            'no_bots' => array(
                'section' => 'tracking',
                'label' => __('Dont count bots', 'wpupostviews') ,
                'label_check' => __('Do not track views from bots and crawlers', 'wpupostviews') ,
                'type' => 'checkbox'
            ) ,
        );
    }

    /* ----------------------------------------------------------
      Admin
    ---------------------------------------------------------- */

    /* Settings link */

    function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=' . $this->options['plugin_id']) . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /* Menu */

    function admin_menu() {
        add_options_page($this->options['plugin_name'] . ' - ' . __('Settings') , $this->options['plugin_publicname'], $this->options['plugin_userlevel'], $this->options['plugin_pageslug'], array(&$this,
            'admin_settings'
        ) , '', 110);
    }

    /* Settings */

    function admin_settings() {
        echo '<div class="wrap"><h1>' . get_admin_page_title() . '</h1>';

        $nb_top_posts = 10;
        $top_posts = get_posts(array(
            'posts_per_page' => $nb_top_posts,
            'meta_key' => 'wpupostviews_nbviews',
            'orderby' => 'meta_value_num',
        ));
        if (!empty($top_posts)) {
            echo '<hr />';
            echo '<h2>' . sprintf(__('Top %s posts', 'wpupostviews') , $nb_top_posts) . '</h2>';
            echo '<ol>';
            foreach ($top_posts as $tp) {
                echo '<li><a href="' . get_edit_post_link($tp->ID) . '"><strong>' . esc_attr($tp->post_title) . '</strong></a> (' . get_post_meta($tp->ID, 'wpupostviews_nbviews', 1) . ')</li>';
            }
            echo '</ol>';
        }
        echo '<hr />';
        echo '<h2>' . __('Settings') . '</h2>';
        echo '<form action="options.php" method="post">';
        settings_fields($this->settings_details['option_id']);
        do_settings_sections($this->options['plugin_id']);
        echo submit_button(__('Save Changes', 'wpupostviews'));
        echo '</form>';
        echo '</div>';
    }

    function add_settings() {
        register_setting($this->settings_details['option_id'], $this->settings_details['option_id'], array(&$this,
            'options_validate'
        ));
        $default_section = key($this->settings_details['sections']);
        foreach ($this->settings_details['sections'] as $id => $section) {
            add_settings_section($id, $section['name'], '', $this->options['plugin_id']);
        }

        foreach ($this->settings as $id => $input) {
            $label = isset($input['label']) ? $input['label'] : '';
            $label_check = isset($input['label_check']) ? $input['label_check'] : '';
            $type = isset($input['type']) ? $input['type'] : 'text';
            $section = isset($input['section']) ? $input['section'] : $default_section;
            add_settings_field($id, $label, array(&$this,
                'render__field'
            ) , $this->options['plugin_id'], $section, array(
                'name' => 'wpupostviews_options[' . $id . ']',
                'id' => $id,
                'label_for' => $id,
                'type' => $type,
                'label_check' => $label_check
            ));
        }
    }

    function options_validate($input) {
        $options = get_option($this->settings_details['option_id']);
        foreach ($this->settings as $id => $name) {
            $options[$id] = esc_html(trim($input[$id]));
        }
        return $options;
    }

    function render__field($args = array()) {
        $options = get_option($this->settings_details['option_id']);
        $label_check = isset($args['label_check']) ? $args['label_check'] : '';
        $type = isset($args['type']) ? $args['type'] : 'text';
        $name = ' name="wpupostviews_options[' . $args['id'] . ']" ';
        $id = ' id="' . $args['id'] . '" ';

        switch ($type) {
            case 'checkbox':
                echo '<label><input type="checkbox" ' . $name . ' ' . $id . ' ' . checked($options[$args['id']], '1', 0) . ' value="1" /> ' . $label_check . '</label>';
            break;
            default:
                echo '<input ' . $name . ' ' . $id . ' type="' . $type . '" value="' . esc_attr($options[$args['id']]) . '" />';
        }
    }

    /* ----------------------------------------------------------
      Meta box
    ---------------------------------------------------------- */

    function add_meta_box() {
        add_meta_box('wpupostviews_sectionid', $this->options['plugin_publicname'], array(&$this,
            'meta_box_callback'
        ) , 'post', 'side');
    }

    function meta_box_callback($post) {

        // Add a nonce field so we can check for it later.
        wp_nonce_field('wpupostviews_save_meta_box_data', 'wpupostviews_meta_box_nonce');

        $value = get_post_meta($post->ID, 'wpupostviews_nbviews', true);

        echo '<label for="wpupostviews_nbviews">' . __('Number of views', 'wpupostviews') . ' : </label><br />';
        echo '<input type="number" id="wpupostviews_nbviews" name="wpupostviews_nbviews" value="' . esc_attr($value) . '" />';
    }

    function save_meta_box_data($post_id) {

        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !isset($_POST['wpupostviews_meta_box_nonce']) || !wp_verify_nonce($_POST['wpupostviews_meta_box_nonce'], 'wpupostviews_save_meta_box_data')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!isset($_POST['wpupostviews_nbviews']) || !is_numeric($_POST['wpupostviews_nbviews'])) {
            return;
        }

        $my_data = sanitize_text_field($_POST['wpupostviews_nbviews']);

        update_post_meta($post_id, 'wpupostviews_nbviews', $my_data);
    }

    /* ----------------------------------------------------------
      Tracking
    ---------------------------------------------------------- */

    function js_callback() {
        if (!is_singular()) {
            return;
        }

        $options = get_option($this->settings_details['option_id']);
        $script_settings = array(
            'ajax_url' => admin_url('admin-ajax.php') ,
            'post_id' => get_the_ID() ,
            'cookie_days' => isset($options['cookie_days']) ? $options['cookie_days'] : '10',
            'use_cookie' => (isset($options['use_cookie']) && $options['use_cookie'] == '1') ? '1' : '0',
            'no_bots' => (isset($options['no_bots']) && $options['no_bots'] == '1') ? '1' : '0',
        );

        wp_enqueue_script('wpupostviews-tracker', plugins_url('/assets/js/tracker.js', __FILE__) , array(
            'jquery'
        ));

        wp_localize_script('wpupostviews-tracker', 'ajax_object', $script_settings);
    }

    function track_view() {
        $post_id = intval($_POST['post_id']);
        if (!is_numeric($post_id)) {
            return;
        }
        $nb_views = intval(get_post_meta($post_id, 'wpupostviews_nbviews', 1));
        update_post_meta($post_id, 'wpupostviews_nbviews', ++$nb_views);
        $orig_nb_views = intval(get_post_meta($post_id, 'wpupostviews_orig_nbviews', 1));
        update_post_meta($post_id, 'wpupostviews_orig_nbviews', ++$orig_nb_views);
        wp_die();
    }
}

$WPUPostViews = new WPUPostViews();
