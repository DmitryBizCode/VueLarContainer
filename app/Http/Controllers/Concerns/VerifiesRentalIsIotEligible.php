<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Rental;

trait VerifiesRentalIsIotEligible
{
    protected function verifyRentalIsIotEligible(Rental $rental): void
    {
        abort_if(! $rental->isIotEligible(), 403, 'IoT is available only after the rental is approved.');
    }

    protected function verifyRentalCanAccessIotMonitor(Rental $rental): void
    {
        abort_if(! $rental->canAccessIotMonitor(), 403, 'IoT monitoring is not available for this rental.');
    }
}
