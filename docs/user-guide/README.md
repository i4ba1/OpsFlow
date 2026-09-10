# User Guide

How to use OpsFlow day to day. OpsFlow is a multi-tenant operations platform:
your organization manages customers, projects, and service requests, with SLA
tracking, approvals, invoicing, and real-time notifications.

- [Signing in](#signing-in)
- [Roles and what you can do](#roles-and-what-you-can-do)
- [Navigation](#navigation)
- [Dashboard](#dashboard)
- [Customers and contacts](#customers-and-contacts)
- [Projects](#projects)
- [Service requests](#service-requests)
- [Approvals](#approvals)
- [Invoices](#invoices)
- [SLA policies](#sla-policies)
- [Notifications and real-time updates](#notifications-and-real-time-updates)
- [Tips](#tips)

## Signing in

Open the app URL (locally <http://localhost:8080>) and sign in with your email
and password. The demo environment uses the password `password` for every account
(see the [Demo walkthrough](../demo-walkthrough/README.md)). Sign out from the
top-right menu.

## Roles and what you can do

Your role determines which navigation items and buttons you see. The API enforces
the same rules, so nothing is hidden-only.

| Role | Typical capabilities |
| --- | --- |
| **Organization admin** | Everything: users, roles, customers, projects, requests, SLAs, approvals, invoices, audit logs, reports. |
| **Manager** | Manage customers and projects; create/assign/transition requests; request and respond to approvals; create invoices; view SLAs, audit logs, and reports. |
| **Operator** | Work service requests (assign is manager/admin), comment, upload attachments, request approvals, respond to approvals, view the dashboard. |
| **Finance** | Create/issue/void invoices, mark them paid; view customers, projects, requests, and reports. |
| **Customer** | Create and track their own requests, comment, upload attachments, respond to approvals, and view their invoices. |

## Navigation

The left sidebar shows only the areas you can access: **Dashboard**,
**Customers**, **Projects**, **Requests**, **Approvals**, and **Invoices**. SLA
policies live under settings for users with the SLA permission.

## Dashboard

The operations dashboard is a live snapshot of the tenant:

- **Open requests**, **Critical**, **At-risk SLA**, **Breached SLA**
- **SLA compliance** percentage and **median resolution** time
- **Requests over the last 30 days** (bar chart)
- **Requests by priority** (pie chart)

Use it to spot SLA risk and workload at a glance.

## Customers and contacts

**Customers** lists every client with code, status, and contact count.

- **Create** a customer (name required; legal name, registration number, email,
  phone, billing email, and address optional).
- Open a customer to see its **profile** and **contacts**.
- **Edit** the profile or **Archive** a customer you no longer serve (archiving
  is reversible in the database, not a hard delete).
- **Add contact** to record the people at the customer, optionally marking one
  as the primary contact.

## Projects

**Projects** lists engagements and the customer each belongs to.

- **Create** a project by selecting a customer, then setting a name, status,
  optional dates, description, and a manager.
- Open a project to view or **edit** its details, or **archive** it.

## Service requests

Service requests are the heart of OpsFlow. Each has a number (`REQ-YYYY-NNNNNN`),
status, priority, SLA timers, comments, attachments, and an activity timeline.

### Statuses

```text
NEW → TRIAGED → IN_PROGRESS ⇄ WAITING_CUSTOMER / WAITING_INTERNAL / ESCALATED
                       ↓
                   RESOLVED → CLOSED
   (any active state) → CANCELLED
```

Only legal transitions are offered; the buttons you see are exactly the moves you
are allowed to make.

### Create a request

1. Open **Requests** and click **New request**.
2. Pick a customer (and optionally a project), then enter a title, description,
   and priority (`CRITICAL`, `HIGH`, `MEDIUM`, `LOW`).
3. Submit. The request starts in **NEW** and receives an SLA due date from the
   active policy for its priority.

> Customers creating a request don't choose a customer — it is tied to their own
> company automatically.

### Work a request

Open a request from the list to see its description, details, conversation,
attachments, and timeline. The **Manage** panel gives you the actions your role
allows:

- **Assign** an operator.
- **Change priority**.
- **Transition** status (with an optional note). Resolving, closing, and
  reopening require those specific capabilities.

### Collaborate

- **Comments** — post a **Public** reply visible to the customer, or an
  **Internal** note visible only to your team (if you have the internal-comment
  permission).
- **Attachments** — upload files (PDF, Office, text, images, archives up to
  25 MB), download them, or delete them.
- **Request approval** — ask a colleague or the customer to sign off; track it in
  **Approvals**.

### SLA states

Each request shows an SLA badge and time remaining:

- **HEALTHY** — comfortably inside the resolution window.
- **AT_RISK** — past the warning threshold (a warning is raised).
- **BREACHED** — the resolution due date has passed.
- **MET** — resolved within the window.

Due dates are calculated against real business hours, so weekends and holidays
don't count against the team.

## Approvals

**Approvals** lists requests for sign-off with status, assignee, and due date.

- Open an approval assigned to you and **Approve** or **Reject**, optionally with
  a response note.
- Requesters (or users with cancel rights) can **Cancel** a pending approval.
- Pending approvals past their due date expire automatically.

## Invoices

**Invoices** tracks billing. Users with finance capabilities can:

- **Create** a draft invoice: choose a customer, add line items (description,
  quantity, unit price), and see the subtotal update live.
- **Issue** a draft to allocate an invoice number.
- **Mark paid** an issued or overdue invoice.
- **Void** an invoice that should not stand.

Invoices past their due date are flagged **OVERDUE** by a scheduled sweep.

## SLA policies

Under settings, users with SLA rights can review the **SLA policies** that drive
due dates: the policy name, timezone, business calendar, and per-priority first
response/resolution targets with warning thresholds. The seeded **Standard
Support SLA** uses the Singapore business calendar.

## Notifications and real-time updates

- The **Notifications** bell shows an unread count and the latest updates. Click
  one to jump to the related request, approval, or invoice, or **View all** for
  the full list.
- OpsFlow is **real-time**: when someone assigns, transitions, or comments on a
  request you're viewing, the page refreshes automatically. Notifications arrive
  without a manual reload.

## Tips

- Use the search box on each list to filter by name, code, or request number.
- Sort and paginate large lists; requests default to newest first.
- Watch the SLA badge color — amber means at risk, red means breached.
- Internal comments are never shown to customer users.
- If your session expires, you are returned to the sign-in screen; sign in again
  to continue.
