---
description: "Use the OpenHR PH document repository and approval workflows: versions, classification, acknowledgments, workflow definitions, delegations, approvals inbox and audit trail."
---

# Documents and workflow

This page covers the shared **Documents** and **Workflow** menus plus the HR **Approvals** inbox. Both
Private and Government installations use the same repository and workflow engine.

## Documents

### Create and upload

**Documents → Documents**

1. Create the document record with a title, category, document number, and description.
2. Choose an owner when the document belongs to an employee, position, organization unit, or another
   record. Owned documents can be shown to the owner in the employee portal.
3. Upload the file. Files are stored privately; downloads go through an authorized route.
4. Set the issued date, expiry date, and retention date where they apply.

### Versions

Editing a document's content creates a new version. Versions are immutable:

- earlier versions stay available for audit;
- each version records its own checksum, uploader, and timestamp;
- the current version is the one used for downloads and acknowledgments.

Add a version instead of overwriting a file when a policy or employee document changes. This keeps the
acknowledgment history meaningful.

### Classification

| Classification | Who can see it |
| --- | --- |
| **Public** | Anyone with document access |
| **Internal** | Users with document access in the company |
| **Confidential** | Owners, the uploader, and users with document-management permission |
| **Restricted** | Owners, the uploader, and users with document-management permission |

The employee portal uses the same policy. An employee can see documents they own, including their own
confidential documents, but cannot see another employee's documents.

### Acknowledge and download

- A user with access can acknowledge a document. Acknowledgments record the user, employee, timestamp,
  and request IP.
- Employees acknowledge their documents from **Portal → Documents**.
- Use the access log to see who viewed or downloaded sensitive documents.

### Expiry and retention

`hr:reminders` notifies document owners and HR about documents expiring in the next 30 days. Retain
expired documents according to your records-retention policy; deleting a document also removes it from
future acknowledgment lists but leaves audit events.

## Workflow definitions

**Workflow → Workflow Definitions**

1. Create a definition with a code, name, and version.
2. Add the steps. A definition can run steps sequentially or in parallel.
3. Choose the approver scope for each step:
   - a named user,
   - the employee's reporting-line supervisor,
   - a permission or group,
   - an organization-unit role.
4. Set due-day offsets where approvals should have a deadline.
5. Validate and activate the definition. Invalid step graphs are rejected before activation.

Definitions are versioned. Changing an active definition creates a new version so in-flight instances keep
their original steps.

## Approvals inbox

**HR → Approvals**

The inbox lists tasks assigned to you or your delegation. Each task shows:

- the subject record,
- the step and definition,
- the due date,
- the available decision.

Complete a task with **Approve**, **Reject**, or **Return** as configured, and add comments. Decisions are
immutable. If a task is not yours, ask the owner to delegate it rather than editing the record directly.

## Delegations

**Workflow → Delegations**

1. Choose the delegator and delegate.
2. Set the start and end dates.
3. Optionally limit the delegation to a workflow definition or organization unit.

Delegations are checked when a task is created and when the inbox is loaded. Expired delegations stop
routing new tasks automatically.

## Audit trail

**Workflow → Audit Trail**

The audit trail is append-only. Each event records:

- the action,
- the actor,
- the subject record,
- the before/after payload where applicable,
- the timestamp and request metadata.

Use it for investigations and compliance reviews. Do not attempt to edit audit rows; corrections belong in
the source record and produce a new audit event.

## Common problems

| Problem | What to check |
| --- | --- |
| A user cannot see a document | Check classification, ownership, and whether the user has document access. |
| A document download returns 403 | The user is not the owner and does not have document-management permission. |
| An approval task is missing | The definition may not be active, or the approver scope does not match the employee. |
| A workflow cannot be activated | Validate the definition; every step needs a reachable approver and valid order. |
| An approver is on leave | Create a delegation for the approval period. |
| A document version is wrong | Upload a new version; do not edit the old one. |

## Related documentation

- [HR, workforce and talent overview](hr-and-workforce.md)
- [Organization and positions](organization-and-positions.md)
- [Employee self-service](employee-self-service.md)
- [Release notes — November 2026](../../releases/2026-11-hr-platform.md)
