<?php
if (!defined('ABSPATH')) exit;

class GS_QGS_Bridge_Admin_Tools {

  public static function init(): void {
    add_action('admin_menu', [__CLASS__, 'menu']);
  }

  public static function menu(): void {
    add_management_page(
      'QGS Bridge',
      'QGS Bridge',
      'manage_options',
      'gs-qgs-bridge',
      [__CLASS__, 'render']
    );
  }

  public static function render(): void {
    if (!current_user_can('manage_options')) return;

    $counts = GS_QGS_Bridge_DB::counts();

    $email = '';
    $rows  = [];
    $notice = '';

    if (isset($_GET['gs_qgs_email'])) {
      $email = sanitize_email((string) $_GET['gs_qgs_email']);
      if ($email) {
        $email_norm = GS_QGS_Bridge_Association::normalise_email($email);
        $rows = GS_QGS_Bridge_DB::get_all_by_email($email_norm, 50);
      }
    }

    // Handle retry action
    if (isset($_POST['gs_qgs_retry']) && check_admin_referer('gs_qgs_retry_action')) {
      $retry_email = sanitize_email((string) ($_POST['gs_qgs_email'] ?? ''));
      if ($retry_email) {
        $email_norm = GS_QGS_Bridge_Association::normalise_email($retry_email);
        $pending = GS_QGS_Bridge_DB::get_pending_by_email($email_norm, 50);
        $assoc_count = 0;

        foreach ($pending as $row) {
          $result = GS_QGS_Bridge_Association::try_associate_row($row);
          if (!empty($result['associated'])) $assoc_count++;
        }

        $notice = sprintf('Retry complete. Associated %d pending submission(s).', $assoc_count);

        // Refresh view
        $rows = GS_QGS_Bridge_DB::get_all_by_email($email_norm, 50);
        $email = $retry_email;
      }
    }

    ?>
    <div class="wrap">
      <h1>QGS Bridge</h1>

      <?php if ($notice): ?>
        <div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div>
      <?php endif; ?>

      <h2>Overview</h2>
      <p>
        Total: <strong><?php echo (int)$counts['total']; ?></strong> |
        Pending: <strong><?php echo (int)$counts['pending']; ?></strong> |
        Associated: <strong><?php echo (int)$counts['associated']; ?></strong> |
        Failed: <strong><?php echo (int)$counts['failed']; ?></strong>
      </p>

      <h2>Search by email</h2>
      <form method="get" action="">
        <input type="hidden" name="page" value="gs-qgs-bridge" />
        <input type="email" name="gs_qgs_email" value="<?php echo esc_attr($email); ?>" style="width:320px;" placeholder="email@example.com" />
        <button class="button button-primary" type="submit">Search</button>
      </form>

      <?php if ($email && $rows): ?>
        <h2>Submissions for <?php echo esc_html($email); ?></h2>

        <form method="post" style="margin: 12px 0;">
          <?php wp_nonce_field('gs_qgs_retry_action'); ?>
          <input type="hidden" name="gs_qgs_email" value="<?php echo esc_attr($email); ?>" />
          <button class="button" type="submit" name="gs_qgs_retry" value="1">Retry association</button>
        </form>

        <table class="widefat striped">
          <thead>
            <tr>
              <th>Submitted</th>
              <th>Submission ID</th>
              <th>Status</th>
              <th>User ID</th>
              <th>Scores</th>
              <th>Source</th>
              <th>Version</th>
              <th>Last Error</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?php echo esc_html($r['submitted_at'] ?? ''); ?></td>
                <td><code><?php echo esc_html($r['submission_id'] ?? ''); ?></code></td>
                <td><?php echo esc_html($r['status'] ?? ''); ?></td>
                <td><?php echo esc_html($r['user_id'] ?? ''); ?></td>
                <td>
                  G: <?php echo esc_html($r['gravityscore_grade'] ?? ''); ?>,
                  S: <?php echo esc_html($r['strategy_grade'] ?? ''); ?>,
                  F: <?php echo esc_html($r['funnel_grade'] ?? ''); ?>,
                  T: <?php echo esc_html($r['traffic_grade'] ?? ''); ?>
                </td>
                <td><?php echo esc_html($r['source'] ?? ''); ?></td>
                <td><?php echo esc_html($r['scoring_version'] ?? ''); ?></td>
                <td><?php echo esc_html($r['last_error'] ?? ''); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

      <?php elseif ($email): ?>
        <p>No submissions found for that email.</p>
      <?php endif; ?>

    </div>
    <?php
  }
}
