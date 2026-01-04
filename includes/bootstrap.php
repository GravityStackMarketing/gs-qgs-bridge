<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-db.php';
require_once __DIR__ . '/class-association.php';
require_once __DIR__ . '/class-rest-qgs.php';
require_once __DIR__ . '/class-jobs.php';
require_once __DIR__ . '/class-admin-tools.php';

add_filter('cron_schedules', function($schedules) {
  if (!isset($schedules['gs_qgs_15min'])) {
    $schedules['gs_qgs_15min'] = [
      'interval' => 15 * 60,
      'display'  => 'Every 15 minutes (GS QGS Bridge)',
    ];
  }
  return $schedules;
});

add_action('rest_api_init', function () {
  GS_QGS_Bridge_REST::register_routes();
});

GS_QGS_Bridge_Jobs::init();
GS_QGS_Bridge_Admin_Tools::init();
