# Signed outbound webhooks

An organization can configure the `webhook` adapter from **Integrations**. Save a public HTTPS endpoint and a random signing secret of at least 24 characters. Secret fields are Laravel-encrypted at rest and never returned by the API. The adapter rejects local hostnames and private/reserved DNS answers, pins the validated address for the HTTPS connection, and does not follow redirects.

Events run on Laravel's configured queue after the database transaction commits. Delivery retries up to four attempts with increasing delays. The receiver gets a JSON document with `id`, `event`, `created_at`, and `data`, plus `X-Taskflow-Event`, `X-Taskflow-Event-ID`, `X-Taskflow-Timestamp`, and `X-Taskflow-Signature` headers. The event ID remains unchanged across retries; consumers should record it for idempotency.

The signature is `sha256=` followed by the lowercase hex HMAC-SHA256 of `<timestamp>.<raw-request-body>` using the shared signing secret. Reject timestamps outside your replay window, compare signatures in constant time, and return a 2xx response after durable acceptance. `POST /api/v1/integrations/{id}/test` sends an `integration.test` event.

The webhook adapter sends task, comment, attachment, and time tracking activity events. Optional OAuth adapters are available for Slack, GitHub, Google Calendar, Microsoft Calendar, and Dropbox when server-side client credentials are configured. Their current scopes, callback setup, and operating limits are documented in [OAuth provider integrations](oauth-integrations.md). Slack can post minimal task activity messages to one configured channel. GitHub, calendar, and Dropbox connectors currently authorize and test connections; they do not synchronize records yet.
