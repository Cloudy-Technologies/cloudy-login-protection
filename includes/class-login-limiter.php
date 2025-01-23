<?php
class Login_Limiter
{
  private $settings;
  private $table_name;

  public function __construct($settings)
  {
    global $wpdb;
    $this->settings = $settings;
    $this->table_name = $wpdb->prefix . 'login_attempts';

    add_filter('authenticate', [$this, 'check_login_allowed'], 30, 3);
    add_action('wp_login_failed', [$this, 'log_failed_attempt']);
    add_action('wp_login', [$this, 'clear_login_attempts']);
    add_filter('login_errors', [$this, 'add_remaining_attempts_message']);
    add_action('login_enqueue_scripts', [$this, 'enqueue_recaptcha']);
    add_action('login_form', [$this, 'add_recaptcha_field']);
    add_filter('wp_authenticate_user', [$this, 'verify_recaptcha'], 10, 2);
  }

  public function create_attempts_table()
  {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip_address varchar(100) NOT NULL,
            attempted_at datetime DEFAULT CURRENT_TIMESTAMP,
            locked_until datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY ip_address (ip_address),
            KEY locked_until (locked_until)
        ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
  }

  public function check_login_allowed($user, $username, $password)
  {
    if (!empty($username)) {
      $ip = $this->get_ip_address();

      $lockout_info = $this->get_lockout_info($ip);
      if ($lockout_info['is_locked']) {
        $minutes_left = ceil(($lockout_info['locked_until'] - time()) / 60);
        return new WP_Error(
          'exceeded_login_limit',
          sprintf(__('Too many failed login attempts. Please try again in %d minutes.'), $minutes_left)
        );
      }
    }
    return $user;
  }

  public function add_remaining_attempts_message($error)
  {
    if (strpos($error, 'incorrect') !== false || strpos($error, 'invalid') !== false) {
      $ip = $this->get_ip_address();
      $attempts = $this->count_recent_attempts($ip);
      $max_attempts = $this->settings->get_option('max_login_attempts');
      $remaining = max(0, $max_attempts - $attempts);

      if ($remaining > 0) {
        $error .= '<br/><br/>' . sprintf(
          _n(
            'Warning: You have %d login attempt remaining.',
            'Warning: You have %d login attempts remaining.',
            $remaining,
            'secure-custom-login'
          ),
          $remaining
        );
      }
    }
    return $error;
  }

  public function enqueue_recaptcha()
  {
    $site_key = $this->settings->get_option('recaptcha_site_key');
    if (!empty($site_key)) {
      wp_enqueue_script('recaptcha', 'https://www.google.com/recaptcha/api.js', [], null);
    }
  }

  public function add_recaptcha_field()
  {
    $site_key = $this->settings->get_option('recaptcha_site_key');
    if (!empty($site_key)) {
      echo '<div class="g-recaptcha" style="margin-bottom: 10px;" data-sitekey="' . esc_attr($site_key) . '"></div>';
    }
  }

  public function verify_recaptcha($user, $password)
  {
    if (
      empty($this->settings->get_option('recaptcha_site_key')) ||
      empty($this->settings->get_option('recaptcha_secret_key'))
    ) {
      return $user;
    }

    if (wp_doing_ajax()) {
      return $user;
    }

    $recaptcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';

    if (empty($recaptcha_response)) {
      return new WP_Error('empty_captcha', 'Please complete the CAPTCHA.');
    }

    $verify = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
      'body' => [
        'secret' => $this->settings->get_option('recaptcha_secret_key'),
        'response' => $recaptcha_response,
        'remoteip' => $this->get_ip_address()
      ]
    ]);

    if (is_wp_error($verify)) {
      return $user;
    }

    $verify = json_decode(wp_remote_retrieve_body($verify));

    if (empty($verify->success)) {
      return new WP_Error('invalid_captcha', 'CAPTCHA verification failed. Please try again.');
    }

    return $user;
  }

  public function log_failed_attempt($username)
  {
    global $wpdb;

    $ip = $this->get_ip_address();
    $max_attempts = $this->settings->get_option('max_login_attempts');
    $lockout_duration = $this->settings->get_option('lockout_duration');
    $reset_after = $this->settings->get_option('reset_attempts_after');

    $this->clean_old_attempts($ip, $reset_after);

    $wpdb->insert(
      $this->table_name,
      [
        'ip_address' => $ip,
        'attempted_at' => current_time('mysql')
      ]
    );

    $attempts = $this->count_recent_attempts($ip);
    if ($attempts >= $max_attempts) {
      $locked_until = date('Y-m-d H:i:s', strtotime("+{$lockout_duration} minutes"));
      $wpdb->update(
        $this->table_name,
        ['locked_until' => $locked_until],
        ['ip_address' => $ip]
      );
    }
  }

  public function clear_login_attempts($username)
  {
    global $wpdb;
    $ip = $this->get_ip_address();

    $wpdb->delete(
      $this->table_name,
      ['ip_address' => $ip]
    );
  }

  private function get_lockout_info($ip)
  {
    global $wpdb;

    $lockout = $wpdb->get_row($wpdb->prepare(
      "SELECT locked_until FROM {$this->table_name}
            WHERE ip_address = %s AND locked_until IS NOT NULL
            AND locked_until > NOW()
            LIMIT 1",
      $ip
    ));

    if ($lockout) {
      return [
        'is_locked' => true,
        'locked_until' => strtotime($lockout->locked_until)
      ];
    }

    return ['is_locked' => false, 'locked_until' => null];
  }

  private function count_recent_attempts($ip)
  {
    global $wpdb;

    return (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$this->table_name}
            WHERE ip_address = %s
            AND attempted_at > DATE_SUB(NOW(), INTERVAL %d HOUR)",
      $ip,
      $this->settings->get_option('reset_attempts_after')
    ));
  }

  private function clean_old_attempts($ip, $hours)
  {
    global $wpdb;

    $wpdb->query($wpdb->prepare(
      "DELETE FROM {$this->table_name}
            WHERE ip_address = %s
            AND attempted_at < DATE_SUB(NOW(), INTERVAL %d HOUR)",
      $ip,
      $hours
    ));
  }

  private function get_ip_address()
  {
    $ip = '';

    $proxy_headers = [
      'HTTP_CLIENT_IP',
      'HTTP_X_FORWARDED_FOR',
      'HTTP_X_FORWARDED',
      'HTTP_X_CLUSTER_CLIENT_IP',
      'HTTP_FORWARDED_FOR',
      'HTTP_FORWARDED'
    ];

    foreach ($proxy_headers as $header) {
      if (!empty($_SERVER[$header])) {
        $ip = $_SERVER[$header];
        break;
      }
    }

    return $ip ? $ip : $_SERVER['REMOTE_ADDR'];
  }
}
?>