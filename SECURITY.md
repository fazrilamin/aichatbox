# Security policy

## Supported versions

Only the latest release receives security fixes. Updates arrive through **Dashboard → Updates**.

## Reporting a vulnerability

Please **don't** report security problems in public issues.

Use GitHub's private reporting instead: open the repository's **Security** tab and click **Report a vulnerability** ([direct link](https://github.com/fazrilamin/aichatbox/security/advisories/new)). Include:

- the affected version;
- what an attacker can do, and what access they need (visitor, logged-in user, administrator);
- steps or a minimal proof of concept to reproduce it.

You should get a reply within a few days. Once a fix is released, the advisory will be published with credit to you, unless you prefer to stay anonymous.

## Scope

In scope: the plugin code in this repository, for example:

- the public chat and feedback endpoints;
- the admin screens and admin REST routes;
- output escaping in the widget;
- the update mechanism.

Out of scope:

- vulnerabilities in WordPress core, your theme or other plugins;
- your AI provider's service;
- prompt injection that only changes the wording of an answer without exposing data or bypassing a control. Reports showing that the AI can be made to reveal non-public content, or to skip the site's restrictions, are in scope.
