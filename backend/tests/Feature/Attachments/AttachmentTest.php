<?php

use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Grant the given user a single permission by attaching it through a fresh
 * role, mirroring how permission middleware checks real DB rows.
 */
function grantAttachmentPermission(User $user, string $permission): void
{
    $permissionModel = Permission::firstOrCreate(
        ['name' => $permission],
        ['group' => explode('.', $permission)[0], 'description' => ucfirst($permission)],
    );

    $role = Role::factory()->create(['organization_id' => $user->organization_id]);
    $role->permissions()->attach($permissionModel->name);

    $user->roles()->attach($role->id);
}

function makeAttachmentUploader(Organization $org): User
{
    $user = User::factory()->create(['organization_id' => $org->id]);

    foreach (['request.view', 'attachment.create', 'attachment.download', 'attachment.delete'] as $permission) {
        grantAttachmentPermission($user, $permission);
    }

    return $user;
}

function attachmentRequest(Organization $org, User $requester): ServiceRequest
{
    $customer = Customer::factory()->create(['organization_id' => $org->id]);

    return ServiceRequest::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'requested_by' => $requester->id,
    ]);
}

it('uploads a PDF attachment and stores the file server-side', function () {
    Storage::fake('local');

    $org = Organization::factory()->create();
    $user = makeAttachmentUploader($org);
    $request = attachmentRequest($org, $user);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->post("/api/v1/service-requests/{$request->id}/attachments", [
            'file' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.original_name', 'invoice.pdf')
        ->assertJsonPath('data.mime_type', 'application/pdf')
        ->assertJsonPath('data.service_request_id', $request->id);

    $attachment = Attachment::query()->firstOrFail();

    expect($attachment->organization_id)->toBe($org->id);
    expect($attachment->storage_disk)->toBe('local');
    expect($attachment->storage_path)->toStartWith('attachments/');
    expect($attachment->original_name)->toBe('invoice.pdf');
    expect($attachment->checksum)->toMatch('/^[a-f0-9]{64}$/');
    expect($attachment->size_bytes)->toBe(100 * 1024);

    Storage::disk('local')->assertExists($attachment->storage_path);

    // The client filename never appears in the stored path.
    expect($attachment->storage_path)->not->toContain('invoice.pdf');
});

it('rejects an oversized attachment', function () {
    Storage::fake('local');

    $org = Organization::factory()->create();
    $user = makeAttachmentUploader($org);
    $request = attachmentRequest($org, $user);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->post("/api/v1/service-requests/{$request->id}/attachments", [
            'file' => UploadedFile::fake()->create('huge.pdf', (int) (config('attachments.max_size') / 1024) + 1024, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['file']]]);

    expect(Attachment::query()->count())->toBe(0);
});

it('rejects a disallowed mime type', function () {
    Storage::fake('local');

    $org = Organization::factory()->create();
    $user = makeAttachmentUploader($org);
    $request = attachmentRequest($org, $user);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->post("/api/v1/service-requests/{$request->id}/attachments", [
            'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['file']]]);

    expect(Attachment::query()->count())->toBe(0);
});

it('downloads an attachment with its original name and content type', function () {
    Storage::fake('local');

    $org = Organization::factory()->create();
    $user = makeAttachmentUploader($org);
    $request = attachmentRequest($org, $user);

    $token = $user->createToken('spa')->plainTextToken;

    $response = $this->withToken($token)
        ->post("/api/v1/service-requests/{$request->id}/attachments", [
            'file' => UploadedFile::fake()->create('screenshot.png', 50, 'image/png'),
        ])
        ->assertCreated();

    $attachment = Attachment::query()->firstOrFail();

    $this->withToken($token)
        ->get("/api/v1/attachments/{$attachment->id}/download")
        ->assertOk()
        ->assertHeader('content-type', 'image/png')
        ->assertHeader('content-disposition', 'attachment; filename=screenshot.png');
});

it('returns 404 downloading a foreign-organization attachment', function () {
    Storage::fake('local');

    $orgA = Organization::factory()->create();
    $userA = makeAttachmentUploader($orgA);

    $orgB = Organization::factory()->create();
    $userB = makeAttachmentUploader($orgB);
    $requestB = attachmentRequest($orgB, $userB);

    $this->withToken($userB->createToken('spa')->plainTextToken)
        ->post("/api/v1/service-requests/{$requestB->id}/attachments", [
            'file' => UploadedFile::fake()->create('theirs.pdf', 20, 'application/pdf'),
        ])
        ->assertCreated();

    $foreign = Attachment::query()->firstOrFail();

    // Reset the stateful auth guard so the next request authenticates as A.
    auth()->forgetGuards();

    $token = $userA->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->get("/api/v1/attachments/{$foreign->id}/download")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});

it('deletes an attachment row and its storage object', function () {
    Storage::fake('local');

    $org = Organization::factory()->create();
    $user = makeAttachmentUploader($org);
    $request = attachmentRequest($org, $user);

    $token = $user->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->post("/api/v1/service-requests/{$request->id}/attachments", [
            'file' => UploadedFile::fake()->create('obsolete.pdf', 30, 'application/pdf'),
        ])
        ->assertCreated();

    $attachment = Attachment::query()->firstOrFail();

    Storage::disk('local')->assertExists($attachment->storage_path);

    $this->withToken($token)
        ->deleteJson("/api/v1/attachments/{$attachment->id}")
        ->assertOk();

    expect($attachment->fresh()->deleted_at)->not->toBeNull();
    Storage::disk('local')->assertMissing($attachment->storage_path);
});

it('returns 404 uploading to a foreign-organization request', function () {
    Storage::fake('local');

    $orgA = Organization::factory()->create();
    $userA = makeAttachmentUploader($orgA);

    $orgB = Organization::factory()->create();
    $userB = User::factory()->create(['organization_id' => $orgB->id]);
    $requestB = attachmentRequest($orgB, $userB);

    $token = $userA->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->post("/api/v1/service-requests/{$requestB->id}/attachments", [
            'file' => UploadedFile::fake()->create('x.pdf', 20, 'application/pdf'),
        ])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

    expect(Attachment::query()->count())->toBe(0);
});

it('requires the attachment.create permission to upload', function () {
    Storage::fake('local');

    $org = Organization::factory()->create();
    $viewer = User::factory()->create(['organization_id' => $org->id]);
    grantAttachmentPermission($viewer, 'request.view');

    $request = attachmentRequest($org, $viewer);

    $token = $viewer->createToken('spa')->plainTextToken;

    $this->withToken($token)
        ->post("/api/v1/service-requests/{$request->id}/attachments", [
            'file' => UploadedFile::fake()->create('x.pdf', 20, 'application/pdf'),
        ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect(Attachment::query()->count())->toBe(0);
});
