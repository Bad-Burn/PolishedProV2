# Present the full PolishedPro app from GitHub

1. [Open PolishedPro in GitHub Codespaces](https://codespaces.new/Bad-Burn/PolishedProV2/tree/presentation-full) and select **Create codespace**. Choose the 2-core machine for this demo.
2. Wait for the terminal to show **PolishedPro is ready**. In **Ports**, open the **PolishedPro** forward on port **8765**.
3. Open **Sign in** and use the accounts below. Sign out between roles or use separate browser profiles for simultaneous sessions.

The Codespace runs the same PHP pages as the project, with a dedicated MariaDB database, actual application sessions, and seeded demo data. Keep the Codespace running while presenting. The forwarded port is private by default, so you can present by screen sharing.

| Role | Email | Password |
| --- | --- | --- |
| Client | `bea@example.test` | `PolishedDemo!2026` |
| Artist, approval workflow | `paolo@polishedpro.test` | `PolishedDemo!2026` |
| Artist, existing subscription | `mariel@polishedpro.test` | `PolishedDemo!2026` |
| Administrator | `demo-admin@polishedpro.test` | Run `cat storage/codespaces/admin-password` in the Codespace terminal |

To walk through a manual booking, sign in as the client and choose a service from **Bautista Barbers**. The artist account can confirm it in **Bookings**. The administrator can find it by reference in **Bookings**.

Premium pricing and billing screens are available. Online checkout requires separate PayPal Sandbox credentials and a reachable Sandbox webhook. The demo can show an administrator granting a plan to an artist from **Subscriptions**. Outgoing emails are recorded in `storage/logs/mail.log`.

The app starts automatically on creation and restart. If you need to start it again in the Codespace, run `bash .devcontainer/start-presentation.sh`. Demo records persist across stops and starts in the Codespace’s database volume.
