<?php
class Custom_Login_URL
{
  private $settings;

  public function __construct($settings)
  {
    $this->settings = $settings;

    add_action('init', [$this, 'add_rewrite_rules']);
    add_filter('query_vars', [$this, 'add_query_vars']);
    add_action('parse_request', [$this, 'parse_request']);
    add_filter('site_url', [$this, 'filter_login_url'], 10, 4);
    add_filter('network_site_url', [$this, 'filter_login_url'], 10, 3);
    add_filter('wp_redirect', [$this, 'filter_login_url_redirect'], 10, 2);
    add_action('init', [$this, 'block_wp_login']);
    add_action('admin_notices', [$this, 'check_permalink_structure']);
  }

  public function add_rewrite_rules()
  {
    $login_slug = $this->settings->get_option('new_login_url');

    if (!empty($login_slug)) {
      add_rewrite_rule(
        '^' . $login_slug . '/?$',
        'index.php?custom_login=1',
        'top'
      );
    }
  }

  public function add_query_vars($vars)
  {
    $vars[] = 'custom_login';

    return $vars;
  }

  public function parse_request($wp)
  {
    if (isset($wp->query_vars['custom_login'])) {
      require_once(ABSPATH . 'wp-login.php');
      exit;
    }
  }

  public function block_wp_login()
  {
    $login_slug = $this->settings->get_option('new_login_url');

    if (!empty($login_slug)) {
      global $pagenow;

      if (
        $pagenow == 'wp-login.php' &&
        !isset($_GET['action']) &&
        !isset($_POST['action']) &&
        !is_admin()
      ) {
        $allowed_actions = ['logout', 'lostpassword', 'rp', 'resetpass', 'postpass'];
        $current_action = isset($_GET['action']) ? $_GET['action'] : '';

        if (!in_array($current_action, $allowed_actions)) {
          if (!wp_doing_ajax()) {
            wp_safe_redirect(home_url('404'), 302);

            exit();
          }
        }
      }
    }
  }

  public function filter_login_url($url, $path, $scheme = null, $blog_id = null)
  {
    $login_slug = $this->settings->get_option('new_login_url');

    if (!empty($login_slug) && strpos($url, 'wp-login.php') !== false) {
      $allowed_actions = ['logout', 'lostpassword', 'rp', 'resetpass'];

      foreach ($allowed_actions as $action) {
        if (strpos($url, 'action=' . $action) !== false) {
          return $url;
        }
      }

      $url = str_replace('wp-login.php', $login_slug, $url);
    }

    return $url;
  }

  public function filter_login_url_redirect($location, $status)
  {
    $login_slug = $this->settings->get_option('new_login_url');

    if (!empty($login_slug)) {
      $allowed_actions = ['logout', 'lostpassword', 'rp', 'resetpass'];

      foreach ($allowed_actions as $action) {
        if (strpos($location, 'action=' . $action) !== false) {
          return $location;
        }
      }

      return str_replace('wp-login.php', $login_slug, $location);
    }

    return $location;
  }

  public function check_permalink_structure()
  {
    $login_slug = $this->settings->get_option('new_login_url');

    if (!empty($login_slug) && !get_option('permalink_structure')) {
      ?>
      <div class="notice notice-error">
        <p><strong>Secure Custom Login:</strong> Please enable pretty permalinks for the custom login URL to work. Go to <a
            href="<?php echo admin_url('options-permalink.php'); ?>">Settings → Permalinks</a> and choose any option other
          than "Plain".</p>
      </div>
      <?php
    }
  }

  public function get_login_url()
  {
    $login_slug = $this->settings->get_option('new_login_url');

    if (!empty($login_slug)) {
      return home_url($login_slug);
    }

    return wp_login_url();
  }

  public function is_login_page()
  {
    $login_slug = $this->settings->get_option('new_login_url');

    if (empty($login_slug)) {
      return false;
    }

    global $wp;
    $current_url = home_url($wp->request);
    $login_url = home_url($login_slug);

    return trailingslashit($current_url) === trailingslashit($login_url);
  }
}
?>