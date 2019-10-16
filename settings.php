<?php

$SPWP_CARCOM_LANGUAGES = [ 'he' => __('Hebrew', 'simple-payment'), 'en' => __('English', 'simple-payment') ];
$SPWP_CARCOM_OPERATIONS = [ 1 => __('Charge', 'simple-payment'), 2 => __('Charge & Token', 'simple-payment'), 3 => __('Token (Charge Pending)', 'simple-payment'), 4 => __('Suspended Deal', 'simple-payment') ];
$SPWP_CARCOM_DOC_TYPES = [ 1 => __('Invoice', 'simple-payment'), 3 => __('Donation Receipt', 'simple-payment'), 101 => __('Order Confirmation', 'simple-payment'), 400 => __('Receipt', 'simple-payment') ];
$SPWP_CARCOM_FIELD_STATUS = [ 'require' => __('Shown & Required', 'simple-payment'), 'show' => __('Shown', 'simple-payment'), 'hide' => __('Hidden', 'simple-payment')];
$SPWP_CARCOM_CREDIT_TYPES = [ 1 => __('Normal', 'simple-payment'), 6 => __('Credit', 'simple-payment')];
$SPWP_CARCOM_DOC_OPERATIONS = [ 0 => __('No Invoice', 'simple-payment'), 1 => 'Invoice', 2 => __('Forward (Do not show)', 'simple-payment')];

$SPWP_CARCOM_RECURRING_OPERATIONS = ['NewAndUpdate' => __('New and Update', 'simple-payment'), 'Update' => __('Only Update', 'simple-payment')];

$SPWP_CURRENCIES = [ 'ALL' => 'Albania Lek', 'AFN' => 'Afghanistan Afghani', 'ARS' => 'Argentina Peso', 'AWG' => 'Aruba Guilder', 'AUD' => 'Australia Dollar', 'AZN' => 'Azerbaijan New Manat', 'BSD' => 'Bahamas Dollar', 'BBD' => 'Barbados Dollar', 'BDT' => 'Bangladeshi taka', 'BYR' => 'Belarus Ruble', 'BZD' => 'Belize Dollar', 'BMD' => 'Bermuda Dollar', 'BOB' => 'Bolivia Boliviano', 'BAM' => 'Bosnia and Herzegovina Convertible Marka', 'BWP' => 'Botswana Pula', 'BGN' => 'Bulgaria Lev', 'BRL' => 'Brazil Real', 'BND' => 'Brunei Darussalam Dollar', 'KHR' => 'Cambodia Riel', 'CAD' => 'Canada Dollar', 'KYD' => 'Cayman Islands Dollar', 'CLP' => 'Chile Peso', 'CNY' => 'China Yuan Renminbi', 'COP' => 'Colombia Peso', 'CRC' => 'Costa Rica Colon', 'HRK' => 'Croatia Kuna', 'CUP' => 'Cuba Peso', 'CZK' => 'Czech Republic Koruna', 'DKK' => 'Denmark Krone', 'DOP' => 'Dominican Republic Peso', 'XCD' => 'East Caribbean Dollar', 'EGP' => 'Egypt Pound', 'SVC' => 'El Salvador Colon', 'EEK' => 'Estonia Kroon', 'EUR' => 'Euro Member Countries', 'FKP' => 'Falkland Islands (Malvinas) Pound', 'FJD' => 'Fiji Dollar', 'GHC' => 'Ghana Cedis', 'GIP' => 'Gibraltar Pound', 'GTQ' => 'Guatemala Quetzal', 'GGP' => 'Guernsey Pound', 'GYD' => 'Guyana Dollar', 'HNL' => 'Honduras Lempira', 'HKD' => 'Hong Kong Dollar', 'HUF' => 'Hungary Forint', 'ISK' => 'Iceland Krona', 'INR' => 'India Rupee', 'IDR' => 'Indonesia Rupiah', 'IRR' => 'Iran Rial', 'IMP' => 'Isle of Man Pound', 'ILS' => 'Israel Shekel', 'JMD' => 'Jamaica Dollar', 'JPY' => 'Japan Yen', 'JEP' => 'Jersey Pound', 'KZT' => 'Kazakhstan Tenge', 'KPW' => 'Korea (North) Won', 'KRW' => 'Korea (South) Won', 'KGS' => 'Kyrgyzstan Som', 'LAK' => 'Laos Kip', 'LVL' => 'Latvia Lat', 'LBP' => 'Lebanon Pound', 'LRD' => 'Liberia Dollar', 'LTL' => 'Lithuania Litas', 'MKD' => 'Macedonia Denar', 'MYR' => 'Malaysia Ringgit', 'MUR' => 'Mauritius Rupee', 'MXN' => 'Mexico Peso', 'MNT' => 'Mongolia Tughrik', 'MZN' => 'Mozambique Metical', 'NAD' => 'Namibia Dollar', 'NPR' => 'Nepal Rupee', 'ANG' => 'Netherlands Antilles Guilder', 'NZD' => 'New Zealand Dollar', 'NIO' => 'Nicaragua Cordoba', 'NGN' => 'Nigeria Naira', 'NOK' => 'Norway Krone', 'OMR' => 'Oman Rial', 'PKR' => 'Pakistan Rupee', 'PAB' => 'Panama Balboa', 'PYG' => 'Paraguay Guarani', 'PEN' => 'Peru Nuevo Sol', 'PHP' => 'Philippines Peso', 'PLN' => 'Poland Zloty', 'QAR' => 'Qatar Riyal', 'RON' => 'Romania New Leu', 'RUB' => 'Russia Ruble', 'SHP' => 'Saint Helena Pound', 'SAR' => 'Saudi Arabia Riyal', 'RSD' => 'Serbia Dinar', 'SCR' => 'Seychelles Rupee', 'SGD' => 'Singapore Dollar', 'SBD' => 'Solomon Islands Dollar', 'SOS' => 'Somalia Shilling', 'ZAR' => 'South Africa Rand', 'LKR' => 'Sri Lanka Rupee', 'SEK' => 'Sweden Krona', 'CHF' => 'Switzerland Franc', 'SRD' => 'Suriname Dollar', 'SYP' => 'Syria Pound', 'TWD' => 'Taiwan New Dollar', 'THB' => 'Thailand Baht', 'TTD' => 'Trinidad and Tobago Dollar', 'TRY' => 'Turkey Lira', 'TRL' => 'Turkey Lira', 'TVD' => 'Tuvalu Dollar', 'UAH' => 'Ukraine Hryvna', 'GBP' => 'United Kingdom Pound', 'USD' => 'United States Dollar', 'UYU' => 'Uruguay Peso', 'UZS' => 'Uzbekistan Som', 'VEF' => 'Venezuela Bolivar', 'VND' => 'Viet Nam Dong', 'YER' => 'Yemen Rial', 'ZWD' => 'Zimbabwe Dollar' ];
$SPWP_COUNTRIES = [ "Afghanistan", "Albania", "Algeria", "American Samoa", "Andorra", "Angola", "Anguilla", "Antarctica", "Antigua and Barbuda", "Argentina", "Armenia", "Aruba", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bermuda", "Bhutan", "Bolivia", "Bosnia and Herzegowina", "Botswana", "Bouvet Island", "Brazil", "British Indian Ocean Territory", "Brunei Darussalam", "Bulgaria", "Burkina Faso", "Burundi", "Cambodia", "Cameroon", "Canada", "Cape Verde", "Cayman Islands", "Central African Republic", "Chad", "Chile", "China", "Christmas Island", "Cocos (Keeling) Islands", "Colombia", "Comoros", "Congo", "Congo, the Democratic Republic of the", "Cook Islands", "Costa Rica", "Cote d'Ivoire", "Croatia (Hrvatska)", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "East Timor", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Ethiopia", "Falkland Islands (Malvinas)", "Faroe Islands", "Fiji", "Finland", "France", "France Metropolitan", "French Guiana", "French Polynesia", "French Southern Territories", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Gibraltar", "Greece", "Greenland", "Grenada", "Guadeloupe", "Guam", "Guatemala", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Heard and Mc Donald Islands", "Holy See (Vatican City State)", "Honduras", "Hong Kong", "Hungary", "Iceland", "India", "Indonesia", "Iran (Islamic Republic of)", "Iraq", "Ireland", "Israel", "Italy", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Korea, Democratic People's Republic of", "Korea, Republic of", "Kuwait", "Kyrgyzstan", "Lao, People's Democratic Republic", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libyan Arab Jamahiriya", "Liechtenstein", "Lithuania", "Luxembourg", "Macau", "Macedonia, The Former Yugoslav Republic of", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Martinique", "Mauritania", "Mauritius", "Mayotte", "Mexico", "Micronesia, Federated States of", "Moldova, Republic of", "Monaco", "Mongolia", "Montserrat", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "Netherlands Antilles", "New Caledonia", "New Zealand", "Nicaragua", "Niger", "Nigeria", "Niue", "Norfolk Island", "Northern Mariana Islands", "Norway", "Oman", "Pakistan", "Palau", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Pitcairn", "Poland", "Portugal", "Puerto Rico", "Qatar", "Reunion", "Romania", "Russian Federation", "Rwanda", "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines", "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Seychelles", "Sierra Leone", "Singapore", "Slovakia (Slovak Republic)", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Georgia and the South Sandwich Islands", "Spain", "Sri Lanka", "St. Helena", "St. Pierre and Miquelon", "Sudan", "Suriname", "Svalbard and Jan Mayen Islands", "Swaziland", "Sweden", "Switzerland", "Syrian Arab Republic", "Taiwan, Province of China", "Tajikistan", "Tanzania, United Republic of", "Thailand", "Togo", "Tokelau", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan", "Turks and Caicos Islands", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "United States Minor Outlying Islands", "Uruguay", "Uzbekistan", "Vanuatu", "Venezuela", "Vietnam", "Virgin Islands (British)", "Virgin Islands (U.S.)", "Wallis and Futuna Islands", "Western Sahara", "Yemen", "Yugoslavia", "Zambia", "Zimbabwe" ];

$SPWP_STATES = [ 'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'Washington DC', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'PR' => 'Puerto Rico', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VI' => 'Virgin Islands', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming' ];

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
  'paypal_settings' => [
      'title' => __('PayPal Settings', 'simple-payment'),
      'description' => __('Setup how PayPal should operate the payment below:', 'simple-payment'),
      'section' => 'paypal'
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
  ],
  'cardcom_recurring' => [
    'title' => __('Cardcom Recurring Payments', 'simple-payment'),
    'description' => __('Cardcom accepts recurring payments (monthly), required settings below', 'simple-payment'),
    'section' => 'cardcom'
]
];

$sp_settings = [
  'engine' => [
    'title' => __('Engine', 'simple-payment'),
    'type' => 'select',
    'options' => ['PayPal' => __('PayPal', 'simple-payment'), 'Cardcom' => __('Cardcom', 'simple-payment'), 'Custom' => __('Custom', 'simple-payment')]],
  'mode' => [ //Mode
    'title' => __('Mode', 'simple-payment'),
    'type' => 'radio',
    'options' => ['live' => __('Live', 'simple-payment'), 'sandbox' => __('Sandbox', 'simple-payment')]],
  'currency' => [
    'title' => __('Currency', 'simple-payment'),
    'type' => 'select',
    'options' => $SPWP_CURRENCIES, 'display' => 'both'],
  'sp_payment_page' => [
    'title' => __('Payment Page', 'simple-payment'),
    'label_for' => 'sp_payment_page', 'render_function' => 'setting_callback_function'],
  'callback_url' => [ // Redirect URL
    'title' => __('Callback URL', 'simple-payment')],
  'redirect_url' => [ // Redirect URL
    'title' => __('Redirect URL', 'simple-payment')],
  'form_type' => [ //Mode
    'title' => __('Form Template', 'simple-payment'),
    'type' => 'select',
    'options' => ['legacy' => __('Legacy', 'simple-payment'), 'bootstrap' => __('Bootstrap', 'simple-payment'), 'experimental' => __('Experimental', 'simple-payment')]],

  'paypal.client_id' => [ // Redirect URL
    'title' => __('Client ID', 'simple-payment'),
    'section' => 'paypal_settings'],
  'paypal.client_secret' => [ // Redirect URL
    'title' => __('Client Secret', 'simple-payment'),
    'section' => 'paypal_settings'],
  'paypal.business' => [ // Redirect URL
    'title' => __('Business', 'simple-payment'),
    'section' => 'paypal_settings'],
  'paypal.hosted_button_id' => [ // Redirect URL
    'title' => __('Hosted Button ID', 'simple-payment'),
    'section' => 'paypal_settings'],

  'cardcom.terminal' => [
    'title' => __('Terminal ID', 'simple-payment'),
    'section' => 'cardcom_settings'
  ],
  'cardcom.username' => [
    'title' => __('Username', 'simple-payment'),
    'section' => 'cardcom_settings'
  ],
  'cardcom.password' => [
    'title' => __('API Password', 'simple-payment'),
    'section' => 'cardcom_settings'
  ],
  'cardcom.operation' => [ //Operation
    'title' => __('Operation', 'simple-payment'),
    'type' => 'select',
    'options' => $SPWP_CARCOM_OPERATIONS,
    'section' => 'cardcom_settings'
  ],
  'cardcom.language' => [ // Language
    'title' => __('Force Language Interface', 'simple-payment'),
    'type' => 'select',
    'auto' => true,
    'options' => $SPWP_CARCOM_LANGUAGES,
    'section' => 'cardcom_display'
  ],
  'cardcom.credit_type' => [ // CreditType
    'title' => __('Credit Type', 'simple-payment'),
    'type' => 'select',
    'options' => $SPWP_CARCOM_CREDIT_TYPES,
    'section' => 'cardcom_settings',
    'auto' => true
  ],

  'cardcom.field_name' => [ // CardOwnerName, HideCardOwnerName
    'title' => __('Name field settings', 'simple-payment'),
    'type' => 'select',
    'options' => $SPWP_CARCOM_FIELD_STATUS,
    'section' => 'cardcom_display', 'auto' => true
  ],
  'cardcom.field_phone' => [ // ShowCardOwnerPhone, CardOwnerPhone, ReqCardOwnerPhone
    'title' => __('Phone field settings', 'simple-payment'),
    'type' => 'select',
    'options' => $SPWP_CARCOM_FIELD_STATUS,
    'section' => 'cardcom_display', 'auto' => true
  ],
  'cardcom.field_email' => [ // ShowCardOwnerEmail, CardOwnerEmail, ReqCardOwnerEmail
    'title' => __('Email field settings', 'simple-payment'),
    'type' => 'select',
    'options' => $SPWP_CARCOM_FIELD_STATUS,
    'section' => 'cardcom_display', 'auto' => true
  ],
  'cardcom.hide_user_id' => [
    'title' => __('Hide Credit Card User ID', 'simple-payment'),
    'type' => 'check',
    'section' => 'cardcom_settings'
  ],
  'cardcom.show_invoice_operation' => [ //InvoiceHeadOperation
    'title' => __('Invoice Processing', 'simple-payment'),
    'type' => 'select',
    'auto' => true,
    'options' => $SPWP_CARCOM_DOC_OPERATIONS,
    'section' => 'cardcom_document'
  ],
  'cardcom.doc_type' => [
    'title' => __('Document Type Upon Success', 'simple-payment'),
    'type' => 'select',
    'auto' => true,
    'options' => $SPWP_CARCOM_DOC_TYPES,
    'section' => 'cardcom_document'
  ],

  'cardcom.auto_create_account' => [
    'title' => __('Auto Create/Update Account', 'simple-payment'),
    'type' => 'check',
    'section' => 'cardcom_settings'
  ], // IsAutoCreateUpdateAccount
  'cardcom.auto_load_account' => [
    'title' => __('Load Account Info to Invoice', 'simple-payment'),
    'type' => 'check',
    'section' => 'cardcom_settings'
   ], // IsLoadInfoFromAccountID
  'cardcom.show_invoice_info' => [
    'title' => __('Show Invoice Information', 'simple-payment'),
    'type' => 'check',
    'section' => 'cardcom_display'
  ], // ShowInvoiceHead
  'cardcom.min_payments' => [ // MinNumOfPayments
    'title' => __('Min # of Payments', 'simple-payment'),
    'type' => 'select',
    'min' => 1, 'max' => 36,
    'section' => 'cardcom_display'
  ],
  'cardcom.max_payments' => [  // MaxNumOfPayments
    'title' => __('Max # of Payments', 'simple-payment'),
    'type' => 'select',
    'min' => 1, 'max' => 36,
    'section' => 'cardcom_display'
  ],
  'cardcom.default_payments' => [ // DefaultNumOfPayments
    'title' => __('Default # of Payments', 'simple-payment'),
    'type' => 'select',
    'min' => 1, 'max' => 36,
    'section' => 'cardcom_display'
  ],

  'cardcom.css' => [ // ShowCardOwnerEmail, CardOwnerEmail, ReqCardOwnerEmail
    'title' => __('CSS', 'simple-payment'),
    'type' => 'textarea',
    'section' => 'cardcom_display'
  ],

  'cardcom.vat_free' => [
    'title' => __('Prices Globally VAT Free', 'simple-payment'),
    'type' => 'check',
    'section' => 'cardcom_document'
  ], // ExtIsVatFree
  'cardcom.email_invoice' => [ // SendByEmail
    'title' => __('Email Invoice to Client', 'simple-payment'),
    'type' => 'check',
    'section' => 'cardcom_document'
  ],
  'cardcom.site_id' => [
    'title' => __('Site ID', 'simple-payment'),
    'section' => 'cardcom_document'
  ], // SiteUniqueId
  'cardcom.department_id' => [
    'title' => __('Department ID', 'simple-payment'),
    'section' => 'cardcom_document',
    'description' => __('Numeric ID', 'simple-payment'),
  ], // DepartmentId


  'cardcom.reurring' => [
    'title' => __('Enable Recurring Payments', 'simple-payment'),
    'section' => 'cardcom_recurring',
    'type' => 'select',
    'options' => [ 'disabled' => __('Disabled', 'simple-payment'), 'provider' => __('Provider', 'simple-payment')] // 'internal' => __('Internal', 'simple-payment')
  ],

  'cardcom.reurring_operation' => [
    'title' => __('Recurring Operation', 'simple-payment'),
    'section' => 'cardcom_recurring',
    'type' => 'select',
    'options' => $SPWP_CARCOM_RECURRING_OPERATIONS
  ],

  'cardcom.total_recurring' => [
    'title' => __('Repeated Recurring', 'simple-payment'),
    'section' => 'cardcom_recurring',
  ],

  'cardcom.interval' => [
    'title' => __('Recurring Interval ID', 'simple-payment'),
    'section' => 'cardcom_recurring',
  ],
  
];