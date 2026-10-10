# Deployment

No deployment workflow, container definition, CI pipeline, hosting manifest, or environment-specific deployment configuration was found in the inspected project root. Do not assume a platform or deployment target.

The checked-in `.env.example` lists configurable environment names; deployers must supply appropriate values through the target platform's secret/configuration mechanism. Never place secret values in docs or plans. Before deployment guidance becomes actionable, confirm the target platform, database, asset-build process, queue/session/cache drivers, mail transport, and migration/release procedure.

Laravel Cloud is available as a Boost skill, but the presence of that skill does not mean this application is deployed there. See [integrations](../system-design/integrations.md) and [known unknowns](../status/known-issues.md).