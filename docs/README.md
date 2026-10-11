# Project Documentation

This directory is the durable project context for humans and coding agents. It describes the repository as it exists today; it does not treat the workspace name as proof that marketplace features have been implemented.

## Start Here

1. Read [current state](status/current-state.md) and [known issues and unknowns](status/known-issues.md).
2. Find the requested work in the [backlog](plans/backlog.md), then read its plan.
3. Read the relevant architecture, product, development, and decision documents linked from that plan.
4. Use the Laravel Boost guidance, skills, and MCP tools for framework-specific work. This documentation does not replace Boost.

## Sections

- [Product](product/requirements.md): verified scope, implemented capabilities, and user flows.
- [Architecture](architecture/overview.md): current backend, frontend, database, and security.
- [System design](system-design/components.md): components, request/data flow, and integrations.
- [API and routes](api/README.md): current HTTP surface, including the distinction between web routes and APIs.
- [Development](development/setup.md): setup, conventions, and testing. [Vercel deployment preparation](development/deployment-vercel.md) describes the current Aiven/Vercel rollout plan and its limits.
- [Decisions](decisions/README.md): ADR process and template; no historical ADRs are asserted.
- [Plans](plans/README.md): task lifecycle, templates, and backlog.
- [Status](status/current-state.md): verified project snapshot and [changelog](status/changelog.md).

## Evidence Labels

Documents distinguish verified implementation from inference, proposed work, and unanswered questions. Code and configuration are authoritative when they conflict with these notes; update the relevant documentation as part of the task that changes them.

## AI Tooling Boundary

Laravel Boost remains the source for Laravel and installed-package guidance, skills, MCP tools, and Boost-managed rules. Root `AGENTS.md` is the canonical shared task workflow; `CLAUDE.md` points to it. The project currently has no `.ai/rules/` directory or rule index. Do not create duplicate framework rules or edit Boost-generated content inside its guideline markers.
