<?php

use leewillis77\WpListTableExportable;

class Transaction_List extends WpListTableExportable\WpListTableExportable {

  public static $table_name = 'sp_transactions';

  public function __construct() {
    global $wpdb;

		parent::__construct( [
			'singular' => __( 'Transaction', 'simple-payment' ),
			'plural'   => __( 'Transaction', 'simple-payment' ),
			'ajax'     => false
		] );
    parent::__construct( );
    self::$table_name = $wpdb->prefix.self::$table_name;
	}


  protected function get_views() {
      $status_links = [
          "all"       => "<a href=''>".__("All", 'simple-payment')."</a>",
          "success" => "<a href='&status=success'>".__("Success", 'simple-payment')."</a>",
          "failed"   => "<a href='&status=failed'>".__("Failed", 'simple-payment')."</a>",
          "cancelled"   =>"<a href='&status=cancelled'>". __("Cancelled", 'simple-payment')."</a>"
      ];
      return($status_links);
  }

  function extra_tablenav( $which ) {
      global $wpdb;
      $move_on_url = '&engine=';
      if ($which == "top"){
          ?>
          <div class="alignleft actions bulkactions">
          <?php
          $options = $wpdb->get_results('SELECT `engine` AS `title` FROM '.self::$table_name.' GROUP BY `engine` ORDER BY `engine` ASC ', ARRAY_A);
          if ($options) {
              echo '<select name="engine" class="sp-filter-engine"><option value="">All Engines</option>';
              foreach ($options as $option) {
                  if ($option['title']) echo '<option value="'.$move_on_url . $cat['id'].'"'.( $_GET['engine'] == $option['id'] ? ' selected' : '').'>'.$option['title'].'</option>';
              }
              echo "</select>";
          }
          echo '</div>';
      }
      if ( $which == "bottom" ){
      }
  }

  public static function get_transactions( $per_page = 5, $page_number = 1, $instance = null ) {
    global $wpdb;
    if ($instance) {
      $orderby = $instance->get_pagination_arg('orderby');
      $order = $instance->get_pagination_arg('order');
    } else {
      $order = 'ASC';
    }
    $sql = "SELECT * FROM ".self::$table_name;
    if ( ! empty( $_REQUEST['orderby'] ) || isset($orderby) ) {
      $sql .= ' ORDER BY ' . (isset($_REQUEST['orderby']) && ! empty($_REQUEST['orderby']) ? esc_sql ($_REQUEST['orderby']) : $orderby) ;
      $sql .= isset($_REQUEST['order']) && !empty($_REQUEST['order']) ? ' '.esc_sql($_REQUEST['order']) : ' '.$order;
    }
    $sql .= " LIMIT $per_page";
    $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;
    $result = $wpdb->get_results( $sql , 'ARRAY_A' );
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
    $archive_nonce = wp_create_nonce( 'sp_archive_transaction' );
    $title = '<strong>' . $item['id'] . '</strong>';
    $actions = [
      'archive' => sprintf( '<a href="?page=%s&action=%s&transaction=%s&_wpnonce=%s">Archive</a>', esc_attr( $_REQUEST['page'] ), 'archive', absint( $item['id'] ), $archive_nonce )
    ];
    return $title.$this->row_actions( $actions );
  }

  protected function get_bulk_actions() {
		$actions = array(
			'archive' => __( 'Archive', 'simple-payment' ),
		);
		return $actions;
	}

  public function column_default($item, $column_name) {
      return($item[$column_name]);
  }

  public function column_user_id($item) {
    if (!$item) return('');
    $user = get_userdata($item['user_id']);
    return($user ? $user->display_name : '');
  }

  public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->_args['singular'],
			$item['id']
		);
	}

  function get_columns() {
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
      'transaction_id'    => __( 'Transaction ID', 'simple-payment' ),
      'user_id'    => __( 'User', 'simple-payment' ),
      'url'    => __( 'URL', 'simple-payment' ),
      'parameters'    => __( 'Parameters', 'simple-payment' ),
      'error_code'    => __( 'Error', 'simple-payment' ),
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
      'transaction_id' => array( 'transaction_id', false ),
      'user_id' => array( 'user_id', false ),
      'error_code' => array( 'error_code', false ),
      'modified' => array( 'modified', false ),
      'created' => array( 'created', false ),
    );
    return($sortable_columns);
  }

  public function process_bulk_action() {
    if ( 'archive' === $this->current_action() ) {
      $nonce = esc_attr( $_REQUEST['_wpnonce'] );
      if ( ! wp_verify_nonce( $nonce, 'sp_archive_transaction' ) ) {
        die( 'Go get a life script kiddies' );
      } else {
        // TODO: implement function
        die('process archive');
        self::archive_transaction( absint( $_GET['transaction'] ) );
        wp_redirect( esc_url( add_query_arg() ) );
        exit;
      }
    }
    // If the delete bulk action is triggered
    if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-archive' )
         || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-archive' )
    ) {
      $archive_ids = esc_sql( $_POST['id'] );
      die('process bulk-archive');

      // loop over the array of record IDs and delete them
      // TODO: implement function
      foreach ( $archive_ids as $id ) {
        self::archive_transaction( $id );
      }
      wp_redirect( esc_url( add_query_arg() ) );
      exit;
    }
  }

  public function prepare_items() {
    $current_screen = get_current_screen();
    // TODO: make sure why it should work with this function
    // $this->_column_headers = $this->get_column_info();
    $this->_column_headers = array(
    	 $this->get_columns(),		// columns
    	 array(),			// hidden
    	 $this->get_sortable_columns(),	// sortable
    );
    $this->process_bulk_action();

    $per_page     = $this->get_items_per_page( 'transactions_per_page', 20 );
    $current_page = $this->get_pagenum();
    $total_items  = self::record_count();
    $this->set_pagination_args([
      'total_items' => $total_items,
      'per_page'    => $per_page,
      'orderby' => 'id',
      'order' => 'DESC',
      'offset' => ( $this->get_pagenum() - 1 ) * $per_page,
    ]);

    $this->items = self::get_transactions( $per_page, $current_page, $this );
  }

}
