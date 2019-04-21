<?php
/*
Plugin Name: Example
Plugin URI: https://example.com/
Description: Generic description test
Version: 1.0.0
Author: Authors Name
Author URI: https://example.com
License: GPLv2 or later
Text Domain: example
*/

require_once('class-wp-updater.php');
(new WP_Updater('1', __DIR__, __FILE__));//->debug();
