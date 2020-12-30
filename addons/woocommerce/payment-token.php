<?php

class WC_Payment_Token_SimplePayment extends WC_Payment_Token_CC {

    protected $type = 'SimplePayment';

    public function validate() {
        // TODO: do we require to validate any info

        return(true);
    }

}