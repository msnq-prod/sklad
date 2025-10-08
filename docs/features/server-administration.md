# Server Administration: Users Overview

## Purpose and Entry Point
The server administration *Users* dashboard (`src/server/users.php`) provides a consolidated view of all non-deleted user accounts that a server administrator can inspect for compliance, support, and audit activity. The page is rendered through the secure bootstrap (`headSecure.php`), ensuring only authenticated sessions with appropriate permissions may access it.【F:src/server/users.php†L1-L41】

## Data Displayed on the Users Dashboard
The controller queries several datasets for each user and injects them into the Twig view. Understanding these data slices helps administrators interpret the interface correctly.【F:src/server/users.php†L9-L41】

### Core User Profile
* **Identity fields:** Primary and secondary names, username, email address, email verification status, and thumbnail are fetched to give quick context when reviewing accounts.【F:src/server/users.php†L9-L21】
* **Account state:** Suspension and terms-of-service acceptance flags highlight compliance issues that may require follow-up.【F:src/server/users.php†L9-L21】
* **Creation date sorting:** Results are ordered by name and creation timestamp so that records appear in a predictable sequence for review and export purposes.【F:src/server/users.php†L9-L12】

### Email Activity
* **Email log:** The controller gathers the message IDs and subjects of emails sent to each user from the `emailSent` table. This enables administrators to review the communication history before contacting the user again or during incident response.【F:src/server/users.php†L15-L21】
* **Mailing permissions:** Access to these logs presumes the `USERS:VIEW:MAILINGS` permission, which depends on `USERS:VIEW`. Administrators should verify that anyone tasked with reviewing email history has the correct entitlements.【F:src/common/libs/Auth/serverActions.php†L64-L76】

### Instance Membership
* **Instance enrollment:** Joined with `instances` and `instancePositions`, the dashboard lists every active instance membership for the user, including plan names and any custom labels added on the `userInstances` record. This assists with capacity management and cross-instance coordination.【F:src/server/users.php†L23-L33】
* **Position metadata:** Display names from `instancePositions` clarify the user’s functional role inside each instance, aiding access reviews and role validation.【F:src/server/users.php†L23-L33】

### Active Positions Across the Platform
* **Current assignments:** A dedicated query collects positions whose validity window spans the current time (`userPositions_start`/`userPositions_end`). Ordering by rank and display name surfaces high-privilege positions first, helping administrators spot elevated access quickly.【F:src/server/users.php†L35-L39】

### Authentication and Analytics Signals
* **Last successful login:** The latest `authTokens` record (excluding admin impersonation tokens) reveals when the user last authenticated, supporting dormant-account cleanups and intrusion detection.【F:src/server/users.php†L41-L43】
* **Latest analytics event:** The most recent `analyticsEvents` timestamp (ignoring admin-generated activity) provides an additional activity signal to corroborate user engagement or inactivity periods.【F:src/server/users.php†L45-L47】

## Permission Model and Dependencies
The Users dashboard enforces the `USERS:VIEW` server permission. Administrators should understand related entitlements to safely delegate responsibilities.【F:src/server/users.php†L7-L8】【F:src/common/libs/Auth/serverActions.php†L16-L118】

| Permission | Category | Purpose | Notable Dependencies |
| --- | --- | --- | --- |
| `USERS:VIEW` | User Management | Grants access to the Users dashboard and general user listings. | None |
| `USERS:VIEW:MAILINGS` | User Management | Allows review of a user’s email history (subjects and identifiers). | `USERS:VIEW` |
| `USERS:VIEW:OWN_POSITIONS` | Permissions Management | Enables viewing of assigned positions when troubleshooting membership escalations. | None |
| `USERS:EDIT:SUSPEND` | User Management | Required for suspending accounts; combined with the dashboard to investigate users before actioning. | `USERS:VIEW` |
| `USERS:EDIT` / `USERS:DELETE` | User Management | Allow updates or deletions following investigations surfaced on this page. | `USERS:VIEW` |
| `VIEW-ANALYTICS` | General sys admin | Unlocks detailed analytics dashboards that complement the last-activity data gathered here. | `INSTANCES:VIEW` |
| `VIEW-AUDIT-LOG` | General sys admin | Provides access to the audit log interface for deeper traceability. | None |
| `PERMISSIONS:VIEW` / `PERMISSIONS:EDIT` | Permissions Management | Coordinate with the Users dashboard when adjusting entitlements uncovered during reviews. | `USERS:VIEW` (indirect, via dependencies) |

When provisioning roles, pair `USERS:VIEW` with analytics or audit permissions only when recipients must cross-reference logs. This minimizes exposure to sensitive metadata.

## Relationship with Analytics and Audit Tooling
The controller’s activity signals (`authTokens` and `analyticsEvents`) are designed to align with dedicated analytics pages (`src/server/analytics/*.php`). Administrators with `VIEW-ANALYTICS` can drill into aggregated trends (page views, table queries) to corroborate findings from the Users dashboard. For forensic investigations, the audit log (`VIEW-AUDIT-LOG`) surfaces immutable event trails that extend beyond what the Users page displays by default.【F:src/server/users.php†L35-L47】【F:src/server/analytics/index.php†L1-L6】【F:src/server/analytics/pageViews.php†L1-L6】【F:src/server/analytics/tables.php†L1-L6】【F:src/common/libs/Auth/serverActions.php†L88-L118】

## Audit and Security Procedures
1. **Routine Access Review:** Use the dashboard’s instance and position listings to verify that users maintain only necessary memberships. Cross-reference positions with the Permissions admin page before revoking or downgrading access.【F:src/server/users.php†L23-L39】【F:src/common/libs/Auth/serverActions.php†L69-L112】
2. **Login Monitoring:** Track the `authTokens_created` timestamp to flag stale accounts. If an account shows no recent logins, consider suspending it (`USERS:EDIT:SUSPEND`) after confirming with business owners.【F:src/server/users.php†L41-L43】【F:src/common/libs/Auth/serverActions.php†L49-L72】
3. **Analytics Corroboration:** Compare the last analytics timestamp with platform analytics dashboards. Sudden gaps or spikes may indicate unauthorized use or application errors. Ensure investigators hold `VIEW-ANALYTICS` before sharing detailed analytics views.【F:src/server/users.php†L45-L47】【F:src/server/analytics/index.php†L1-L6】【F:src/common/libs/Auth/serverActions.php†L104-L118】
4. **Email Communication Audit:** Review the email subjects logged for each user to validate notifications sent during incidents. Restrict this to staff with `USERS:VIEW:MAILINGS` to protect communication content.【F:src/server/users.php†L15-L21】【F:src/common/libs/Auth/serverActions.php†L64-L76】
5. **Revoking Access:** After confirming elevated roles or stale access, use the Permissions administration tools to adjust role assignments, or suspend the user where necessary. Document each change in the audit log for traceability.【F:src/common/libs/Auth/serverActions.php†L69-L118】
6. **Security Recommendations:**
   * Limit `USERS:VIEW` to trusted operators and pair with `VIEW-AUDIT-LOG` only when audit responsibilities exist.
   * Regularly export user data for offline review through secure channels.
   * Ensure suspension actions are accompanied by a check of login tokens and analytics events to confirm the account is dormant.

## Interactions with Other Administrative Pages
* **Permissions Management (`src/server/permissions.php`):** Adjust user or position permissions uncovered during reviews. Pair findings from the Users dashboard with targeted permission updates to enforce least privilege.【F:src/common/libs/Auth/serverActions.php†L69-L112】
* **Import Utilities (`src/server/import/*.php`):** When onboarding or bulk-updating users, use import tools to reconcile instance memberships displayed on the Users page. Validate imports by refreshing the Users dashboard afterward to confirm assignments.【F:src/server/users.php†L23-L39】
* **Analytics Suite (`src/server/analytics/*.php`):** Dive deeper into usage metrics when the Users dashboard signals unusual activity. The analytics pages require `VIEW-ANALYTICS` and supplement the per-user activity timestamps shown here.【F:src/server/users.php†L35-L47】【F:src/server/analytics/index.php†L1-L6】
* **Audit Log (`src/server/auditLog.php`, where available):** Use alongside the Users page to review change histories, suspension events, and permission updates made during an investigation. This reinforces a comprehensive compliance trail.【F:src/common/libs/Auth/serverActions.php†L88-L118】

Maintaining disciplined use of these interconnected tools ensures robust oversight of user accounts, aligns with audit obligations, and reduces the risk of unauthorized access.

## Typical Administrative Scenario
The following scenario illustrates how a support engineer can combine the Users dashboard with adjacent tooling to resolve an access issue from start to finish:

1. **Triage the request:** After receiving a ticket about a user who cannot access a project, the engineer opens the Users dashboard (requires `USERS:VIEW`) and locates the account using the alphabetical listing assembled by the controller.【F:src/server/users.php†L9-L21】
2. **Validate account status:** They verify that the user is not suspended and confirm the last login timestamp to ensure authentication is functioning as expected.【F:src/server/users.php†L9-L21】【F:src/server/users.php†L41-L43】
3. **Inspect instance memberships:** The engineer reviews the user’s instance memberships and current positions to confirm whether the relevant instance is present and whether the assigned role grants the needed capabilities.【F:src/server/users.php†L23-L39】 If a missing or incorrect role is identified, the engineer navigates to the Permissions administration page to adjust entitlements, provided they hold `PERMISSIONS:EDIT`.【F:src/common/libs/Auth/serverActions.php†L69-L112】
4. **Review recent communications:** If the issue might stem from onboarding emails or policy updates, the engineer checks the email log (requires `USERS:VIEW:MAILINGS`) to confirm the user received pertinent instructions.【F:src/server/users.php†L15-L21】【F:src/common/libs/Auth/serverActions.php†L64-L76】
5. **Corroborate activity:** To rule out broader platform problems, they open the analytics dashboards (requires `VIEW-ANALYTICS`) and compare instance usage trends with the user’s latest analytics event to ensure no widespread outage exists.【F:src/server/users.php†L35-L47】【F:src/server/analytics/index.php†L1-L6】
6. **Document the resolution:** Once access is restored, the engineer records the intervention in the audit log interface (requires `VIEW-AUDIT-LOG`) to maintain a compliance trail, noting any permission changes or follow-up actions.【F:src/common/libs/Auth/serverActions.php†L88-L118】

This end-to-end workflow demonstrates how the datasets and permissions surfaced on the Users dashboard support practical support and security operations while reinforcing least-privilege and auditability principles.

## Plain-Language Guide: How the Users Page Works

1. **Open the page with the right badge.** You must have the `USERS:VIEW` permission, so only trusted admins can get in.【F:src/server/users.php†L7-L8】
2. **Find the person.** Search the alphabetical list or scroll until you see their name, photo, and email. This tells you who you are checking.【F:src/server/users.php†L9-L21】
3. **Check their status.** Look for suspension flags, terms-of-service status, and the “last login” time to make sure their account is active.【F:src/server/users.php†L9-L21】【F:src/server/users.php†L41-L43】
4. **See where they belong.** The page shows every instance and position the user currently holds. This reveals what they should be able to do today.【F:src/server/users.php†L23-L39】
5. **Review their emails (optional).** If you have `USERS:VIEW:MAILINGS`, you can see the subjects of emails sent to them to confirm important messages went out.【F:src/server/users.php†L15-L21】
6. **Confirm their activity.** Check the last analytics event to see if they recently used the system. This helps spot dormant or suspicious accounts.【F:src/server/users.php†L45-L47】
7. **Fix access if needed.** Jump to the Permissions page to change roles, or suspend the user if you have the edit permissions. Always record what you changed in the audit log.【F:src/common/libs/Auth/serverActions.php†L69-L118】
8. **Log the outcome.** Use the audit tools (`VIEW-AUDIT-LOG`) to note what happened so the next admin sees a clear history.【F:src/common/libs/Auth/serverActions.php†L88-L118】

In short: open the Users page, find the person, read their status and memberships, double-check recent emails and activity, make the fix, and write down what you did. Repeat this flow for each account review to keep the system clean and safe.
