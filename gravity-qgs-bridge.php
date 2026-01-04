<?php
/**
 * Plugin Name: Gravity QGS Bridge
 * Description: Receives Quick GravityScore submissions from the marketing site and stores/associates them in Gravity Hub.
 * Version: 0.4.0
 * Author: GravityStack
 */

if (!defined('ABSPATH')) exit;

define('GS_QGS_BRIDGE_VERSION', '0.4.0');

require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-jobs.php';

register_activation_hook(__FILE__, function () {
  GS_QGS_Bridge_DB::maybe_create_table();
});

register_deactivation_hook(__FILE__, function () {
  if (class_exists('GS_QGS_Bridge_Jobs')) {
    GS_QGS_Bridge_Jobs::clear_schedule();
  }
});

require_once __DIR__ . '/includes/bootstrap.php';
