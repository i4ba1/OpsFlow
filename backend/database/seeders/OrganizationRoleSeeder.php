<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seed the default system roles for an organization from the permission
 * catalog (PRD §7.2/§8). Idempotent per organization slug.
 */
class OrganizationRoleSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private const ROLE_DEFINITIONS = [
        'admin' => [
            'organization.view', 'organization.update',
            'user.view', 'user.create', 'user.update', 'user.disable', 'user.manage_roles',
            'role.view', 'role.create', 'role.update', 'role.delete',
            'customer.view', 'customer.create', 'customer.update', 'customer.archive',
            'project.view', 'project.create', 'project.update', 'project.archive',
            'request.view', 'request.create', 'request.update', 'request.assign',
            'request.change_priority', 'request.transition', 'request.resolve',
            'request.close', 'request.reopen',
            'comment.create', 'comment.update_own', 'comment.delete_own', 'comment.internal',
            'attachment.create', 'attachment.download', 'attachment.delete',
            'approval.view', 'approval.request', 'approval.respond', 'approval.cancel',
            'invoice.view', 'invoice.create', 'invoice.update', 'invoice.issue',
            'invoice.void', 'invoice.mark_paid',
            'sla.view', 'sla.manage',
            'audit.view', 'dashboard.view', 'report.view',
        ],
        'manager' => [
            'customer.view', 'customer.create', 'customer.update',
            'project.view', 'project.create', 'project.update', 'project.archive',
            'request.view', 'request.create', 'request.update', 'request.assign',
            'request.change_priority', 'request.transition', 'request.resolve',
            'request.close', 'request.reopen',
            'comment.create', 'comment.update_own', 'comment.delete_own', 'comment.internal',
            'attachment.create', 'attachment.download', 'attachment.delete',
            'approval.view', 'approval.request', 'approval.respond', 'approval.cancel',
            'invoice.view', 'invoice.create',
            'sla.view', 'sla.manage',
            'audit.view', 'dashboard.view', 'report.view',
        ],
        'operator' => [
            'customer.view',
            'project.view',
            'request.view', 'request.update', 'request.transition', 'request.resolve',
            'comment.create', 'comment.update_own', 'comment.delete_own', 'comment.internal',
            'attachment.create', 'attachment.download', 'attachment.delete',
            'approval.view', 'approval.request', 'approval.respond',
            'dashboard.view',
        ],
        'finance' => [
            'customer.view', 'project.view', 'request.view',
            'invoice.view', 'invoice.create', 'invoice.update', 'invoice.issue',
            'invoice.void', 'invoice.mark_paid',
            'dashboard.view', 'report.view',
        ],
        'customer' => [
            'request.view', 'request.create',
            'comment.create', 'comment.update_own', 'comment.delete_own',
            'attachment.create', 'attachment.download',
            'approval.view', 'approval.respond',
            'invoice.view',
        ],
    ];

    public function run(Organization $organization): void
    {
        foreach (self::ROLE_DEFINITIONS as $slug => $permissions) {
            $role = Role::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'slug' => $slug],
                [
                    'name' => ucwords(str_replace('_', ' ', $slug)),
                    'description' => "System {$slug} role",
                    'is_system' => true,
                ],
            );

            $names = Permission::query()->whereIn('name', $permissions)->pluck('name');

            $role->permissions()->sync($names->all());
        }
    }
}
