# OpsFlow
## Complete Technical Product Requirements Document (PRD) & Implementation Specification

**Product:** OpsFlow  
**Product Type:** Multi-Tenant B2B Operations & Client Service Platform  
**Document Version:** 1.0  
**Status:** Build-ready  
**Target Audience:** Software engineers, reviewers, technical recruiters, engineering managers, product stakeholders  
**Primary Showcase Goal:** Demonstrate production-grade Laravel + React engineering for a Singapore-based remote software engineering opportunity  
**Last Updated:** 6 September 2026

---

# 1. Executive Summary

OpsFlow is a multi-tenant B2B operations platform designed for service-oriented SMEs and regional companies that need one system to manage customers, projects, service requests, internal assignments, SLA commitments, approvals, documents, invoices, notifications, and operational reporting.

The platform has two primary experiences:

1. **Internal Operations Portal** — used by organization administrators, managers, operators, finance staff, and support staff.
2. **Customer Portal** — used by customer contacts to create requests, collaborate with the service team, approve work, upload/download files, and monitor request and invoice status.

OpsFlow is intentionally designed as more than a CRUD portfolio project. It demonstrates:

- modern PHP and Laravel architecture;
- production React and TypeScript patterns;
- relational database design;
- secure multi-tenancy;
- RBAC and policy-based authorization;
- workflow/state-machine design;
- SLA calculation and escalation;
- event-driven and asynchronous processing;
- real-time WebSocket updates;
- file storage;
- audit logging;
- observability;
- automated testing;
- Dockerized local development;
- CI/CD;
- API design;
- performance engineering;
- maintainability and engineering documentation.

The technical implementation should make it immediately clear to a reviewer that the engineer can contribute to an existing Laravel + React production environment.

---

# 2. Product Vision

## 2.1 Vision Statement

> Enable service organizations to manage the complete lifecycle of customer work—from request intake through assignment, execution, approval, SLA tracking, billing, and audit—in a secure, multi-tenant platform.

## 2.2 Portfolio Vision

The portfolio implementation must communicate the following within five minutes of review:

- The engineer is actively capable with Laravel and React.
- The engineer understands enterprise application architecture.
- The engineer can model non-trivial business workflows.
- The engineer understands SQL and relational data.
- The engineer writes testable, maintainable code.
- The engineer understands production concerns beyond feature development.
- The engineer can communicate architecture and engineering decisions clearly in English.

---

# 3. Product Goals

## 3.1 Primary Goals

1. Provide organizations with a centralized operations workspace.
2. Allow customer users to submit and monitor service requests.
3. Track request SLAs and automatically escalate at-risk work.
4. Enforce secure tenant isolation.
5. Enforce granular role/permission authorization.
6. Provide a complete audit trail for sensitive operations.
7. Support real-time request collaboration.
8. Provide operational dashboards and metrics.
9. Provide a professional, polished React user experience.
10. Be easy for another engineer to run locally and review.

## 3.2 Secondary Goals

- Demonstrate background processing with queues.
- Demonstrate scheduled tasks.
- Demonstrate transactional business operations.
- Demonstrate optimistic frontend updates where appropriate.
- Demonstrate robust API error handling.
- Demonstrate object storage integration.
- Demonstrate CI/CD and code-quality gates.
- Demonstrate systematic testing rather than only happy-path tests.

## 3.3 Non-Goals for V1

The first portfolio release does **not** need to implement:

- full accounting;
- tax filing;
- payroll;
- complex ERP inventory;
- native mobile applications;
- public marketplace functionality;
- cross-organization billing;
- generative AI as a core dependency;
- microservices.

OpsFlow should begin as a **modular monolith**. This is intentional. A well-structured Laravel modular monolith is more appropriate than unnecessary microservices for the product's initial scale.

---

# 4. Current Technology Baseline

The baseline below is selected as of September 2026.

## 4.1 Backend

| Component | Selected Baseline |
|---|---|
| Language | PHP 8.5.x |
| Framework | Laravel 13.x |
| Authentication | Laravel Sanctum |
| Authorization | Laravel Policies + application RBAC |
| Queue | Laravel Queue |
| Queue Monitoring | Laravel Horizon |
| Broadcasting | Laravel Reverb |
| Scheduler | Laravel Scheduler |
| Feature Flags | Laravel Pennant, optional |
| Search | PostgreSQL full-text initially; Laravel Scout optional |
| API Style | REST / JSON |
| API Documentation | OpenAPI 3.1 |
| Testing | Pest 4 + PHPUnit ecosystem |
| Static Analysis | PHPStan/Larastan |
| Formatting | Laravel Pint |

Laravel 13 was released in March 2026 and supports PHP 8.3–8.5. The project standardizes on PHP 8.5.

## 4.2 Frontend

| Component | Selected Baseline |
|---|---|
| Runtime UI | React 19.2 |
| Language | TypeScript |
| Build Tool | Vite 8.1 |
| Routing | React Router |
| Server State | TanStack Query v5 |
| Forms | React Hook Form |
| Schema Validation | Zod |
| UI System | Material UI |
| Data Tables | TanStack Table |
| Charts | Recharts |
| HTTP | Axios |
| Date Handling | date-fns |
| Unit/Component Testing | Vitest + React Testing Library |
| Browser E2E | Playwright |
| Linting | ESLint |
| Formatting | Prettier |

## 4.3 Data and Infrastructure

| Component | Selected Baseline |
|---|---|
| Primary Database | PostgreSQL 18.6 |
| Cache / Queue Backend | Redis 8.x |
| Object Storage | S3-compatible storage |
| Web Server / Reverse Proxy | Nginx |
| Containers | Docker + Docker Compose |
| CI/CD | GitHub Actions |
| Container Registry | GitHub Container Registry |
| Production Hosting | AWS / DigitalOcean / equivalent |
| Error Tracking | Sentry-compatible integration |
| Metrics | Prometheus-compatible metrics |
| Logs | Structured JSON logs |

PostgreSQL 19 is still pre-release as of this document date; OpsFlow therefore uses the current stable PostgreSQL 18 branch.

For Redis, production deployment should prefer a supported extended/LTS-compatible Redis 8 release rather than a short-lived release when operational stability is more important than novelty.

---

# 5. High-Level Architecture

## 5.1 Architecture Style

OpsFlow uses a **modular monolith**:

- one Laravel backend application;
- one React SPA;
- one PostgreSQL database;
- Redis for cache, queues, locks, and ephemeral data;
- object storage for uploaded files;
- WebSocket server using Laravel Reverb;
- queue worker processes;
- scheduler process.

This architecture provides clear module boundaries while minimizing deployment and operational complexity.

## 5.2 System Context

```text
                          ┌─────────────────────┐
                          │       Browser       │
                          │  Internal/Customer  │
                          └──────────┬──────────┘
                                     │ HTTPS / WSS
                                     ▼
                          ┌─────────────────────┐
                          │        Nginx        │
                          └───────┬─────┬───────┘
                                  │     │
                            HTTPS │     │ WebSocket
                                  ▼     ▼
                     ┌──────────────┐  ┌──────────────┐
                     │ Laravel API  │  │    Reverb    │
                     └──────┬───────┘  └──────┬───────┘
                            │                 │
              ┌─────────────┼───────────────┐ │
              │             │               │ │
              ▼             ▼               ▼ ▼
       ┌────────────┐ ┌────────────┐ ┌─────────────┐
       │ PostgreSQL │ │   Redis    │ │   Workers   │
       └────────────┘ └────────────┘ └─────────────┘
              │
              │ Metadata
              │
              └─────────────────────┐
                                    ▼
                             ┌──────────────┐
                             │ S3 Storage   │
                             └──────────────┘
```

## 5.3 Request Lifecycle

```text
React SPA
  ↓
Nginx
  ↓
Laravel route
  ↓
Authentication middleware
  ↓
Tenant context middleware
  ↓
Authorization policy
  ↓
FormRequest validation
  ↓
Controller
  ↓
Application service / action
  ↓
Domain operation
  ↓
Repository/Eloquent query
  ↓
Database transaction
  ↓
Domain/application event
  ↓
Listeners / Queue jobs / Broadcast
  ↓
API Resource
  ↓
JSON response
```

---

# 6. Architectural Principles

1. **Thin controllers.**
2. **Business logic must not live in React components.**
3. **Business-critical backend changes execute inside database transactions.**
4. **Tenant scoping is mandatory and centralized.**
5. **Authorization must be enforced on the server even if UI controls are hidden.**
6. **Frontend server state is owned by TanStack Query.**
7. **Domain transitions are explicit rather than arbitrary string updates.**
8. **Background jobs must be idempotent where practical.**
9. **Every sensitive mutation produces an audit event.**
10. **APIs return predictable error envelopes.**
11. **Observability is designed into the application.**
12. **No premature microservices.**

---

# 7. User Types and Roles

## 7.1 Platform-Level Role

### Platform Administrator

Capabilities:

- manage organization records;
- activate/suspend tenants;
- inspect high-level platform health;
- support organization onboarding.

Platform administrators are outside normal tenant data access and must not automatically gain unrestricted access to customer content.

## 7.2 Tenant Roles

### Organization Admin

- manage organization settings;
- manage users;
- manage roles and permissions;
- manage customers;
- manage projects;
- manage SLA policies;
- view all tenant dashboards;
- manage integrations.

### Manager

- manage customers/projects;
- assign service requests;
- reassign operators;
- change request priority;
- approve escalations;
- view team performance;
- view SLA reports.

### Operator

- view assigned/eligible requests;
- update request status;
- add comments;
- upload attachments;
- log work;
- resolve requests.

### Finance

- view customer/project billing context;
- create invoices;
- issue invoices;
- record payment state;
- view financial dashboard.

### Customer

- submit service requests;
- view requests belonging to their customer account;
- add comments;
- upload attachments;
- approve/reject approval requests;
- view issued invoices.

---

# 8. RBAC Permission Model

Suggested permissions:

```text
organization.view
organization.update

user.view
user.create
user.update
user.disable
user.manage_roles

role.view
role.create
role.update
role.delete

customer.view
customer.create
customer.update
customer.archive

project.view
project.create
project.update
project.archive

request.view
request.create
request.update
request.assign
request.change_priority
request.transition
request.resolve
request.close
request.reopen

comment.create
comment.update_own
comment.delete_own
comment.internal

attachment.create
attachment.download
attachment.delete

approval.view
approval.request
approval.respond
approval.cancel

invoice.view
invoice.create
invoice.update
invoice.issue
invoice.void
invoice.mark_paid

sla.view
sla.manage

audit.view

dashboard.view
report.view
```

## 8.1 Authorization Rules

Authorization should combine:

- authenticated identity;
- organization membership;
- customer membership if applicable;
- role permissions;
- resource ownership;
- resource state.

Example:

```php
public function assign(User $user, ServiceRequest $request): bool
{
    return $user->organization_id === $request->organization_id
        && $user->hasPermission('request.assign')
        && $request->isAssignable();
}
```

Frontend permission checks improve UX but never replace backend policies.

---

# 9. Multi-Tenancy

## 9.1 Model

V1 uses **single database, shared schema, row-level tenant ownership**.

Each tenant-owned record includes:

```text
organization_id UUID NOT NULL
```

Advantages:

- simpler operations;
- easier portfolio deployment;
- easier migrations;
- lower infrastructure cost;
- still demonstrates serious tenant isolation.

## 9.2 Tenant Resolution

For authenticated API requests, tenant context is derived from the authenticated user's active membership.

Optional future domain support:

```text
acme.opsflow.example
globex.opsflow.example
```

## 9.3 Tenant Isolation

Every tenant-owned query must be scoped.

Example implementation strategies:

- global Eloquent tenant scope;
- explicit tenant repository boundaries;
- tenant-context service;
- policy verification.

Do not depend on controller developers remembering `where('organization_id', ...)` manually.

## 9.4 Tenant Context

```php
final class TenantContext
{
    public function id(): string;
    public function organization(): Organization;
}
```

Middleware resolves tenant once and binds `TenantContext` into Laravel's service container.

## 9.5 Cross-Tenant Test Requirement

The test suite must explicitly prove:

```text
User from tenant A cannot:
- read tenant B request;
- update tenant B customer;
- download tenant B attachment;
- access tenant B invoice;
- approve tenant B approval;
- discover tenant B resources through list/search endpoints.
```

---

# 10. Core Domain Modules

```text
Identity & Access
Organizations
Customers
Projects
Service Requests
Workflow
SLA
Comments
Attachments
Approvals
Notifications
Invoices
Audit
Dashboard & Reporting
```

Each module should own its domain logic and expose explicit application actions/services.

---

# 11. Functional Requirements

# 11.1 Authentication

### FR-AUTH-001 Login

Users can authenticate using email and password.

Acceptance criteria:

- validates credentials;
- inactive users cannot log in;
- suspended organizations cannot log in;
- login attempts are rate limited;
- successful login returns current user and authorization context;
- failed attempts do not reveal whether an email exists.

### FR-AUTH-002 Logout

- invalidates session/token;
- clears frontend auth state.

### FR-AUTH-003 Current User

`GET /api/v1/me`

Returns:

- user profile;
- organization;
- role;
- permissions;
- feature flags;
- timezone.

### FR-AUTH-004 Password Reset

- reset token expiry;
- single use;
- asynchronous email;
- audit security event.

### FR-AUTH-005 Optional MFA

MFA can be added as a stretch feature.

---

# 11.2 Organization Management

### FR-ORG-001 Organization Profile

Fields:

```text
id
name
slug
legal_name
registration_number
country_code
timezone
currency
logo_path
status
created_at
updated_at
```

Default portfolio tenant:

```text
Country: Singapore
Timezone: Asia/Singapore
Currency: SGD
```

### FR-ORG-002 Organization Settings

- business hours;
- timezone;
- default SLA;
- notification preferences;
- request numbering;
- invoice numbering.

---

# 11.3 User Management

Functions:

- list users;
- invite user;
- activate/deactivate;
- assign role;
- update profile;
- search/filter;
- paginate.

Suggested user states:

```text
INVITED
ACTIVE
DISABLED
```

---

# 11.4 Customer Management

Customer fields:

```text
id
organization_id
customer_code
name
legal_name
registration_number
email
phone
billing_email
address_line_1
address_line_2
city
postal_code
country_code
status
metadata JSONB
created_by
created_at
updated_at
archived_at
```

Statuses:

```text
ACTIVE
INACTIVE
ARCHIVED
```

Customer detail page shows:

- profile;
- active projects;
- open requests;
- recent activity;
- SLA summary;
- invoices;
- contacts.

---

# 11.5 Customer Contacts

```text
customer_contacts
-----------------
id
organization_id
customer_id
user_id nullable
name
email
phone
job_title
is_primary
created_at
updated_at
```

A contact may later be converted/invited into a customer portal user.

---

# 11.6 Projects

Project fields:

```text
id
organization_id
customer_id
project_code
name
description
status
starts_on
ends_on
manager_id
sla_policy_id
created_at
updated_at
```

Statuses:

```text
DRAFT
ACTIVE
ON_HOLD
COMPLETED
ARCHIVED
```

---

# 11.7 Service Requests

Service requests are the principal domain aggregate.

Fields:

```text
id
organization_id
request_number
customer_id
project_id nullable
requested_by
assigned_to nullable
title
description
category_id nullable
priority
status
sla_policy_id
first_response_due_at
resolution_due_at
first_responded_at nullable
resolved_at nullable
closed_at nullable
version
created_at
updated_at
```

Priority:

```text
CRITICAL
HIGH
MEDIUM
LOW
```

Status:

```text
NEW
TRIAGED
IN_PROGRESS
WAITING_CUSTOMER
WAITING_INTERNAL
ESCALATED
RESOLVED
CLOSED
CANCELLED
```

---

# 12. Service Request Workflow

## 12.1 State Machine

```text
                     ┌───────────────┐
                     │      NEW      │
                     └───────┬───────┘
                             ▼
                     ┌───────────────┐
                     │    TRIAGED    │
                     └───────┬───────┘
                             ▼
                     ┌───────────────┐
                     │  IN_PROGRESS  │
                     └─┬──────┬────┬─┘
                       │      │    │
                       │      │    └────────────┐
                       │      ▼                 ▼
                       │ ┌───────────────┐ ┌───────────┐
                       │ │WAITING_CUSTOMER│ │ ESCALATED │
                       │ └───────┬───────┘ └─────┬─────┘
                       │         │               │
                       └─────────┴───────┬───────┘
                                         ▼
                                 ┌───────────────┐
                                 │   RESOLVED    │
                                 └───────┬───────┘
                                         ▼
                                 ┌───────────────┐
                                 │    CLOSED     │
                                 └───────────────┘
```

`CANCELLED` is reachable according to explicit business rules.

## 12.2 Transition Matrix

| From | To | Allowed Roles |
|---|---|---|
| NEW | TRIAGED | Manager, Operator |
| NEW | CANCELLED | Manager |
| TRIAGED | IN_PROGRESS | Manager, Operator |
| TRIAGED | CANCELLED | Manager |
| IN_PROGRESS | WAITING_CUSTOMER | Operator, Manager |
| IN_PROGRESS | WAITING_INTERNAL | Operator, Manager |
| IN_PROGRESS | ESCALATED | Manager/System |
| IN_PROGRESS | RESOLVED | Operator, Manager |
| WAITING_CUSTOMER | IN_PROGRESS | Operator, Manager/System |
| WAITING_INTERNAL | IN_PROGRESS | Operator, Manager |
| ESCALATED | IN_PROGRESS | Manager |
| ESCALATED | RESOLVED | Manager, Operator |
| RESOLVED | CLOSED | Manager/System |
| RESOLVED | IN_PROGRESS | Manager |
| CLOSED | IN_PROGRESS | Manager |
| Any eligible | CANCELLED | Manager |

## 12.3 Transition Requirements

A state transition must:

1. lock/reload request when necessary;
2. verify tenant;
3. authorize;
4. validate transition;
5. execute transaction;
6. update request;
7. insert history;
8. insert audit entry;
9. dispatch event after commit;
10. broadcast change;
11. invalidate relevant frontend queries.

---

# 13. SLA Engine

## 13.1 Purpose

The SLA subsystem makes OpsFlow substantially more representative of real production software.

## 13.2 SLA Policy

```text
sla_policies
------------
id
organization_id
name
description
timezone
business_calendar_id
active
created_at
updated_at
```

```text
sla_targets
-----------
id
sla_policy_id
priority
first_response_minutes
resolution_minutes
warning_threshold_percent
created_at
updated_at
```

Example:

| Priority | First Response | Resolution | Warning |
|---|---:|---:|---:|
| CRITICAL | 15m | 4h | 75% |
| HIGH | 60m | 8h | 75% |
| MEDIUM | 4h | 24h | 80% |
| LOW | 8h | 72h | 80% |

## 13.3 Business Hours

Calendar:

```text
Monday-Friday
09:00-18:00 Asia/Singapore
```

Support:

- working weekdays;
- daily working intervals;
- holidays;
- organization timezone.

SLA calculations must not simply add wall-clock minutes if policy uses business hours.

## 13.4 SLA States

Derived states:

```text
HEALTHY
AT_RISK
BREACHED
MET
```

## 13.5 SLA Monitoring Job

Scheduler runs once per minute:

```text
Schedule
  ↓
Dispatch CheckActiveServiceRequestSlas job
  ↓
Query unresolved eligible requests
  ↓
Calculate SLA state
  ↓
Detect state change
  ↓
Acquire idempotency/locking protection
  ↓
Create SLA event
  ↓
Dispatch notifications
  ↓
Broadcast dashboard/request update
```

## 13.6 Idempotency

The job must not repeatedly notify users for the same SLA threshold.

Suggested unique key:

```text
request_id + sla_type + threshold + due_at
```

---

# 14. Comments and Collaboration

Comments contain:

```text
id
organization_id
service_request_id
author_id
body
visibility
created_at
updated_at
deleted_at
```

Visibility:

```text
PUBLIC
INTERNAL
```

Customer users can never access `INTERNAL` comments.

Requirements:

- Markdown/plain-text rendering with sanitization;
- mention support can be optional;
- real-time comment updates;
- audit on deletion.

---

# 15. Attachments

Attachment record:

```text
id
organization_id
service_request_id nullable
comment_id nullable
approval_id nullable
uploaded_by
original_name
storage_disk
storage_path
mime_type
size_bytes
checksum
created_at
deleted_at
```

Requirements:

- allowlisted MIME types;
- configurable size limit;
- generated storage name, never trust supplied filename as path;
- tenant-scoped authorization;
- private bucket/storage;
- temporary signed download URLs;
- checksum for integrity;
- optional malware scanning integration as stretch feature.

---

# 16. Approval Workflow

Approvals enable customer or managerial sign-off.

```text
approvals
---------
id
organization_id
service_request_id
requested_by
assigned_to
type
title
description
status
due_at
responded_at
response_note
created_at
updated_at
```

Status:

```text
PENDING
APPROVED
REJECTED
CANCELLED
EXPIRED
```

Examples:

- customer acceptance;
- quotation approval;
- change request approval;
- manager exception approval.

A decision is immutable except through an explicit reset/reissue operation.

---

# 17. Invoice Module

The invoice module is intentionally lightweight.

Invoice fields:

```text
id
organization_id
invoice_number
customer_id
project_id nullable
status
currency
subtotal
tax_amount
total_amount
issued_at
due_at
paid_at nullable
notes
created_at
updated_at
```

Line items:

```text
invoice_items
-------------
id
invoice_id
description
quantity
unit_price
amount
sort_order
```

Statuses:

```text
DRAFT
ISSUED
PAID
OVERDUE
VOID
```

V1 does not integrate real payment processing.

---

# 18. Notifications

Channels:

- in-app;
- email;
- WebSocket.

Notification events:

```text
RequestCreated
RequestAssigned
RequestPriorityChanged
RequestStatusChanged
RequestCommentAdded
ApprovalRequested
ApprovalResponded
SlaAtRisk
SlaBreached
RequestResolved
InvoiceIssued
InvoiceOverdue
```

Notifications table should support:

```text
read_at
type
payload
actor
resource reference
```

Email delivery occurs asynchronously.

---

# 19. Audit Logging

## 19.1 Audit Record

```text
audit_logs
----------
id
organization_id
actor_id nullable
action
entity_type
entity_id
request_id nullable
old_values JSONB nullable
new_values JSONB nullable
metadata JSONB nullable
ip_address nullable
user_agent nullable
created_at
```

Examples:

```text
request.created
request.assigned
request.priority_changed
request.status_changed
customer.updated
user.role_changed
approval.approved
invoice.issued
attachment.downloaded
```

Audit logs should be append-only from normal application flows.

Sensitive values must be redacted.

Never log:

- passwords;
- raw auth tokens;
- session secrets;
- reset tokens.

---

# 20. Dashboard Requirements

## 20.1 Operations Dashboard

Cards:

- Open Requests
- Critical Requests
- At-Risk SLA
- SLA Compliance %
- Resolved Today
- Median Resolution Time

Charts:

- requests over time;
- requests by priority;
- requests by status;
- SLA compliance trend;
- team workload;
- request category distribution.

Tables:

- urgent requests;
- recently breached requests;
- recent activity.

## 20.2 Manager Dashboard

Additional:

- operator workload;
- operator resolution count;
- average first response;
- average resolution;
- reopened requests.

## 20.3 Customer Dashboard

- open requests;
- awaiting customer action;
- recent updates;
- approvals pending;
- invoices due.

---

# 21. Search, Filtering, Sorting, Pagination

List pages must implement server-side:

- pagination;
- sorting;
- filtering;
- search.

Example request:

```http
GET /api/v1/service-requests
    ?page=1
    &per_page=25
    &search=database
    &status[]=IN_PROGRESS
    &priority[]=HIGH
    &assigned_to=...
    &sort=-created_at
```

Allowed sort fields must be explicitly whitelisted.

Never interpolate arbitrary user-supplied SQL column names.

---

# 22. API Standards

## 22.1 Base URL

```text
/api/v1
```

## 22.2 Headers

```http
Accept: application/json
Content-Type: application/json
X-Request-ID: optional-client-id
```

Server emits:

```http
X-Request-ID: <uuid>
```

## 22.3 Success Envelope

Single resource:

```json
{
  "data": {
    "id": "..."
  },
  "meta": {
    "request_id": "..."
  }
}
```

Collection:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 100,
    "last_page": 4,
    "request_id": "..."
  }
}
```

## 22.4 Error Envelope

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The submitted data is invalid.",
    "fields": {
      "title": [
        "The title field is required."
      ]
    }
  },
  "meta": {
    "request_id": "..."
  }
}
```

## 22.5 HTTP Statuses

```text
200 OK
201 Created
202 Accepted
204 No Content
400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
422 Unprocessable Entity
429 Too Many Requests
500 Internal Server Error
```

---

# 23. Core API Endpoints

## Authentication

```text
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
GET    /api/v1/me
```

## Organization

```text
GET    /api/v1/organization
PATCH  /api/v1/organization
```

## Users

```text
GET    /api/v1/users
POST   /api/v1/users
GET    /api/v1/users/{id}
PATCH  /api/v1/users/{id}
POST   /api/v1/users/{id}/disable
POST   /api/v1/users/{id}/activate
PUT    /api/v1/users/{id}/roles
```

## Roles

```text
GET    /api/v1/roles
POST   /api/v1/roles
GET    /api/v1/roles/{id}
PATCH  /api/v1/roles/{id}
DELETE /api/v1/roles/{id}
```

## Customers

```text
GET    /api/v1/customers
POST   /api/v1/customers
GET    /api/v1/customers/{id}
PATCH  /api/v1/customers/{id}
DELETE /api/v1/customers/{id}
```

## Projects

```text
GET    /api/v1/projects
POST   /api/v1/projects
GET    /api/v1/projects/{id}
PATCH  /api/v1/projects/{id}
POST   /api/v1/projects/{id}/archive
```

## Requests

```text
GET    /api/v1/service-requests
POST   /api/v1/service-requests
GET    /api/v1/service-requests/{id}
PATCH  /api/v1/service-requests/{id}

POST   /api/v1/service-requests/{id}/assign
POST   /api/v1/service-requests/{id}/priority
POST   /api/v1/service-requests/{id}/transition

GET    /api/v1/service-requests/{id}/history
```

## Comments

```text
GET    /api/v1/service-requests/{id}/comments
POST   /api/v1/service-requests/{id}/comments
PATCH  /api/v1/comments/{id}
DELETE /api/v1/comments/{id}
```

## Attachments

```text
POST   /api/v1/service-requests/{id}/attachments
GET    /api/v1/attachments/{id}/download
DELETE /api/v1/attachments/{id}
```

## Approvals

```text
GET    /api/v1/approvals
POST   /api/v1/service-requests/{id}/approvals
GET    /api/v1/approvals/{id}
POST   /api/v1/approvals/{id}/approve
POST   /api/v1/approvals/{id}/reject
POST   /api/v1/approvals/{id}/cancel
```

## Invoices

```text
GET    /api/v1/invoices
POST   /api/v1/invoices
GET    /api/v1/invoices/{id}
PATCH  /api/v1/invoices/{id}
POST   /api/v1/invoices/{id}/issue
POST   /api/v1/invoices/{id}/mark-paid
POST   /api/v1/invoices/{id}/void
```

## SLA

```text
GET    /api/v1/sla-policies
POST   /api/v1/sla-policies
GET    /api/v1/sla-policies/{id}
PATCH  /api/v1/sla-policies/{id}
```

## Dashboard

```text
GET /api/v1/dashboard/overview
GET /api/v1/dashboard/request-volume
GET /api/v1/dashboard/sla
GET /api/v1/dashboard/team-performance
```

## Audit

```text
GET /api/v1/audit-logs
GET /api/v1/audit-logs/{id}
```

---

# 24. Database Model

## 24.1 Primary Tables

```text
organizations
users
organization_user_memberships
roles
permissions
role_permissions
user_roles

customers
customer_contacts
projects

service_request_categories
service_requests
service_request_status_histories
service_request_assignments

sla_policies
sla_targets
business_calendars
business_calendar_hours
business_calendar_holidays
sla_events

comments
attachments

approvals

invoices
invoice_items

notifications
audit_logs

personal_access_tokens
jobs
failed_jobs
```

## 24.2 ID Strategy

Use UUIDv7 or another sortable UUID strategy for externally visible aggregate IDs where Laravel/application support is appropriate.

Benefits:

- globally unique;
- harder to enumerate;
- time-sortable;
- useful for distributed generation.

Human-readable identifiers remain separate:

```text
REQ-2026-000001
CUS-000001
PRJ-2026-000001
INV-2026-000001
```

## 24.3 Request Number Generation

Must be concurrency-safe.

Potential implementation:

- per-organization/year sequence table;
- transaction + row lock;
- increment;
- format.

Do **not** use `count(*) + 1`.

---

# 25. Important Database Indexes

Examples:

```sql
CREATE INDEX idx_service_requests_org_status
ON service_requests (organization_id, status);

CREATE INDEX idx_service_requests_org_priority
ON service_requests (organization_id, priority);

CREATE INDEX idx_service_requests_org_assignee_status
ON service_requests (organization_id, assigned_to, status);

CREATE INDEX idx_service_requests_org_customer
ON service_requests (organization_id, customer_id);

CREATE INDEX idx_service_requests_resolution_due
ON service_requests (organization_id, resolution_due_at)
WHERE resolved_at IS NULL;

CREATE INDEX idx_comments_request_created
ON comments (service_request_id, created_at);

CREATE INDEX idx_audit_org_entity
ON audit_logs (organization_id, entity_type, entity_id, created_at DESC);
```

Indexes must be justified by query patterns, not added indiscriminately.

---

# 26. Transaction Boundaries

Use transactions for operations such as:

### Create Request

```text
BEGIN
  generate request number
  insert request
  insert initial history
  insert audit
COMMIT

AFTER COMMIT
  dispatch RequestCreated
```

### Transition Request

```text
BEGIN
  lock request
  validate current state/version
  update state
  insert state history
  insert audit
COMMIT

AFTER COMMIT
  dispatch RequestStatusChanged
```

### Issue Invoice

```text
BEGIN
  validate draft
  calculate totals
  assign invoice number
  mark issued
  insert audit
COMMIT

AFTER COMMIT
  queue email
```

---

# 27. Concurrency Control

Where two users may modify the same request:

- use optimistic locking with a `version` field for general edits;
- use `SELECT ... FOR UPDATE` / pessimistic locking for critical transitions when necessary.

Conflict response:

```http
409 Conflict
```

Example:

```json
{
  "error": {
    "code": "RESOURCE_VERSION_CONFLICT",
    "message": "This request was modified by another user. Refresh and try again."
  }
}
```

---

# 28. Laravel Backend Structure

```text
backend/
├── app/
│   ├── Actions/
│   │   ├── Auth/
│   │   ├── Customers/
│   │   ├── Projects/
│   │   ├── ServiceRequests/
│   │   ├── Approvals/
│   │   └── Invoices/
│   │
│   ├── Domain/
│   │   ├── ServiceRequests/
│   │   │   ├── Enums/
│   │   │   ├── Exceptions/
│   │   │   ├── Services/
│   │   │   └── ValueObjects/
│   │   └── SLA/
│   │
│   ├── Events/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   ├── Middleware/
│   │   ├── Requests/
│   │   └── Resources/
│   │
│   ├── Jobs/
│   ├── Listeners/
│   ├── Models/
│   ├── Notifications/
│   ├── Policies/
│   ├── Providers/
│   ├── Queries/
│   └── Support/
│
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── routes/
│   ├── api.php
│   └── console.php
└── tests/
    ├── Feature/
    ├── Unit/
    └── Architecture/
```

Do not create abstractions only for appearance. Each layer must have a clear responsibility.

---

# 29. Backend Coding Conventions

## Controller

Controller responsibilities:

- accept request;
- call authorization;
- invoke action/service;
- return resource.

Example:

```php
final class TransitionServiceRequestController
{
    public function __invoke(
        TransitionServiceRequestRequest $request,
        ServiceRequest $serviceRequest,
        TransitionServiceRequest $action
    ): ServiceRequestResource {
        $this->authorize('transition', $serviceRequest);

        $updated = $action->handle(
            request: $serviceRequest,
            target: ServiceRequestStatus::from($request->validated('status')),
            actor: $request->user(),
            note: $request->validated('note'),
        );

        return ServiceRequestResource::make($updated);
    }
}
```

## Action

```php
final readonly class TransitionServiceRequest
{
    public function handle(
        ServiceRequest $request,
        ServiceRequestStatus $target,
        User $actor,
        ?string $note,
    ): ServiceRequest {
        return DB::transaction(function () use ($request, $target, $actor, $note) {
            $locked = ServiceRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->workflow->assertCanTransition(
                from: $locked->status,
                to: $target,
            );

            $from = $locked->status;

            $locked->status = $target;
            $locked->version++;
            $locked->save();

            $locked->statusHistories()->create([
                'organization_id' => $locked->organization_id,
                'from_status' => $from,
                'to_status' => $target,
                'changed_by' => $actor->id,
                'note' => $note,
            ]);

            $this->audit->record(
                actor: $actor,
                action: 'request.status_changed',
                entity: $locked,
                oldValues: ['status' => $from->value],
                newValues: ['status' => $target->value],
            );

            DB::afterCommit(
                fn () => ServiceRequestStatusChanged::dispatch(
                    $locked->fresh(),
                    $from,
                    $target,
                    $actor,
                )
            );

            return $locked->fresh();
        });
    }
}
```

---

# 30. Domain Enums

Use backed enums:

```php
enum ServiceRequestStatus: string
{
    case New = 'NEW';
    case Triaged = 'TRIAGED';
    case InProgress = 'IN_PROGRESS';
    case WaitingCustomer = 'WAITING_CUSTOMER';
    case WaitingInternal = 'WAITING_INTERNAL';
    case Escalated = 'ESCALATED';
    case Resolved = 'RESOLVED';
    case Closed = 'CLOSED';
    case Cancelled = 'CANCELLED';
}
```

Same pattern for:

- priority;
- approval status;
- invoice status;
- user status.

---

# 31. Event-Driven Design

Events:

```text
ServiceRequestCreated
ServiceRequestAssigned
ServiceRequestStatusChanged
ServiceRequestPriorityChanged
CommentCreated
ApprovalRequested
ApprovalResponded
SlaAtRisk
SlaBreached
InvoiceIssued
```

Example event consumers:

```text
ServiceRequestAssigned
  ├── SendAssignmentNotification
  ├── BroadcastRequestUpdated
  └── UpdateOperationalMetrics
```

Use queued listeners when work is not required for the synchronous response.

---

# 32. Queue Strategy

Queues:

```text
high
default
notifications
reports
low
```

Examples:

- SLA breach: high
- normal email: notifications
- exports: reports
- cleanup: low

Job requirements:

- serializable payloads;
- retries;
- backoff;
- timeout;
- failure handling;
- idempotency when side effects can duplicate.

Queue dashboard via Horizon.

---

# 33. Laravel Scheduler

```php
Schedule::job(new CheckActiveServiceRequestSlas)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::job(new MarkOverdueInvoices)
    ->hourly()
    ->withoutOverlapping();

Schedule::job(new ExpirePendingApprovals)
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('model:prune')
    ->daily();
```

---

# 34. Real-Time Architecture

Use Laravel Reverb for WebSockets.

Channels:

```text
private-organization.{organizationId}
private-request.{requestId}
private-user.{userId}
```

Authorization must verify tenant and resource access.

Events:

```text
request.updated
request.comment.created
request.assigned
approval.requested
notification.created
dashboard.updated
```

React consumes events and invalidates/patches TanStack Query cache.

Do not use WebSocket payloads as the authoritative persisted state. The database/API remains the source of truth.

---

# 35. React Application Structure

```text
frontend/
├── src/
│   ├── app/
│   │   ├── providers/
│   │   ├── router/
│   │   └── config/
│   │
│   ├── api/
│   │   ├── client.ts
│   │   ├── query-client.ts
│   │   └── errors.ts
│   │
│   ├── auth/
│   ├── components/
│   │   ├── common/
│   │   ├── forms/
│   │   ├── feedback/
│   │   └── layout/
│   │
│   ├── features/
│   │   ├── dashboard/
│   │   ├── customers/
│   │   ├── projects/
│   │   ├── requests/
│   │   ├── approvals/
│   │   ├── invoices/
│   │   ├── users/
│   │   └── settings/
│   │
│   ├── hooks/
│   ├── lib/
│   ├── permissions/
│   ├── realtime/
│   ├── types/
│   ├── main.tsx
│   └── vite-env.d.ts
├── e2e/
└── tests/
```

---

# 36. React State Strategy

## 36.1 Server State

TanStack Query owns:

- API collections;
- API resources;
- mutation lifecycle;
- cache;
- invalidation;
- background refetch.

## 36.2 Local UI State

React state/context owns:

- dialogs;
- open/closed panels;
- temporary UI selections;
- wizard step state.

## 36.3 Form State

React Hook Form + Zod.

## 36.4 Avoid

Do not duplicate API data in global state unnecessarily.

---

# 37. Query Key Convention

```ts
export const requestKeys = {
  all: ['service-requests'] as const,
  lists: () => [...requestKeys.all, 'list'] as const,
  list: (filters: RequestFilters) =>
    [...requestKeys.lists(), filters] as const,
  details: () => [...requestKeys.all, 'detail'] as const,
  detail: (id: string) =>
    [...requestKeys.details(), id] as const,
};
```

---

# 38. Axios Client

Responsibilities:

- base URL;
- credentials/token strategy;
- request ID;
- normalized API errors;
- 401 handling.

Example:

```ts
export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
  },
});
```

For same-site SPA deployments, prefer secure HttpOnly cookie/session-based authentication through Sanctum where appropriate instead of exposing long-lived tokens to JavaScript.

---

# 39. Route Map

```text
/login

/app
  /dashboard

  /customers
  /customers/new
  /customers/:customerId

  /projects
  /projects/new
  /projects/:projectId

  /requests
  /requests/new
  /requests/:requestId

  /approvals
  /approvals/:approvalId

  /invoices
  /invoices/new
  /invoices/:invoiceId

  /team
  /roles

  /audit

  /settings
    /organization
    /sla
    /business-hours
    /notifications

/customer
  /dashboard
  /requests
  /requests/new
  /requests/:requestId
  /approvals
  /invoices
```

---

# 40. UI/UX Requirements

## 40.1 General

- responsive desktop-first SaaS design;
- usable at tablet widths;
- WCAG-aware contrast;
- keyboard accessible controls;
- loading skeletons;
- empty states;
- error states;
- consistent confirmation dialogs;
- clear destructive action styling;
- toast feedback only where appropriate.

## 40.2 Application Shell

Left navigation:

```text
OpsFlow
────────────
Dashboard

Operations
  Requests
  Approvals

Clients
  Customers
  Projects

Finance
  Invoices

Organization
  Team
  Audit
  Settings
```

Top bar:

- global search;
- notifications;
- organization name;
- current user menu.

---

# 41. Key Screens

## 41.1 Login

- email;
- password;
- remember me;
- forgot password;
- demo credentials panel in portfolio environment.

## 41.2 Dashboard

High-impact visual page for recruiters.

## 41.3 Requests List

Columns:

```text
Request
Customer
Priority
Status
Assignee
SLA
Updated
```

Functions:

- full-text search;
- saved filter optional;
- multi-filter;
- sort;
- pagination;
- row click;
- bulk actions only if justified.

## 41.4 Request Detail

Header:

```text
REQ-2026-000041
Database timeout in reporting service

[HIGH] [IN PROGRESS] [SLA 01:42:18]
```

Layout:

```text
Main content                 Right sidebar
──────────────────────────   ─────────────────
Description                  Customer
Conversation                 Project
Activity timeline            Assignee
Attachments                  Priority
Approvals                    SLA
                             Created
```

## 41.5 Customer Detail

Tabs:

- Overview
- Projects
- Requests
- Contacts
- Invoices
- Activity

---

# 42. Frontend Permission Component

```tsx
type CanProps = {
  permission: Permission;
  children: React.ReactNode;
  fallback?: React.ReactNode;
};

export function Can({
  permission,
  children,
  fallback = null,
}: CanProps) {
  const { hasPermission } = useAuth();

  return hasPermission(permission) ? children : fallback;
}
```

Use route-level guards plus control-level guards.

Again: backend is authoritative.

---

# 43. Form Validation

Backend validation is mandatory.

Frontend Zod schemas should mirror UX-relevant constraints.

Example:

```ts
const createRequestSchema = z.object({
  customerId: z.string().uuid(),
  projectId: z.string().uuid().nullable(),
  title: z.string().trim().min(5).max(200),
  description: z.string().trim().min(10).max(10_000),
  priority: z.enum(['CRITICAL', 'HIGH', 'MEDIUM', 'LOW']),
});
```

---

# 44. Security Requirements

## 44.1 Authentication

- secure cookie configuration;
- HTTPS only in production;
- SameSite policy;
- CSRF protection for cookie-based auth;
- login rate limiting;
- password hashing using framework-recommended algorithm.

## 44.2 Authorization

- every tenant resource protected by policy;
- route/model binding must not bypass tenant validation;
- deny by default.

## 44.3 SQL Injection

- Eloquent/query binding;
- whitelist sort fields;
- avoid untrusted raw expressions.

## 44.4 XSS

- escape rendered user content;
- sanitize any allowed rich text;
- do not render raw HTML from comments.

## 44.5 CSRF

Sanctum SPA configuration + Laravel CSRF middleware.

## 44.6 File Upload Security

- allowlist MIME;
- extension not trusted;
- size limit;
- generated path;
- private storage;
- authorization before download.

## 44.7 Rate Limits

Suggested:

```text
Login: 5/minute per identity/IP
Password reset: 3/hour
General API: 120/minute/user
Uploads: 20/minute/user
Exports: 5/hour/user
```

Tune as needed.

## 44.8 Secrets

Never commit:

```text
.env
API keys
database passwords
AWS credentials
Sentry auth tokens
```

Use CI/environment secrets.

---

# 45. Privacy and Data Handling

- collect only required customer data;
- tenant boundary is a security boundary;
- redact sensitive audit values;
- define retention policy;
- support soft deletion only where business/audit needs justify it;
- use hard deletion for temporary data when safe;
- uploaded objects deleted according to lifecycle rules.

---

# 46. Observability

## 46.1 Structured Logging

Each request should include:

```text
request_id
user_id
organization_id
route
method
status
duration_ms
```

Use JSON logs in production.

## 46.2 Error Tracking

Capture:

- exceptions;
- release version;
- environment;
- trace ID/request ID;
- user ID if privacy policy allows;
- tenant ID if appropriate.

## 46.3 Metrics

Suggested metrics:

```text
http_request_duration_seconds
http_requests_total
queue_job_duration_seconds
queue_jobs_failed_total
service_requests_created_total
service_requests_resolved_total
sla_breaches_total
sla_at_risk_total
websocket_connections
```

## 46.4 Health Checks

```text
GET /up
```

Optional richer internal endpoint:

```text
GET /internal/health
```

Checks:

- app;
- database;
- Redis;
- queue heartbeat;
- storage.

Do not expose sensitive diagnostics publicly.

---

# 47. Performance Requirements

Initial target:

| Metric | Target |
|---|---|
| Normal API p95 | < 500 ms |
| List API p95 | < 750 ms |
| Dashboard API p95 | < 1.5 s |
| SPA route transition | perceived immediate / cached where possible |
| Queue normal job start | < 10 s |
| WebSocket update | < 2 s |

Portfolio targets are not contractual enterprise SLOs but provide measurable engineering criteria.

---

# 48. Performance Techniques

Backend:

- selective columns;
- eager loading;
- pagination;
- indexed filtering;
- aggregate queries;
- cache expensive stable metrics;
- Redis lock for conflicting scheduled work;
- avoid N+1.

Frontend:

- route-level lazy loading;
- query caching;
- stale times;
- virtualization only for truly large lists;
- code splitting;
- memoization only when measured/justified.

---

# 49. Testing Strategy

Testing pyramid:

```text
                E2E
             /       \
        Feature/API
       /             \
  Domain Unit Tests
```

The most important tests are business behavior tests.

---

# 50. Backend Unit Tests

Examples:

```text
SlaCalculatorTest
BusinessCalendarTest
RequestWorkflowTest
InvoiceTotalsTest
RequestNumberGeneratorTest
PermissionEvaluatorTest
```

Critical cases:

- invalid state transition rejected;
- SLA skips weekend/non-working hours;
- SLA respects Singapore timezone;
- invoice totals are exact;
- request sequence is concurrency-safe.

---

# 51. Backend Feature Tests

Authentication:

```text
user can login
disabled user cannot login
suspended tenant cannot login
login throttles brute force attempts
```

Tenant security:

```text
tenant A cannot read tenant B request
tenant A cannot update tenant B customer
tenant A cannot download tenant B file
customer cannot see internal comment
```

Request workflow:

```text
manager can assign
operator cannot perform forbidden transition
status transition writes history
status transition writes audit
event dispatched after commit
```

SLA:

```text
request receives calculated deadlines
at-risk event fires once
breach notification fires once
resolved request is excluded
```

Invoice:

```text
draft can be issued
issued invoice cannot be arbitrarily edited
mark-paid records audit
```

---

# 52. Architecture Tests

Use Pest architecture expectations to enforce boundaries where useful.

Examples:

- controllers do not directly contain domain-heavy logic;
- domain classes do not depend on HTTP controllers;
- actions follow naming conventions;
- prohibited debug functions absent.

---

# 53. Frontend Tests

## Component

Examples:

```text
RequestPriorityBadge
SlaCountdown
PermissionGate
RequestFilters
ApprovalDecisionDialog
```

## Feature

Examples:

```text
Requests list renders server data
Filters update query
Create request validates form
Transition mutation updates detail
403 renders authorization feedback
```

---

# 54. End-to-End Tests

Use Playwright for representative critical paths.

### E2E-001 Manager request lifecycle

```text
login
→ open requests
→ create request
→ assign operator
→ change priority
→ transition
→ verify timeline
```

### E2E-002 Customer collaboration

```text
customer login
→ create request
→ upload attachment
→ comment
→ receive update
```

### E2E-003 Approval

```text
manager requests customer approval
→ customer approves
→ internal user sees real-time status
```

### E2E-004 Cross-role authorization

```text
operator navigates to admin route
→ access denied
```

---

# 55. Minimum Test Quality Gate

Suggested portfolio target:

```text
Backend:
- 80%+ coverage on domain/application critical paths
- 100% coverage not required

Frontend:
- important features and utilities covered
- avoid testing implementation details

E2E:
- 4–8 stable high-value scenarios
```

Quality is more important than vanity coverage.

---

# 56. Seed Data

Demo seeder must create realistic data.

Organizations:

```text
Merlion Digital Services Pte. Ltd.
```

Users:

```text
admin@demo.opsflow.local
manager@demo.opsflow.local
operator@demo.opsflow.local
finance@demo.opsflow.local
customer@demo.opsflow.local
```

Customers:

```text
Orchid Retail Pte. Ltd.
Lion City Logistics Pte. Ltd.
HarbourTech Solutions Pte. Ltd.
```

Projects:

```text
Managed Application Support
Regional Reporting Platform
Customer Portal Revamp
```

Seed:

- 100+ requests;
- mix of priorities/statuses;
- SLA healthy/at-risk/breached examples;
- comments;
- approvals;
- invoices;
- audit entries.

This makes the dashboard visually meaningful immediately after startup.

---

# 57. Local Development

## 57.1 Repository

Recommended monorepo:

```text
opsflow/
├── backend/
├── frontend/
├── docker/
├── docs/
├── .github/
├── compose.yaml
├── Makefile
└── README.md
```

## 57.2 Services

```text
nginx
backend
frontend
postgres
redis
queue
scheduler
reverb
mailpit
minio
```

Mailpit provides local email inspection.

MinIO provides local S3-compatible storage.

---

# 58. Developer Commands

Makefile examples:

```make
setup:
	docker compose build
	docker compose up -d
	docker compose exec backend composer install
	docker compose exec frontend npm install
	docker compose exec backend php artisan migrate --seed

up:
	docker compose up -d

down:
	docker compose down

backend-test:
	docker compose exec backend php artisan test

frontend-test:
	docker compose exec frontend npm test

lint:
	docker compose exec backend ./vendor/bin/pint --test
	docker compose exec frontend npm run lint
```

Desired onboarding:

```bash
git clone <repo>
cd opsflow
cp .env.example .env
make setup
```

---

# 59. Environment Variables

Backend example categories:

```text
APP_*
DB_*
REDIS_*
QUEUE_*
REVERB_*
MAIL_*
FILESYSTEM_*
AWS_*
SENTRY_*
```

Frontend:

```text
VITE_API_BASE_URL
VITE_REVERB_HOST
VITE_REVERB_PORT
VITE_REVERB_SCHEME
```

Provide `.env.example` with safe placeholder values.

---

# 60. CI Pipeline

On pull request:

```text
Checkout
  ↓
Backend dependency install
  ↓
Laravel Pint check
  ↓
PHPStan/Larastan
  ↓
Pest backend test suite
  ↓
Frontend dependency install
  ↓
ESLint
  ↓
TypeScript type check
  ↓
Vitest
  ↓
Production frontend build
```

Optional:

```text
Playwright E2E
```

Run E2E on main branch or selected PRs if CI cost/time becomes significant.

---

# 61. CD Pipeline

On merge/tag:

```text
main
 ↓
CI quality gates
 ↓
Build backend image
 ↓
Build frontend image
 ↓
Push images to GHCR
 ↓
Deploy
 ↓
Run migration
 ↓
Restart workers
 ↓
Health check
 ↓
Mark release
```

Database migrations must be backward-safe whenever feasible.

---

# 62. Docker Production Layout

Suggested processes:

```text
opsflow-web
opsflow-php
opsflow-queue
opsflow-scheduler
opsflow-reverb
postgres-managed
redis-managed
object-storage
```

For a portfolio deployment, PostgreSQL/Redis can be containerized; for a credible production architecture discussion, recommend managed services.

---

# 63. Backup and Recovery

Production design:

- PostgreSQL automated backups;
- point-in-time recovery where provider supports it;
- object storage versioning/lifecycle;
- infrastructure configuration in source control;
- secrets stored outside repository.

Document RPO/RTO assumptions even if portfolio environment is non-critical.

---

# 64. API Documentation

Generate and expose OpenAPI documentation in development/demo environments.

Documentation must include:

- endpoint;
- request schema;
- response schema;
- auth requirements;
- permission requirement;
- status codes;
- example payload.

Potential route:

```text
/docs/api
```

Swagger/OpenAPI page should not expose production secrets.

---

# 65. Error Code Catalog

Examples:

```text
AUTH_INVALID_CREDENTIALS
AUTH_ACCOUNT_DISABLED

TENANT_SUSPENDED

VALIDATION_ERROR

FORBIDDEN

RESOURCE_NOT_FOUND
RESOURCE_VERSION_CONFLICT

REQUEST_INVALID_TRANSITION
REQUEST_NOT_ASSIGNABLE

APPROVAL_ALREADY_RESPONDED

INVOICE_NOT_EDITABLE
INVOICE_ALREADY_PAID

RATE_LIMITED
INTERNAL_ERROR
```

Frontend can map codes to contextual UI.

---

# 66. Request History / Timeline

Timeline combines:

- state transitions;
- assignments;
- priority changes;
- comments;
- attachments;
- approval events.

API:

```text
GET /api/v1/service-requests/{id}/timeline
```

Unified timeline DTO:

```json
{
  "id": "...",
  "type": "STATUS_CHANGED",
  "actor": {
    "id": "...",
    "name": "..."
  },
  "occurred_at": "2026-09-06T03:30:00Z",
  "data": {
    "from": "TRIAGED",
    "to": "IN_PROGRESS"
  }
}
```

---

# 67. Dashboard Query Design

Do not load thousands of rows and aggregate them in PHP.

Use SQL aggregation.

Example concepts:

```sql
SELECT status, COUNT(*)
FROM service_requests
WHERE organization_id = :organization
GROUP BY status;
```

For trend queries, group using time buckets appropriate to PostgreSQL.

Cache dashboard responses briefly, for example 30–60 seconds, then invalidate/refresh when significant events occur.

---

# 68. SLA Calculation Design

Pseudo-code:

```text
calculateDueAt(start, durationMinutes, calendar):
    remaining = durationMinutes
    cursor = start in calendar timezone

    while remaining > 0:
        if cursor is outside business hours:
            cursor = next business opening
            continue

        available = minutes until current business closing
        consumed = min(available, remaining)

        cursor += consumed minutes
        remaining -= consumed

    return cursor converted to UTC
```

All timestamps stored in UTC.

Organization/user timezones are presentation/calculation context.

---

# 69. Time Handling Rules

1. Database stores timestamps in UTC.
2. API returns ISO-8601 timestamps.
3. Organization has IANA timezone.
4. SLA calendar uses organization timezone.
5. Frontend displays according to organization/user preference.
6. Never hardcode UTC offsets such as `+08:00` for rules that should use a timezone identifier.

---

# 70. Money Handling Rules

Use decimal/numeric values, never binary floating point.

Suggested PostgreSQL:

```text
NUMERIC(18, 2)
```

For currencies requiring different decimal precision, introduce a money abstraction.

Portfolio V1 focuses on SGD and common 2-decimal currencies.

---

# 71. Soft Deletion Policy

Use soft deletion selectively.

Potential soft-delete:

- customers;
- contacts;
- projects;
- comments;
- attachments.

Avoid hiding financial/audit history in ways that break traceability.

Service requests should typically be cancelled/archived rather than deleted.

---

# 72. Accessibility

Target reasonable WCAG 2.2 AA patterns:

- form labels;
- keyboard navigation;
- focus states;
- semantic tables;
- aria labels where necessary;
- status not conveyed only by color;
- accessible modal focus management.

---

# 73. Responsive Behavior

Primary target:

```text
1440px desktop
1280px laptop
1024px tablet
```

Customer portal should remain usable at mobile widths.

Internal operations tables may use responsive column hiding or card transformations rather than horizontal chaos.

---

# 74. Internationalization Readiness

V1 UI language: English.

Architecture should avoid hardcoding business-facing strings across components.

Potential later languages:

- Bahasa Indonesia;
- Simplified Chinese.

Currency/time/date formatting should use locale-aware APIs.

---

# 75. Demo Environment

Public demo should provide:

```text
Manager Demo
Operator Demo
Customer Demo
```

Restrictions:

- rate limited;
- destructive admin operations constrained;
- seeded reset job optional;
- no real customer data;
- all credentials clearly demo-only.

---

# 76. README Requirements

README must include:

1. Product overview
2. Screenshots
3. Live demo URL
4. Demo credentials
5. Architecture
6. Tech stack
7. Major features
8. Local setup
9. Testing
10. Security
11. API docs
12. Engineering decisions
13. CI/CD
14. Roadmap

Opening:

> OpsFlow is a production-oriented multi-tenant operations platform built with Laravel 13 and React 19 to demonstrate modern full-stack engineering, relational data modeling, secure tenant isolation, workflow automation, SLA tracking, event-driven processing, real-time collaboration, automated testing, and deployable infrastructure.

---

# 77. Architecture Decision Records

Create:

```text
docs/adr/
├── 0001-modular-monolith.md
├── 0002-row-level-multi-tenancy.md
├── 0003-sanctum-spa-auth.md
├── 0004-postgresql.md
├── 0005-tanstack-query-server-state.md
├── 0006-domain-events-after-commit.md
└── 0007-sla-business-calendar.md
```

Example ADR structure:

```text
Context
Decision
Alternatives
Consequences
```

---

# 78. Engineering Decisions to Explain in Interview

## Why Laravel modular monolith?

Because OpsFlow needs strong domain separation, but its initial scale does not justify network boundaries, distributed transactions, service discovery, and operational complexity.

## Why PostgreSQL?

- mature relational semantics;
- transactions;
- indexing;
- JSONB when appropriate;
- advanced queries;
- strong ecosystem.

## Why Redis?

- queue backend;
- cache;
- locks;
- ephemeral state.

## Why TanStack Query?

Server state has cache/invalidation/refetch semantics fundamentally different from local UI state.

## Why Reverb?

Native Laravel ecosystem integration demonstrates real-time features without introducing an unrelated external stack.

## Why row-level tenancy?

Best complexity/security tradeoff for the initial stage; tests and centralized scoping enforce isolation.

---

# 79. Implementation Phases

## Phase 0 — Foundation

Deliverables:

- repository;
- Docker Compose;
- Laravel 13 backend;
- React 19 + TypeScript + Vite frontend;
- PostgreSQL;
- Redis;
- Nginx;
- Mailpit;
- MinIO;
- CI baseline;
- code standards.

Verification:

```text
make setup
```

results in working frontend/backend with health endpoint.

---

## Phase 1 — Identity, Tenancy and RBAC

Deliver:

- organization;
- users;
- login/logout;
- current user;
- tenant context;
- roles;
- permissions;
- policies;
- React auth provider;
- protected routes;
- `<Can>` component.

Verification:

- tenant-crossing tests pass;
- permission tests pass.

---

## Phase 2 — Customers and Projects

Deliver:

- customer CRUD;
- contacts;
- project CRUD;
- filtering;
- pagination;
- detail pages;
- audit events.

Verification:

- customer/project E2E path;
- tenant isolation.

---

## Phase 3 — Service Request Core

Deliver:

- request creation;
- request listing;
- detail;
- assignment;
- priority;
- workflow state machine;
- history;
- request numbering.

Verification:

- workflow unit suite;
- transition feature suite;
- professional request UI.

---

## Phase 4 — Comments, Files, Real-Time

Deliver:

- public/internal comments;
- uploads;
- download authorization;
- Reverb;
- live request updates;
- notifications.

Verification:

- two browser sessions see update without refresh;
- customer cannot see internal comment.

---

## Phase 5 — SLA Engine

Deliver:

- SLA policy;
- SLA targets;
- business calendar;
- SLA calculator;
- scheduler;
- warnings;
- breaches;
- SLA badges/countdown;
- SLA dashboard.

Verification:

- business-hours tests;
- weekend test;
- warning/breach idempotency tests.

---

## Phase 6 — Approvals

Deliver:

- approval request;
- decision;
- expiration;
- notifications;
- timeline.

Verification:

- customer decision path E2E.

---

## Phase 7 — Finance

Deliver:

- invoices;
- items;
- totals;
- issue;
- paid/void;
- customer invoice page.

Verification:

- transaction and amount tests.

---

## Phase 8 — Dashboard and Reporting

Deliver:

- metrics APIs;
- charts;
- team performance;
- customer dashboard;
- caching.

Verification:

- realistic seeded dashboard.

---

## Phase 9 — Production Hardening

Deliver:

- structured logging;
- error tracking;
- health checks;
- rate limiting;
- security headers;
- production Docker;
- backup notes;
- performance review;
- OpenAPI;
- final CI/CD.

---

## Phase 10 — Portfolio Polish

Deliver:

- screenshots;
- demo;
- README;
- architecture diagrams;
- ADRs;
- test report;
- 3–5 minute English walkthrough;
- deployment.

---

# 80. Definition of Done

A feature is complete when:

- requirement implemented;
- backend authorization implemented;
- validation implemented;
- tenant scope verified;
- tests added;
- frontend loading/error/empty states implemented;
- accessibility basics verified;
- audit event added if sensitive;
- API documented;
- no lint/type/static-analysis violations;
- CI passes.

---

# 81. Suggested Git Workflow

Branches:

```text
main
feature/...
fix/...
chore/...
```

Commit convention:

```text
feat(requests): add request transition workflow
fix(sla): skip non-working holidays
test(auth): add cross-tenant access coverage
docs(adr): explain row-level tenancy
```

Use small reviewable pull requests even if developing solo.

This gives recruiters evidence of professional Git discipline.

---

# 82. Pull Request Template

```md
## What

## Why

## Changes

## Screenshots

## Testing

## Database changes

## Security considerations

## Checklist
- [ ] Tests added/updated
- [ ] API docs updated
- [ ] No secrets committed
- [ ] Migration reviewed
```

---

# 83. Code Quality Gates

Backend:

```text
Laravel Pint
Larastan/PHPStan
Pest
Composer audit
```

Frontend:

```text
ESLint
TypeScript noEmit check
Prettier check
Vitest
npm audit / dependency scanning where appropriate
```

Repository:

```text
GitHub Dependabot
secret scanning where available
```

---

# 84. Security Test Checklist

Must verify:

```text
[ ] SQL injection attempts fail
[ ] Cross-tenant IDs do not expose data
[ ] Customer cannot fetch internal comments
[ ] Upload filename cannot escape path
[ ] Unauthorized signed URL generation denied
[ ] Invalid WebSocket channel authorization denied
[ ] Disabled users lose access
[ ] Login rate limit works
[ ] CSRF protection active
[ ] API sort fields whitelisted
[ ] Secrets absent from repo
[ ] Audit log redacts sensitive values
```

---

# 85. Performance Test Scenarios

Use lightweight load testing later.

Scenarios:

```text
100 concurrent request-list reads
20 concurrent request creates
20 concurrent transitions
dashboard reads with realistic seed volume
SLA scan of 50k unresolved requests
```

The purpose is to identify obvious query/index/N+1 weaknesses, not claim unsupported hyperscale.

---

# 86. Seeded Scale for Performance Testing

Suggested local benchmark dataset:

```text
10 organizations
1,000 customers
2,000 projects
50,000 service requests
250,000 comments
100,000 audit entries
```

A separate benchmark seeder can generate this dataset.

---

# 87. Future Enhancements

After the principal portfolio version:

- SSO / OIDC;
- MFA;
- richer customer contract management;
- email-to-request ingestion;
- webhook API;
- Slack/Microsoft Teams notifications;
- advanced report exports;
- saved searches;
- custom request fields;
- configurable workflow definitions;
- semantic request search;
- AI-assisted request summary;
- knowledge base suggestions;
- mobile PWA improvements;
- database row-level security evaluation;
- infrastructure-as-code.

AI features should be secondary. They must not distract from demonstrating Laravel + React competence.

---

# 88. Optional Laravel 13 Showcase Features

Laravel 13 introduces newer capabilities that may be selectively demonstrated.

Potential additions after MVP:

- JSON:API-oriented resources where justified;
- Laravel Scout semantic/hybrid search for historical service requests;
- Laravel Pennant feature flags;
- modern queue/cache improvements.

Do not force new framework capabilities into the project simply to advertise them.

---

# 89. Recruiter Demo Scenario

The demo should tell a story.

## Step 1 — Manager

Login as manager.

Show:

```text
Dashboard:
7 critical requests
3 SLA at risk
96.4% SLA compliance
```

## Step 2 — Customer

Create request:

```text
Title:
Intermittent API timeout during checkout

Priority:
HIGH

Description:
Checkout API experiences repeated timeouts...
```

## Step 3 — Manager

Request appears in real time.

Manager:

- assigns operator;
- changes status;
- shows SLA timer.

## Step 4 — Operator

Operator comments and uploads a diagnostic report.

## Step 5 — Customer

Customer receives live notification and replies.

## Step 6 — SLA

Explain scheduled background SLA monitoring and warning events.

## Step 7 — Approval

Operator requests customer approval.

Customer approves.

## Step 8 — Resolution

Operator resolves request.

Timeline visibly contains every important event.

## Step 9 — Architecture

Show:

- Laravel actions/policies;
- workflow unit test;
- PostgreSQL schema/index;
- React query hook;
- GitHub Actions.

This connects polished UI to engineering depth.

---

# 90. English Video Walkthrough Outline

Target length: 3–5 minutes.

```text
00:00 — Introduction
00:20 — Problem OpsFlow solves
00:50 — Technical architecture
01:20 — Customer request flow
02:00 — Workflow + authorization
02:30 — SLA/background processing
03:00 — Real-time collaboration
03:30 — Testing/security
04:00 — CI/CD and deployment
04:30 — Closing
```

Do not read source code line by line.

Explain engineering decisions clearly and confidently.

---

# 91. Acceptance Criteria for Portfolio Release

The portfolio version is considered ready when:

### Product

- [ ] Internal user can login.
- [ ] Customer user can login.
- [ ] Manager can manage customers/projects.
- [ ] Customer can create request.
- [ ] Manager can assign request.
- [ ] Authorized user can transition request.
- [ ] Invalid transitions are blocked.
- [ ] Public/internal comments work.
- [ ] Attachments work securely.
- [ ] Real-time updates work.
- [ ] SLA due dates calculate correctly.
- [ ] At-risk and breach automation works.
- [ ] Approval flow works.
- [ ] Invoice flow works.
- [ ] Dashboards show seeded data.
- [ ] Audit logs record sensitive changes.

### Engineering

- [ ] Multi-tenant tests pass.
- [ ] RBAC tests pass.
- [ ] Workflow tests pass.
- [ ] SLA tests pass.
- [ ] Frontend type check passes.
- [ ] Static analysis passes.
- [ ] Lint passes.
- [ ] CI passes.
- [ ] Production build succeeds.
- [ ] OpenAPI docs available.
- [ ] Docker setup documented.
- [ ] README completed.
- [ ] ADRs completed.

### Portfolio

- [ ] Live deployment available.
- [ ] Demo credentials available.
- [ ] Screenshots available.
- [ ] Architecture diagram available.
- [ ] English video walkthrough recorded.

---

# 92. Suggested First API Implementation Order

Implement in this order:

```text
1. GET /up
2. Auth login/logout/me
3. Organization context
4. Users/roles/permissions
5. Customers
6. Projects
7. Service requests CRUD
8. Assignment
9. Transition
10. Comments
11. Attachments
12. SLA
13. Approvals
14. Notifications
15. Invoices
16. Dashboard
17. Audit search
```

This minimizes dependency inversion and rework.

---

# 93. Suggested First Frontend Implementation Order

```text
1. Application shell
2. Login
3. Auth provider
4. Permission guard
5. Dashboard placeholder
6. Customers list/detail/form
7. Projects list/detail/form
8. Requests list
9. Request creation
10. Request detail
11. Assignment/transition
12. Comments
13. File uploads
14. Real-time
15. SLA UI
16. Approvals
17. Invoices
18. Dashboard charts
19. Team/admin/settings
20. Audit
```

---

# 94. Initial Migration Sequence

```text
000001_create_organizations
000002_create_users
000003_create_memberships
000004_create_roles
000005_create_permissions
000006_create_role_permission
000007_create_user_roles

000010_create_customers
000011_create_customer_contacts
000012_create_projects

000020_create_service_request_categories
000021_create_service_requests
000022_create_service_request_status_histories
000023_create_service_request_assignments

000030_create_business_calendars
000031_create_business_calendar_hours
000032_create_business_calendar_holidays
000033_create_sla_policies
000034_create_sla_targets
000035_create_sla_events

000040_create_comments
000041_create_attachments

000050_create_approvals

000060_create_invoices
000061_create_invoice_items

000070_create_audit_logs
```

Laravel-generated framework tables can be sequenced separately.

---

# 95. Core Domain Relationships

```text
Organization
 ├── Users
 ├── Roles
 ├── Customers
 │    ├── Contacts
 │    ├── Projects
 │    │    └── ServiceRequests
 │    └── Invoices
 │
 ├── ServiceRequests
 │    ├── StatusHistories
 │    ├── Assignments
 │    ├── Comments
 │    ├── Attachments
 │    ├── Approvals
 │    └── SLAEvents
 │
 ├── SLAPolicies
 │    └── SLATargets
 │
 └── AuditLogs
```

---

# 96. Request Aggregate Invariants

A service request must satisfy:

1. organization exists;
2. customer belongs to organization;
3. project, if present, belongs to customer and organization;
4. requester can access customer;
5. assignee, if present, belongs to organization;
6. status transition is legal;
7. priority is valid;
8. resolved timestamp exists only in resolved/closed contexts;
9. SLA policy belongs to organization;
10. all child records share organization ownership.

These invariants should drive test cases.

---

# 97. Operational Failure Handling

## Email provider failure

- request transaction remains successful;
- notification job retries;
- failure visible in Horizon/logs.

## WebSocket outage

- database/API remains authoritative;
- React refresh/refetch still works.

## Redis outage

Depending on failure mode:

- queues/cache/realtime degraded;
- API should avoid catastrophic dependency on cache for correctness.

## Object storage failure

- upload returns controlled failure;
- request itself remains intact.

## Database failure

- fail request safely;
- error captured;
- no partial transaction.

---

# 98. Logging Examples

Application event:

```json
{
  "level": "info",
  "message": "service_request.transitioned",
  "request_id": "...",
  "organization_id": "...",
  "user_id": "...",
  "service_request_id": "...",
  "from": "TRIAGED",
  "to": "IN_PROGRESS",
  "duration_ms": 42
}
```

Never put complete comment contents or sensitive attachment contents in application logs.

---

# 99. Interview Talking Points

A reviewer should be able to ask:

- Why modular monolith instead of microservices?
- How do you guarantee tenant isolation?
- How does your SLA algorithm handle weekends?
- How do you avoid duplicate SLA notifications?
- Why dispatch events after database commit?
- How do you handle concurrent request transitions?
- How do you prevent N+1 queries?
- Why TanStack Query instead of Redux for API data?
- What happens when WebSockets are unavailable?
- How do you authorize file downloads?
- What is your rollback strategy for migrations?
- How would you scale queue workers?
- How would you support 1 million requests?

The repository should contain enough evidence to answer each question concretely.

---

# 100. Final Product Positioning

OpsFlow should not be presented as:

> “A sample Laravel/React CRUD project.”

It should be presented as:

> **A production-oriented multi-tenant operations platform demonstrating modern Laravel 13 and React 19 engineering across workflow automation, relational data modeling, RBAC, SLA management, real-time events, asynchronous jobs, auditability, automated testing, observability, containerized deployment, and CI/CD.**

The showcase is successful when a technical reviewer can inspect the repository and conclude that the engineer understands both **feature delivery** and **production engineering discipline**.

---

# Appendix A — Source Baseline

Version choices were checked against current official documentation available on 6 September 2026:

- Laravel official release notes — Laravel 13, released 17 March 2026; PHP 8.3–8.5 supported.
- PHP official releases — PHP 8.5 is the current stable branch; 8.5.10 released 27 August 2026.
- React official versions — React 19.2 is the latest documented stable version.
- Vite official blog — Vite 8.1 released 23 June 2026.
- TanStack Query official documentation — v5 is the current documented React Query major.
- PostgreSQL official version policy — PostgreSQL 18.6 is the current stable minor; PostgreSQL 19 remains pre-release at document date.
- Redis official version management documentation — Redis 8.x current supported releases, with extended-support releases available.

Always pin concrete patch versions through lockfiles (`composer.lock`, package manager lockfile) and update them through dependency automation after tests pass.

---

# Appendix B — Recommended Repository Evidence Checklist

When sending OpsFlow to the company, the public repository should make these items easy to find:

```text
[ ] README with screenshots
[ ] Live demo
[ ] Demo credentials
[ ] Architecture diagram
[ ] ERD
[ ] OpenAPI
[ ] CI badge
[ ] Test commands
[ ] Docker one-command setup
[ ] Laravel Policies
[ ] Tenant isolation test
[ ] Workflow test
[ ] SLA test
[ ] React Query usage
[ ] React permission guard
[ ] Reverb integration
[ ] Queue/Horizon setup
[ ] Database indexes
[ ] ADR documents
[ ] English walkthrough video
```

---

**End of Technical PRD**
