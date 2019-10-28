<?php

use leewillis77\WpListTableExportable;

class Transaction_List extends WpListTableExportable\WpListTableExportable {

  public static $table_name = 'sp_transactions';
  public static $views_rendered = false;
  public static $engine;
  public static $columns;

  public function __construct($engine = null) {
    global $wpdb;
		parent::__construct( [
			'singular' => __( 'Transaction', 'simple-payment' ),
			'plural'   => __( 'Transaction', 'simple-payment' ),
			'ajax'     => false
		] );
    self::$engine = $engine;
    self::$table_name = $engine ? $wpdb->prefix.'sp_'.strtolower('history') : $wpdb->prefix.self::$table_name;
	}

  protected function is_export() {
    return(!empty($_GET['wlte_export']));
  }

  protected function get_views() {
      if (self::$engine) return;
      $status_links = [
          "all"       => "<a href='?page=simple-payments'>".__("All", 'simple-payment')."</a>",
          "success" => "<a href='?page=simple-payments&status=success'>".__("Success", 'simple-payment')."</a>",
          "failed"   => "<a href='?page=simple-payments&status=failed'>".__("Failed", 'simple-payment')."</a>",
          "cancelled"   =>"<a href='?page=simple-payments&status=cancelled'>". __("Cancelled", 'simple-payment')."</a>",
          "pending"   =>"<a href='?page=simple-payments&status=pending'>". __("Pending", 'simple-payment')."</a>",
          "archived"   =>"<a href='?page=simple-payments&archive=true'>". __("Archived", 'simple-payment')."</a>"
      ];
      return($status_links);
  }

  public function views() {
      if (self::$views_rendered) return;
      parent::views();
      self::$views_rendered = true;
  }

  function extra_tablenav( $which ) {
      if (self::$engine) return;
      global $wpdb;
      if ($which == "top"){
          ?>
          <div class="alignleft actions bulkactions">
          <?php
          $engine = isset($_REQUEST['engine']) && $_REQUEST['engine'] ? sanitize_text_field( $_REQUEST['engine'] ) : null;
          $options = $wpdb->get_results('SELECT `engine` AS `title` FROM '.self::$table_name.' GROUP BY `engine` ORDER BY `engine` ASC ', ARRAY_A);
          if ($options) {
              echo '<select name="engine" class="sp-filter-engine" onchange="location.href=this.value;"><option value="'.remove_query_arg('engine').'">'.__('All Engines', 'simple-payment').'</option>';
              foreach ($options as $option) {
                  $url = add_query_arg([
                      'engine' => $option['title']
                  ]);
                  if ($option['title']) echo '<option value="'.$url.'"'.( $engine == $option['title'] ? ' selected' : '').'>'.$option['title'].'</option>';
              }
              echo "</select>";
          }
          echo '</div>';
      }
      if ( $which == "bottom" ){
      }
  }

  public static function get_transactions( $per_page = 5, $page_number = 1, $instance = null, $count = false) {
    global $wpdb;
    if ($instance && !self::$engine) {
      $orderby = $instance->get_pagination_arg('orderby');
      $order = $instance->get_pagination_arg('order');
    } else {
      $orderby = 'id';
      $order = 'DESC';
    }
    if ($count) $sql = "SELECT COUNT(*) FROM ".self::$table_name;
    else $sql = "SELECT * FROM ".self::$table_name;
    $where = [];
    if ( ! empty( $_REQUEST['transaction_id'] ) ) $where[] = "`transaction_id` =  '" .esc_sql($_REQUEST['transaction_id'])."'";

    if ( ! empty( $_REQUEST['status'] ) ) $where[] = "`status` =  '" .esc_sql($_REQUEST['status'])."'";

    if (!self::$engine) {
      $where[] = "`archived` = ".(!empty($_REQUEST['archive']) ? '1' : 0);
      if ( ! empty( $_REQUEST['engine'] ) ) $where[] = "`engine` =  '" .esc_sql($_REQUEST['engine'])."'";
    }

    if ( ! empty( $_REQUEST['s'] ) ) {
      $where[] = "`transaction_id` LIKE '%" .esc_sql($_REQUEST['s'])."%' OR `concept` LIKE '%" .esc_sql($_REQUEST['s'])."%'";
    }

    if (count($where) > 0) $sql .=  ' WHERE '.implode(' AND ', $where);
    if ($count) {
      return($wpdb->get_var($sql));
    }
    if ( ! empty( $_REQUEST['orderby'] ) || isset($orderby) ) {
      $sql .= ' ORDER BY ' . (isset($_REQUEST['orderby']) && ! empty($_REQUEST['orderby']) ? esc_sql ($_REQUEST['orderby']) : $orderby) ;
      $sql .= isset($_REQUEST['order']) && !empty($_REQUEST['order']) ? ' '.esc_sql($_REQUEST['order']) : ' '.$order;
    }
    $sql .= " LIMIT $per_page";
    $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;
    $result = $wpdb->get_results( $sql , 'ARRAY_A' );
    if ($result) self::$columns = array_keys($result[0]);
    return($result);
  }

  public static function record_count() {
    global $wpdb;
    $sql = "SELECT COUNT(*) FROM ".self::$table_name;
    return($wpdb->get_var($sql));
  }

  public function no_items() {
    _e( 'No transactions avaliable.', 'simple-payment' );
  }

  function column_id( $item ) {
    if (self::$engine || $this->is_export()) return($item['id']);
    $archive_nonce = wp_create_nonce( 'sp_archive_transaction' );
    $title = '<strong>' . $item['id'] . '</strong>';
    $actions = [
      'archive' => sprintf( '<a href="%s">%s</a>', add_query_arg([
          'action' => 'archive',
          'id' => absint($item['id']),
          '_wpnonce' => $archive_nonce
      ]), __('Archive', 'simple-payment') ),
      'details' => sprintf( '<a href="?page=simple-payments-details&id=%s&transaction_id=%s&engine=%s">%s</a>', $item['id'], $item['transaction_id'], $item['engine'], __('Details', 'simple-payment') )

    ];
    return $title.$this->row_actions( $actions );
  }

  protected function get_bulk_actions() {
    if (self::$engine) return;
		$actions = array(
			'bulk-archive' => __( 'Archive', 'simple-payment' ),
		);
		return $actions;
	}

  public function column_default($item, $column_name) {
    $value = $item[$column_name];
    if ($this->is_export()) return($value);
      if (strlen($value) > 40) {
        add_thickbox();
        $type = strpos($value, '://') !== FALSE ? 'url' : '';
        $type = json_decode($value) && json_last_error() === JSON_ERROR_NONE ? 'json' : $type;
        $id = 'tbox-'.$column_name.'-'.$item['id'];
        $href = "#TB_inline?&width=600&height=550&inlineId=".$id;
        $value = '<a href="'.$href.'" title="'.$column_name.'" class="thickbox">'.substr($value, 0, 30).'...</a>';
        $value .= '<div id="'.$id.'" style="display:none;"><pre class="'.$type.'">'.$item[$column_name].'</pre></div>';
      }
      return($value);
  }

  public function column_user_id($item) {
    if (!$item) return('');
    $user = get_userdata($item['user_id']);
    return($user ? $user->display_name : '');
  }

  public function column_cb( $item ) {
    if ($this->is_export()) return($item['id']);
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			'id',
			$item['id']
		);
	}

  function get_columns() {
    if (self::$engine && self::$columns) {
      $cols = [];
      foreach (self::$columns as $key) $cols[$key] = __($key, 'simple-payments');
      return($cols);
    }
    return(self::define_columns());
  }

  public static function define_columns() {
    $columns = [
      'cb' => '<input type="checkbox" />',
      'id'    => __( 'ID', 'simple-payment' ),
      'concept' => __( 'Concept', 'simple-payment' ),
      'amount'    => __( 'Amount', 'simple-payment' ),
      'engine'    => __( 'Engine', 'simple-payment' ),
      'status'    => __( 'Status', 'simple-payment' ),
      'payments'    => __( 'Payments', 'simple-payment' ),
      'transaction_id'    => __( 'Transaction ID', 'simple-payment' ),
      'user_id'    => __( 'User', 'simple-payment' ),
      'url'    => __( 'URL', 'simple-payment' ),
      'parameters'    => __( 'Parameters', 'simple-payment' ),
      'error_code'    => __( 'Error', 'simple-payment' ),
      'sandbox'    => __( 'Sandbox', 'simple-payment' ),
      'modified'    => __( 'Modified', 'simple-payment' ),
      'created'    => __( 'Created', 'simple-payment' ),
    ];
    return($columns);
	}

  public function get_sortable_columns() {
    $sortable_columns = array(
      'id' => array( 'id', true ),
      'concept' => array( 'concept', false ),
      'amount' => array( 'amount', false ),
      'engine' => array( 'engine', false ),
      'status' => array( 'status', false ),
      'url' => array( 'url', false ),
      'transaction_id' => array( 'transaction_id', false ),
      'user_id' => array( 'user_id', false ),
      'error_code' => array( 'error_code', false ),
      'parameters' => array( 'parameters', false ),
      'sandbox' => array( 'sandbox', false ),
      'modified' => array( 'modified', false ),
      'created' => array( 'created', false ),
    );
    return($sortable_columns);
  }

  public function archive_transaction($id) {
    SimplePaymentPlugin::archive($id);
  }

  public function process_bulk_action() {
    if ( 'archive' === $this->current_action() ) {
      $nonce = esc_attr( $_REQUEST['_wpnonce'] );
      if ( ! wp_verify_nonce( $nonce, 'sp_archive_transaction' ) ) {
        die( 'Go get a life script kiddies' );
      } else {
        self::archive_transaction( absint( $_GET['id'] ) );
        //wp_redirect( wp_get_referer() );
        return;
      }
    }
    // If the delete bulk action is triggered
    if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-archive' )
         || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-archive' )
    ) {
      $archive_ids = esc_sql( $_POST['id'] );
      foreach ( $archive_ids as $id ) {
        self::archive_transaction( $id );
      }
      //wp_redirect( wp_get_referer() );
      return;
    }
  }

  public function prepare_items() {
    $this->process_bulk_action();

    $screen = get_current_screen();
    $per_page = get_user_meta(get_current_user_id(), $screen->get_option('per_page', 'option'), true);
    $per_page     = $this->get_items_per_page( 'per_page', $screen->get_option('per_page', 'default'));
    $per_page = $per_page ? : 20;
    $current_page = $this->get_pagenum();

    $this->set_pagination_args([
      'per_page'    => $per_page,
      'orderby' => 'id',
      'order' => 'DESC',
    ]);

    $this->items = self::get_transactions( $per_page, $current_page, $this );
    $total_items = self::get_transactions( 0, 0, $this, true );

    $this->_column_headers = $this->get_column_info();
    $this->_column_headers = [
      $this->get_columns(),
      [],
      $this->get_sortable_columns()
    ];
    $this->set_pagination_args([
      'total_items' => $total_items,
      'per_page'    => $per_page,
      'orderby' => 'id',
      'order' => 'DESC',
      'offset' => ( $this->get_pagenum() - 1 ) * $per_page,
    ]);

  }

}
