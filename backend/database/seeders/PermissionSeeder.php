<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * The canonical permission catalog (PRD §8).
     *
     * @return list<array{name: string, group: string, description: string}>
     */
    public static function catalog(): array
    {
        return [
            ['name' => 'organization.view', 'group' => 'organization', 'description' => 'View organization profile and settings'],
            ['name' => 'organization.update', 'group' => 'organization', 'description' => 'Update organization settings'],

            ['name' => 'user.view', 'group' => 'user', 'description' => 'View organization users'],
            ['name' => 'user.create', 'group' => 'user', 'description' => 'Invite users'],
            ['name' => 'user.update', 'group' => 'user', 'description' => 'Update user profiles'],
            ['name' => 'user.disable', 'group' => 'user', 'description' => 'Disable users'],
            ['name' => 'user.manage_roles', 'group' => 'user', 'description' => 'Assign roles to users'],

            ['name' => 'role.view', 'group' => 'role', 'description' => 'View roles'],
            ['name' => 'role.create', 'group' => 'role', 'description' => 'Create roles'],
            ['name' => 'role.update', 'group' => 'role', 'description' => 'Update roles'],
            ['name' => 'role.delete', 'group' => 'role', 'description' => 'Delete roles'],

            ['name' => 'customer.view', 'group' => 'customer', 'description' => 'View customers'],
            ['name' => 'customer.create', 'group' => 'customer', 'description' => 'Create customers'],
            ['name' => 'customer.update', 'group' => 'customer', 'description' => 'Update customers'],
            ['name' => 'customer.archive', 'group' => 'customer', 'description' => 'Archive customers'],

            ['name' => 'project.view', 'group' => 'project', 'description' => 'View projects'],
            ['name' => 'project.create', 'group' => 'project', 'description' => 'Create projects'],
            ['name' => 'project.update', 'group' => 'project', 'description' => 'Update projects'],
            ['name' => 'project.archive', 'group' => 'project', 'description' => 'Archive projects'],

            ['name' => 'request.view', 'group' => 'request', 'description' => 'View service requests'],
            ['name' => 'request.create', 'group' => 'request', 'description' => 'Create service requests'],
            ['name' => 'request.update', 'group' => 'request', 'description' => 'Update service requests'],
            ['name' => 'request.assign', 'group' => 'request', 'description' => 'Assign service requests'],
            ['name' => 'request.change_priority', 'group' => 'request', 'description' => 'Change request priority'],
            ['name' => 'request.transition', 'group' => 'request', 'description' => 'Transition request status'],
            ['name' => 'request.resolve', 'group' => 'request', 'description' => 'Resolve requests'],
            ['name' => 'request.close', 'group' => 'request', 'description' => 'Close requests'],
            ['name' => 'request.reopen', 'group' => 'request', 'description' => 'Reopen requests'],

            ['name' => 'comment.create', 'group' => 'comment', 'description' => 'Add comments'],
            ['name' => 'comment.update_own', 'group' => 'comment', 'description' => 'Update own comments'],
            ['name' => 'comment.delete_own', 'group' => 'comment', 'description' => 'Delete own comments'],
            ['name' => 'comment.internal', 'group' => 'comment', 'description' => 'Use internal-visibility comments'],

            ['name' => 'attachment.create', 'group' => 'attachment', 'description' => 'Upload attachments'],
            ['name' => 'attachment.download', 'group' => 'attachment', 'description' => 'Download attachments'],
            ['name' => 'attachment.delete', 'group' => 'attachment', 'description' => 'Delete attachments'],

            ['name' => 'approval.view', 'group' => 'approval', 'description' => 'View approvals'],
            ['name' => 'approval.request', 'group' => 'approval', 'description' => 'Request approvals'],
            ['name' => 'approval.respond', 'group' => 'approval', 'description' => 'Respond to approvals'],
            ['name' => 'approval.cancel', 'group' => 'approval', 'description' => 'Cancel approvals'],

            ['name' => 'invoice.view', 'group' => 'invoice', 'description' => 'View invoices'],
            ['name' => 'invoice.create', 'group' => 'invoice', 'description' => 'Create invoices'],
            ['name' => 'invoice.update', 'group' => 'invoice', 'description' => 'Update invoices'],
            ['name' => 'invoice.issue', 'group' => 'invoice', 'description' => 'Issue invoices'],
            ['name' => 'invoice.void', 'group' => 'invoice', 'description' => 'Void invoices'],
            ['name' => 'invoice.mark_paid', 'group' => 'invoice', 'description' => 'Mark invoices paid'],

            ['name' => 'sla.view', 'group' => 'sla', 'description' => 'View SLA policies'],
            ['name' => 'sla.manage', 'group' => 'sla', 'description' => 'Manage SLA policies'],

            ['name' => 'audit.view', 'group' => 'audit', 'description' => 'View audit logs'],
            ['name' => 'dashboard.view', 'group' => 'dashboard', 'description' => 'View dashboards'],
            ['name' => 'report.view', 'group' => 'report', 'description' => 'View reports'],
        ];
    }

    public function run(): void
    {
        foreach (self::catalog() as $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $permission['name']],
                $permission,
            );
        }
    }
}
