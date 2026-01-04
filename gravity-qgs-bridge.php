<?php
/**
 * Plugin Name: Gravity QGS Bridge
 * Description: Receives Quick GravityScore submissions from the marketing site and stores/associates them in Gravity Hub.
 * Version: 0.2.0
 * Author: GravityStack
 */

if (!defined('ABSPATH')) exit;

define('GS_QGS_BRIDGE_VERSION', '0.2.0');

require_once __DIR__ . '/includes/class-db.php';

register_activation_hook(__FILE__, function () {
  GS_QGS_Bridge_DB::maybe_create_table();
});

require_once __DIR__ . '/includes/bootstrap.php';
