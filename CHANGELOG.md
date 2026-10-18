# Changelog

## 0.2.0

- **Breaking:** Google sign-in fails closed — an empty Allowed Workspace Domain refuses every Google login
  instead of allowing any Google account. Set a domain before upgrading.
- **Breaking:** Allowed Workspace Domain is required and validated (bare hostname, normalized to lowercase)
  on save while Google is the active provider.
- **Breaking:** Google logins require `email_verified=true`.
- The authorization request carries the `hd` param, pre-filtering Google's account chooser.
- **Breaking:** requires `dmlab/module-admin-sso` ^0.2.0 (passes the `hd` param).
- `dmlab/module-sso-core` and `magento/module-config` declared as direct dependencies.
