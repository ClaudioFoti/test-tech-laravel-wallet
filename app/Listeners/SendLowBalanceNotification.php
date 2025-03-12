<?php

namespace App\Listeners;

use App\Events\LowBalance;
use App\Mail\LowBalanceNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendLowBalanceNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(LowBalance $event): void
    {
        Mail::to($event->user)->send(new LowBalanceNotification($event->user->wallet));
    }
}
