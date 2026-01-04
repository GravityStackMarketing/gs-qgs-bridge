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

    $incoming = isset($payload['submitted_at']) ? trim((string) $payload['submitted_at']) : '';
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

  public static function try_associate_row(array $row): array {
    $email_norm = isset($row['email_normalised']) ? (string) $row['email_normalised'] : '';
    $id         = isset($row['id']) ? (int) $row['id'] : 0;

    if ($id <= 0 || $email_norm === '') {
      return ['ok' => false, 'associated' => false, 'error' => 'Invalid row'];
    }

    $user_id = self::find_user_id_by_email($email_norm);

    if ($user_id <= 0) {
      // Still pending — bump attempt for observability
      GS_QGS_Bridge_DB::bump_attempt($id, 'No matching user for email');
      return ['ok' => true, 'associated' => false, 'user_id' => null];
    }

    // Mark associated in DB
    $ok = GS_QGS_Bridge_DB::mark_associated($id, $user_id);
    if (!$ok) {
      GS_QGS_Bridge_DB::bump_attempt($id, 'DB mark_associated failed');
      return ['ok' => false, 'associated' => false, 'error' => 'DB update failed'];
    }

    // Update ACF latest snapshot (latest-wins logic already inside)
    self::maybe_update_acf_latest($user_id, [
      'submission_id'      => $row['submission_id'] ?? null,
      'submitted_at'       => $row['submitted_at'] ?? null,
      'scoring_version'    => $row['scoring_version'] ?? null,
      'source'             => $row['source'] ?? null,
      'name'               => $row['name'] ?? null,
      'gravityscore_grade' => $row['gravityscore_grade'] ?? null,
      'strategy_grade'     => $row['strategy_grade'] ?? null,
      'funnel_grade'       => $row['funnel_grade'] ?? null,
      'traffic_grade'      => $row['traffic_grade'] ?? null,
    ]);

    return ['ok' => true, 'associated' => true, 'user_id' => $user_id];
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
