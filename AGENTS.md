# Project Agent Instructions

## Skill Router (`ask-matt`)

Whenever the user types `ask-matt` or `/ask-matt` in a prompt (or asks which skill/workflow to use):
1. Read `.agents/skills/ask-matt/SKILL.md` (and `.agents/skills/ask-matt/PHASE-BOUNDARIES.md` when phase boundaries are involved).
2. Read the `SKILL.md` of any skill you recommend before stating what it does or suggesting skipping a step.
3. Recommend the fitting skill or flow for the user's situation.

## User-Invoked Skills

Skills under `.agents/skills/` marked `disable-model-invocation: true` do not appear in the automatic skill index (`ask-matt`, `setup-matt-pocock-skills`, `grill-with-docs`, `grill-me`, `to-spec`, `to-tickets`, `implement`, `implement-spec`, `improve-codebase-architecture`, `triage`, `retro`, `handoff`, `wayfinder`, `wait-what`, `teach`, `to-questionnaire`, `loop-me`, `claude-handoff`, `writing-beats`, `writing-fragments`, `writing-shape`).
When the user references any of these by name or `/name`, read `.agents/skills/<name>/SKILL.md` and follow its instructions.

## Agent skills

### Issue tracker

Issues, specs, and wayfinder maps live as local markdown files under `.scratch/`. See `docs/agents/issue-tracker.md`.

### Triage labels

Default five canonical triage roles (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context layout (`CONTEXT.md` and `docs/adr/` at the repo root). See `docs/agents/domain.md`.
