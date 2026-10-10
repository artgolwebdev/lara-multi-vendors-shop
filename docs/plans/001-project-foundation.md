# 001: Project Documentation and AI Task Workflow

- ID: `001-project-foundation`
- Status: completed
- Created: 2026-10-10
- Updated: 2026-10-10
- Completed: 2026-10-10

## Problem and Intended Outcome

The repository had no project architecture/product documentation or persistent task-planning index. Establish linked Markdown context grounded in the current application and a canonical agent workflow that preserves Laravel Boost as the Laravel-specific source.

## Scope

### In Scope

- Document verified application architecture, routes, schema, security, setup, tests, integrations, and known unknowns.
- Add a backlog, reusable plan template, and this historical implementation plan.
- Integrate the workflow with the existing `AGENTS.md` entrypoint without replacing Boost-managed guidance; let `CLAUDE.md` point to that shared workflow.

### Non-Goals

- Implement product functionality or modify application behavior/dependencies.
- Invent marketplace requirements, product architecture, historical decisions, or future feature plans.
- Change Boost MCP configuration, skills, or generated guideline content.

## References

- Requirements: [product requirements](../product/requirements.md), [feature inventory](../product/features.md)
- Architecture: [overview](../architecture/overview.md), [backend](../architecture/backend.md), [frontend](../architecture/frontend.md), [database](../architecture/database.md), [security](../architecture/security.md)
- Decisions: no ADR created; no historical decision was evidenced.

## Existing Implementation Findings

- Verified: PHP 8.4.23, Laravel 13.35.0, Laravel Boost 2.10.3; MySQL is the active Boost MCP database, while repository configuration defaults to SQLite.
- Verified: Blade web pages, Laravel UI session auth, one authenticated home controller, one `User` model, and framework cache/session/queue migrations. No product domain/API layer was found.
- Verified: Vite, Bootstrap/Sass, Tailwind CSS, and a React example component; no Inertia or Livewire integration was found.
- Verified: project Boost setup lists Claude Code and Cursor; MCP config points to `php artisan boost:mcp`; no project `.ai/rules/` index exists.
- Verified: root files were already staged as additions on an unborn `master` branch before this task. Preserve their staged state and do not stage this task's edits.
- Unanswered: product requirements, desired marketplace behavior, deployment target, and active environment's intended database choice.

## Proposed Design

### Affected Components

New `docs/` Markdown files and a project-owned workflow appended outside Boost's `<laravel-boost-guidelines>` block in `AGENTS.md`; `CLAUDE.md` points to the canonical workflow. No runtime components are changed.

### Database

Not applicable; document the current migrated tables only.

### API / Routes

Document the existing web route list; no API endpoints are introduced.

### Frontend

Document the current Blade/Vite setup; no frontend implementation changes.

### Security

Do not read or reproduce `.env` values. Record only environment variable names and verified security behavior.

## Implementation Tasks

- [x] Inspect Boost configuration, guidance ownership, existing agent files, and skills.
- [x] Inspect actual routes, controllers, models, migrations, frontend, tests, and runtime schema.
- [x] Create populated architecture/product/development/API/status docs, templates, and backlog.
- [x] Add the shared agent workflow outside Boost-managed markers and reference it from Claude instructions.
- [x] Validate Markdown links, plan references, Boost configuration preservation, and test results.

## Acceptance Criteria

- [x] Docs describe actual code and distinguish verified facts from inference and open questions.
- [x] All internal Markdown file links resolve and backlog plan links exist.
- [x] Instructions preserve Boost's framework guidance and MCP configuration.
- [x] Tests and documentation consistency checks have actual results recorded.
- [x] Existing staged starter files were preserved; the workspace now has an initial commit.

## Testing Strategy and Results

| Check | Expected result | Actual result |
| --- | --- | --- |
| `php artisan route:list --except-vendor` | Route inventory matches docs | Passed; 13 web routes shown |
| `php artisan test --compact` | Existing suite passes | Passed after doc edits; 2 tests, 2 assertions |
| Markdown link/plan-reference check | No broken local file links or plan references | Passed; 30 Markdown files checked |
| Boost/configuration and secret scan | Generated guidance and MCP config preserved; no secret assignments in docs | Passed; guideline blocks match, both MCP configs match, manifests unchanged |

## Risks, Dependencies, and Open Questions

- Product direction is not specified in repository files. Keep feature requirements and plans open until confirmed.
- MySQL was observed through Boost, while repository defaults to SQLite. Confirm environment-specific intended database before relying on the live DB snapshot.
- This repository starts from an unborn Git branch with files staged as additions; do not stage or reset anything during this task.

## Implementation Notes and Discoveries

- Boost 2.10.3's `GuidelineWriter` replaces only the content inside its guideline tags, preserving surrounding project content. No `boost:install` or dependency changes are needed.
- No root `.ai/rules/index.md` exists. No Boost project rule was recorded because the new workflow is agent task procedure, not a codebase convention or Laravel-specific rule.
- No `.env` file was read or secret value copied.

## Final Summary and Remaining Work

Persistent project documentation, task templates/backlog, and the canonical agent workflow are in place without application behavior or dependency changes. Remaining product questions are listed in [known issues and unknowns](../status/known-issues.md); resolve them before planning marketplace features.