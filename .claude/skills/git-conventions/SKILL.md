---
name: git-conventions
description: "Handles Git commits and version control operations. Activates when committing changes, staging files, writing commit messages, or when the user mentions commit, git, push, stage, or asks to check in code."
license: MIT
metadata:
  author: azzazkhan
---

# Git Conventions

## When to Apply

Activate this skill when:

- The user asks to commit changes
- The user asks to stage files or check in code
- Writing commit messages
- Performing any git version control operations

## Commit Message Format

- Always use signed commits: `git commit -S -m "prefix: Message"`
- Single-line commit messages only — no multi-line body, no HEREDOC, no descriptions, no Co-Authored-By trailers
- Keep messages short but declarative
- Commit message (excluding the prefix) must not exceed 60 characters

## Conventional Prefixes

| Prefix | Usage |
|--------|-------|
| `feat:` | New feature |
| `fix:` | Bug fix |
| `test:` | Adding or updating tests |
| `wip:` | Work in progress |
| `doc:` | Documentation changes |
| `break:` | Breaking change |
| `refactor:` | Code refactoring (no behavior change) |
| `chore:` | Maintenance tasks, dependencies, config |
| `style:` | Code style/formatting changes |

## Staging Files

- Stage specific files by name rather than using `git add -A` or `git add .`
- Never stage files that may contain secrets (`.env`, credentials, etc.)

## Pre-Commit Hooks

- This project uses Husky + lint-staged for automatic formatting on commit
- Do NOT manually run Pint, Prettier, or any formatter before committing — the hooks handle it
- If a pre-commit hook fails, fix the underlying issue and create a NEW commit (never amend)

## Safety Rules

- Never force push to master/main
- Never use `--no-verify` or `--no-gpg-sign`
- Never amend commits unless explicitly asked
- Never run destructive git commands (reset --hard, checkout ., clean -f) unless explicitly asked
- Always create new commits rather than amending existing ones
