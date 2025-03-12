<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\PerformWalletTransfer;
use App\Events\LowBalance;
use App\Http\Requests\Api\V1\SendMoneyRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Number;

class SendMoneyController
{
    public function __invoke(SendMoneyRequest $request, PerformWalletTransfer $performWalletTransfer): Response
    {
        $recipient = $request->getRecipient();

        $performWalletTransfer->execute(
            sender: $request->user(),
            recipient: $recipient,
            amount: $request->input('amount'),
            reason: $request->input('reason'),
        );

        LowBalance::dispatchIf(Number::currencyCents(auth()->user()->wallet->balance < 10), auth()->user());

        return response()->noContent(201);
    }
}
