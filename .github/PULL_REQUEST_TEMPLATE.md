## What this changes

<!-- One change per pull request. Link the issue it fixes, e.g. "Fixes #12". -->

## How I tested it

<!-- WordPress and PHP versions, AI provider (or Mock), what you checked. -->

## Checklist

- [ ] Works on PHP 7.4 (no newer syntax) and passes `php -l`
- [ ] No code tied to a particular theme, plugin or company
- [ ] New user-visible strings are translatable (`ai-site-assistant` text domain), and I ran `php tools/make-pot.php`
- [ ] New options, transients or tables are also removed in `uninstall.php`
- [ ] Docs and `CHANGELOG.md` (under "Unreleased") updated if behaviour changed
- [ ] Admin screens render without PHP notices, and the widget works at phone and desktop widths
