<?php

declare(strict_types=1);

namespace ACA\SimplePayment\Service;

use AC;
use AC\Registerable;

class ColumnGroup implements Registerable
{

    public function register(): void
    {
        add_action( 'ac/column_groups', [$this, 'register_column_group']);
    }

    public function register_column_group(AC\Groups $groups): void
    {
        $groups->add( 'simple-payment', __( 'Simple Payment', 'simple-payment' ), 14);
    }

}