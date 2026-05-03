<?php

namespace App\Events;

use App\Models\UserMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserMessageCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public UserMessage $message,
    ) {}
}
