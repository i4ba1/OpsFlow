<?php

use App\Enums\CommentVisibility;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;

/**
 * Grant the given user a single permission by attaching it through a fresh
 * role, mirroring how permission middleware checks real DB rows.
 */
function grantCommentPermission(User $user, string $permission): void
{
    $permissionModel = Permission::firstOrCreate(
        ['name' => $permission],
        ['group' => explode('.', $permission)[0], 'description' => ucfirst($permission)],
    );

    $role = Role::query()->firstOrCreate(
        ['organization_id' => $user->organization_id, 'slug' => 'commenter-'.$permission],
        ['name' => 'Commenter '.$permission, 'description' => null],
    );
    $role->permissions()->syncWithoutDetaching($permissionModel->name);
    $user->roles()->syncWithoutDetaching($role->id);
}

/**
 * A user who can view requests and write PUBLIC comments, but holds none of
 * the internal comment capabilities — the customer-like profile.
 */
function makeCustomerCommenter(Organization $org): User
{
    $user = User::factory()->create(['organization_id' => $org->id]);

    grantCommentPermission($user, 'request.view');
    grantCommentPermission($user, 'comment.create');

    return $user;
}

function makeInternalCommenter(Organization $org): User
{
    $user = User::factory()->create(['organization_id' => $org->id]);

    foreach (['request.view', 'comment.create', 'comment.internal', 'comment.update_own', 'comment.delete_own'] as $permission) {
        grantCommentPermission($user, $permission);
    }

    return $user;
}

function commentRequest(Organization $org, User $requester): ServiceRequest
{
    $customer = Customer::factory()->create(['organization_id' => $org->id]);

    return ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
    ]);
}

it('adds a PUBLIC comment as an operator', function () {
    $org = Organization::factory()->create();
    $operator = makeInternalCommenter($org);
    $request = commentRequest($org, $operator);

    $token = $operator->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/comments", [
            'body' => 'We are looking into this now.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.body', 'We are looking into this now.')
        ->assertJsonPath('data.visibility', 'PUBLIC')
        ->assertJsonPath('data.author.id', $operator->id);

    $comment = Comment::query()->firstOrFail();

    expect($comment->service_request_id)->toBe($request->id);
    expect($comment->author_id)->toBe($operator->id);
    expect($comment->visibility)->toBe(CommentVisibility::Public);
});

it('rejects an INTERNAL comment without the comment.internal permission', function () {
    $org = Organization::factory()->create();
    $customerUser = makeCustomerCommenter($org);
    $request = commentRequest($org, $customerUser);

    $token = $customerUser->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/comments", [
            'body' => 'Internal only',
            'visibility' => 'INTERNAL',
        ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect(Comment::query()->count())->toBe(0);
});

it('accepts an INTERNAL comment from a user with comment.internal', function () {
    $org = Organization::factory()->create();
    $operator = makeInternalCommenter($org);
    $request = commentRequest($org, $operator);

    $token = $operator->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/comments", [
            'body' => 'Escalate to L2',
            'visibility' => 'INTERNAL',
        ])
        ->assertCreated()
        ->assertJsonPath('data.visibility', 'INTERNAL');
});

it('does not reveal INTERNAL comments to customer users when listing', function () {
    $org = Organization::factory()->create();
    $customerUser = makeCustomerCommenter($org);
    $operator = makeInternalCommenter($org);
    $request = commentRequest($org, $customerUser);

    Comment::factory()->create([
        'organization_id' => $org->id,
        'service_request_id' => $request->id,
        'author_id' => $operator->id,
        'body' => 'A public note',
        'visibility' => CommentVisibility::Public,
    ]);
    $internal = Comment::factory()->internal()->create([
        'organization_id' => $org->id,
        'service_request_id' => $request->id,
        'author_id' => $operator->id,
        'body' => 'A staff-only note',
    ]);

    $token = $customerUser->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/service-requests/{$request->id}/comments")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'A public note');

    // The internal user sees both.
    $operatorToken = $operator->createToken('spa-operator')->plainTextToken;

    auth()->forgetGuards();

    $this->withToken($operatorToken)
        ->getJson("/api/v1/service-requests/{$request->id}/comments")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.body', 'A public note');
});

it('lets an author update their own comment', function () {
    $org = Organization::factory()->create();
    $operator = makeInternalCommenter($org);
    $request = commentRequest($org, $operator);

    $comment = Comment::factory()->create([
        'organization_id' => $org->id,
        'service_request_id' => $request->id,
        'author_id' => $operator->id,
        'body' => 'Original wording',
    ]);

    $token = $operator->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/comments/{$comment->id}", ['body' => 'Revised wording'])
        ->assertOk()
        ->assertJsonPath('data.body', 'Revised wording');

    expect($comment->fresh()->body)->toBe('Revised wording');
});

it('forbids a user from updating a comment they did not write', function () {
    $org = Organization::factory()->create();
    $operator = makeInternalCommenter($org);
    $other = makeInternalCommenter($org);
    $request = commentRequest($org, $operator);

    $comment = Comment::factory()->create([
        'organization_id' => $org->id,
        'service_request_id' => $request->id,
        'author_id' => $operator->id,
        'body' => 'Written by operator',
    ]);

    $token = $other->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/comments/{$comment->id}", ['body' => 'Tampered'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect($comment->fresh()->body)->toBe('Written by operator');
});

it('soft-deletes a comment and hides it from listings', function () {
    $org = Organization::factory()->create();
    $operator = makeInternalCommenter($org);
    $request = commentRequest($org, $operator);

    $comment = Comment::factory()->create([
        'organization_id' => $org->id,
        'service_request_id' => $request->id,
        'author_id' => $operator->id,
        'body' => 'To be removed',
    ]);

    $token = $operator->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->deleteJson("/api/v1/comments/{$comment->id}")
        ->assertOk();

    expect($comment->fresh()->deleted_at)->not->toBeNull();
    expect(Comment::query()->count())->toBe(0);

    $this->withToken($token)
        ->getJson("/api/v1/service-requests/{$request->id}/comments")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns 404 when patching a comment from another organization', function () {
    $orgA = Organization::factory()->create();
    $userA = makeInternalCommenter($orgA);
    $requestA = commentRequest($orgA, $userA);

    $orgB = Organization::factory()->create();
    $userB = User::factory()->create(['organization_id' => $orgB->id]);
    grantCommentPermission($userB, 'comment.update_own');
    $requestB = commentRequest($orgB, $userB);

    $foreignComment = Comment::factory()->create([
        'organization_id' => $orgB->id,
        'service_request_id' => $requestB->id,
        'author_id' => $userB->id,
    ]);

    $token = $userA->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/comments/{$foreignComment->id}", ['body' => 'Nope'])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});

it('returns 404 when commenting on a request from another organization', function () {
    $orgA = Organization::factory()->create();
    $userA = makeInternalCommenter($orgA);

    $orgB = Organization::factory()->create();
    $userB = User::factory()->create(['organization_id' => $orgB->id]);
    $requestB = commentRequest($orgB, $userB);

    $token = $userA->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$requestB->id}/comments", [
            'body' => 'Intrusion attempt',
        ])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

    expect(Comment::query()->count())->toBe(0);
});

it('requires comment.create to add a comment', function () {
    $org = Organization::factory()->create();
    $viewer = User::factory()->create(['organization_id' => $org->id]);
    grantCommentPermission($viewer, 'request.view');

    $request = commentRequest($org, $viewer);

    $token = $viewer->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/comments", [
            'body' => 'Not allowed',
        ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('validates the comment body on create', function () {
    $org = Organization::factory()->create();
    $operator = makeInternalCommenter($org);
    $request = commentRequest($org, $operator);

    $token = $operator->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/service-requests/{$request->id}/comments", ['body' => ''])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['body']]]);

    expect(Comment::query()->count())->toBe(0);
});
