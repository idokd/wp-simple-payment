<?php

class Wpjb_Form_Admin_Config_SimplePayment extends Wpjb_Form_Abstract_Payment {
    public function init() {
        parent::init();
        $engines = ['default' => __('Default', 'simple-payment')];
        foreach(SimplePaymentPlugin::$engines as $engine) $engines[$engine] = __($engine, 'simple-payment');
        $this->addGroup('sp', __('Simple Payment', 'simple-payment'));

        $e = $this->create('engine', Daq_Form_Element::TYPE_SELECT);
        $e->setValue($this->conf('engine'));
        $e->setLabel(__('Engine', 'simple-payment'));
        $e->setHint(__('If none selected it will use Simple Payment default.', 'simple-payment'));
        //$e->addValidator(new Daq_Validate_InArray(array_keys($engines)));
        foreach($engines as $k => $v) {
            $e->addOption($k, $k, $v);
        }
        $this->addElement($e, 'sp');

        $displays = ['default' => 'Default', 'iframe' => 'IFRAME', 'modal' => 'Modal', 'redirect' => 'redirect'];
        $e = $this->create('display', Daq_Form_Element::TYPE_SELECT);
        $e->setValue($this->conf('display'));
        $e->setLabel(__('Display Method', 'simple-payment'));
        $e->setHint(__('If none selected it will use Simple Payment default.', 'simple-payment'));
        $e->addValidator(new Daq_Validate_InArray(array_keys($displays)));
        foreach($displays as $k => $v) {
            $e->addOption($k, $k, $v);
        }
        $this->addElement($e, 'sp');
        // single product item, , 

        $e = $this->create('product', Daq_Form_Element::TYPE_TEXT);
        $e->setValue($this->conf('product')); // 'Order %1$s. (%2$s).'
        $e->setLabel(__('Product', 'simple-payment'));
        $e->setHint(__('Custom product name to use in Simple Payment.', 'simple-payment'));
        $this->addElement($e, 'sp');

        $e = $this->create('installments', Daq_Form_Element::TYPE_CHECKBOX);
        $e->setValue($this->conf('installments'));
        $e->addOption(1, 'on', __('Enable', 'simple-payment'));
        $e->setLabel(__('Installments', 'simple-payment'));
        $e->setHint(__('Enable installments on checkout page', 'simple-payment'));
        $this->addElement($e, 'sp');

        $e = $this->create('template', Daq_Form_Element::TYPE_TEXT);
        $e->setValue($this->conf('template'));
        $e->setLabel(__('Template', 'simple-payment'));
        $e->setHint(__('Custom checkout template form', 'simple-payment'));
        $this->addElement($e, 'sp');

        $e = $this->create('settings', Daq_Form_Element::TYPE_TEXTAREA);
        $e->setValue($this->conf('settings'));
        $e->setLabel(__('Settings', 'simple-payment'));
        $e->setHint(__( 'Custom & advanced checkout settings', 'simple-payment'));
        $this->addElement($e, 'sp');
    }
}
