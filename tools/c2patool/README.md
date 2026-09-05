# c2patool deployment

PAIRfect Paws verifies post-adoption photos with the official `c2patool` CLI. The executable and the C2PA trust list are deployment artifacts, so they are intentionally excluded from Git. This directory documents how to reproduce them.

## Windows development/server installation

From the repository root, run PowerShell 5.1 or newer. The execution-policy override applies only to this child process; inspect the tracked script before running it:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\install_c2patool.ps1
```

The installer:

1. Downloads the official `c2patool` **v0.27.15** Windows x64 archive from the [`contentauth/c2pa-rs` release](https://github.com/contentauth/c2pa-rs/releases/tag/c2patool-v0.27.15).
2. Refuses to extract or run it unless its SHA-256 is exactly `7e931f3d7dce7ffed47e33e46a01b1d7e453059b639f676bf7efb44805610d0a`.
3. Installs the executable at `tools/c2patool/bin/c2patool/c2patool.exe`, which is the Windows default in `config/post_adoption.php`.
4. Downloads and validates the current [official C2PA trust list](https://github.com/c2pa-org/conformance-public/blob/main/trust-list/C2PA-TRUST-LIST.pem), then provisions it at `storage/app/c2pa/c2pa-trust-list.pem`.
5. Prints the trust-list SHA-256 so it can be recorded with the deployment. The trust list changes independently of application releases, so rerun the installer as part of a controlled trust-list refresh.

If another executable already occupies the target, the script stops. Review the existing file before explicitly replacing only that executable:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\install_c2patool.ps1 -Force
```

The bundled sample trust anchors are test credentials and must never be copied into `storage/app/c2pa` for production verification.

## Production configuration

The verifier uses absolute, local paths and reports itself unavailable when any required artifact is absent or unreadable. The browser-camera workflow can still accept a valid single-use server capture challenge, while recording that optional C2PA evidence was unavailable. Windows uses the repository-local executable by default. Other deployments should set:

```dotenv
C2PATOOL_PATH=/opt/pairfect-paws/bin/c2patool
C2PA_SETTINGS_PATH=/var/www/pairfect-paws/config/c2pa-settings.json
C2PA_TRUST_ANCHORS_PATH=/var/www/pairfect-paws/storage/app/c2pa/c2pa-trust-list.pem
```

Install the matching archive for the server platform from the same official release, verify its published GitHub SHA-256 before extracting it, grant execute permission only as needed, and use its absolute path. The official project lists these archive conventions: Windows `x86_64-pc-windows-msvc.zip`, Linux `x86_64-unknown-linux-gnu.tar.gz`, and macOS `universal-apple-darwin.zip`.

The web/PHP service account needs execute access to `c2patool` and read access to the settings, uploaded temporary photo, and trust-list PEM. It does not need write access to the executable or verifier configuration. After changing environment values, rebuild Laravel's configuration cache:

```console
php artisan config:clear
php artisan config:cache
```

Confirm the deployed binary directly:

```console
c2patool -V
```

Version 0.27.15 reports `c2patool 0.27.15`. Current c2patool syntax has no `verify` subcommand: reading an asset produces a JSON report, and trust evaluation is enabled with `c2patool <asset> ... trust --trust_anchors <PEM>`. The application invokes it through Symfony Process with an argument array, a timeout, a local settings file, and a local trust list. On Windows the service passes the PEM filename relative to its working directory because a drive-letter path can be interpreted as a URL scheme by this CLI.

### Timestamp trust limitation in c2patool 0.27.15

Do not treat this deployment as C2PA-conformant timestamp verification yet. C2PA defines two distinct lists and requires a validator's TSA trust anchors to remain separate from its C2PA signer trust anchors. The official C2PA TSA trust list is therefore not interchangeable with `C2PA-TRUST-LIST.pem`.

The pinned c2patool 0.27.15 CLI exposes `--trust_anchors`, `--allowed_list`, and `--trust_config`, but no separate TSA trust-anchor option. Its SDK settings also expose one `trust.trust_anchors` value which the same certificate trust policy uses for both manifest and timestamp validation. Consequently:

- provisioning only `C2PA-TRUST-LIST.pem`, as this installer does, cannot establish conformance-grade trust in certificates that exist only in `C2PA-TSA-TRUST-LIST.pem`;
- concatenating the signer and TSA PEM files is not an acceptable workaround because it would no longer keep the trust stores separate; and
- `verify_timestamp_trust: true` asks the SDK to perform its timestamp trust check, but it does not solve the missing separate TSA trust-store configuration.

The application still fails closed unless c2patool reports both `timeStamp.validated` and `timeStamp.trusted`, but those status names alone are not proof that the deployment follows the C2PA 2.4 trust-list separation requirement. Before production use, move to a verifier release/API that supports independent signer and TSA trust stores (or add a separately reviewed conformant timestamp-validation layer), provision both official lists, and validate the result with C2PA conformance test assets. Track this explicitly during verifier upgrades; do not silently combine the lists.

References:

- [C2PA 2.4 Content Credentials, section 9.4.2](https://spec.c2pa.org/specifications/specifications/2.4/specs/ContentCredentials.html#_time_stamping_authorities)
- [Official C2PA trust-list documentation](https://opensource.contentauthenticity.org/docs/conformance/trust-lists/)
- [c2patool 0.27.15 CLI source and trust options](https://github.com/contentauth/c2pa-rs/blob/c2patool-v0.27.15/cli/src/main.rs)
- [c2pa-rs 0.27.15 trust settings](https://github.com/contentauth/c2pa-rs/blob/c2patool-v0.27.15/sdk/src/settings/mod.rs)

`config/c2pa-settings.json` disables remote-manifest and OCSP fetching and allows no network hosts during request-time verification. Refresh the trust list during deployment instead of allowing uploaded media to cause outbound network access. See the official [c2patool usage and trust documentation](https://opensource.contentauthenticity.org/docs/c2patool/docs/usage/) and [SDK settings documentation](https://opensource.contentauthenticity.org/docs/c2pa-python/docs/context-settings/).

## Acceptance policy and browser-camera limitation

When C2PA credentials are present, PAIRfect Paws accepts them only when the active manifest is recent, its signature and timestamp chain are trusted, its validation report has no failures, and its `c2pa.created` action declares the IPTC `digitalCapture` source type. A present credential that fails those checks is rejected.

This strict optional evidence policy cannot turn a normal browser capture into signed media. `getUserMedia`, drawing a video frame to a canvas, and exporting a Blob typically creates an unsigned image with no C2PA manifest. Those ordinary browser photos instead use a short-lived, authenticated, single-use server challenge and a persisted SHA-256 image hash; missing C2PA credentials do not block them. C2PA's own guidance explains that meaningful hardware-level capture assurance still requires a trusted signing device and, for stronger assurance, secure hardware/attestation. The server challenge establishes a recent authenticated upload session and prevents reuse, but it must not be described as cryptographic proof of the depicted scene.

References:

- [C2PA implementation guidance: trustworthy devices](https://spec.c2pa.org/specifications/specifications/2.4/guidance/Guidance.html#trustworthy-devices)
- [C2PA Content Credentials trust model](https://spec.c2pa.org/specifications/specifications/2.2/specs/ContentCredentials.html#establishing-trust)
- [MDN browser still-photo capture flow](https://developer.mozilla.org/en-US/docs/Web/API/Media_Capture_and_Streams_API/Taking_still_photos)
