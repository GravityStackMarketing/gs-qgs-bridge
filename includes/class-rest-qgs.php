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

    $payload = json_decode($raw_body, true);
    if (!is_array($payload)) {
      return new WP_REST_Response([
        'success' => false,
        'message' => 'Invalid JSON.'
      ], 400);
    }

    // --- Validate required fields ---
    $submission_id = isset($payload['submission_id']) ? trim((string) $payload['submission_id']) : '';
    $email_raw     = isset($payload['email']) ? trim((string) $payload['email']) : '';
    $submitted_at  = isset($payload['submitted_at']) ? trim((string) $payload['submitted_at']) : '';

    if ($submission_id === '' || $email_raw === '' || $submitted_at === '') {
      return new WP_REST_Response([
        'success' => false,
        'message' => 'Missing required fields (submission_id, email, submitted_at).'
      ], 400);
    }

    // Normalise email: trim + lowercase only
    $email_norm = strtolower(trim($email_raw));

    // Optional fields
    $name            = isset($payload['name']) ? trim((string) $payload['name']) : null;
    $scoring_version = isset($payload['scoring_version']) ? trim((string) $payload['scoring_version']) : null;
    $source          = isset($payload['source']) ? trim((string) $payload['source']) : null;

    // Letter grades (A/B/C/D)
    $gravityscore = isset($payload['gravityscore_grade']) ? trim((string) $payload['gravityscore_grade']) : null;
    $strategy     = isset($payload['strategy_grade']) ? trim((string) $payload['strategy_grade']) : null;
    $funnel       = isset($payload['funnel_grade']) ? trim((string) $payload['funnel_grade']) : null;
    $traffic      = isset($payload['traffic_grade']) ? trim((string) $payload['traffic_grade']) : null;

    // --- Idempotency check ---
    $existing = GS_QGS_Bridge_DB::get_by_submission_id($submission_id);
    if ($existing) {
      return new WP_REST_Response([
        'success'   => true,
        'duplicate' => true,
        'message'   => 'Already received.',
        'received'  => [
          'submission_id' => $submission_id,
          'email'         => $email_norm,
        ],
      ], 200);
    }

    // --- Insert (initially pending; association may happen below) ---
    $insert = GS_QGS_Bridge_DB::insert_submission([
      'submission_id'      => $submission_id,
      'email_normalised'   => $email_norm,
      'name'               => ($name !== '' ? $name : null),
      'submitted_at'       => $submitted_at,
      'scoring_version'    => ($scoring_version !== '' ? $scoring_version : null),
      'source'             => ($source !== '' ? $source : null),
      'gravityscore_grade' => ($gravityscore !== '' ? $gravityscore : null),
      'strategy_grade'     => ($strategy !== '' ? $strategy : null),
      'funnel_grade'       => ($funnel !== '' ? $funnel : null),
      'traffic_grade'      => ($traffic !== '' ? $traffic : null),
      'status'             => GS_QGS_Bridge_DB::STATUS_PENDING,
      'user_id'            => null,
    ]);

    if (empty($insert['ok'])) {
      return new WP_REST_Response([
        'success' => false,
        'message' => 'DB insert failed.',
        'error'   => $insert['error'] ?? 'unknown',
      ], 500);
    }

    $insert_id = (int) ($insert['id'] ?? 0);

    // --- Attempt association immediately ---
    $associated = false;
    $user_id = 0;

    if ($insert_id > 0 && class_exists('GS_QGS_Bridge_Association')) {
      $user_id = (int) GS_QGS_Bridge_Association::find_user_id_by_email($email_norm);

      if ($user_id > 0) {
        // Mark associated in DB
        if (method_exists('GS_QGS_Bridge_DB', 'mark_associated')) {
          GS_QGS_Bridge_DB::mark_associated($insert_id, $user_id);
        }

        // Update ACF latest snapshot (only if newer)
        GS_QGS_Bridge_Association::maybe_update_acf_latest($user_id, [
          'submission_id'      => $submission_id,
          'submitted_at'       => $submitted_at,
          'scoring_version'    => $scoring_version,
          'source'             => $source,
          'name'               => $name,
          'gravityscore_grade' => $gravityscore,
          'strategy_grade'     => $strategy,
          'funnel_grade'       => $funnel,
          'traffic_grade'      => $traffic,
        ]);

        $associated = true;
      }
    }

    return new WP_REST_Response([
      'success'    => true,
      'duplicate'  => false,
      'message'    => 'Stored.',
      'received'   => [
        'submission_id' => $submission_id,
        'email'         => $email_norm,
      ],
      'associated' => $associated,
      'user_id'    => $user_id ?: null,
    ], 200);
  }
}
