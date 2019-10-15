<?php
/**
 * Plugin Name: Simple Payment
 * Plugin URI: https://simple-payment.yalla-ya.com
 * Description: This is a Simple Payment to work with Cardom
 * Version: 1.1.2
 * Author: Ido Kobelkowsky / yalla ya!
 * Author URI: https://github.com/idokd
 * License: GPLv2
 */

if (!defined("ABSPATH")) {
  exit; // Exit if accessed directly
}

define('SPWP_PLUGIN_FILE', __FILE__);
define('SPWP_PLUGIN_DIR', dirname(SPWP_PLUGIN_FILE));

require_once(SPWP_PLUGIN_DIR . '/vendor/autoload.php');

if (file_exists(SPWP_PLUGIN_DIR .'/vendor/leewillis77/WpListTableExportable/bootstrap.php')) require_once(SPWP_PLUGIN_DIR .'/vendor/leewillis77/WpListTableExportable/bootstrap.php');

class SimplePaymentPlugin extends SimplePayment\SimplePayment {

  protected $option_name = 'sp';
  protected $payment_page = null;

  protected $test_shortcodes = [
    'button' => [
        'title' => 'Standard Button Shortcode',
        'description' => 'Show standard button',
        'shortcode' => '[simple_payment product="Test Product" amount="99.00" type="button" target="_blank"]'
    ],
    'paypal' => [
        'title' => 'Paypal Button Shortcode',
        'description' => 'Show standard button',
        'shortcode' => '[simple_payment product="Test Product" amount="99.00" title="Buy via Paypal" type="button" target="_blank" method="paypal"]'
    ]
  ];

  protected $defaults = [
      'form_type' => 'legacy',
      'amount_field' => 'amount',
      'engine' => 'PayPal',
      'mode' => 'sandbox',
      'currency' => 'USD'
  ];

  public function __construct($params = []) {
    $option = get_option('sp') ? : [];
    parent::__construct(array_merge(array_merge($this->defaults, $params), $option));
    $this->license = get_option('sp_license');
  }

  public function setEngine($engine) {
    if ($this->param('mode') == 'live' && $this->license) {
      $this->sandbox = false;
    }
    parent::setEngine($engine);
    if ($this->engine) $this->engine->setCallback(strpos($this->callback, '://') ? $this->callback : get_bloginfo('url') . $this->callback);
  }

  public function load() {
    add_action('plugins_loaded', [$this, 'load_textdomain']);
    add_action('plugins_loaded', [$this, 'init']);

    if (is_admin()) {
      register_activation_hook(__FILE__, [$this, 'activate']);
      register_deactivation_hook(__FILE__, [$this, 'deactivate']);

      //if (isset($_REQUEST['action'])) {
      //  do_action("admin_post_{$_REQUEST['action']}", [$this, 'archive']);
      //}

      add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), [$this, 'plugin_action_links']);

      add_filter('display_post_states', [$this, 'add_custom_post_states']);
      add_action('admin_menu', [$this, 'add_plugin_options_page']);
      if (!empty($GLOBALS['page'])) {
          switch ($GLOBALS['page']) {
              case 'simple-payments':
                add_filter('set-screen-option', [$this, 'screen_option'], 10, 3);
                break;
              default:
                break;
          }
      }
      add_action('admin_menu', [$this, 'add_plugin_options_page']);
      if (!empty($GLOBALS['pagenow'])) {
          switch ($GLOBALS['pagenow']) {
              case 'options-general.php':
              case 'options.php':
              case 'options-reading.php':
                add_action('admin_init', [$this, 'add_plugin_settings']);
                break;
              default:
                break;
          }
      }
    }
    add_action('parse_request', [$this, 'callback']);
    add_shortcode('simple_payment', [$this, 'shortcode'] );
  }

  function plugin_action_links($links) {
  	$links = array_merge( array(
  		'<a href="' . esc_url( admin_url( '/options-general.php?page=sp' ) ) . '">' . __( 'Settings', 'simple_payment' ) . '</a>'
  	), $links );
  	return($links);
  }

  public function init() {
    $this->payment_page = self::param('payment_page');
    $this->callback = $this->payment_page();
  }

  protected function payment_page() {
      if ($this->payment_page) $this->callback = get_page_link($this->payment_page);
      else $this->callback = self::param('callback_url');
      return($this->callback);
  }

  function activate() {}
  function deactivate() {}

  function register_reading_setting() {
    register_setting(
      'reading',
      'sp',
      [ 'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => NULL ]
    );
    add_settings_field(
        'sp_payment_page',
        __('Payment Page', 'simple-payment'),
        [$this, 'setting_callback_function'],
        'reading',
        'default',
        array( 'label_for' => 'sp_payment_page' )
    );
  }

  function setting_callback_function($args){
      $project_page_id = self::param('payment_page');
      $args = array(
          'posts_per_page'   => -1,
          'orderby'          => 'name',
          'order'            => 'ASC',
          'post_type'        => 'page',
      );
      $items = get_posts( $args );
      echo '<select id="sp_payment_page" name="sp[payment_page]">';
      echo '<option value="0">'.__('— Select —', 'wordpress').'</option>';
      foreach ($items as $item) {
          echo '<option value="'.$item->ID.'" '.($project_page_id == $item->ID ? 'selected="selected"' : '').'>'.$item->post_title.'</option>';
      }
      echo '</select>';
  }

  function add_custom_post_states($states) {
      global $post;
      $payment_page_id = self::param('payment_page');
      if( 'page' == get_post_type($post->ID) && $post->ID == $payment_page_id && $payment_page_id != '0') {
          $states[] = __('Payment Page', 'simple-payment');
      }
      return($states);
  }

  public function add_plugin_options_page() {
    add_options_page(
      __('Simple Payment', 'simple-payment'),
      __('Simple Payment', 'simple-payment'),
      'manage_options',
      'sp',
      [$this, 'render_admin_page']
    );

    $hook = add_menu_page(
      __('Payments', 'simple-payment'),
      __('Payments', 'simple-payment'),
      'manage_options',
      'simple-payments',
      [$this, 'render_transactions'],
      plugin_dir_url( __FILE__ ).'assets/simple-payment-icon.png',
      30
    );
    add_action( "load-$hook", [$this, 'transactions'] );

    $hook = add_submenu_page( null,
      __('Transaction Details', 'simple-payment'),
      null,
      'manage_options',
      'simple-payments-details',
      [$this, 'render_transaction_log']
    );
    add_action( "load-$hook", [$this, 'info'] );
  }

  public function screen_option($status, $option, $value) {
      if ( 'sp_per_page' == $option ) return $value;
      return $status;
  }

  // Render our plugin's option page.
  public function render_admin_page() {
    if (!current_user_can('manage_options')) return;

    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'sp';
    $section = $tab;
    $tabs = ['General', 'PayPal', 'Cardcom', 'License', 'Shortcode', 'Instructions'];
    ?>
    <div class="wrap">
      <h1><?php _e('Simple Payment Settings', 'simple-payment'); ?></h1>
      <h2 class="nav-tab-wrapper">
            <a id="sp" href="options-general.php?page=sp" class="nav-tab <?php echo $tab == 'sp' ? 'nav-tab-active' : ''; ?>"><?php _e('General', 'simple-payment'); ?></a>
            <a id="paypal" href="options-general.php?page=sp&tab=paypal" class="nav-tab <?php echo $tab == 'paypal' ? 'nav-tab-active' : ''; ?>"><?php _e('PayPal', 'simple-payment'); ?></a>
            <a id="cardcom" href="options-general.php?page=sp&tab=cardcom" class="nav-tab <?php echo $tab == 'cardcom' ? 'nav-tab-active' : ''; ?>"><?php _e('Cardcom', 'simple-payment'); ?></a>
            <a id="license" href="options-general.php?page=sp&tab=license" class="nav-tab <?php echo $tab == 'license' ? 'nav-tab-active' : ''; ?>"><?php _e('License', 'simple-payment'); ?></a>
            <a id="shortcode" href="options-general.php?page=sp&tab=shortcode" class="nav-tab <?php echo $tab == 'shortcode' ? 'nav-tab-active' : ''; ?>"><?php _e('Shortcode', 'simple-payment'); ?></a>
            <a id="instructions" href="options-general.php?page=sp&tab=instructions" class="nav-tab <?php echo $tab == 'instructions' ? 'nav-tab-active' : ''; ?>"><?php _e('Instructions', 'simple-payment'); ?></a>
        </h2>
      <?php
      switch ($tab) {
        case 'instructions':
          require(SPWP_PLUGIN_DIR.'/admin/instructions.php');
          break;
        case 'shortcode':
          require(SPWP_PLUGIN_DIR.'/admin/shortcode.php');
          foreach ($this->test_shortcodes as $key => $shortcode) {
              if (isset($shortcode['title'])) echo '<div>'.$shortcode['title'].'</div>';
              if (isset($shortcode['description'])) echo '<div>'.$shortcode['description'].'</div>';
              echo '<pre>'.$shortcode['shortcode'].'</pre>';
              echo do_shortcode($shortcode['shortcode']);
          }
          break;
        default:
          echo '<form method="post" action="options.php">';
          settings_fields('sp');
          do_settings_sections($section);
          submit_button();
          echo '</form>';
    }
    echo "</div>";
  }

  public function register_license_settings() {
    register_setting('sp', 'sp_license', ['type' => 'string', 'sanitize_callback' => [$this, 'license_key_callback']]);
    add_settings_field(
      'sp_license',
      __('License Key', 'simple-payment'),
      [$this, 'render_license_key_field'],
      'license',
      'licensing',
      array('label_for' => 'sp_license')
    );
  }
  // Initialize our plugin's settings.
  public function add_plugin_settings() {
    $this->register_reading_setting();
    $this->register_license_settings();

    require_once('settings.php');
    $this->sections = $sp_sections;

    foreach ($sp_sections as $key => $section) {
        add_settings_section(
          $key,
          $section['title'],
          [$this, isset($section['render_function']) ? $section['render_function'] : 'render_section'],
          isset($section['section']) ? $section['section'] : 'sp'
        );
    }
    register_setting('sp', 'sp', ['sanitize_callback' => [$this, 'validate_options'], 'default' => []]);

    foreach ($sp_settings as $key => $value) {
        add_settings_field(
          $key,
          $value['title'],
          [$this, isset($value['render_function']) ? $value['render_function'] : 'render_setting_field'],
          isset($value['section']) && isset($this->sections[$value['section']]) ? $this->sections[$value['section']]['section'] : 'sp',
          isset($value['section']) ? $value['section'] : 'settings',
          ['option' => $key, 'params' => $value, 'default' => NULL],
          array('label_for' => $key)
        );
    }
  }

  protected function validate_single($options) {
    foreach($options as $key => $value) $options[$key] = is_array($value) ? $this->validate_single($value) : sanitize_text_field($value);
    return($options);
  }

  public function validate_options($options) {
    if (!$options) $options = isset($_REQUEST['sp']) ? $_REQUEST['sp'] : [];
    if (is_array($options)) $options = $this->validate_single($options);
    else $options = sanitize_text_field($options);
    $options = array_merge(self::$params, $options);
    return($options);
  }

  public function render_section($params = null) {
    $section_id = $params['id'];
    //if (isset($params['title'])) print $params['title'].'<br/ >';
    if (isset($this->sections[$section_id]['description'])) print $this->sections[$section_id]['description'].'<br/ >';
  }

  // Render the license key field.
  public function render_license_key_field() {
    printf(
      '<input type="text" id="key" size="40" name="sp_license[key]" value="%s" />',
      isset($this->license['key']) ? esc_attr($this->license['key']) : ''
    );

    if (isset($this->license['status'])) {
      printf(
        '&nbsp;<span class="description">License %s</span>',
        isset($this->license['status']) ? esc_attr($this->license['status']) : 'is missing'
      );
    }
  }

  // Sanitize input from our plugin's option form and validate the provided key.
  public function license_key_callback($options) {
    if (!isset($options['key'])) return($this->license);
    if (isset($options['key']) && !$options['key']) {
      add_settings_error('sp_license', esc_attr('settings_updated'), __('License key is required', 'simple-payment'), 'error');
      return;
    }
    // Detect multiple sanitizing passes.
    // Workaround for: https://core.trac.wordpress.org/ticket/21989
    static $cache = null;
    if ($cache !== null) return $cache;

    // Get the current domain. This example validates keys against a node-locked
    // license policy, allowing us to lock our plugin to a specific domain.
    $domain = parse_url(get_bloginfo('url'), PHP_URL_HOST);

    // Validate the license key within the scope of the current domain.
    $key = sanitize_text_field($options['key']);
    try {
      $license = $this->validate_key($key);//, $domain);
    } catch (Exception $e) {
      $message = $e->getMessage();
      $code = $e->getCode();
      switch ($message) {
        // When the license has been activated, but the current domain is not
        // associated with it, return an error.
        case 'FINGERPRINT_SCOPE_MISMATCH': {
          add_settings_error('sp_license', esc_attr('settings_updated'), __('License is not valid on the current domain', 'simple-payment'), 'error');
          break;
        }
        // When the license has not been activated yet, return an error. This
        // shouldn't happen, since we should be activating the customer's domain
        // upon purchase - around the time we create their license.
        case 'NO_MACHINES':
        case 'NO_MACHINE': {
          add_settings_error('sp_license', esc_attr('settings_updated'), __('License has not been activated', 'simple-payment'), 'error');
          break;
        }
        // When the license key does not exist, return an error.
        case 'NOT_FOUND': {
          add_settings_error('sp_license', esc_attr('settings_updated'), __('License key was not found', 'simple-payment'), 'error');
          break;
        }
        default: {
          add_settings_error('sp_license', esc_attr('settings_updated'), __("Unhandled error:", 'simple-payment') . " {$message} ({$code})", 'error');
          break;
        }
        // Clear any options that were previously stored in the database.
      }
      return([]);
    }

    // Save result to local cache.
    $cache = [
      'policy' => $license['data']['relationships']['policy']['data']['id'],
      'key' => $license['data']['attributes']['key'],
      'expiry' => $license['data']['attributes']['expiry'],
      'valid' => $license['meta']['valid'],
      'status' => $license['meta']['detail'],
      'domain' => $domain,
      'meta' => []
    ];
    foreach ($license['data']['attributes']['metadata'] as $key => $value) {
      $cache['meta'][$key] = $value;
    }
    return($cache);
  }

  function render_setting_field($options) {
      $type = isset($options['params']['type']) ? $options['params']['type'] : 'string';
      switch ($type) {
        case 'select':
          if (!isset($options['params']['options'])) {
              $items = [];
              for ($i = $options['params']['min']; $i <= $options['params']['max']; $i++) {
                  $items[$i] = $i;
              }
              $options['params']['options'] = $items;
          }
          $this->setting_select_fn($options['option'], $options['params']);
          break;
        case 'check':
          $this->setting_check_fn($options['option'], $options['params']);
          break;
        case 'radio':
          $this->setting_radio_fn($options['option'], $options['params']);
          break;
        case 'textarea':
          $this->setting_textarea_fn($options['option'], $options['params']);
          break;
        case 'string':
        default:
          $this->setting_text_fn($options['option'], $options['params']);
          break;
      }
  }

  function param_name($key) {
    $keys = explode('.', $key);
    $keys = join('][', $keys);
    return('['.$keys.']');
  }

  function setting_select_fn($key, $params = null) {
  	$option = self::param($key);
  	$items = $params['options'];
    $field = $this->option_name.$this->param_name($key);
  	echo "<select id='$key' name='{$field}'>";
    $auto = isset($params['auto']) && $params['auto'];
    if ($auto) echo "<option value=''>".($auto ? __('Auto', 'simple-payment') : '')."</option>";
  	foreach ($items as $value => $title) {
  		$selected = ($option != '' && $option == $value) ? ' selected="selected"' : '';
      if (isset($params['display']) && $params['display'] == 'both') $title = $value.' - '.$title;
  		echo "<option value='$value'$selected>$title</option>";
  	}
  	echo "</select>";
  }

  function setting_radio_fn($key, $params = null) {
    $option = self::param($key);
    $items = $params['options'];
    $field = $this->option_name.$this->param_name($key);
    foreach($items as $value => $title) {
      $checked = ($option != '' && $option == $value) ? ' checked="checked"' : '';
      echo "<label><input ".$checked." value='$value' name='{$field}' type='radio' /> $title</label><br />";
    }
  }

  function setting_text_fn($key, $params = null) {
    $option = self::param($key);
    $field = $this->option_name.$this->param_name($key);
    echo "<input id='$key' name='{$field}' size='40' type='text' value='{$option}' />";
  }

  function setting_check_fn($key, $params = null) {
  	$option = self::param($key);
    $field = $this->option_name.$this->param_name($key);
  	echo "<input ".($option ? ' checked="checked" ' : '')." id='$key' value='true' name='{$field}' type='checkbox' />";
  }

  function setting_textarea_fn($key, $params = null) {
  	$option = self::param($key);
    $field = $this->option_name.$this->param_name($key);
  	echo "<textarea id='".$key."' name='{$field}' rows='7' cols='50' type='textarea'>{$option}</textarea>";
  }

  function setting_password_fn($key, $params = null) {
  	$option = self::param($key);
    $field = $this->option_name.$this->param_name($key);
  	echo "<input id='".$key."' name='{$field}' size='40' type='password' value='{$option}' />";
  }

/*
  // CHECKBOX - Name: plugin_options[chkbox2]
  function setting_chk2_fn() {
  	$options = get_option('plugin_options');
  	if($options['chkbox2']) { $checked = ' checked="checked" '; }
  	echo "<input ".$checked." id='plugin_chk2' name='plugin_options[chkbox2]' type='checkbox' />";
  }
  */

  function process($params = []) {
    $status = parent::process($params);
    do_action('sp_payment_process', $params);
    return($status);
  }

  function post_process($params = []) {
    if (parent::post_process($params)) {
      $this->update($this->engine->transaction, [
        'status' => self::TRANSACTION_SUCCESS
      ], true);
      do_action('sp_payment_post_process', $params);
      return(true);
    }
    return(false);
  }

  function pre_process($params = []) {
    $method = isset($_REQUEST['method']) ? strtolower(sanitize_text_field($_REQUEST['method'])) : null;
    $fields = ['engine', 'amount', 'product', 'concept', 'method', 'first_name', 'last_name', 'phone', 'mobile', 'address', 'address2', 'email', 'country', 'state', 'zipcode', 'payments', 'installments', 'cvv', 'expiration', 'card_number', 'currency', 'comment', 'city', 'tax_id', 'card_holder_name'];

    foreach ($fields as $field) if (isset($_REQUEST[$field]) && $_REQUEST[$field]) $params[$field] = sanitize_text_field($_REQUEST[$field]);

    if (!isset($params['concept']) && isset($params['product'])) $params['concept'] = $params['product'];
    if ($method) $params['method'] = $method;
    if (!isset($_REQUEST['full_name']) && (isset($params['first_name']) || isset($params['last_name']))) $params['full_name'] = (isset($params['first_name']) ? $params['first_name'] : '').' '.(isset($params['last_name']) ? $params['last_name'] : '');
    if (!isset($_REQUEST['card_holder']) && isset($_REQUEST['full_name'])) $params['card_holder'] = $params['full_name'];
    $params['payment_id'] = $this->payment($params);
    try {
      $process = parent::pre_process($params);
      do_action('sp_payment_pre_process', $params);
    } catch (Exception $e) {
      $this->update($params['payment_id'], [
        'status' => self::TRANSACTION_FAILED,
        'error_code' => $e->getCode(),
        'error_description' => $e->getMessage(),
      ]);
      return(false);
    }
    $this->update($params['payment_id'], ['status' => self::TRANSACTION_PENDING, 'transaction_id' => $this->engine->transaction]);
    return($process);
  }

  function recur($params = []) {
    if (parent::recur($params)) {
      do_action('sp_payment_recur', $params);
      return(true);
    }
    return(false);
  }

  function callback() {
    $info = parse_url($_SERVER["REQUEST_URI"]);
    $callback = parse_url($this->callback);
    if ($info['path'] != $callback['path']) return;
    if (!isset($_REQUEST['op'])) return;
    $url = null;
    $engine = isset($_REQUEST['engine']) ? sanitize_text_field($_REQUEST['engine']) : self::param('engine');
    $this->setEngine($engine);
    switch (strtolower(sanitize_text_field($_REQUEST['op']))) {
        case self::OPERATION_SUCCESS:
          $url = isset($_REQUEST['redirect_url']) && $_REQUEST['redirect_url'] ? esc_url_raw($_REQUEST['redirect_url']) : self::param('redirect_url');
          if (!$url) $url = $this->payment_page();
          if (!$url) $url = get_bloginfo('url');
          $url .= (strpos($url, '?') ? '&' : '?').http_build_query($_REQUEST);
          $this->post_process();
          break;
        case self::OPERATION_CANCEL:
          $url = $this->payment_page();
          $url .= (strpos($url, '?') ? '&' : '?').http_build_query($_REQUEST);
          break;
        case 'purchase':
        case 'payment':
        case 'redirect':
          if ($process = $this->pre_process()) {
            $process = $this->process($process);
            if ($process === true) die;
            $this->post_process($process);
            break;
          }
        case self::OPERATION_ERROR:
          $url = $this->payment_page();
          $status['op'] = 'fail';
          $url .= (strpos($url, '?') ? '&' : '?').http_build_query($status);
          break;
        case self::OPERATION_STATUS:
          $this->status($_REQUEST);
          die; break;
          break;
        case self::OPERATION_ZAPIER:
          echo 'OK';
          die; break;
          break;
        case 'css':
          header('Content-Type: text/css');
          echo self::param('css');
          die; break;
        case 'recur':

          die; break;
    }
    if ($url) { echo '<html><head><script type="text/javascript"> parent.location.replace("'.$url.'"); </script></head><body></body</html>'; die(); }
  }

  function shortcode($atts) {
      extract( shortcode_atts( array(
            'id' => null,
            'amount' => null,
            'product' => null,
            'fixed' => false,
            'title' => null,
            'type' => 'form',
            'enable_query' => false,
            'target' => null,
            'engine' => null,
            'redirect_url' => null,
            'method' => null,
            'form' => self::param('form_type'),
            'template' => null,
            'amount_field' => self::param('amount_field'),
            'product_field' => null,
      ), $atts ) );
      if (!$amount || !$product) {
          $id = $id ? $id : get_the_ID();
          if (!$amount) $amount = get_post_meta($id, $amount_field, true);
          if (!$product) $product = $product_field ? get_post_meta($id, $product_field, true) : get_the_title($id);
      }
      $params = [
          'amount' => $amount,
          'product' => $product,
          'engine' => $engine ? $engine : self::param('engine'),
          'method' => $method,
          'redirect_url' => $redirect_url,
      ];
      if ($enable_query) {
        if (isset($_REQUEST['full_name'])) $params['full_name'] = sanitize_text_field($_REQUEST['full_name']);
        if (isset($_REQUEST['phone'])) $params['phone'] = sanitize_text_field($_REQUEST['phone']);
        if (isset($_REQUEST['email'])) $params['email'] = sanitize_email($_REQUEST['email']);
      }
      switch ($type) {
          case 'button':
            $url = $this->callback;
            $params['op'] = 'redirect';
            return sprintf('<a class="btn" href="%1$s"'.($target ? ' target="'.$target.'"' : '').'>%2$s</a>',
                $url.'?'.http_build_query($params),
                esc_html( $title ? $title : 'Buy' ));
            break;
          default:
          case 'form':
              $template = 'form-'.$form;
              $plugin = get_file_data(__FILE__, array('Version' => 'Version'), false);
              wp_enqueue_script( 'simple-payment-js', plugin_dir_url( __FILE__ ).'assets/js/simple-payment.js', [], $plugin['Version'], true );
          case 'template':
            foreach ($params as $key => $value) set_query_var($key, $value);
            ob_start();
            if ($override_template = locate_template($template.'.php')) load_template($override_template);
            else load_template(SPWP_PLUGIN_DIR.'/templates/'.$template.'.php');
            return ob_get_clean();
            break;
      }
  }

  protected function payment($params) {
    global $wpdb;
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'sp_transactions';
    $result = $wpdb->insert($table_name, [
        'engine' => $this->engine->name,
        'currency' => $params['currency'] ? $params['currency'] : null,
        'amount' => $params['amount'],
        'concept' => $params['product'],
        'payments' => $params['payments'],
        'parameters' => json_encode($params),
        'url' => $_SERVER["HTTP_REFERER"],
        'status' => self::TRANSACTION_NEW,
        'user_id' => $user_id ? $user_id : null
    ]);
    return($result ? $wpdb->insert_id : false);
  }

  protected static function update($id, $params, $transaction_id = false) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sp_transactions';
    if (!isset($params['modified'])) $params['modified'] = current_time('mysql');
    $result = $wpdb->update($table_name, $params, [($transaction_id ? 'transaction_id' : 'id') => $id]);
    if ($result === false) throw new Exception(__("Couldn't update transaction: ") . $wpdb->last_error);
    return($result);
  }

  public function transactions() {
      global $wpdb, $list;
      add_screen_option('per_page', [
         'default' => 20,
         'option' => 'sp_per_page'
      ]);
      require(SPWP_PLUGIN_DIR.'/admin/transaction-list-table.php');
      $list = new Transaction_List();
  }

  public function info() {
      global $wpdb, $list;
      add_screen_option('per_page', [
         'default' => 20,
         'option' => 'sp_per_page'
      ]);
      if (!isset($_REQUEST['transaction_id']) || !isset($_REQUEST['engine'])) throw new Exception(__('Error fetching transaction'), 500);
      $id = sanitize_text_field($_REQUEST['transaction_id']);
      $engine = sanitize_text_field($_REQUEST['engine']);

      require(SPWP_PLUGIN_DIR.'/admin/transaction-list-table.php');
      $list = new Transaction_List($engine);
  }

  public function render_transactions() {
    global $list;
    require(SPWP_PLUGIN_DIR.'/admin/transactions.php');
  }

  public function render_transaction_log() {
    global $list;
    require(SPWP_PLUGIN_DIR.'/admin/transaction-log.php');
  }


  /**
   * Check if Gutenberg is active
   *
   * @since 1.0.0
   *
   * @return boolean
   */
    public function is_gutenberg_active() {
      return function_exists( 'register_block_type' );
    }

    /**
     * Load Simple Payment Text Domain.
     * This will load the translation textdomain depending on the file priorities.
     *      1. Global Languages /wp-content/languages/simple-payment/ folder
     *      2. Local dorectory /wp-content/plugins/simple-payment/languages/ folder
     *
     * @since  1.0.0
     * @return void
     */
    public function load_textdomain() {
      /**
       * Filters the languages directory path to use for AffiliateWP.
       *
       * @param string $lang_dir The languages directory path.
       */
      $lang_dir = apply_filters( 'sp_languages_directory', SPWP_PLUGIN_DIR . '/languages/' );

      load_plugin_textdomain( 'simple-payment', $lang_dir, str_replace(WP_PLUGIN_DIR, '', $lang_dir) );
    }

    public static function archive($id = null) {
      global $wpdb;
      self::update($id ? : $_REQUEST['transaction'], [
        'archived' => true
      ]);
      //wp_redirect( wp_get_referer() );
    }

    public function save($tablename, $params, $id = null) {
      global $wpdb;
      $result = $wpdb->insert($wpdb->prefix . 'sp_' . $tablename, $params);
      return($result != null ? $wpdb->insert_id : false);
    }

}

require_once('db/simple-payment-database.php');

$plugin = new SimplePaymentPlugin();
$plugin->load();
