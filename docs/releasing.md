# Releasing a new version

Releases are built by GitHub Actions. When you push a tag such as `v1.2.0`, the **Release** workflow:

1. checks that the tag matches both version numbers in the plugin;
2. builds `ai-site-assistant-1.2.0.zip`;
3. publishes a GitHub Release with that zip and the matching CHANGELOG section as its notes.

Sites running the plugin then see the update under **Dashboard → Updates** within about 12 hours, or right away after **Check again**.

## Checklist

1. **Bump the version** in all three places:
   - `ai-site-assistant/ai-site-assistant.php`: the `Version:` header and `define( 'AISA_VERSION', … )`.
   - `ai-site-assistant/readme.txt`: `Stable tag:`, plus a new `= x.y.z =` entry under `== Changelog ==`.
   - If the database tables changed, also bump `AISA_DB_VERSION` in `inc/db.php`.
2. **Write the changelog:** add a `## [x.y.z] - YYYY-MM-DD` section at the top of `CHANGELOG.md`. The release notes are taken from it.
3. **Build and test locally:** `& tools\build-zip.ps1`. This checks the versions, lints the PHP and refreshes the `.pot`. Install the zip on a test site.
4. **Commit and tag:**

   ```
   git add -A
   git commit -m "Release 1.2.0"
   git tag v1.2.0
   git push origin main --tags
   ```

5. Watch the **Actions** tab. When the Release workflow is green, the release is live.

### Without git (from the website)

1. Upload the changed files in the repository's **Add file → Upload files** (keep the folder structure) and commit.
2. Open **Releases → Draft a new release**, type a new tag such as `v1.2.0`, set the title, paste the CHANGELOG section as the notes, and click **Publish release**.
3. The Release workflow attaches `ai-site-assistant-1.2.0.zip` within a minute or two. Check that the zip appears under the release's **Assets**. If the workflow failed, upload `dist\ai-site-assistant-1.2.0.zip` there yourself. The file name must be exactly `ai-site-assistant-<version>.zip`.

## If something goes wrong

- **The workflow fails with "Tag v1.2.0 does not match":** the tag and the plugin versions differ. Delete the tag (`git tag -d v1.2.0` and `git push origin :refs/tags/v1.2.0`), fix the version, commit, and tag again.
- **A broken release went out:** publish a fixed version with a higher number. Don't reuse version numbers, because sites that already updated won't see a changed release with the same number.
- **The zip must never be built with Windows' Compress-Archive** (it writes backslashes, and WordPress on Linux then reports "Plugin file does not exist"). `build-zip.ps1` and the workflow both write forward slashes.

## Maintainer setup (project folder outside this repository)

If you keep the working source in a separate project folder and copy it into this repository with an export script, run the export before committing. If you merge pull requests on GitHub, pull them and copy the changes back into the working source first, so the next export doesn't overwrite them.
