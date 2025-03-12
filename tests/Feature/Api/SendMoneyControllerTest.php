<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\SendMoneyController;
use App\Models\User;
use App\Models\Wallet;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

test('send money to a friend', function () {
    $user = User::factory()
        ->has(Wallet::factory()->richChillGuy())
        ->create();

    $recipient = User::factory()
        ->has(Wallet::factory())
        ->create();

    actingAs($user);

    postJson(action(SendMoneyController::class), [
        'recipient_email' => $recipient->email,
        'amount' => 100,
        'reason' => 'Just a chill guy gift',
    ])
        ->assertNoContent(201);

    expect($recipient->refresh()->wallet->balance)->toBe(100);

    assertDatabaseHas('wallet_transfers', [
        'amount' => 100,
        'source_id' => $user->wallet->id,
        'target_id' => $recipient->wallet->id,
    ]);

    assertDatabaseCount('wallet_transactions', 3);
});

test('cannot send money to a friend with insufficient balance', function () {
    $user = User::factory()
        ->has(Wallet::factory())
        ->create();

    $recipient = User::factory()
        ->has(Wallet::factory())
        ->create();

    actingAs($user);

    postJson(action(SendMoneyController::class), [
        'recipient_email' => $recipient->email,
        'amount' => 100,
        'reason' => 'Just a chill guy gift',
    ])
        ->assertBadRequest()
        ->assertJson([
            'code' => 'INSUFFICIENT_BALANCE',
            'message' => 'Insufficient balance in wallet.',
        ]);

    expect($recipient->refresh()->wallet->balance)->toBe(0);
});

test('receive notification if low balance', function () {
    Event::fake();
    Mail::fake();

    $user = User::factory()
        ->has(Wallet::factory()->balance(1000))
        ->create();

    $recipient = User::factory()
        ->has(Wallet::factory())
        ->create();

    actingAs($user);

    postJson(action(SendMoneyController::class), [
        'recipient_email' => $recipient->email,
        'amount' => 100,
        'reason' => 'Just a chill guy gift',
    ])
        ->assertNoContent(201);

    expect($user->refresh()->wallet->balance)->toBe(900);

    Event::assertDispatched(\App\Events\LowBalance::class);
    Event::assertListening(\App\Events\LowBalance::class, \App\Listeners\SendLowBalanceNotification::class);
    Mail::assertSent(\App\Mail\LowBalanceNotification::class, $user->email);
});
