# Known Issues and Unknowns

This file separates verified implementation limits from questions. It does not claim that unrequested product features are bugs.

## Verified Limits

- Only starter authentication/home functionality is present. No multi-vendor product feature, domain model, or product API was found.
- Only example-level tests exist; domain workflows have no coverage because no domain workflows were found.
- No deployment target or deployment automation was identified.
- Email verification files and a user timestamp column exist, but no verification routes appeared in the inspected route list.

## Open Questions

- Is a multi-vendor marketplace the intended product? What are the confirmed roles and primary workflows?
- Which database should local, test, and deployed environments use? The Boost-connected environment reports MySQL, while repository defaults select SQLite.
- What is the deployment target and how should production assets, migrations, sessions, queues, cache, and email be managed?
- Should email verification be enabled, or are the present files/column unused starter scaffolding?

Resolve questions with the product owner before creating feature plans. See [requirements](../product/requirements.md) and [backlog](../plans/backlog.md).