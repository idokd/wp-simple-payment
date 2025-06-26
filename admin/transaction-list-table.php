<?php

use leewillis77\WpListTableExportable;

class Transaction_List extends WpListTableExportable\WpListTableExportable {

  public static $table_name = 'sp_transactions';
  public static $views_rendered = false;
  public static $details;
  public static $columns;

  public function __construct($details = false) {
    global $wpdb;
		parent::__construct( [
			'singular' => __( 'Transaction', 'simple-payment' ),
			'plural'   => __( 'Transactions', 'simple-payment' ),
			'ajax'     => false
		] );
    self::$details = $details;
    self::$table_name = $details ? $wpdb->prefix.'sp_'.strtolower('history') : $wpdb->prefix.self::$table_name;
    $this->export_button_text = __( 'Export', 'simple-payment' );
    $this->process_bulk_action();
  }

  protected function is_export() {
    return( !empty( $_GET[ 'wlte_export' ] ) ? sanitize_text_field( $_GET[ 'wlte_export' ] ) : false);
  }

  protected function get_views() {
      if (self::$details) return;
      // TODO: consider replacing the status query para of the current url, not building it from scratch - in order to respect other parameters
      $status_links = [
          "all"       => "<a href='?page=simple-payments'>".__("All", 'simple-payment')."</a>",
          "success" => "<a href='?page=simple-payments&status=success'>".__("Success", 'simple-payment')."</a>",
          "failed"   => "<a href='?page=simple-payments&status=failed'>".__("Failed", 'simple-payment')."</a>",
          "cancelled"   =>"<a href='?page=simple-payments&status=cancelled'>". __("Cancelled", 'simple-payment')."</a>",
          "pending"   =>"<a href='?page=simple-payments&status=pending'>". __("Pending", 'simple-payment')."</a>",
          "created"   =>"<a href='?page=simple-payments&status=created'>". __("Created", 'simple-payment')."</a>",
          "archived"   =>"<a href='?page=simple-payments&archive=true'>". __("Archived", 'simple-payment')."</a>"
      ];
      return( apply_filters( 'sp_admin_table_view', $status_links ) );
  }

  public function views() {
      if ( self::$views_rendered ) return;
      parent::views();
      self::$views_rendered = true;
  }

  function extra_tablenav( $which ) {
      if ( self::$details ) return;
      global $wpdb;
      if ( $which == "top" ) {
          if ( isset( $_REQUEST[ 'page' ] ) ) echo '<input type="hidden" name="page" value="' . esc_attr( sanitize_text_field( $_REQUEST[ 'page' ] ) ) . '" />';
          if ( isset( $_REQUEST[ 'status' ] ) ) echo '<input type="hidden" name="status" value="' . esc_attr( sanitize_text_field( $_REQUEST[ 'status' ] ) ) . '" />';
          ?>
          <div class="alignleft actions">
          <?php
          $engine = isset( $_REQUEST[ 'engine' ] ) && $_REQUEST[ 'engine' ] ? sanitize_text_field( $_REQUEST[ 'engine' ] ) : null;
          $options = $wpdb->get_results( 'SELECT `engine` AS `title` FROM ' . self::$table_name . ' GROUP BY `engine` ORDER BY `engine` ASC ', ARRAY_A );
          if (count($options) > 1) {
              echo '<select id="engine" class="sp-filter-engine" name="engine"><option value="">' . __( 'All Engines', 'simple-payment' ) . '</option>';
              foreach ($options as $option) {
                  if ( $option[ 'title' ] ) echo '<option value="' . esc_attr( $option[ 'title' ] ) . '"' . ( $engine == $option[ 'title' ] ? ' selected' : '' ) . '>' . esc_html( $option[ 'title' ] ) . '</option>';
              }
              echo "</select>";
          }
          echo '<label for="from-date">Date Range:</label><input type="date" name="created_from" id="from-date" value="' . ( isset( $_REQUEST[ 'created_from' ] ) ? $_REQUEST[ 'created_from' ] : '' ) . '" /><input type="date" name="created_to" id="to-date" value="' . ( isset( $_REQUEST[ 'created_to' ] ) ? $_REQUEST[ 'created_to' ] : '' ) . '" />';
          echo '<input type="submit" name="filter_action" id="transaction-query-submit" class="button" value="' . __( 'Filter', 'simple-payment' ) . '">';
          echo '</div>';
        }
      if ( $which == "bottom" ){
      }
  }

  public static function get_transactions( $per_page = 5, $page_number = 1, $instance = null, $count = false) {
    global $wpdb;
    $orderby = 'id';
    $order = 'DESC';
    if ( $instance && !self::$details ) {
      $orderby = $instance->get_pagination_arg( 'orderby' );
      $order = $instance->get_pagination_arg( 'order' );
    }
    if ( !$orderby ) $orderby = 'id';
    if ( !$order ) $order = 'DESC';
    if ($count) $sql = "SELECT COUNT(*) FROM ".self::$table_name;
    else $sql = "SELECT * FROM " . self::$table_name;
    $where = [];
    if ( ! empty( $_REQUEST[ 'id' ] ) && empty( $_REQUEST[ 'action' ] ) ) $where[] = "`payment_id` = " . esc_sql( absint( $_REQUEST[ 'id' ] ) );
    if ( ! empty( $_REQUEST[ 'transaction_id' ] ) && isset( $_REQUEST[ 'engine' ] ) && $_REQUEST[ 'engine' ] ) $where[] = "`transaction_id` =  '" . esc_sql( $_REQUEST[ 'transaction_id' ] ) . "'";
    
    if ( ! empty( $_REQUEST[ 'status' ] ) ) $where[] = "`status` =  '" . esc_sql( $_REQUEST[ 'status' ] ) . "'";
    if ( ! empty( $_REQUEST[ 'user_id' ] ) ) $where[] = "`user_id` =  '" . esc_sql( $_REQUEST[ 'user_id' ] ) . "'";

    if ( !self::$details ) {
      $where[] = "`archived` = " . ( !empty( $_REQUEST[ 'archive' ] ) ? '1' : 0 );
      if ( ! empty( $_REQUEST[ 'engine' ] ) ) $where[] = "`engine` =  '" . esc_sql( $_REQUEST[ 'engine' ] ) . "'";
    }

    if ( ! empty( $_REQUEST[ 's' ] ) ) {
      $where[] = "`transaction_id` LIKE '%" .esc_sql( $_REQUEST[ 's' ] ) . "%' OR `concept` LIKE '%" . esc_sql( $_REQUEST[ 's' ] ) . "%'";
    }

    if ( ! empty( $_REQUEST[ 'created_from' ] ) ) {
      $where[] = "`created` >= '" . esc_sql( $_REQUEST[ 'created_from' ] ) . " 00:00:00'";
    }
    if ( ! empty( $_REQUEST['created_to' ] ) ) {
      $where[] = "`created` <= '".esc_sql( $_REQUEST[ 'created_to' ] ) . " 23:59:59'";
    }
    if ( count( $where ) > 0 ) $sql .=  ' WHERE ' . implode( ' AND ', $where );
    if ( $count ) {
      return( $wpdb->get_var( $sql ) );
    }
    if ( ! empty( $_REQUEST[ 'orderby' ] ) || isset( $orderby ) ) {
      $sql .= ' ORDER BY ' . ( isset( $_REQUEST[ 'orderby' ] ) && ! empty( $_REQUEST[ 'orderby' ] ) ? esc_sql ( $_REQUEST[ 'orderby' ] ) : $orderby ) ;
      $sql .= isset( $_REQUEST[ 'order' ] ) && !empty( $_REQUEST[ 'order' ] ) ? ' ' . esc_sql( $_REQUEST[ 'order' ] ) : ' '. $order;
    }
    if ( $per_page > 0 ) {
      $sql .= " LIMIT $per_page";
      $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;
    }
    $result = $wpdb->get_results( $sql , 'ARRAY_A' );
    if ( $result ) self::$columns = array_keys( $result[ 0 ] );
    return( $result );
  }

  public static function record_count() {
    global $wpdb;
    $sql = "SELECT COUNT(*) FROM ".self::$table_name;
    return( $wpdb->get_var( $sql ) );
  }

  public function no_items() {
    _e( 'No transactions avaliable.', 'simple-payment' );
  }

  function column_id( $item ) {
    if ( self::$details || $this->is_export() ) return( $item[ 'id' ] );
    $title = '<strong>' . $item[ 'id' ] . '</strong>';
    if ( isset( $item[ 'archived' ] ) && $item[ 'archived' ]) {
      $unarchive_nonce = wp_create_nonce( 'unarchive_' . $this->_args[ 'singular' ] );
      $actions = [
        'unarchive' => sprintf( '<a href="%s">%s</a>', add_query_arg( [
            'action' => 'unarchive',
            'id' => absint( $item[ 'id' ] ),
            '_wpnonce' => $unarchive_nonce
        ], menu_page_url( 'simple-payments', false ) ), __( 'Unarchive', 'simple-payment' ) )
      ];
    } else {
      $archive_nonce = wp_create_nonce( 'archive_' . $this->_args[ 'singular' ] );
      $verify_nonce = wp_create_nonce( 'verify_' . $this->_args[ 'singular' ] );
      $actions = [
        'archive' => sprintf( '<a href="%s">%s</a>', add_query_arg( [
            'action' => 'archive',
            'id' => absint( $item[ 'id' ] ),
            '_wpnonce' => $archive_nonce
        ], menu_page_url( 'simple-payments', false ) ), __( 'Archive', 'simple-payment' ) ),
        'verify' => sprintf( '<a href="%s">%s</a>', add_query_arg( [
          'action' => 'verify',
          'id' => absint( $item[ 'id' ] ),
          '_wpnonce' => $verify_nonce
        ], menu_page_url( 'simple-payments', false ) ), __( 'Verify', 'simple-payment' ) ),
      ];
    }
    $actions[ 'details' ] = sprintf( '<a href="?page=simple-payments-details&id=%s&engine=%s">%s</a>', $item[ 'id' ], $item[ 'engine' ], __( 'Details', 'simple-payment' ) );
    return( $title . $this->row_actions( $actions ) );
  }

  protected function get_bulk_actions() {
    if ( self::$details ) return;
    if ( isset( $_REQUEST[ 'archive' ] ) && $_REQUEST[ 'archive' ] ) {
      $actions = [
        'bulk-unarchive' => __( 'Unarchive', 'simple-payment' ),
      ];
    } else {
      $actions = [
        'bulk-verify' => __( 'Verify', 'simple-payment' ),
        'bulk-archive' => __( 'Archive', 'simple-payment' ),
      ];
    }
		return($actions);
	}

  public function column_default($item, $column_name) {
    $value = isset( $item[ $column_name ] ) ? $item[ $column_name ] : null;
    if ( strpos( $column_name, '.' ) > 0 ) { // Fetch a json path
      $query = substr( $column_name, strpos( $column_name, '.' ) + 1 );
      $main_column_name = substr( $column_name, 0, strpos( $column_name, '.' ) );
      $value = isset( $item[ $main_column_name ] ) ? $item[ $main_column_name ] : null;
      $jsonq = new Nahid\JsonQ\Jsonq( $value );
      $value = $jsonq->find( $query )->toArray();
      if ( !$value || !count( $value ) ) $value = '';
      else $value = $value[0];
    }

    if ( $this->is_export() ) return( apply_filters( 'sp_list_table_column_value', $value, $column_name, $item, $this ) );
    if ( strlen( $value ) > 40 ) {
        add_thickbox();
        $type = strpos($value, '://') < 10 ? 'url' : '';
        $type = json_decode($value) && json_last_error() === JSON_ERROR_NONE ? 'json' : $type;
        if (!$type) $type = is_string($value) && strpos($value, '<?xml') === 0 ? 'xml' : $type;
        $id = 'tbox-'.$column_name.'-'.$item['id'];
        $href = "#TB_inline?&width=600&height=550&inlineId=".$id;
        $value = '<a href="'.$href.'" title="'.$column_name.'" class="thickbox">'.substr( htmlentities( $value ), 0, 30 ).'...</a><div id="'.$id.'" style="display:none;"><pre class="'.$type.'">'.htmlentities( $value ).'</pre></div>';
    }
    return( apply_filters( 'sp_list_table_column_value', $value, $column_name, $item, $this ) );
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
    if (self::$details && self::$columns) {
      $cols = [];
      foreach (self::$columns as $key) $cols[$key] = __($key, 'simple-payments');
      return( apply_filters( 'sp_list_table_columns', $cols, $this ) );
    }
    return(  apply_filters( 'sp_list_table_columns', self::define_columns(), $this ) );
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
      'confirmation_code'    => __( 'Confirmation Code', 'simple-payment' ),
      'user_id'    => __( 'User', 'simple-payment' ),
      'url'    => __( 'URL', 'simple-payment' ),
      'parameters'    => __( 'Parameters', 'simple-payment' ),
      'ip_address'    => __( 'IP Address', 'simple-payment' ),
      'user_agent'    => __( 'User Agent', 'simple-payment' ),
      'error_code'    => __( 'Error', 'simple-payment' ),
      'token'    => __( 'Token', 'simple-payment' ),
      'sandbox'    => __( 'Sandbox', 'simple-payment' ),
      'modified'    => __( 'Modified', 'simple-payment' ),
      'created'    => __( 'Created', 'simple-payment' ),
    ];
    return( $columns );
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
      'confirmation_code' => array( 'confirmation_code', false ),
      'user_id' => array( 'user_id', false ),
      'error_code' => array( 'error_code', false ),
      'parameters' => array( 'parameters', false ),
      'ip_address' => array( 'ip_address', false ),
      'sandbox' => array( 'sandbox', false ),
      'modified' => array( 'modified', false ),
      'created' => array( 'created', false ),
    );
    return( $sortable_columns );
  }

  public function verify_transaction( $id ) {
    add_action( 'sp_payment_verify', function( $params ) {
      set_transient( 'sp_message', sprintf( __( "Verification result for %s:<br />%s", 'simple-payment' ),  $params[ 'id' ], json_encode( $params ) ) );
    } );
    $result = SimplePaymentPlugin::verify( $id );
  }

  public function archive_transaction($id) {
    SimplePaymentPlugin::archive($id);
  }

  public function unarchive_transaction($id) {
    SimplePaymentPlugin::unarchive($id);
  }

  public function process_bulk_action() {
    global $sp_bulk_processed;
    if ( isset( $sp_bulk_processed ) && $sp_bulk_processed ) return;
    $sp_bulk_processed = true;
    if ( in_array( $this->current_action(), [ 'archive', 'unarchive', 'verify' ] ) ) {
      $nonce = esc_attr( $_REQUEST['_wpnonce'] );
      if ( !wp_verify_nonce( $nonce, $this->current_action() . '_' . $this->_args[ 'singular' ] ) ) {
        die( 'Go get a life script kiddies' );
      } 
      switch ( $this->current_action() ) {
        case 'verify':
          self::verify_transaction( absint( $_GET[ 'id' ] ) );
          break;
        case 'archive':
          self::archive_transaction( absint( $_GET[ 'id' ] ) );
          break;
        case 'unarchive':
          self::unarchive_transaction( absint( $_GET[ 'id' ] ) );
          break;
      }
      
      wp_safe_redirect( wp_get_referer() );
      return;
    }
    // If the delete bulk action is triggered
    if ( ( isset( $_REQUEST[ 'action' ] ) && $_REQUEST[ 'action' ] && $_REQUEST[ 'action' ] != '-1' )
         || ( isset( $_REQUEST[ 'action2' ] ) && $_REQUEST[ 'action2' ]  && $_REQUEST[ 'action2' ] != '-1' )
    ) {
      $nonce = esc_attr( $_REQUEST[ '_wpnonce' ] );
      if ( ! wp_verify_nonce( $nonce, 'bulk-'.$this->_args[ 'plural' ] ) ) {
        die( 'Go get a life script kiddies' );
      }
      $ids = isset( $_REQUEST[ 'id' ] ) ? esc_sql( $_REQUEST[ 'id' ] ) : [];
      $action = isset( $_REQUEST[ 'action' ] ) && $_REQUEST[ 'action' ] != -1 ? $_REQUEST[ 'action' ] : $_REQUEST[ 'action2' ];
      foreach ( $ids as $id ) {
        switch ($action) {
          case 'bulk-verify':
            self::verify_transaction( $id );
            break;
          case 'bulk-archive':
            self::archive_transaction( $id );
            break;
          case 'bulk-unarchive':
            self::unarchive_transaction( $id );
            break;
        }
      }
      wp_safe_redirect( wp_get_referer() );
      return;
    }
  }

  public function prepare_items() {
    $this->process_bulk_action();

    if ($this->is_export()) {
      $per_page = -1;
      $current_page = 0;
    } else {
      $screen = get_current_screen();
      $per_page = get_user_meta(get_current_user_id(), $screen->get_option('per_page', 'option'), true);
      $per_page = $per_page ? $per_page : $this->get_items_per_page( 'per_page', $screen->get_option('per_page', 'default'));
      // $per_page = $per_page ? $per_page : 20;
      $current_page = $this->get_pagenum();

      $total_items = self::get_transactions( 0, 0, $this, true );

      $this->set_pagination_args([
        'total_items' => $total_items,
        'per_page'    => $per_page,
        'orderby' => 'id',
        'order' => 'DESC',
        'offset' => ( $this->get_pagenum() - 1 ) * $per_page,
      ]);

    }
    
    /*$this->set_pagination_args([
      'per_page'    => $per_page,
      'orderby' => 'id',
      'order' => 'DESC',
    ]);*/

    $this->items = self::get_transactions( $per_page, $current_page, $this );

    $this->_column_headers = $this->get_column_info();
    $this->_column_headers = [
      $this->get_columns(),
      [],
      $this->get_sortable_columns()
    ];

  }

}
