# Authentication

The extension supports historical API-key authentication and the optional
HelloAsso partner authorization screen.

## API-key connection

API-key mode is the conservative option for an existing production processor.
Configure the following fields:

- `Client Id`
- `Client Secret`
- `Organization Name`, containing the HelloAsso organization slug

Default API URLs are:

- Production: `https://api.helloasso.com`
- Sandbox: `https://api.helloasso-sandbox.com`

Do not append `/v5` to the processor URL. The extension adds the required API
routes itself.

## Partner authorization screen

Partner authorization is enabled by default. It lets an organization connect
from CiviCRM and enables automatic partner webhook registration and signature
key management.

1. Open **Administer > System Settings > HelloAsso settings**.
2. Enable the shared HelloAsso connection.
3. Open the sandbox or production authorization rail.
4. Enter the partner `client_id` and `client_secret` for that environment.
5. Register the callback URL displayed by CiviCRM in the HelloAsso partner
   configuration, then start the connection.
6. Verify the linked organization, registered webhook URL and webhook signing
   key.

After a successful connection, the processor uses partner mode for the selected
environment. Historical credentials stored on that processor are cleared so
that the connected partner rail remains authoritative.

Sandbox and production partner credentials are distinct. Never exchange them
or commit them to a public repository.

## Connection ownership

One sandbox organization may send webhooks to several CiviCRM instances, for
example through a test relay. Partner OAuth ownership is different: reconnecting
another instance with the same client, organization and environment can
invalidate refresh tokens stored by the previous instance.

Only one instance should own that partner connection. Other instances may
receive relayed webhooks but should not maintain a concurrent OAuth connection
with the same credentials.

## Restoring a backup

Restored tokens and webhook metadata may no longer match the current HelloAsso
state.

- A recent refresh token should obtain a new access token if no other instance
  has reconnected the same partner account.
- An expired or invalidated refresh token changes the link state to
  `reconnect_required` or `refresh_failed`; an administrator must reconnect it.
- A restore on another domain triggers warnings when the current domain differs
  from the registered OAuth callback or webhook URL.
- Signed webhooks are accepted only when the local signing key matches the key
  registered by HelloAsso.
- An unsigned or unverifiable webhook can trigger API confirmation only for a
  HelloAsso object already known locally.

After a restore, review CiviCRM HelloAsso alerts, `refresh_status`, the webhook
URL and callback domain. Reconnect only the instance that should own the
organization and environment.
