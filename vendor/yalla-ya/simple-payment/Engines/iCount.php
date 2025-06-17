<?php

namespace SimplePayment\Engines;

use SimplePayment\SimplePayment;
use Exception;

if ( !defined( "ABSPATH" ) ) {
  exit; // Exit if accessed directly
}

class iCount extends Engine {

    static $CARD_TYPES = [ 'VISA' => 1, 'MASTERCARD' => 2, 'AMEX' => 3, 'DINERS' => 5, 'JCB' => 6, 'MAESTRO' => 7, 'LEUMICARD' => 8, 'ISRACARD' => 22 ];

    public static $name = 'iCount';
    public $api = [
      'bill' => 'https://api.icount.co.il/api/v3.php/cc/bill',
      'store' => 'https://api.icount.co.il/api/v3.php/cc_storage/store',
      'document' => 'https://api.icount.co.il/api/v3.php/doc/create',
      'verify' => 'https://api.icount.co.il/api/v3.php/cc/transactions',
      'client' => 'https://api.icount.co.il/api/v3.php/client/create',
      'recurr' => 'https://api.icount.co.il/api/v3.php/hk/create',
      'update' => 'https://api.icount.co.il/api/v3.php/client/create_or_update',
      'create' => 'https://api.icount.co.il/api/v3.php/client/create',
      'info' => 'https://api.icount.co.il/api/v3.php/client/info',
      'validate' => 'https://api.icount.co.il/api/v3.php/cc/validate_card_cvv'
    ];

    public static $supports = [ 'cvv', 'tokenization', 'card_owner_id' ];

    public function __construct($params = null, $handler = null, $sandbox = true) {
      parent::__construct( $params, $handler, $sandbox );
      //if ( $this->param( 'use_storage' ) ) self::$supports[] = 'tokenization';
      //$this->password = $this->sandbox ? $this->password : $this->param('password');
      //$this->box = $this->sandbox ? $this->box : $this->param('box');
      //$this->api = $this->api[$this->sandbox? 'sandbox' : 'live'];
    }


    // 
    public static function is_subscription( $params ) {
      if ( !isset( $params[ 'payments' ] ) ) return( false );
      $period = false;
      switch( $params[ 'payments' ] ) {
        case 'yearly':
          $period = 12;
          break;
        case 'quarterly':
          $period = 4;
          break;
        case 'semesterly':
          $period = 6;
          break;	
        case 'monthly':
          $period = 1;
      }
      return( $period );
    }

    public function process( $params ) {
      $post = $this->basics( $params );
      if ($this->sandbox) $post['is_test'] = true;
      $post['currency_code'] = isset($params[SimplePayment::CURRENCY]) ? $params[SimplePayment::CURRENCY] : $this->param(SimplePayment::CURRENCY); // currency_code (cuurency_id / currency
      $post['is_credit'] = false;
      $post['sum'] = $params[SimplePayment::AMOUNT];
      if (isset($params[SimplePayment::PAYMENTS]) && is_numeric($params[SimplePayment::PAYMENTS])) $post['num_of_payments'] = intval( $params[SimplePayment::PAYMENTS] );

      $service = 'bill';
      $subscription = self::is_subscription( $params );
      if ( $subscription && $this->param( 'reurring' ) == 'provider' ) {
        $service = 'recurr';
        $post[ 'items' ] = $this->items( $params );
        //$post[ 'doctype' ] = $this->param( 'doc_type' );
        $post[ 'currency' ] = $post['currency_code'];
        $post[ 'issue_every' ] = $subscription;

        // TODO: support: start_date, num_of_payments
        $post[ 'tax_exempt' ] = $this->param( 'doc_vat' ) == 'exempt';
        $post[ 'incvat' ] = $this->param( 'doc_vat' ) == 'include';
        if ( isset( $params[ SimplePayment::LANGUAGE ] ) ) $post[ 'lang' ] = $params[ SimplePayment::LANGUAGE ];
        if ( $this->param( 'email_document' ) ) {
          // This notifies everytime, event on fails, too much information...
          // $post[ 'email_to_client' ] = $this->param( 'email_document' );
        }
      }
      $status = $this->post( $this->api[ $service ], $post );
      $response = json_decode( $status, true );
      $this->save([
        'transaction_id' => $this->transaction,
        'url' => $this->api[ $service ],
        'status' => $response[ 'status' ], 
        'description' => isset( $response[ 'error_description' ] ) ? $response[ 'error_description' ] : $response[ 'reason' ],
        'request' => json_encode( $post ),
        'response' => json_encode( $response )
      ]);
      if ( !$response[ 'status' ] ) {
       throw new Exception( $response[ 'error_description' ], intval( $response[ 'status' ] ) );
      }
      
      if ( isset( $response[ 'currency_code' ] ) ) $params[ 'currency_code' ] = $response[ 'currency_code' ];
      if ( isset( $response[ 'confirmation_code' ] ) ) $params[ 'confirmation_code' ] = $response[ 'confirmation_code' ];
      if ( isset( $response[ 'cc_card_type' ] ) ) $params[ 'cc_card_type' ] = $response[ 'cc_card_type' ];
      if ( isset( $response[ 'cc_type' ] ) ) $params[ 'cc_type' ] = $response[ 'cc_type' ];
      $this->confirmation_code = $response[ 'confirmation_code' ];
      return( $params );
    }

    public function items( $params ) {
      $items = [];
      $pricefield = $this->param( 'doc_vat' ) == 'exempt' ? 'unitprice_exempt' : ( $this->param( 'doc_vat' ) == 'include' ? 'unitprice_incvat' : 'unitprice' );
      if ( isset( $params[ 'products' ] ) && is_array( $params[ 'products' ] ) ) {
        foreach ( $params[ 'products' ] as $product ) {
          $item = []; 
          $amount = $product[ 'amount' ];
          if ( $this->param( 'doc_vat' ) == 'exempt' ) {
            $item[ 'tax_exempt' ] = true;
            $item[ 'unitprice' ] = $amount;
          }
          if ( isset( $product[ 'id' ] ) ) $item[ 'sku' ] = $product[ 'id' ];
          $item[ 'quantity' ] = $product[ 'qty' ];
          $item[ 'description' ] = $product[ 'name' ] ? $product[ 'name' ] : $params[ SimplePayment::PRODUCT ];
          if ( trim( $product[ 'description' ] ) ) $item[ 'long_description' ] = $product[ 'description' ];
          $item[ $pricefield ] = $product[ 'amount' ];
          // serial
          $items[] = $item;
        }
      } else {
        $amount = $params[ SimplePayment::AMOUNT ];
        $item = [
          'description' => $params[ SimplePayment::PRODUCT ],  
          'quantity' => 1,
          // long_description, serial
        ];
        if ( $this->param( 'doc_vat' ) == 'exempt' ) {
          $item[ 'tax_exempt' ] = true;
          $item[ 'unitprice' ] = $amount;
        }
        $item[ $pricefield ] = $amount;
        /*
        if ( $this->param( 'doc_vat' ) == 'exempt' ) {
          $item[ 'unitprice_exempt' ] = $amount;
          $item[ 'unitprice' ] = $amount;
        } else if ( $this->param( 'doc_vat' ) == 'include' ) {
          $item[ 'unitprice_incvat' ] = $amount;
        } else $item[ 'unitprice' ] = $amount;*/
        if ( isset( $params[ SimplePayment::PRODUCT_CODE ] ) ) $item[ 'sku' ] = $params[ SimplePayment::PRODUCT_CODE ];
        $items[] = $item;
      }
      return( $items );
    }

    public function post_process($params) {
      parent::post_process( $params );
      if ( !isset( $params[ 'token' ] ) && $this->param( 'use_storage' )
        && isset( $params[ SimplePayment::CARD_NUMBER ] ) && $params[ SimplePayment::CARD_NUMBER ]
        && ( ( isset( $params[ SimplePayment::FULL_NAME ] ) && $params[ SimplePayment::FULL_NAME ] )
          || ( isset( $params[ SimplePayment::EMAIL ] ) && $params[ SimplePayment::EMAIL ] )
          || ( isset( $params[ SimplePayment::CARD_OWNER ] ) && $params[ SimplePayment::CARD_OWNER ] )
        ) ) {
          $this->store( $params );
      }
      if ( self::is_subscription( $params ) && $this->param( 'reurring' ) == 'provider' ) {
        $this->save( [
          'transaction_id' => $this->transaction,
          'url' => ':subscription',
          'status' => true,
          'description' => null,
          'request' => json_encode( $params ),
          'response' => null
        ] );
        return( true );
      }
      $doctype = $this->param( 'doc_type' );
      if ( !$doctype || $doctype == 'none' ) return( true );
      // Process the result of the transactions save

      $post = $this->basics( $params, false );

      $post[ 'doc_title' ] = $params[ SimplePayment::PRODUCT ];
      $post[ 'doctype' ] = $doctype;
      if ( isset( $params[ SimplePayment::LANGUAGE ] ) ) $post[ 'lang' ] = $params[ SimplePayment::LANGUAGE ];

      //vat_percent, tax_exempt
      $post[ 'currency_code' ] = $params[ 'currency_code' ];
      $amount = $params[ SimplePayment::AMOUNT ];
      // Amount to be in ILS only
      //$post['totalsum'] = $amount;
     // $post['totalwithvat'] = $amount;
      //$post['totalpaid'] = $amount;
      //$post['paid'] = $amount;
      
      // unitprice_incvat
      /*
      $date = new DateTime();
      $post['paydate'] =  $date->format('Y-m-y');
      $post['duedate'] =  $date->format('Y-m-y');
      */
      $post[ 'items' ] = $this->items( $params );
      if ($this->param('auto_invoice')) $post['autoinvoice_cc'] = $this->param('auto_invoice');
      if ($this->param('email_document')) $post['send_email'] = $this->param('email_document');

      $post['sanity_string'] = $this->transaction;
      $status = $this->post($this->api['document'], $post);
      $response = json_decode($status, true);
      $this->save([
        'transaction_id' => $this->transaction,
        'url' => $this->api[ 'document' ],
        'status' => $response[ 'status' ],
        'description' => isset( $response[ 'error_description' ] ) ? $response['error_description'] : $response['reason'],
        'request' => json_encode($post),
        'response' => json_encode($response)
      ]);
      if ( !$response[ 'status' ] ) {
       //throw new Exception($response['error_description'], intval($response['status']));
      }
      return( true );
    }

    public function basics( $params, $cc = true ) {
      $post = [];
      $post[ 'cid' ] = $this->param( 'business' );
      $post[ 'user' ] = $this->param( 'username' );
      $this->password = $this->param( 'password' );
      $post[ 'pass' ] = $this->password;
      // custom_client_id
      $client_name = isset( $params[ SimplePayment::FULL_NAME ] ) && $params[ SimplePayment::FULL_NAME ] ? $params[ SimplePayment::FULL_NAME ] : $params[ SimplePayment::CARD_OWNER ];
      if ( $client_name ) $post[ 'client_name' ] = $client_name;
      if ( isset( $params[ SimplePayment::TAX_ID ] ) ) $post[ 'vat_id' ] = $params[ SimplePayment::TAX_ID ];
      if ( isset( $params[ SimplePayment::EMAIL ] ) ) $post[ 'email' ] = $params[ SimplePayment::EMAIL ]; 
      if ( $cc ) {
        if ( isset( $params[ 'cc_type' ] ) ) $post[ 'cc_type' ] = $params[ 'cc_type' ]; // else maybe= $params[SimplePayment::CARD_TYPE]
        if ( isset( $params[ 'token' ] ) ) {
            $token_parts = explode( '-', $params[ 'token' ][ 'token' ] );
            if ( count( $token_parts ) > 1 ) {
              $params[ 'token' ][ 'token' ] = $token_parts[ 0 ];
              $post[ 'client_id' ] = $token_parts[ 1 ];
            }
            if ( !isset( $post[ 'client_name' ] ) || !$post[ 'client_name' ] ) $post[ 'client_name' ] =  $params[ 'token' ][ SimplePayment::CARD_OWNER ];
            $post[ 'cc_token_id' ] = intval( $params[ 'token' ][ 'token' ] );
            if ( isset( $params[ 'token' ][ SimplePayment::CARD_OWNER_ID ] ) && $params[ 'token' ][ SimplePayment::CARD_OWNER_ID ] ) $post[ 'cc_holder_id' ] = $params[ 'token' ][ SimplePayment::CARD_OWNER_ID ];
            $post[ 'cc_cvv' ] = $params[ 'token' ][ SimplePayment::CARD_CVV ];
            //if ( isset( $params[ 'token' ][ SimplePayment::CARD_EXPIRY_YEAR ] ) && $params[ 'token' ][ SimplePayment::CARD_EXPIRY_YEAR ] && isset( $params[ 'token' ][ SimplePayment::CARD_EXPIRY_MONTH ] ) && $params[ 'token' ][ SimplePayment::CARD_EXPIRY_MONTH ] ) $post[ 'cc_validity' ] = $params[ 'token' ][ SimplePayment::CARD_EXPIRY_YEAR ] . '-' . str_pad( $params[ 'token' ][ SimplePayment::CARD_EXPIRY_MONTH ], 2, '0', STR_PAD_LEFT );
        } else {
            if ( isset( $params[ SimplePayment::CARD_NUMBER ] ) ) $post[ 'cc_number' ] = $params[ SimplePayment::CARD_NUMBER ];
            if ( isset( $params[ SimplePayment::CARD_CVV ] ) ) $post[ 'cc_cvv' ] = $params[ SimplePayment::CARD_CVV ];
            if ( isset( $params[ SimplePayment::CARD_EXPIRY_YEAR ] ) && $params[ SimplePayment::CARD_EXPIRY_YEAR ] && isset( $params[ SimplePayment::CARD_EXPIRY_MONTH ] ) && $params[ SimplePayment::CARD_EXPIRY_MONTH ] ) $post[ 'cc_validity' ] = $params[ SimplePayment::CARD_EXPIRY_YEAR ] . '-' . str_pad( $params[ SimplePayment::CARD_EXPIRY_MONTH ], 2, '0', STR_PAD_LEFT );
            
	//if ( isset( $params[ SimplePayment::CARD_EXPIRY_YEAR ] ) && $params[ SimplePayment::CARD_EXPIRY_YEAR ] && isset( $params[ SimplePayment::CARD_EXPIRY_MONTH ] ) && $params[ SimplePayment::CARD_EXPIRY_MONTH ] ) $post[ 'cc_validity' ] = $params[ SimplePayment::CARD_EXPIRY_YEAR ] . '-' . $params[ SimplePayment::CARD_EXPIRY_MONTH ];
            if ( isset( $params[ SimplePayment::CARD_OWNER ] ) ) $post[ 'cc_holder_name' ] = $params[ SimplePayment::CARD_OWNER ];
            if ( isset( $params[ SimplePayment::CARD_OWNER_ID ] ) && $params[ SimplePayment::CARD_OWNER_ID ] ) $post[ 'cc_holder_id' ] = $params[ SimplePayment::CARD_OWNER_ID ];
        }  
      } else {
        $post[ 'cc' ] = [
          'sum' => $params[ SimplePayment::AMOUNT ],
          'card_type' => $params[ 'cc_type' ],
          'card_number' => substr( $params[SimplePayment::CARD_NUMBER ], -4 ),
          'exp_year' => $params[ SimplePayment::CARD_EXPIRY_YEAR ],
          'exp_month' => $params[ SimplePayment::CARD_EXPIRY_MONTH ],
          'holder_id' => isset( $params[ SimplePayment::CARD_OWNER_ID ] ) ? $params[ SimplePayment::CARD_OWNER_ID ] : null,
          'holder_name' => $params[ SimplePayment::CARD_OWNER ],
          'confirmation_code' => $params[ 'confirmation_code' ],
        ];
      }
      if ( isset( $post[ 'cc_holder_id' ] ) && $post[ 'cc_holder_id' ] && ( !isset( $post[ 'vat_id' ] ) || !$post[ 'vat_id' ] ) )  $post[ 'vat_id' ] = $post[ 'cc_holder_id' ];
      return( $post );
    }

    public function pre_process( $params ) {
      parent::pre_process( $params );
      $this->transaction = self::uuid();
      $params[ SimplePayment::TRANSACTION_ID ] = $this->transaction;
      return( $params );
    }

    public function store( $params ) {
      $token = null;
      if ( !$this->transaction ) {
        $this->transaction = self::uuid();
        $token = false;
        unset( $params[ 'token' ] );
        $post = $this->basics( $params );
        $status = $this->post( $this->api[ 'validate' ], $post );
        $response = json_decode( $status, true );
        $this->save( [
          'transaction_id' => $this->transaction,
          'url' => $this->api[ 'validate' ],
          'status' => $response[ 'status' ],
          'description' => isset( $response[ 'error_description' ] ) ? $response[ 'error_description' ] : $response[ 'reason' ],
          'request' => json_encode( $post ),
          'response' => json_encode( $response )
        ] );
        // Some account has no cvv test so we do not raise an error
        // TODO: add a switch in settings to determine if use or not this feature
        //if ( !$response[ 'status' ] ) {
        //  throw new Exception( $response[ 'error_description' ], intval( $response[ 'status' ] ) );
        //}
      }

      $post = $this->basics( $params, ( !isset( $params[ 'token' ] ) || !$params[ 'token' ] ) );
      $status = $this->post( $this->api[ 'info' ], $post );
      $response = json_decode( $status, true );
      $this->save( [
        'transaction_id' => $this->transaction,
        'url' => $this->api[ 'info' ],
        'status' => $response[ 'status' ],
        'description' => isset( $response[ 'error_description' ] ) ? $response[ 'error_description' ] : $response[ 'reason' ],
        'request' => json_encode( $post ),
        'response' => json_encode( $response )
      ] );

      if ( !$response[ 'status' ] ) {
        $post = $this->basics( $params, ( !isset( $params[ 'token' ] ) || !$params[ 'token' ] ) );
        $status = $this->post( $this->api[ 'create' ], $post );
        $response = json_decode( $status, true );
        $this->save( [
          'transaction_id' => $this->transaction,
          'url' => $this->api[ 'create' ],
          'status' => $response[ 'status' ],
          'description' => isset( $response[ 'error_description' ] ) ? $response[ 'error_description' ] : $response[ 'reason' ],
          'request' => json_encode( $post ),
          'response' => json_encode( $response )
        ] );
        if ( !$response[ 'status' ] ) {
          throw new Exception( $response[ 'error_description' ], intval( $response[ 'status' ] ) );
        }
      }

      $post = $this->basics( $params );
      //$post[ 'client_id' ] = $status[ 'client_id' ];
      if ( !defined( 'SP_FORCE_CVV_STORE' ) || !SP_FORCE_CVV_STORE ) unset( $post[ 'cc_cvv' ] );
      $status = $this->post( $this->api[ 'store' ], $post );
      $response = json_decode( $status, true );
      if ( $response[ 'status' ] ) {
        $token = [
            'token' => $response[ 'cc_token_id' ] . ( $response[ 'client_id' ] ? '-' . $response[ 'client_id' ] : '' ),
            SimplePayment::CARD_NUMBER => substr( $params[ SimplePayment::CARD_NUMBER ], -4, 4 ),
            SimplePayment::CARD_OWNER => $params[ SimplePayment::CARD_OWNER ],
            SimplePayment::CARD_OWNER_ID => $params[ SimplePayment::CARD_OWNER_ID ],
            SimplePayment::CARD_EXPIRY_YEAR => $params[ SimplePayment::CARD_EXPIRY_YEAR ],
            SimplePayment::CARD_EXPIRY_MONTH => $params[ SimplePayment::CARD_EXPIRY_MONTH ],
            SimplePayment::CARD_CVV => $params[ SimplePayment::CARD_CVV ],
            'engine' => self::$name
        ];
      }
      $this->save( [
        'transaction_id' => $this->transaction,
        'url' => $this->api[ 'store' ],
        'status' => $response[ 'status' ],
        'description' => isset( $response[ 'error_description' ] ) ? $response[ 'error_description' ] : $response[ 'reason' ],
        'request' => json_encode( $post ),
        'response' => json_encode( $response ),
        'token' => $token 
      ] );
      if ( !$response[ 'status' ] ) {
        throw new Exception( $response[ 'error_description' ], intval( $response[ 'status' ] ) );
      }
      $params[ 'token' ] = $token;
      $params[ 'cc_token_id' ] = $response[ 'cc_token_id' ]; 
      return( $params );
    }

    public static function uuid() {
      $uuid = parent::uuid();
      $uuid = str_replace( '-', '', $uuid );
      return( base64_encode( pack( 'h*', $uuid ) ) );
    }

    public function feedback( $params ) {
      $data = json_decode( file_get_contents( 'php://input' ), true );
      return( $data );
    }

    public function verify( $transaction ) {
      $this->transaction = $transaction[ 'transaction_id' ];
      $post = [];
      $post[ 'cid' ] = $this->param( 'business' );
      $post[ 'user' ] = $this->param( 'username' );
      $this->password = $this->param( 'password' );
      $post[ 'pass' ] = $this->password;
      $post[ 'client_name' ] = $transaction[ 'card_owner' ];
      $post[ 'email' ] = $transaction[ 'email' ];
      $post[ 'confirmation_code' ] = $transaction[ 'confirmation_code' ];
      $status = $this->post( $this->api[ 'verify' ], $post );
      $response = json_decode( $status, true );
      $this->save( [
        'transaction_id' => $this->transaction,
        'url' => $this->api[ 'verify' ],
        'status' => $response[ 'status' ],
        'description' => isset( $response[ 'error_description' ] ) ? $response[ 'error_description' ] : $response[ 'reason' ],
        'request' => json_encode( $post ),
        'response' => json_encode( $response )
      ] );
      if ( !$response[ 'status' ] ) {
        throw new Exception( $response[ 'error_description' ], intval( $response[ 'status' ] ) );
      }
      return( $response[ 'results_count' ] == 1 );
    }

}
