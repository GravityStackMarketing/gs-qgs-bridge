<?php
if (!defined('ABSPATH')) exit;

class GS_QGS_Bridge_Association {

  public static function normalise_email(string $email): string {
    return strtolower(trim($email));
  }

  public static function find_user_id_by_email(string $email_norm): int {
    $user = get_user_by('email', $email_norm);
    return $user ? (int) $user->ID : 0;
  }

  public static function maybe_update_acf_latest(int $user_id, array $payload): void {
    // Compare against existing latest_at
    $existing_latest = self::get_user_field('gs_qgs_latest_at', $user_id);

    $incoming = isset($payload['submitted_at']) ? trim((string)$payload['submitted_at']) : '';
    if ($incoming === '') return;

    // If existing is set and incoming is not newer, skip update.
    if ($existing_latest) {
      $existing_ts = strtotime($existing_latest);
      $incoming_ts = strtotime($incoming);
      if ($existing_ts !== false && $incoming_ts !== false && $incoming_ts <= $existing_ts) {
        return;
      }
    }

    self::set_user_field('gs_qgs_latest_submission_id', $user_id, $payload['submission_id'] ?? '');
    self::set_user_field('gs_qgs_latest_at',           $user_id, $incoming);
    self::set_user_field('gs_qgs_scoring_version',     $user_id, $payload['scoring_version'] ?? '');
    self::set_user_field('gs_qgs_source',              $user_id, $payload['source'] ?? '');

    self::set_user_field('gs_qgs_name',                $user_id, $payload['name'] ?? '');

    self::set_user_field('gs_qgs_gravityscore_grade',  $user_id, $payload['gravityscore_grade'] ?? '');
    self::set_user_field('gs_qgs_strategy_grade',      $user_id, $payload['strategy_grade'] ?? '');
    self::set_user_field('gs_qgs_funnel_grade',        $user_id, $payload['funnel_grade'] ?? '');
    self::set_user_field('gs_qgs_traffic_grade',       $user_id, $payload['traffic_grade'] ?? '');
  }

  private static function set_user_field(string $field_name, int $user_id, $value): void {
    $value = is_string($value) ? trim($value) : $value;

    // Prefer ACF if available.
    if (function_exists('update_field')) {
      update_field($field_name, $value === '' ? null : $value, 'user_' . $user_id);
      return;
    }

    // Fallback: user meta
    update_user_meta($user_id, $field_name, $value === '' ? '' : $value);
  }

  private static function get_user_field(string $field_name, int $user_id): ?string {
    if (function_exists('get_field')) {
      $v = get_field($field_name, 'user_' . $user_id);
      return is_string($v) ? $v : null;
    }
    $v = get_user_meta($user_id, $field_name, true);
    return is_string($v) ? $v : null;
  }
}
