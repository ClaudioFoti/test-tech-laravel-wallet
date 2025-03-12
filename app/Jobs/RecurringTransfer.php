<?php

namespace App\Jobs;

use App\Actions\PerformWalletTransfer;
use App\Events\LowBalance;
use App\Exceptions\InsufficientBalance;
use App\Http\Requests\RecurringTransferRequest;
use App\Mail\LowBalanceNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Number;

class RecurringTransfer implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public RecurringTransferRequest $request, public PerformWalletTransfer $performWalletTransfer)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(RecurringTransferRequest $request, PerformWalletTransfer $performWalletTransfer): void
    {
        $recipient = $request->getRecipient();
        try {
            $performWalletTransfer->execute(
                sender: $request->user(),
                recipient: $recipient,
                amount: $request->getAmountInCents(),
                reason: $request->input('reason'),
            );

            LowBalance::dispatchIf(Number::currencyCents($request->user()->wallet->balance < 10), $request->user());

        } catch (InsufficientBalance $exception) {
            Mail::to($request->user())->send(new LowBalanceNotification($request->user()->wallet));
        }
    }
}
