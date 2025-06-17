<?php
//var $product, $price, $id, $fixed;
$SPWP = SimplePaymentPlugin::instance();
require(SPWP_PLUGIN_DIR.'/settings.php');
$installments = isset($installments) && $installments ? $installments : 0;
$year_today = date('Y'); $year_max = $year_today + 10;
$installments_min = $SPWP->param('installments_min');
$installments_max = $installments > $SPWP->param('installments_max') ? $installments : $SPWP->param('installments_max');
$installments_default = $installments && $installments !== true ? $installments : $SPWP->param('installments_default');

// TODO: valdate 3 digits (or 4 in american express) cvv and further credit card format
// TODO: Consider adding credit card type
$amount = isset($amount) ? $amount : null;
$amount_formatted = $amount ? number_format((float) $amount, 2) : '';

$target = isset($target) ? $target : $SPWP->param('target');
$target = $target ? ' target="'.$target.'"' : '';

$cvv = $SPWP->supports('cvv');
$owner_id = $SPWP->supports('card_owner_id');
$settings = isset($settings) ? $settings : null;


