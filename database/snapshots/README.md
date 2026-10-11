# Full local CRM snapshot — 2026-10-11

The source code snapshot is saved in Git. Its associated `crm-full-private-20261011-complete.zip.enc` is retained locally and **not committed or uploaded**: publication to this public repository requires separate approval. Operational data is encrypted using AES-256-GCM. The key is **not committed**.

## Contents and verification

- Complete `crm_egosolar_test` database: **394 tables, 32,964 rows, 5 triggers**; routines/events/views: zero at capture.
- Database dump restored into a newly-created temporary database. Every table's complete row contents and row counts matched; trigger count matched. The verification database was then removed.
- Local `.env`, root-level files (including local repair tools/documents), private Claude settings, uploads in `storage/app` and `public/uploads`, session files and compiled `public/build` assets.
- ZIP integrity, authenticated decryption and the original SQL SHA-256 were verified.
- Encrypted archive: **2,817,167 bytes**, **214 members** after decryption.
- Ciphertext SHA-256: `b4e0d3396ab186703723add7024077993546b2922214e0f2b9e3dda67dbce7d2`.
- SQL SHA-256: `3f0a0f6587505063a7c72a25f802abb94b8fe0093df0cb40d384e6c51ae37fbc`.

Generated reports, package-manager dependencies, caches and local plaintext database dumps are excluded from public Git. Application source, migrations, tests, frontend assets, project documentation and Claude skills are included. Local-only repair scripts remain available inside the encrypted archive.

## Key and local backup

The original encrypted archive, SQL dump, verification manifest and key are held separately under:

`D:/Source Dev/crm.egosolar.com.vn/backups/git-full-20261011/`

Key file: `snapshot-key.hex`. This is a random 32-byte key encoded as hexadecimal. Preserve a separate secure copy of this file; Git alone cannot decrypt the snapshot. Never add the key, decrypted ZIP, SQL, `.env` or credentials to Git.

## Decrypt

Use Python with `cryptography` installed and the supplied helper. The encrypted input path below currently exists only in the local workspace; obtain the archive privately if restoring another checkout:

```text
python scripts/ops/snapshot_crypto.py decrypt database/snapshots/crm-full-private-20261011-complete.zip.enc D:/private-restore/crm-full.zip --key-file D:/private-key/snapshot-key.hex
```

Replace the destination/key paths with your private locations. The helper refuses to overwrite an existing file and authenticates the encrypted contents before making the output final. Extract only into a private folder.

## Restore

1. Check out this source commit and install dependencies from `composer.lock` and `package-lock.json`.
2. Decrypt and validate the archive. Its `database-manifest.json` records per-table verification.
3. Create a **new empty database** and import `database.sql` there, using a credential prompt or private MySQL options file. Do not import over an operating database without a current backup and an intentional restore decision.
4. Restore the required upload paths. Local `.env` is included for recovery of encryption keys and configuration; review host-specific credentials, URLs and filesystem paths before use elsewhere. Do not replace a production configuration blindly.
5. Configure the restored application to use the restored database, rebuild caches as appropriate, and verify access, payroll history and uploaded documents. No migration, approval or data rewrite was performed on the operating database during this snapshot task.

This is a recovery snapshot of the current implementation, not a claim that every KPI policy has been approved or every existing test passes.
