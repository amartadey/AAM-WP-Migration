# Aam WP Migration

[![Latest release](https://img.shields.io/github/v/release/amartadey/AAM-WP-Migration?label=version&sort=semver)](https://github.com/amartadey/AAM-WP-Migration/releases/latest)
[![License: GPL v3](https://img.shields.io/badge/license-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

WordPress migration plugin — export or import a whole site (database, media, plugins, themes) as a single `.wpress` file, with no upload size limit. Based on All-in-One WP Migration with the Unlimited Extension bundled and a Remote File Uploader added.

---

## ⬇️ Download

**Always use this link — it never changes and always serves the newest build:**

### https://github.com/amartadey/AAM-WP-Migration/releases/latest/download/aam-wp-migration.zip

`latest` is a GitHub keyword, not a version number. It automatically resolves to the current release, so the same link works forever — through every version bump.

> Do **not** use the version-specific link (`.../releases/download/v7.87/...`) if you want "always latest". That one is pinned to a single version.

---

## Install (first time on a site)

1. Download `aam-wp-migration.zip` from the link above.
2. WordPress admin → **Plugins → Add New → Upload Plugin** → choose the zip → **Install Now** → **Activate**.
3. A new **Aam WP Migration** menu appears in the admin sidebar.

> Deactivate the original All-in-One WP Migration plugin first. Both use the same code base and cannot run at the same time; if both are active this plugin disables itself and shows a notice.
>
> WordPress **Multisite is not supported** — on a network install the plugin only shows a notice.

## Updates

Once installed, each site checks this repository for new releases automatically (every 12 hours).

- When a newer release exists, an **update notice** shows on the **Plugins** page.
- Click **Update now** — WordPress pulls the new zip straight from GitHub and installs it.

The plugin does **not** talk to wordpress.org, so it will never be replaced by the unrelated plugin of a similar name.

---

## Releases & versioning

Every push to `main` runs [`.github/workflows/release.yml`](.github/workflows/release.yml):

| Change pushed | Result |
| --- | --- |
| Version bumped in the plugin header | New release `vX.Y` is created with that version |
| Code change with no version bump | Patch version is bumped automatically (`7.87` → `7.87.1` → `7.87.2` …), committed back to `main` by `github-actions[bot]`, and released |

Every push therefore produces a new version number, so installed sites always see the update. Pull after pushing to pick up the bot's bump commit. (If `main` has branch protection, allow GitHub Actions to push to it.)

The zip is built with `git archive`, so dev-only files (`.github/`, tests, guides) listed in [`.gitattributes`](.gitattributes) are stripped from the distributed plugin.

### Cutting a new version manually

For a minor/major bump, change the number in **both** places — they must match or the build fails:

- `aam-wp-migration.php` → `Version:` header
- `constants.php` → `AI1WM_VERSION`

Commit, push to `main`, and the release is published automatically.

---

## Components

| Path | What it is |
| --- | --- |
| `/` | All-in-One WP Migration core |
| `extension/` | Unlimited features (bundled) — no import size cap, retention, schedules, Reset Hub, settings |
| `remote-uploader/` | Remote File Uploader — pull a `.wpress` backup from a public URL straight into the backups folder (**Aam WP Migration → Remote Upload**) |
| `updater.php` | GitHub-release self-updater |

## License

GPL v3 or later. See [`LICENSE`](LICENSE).
