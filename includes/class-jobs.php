<?php
if (!defined('ABSPATH')) exit;

class GS_QGS_Bridge_Jobs {

  public const CRON_HOOK = 'gs_qgs_bridge_cron_sweep';

  public static function init(): void {
    add_action('user_register', [__CLASS__, 'on_user_register'], 20, 1);
    add_action(self::CRON_HOOK, [__CLASS__, 'cron_sweep']);

    // Schedule on init if missing
    add_action('init', [__CLASS__, 'ensure_schedule']);
  }

  public static function ensure_schedule(): void {
    if (!wp_next_scheduled(self::CRON_HOOK)) {
      wp_schedule_event(time() + 120, 'gs_qgs_15min', self::CRON_HOOK);
    }
  }

  public static function clear_schedule(): void {
    $ts = wp_next_scheduled(self::CRON_HOOK);
    if ($ts) {
      wp_unschedule_event($ts, self::CRON_HOOK);
    }
  }

  public static function on_user_register(int $user_id): void {
    $user = get_user_by('id', $user_id);
    if (!$user || empty($user->user_email)) return;

    $email_norm = GS_QGS_Bridge_Association::normalise_email((string) $user->user_email);
    $rows = GS_QGS_Bridge_DB::get_pending_by_email($email_norm, 50);

    foreach ($rows as $row) {
      GS_QGS_Bridge_Association::try_associate_row($row);
    }
  }

  public static function cron_sweep(): void {
    $batch = GS_QGS_Bridge_DB::get_pending_batch(50);

    foreach ($batch as $row) {
      // Optional: fail hard after N attempts
      $attempts = isset($row['attempts']) ? (int) $row['attempts'] : 0;
      if ($attempts >= 20) {
        GS_QGS_Bridge_DB::mark_failed((int)$row['id'], 'Max attempts exceeded');
        continue;
      }

      GS_QGS_Bridge_Association::try_associate_row($row);
    }
  }
}
