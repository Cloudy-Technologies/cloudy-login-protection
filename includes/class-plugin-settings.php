<?php
class Plugin_Settings
{
  private $options;

  public function __construct()
  {
    $this->options = get_option('cloudy_login_protection_options');
    add_action('admin_menu', [$this, 'add_plugin_page']);
    add_action('admin_init', [$this, 'page_init']);
  }

  public function add_plugin_page()
  {
    add_options_page(
      'Cloudy Login Protection Settings',
      'Login Protection',
      'manage_options',
      'secure-custom-login',
      [$this, 'create_admin_page']
    );
  }

  public function create_admin_page()
  {
    ?>
    <div class="wrap">
      <h1>Cloudy Login Protection Settings</h1>
      <form method="post" action="options.php">
        <?php
        settings_fields('cloudy_login_protection_options');
        do_settings_sections('secure-custom-login-admin');
        submit_button();
        ?>
      </form>
      <div class="notice notice-info">
        <p>After changing the login URL, please go to Settings > Permalinks and click "Save Changes".</p>
      </div>
    </div>
    <?php
  }

  public function page_init()
  {
    register_setting(
      'cloudy_login_protection_options',
      'cloudy_login_protection_options',
      [$this, 'sanitize']
    );

    add_settings_section(
      'login_url_section',
      'Login URL Settings',
      null,
      'secure-custom-login-admin'
    );

    add_settings_field(
      'new_login_url',
      'Custom Login URL',
      [$this, 'new_login_url_callback'],
      'secure-custom-login-admin',
      'login_url_section'
    );

    add_settings_section(
      'login_limiter_section',
      'Login Attempt Limits',
      null,
      'secure-custom-login-admin'
    );

    add_settings_field(
      'max_login_attempts',
      'Maximum Login Attempts',
      [$this, 'max_attempts_callback'],
      'secure-custom-login-admin',
      'login_limiter_section'
    );

    add_settings_field(
      'lockout_duration',
      'Lockout Duration (minutes)',
      [$this, 'lockout_duration_callback'],
      'secure-custom-login-admin',
      'login_limiter_section'
    );

    add_settings_field(
      'reset_attempts_after',
      'Reset Attempts After (hours)',
      [$this, 'reset_attempts_callback'],
      'secure-custom-login-admin',
      'login_limiter_section'
    );

    add_settings_section(
      'session_management_section',
      'Session Management',
      null,
      'secure-custom-login-admin'
    );

    add_settings_field(
      'session_timeout',
      'Session Timeout (minutes)',
      [$this, 'session_timeout_callback'],
      'secure-custom-login-admin',
      'session_management_section'
    );

    add_settings_section(
      'recaptcha_section',
      'reCAPTCHA Settings',
      [$this, 'recaptcha_section_info'],
      'secure-custom-login-admin'
    );

    add_settings_field(
      'recaptcha_site_key',
      'Site Key',
      [$this, 'recaptcha_site_key_callback'],
      'secure-custom-login-admin',
      'recaptcha_section'
    );

    add_settings_field(
      'recaptcha_secret_key',
      'Secret Key',
      [$this, 'recaptcha_secret_key_callback'],
      'secure-custom-login-admin',
      'recaptcha_section'
    );
  }

  public function recaptcha_section_info()
  {
    echo 'Enter your Google reCAPTCHA v2 credentials below. You can get these from <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Admin</a>';
  }

  public function recaptcha_site_key_callback()
  {
    printf(
      '<input type="text" class="regular-text" id="recaptcha_site_key" name="cloudy_login_protection_options[recaptcha_site_key]" value="%s" />',
      isset($this->options['recaptcha_site_key']) ? esc_attr($this->options['recaptcha_site_key']) : ''
    );
  }

  public function recaptcha_secret_key_callback()
  {
    printf(
      '<input type="text" class="regular-text" id="recaptcha_secret_key" name="cloudy_login_protection_options[recaptcha_secret_key]" value="%s" />',
      isset($this->options['recaptcha_secret_key']) ? esc_attr($this->options['recaptcha_secret_key']) : ''
    );
  }

  public function sanitize($input)
  {
    $sanitized = [];

    $sanitized['new_login_url'] = sanitize_title($input['new_login_url']);
    $sanitized['max_login_attempts'] = absint($input['max_login_attempts']);
    $sanitized['lockout_duration'] = absint($input['lockout_duration']);
    $sanitized['reset_attempts_after'] = absint($input['reset_attempts_after']);

    $sanitized['max_login_attempts'] = max(1, $sanitized['max_login_attempts']);
    $sanitized['lockout_duration'] = max(1, $sanitized['lockout_duration']);
    $sanitized['reset_attempts_after'] = max(1, $sanitized['reset_attempts_after']);

    $sanitized['session_timeout'] = absint($input['session_timeout']);
    $sanitized['session_timeout'] = max(1, $sanitized['session_timeout']);


    if (isset($input['recaptcha_site_key'])) {
      $sanitized['recaptcha_site_key'] = sanitize_text_field($input['recaptcha_site_key']);
    }

    if (isset($input['recaptcha_secret_key'])) {
      $sanitized['recaptcha_secret_key'] = sanitize_text_field($input['recaptcha_secret_key']);
    }

    return $sanitized;
  }

  public function new_login_url_callback()
  {
    printf(
      '<input type="text" id="new_login_url" name="cloudy_login_protection_options[new_login_url]" value="%s" />',
      esc_attr($this->options['new_login_url'])
    );
    echo '<p class="description">This will be your new login url instead of the default "wp-login"</p>';
  }

  public function max_attempts_callback()
  {
    printf(
      '<input type="number" min="1" id="max_login_attempts" name="cloudy_login_protection_options[max_login_attempts]" value="%s" />',
      esc_attr($this->options['max_login_attempts'])
    );
  }

  public function lockout_duration_callback()
  {
    printf(
      '<input type="number" min="1" id="lockout_duration" name="cloudy_login_protection_options[lockout_duration]" value="%s" />',
      esc_attr($this->options['lockout_duration'])
    );
  }

  public function reset_attempts_callback()
  {
    printf(
      '<input type="number" min="1" id="reset_attempts_after" name="cloudy_login_protection_options[reset_attempts_after]" value="%s" />',
      esc_attr($this->options['reset_attempts_after'])
    );
    echo '<p class="description">Determines when the count of failed attempts is cleared/reset to zero to prevent accumulation of old failed attempts from causing lockouts</p>';
  }

  public function get_option($key)
  {
    return isset($this->options[$key]) ? $this->options[$key] : null;
  }

  public function session_timeout_callback()
  {
    printf(
      '<input type="number" min="1" id="session_timeout" name="cloudy_login_protection_options[session_timeout]" value="%s" />',
      esc_attr($this->options['session_timeout'])
    );
    echo '<p class="description">Users will be logged out after this many minutes of inactivity.</p>';
  }
}
?>