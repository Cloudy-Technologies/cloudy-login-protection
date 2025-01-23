<?php
/**
 * Plugin Name: Cloudy Login Protection
 * Description: Extremely lightweight plugin to add some basic login protection to your WordPress site.
 * Version: 1.0
 * Author: CloudyTechnologies
 * Author URI: https://cloudytechnologies.mk/
 * Plugin URI: https://github.com/cloudy-Technologies/cloudy-login-protection
 */

if (!defined('ABSPATH')) {
  exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-custom-login-url.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-login-limiter.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-plugin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-session-manager.php';

class Cloudy_Login_Protection
{
  private $custom_login_url;
  private $login_limiter;
  private $plugin_settings;
  private $session_manager;

  public function __construct()
  {
    // Initialize plugin components
    $this->plugin_settings = new Plugin_Settings();
    $this->custom_login_url = new Custom_Login_URL($this->plugin_settings);
    $this->login_limiter = new Login_Limiter($this->plugin_settings);
    $this->session_manager = new Session_Manager($this->plugin_settings);

    register_activation_hook(__FILE__, [$this, 'activate_plugin']);
    register_deactivation_hook(__FILE__, [$this, 'deactivate_plugin']);
  }

  public function activate_plugin()
  {
    $this->login_limiter->create_attempts_table();

    $default_options = [
      'new_login_url' => '',
      'max_login_attempts' => 5,
      'lockout_duration' => 30,
      'reset_attempts_after' => 24,
      'session_timeout' => 60
    ];

    add_option('cloudy_login_protection_options', $default_options);
    wp_mkdir_p(plugin_dir_path(__FILE__) . 'js');
    flush_rewrite_rules();
    flush_rewrite_rules();
  }

  public function deactivate_plugin()
  {
    flush_rewrite_rules();
  }
}

new Cloudy_Login_Protection();
?>
