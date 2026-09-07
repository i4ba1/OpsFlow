<?php

use App\Models\ServiceRequest;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Organization-wide channel (PRD §34): any member of the organization.
 */
Broadcast::channel('private-organization.{organizationId}', function ($user, $organizationId) {
    return $user->organization_id === $organizationId;
});

/**
 * Per-request channel (PRD §34): members of the request's organization, plus
 * anyone holding request.view for it (participants such as the assignee and
 * requester always belong to the request organization).
 */
Broadcast::channel('private-request.{serviceRequestId}', function ($user, $serviceRequestId) {
    $serviceRequest = ServiceRequest::query()->find($serviceRequestId);

    if ($serviceRequest === null) {
        return false;
    }

    if ($serviceRequest->organization_id === $user->organization_id) {
        return true;
    }

    return $user->hasPermission('request.view');
});

/**
 * Private user channel (PRD §34): the user themselves only.
 */
Broadcast::channel('private-user.{userId}', function ($user, $userId) {
    return $user->id === $userId;
});
