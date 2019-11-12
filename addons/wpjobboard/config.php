<?php

class Wpjb_Form_Admin_Config_SimplePayment extends Wpjb_Form_Abstract_Payment {
    public function init() {
        parent::init();
        $engines = SimplePaymentPlugin::$engines;
        $this->addGroup("sp", __("Simple Payment", "simple-payment"));

        $e = $this->create("engine", Daq_Form_Element::TYPE_SELECT);
        $e->setValue($this->conf("engine"));
        $e->setLabel(__("Engine", "simple-payment"));
        $e->addValidator(new Daq_Validate_InArray(array_keys($engines)));
        foreach($engines as $k => $v) {
            $e->addOption($k, $k,  $v);
        }
        $this->addElement($e, "sp");


        $e = $this->create("installments", Daq_Form_Element::TYPE_CHECKBOX);
        $e->setValue($this->conf("installments"));
        $e->addOption(1, "on", __("Enable", "simple-payment"));
        $e->setLabel(__("Installments", "simple-payment"));
        $this->addElement($e, "sp");

        $e = $this->create("template", Daq_Form_Element::TYPE_TEXT);
        $e->setValue($this->conf("template"));
        $e->setLabel(__("Template", "simple-payment"));
        $this->addElement($e, "sp");
    }
}
