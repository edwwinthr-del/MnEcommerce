<?php

namespace App\Contracts;

use App\Models\SupplierOrder;

interface SupplierConnectorInterface
{
    /** Future connectors must use configured allowlisted hosts and idempotent external references. */
    public function submit(SupplierOrder $order): string;

    public function status(SupplierOrder $order): array;
}
