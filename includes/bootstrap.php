<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-rest-qgs.php';

register_activation_hook(dirname(__DIR__) . '/gravity-qgs-bridge.php', function () {
  GS_QGS_Bridge_DB::maybe_create_table();
});

add_action('rest_api_init', function () {
  GS_QGS_Bridge_REST::register_routes();
});
