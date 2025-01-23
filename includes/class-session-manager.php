<?php
class Session_Manager
{
  private $settings;
  private $activity_meta_key = 'last_activity_timestamp';
  private $default_timeout = 60;

  public function __construct($settings)
  {
    $this->settings = $settings;

    if ($this->get_timeout() > 0) {
      add_action('init', [$this, 'start_session_tracking']);
      add_action('wp_loaded', [$this, 'check_user_session']);
      add_action('admin_init', [$this, 'check_user_session']);
      add_action('wp_login', [$this, 'on_user_login'], 10, 2);
      add_action('wp_ajax_update_user_activity', [$this, 'ajax_update_activity']);
      add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
      add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
      add_action('admin_post_reset_session_timeout', [$this, 'reset_session_timeout']);
    }
  }

  private function get_timeout()
  {
    $timeout = $this->settings->get_option('session_timeout');

    return $timeout ? intval($timeout) : $this->default_timeout;
  }

  public function start_session_tracking()
  {
    if (is_user_logged_in()) {
      $user_id = get_current_user_id();
      $last_activity = get_user_meta($user_id, $this->activity_meta_key, true);

      if (empty($last_activity)) {
        $this->update_user_activity();
      }
    }
  }

  public function check_user_session()
  {
    if (isset($_GET['action']) && $_GET['action'] === 'reset_session_timeout') {
      return;
    }

    if (!is_user_logged_in()) {
      return;
    }

    $user_id = get_current_user_id();
    $last_activity = get_user_meta($user_id, $this->activity_meta_key, true);

    if (empty($last_activity)) {
      $this->update_user_activity();
      return;
    }

    $timeout = $this->get_timeout() * 60; // Convert minutes to seconds

    if (current_user_can('manage_options')) {
      $timeout *= 2; // Double timeout for admins
    }

    if ($last_activity && (time() - $last_activity) > $timeout) {
      wp_logout();
      wp_safe_redirect(add_query_arg('session_expired', '1', wp_login_url()));
      exit;
    }
  }

  public function update_user_activity()
  {
    if (is_user_logged_in()) {
      $user_id = get_current_user_id();
      update_user_meta($user_id, $this->activity_meta_key, time());
    }
  }

  public function ajax_update_activity()
  {
    check_ajax_referer('update_activity_nonce', 'nonce');

    if (is_user_logged_in()) {
      $this->update_user_activity();
      wp_send_json_success();
    }

    wp_send_json_error();
  }

  public function on_user_login($user_login, $user)
  {
    update_user_meta($user->ID, $this->activity_meta_key, time());
  }

  public function enqueue_scripts()
  {
    if (!is_user_logged_in()) {
      return;
    }

    if ($this->get_timeout() <= 0) {
      return;
    }

    wp_enqueue_script(
      'session-manager',
      plugins_url('js/session-manager.js', dirname(__FILE__)),
      ['jquery'],
      '1.0',
      true
    );

    $timeout = $this->get_timeout() * 60;

    if (current_user_can('manage_options')) {
      $timeout *= 2;
    }

    wp_localize_script(
      'session-manager',
      'sessionManager',
      [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'timeout' => $timeout,
        'warning_time' => 60,
        'nonce' => wp_create_nonce('update_activity_nonce'),
        'isAdmin' => current_user_can('manage_options')
      ]
    );
  }

  public function reset_session_timeout()
  {
    if (current_user_can('manage_options')) {
      $options = get_option('cloudy_login_protection_options');
      $options['session_timeout'] = $this->default_timeout;

      update_option('cloudy_login_protection_options', $options);
      delete_metadata('user', 0, $this->activity_meta_key, '', true);
      wp_safe_redirect(admin_url('options-general.php?page=secure-custom-login&reset=1'));

      exit;
    }
  }
}
?>