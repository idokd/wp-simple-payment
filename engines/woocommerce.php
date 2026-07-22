<?php
namespace SimplePayment\Engines;

use SimplePayment\SimplePayment;
use Exception;

if ( !defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

/**
 * WooCommerce (third party) payment engine.
 *
 * Instead of charging a card locally, this engine takes the current 'purchase'
 * and creates it as an Order on a remote (third party) WooCommerce website using
 * the WooCommerce REST API (consumer key / consumer secret).
 *
 * The remote site is expected to return, on the create-order response, a redirect
 * parameter (by default WooCommerce's `payment_url`, but the field name is
 * configurable) which is then used to open an iframe / popup on the current
 * website, so the purchase is actually being paid as a different 'order' on the
 * third party website.
 *
 * The success / cancel / error / status callback URLs of the current website are
 * sent along to the remote order (both as first class fields and as order
 * meta_data) so the third party can redirect the customer back once the payment
 * is done, cancelled or has failed.
 */
class WooCommerce extends Engine {

  public static $name = 'WooCommerce';
  public $interactive = true;

  // Advertise the display modes the checkout form may use for the redirect url.
  public static $supports = [ 'iframe', 'modal' ];

  // Remote hosts are added dynamically (see constructor) so safe_redirect allows them.
  public static $domains = [];

  // WooCommerce REST API namespace, appended to the configured site url.
  protected $namespace = 'wc/v3';

  protected $url;
  protected $consumer_key;
  protected $consumer_secret;

  public function __construct( $params = null, $handler = null, $sandbox = true ) {
    parent::__construct( $params, $handler, $sandbox );
    $this->url = untrailingslashit( trim( (string) $this->param( 'url' ) ) );
    $this->consumer_key = $this->param( 'consumer_key' );
    $this->consumer_secret = $this->param( 'consumer_secret' );
    $ns = $this->param( 'namespace' );
    if ( $ns ) $this->namespace = trim( $ns, '/' );
    // Allow redirecting the browser to the remote host when safe_redirect is on.
    $host = $this->url ? wp_parse_url( $this->url, PHP_URL_HOST ) : null;
    if ( $host && !in_array( $host, self::$domains ) ) self::$domains[] = $host;
  }

  /**
   * Create the remote WooCommerce order and return the response, that is expected
   * to hold the redirect (payment) url to open in the iframe / popup.
   */
  public function pre_process( $params ) {
    if ( !$this->url || !$this->consumer_key || !$this->consumer_secret )
      throw new Exception( 'WOOCOMMERCE_MISSING_CREDENTIALS', 500 );

    $currency = isset( $params[ SimplePayment::CURRENCY ] ) && $params[ SimplePayment::CURRENCY ] ? $params[ SimplePayment::CURRENCY ] : $this->param( 'currency' );
    $amount = isset( $params[ 'amount' ] ) ? $params[ 'amount' ] : 0;
    $concept = isset( $params[ 'concept' ] ) && $params[ 'concept' ] ? $params[ 'concept' ] : ( isset( $params[ SimplePayment::PRODUCT ] ) ? $params[ SimplePayment::PRODUCT ] : self::$name );

    // Callback urls of the current website, passed to the remote order so the
    // third party can send the customer back once done / cancelled / failed.
    $return_url = isset( $params[ 'redirect_url' ] ) && $params[ 'redirect_url' ] ? $params[ 'redirect_url' ] : $this->param( 'redirect_url' );
    $urls = [
      'return_url' => $return_url,
      'success_url' => $this->url( SimplePayment::OPERATION_SUCCESS, $params ),
      'cancel_url' => $this->url( SimplePayment::OPERATION_CANCEL, $params ),
      'error_url' => $this->url( SimplePayment::OPERATION_ERROR, $params ),
      'status_url' => $this->url( SimplePayment::OPERATION_STATUS, $params ),
    ];

    $order = [
      'set_paid' => false,
      'status' => $this->param( 'order_status' ) ? : 'pending',
      'currency' => $currency,
      'billing' => $this->billing( $params ),
      'meta_data' => [],
    ];

    // Only set a payment method when the merchant explicitly configured one: the
    // remote (companion) site owns the list of available gateways, so we do not
    // assume a slug here. Left empty, the companion picks the gateway (and the
    // customer chooses on the remote payment page).
    $payment_method = $this->param( 'payment_method' );
    if ( $payment_method ) {
      $order[ 'payment_method' ] = $payment_method;
      $payment_method_title = $this->param( 'payment_method_title' );
      if ( $payment_method_title ) $order[ 'payment_method_title' ] = $payment_method_title;
    }

    // The purchase total: either a configured product line or an arbitrary fee line.
    $product_id = $this->param( 'product_id' );
    if ( $product_id ) {
      $order[ 'line_items' ] = [ [
        'product_id' => intval( $product_id ),
        'quantity' => 1,
        'total' => (string) $amount,
      ] ];
    } else {
      $order[ 'fee_lines' ] = [ [
        'name' => $concept,
        'total' => (string) $amount,
        'tax_status' => 'none',
      ] ];
    }

    if ( isset( $params[ 'comment' ] ) && $params[ 'comment' ] ) $order[ 'customer_note' ] = $params[ 'comment' ];

    // Expose the callback urls, transaction reference and product both as first
    // class fields and as order meta_data, so the remote site can pick them up.
    $meta = array_merge( $urls, [
      'sp_payment_id' => isset( $params[ 'payment_id' ] ) ? $params[ 'payment_id' ] : null,
      'sp_source' => site_url(),
      'sp_product' => $concept,
      'sp_product_code' => isset( $params[ SimplePayment::PRODUCT_CODE ] ) ? $params[ SimplePayment::PRODUCT_CODE ] : null,
    ] );
    foreach ( $meta as $key => $value ) {
      if ( $value === null || $value === '' ) continue;
      $order[ $key ] = $value;
      $order[ 'meta_data' ][] = [ 'key' => 'sp_' === substr( $key, 0, 3 ) ? $key : 'sp_' . $key, 'value' => $value ];
    }

    $order = apply_filters( 'sp_woocommerce_order', $order, $params, $this );

    $response = $this->request( 'POST', 'orders', $order );

    if ( !isset( $response[ 'id' ] ) ) {
      $message = isset( $response[ 'message' ] ) ? $response[ 'message' ] : 'WOOCOMMERCE_ORDER_FAILED';
      $code = isset( $response[ 'data' ][ 'status' ] ) ? intval( $response[ 'data' ][ 'status' ] ) : 500;
      $this->save( [
        'transaction_id' => $this->transaction,
        'url' => $this->endpoint( 'orders' ),
        'status' => $code,
        'description' => is_string( $message ) ? $message : null,
        'request' => json_encode( $order ),
        'response' => json_encode( $response ),
      ] );
      throw new Exception( $message, $code );
    }

    // The remote WooCommerce order id becomes our transaction id.
    $this->transaction = $response[ 'id' ];

    $redirect = $this->redirect_url( $response );
    if ( !$redirect ) throw new Exception( 'WOOCOMMERCE_NO_REDIRECT', 500 );
    $params[ 'url' ] = $redirect;

    $this->save( [
      'transaction_id' => $this->transaction,
      'url' => $this->endpoint( 'orders' ),
      'status' => isset( $response[ 'status' ] ) ? $response[ 'status' ] : null,
      'description' => isset( $response[ 'number' ] ) ? 'Order #' . $response[ 'number' ] : null,
      'request' => json_encode( $order ),
      'response' => json_encode( $response ),
    ] );

    return( $params );
  }

  /**
   * Return the redirect (payment) url to open in the iframe / popup.
   */
  public function process( $params ) {
    return( isset( $params[ 'url' ] ) ? $params[ 'url' ] : false );
  }

  /**
   * Customer returned to the success url - verify the remote order is paid.
   */
  public function post_process( $params ) {
    if ( isset( $params[ 'transaction_id' ] ) && $params[ 'transaction_id' ] ) $this->transaction = $params[ 'transaction_id' ];
    $this->confirmation_code = $this->verify( $params );
    return( $this->confirmation_code ? true : false );
  }

  /**
   * Handle a status / webhook callback from the remote WooCommerce site.
   */
  public function status( $params ) {
    parent::status( $params );
    $id = $this->incoming_order_id( $params );
    if ( $id ) $this->transaction = $id;
    $this->confirmation_code = $this->verify( $params );
    return( $this->confirmation_code );
  }

  /**
   * Handle a feedback callback, returning the reference of the paid order.
   */
  public function feedback( $params ) {
    $id = $this->incoming_order_id( $params );
    if ( !$id ) return( false );
    $this->transaction = $id;
    $this->confirmation_code = $this->verify( $params );
    return( [ 'transaction_id' => $this->transaction ] );
  }

  /**
   * Fetch the remote order and decide whether it has been paid.
   * Returns the confirmation code (order transaction id / number) or false.
   */
  public function verify( $transaction = null ) {
    if ( is_array( $transaction ) && isset( $transaction[ 'transaction_id' ] ) && $transaction[ 'transaction_id' ] ) $this->transaction = $transaction[ 'transaction_id' ];
    else if ( $transaction && !is_array( $transaction ) ) $this->transaction = $transaction;
    if ( !$this->transaction ) return( false );

    $response = $this->request( 'GET', 'orders/' . rawurlencode( $this->transaction ) );
    $status = isset( $response[ 'status' ] ) ? $response[ 'status' ] : null;

    $this->save( [
      'transaction_id' => $this->transaction,
      'url' => $this->endpoint( 'orders/' . $this->transaction ),
      'status' => $status,
      'description' => isset( $response[ 'number' ] ) ? 'Order #' . $response[ 'number' ] : null,
      'request' => null,
      'response' => json_encode( $response ),
    ] );

    if ( !$status ) {
      $message = isset( $response[ 'message' ] ) ? $response[ 'message' ] : 'WOOCOMMERCE_ORDER_NOT_FOUND';
      throw new Exception( $message, isset( $response[ 'data' ][ 'status' ] ) ? intval( $response[ 'data' ][ 'status' ] ) : 500 );
    }

    if ( in_array( $status, $this->paid_statuses() ) ) {
      $this->confirmation_code = isset( $response[ 'transaction_id' ] ) && $response[ 'transaction_id' ] ? $response[ 'transaction_id' ] : ( isset( $response[ 'number' ] ) ? $response[ 'number' ] : $this->transaction );
      return( $this->confirmation_code );
    }
    if ( in_array( $status, [ 'cancelled', 'failed', 'refunded' ] ) )
      throw new Exception( 'WOOCOMMERCE_ORDER_' . strtoupper( $status ), 400 );
    return( false );
  }

  /**
   * Refund (fully or partially) the remote order.
   */
  public function refund( $params, $transaction_id = null ) {
    if ( $transaction_id ) $this->transaction = $transaction_id;
    else if ( isset( $params[ 'transaction_id' ] ) && $params[ 'transaction_id' ] ) $this->transaction = $params[ 'transaction_id' ];
    if ( !$this->transaction ) return( false );
    $data = [];
    if ( isset( $params[ 'amount' ] ) && $params[ 'amount' ] ) $data[ 'amount' ] = (string) $params[ 'amount' ];
    if ( isset( $params[ 'comment' ] ) && $params[ 'comment' ] ) $data[ 'reason' ] = $params[ 'comment' ];
    $response = $this->request( 'POST', 'orders/' . rawurlencode( $this->transaction ) . '/refunds', $data );
    $this->save( [
      'transaction_id' => $this->transaction,
      'url' => $this->endpoint( 'orders/' . $this->transaction . '/refunds' ),
      'status' => isset( $response[ 'id' ] ) ? 'refunded' : null,
      'description' => isset( $response[ 'id' ] ) ? 'Refund #' . $response[ 'id' ] : ( isset( $response[ 'message' ] ) ? $response[ 'message' ] : null ),
      'request' => json_encode( $data ),
      'response' => json_encode( $response ),
    ] );
    if ( isset( $response[ 'id' ] ) ) {
      $this->confirmation_code = $response[ 'id' ];
      return( true );
    }
    throw new Exception( isset( $response[ 'message' ] ) ? $response[ 'message' ] : 'WOOCOMMERCE_REFUND_FAILED', isset( $response[ 'data' ][ 'status' ] ) ? intval( $response[ 'data' ][ 'status' ] ) : 500 );
  }

  // --- helpers -------------------------------------------------------------

  /**
   * Build the WooCommerce billing block from the purchase params.
   */
  protected function billing( $params ) {
    $map = [
      'first_name' => SimplePayment::FIRST_NAME,
      'last_name' => SimplePayment::LAST_NAME,
      'email' => SimplePayment::EMAIL,
      'phone' => SimplePayment::PHONE,
      'address_1' => SimplePayment::ADDRESS,
      'address_2' => SimplePayment::ADDRESS2,
      'city' => SimplePayment::CITY,
      'state' => SimplePayment::STATE,
      'postcode' => SimplePayment::ZIPCODE,
      'country' => SimplePayment::COUNTRY,
      'company' => SimplePayment::COMPANY,
    ];
    $billing = [];
    foreach ( $map as $wc => $field ) {
      if ( isset( $params[ $field ] ) && $params[ $field ] !== '' ) $billing[ $wc ] = $params[ $field ];
    }
    if ( !isset( $billing[ 'phone' ] ) && isset( $params[ SimplePayment::MOBILE ] ) && $params[ SimplePayment::MOBILE ] ) $billing[ 'phone' ] = $params[ SimplePayment::MOBILE ];
    return( $billing );
  }

  /**
   * Extract the redirect (payment) url from the create-order response.
   * Looks at the configured field, then common WooCommerce fields, then meta_data.
   */
  protected function redirect_url( $response ) {
    $fields = [];
    $configured = $this->param( 'redirect_field' );
    if ( $configured ) $fields[] = $configured;
    $fields = array_merge( $fields, [ 'redirect', 'redirect_url', 'payment_url' ] );
    foreach ( $fields as $field ) {
      if ( isset( $response[ $field ] ) && $response[ $field ] ) return( $response[ $field ] );
    }
    if ( isset( $response[ 'meta_data' ] ) && is_array( $response[ 'meta_data' ] ) ) {
      foreach ( $response[ 'meta_data' ] as $meta ) {
        if ( isset( $meta[ 'key' ] ) && in_array( $meta[ 'key' ], $fields ) && !empty( $meta[ 'value' ] ) ) return( $meta[ 'value' ] );
      }
    }
    return( null );
  }

  /**
   * The remote order statuses that are considered as paid / successful.
   */
  protected function paid_statuses() {
    $statuses = $this->param( 'paid_statuses' );
    if ( !$statuses ) $statuses = 'processing,completed';
    return( array_filter( array_map( 'trim', explode( ',', $statuses ) ) ) );
  }

  /**
   * Resolve the incoming order id from a status / feedback / webhook request.
   */
  protected function incoming_order_id( $params ) {
    foreach ( [ 'transaction_id', 'id', 'order_id' ] as $key ) {
      if ( isset( $params[ $key ] ) && $params[ $key ] ) return( $params[ $key ] );
    }
    // WooCommerce webhooks post the resource payload as the raw json body.
    $body = json_decode( file_get_contents( 'php://input' ), true );
    if ( is_array( $body ) && isset( $body[ 'id' ] ) ) return( $body[ 'id' ] );
    return( null );
  }

  /**
   * Full endpoint url for a given WooCommerce REST route.
   */
  protected function endpoint( $route ) {
    return( $this->url . '/wp-json/' . $this->namespace . '/' . ltrim( $route, '/' ) );
  }

  /**
   * Perform an authenticated WooCommerce REST API request and return the decoded body.
   */
  protected function request( $method, $route, $body = null ) {
    $args = [
      'method' => $method,
      'timeout' => 30,
      'sslverify' => $this->param( 'verify_ssl' ) ? true : false,
      'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Basic ' . base64_encode( $this->consumer_key . ':' . $this->consumer_secret ),
      ],
    ];
    if ( $body !== null ) {
      $args[ 'headers' ][ 'Content-Type' ] = 'application/json';
      $args[ 'body' ] = json_encode( $body );
    }
    $response = wp_remote_request( $this->endpoint( $route ), $args );
    if ( is_wp_error( $response ) ) throw new Exception( $response->get_error_message(), 500 );
    $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
    return( is_array( $decoded ) ? $decoded : [] );
  }

}
