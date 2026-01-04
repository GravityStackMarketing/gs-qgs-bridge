<?php
if (!defined('ABSPATH')) exit;

class GS_QGS_Bridge_REST {

  public static function register_routes(): void {
    register_rest_route('gravity/v1', '/qgs', [
      'methods'  => 'POST',
      'callback' => [__CLASS__, 'ingest'],
      'permission_callback' => '__return_true', // HMAC is the gate
    ]);
  }

  public static function ingest(WP_REST_Request $request): WP_REST_Response {

    // Requires GS_QGS_SHARED_SECRET to be defined on Hub (wp-config.php).
    if (!defined('GS_QGS_SHARED_SECRET') || GS_QGS_SHARED_SECRET === '') {
      return new WP_REST_Response([
        'success' => false,
        'message' => 'Server not configured (missing GS_QGS_SHARED_SECRET).'
      ], 500);
    }

    $timestamp = (string) $request->get_header('x-gs-timestamp');
    $sig       = (string) $request->get_header('x-gs-signature');

    if ($timestamp === '' || $sig === '') {
      return new WP_REST_Response([
        'success' => false,
        'message' => 'Missing auth headers.'
      ], 401);
    }

    // 5-minute replay window
    $now = time();
    if (abs($now - (int) $timestamp) > 300) {
      return new WP_REST_Response([
        'success' => false,
        'message' => 'Expired request.'
      ], 401);
    }

    // Validate signature against the exact raw body.
    $raw_body = $request->get_body();
    $expected = hash_hmac('sha256', $timestamp . '.' . $raw_body, GS_QGS_SHARED_SECRET);

    if (!hash_equals($expected, $sig)) {
      return new WP_REST_Response([
        'success' => false,
        'message' => 'Bad signature.'
      ], 401);
    }

    // Basic JSON sanity check (optional at this stage)
    $payload = json_decode($raw_body, true);
    if (!is_array($payload)) {
      return new WP_REST_Response([
        'success' => false,
        'message' => 'Invalid JSON.'
      ], 400);
    }

    // Handshake success (no DB yet)
    return new WP_REST_Response([
      'success' => true,
      'message' => 'HMAC validated.',
      'received' => [
        'submission_id' => $payload['submission_id'] ?? null,
        'email'         => $payload['email'] ?? null,
      ]
    ], 200);
  }
}
