<?php

$SP_CARCOM_LANGUAGES = [ 'he' => __('Hebrew', 'simple-payment'), 'en' => __('English', 'simple-payment') ];
$SP_CARCOM_OPERATIONS = [ 1 => __('Charge', 'simple-payment'), 2 => __('Charge & Token', 'simple-payment'), 3 => __('Token (Charge Pending)', 'simple-payment'), 4 => __('Suspended Deal', 'simple-payment') ];
$SP_CARCOM_DOC_TYPES = [ 1 => __('Invoice', 'simple-payment'), 3 => __('Donation Receipt', 'simple-payment'), 101 => __('Order Confirmation', 'simple-payment'), 400 => __('Receipt', 'simple-payment') ];
$SP_CARCOM_FIELD_STATUS = [ 'require' => __('Shown & Required', 'simple-payment'), 'show' => __('Shown', 'simple-payment'), 'hide' => __('Hidden', 'simple-payment')];
$SP_CARCOM_CREDIT_TYPES = [ 1 => __('Normal', 'simple-payment'), 6 => __('Credit', 'simple-payment')];
$SP_CARCOM_DOC_OPERATIONS = [ 0 => __('No Invoice', 'simple-payment'), 1 => 'Invoice', 2 => __('Forward (Do not show)', 'simple-payment')];


$sp_sections = [
  'licensing' => [
    'title' => __('Licensing Information', 'simple-payment'),
    'description' => __('Obtain a license <a href="mailto:ido@yalla-ya.com" target="_blank">here</a> to use in production mode', 'simple-payment'),
    'section' => 'license'
  ],
  'settings' => [
      'title' => __('General Settings', 'simple-payment'),
      'description' => __('Setup how Simple Payment should operate the payments below:', 'simple-payment'),
  ],
  'cardcom_settings' => [
      'title' => __('Cardcom Operation', 'simple-payment'),
      'description' => __('Setup how Cardcom should operate the payment below:', 'simple-payment'),
      'section' => 'cardcom'
  ],
  'cardcom_display' => [
      'title' => __('Cardcom Page Display Information', 'simple-payment'),
      'description' => __('Configure the Cardcom Page, which data should be displayed', 'simple-payment'),
      'section' => 'cardcom'
  ],
  'cardcom_document' => [
      'title' => __('Cardcom Document Processing', 'simple-payment'),
      'description' => __('Cardcom can create a document ', 'simple-payment'),
      'section' => 'cardcom'
  ]
];

$sp_settings = [
  'mode' => [ //Mode
    'title' => __('Mode', 'simple-payment'),
    'type' => 'radio',
    'options' => ['production' => __('Production', 'simple-payment'), 'testing' => __('Testing', 'simple-payment')]],
  'redirect_url' => [ // Redirect URL
    'title' => __('Redirect URL', 'simple-payment')],
  'form_type' => [ //Mode
    'title' => __('Form Template', 'simple-payment'),
    'type' => 'select',
    'options' => ['bootstrap' => __('Bootstrap', 'simple-payment'), 'experimental' => __('Experimental', 'simple-payment'), 'legacy' => __('Legacy', 'simple-payment')]],
  'cardcom_terminal' => [
    'title' => __('Terminal ID', 'simple-payment'),
    'section' => 'cardcom_settings'
  ],
  'cardcom_username' => [
    'title' => __('Username', 'simple-payment'),
    'section' => 'cardcom_settings'
  ],
  'cardcom_password' => [
    'title' => __('API Password', 'simple-payment'),
    'section' => 'cardcom_settings'
  ],
  'operation' => [ //Operation
    'title' => __('Operation', 'simple-payment'),
    'type' => 'select',
    'options' => $SP_CARCOM_OPERATIONS,
    'section' => 'cardcom_settings'
  ],
  'currency_id' => [ // CoinID
    'title' => __('Currency', 'simple-payment'),
    'type' => 'select',
    'options' => SimplePayment\Engines\Cardcom::CURRENCIES,
    'section' => 'cardcom_settings'
  ],
  'language' => [ // Language
    'title' => __('Force Language Interface', 'simple-payment'),
    'type' => 'select',
    'auto' => true,
    'options' => $SP_CARCOM_LANGUAGES,
    'section' => 'cardcom_display'
  ],
  'credit_type' => [ // CreditType
    'title' => __('Credit Type', 'simple-payment'),
    'type' => 'select',
    'options' => $SP_CARCOM_CREDIT_TYPES,
    'section' => 'cardcom_settings'
  ],

  'field_name' => [ // CardOwnerName, HideCardOwnerName
    'title' => __('Name field settings', 'simple-payment'),
    'type' => 'select',
    'options' => $SP_CARCOM_FIELD_STATUS,
    'section' => 'cardcom_display'
  ],
  'field_phone' => [ // ShowCardOwnerPhone, CardOwnerPhone, ReqCardOwnerPhone
    'title' => __('Phone field settings', 'simple-payment'),
    'type' => 'select',
    'options' => $SP_CARCOM_FIELD_STATUS,
    'section' => 'cardcom_display'
  ],
  'field_email' => [ // ShowCardOwnerEmail, CardOwnerEmail, ReqCardOwnerEmail
    'title' => __('Email field settings', 'simple-payment'),
    'type' => 'select',
    'options' => $SP_CARCOM_FIELD_STATUS,
    'section' => 'cardcom_display'
  ],

  'show_invoice_operation' => [ //InvoiceHeadOperation
    'title' => __('Invoice Processing', 'simple-payment'),
    'type' => 'select',
    'auto' => true,
    'options' => $SP_CARCOM_DOC_OPERATIONS,
    'section' => 'cardcom_document'
  ],
  'doc_type' => [
    'title' => __('Document Type Upon Success', 'simple-payment'),
    'type' => 'select',
    'auto' => true,
    'options' => $SP_CARCOM_DOC_TYPES,
    'section' => 'cardcom_document'
  ],

  'auto_create_account' => [
    'title' => __('Auto Create/Update Account', 'simple-payment'),
    'type' => 'check',
    'section' => 'cardcom_settings'
  ], // IsAutoCreateUpdateAccount
  'auto_load_account' => [
    'title' => __('Load Account Info to Invoice', 'simple-payment'),
    'type' => 'check',
    'section' => 'cardcom_settings'
   ], // IsLoadInfoFromAccountID
  'show_invoice_info' => [
    'title' => __('Show Invoice Information', 'simple-payment'),
    'type' => 'check',
    'section' => 'cardcom_display'
  ], // ShowInvoiceHead
  'min_payments' => [ // MinNumOfPayments
    'title' => __('Min # of Payments', 'simple-payment'),
    'type' => 'select',
    'min' => 1, 'max' => 36,
    'section' => 'cardcom_display'
  ],
  'max_payments' => [  // MaxNumOfPayments
    'title' => __('Max # of Payments', 'simple-payment'),
    'type' => 'select',
    'min' => 1, 'max' => 36,
    'section' => 'cardcom_display'
  ],
  'default_payments' => [ // DefaultNumOfPayments
    'title' => __('Default # of Payments', 'simple-payment'),
    'type' => 'select',
    'min' => 1, 'max' => 36,
    'section' => 'cardcom_display'
  ],

  'css' => [ // ShowCardOwnerEmail, CardOwnerEmail, ReqCardOwnerEmail
    'title' => __('CSS', 'simple-payment'),
    'type' => 'textarea',
    'section' => 'cardcom_display'
  ],

  'vat_free' => [
    'title' => __('Prices Globally VAT Free', 'simple-payment'),
    'type' => 'check',
    'section' => 'cardcom_document'
  ], // ExtIsVatFree
  'email_invoice' => [ // SendByEmail
    'title' => __('Email Invoice to Client', 'simple-payment'),
    'type' => 'check',
    'section' => 'cardcom_document'
  ],
  'site_id' => [
    'title' => __('Site ID', 'simple-payment'),
    'section' => 'cardcom_document'
  ], // SiteUniqueId
  'department_id' => [
    'title' => __('Department ID', 'simple-payment'),
    'section' => 'cardcom_document',
    'description' => __('Numeric ID', 'simple-payment'),
  ], // DepartmentId
];
