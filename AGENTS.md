# Working on Vazho

Read these before changing this repository:

1. `docs/internal/agent-workflows/STATUS.md` — current progress and permitted scope.
2. `docs/internal/agent-workflows/DECISIONS.md` — owner decisions and boundaries.
3. `docs/internal/agent-workflows/01-Vazho-Development-Framework.md` — architecture, simplicity, module contract, testing and delivery.
4. `docs/internal/agent-workflows/CONTINUE-HERE.md` — next task and inputs.

Preserve working code. Keep normal use simple and optional capabilities modular. Reuse `SSC_Module` / `SSC_Modules`, existing settings, HTTP guard and tests.

Roadmaps, historical audits and attached proposals are reference material, not authorization to implement every listed item. Only the current owner's selected task is executable. If no current task is authorized, report the next concrete step and ask one focused scope question before changing product behavior.

No version bump, tag, release, marketplace upload or deployment without explicit owner authorization. Local test ZIPs are distinct from public releases. README describes automatic release behavior: a version bump followed by merge can publish automatically.

At each handoff update STATUS.md: what changed, what is implemented versus only designed, relevant tests and limitations, current branch/commit, blockers and one next step. Do not write a guessed commit SHA. New stable owner decisions go in DECISIONS.md; do not silently mark unselected roadmap rows as approved.

Internal workflow docs belong under `docs/internal/` and must remain excluded from the installable ZIP. Never delete active regression tests, fixtures, licenses or migrations merely because they are old.
