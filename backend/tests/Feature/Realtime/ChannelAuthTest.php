<?php

use App\Models\Customer;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Realtime channel authorization (PRD §34, security checklist §84).
 *
 * channels.php is loaded once at boot against the configured broadcaster. In
 * tests BROADCAST_CONNECTION=null, so the callbacks are re-registered here
 * against a Pusher driver (credentials are local, no network) to exercise the
 * real authorization path, including AccessDeniedHttpException.
 */
function registerBroadcastChannels(): void
{
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.key' => 'test-key',
        'broadcasting.connections.pusher.secret' => 'test-secret',
        'broadcasting.connections.pusher.app_id' => 'test-app',
    ]);

    Broadcast::purge('pusher');

    require base_path('routes/channels.php');
}

function channelAuthRequest(string $channel, User $user): Request
{
    $request = Request::create('/broadcasting/auth', 'POST', [
        'channel_name' => $channel,
        'socket_id' => '1234.5678',
    ]);

    $request->setUserResolver(fn () => $user);

    return $request;
}

it('authorizes an organization member on the private organization channel', function () {
    registerBroadcastChannels();

    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    $response = Broadcast::auth(channelAuthRequest('private-organization.'.$org->id, $user));

    expect($response)->toBeArray();
});

it('denies a foreign organization member on the private organization channel', function () {
    registerBroadcastChannels();

    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $userB = User::factory()->create(['organization_id' => $orgB->id]);

    Broadcast::auth(channelAuthRequest('private-organization.'.$orgA->id, $userB));
})->throws(AccessDeniedHttpException::class);

it('authorizes the owner on their private user channel', function () {
    registerBroadcastChannels();

    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    $response = Broadcast::auth(channelAuthRequest('private-user.'.$user->id, $user));

    expect($response)->toBeArray();
});

it('denies another user on a private user channel', function () {
    registerBroadcastChannels();

    $org = Organization::factory()->create();
    $owner = User::factory()->create(['organization_id' => $org->id]);
    $other = User::factory()->create(['organization_id' => $org->id]);

    Broadcast::auth(channelAuthRequest('private-user.'.$owner->id, $other));
})->throws(AccessDeniedHttpException::class);

it('authorizes an organization member on a request channel', function () {
    registerBroadcastChannels();

    $org = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $request = ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $user->id,
    ]);

    $response = Broadcast::auth(channelAuthRequest('private-request.'.$request->id, $user));

    expect($response)->toBeArray();
});

it('denies a foreign organization member on a request channel', function () {
    registerBroadcastChannels();

    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $foreignUser = User::factory()->create(['organization_id' => $orgB->id]);
    $customerA = Customer::factory()->create(['organization_id' => $orgA->id]);
    $request = ServiceRequest::factory()->create([
        'organization_id' => $orgA->id,
        'customer_id' => $customerA->id,
        'requested_by' => $foreignUser->id,
    ]);

    Broadcast::auth(channelAuthRequest('private-request.'.$request->id, $foreignUser));
})->throws(AccessDeniedHttpException::class);
