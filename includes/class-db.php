<?php
if (!defined('ABSPATH')) exit;

class GS_QGS_Bridge_DB {

  public const TABLE = 'gs_qgs_submissions';
  public const STATUS_PENDING = 'pending';
  public const STATUS_ASSOCIATED = 'associated';
  public const STATUS_FAILED = 'failed';

  public static function table_name(): string {
    global $wpdb;
    return $wpdb->prefix . self::TABLE;
  }

  public static function maybe_create_table(): void {
    global $wpdb;

    $table = self::table_name();
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // submission_id unique enforces idempotency
    $sql = "CREATE TABLE {$table} (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      submission_id VARCHAR(64) NOT NULL,
      email_normalised VARCHAR(191) NOT NULL,
      name VARCHAR(191) NULL,
      submitted_at DATETIME NOT NULL,
      scoring_version VARCHAR(64) NULL,
      source VARCHAR(191) NULL,
      gravityscore_grade CHAR(1) NULL,
      strategy_grade CHAR(1) NULL,
      funnel_grade CHAR(1) NULL,
      traffic_grade CHAR(1) NULL,

      user_id BIGINT(20) UNSIGNED NULL,
      associated_at DATETIME NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'pending',
      attempts INT UNSIGNED NOT NULL DEFAULT 0,
      last_attempt_at DATETIME NULL,
      last_error TEXT NULL,

      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,

      PRIMARY KEY  (id),
      UNIQUE KEY submission_id (submission_id),
      KEY email_normalised (email_normalised),
      KEY user_id (user_id),
      KEY status (status),
      KEY submitted_at (submitted_at),
      KEY associated_at (associated_at)
    ) {$charset_collate};";

    dbDelta($sql);
  }

  public static function get_by_submission_id(string $submission_id): ?array {
    global $wpdb;
    $table = self::table_name();

    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$table} WHERE submission_id = %s LIMIT 1", $submission_id),
      ARRAY_A
    );

    return $row ?: null;
  }

  public static function get_by_id(int $id): ?array {
    global $wpdb;
    $table = self::table_name();

    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id),
      ARRAY_A
    );

    return $row ?: null;
  }

  public static function insert_submission(array $row): array {
    global $wpdb;
    $table = self::table_name();

    $now = gmdate('Y-m-d H:i:s');

    $defaults = [
      'name' => null,
      'scoring_version' => null,
      'source' => null,
      'gravityscore_grade' => null,
      'strategy_grade' => null,
      'funnel_grade' => null,
      'traffic_grade' => null,

      'user_id' => null,
      'status' => self::STATUS_PENDING,
      'attempts' => 0,
      'last_attempt_at' => null,
      'last_error' => null,

      'created_at' => $now,
      'updated_at' => $now,
    ];

    $data = array_merge($defaults, $row);

    $ok = $wpdb->insert($table, [
      'submission_id'      => $data['submission_id'],
      'email_normalised'   => $data['email_normalised'],
      'name'               => $data['name'],
      'submitted_at'       => $data['submitted_at'],
      'scoring_version'    => $data['scoring_version'],
      'source'             => $data['source'],
      'gravityscore_grade' => $data['gravityscore_grade'],
      'strategy_grade'     => $data['strategy_grade'],
      'funnel_grade'       => $data['funnel_grade'],
      'traffic_grade'      => $data['traffic_grade'],

      'user_id'            => $data['user_id'],
      'status'             => $data['status'],
      'attempts'           => (int) $data['attempts'],
      'last_attempt_at'    => $data['last_attempt_at'],
      'last_error'         => $data['last_error'],

      'created_at'         => $data['created_at'],
      'updated_at'         => $data['updated_at'],
    ], [
      '%s', // submission_id
      '%s', // email_normalised
      '%s', // name
      '%s', // submitted_at
      '%s', // scoring_version
      '%s', // source
      '%s', // gravityscore_grade
      '%s', // strategy_grade
      '%s', // funnel_grade
      '%s', // traffic_grade

      '%d', // user_id
      '%s', // status
      '%d', // attempts
      '%s', // last_attempt_at
      '%s', // last_error

      '%s', // created_at
      '%s', // updated_at
    ]);

    if ($ok === false) {
      return [
        'ok' => false,
        'error' => $wpdb->last_error ?: 'DB insert failed',
      ];
    }

    return [
      'ok' => true,
      'id' => (int) $wpdb->insert_id,
    ];
  }

  public static function mark_associated(int $id, int $user_id): bool {
    global $wpdb;
    $table = self::table_name();

    $now = gmdate('Y-m-d H:i:s');

    $updated = $wpdb->update($table, [
      'user_id'       => $user_id,
      'status'        => self::STATUS_ASSOCIATED,
      'associated_at' => $now,
      'updated_at'    => $now,
    ], [
      'id' => $id,
    ], [
      '%d','%s','%s','%s'
    ], [
      '%d'
    ]);

    return ($updated !== false);
  }
}
