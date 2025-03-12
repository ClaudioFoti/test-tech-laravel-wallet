<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PerformWalletTransfer;
use App\Events\LowBalance;
use App\Exceptions\InsufficientBalance;
use App\Http\Requests\RecurringTransferRequest;
use App\Http\Requests\SendMoneyRequest;
use App\Jobs\RecurringTransfer;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Number;

class SendMoneyController
{
    public function __invoke(SendMoneyRequest $request, PerformWalletTransfer $performWalletTransfer)
    {
        $recipient = $request->getRecipient();

        try {
            $performWalletTransfer->execute(
                sender: $request->user(),
                recipient: $recipient,
                amount: $request->getAmountInCents(),
                reason: $request->input('reason'),
            );

            LowBalance::dispatchIf(Number::currencyCents(auth()->user()->wallet->balance < 10), auth()->user());

            return redirect()->back()
                ->with('money-sent-status', 'success')
                ->with('money-sent-recipient-name', $recipient->name)
                ->with('money-sent-amount', $request->getAmountInCents());
        } catch (InsufficientBalance $exception) {
            return redirect()->back()->with('money-sent-status', 'insufficient-balance')
                ->with('money-sent-recipient-name', $recipient->name)
                ->with('money-sent-amount', $request->getAmountInCents());
        }
    }

    public function recurring(RecurringTransferRequest $request, PerformWalletTransfer $performWalletTransfer)
    {
        $recurringTransferJob = new RecurringTransfer($request, $performWalletTransfer);
        dd($recurringTransferJob);
        Schedule::job(new RecurringTransfer($request, $performWalletTransfer))->dailyAt('2:00');
    }
}
