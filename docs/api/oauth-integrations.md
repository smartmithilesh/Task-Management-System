# OAuth provider integrations

OAuth provider clients are disabled unless the backend has a client ID and secret in its server-side environment. Configure the callback URL in each provider console as:

```text
https://your-host.example/api/v1/integrations/oauth/{provider}/callback
```

The web client requests `POST /api/v1/integrations/oauth/{provider}/authorize`, then navigates to the returned provider URL. The backend stores a random, one-use state value for ten minutes, binds it to the initiating user, organization, and connection, and consumes it on callback. The authorization code is exchanged directly by Laravel. Access and refresh tokens are encrypted in `integration_credentials`, omitted from API resources and logs, and refreshed during connection checks where the provider supports refresh tokens. OAuth providers cannot be configured by posting credentials to the generic integration endpoint.

| Provider key | Environment credentials | Requested default scope | Current operation |
|---|---|---|---|
| `slack` | `SLACK_OAUTH_CLIENT_ID`, `SLACK_OAUTH_CLIENT_SECRET` | `chat:write` | Connection check and queued task activity messages to the selected channel |
| `github` | `GITHUB_OAUTH_CLIENT_ID`, `GITHUB_OAUTH_CLIENT_SECRET` | `read:user` | Authorization and connection check |
| `google_calendar` | `GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET` | Calendar read-only | Authorization, calendar API connection check, token refresh |
| `microsoft_calendar` | `MICROSOFT_OAUTH_CLIENT_ID`, `MICROSOFT_OAUTH_CLIENT_SECRET` | `User.Read`, `Calendars.Read`, `offline_access` | Authorization, Graph connection check, token refresh |
| `dropbox` | `DROPBOX_OAUTH_CLIENT_ID`, `DROPBOX_OAUTH_CLIENT_SECRET` | Account and file metadata read | Authorization, storage API connection check, token refresh |

For Slack, enter the destination channel ID before connecting. The bot must be invited to that channel. Event messages contain the task event name and task number only. Webhooks remain available for the full signed activity payload. GitHub, calendar, and Dropbox synchronization actions are not implemented yet; their current adapter support establishes authorization, encrypted credentials, connection checks, and token refresh where available.

On disconnect, local credentials are always deleted. Slack, GitHub, Google, and Dropbox revocation is requested through provider APIs; Microsoft does not provide a general app token revocation endpoint, so remove the app grant from the Microsoft account or tenant as well. A `remote_token_revoked: false` response means local access was removed but remote revocation could not be confirmed.

Set exact HTTPS callback URLs for production. Keep all client secrets out of the web/mobile clients and repository. The default scope values can be changed through the corresponding `*_SCOPES` variables; request only provider permissions needed by enabled features.
