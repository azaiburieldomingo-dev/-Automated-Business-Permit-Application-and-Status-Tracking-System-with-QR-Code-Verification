<?php

namespace App\Events;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ApplicationRejected
{
    use Dispatchable;

    public function __construct(
        public Application $application,
        public ?User $actor = null,
    ) {}
}
