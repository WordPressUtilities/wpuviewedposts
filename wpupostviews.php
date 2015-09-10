<?php

/*
Plugin Name: WPU post views
Plugin URI: http://github.com/Darklg/WPUtilities
Description: Track most viewed posts
Version: 0.2
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUPostViews {
    public $options;
    function __construct() {

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
                'tracking' => array(
                    'name' => 'Tracking'
                )
            )
        );
        $this->settings = array(
            'use_cookie' => array(
                'label' => 'Use a cookie',
                'label_check' => 'Use a cookie to count only one view per visitor per post.',
                'type' => 'checkbox'
            ) ,
            'cookie_days' => array(
                'label' => 'Cookies expiration (in days)',
                'type' => 'number'
            )
        );
    }

    /* ----------------------------------------------------------
      Admin
    ---------------------------------------------------------- */

    /* Menu */

    function admin_menu() {
        add_options_page($this->options['plugin_name'] . ' Settings', $this->options['plugin_publicname'], $this->options['plugin_userlevel'], $this->options['plugin_pageslug'], array(&$this,
            'admin_settings'
        ) , '', 110);
    }

    /* Settings */

    function admin_settings() {
        echo '<div class="wrap"><h2>' . get_admin_page_title() . '</h2>';
        echo '<form action="options.php" method="post">';
        settings_fields($this->settings_details['option_id']);
        do_settings_sections($this->options['plugin_id']);
        echo submit_button(__('Save Changes'));
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
        wp_die();
    }
}

$WPUPostViews = new WPUPostViews();
