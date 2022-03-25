<?php

/*
Plugin Name: WPU Post views
Plugin URI: http://github.com/Darklg/WPUtilities
Description: Track most viewed posts
Version: 0.10.3
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUPostViews {
    public $plugin_version = '0.10.3';
    public $options;
    public function __construct() {
        add_action('plugins_loaded', array(&$this,
            'load_extras'
        ));
        add_action('plugins_loaded', array(&$this,
            'set_options'
        ));
        add_action('plugins_loaded', array(&$this,
            'bypass_admin_ajax'
        ));
        add_action('wp_enqueue_scripts', array(&$this,
            'js_callback'
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
        add_filter("plugin_action_links_" . plugin_basename(__FILE__), array(&$this,
            'add_settings_link'
        ));
        add_action('add_meta_boxes', array(&$this,
            'add_meta_box'
        ));
        add_action('save_post', array(&$this,
            'save_meta_box_data'
        ));
    }

    public function load_extras() {
        load_plugin_textdomain('wpupostviews', false, dirname(plugin_basename(__FILE__)) . '/lang/');

        include 'inc/WPUBaseUpdate/WPUBaseUpdate.php';
        $this->settings_update = new \wpupostviews\WPUBaseUpdate(
            'WordPressUtilities',
            'wpupostviews',
            $this->plugin_version);
    }

    public function set_options() {
        $this->options = array(
            'plugin_publicname' => 'Post views',
            'plugin_name' => 'Post views',
            'plugin_userlevel' => 'manage_options',
            'plugin_id' => 'wpupostviews',
            'plugin_pageslug' => 'wpupostviews',
            'post_types' => apply_filters('wpupostviews_post_types', array('post')),
            'default_top' => apply_filters('wpupostviews_default_top', 10)
        );
        $this->options['admin_url'] = admin_url('options-general.php?page=' . $this->options['plugin_id']);
        $this->settings_details = array(
            'plugin_id' => 'wpupostviews',
            'option_id' => 'wpupostviews_options',
            'sections' => array(
                'cookie' => array(
                    'name' => __('Cookie', 'wpupostviews')
                ),
                'tracking' => array(
                    'name' => __('Tracking', 'wpupostviews')
                )
            )
        );
        $this->settings = array(
            'use_cookie' => array(
                'label' => __('Use a cookie', 'wpupostviews'),
                'label_check' => __('Use a cookie to count only one view per visitor per post.', 'wpupostviews'),
                'type' => 'checkbox'
            ),
            'cookie_days' => array(
                'label' => __('Expiration (in days)', 'wpupostviews'),
                'type' => 'number'
            ),
            'no_loggedin' => array(
                'section' => 'tracking',
                'label' => __('Ignore logged in users', 'wpupostviews'),
                'label_check' => __('Do not track views by logged in users.', 'wpupostviews'),
                'type' => 'checkbox'
            ),
            'no_bots' => array(
                'section' => 'tracking',
                'label' => __('Dont count bots', 'wpupostviews'),
                'label_check' => __('Do not track views from bots and crawlers.', 'wpupostviews'),
                'type' => 'checkbox'
            )
        );

        if (is_admin()) {
            include 'inc/WPUBaseSettings/WPUBaseSettings.php';
            $settings_obj = new \wpupostviews\WPUBaseSettings($this->settings_details, $this->settings);

            ## if no auto create_page and medias ##
            if (isset($_GET['page']) && $_GET['page'] == 'wpupostviews') {
                add_action('admin_init', array(&$settings_obj, 'load_assets'));
            }
        }
    }

    public function bypass_admin_ajax() {
        if ($_SERVER["SCRIPT_NAME"] != '/wp-admin/admin-ajax.php') {
            return;
        }
        if (empty($_POST) || !isset($_POST['action']) || $_POST['action'] != 'wpupostviews_track_view') {
            return;
        }
        $this->track_view();
    }

    /* ----------------------------------------------------------
      Datas
    ---------------------------------------------------------- */

    public function get_top_posts($nb = false, $orig = false) {
        if ($nb == false) {
            $nb = $this->options['default_top'];
        }
        return get_posts(array(
            'posts_per_page' => $nb,
            'post_type' => $this->options['post_types'],
            'meta_key' => 'wpupostviews_' . ($orig ? 'orig_' : '') . 'nbviews',
            'orderby' => 'meta_value_num meta_value date'
        ));
    }

    /* ----------------------------------------------------------
      Admin
    ---------------------------------------------------------- */

    /* Settings link */

    public function add_settings_link($links) {
        $settings_link = '<a href="' . $this->options['admin_url'] . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /* Menu */

    public function admin_menu() {
        add_options_page($this->options['plugin_name'] . ' - ' . __('Settings'), $this->options['plugin_publicname'], $this->options['plugin_userlevel'], $this->options['plugin_pageslug'], array(&$this,
            'admin_settings'
        ), 110);
    }

    /* Settings */

    public function admin_settings() {
        echo '<div class="wrap"><h1>' . get_admin_page_title() . '</h1>';

        $count_methods = array(
            'public' => array(
                'name' => __('Public top %s posts', 'wpupostviews'),
                'orig' => false
            ),
            'orig' => array(
                'name' => __('Real top %s posts', 'wpupostviews'),
                'orig' => true
            )
        );

        foreach ($count_methods as $method) {
            $top_posts = $this->get_top_posts(false, $method['orig']);

            if (!empty($top_posts)) {
                echo '<hr />';
                echo '<h2>' . sprintf($method['name'], $this->options['default_top']) . '</h2>';
                echo '<ol>';
                foreach ($top_posts as $tp) {
                    echo '<li><a href="' . get_edit_post_link($tp->ID) . '"><strong>' . esc_attr($tp->post_title) . '</strong></a> (' . get_post_meta($tp->ID, 'wpupostviews_' . ($method['orig'] ? 'orig_' : '') . 'nbviews', 1) . ')</li>';
                }
                echo '</ol>';
            }
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

    /* ----------------------------------------------------------
      Meta box
    ---------------------------------------------------------- */

    public function add_meta_box() {
        if (!current_user_can(apply_filters('wpupostviews_min_level', 'delete_private_pages'))) {
            return;
        }
        add_meta_box('wpupostviews_sectionid', $this->options['plugin_publicname'], array(&$this,
            'meta_box_callback'
        ), $this->options['post_types'], 'side');
    }

    public function meta_box_callback($post) {
        $wpupostviews_nbviews = get_post_meta($post->ID, 'wpupostviews_nbviews', true);
        if (!$wpupostviews_nbviews) {
            $wpupostviews_nbviews = 0;
        }
        $wpupostviews_orig_nbviews = get_post_meta($post->ID, 'wpupostviews_orig_nbviews', true);
        $real_str = '';
        if ($wpupostviews_orig_nbviews != $wpupostviews_nbviews) {
            $real_str = '<br /><small>(' . sprintf(__('Real number: %s', 'wpupostviews'), '<strong>' . $wpupostviews_orig_nbviews . '</strong>') . ')</small>';
        }
        wp_nonce_field('wpupostviews_save_meta_box_data', 'wpupostviews_meta_box_nonce');
        echo '<label for="wpupostviews_nbviews">' . __('Number of views', 'wpupostviews') . ' : </label><br />';
        echo '<input type="number" onchange="document.getElementById(\'wpupostviews_update_count_wrapper\').style.display=\'block\';document.getElementById(\'wpupostviews_update_count\').checked=true" id="wpupostviews_nbviews" name="wpupostviews_nbviews" value="' . esc_attr($wpupostviews_nbviews) . '" />';
        echo $real_str;
        echo '<p id="wpupostviews_update_count_wrapper" style="display:none;">';
        echo '<label for="wpupostviews_update_count">';
        echo '<input type="checkbox" id="wpupostviews_update_count" name="wpupostviews_update_count" value="1" />';
        echo __('Update counter', 'wpupostviews') . '</label>';
        echo '</p>';
        echo '<p>';
        echo '<label for="wpupostviews_dntviews">';
        echo '<input type="checkbox" id="wpupostviews_dntviews" name="wpupostviews_dntviews" value="1" ' . checked(get_post_meta($post->ID, 'wpupostviews_dntviews', true), '1', 0) . ' />';
        echo __('Do not track views for this post', 'wpupostviews') . '</label>';
        echo '</p>';

        echo '<div><small><a href="' . $this->options['admin_url'] . '">→ ' . sprintf(__('Top %s posts', 'wpupostviews'), $this->options['default_top']) . '</a></small></div>';
    }

    public function save_meta_box_data($post_id) {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !isset($_POST['wpupostviews_meta_box_nonce']) || !wp_verify_nonce($_POST['wpupostviews_meta_box_nonce'], 'wpupostviews_save_meta_box_data')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Do not track views
        update_post_meta($post_id, 'wpupostviews_dntviews', (isset($_POST['wpupostviews_dntviews']) && $_POST['wpupostviews_dntviews'] == 1) ? '1' : '0');

        // Number of views
        if (isset($_POST['wpupostviews_nbviews'], $_POST['wpupostviews_update_count']) && is_numeric($_POST['wpupostviews_nbviews']) && is_numeric($_POST['wpupostviews_update_count'])) {
            update_post_meta($post_id, 'wpupostviews_nbviews', sanitize_text_field($_POST['wpupostviews_nbviews']));
        }
    }

    /* ----------------------------------------------------------
      Tracking
    ---------------------------------------------------------- */

    public function js_callback() {
        if (!is_singular() || is_home() || is_front_page()) {
            return;
        }

        if (!in_array(get_post_type(), $this->options['post_types'])) {
            return;
        }

        $dntviews = get_post_meta(get_the_ID(), 'wpupostviews_dntviews', 1);
        if ($dntviews == '1') {
            return;
        }

        $options = get_option($this->settings_details['option_id']);
        $script_settings = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_id' => get_the_ID(),
            'dntviews' => $dntviews,
            'cookie_days' => isset($options['cookie_days']) && is_numeric($options['cookie_days']) ? $options['cookie_days'] : apply_filters('wpupostviews_default_cookie_days', 10),
            'use_cookie' => (isset($options['use_cookie']) && $options['use_cookie'] == '1') ? '1' : '0',
            'no_bots' => (isset($options['no_bots']) && $options['no_bots'] == '1') ? '1' : '0'
        );

        wp_enqueue_script('wpupostviews-tracker', plugins_url('/assets/js/tracker.js', __FILE__), array(
            'jquery'
        ), $this->plugin_version, 1);

        wp_localize_script('wpupostviews-tracker', 'wpupostviews_object', $script_settings);
    }

    public function track_view() {
        $no_loggedin = (isset($options['no_loggedin']) && $options['no_loggedin'] == '1');
        $post_id = intval($_POST['post_id']);
        if (!is_numeric($post_id)) {
            return;
        }
        if ($no_loggedin && is_user_logged_in()) {
            return;
        }
        if (get_post_meta($post_id, 'wpupostviews_dntviews', 1) == '1') {
            return;
        }
        $nb_views = intval(get_post_meta($post_id, 'wpupostviews_nbviews', 1));
        update_post_meta($post_id, 'wpupostviews_nbviews', ++$nb_views);
        $orig_nb_views = intval(get_post_meta($post_id, 'wpupostviews_orig_nbviews', 1));
        update_post_meta($post_id, 'wpupostviews_orig_nbviews', ++$orig_nb_views);
        wp_die();
    }

    /* Uninstall */

    public function uninstall() {
        delete_option('wpupostviews_options');
        delete_post_meta_by_key('wpupostviews_nbviews');
        delete_post_meta_by_key('wpupostviews_orig_nbviews');
    }
}

$WPUPostViews = new WPUPostViews();
