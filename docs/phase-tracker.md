# Project phase tracker

Use this file as the durable work checklist for this repository. It records project progress independently of any chat or coding system. Work one phase at a time; mark a task complete only after its implementation or verification is present in the repository. Keep credentials and customer data out of this file.

`[x]` means complete, `[ ]` means pending, and a phase marked **Partial** has both completed and pending tasks. Update the matching status in [Architecture and implementation status](../ARCHITECTURE.md#13-development-roadmap-and-status) when a phase changes.

## Phase checklist

### 1. Architecture and project setup — Complete

- [x] Define Laravel as the source of truth and document browser/mobile client boundaries.
- [x] Establish the monorepo structure and deployment assumptions.
- [x] Document the initial single-organization scope.

### 2. Standalone installation wizard — Complete

- [x] Add pre-Laravel requirements, database, application, and administrator steps.
- [x] Validate inputs, protect steps with sessions and CSRF, and write configuration and install lock safely.
- [x] Add installer feature coverage and document recovery limits.
- [ ] Verify a fresh install on each target hosting profile during release preparation.

### 3. Database architecture and baseline seed data — Complete

- [x] Add migrations for identity, organization, project, task, collaboration, notification, and integration data.
- [x] Add baseline roles, permissions, statuses, priorities, notifications, and settings.
- [x] Use the configured MySQL database for the working local application.
- [ ] Review forward migration behavior against a production-like database before release.

### 4. Authentication — Complete

- [x] Implement browser session login, logout, current-user, and profile flows.
- [x] Implement password reset and email verification endpoints with throttling.
- [x] Implement revocable bearer tokens for API/mobile clients.
- [ ] Configure and verify production mail delivery under Phase 14.

### 5. User management — Complete

- [x] Implement organization-scoped user listing, creation, update, roles, and profile endpoints.
- [x] Add user resources and browser screens.
- [x] Cover authorization and validation in feature tests.

### 6. Roles and permissions — Complete

- [x] Seed the supported roles and granular permissions.
- [x] Enforce permission and organization checks in API operations.
- [x] Add role and permission management endpoints and browser screens.

### 7. Organizations, departments, and teams — Complete

- [x] Implement organization setup and organization-scoped settings.
- [x] Implement department and team management and membership.
- [x] Add corresponding browser screens.
- [x] Keep the initial scope to one organization per installation.

### 8. Projects — Complete

- [x] Implement project CRUD, membership, and organization scoping.
- [x] Add project browser views and tests.

### 9. Tasks — Complete

- [x] Implement task CRUD, assignment, status, priority, tags, watchers, and reviewers.
- [x] Add task list, board, and detail browser flows.
- [x] Cover authorization and organization scoping.

### 10. Subtasks and checklists — Complete

- [x] Support parent tasks and task checklist/item operations.
- [x] Add checklist interactions to task details.
- [x] Cover checklist authorization and validation.

### 11. Comments and attachments — Complete

- [x] Implement comments, mentions, attachment upload, authorized download, and deletion.
- [x] Keep attachments on private storage and scope access to the organization.
- [x] Cover attachment authorization in feature tests.
- [ ] Confirm private storage permissions and retention on the production host under Phase 26.

### 12. Activity logs — Complete

- [x] Record activity for supported task and collaboration changes.
- [x] Expose organization-scoped activity through the API.

### 13. Time tracking — Complete

- [x] Implement time entries and timer start/stop flows.
- [x] Enforce user, task, and organization authorization.
- [x] Include time tracking in reports and activity events.

### 14. Notifications — Partial

- [x] Implement in-app notifications, read state, and user preferences.
- [x] Add email notification delivery paths.
- [x] Implement optional FCM server delivery and device-token registration.
- [ ] Configure the production mail transport and verify reset, verification, and notification emails.
- [ ] Add Firebase service-account credentials on the server and platform Firebase files for each mobile app.
- [ ] Verify push delivery and task navigation on physical Android and iOS devices.

### 15. Dashboard — Complete

- [x] Implement dashboard API data and browser overview/profile/workspace setup.

### 16. Kanban — Complete

- [x] Implement the task board and status-change flow.

### 17. Calendar — Complete

- [x] Implement the browser calendar view using task due dates.

### 18. Reports and exports — Complete

- [x] Implement organization-scoped task reports, date/project filters, pagination, and CSV export.
- [x] Escape spreadsheet formula-leading cells in CSV output.

### 19. Integrations framework and providers — Partial

- [x] Implement provider adapters, OAuth state validation, encrypted credentials, connection checks, and disconnect flows.
- [x] Implement signed outgoing webhooks and Slack task-activity messages.
- [ ] Add GitHub issue/record synchronization.
- [ ] Add Google and Microsoft Calendar synchronization.
- [ ] Add Dropbox file/storage synchronization.
- [ ] Configure and verify customer OAuth credentials and callback URLs for enabled providers.

### 20. REST API and OpenAPI — Partial

- [x] Implement versioned API routes, validation, resources, authentication, permissions, and throttling for current features.
- [x] Publish an OpenAPI document.
- [ ] Reconcile every shipped route, request, response, and error case against the OpenAPI document.

### 21. Next.js browser client — Complete

- [x] Implement sign-in, dashboard, people, organization settings, projects, tasks, board, calendar, reports, notifications, and integrations screens.
- [x] Build the static client and publish it into Laravel's public directory.
- [x] Serve the browser client and API same-origin with the MySQL-backed Laravel application.

### 22. Flutter mobile client and push — Partial; Android release deferred

- [x] Implement secure sign-in/token handling, task list/detail, comments, checklists, timer, and push-registration code.
- [x] Generate Android and iOS runner projects and build an Android debug APK.
- [ ] Replace placeholder Android and iOS application identifiers with organization-owned IDs.
- [ ] Configure Android release signing and build the release artifact when Android work resumes.
- [ ] Add Firebase Android and iOS app configuration and verify push delivery under Phase 14.
- [ ] Build and validate the iOS app on macOS with Xcode.

### 23. Automated tests and installation scenarios — Partial

- [x] Add backend feature tests for authentication, authorization, data foundation, installation, integrations, notifications, and operations.
- [x] Verify the current backend test suite, web typecheck/build, and Flutter analysis/tests during implementation.
- [ ] Exercise a fresh installation against a disposable MySQL database.
- [ ] Complete production-like browser/API end-to-end verification with an installed administrator account.

### 24. Security review — Pending

- [ ] Review tenant isolation and permissions across every API resource and relationship.
- [ ] Review session/CSRF, bearer-token lifecycle, installer lock, uploads, and private downloads.
- [ ] Review OAuth state/scopes, webhook SSRF/signatures, and secret handling.
- [ ] Review dependency advisories, production headers, TLS, and error/log redaction.
- [ ] Record findings, fixes, and release sign-off.

### 25. Performance optimization — Pending

- [ ] Profile expected dashboard, task-list, report, and attachment workloads.
- [ ] Check query counts, eager loading, indexes, pagination, and large CSV behavior.
- [ ] Measure queue and integration delivery under expected load.
- [ ] Record measured bottlenecks and verify improvements.

### 26. Production deployment and operations — Partial

- [x] Document Apache/Nginx deployment, runtime requirements, configuration, queues, scheduler, and release steps.
- [x] Add Apache/Nginx security configuration examples.
- [ ] Validate the deployment guide against the actual target host and its PHP/MySQL versions.
- [ ] Configure HTTPS, mail, queue workers, scheduler, private storage, monitoring, and log rotation on the target host.
- [ ] Run production smoke checks and record the release procedure.

### 27. Upgrade and backup mechanisms — Partial

- [x] Implement database backup, restore, and application-upgrade Artisan commands.
- [x] Document backup scope, restore requirements, and upgrade procedure.
- [ ] Perform a MySQL backup and restore drill in an isolated environment.
- [ ] Verify restored private attachments and application sign-in.
- [ ] Verify an upgrade from the previous release with customer data preserved.

## Working order

Resume at the earliest partial phase that does not require unavailable customer credentials or hardware. Android release work remains deferred by request. Record any blocker under the affected task without marking the whole phase complete.
