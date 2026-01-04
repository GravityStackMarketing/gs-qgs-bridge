<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-rest-qgs.php';

add_action('rest_api_init', function () {
  GS_QGS_Bridge_REST::register_routes();
});
