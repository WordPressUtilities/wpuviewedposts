<?php
defined('ABSPATH') || die;
if (!defined('WP_UNINSTALL_PLUGIN')) exit();

require_once dirname( __FILE__ ).'/wpupostviews.php';

$WPUPostViews->uninstall();
