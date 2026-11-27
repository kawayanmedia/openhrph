---
description: "Government separation and retirement in OpenHR PH: resignation, optional and compulsory retirement, GSIS claims, terminal leave, death in service, dropping from rolls, dismissal, clearance and service-record closure."
---

# Government separation and retirement

This page covers government separation and retirement. It complements the shared
[Retirement](../shared/retirement.md) and [Separation](../private/private-separation.md) modules; the government-specific rules
are summarized here.

## Separation types

| Type | Government handling |
| --- | --- |
| **Resignation** | Request, acceptance/action, effectivity, and service-record update |
| **Retirement** | Eligibility, application, service history, GSIS coordination, terminal leave |
| **Death in service** | Beneficiary/claim documents with restricted data |
| **Dropping from rolls** | Grounds, notices, authority, and documentation |
| **Dismissal/separation** | CSC process and final personnel action |
| **End of term/other** | Agency-specific authority and records |

Use a **Separation Case** for clearance and final settlement where your agency requires the same workflow
as private separation. Use **Personnel Actions** for the official CSC-facing action.

## Retirement eligibility

Government retirement is configured through a **Retirement Plan** with plan type **GSIS** or
**statutory**.

1. Create the plan with:
   - the optional and compulsory retirement ages,
   - the minimum service years,
   - effective dates,
   - any agency-specific eligibility conditions.
2. Open a **Retirement Case** for the employee.
3. Assess eligibility. The snapshot records age, service years, required age/service, and the reasons for
   any ineligibility. Confirm the birth date and service history before relying on it.
4. Record the optional or compulsory retirement type and the proposed retirement date.
5. Submit and approve the application.

Government rules for optional and compulsory retirement ages and service are agency- and
issuance-specific. Configure them in the plan and confirm against the current GSIS/CSC rules; do not
hard-code them in the case.

## GSIS coordination

**Retirement → External Claims**

1. Create a claim with agency **GSIS**.
2. Record the reference number, submission date, and status.
3. Update the status as the agency processes it: `submitted`, `in_process`, `approved`, `denied`, or
   `released`.
4. Record the last-checked date and notes.
5. Attach the GSIS application, checklist, and supporting documents to the retirement case.

The system tracks the claim and prepares the documents. It does not assume a public GSIS API exists.
Claims not checked in 30 days are included in `hr:reminders`.

## Terminal leave

Terminal leave is a separate retirement calculation.

1. Review the employee's **Leave Card** and leave ledger.
2. Create a terminal-leave calculation with the reviewed days and daily rate.
3. Review and approve the calculation.
4. The calculation is provisional until approved and cannot be edited once approved.

Do not use an unreviewed estimate as the final terminal-leave entitlement.

## Death in service

For death in service:

- record the separation type and date;
- restrict access to beneficiary and claim documents;
- record the beneficiary/claim workflow where your agency requires it;
- coordinate with GSIS and the agency's benefits office;
- keep the service record and clearance records complete.

## Dropping from rolls and dismissal

Use a personnel action or separation case as required by the CSC process. Record:

- the grounds,
- the notice and authority,
- the effective date,
- the supporting documents,
- the final personnel action.

The employee's service record must reflect the official outcome. Do not remove history; record the action
and its authority.

## Clearance and service-record closure

1. **Separation → Clearance Tasks** — complete office, property, finance, IT, HR, and other agency
   clearances. Attach evidence.
2. **Separation → Final Settlements** — record the reviewed final pay, leave conversion, and other
   components where applicable.
3. **Government → Service Records** — confirm the final service history, leave, and appointment records.
4. Complete the retirement/separation case and close the employee record.

Service-record closure is a record review, not a delete. The employee's official history remains available
under the retention policy.

## Common problems

| Problem | What to check |
| --- | --- |
| Retirement case cannot be completed | An approved benefit calculation is required. |
| Eligibility shows ineligible | Check birth date, service record, plan age/service, and plan effective dates. |
| GSIS claim is missing | Create an External Claim with agency GSIS and the reference number. |
| Terminal leave is zero | Check the leave card, reviewed days, and daily rate. |
| Service record is incomplete | Validate the appointment and complete the personnel action. |
| Clearance is not complete | Close all clearance tasks or mark them waived with authority. |

## Related documentation

- [Government-sector HR](government-hr.md)
- [Government records](government-records.md)
- [Personnel actions](government-personnel-actions.md)
- [Retirement](../shared/retirement.md)
- [Private separation](../private/private-separation.md)
