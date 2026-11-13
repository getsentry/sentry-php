#!/bin/sh
# Runs decision-model-linter on the lines a pull request changes, keeps one PR comment with the
# findings, and lets the override label pass a failing run. Called by
# .github/workflows/decision-model-lint.yml from the repository root.
#
# Env: OPENROUTER_API_KEY, GH_TOKEN, PR, LABEL, OVERRIDDEN, plus GitHub's GITHUB_REPOSITORY,
# RUNNER_TEMP and GITHUB_STEP_SUMMARY.
set -eu

if [ -z "${OPENROUTER_API_KEY:-}" ]; then
  printf '::warning::Skipping decision model lint: OPENROUTER_API_KEY is empty, so the workflow has no OpenRouter secret.\n'
  exit 0
fi

report="$RUNNER_TEMP/decision-model-lint.md"
status=0
decision-model-linter/node_modules/.bin/decision-model-linter --config decision-model-linter/config.ts \
  --base HEAD^1 --format markdown > "$report" || status=$?

label_md="\`$LABEL\`"

# Only 1 means findings; any other failure means the linter did not finish.
if [ "$status" -eq 1 ] && [ "$OVERRIDDEN" = true ]; then
  printf '\nThe %s label keeps these errors from failing the check.\n' "$label_md" >> "$report"
elif [ "$status" -eq 1 ]; then
  printf '\nThese errors fail the check; fix the code to resolve them. If you and a reviewer are confident an error is wrong, a person can add the %s label to pass the check while the findings stay reported. Agents should never add this label.\n' "$label_md" >> "$report"
elif [ "$status" -gt 1 ] && [ "$OVERRIDDEN" = true ]; then
  printf 'The linter did not finish (exit %s), and the %s label passes the check anyway; see the job log.\n' "$status" "$label_md" >> "$report"
fi

marker='<!-- decision-model-linter -->'
comment_ids=$(gh api "repos/$GITHUB_REPOSITORY/issues/$PR/comments" --paginate \
  --jq ".[] | select(.user.login == \"github-actions[bot]\" and (.body | contains(\"$marker\"))) | .id")
comment=$(printf '%s\n' "$comment_ids" | head -n 1)

body=
if [ -s "$report" ] && { [ "$status" -le 1 ] || [ "$OVERRIDDEN" = true ]; }; then
  findings=$(cat "$report")
  body=$(printf '%s\n%s' "$marker" "$findings")
elif [ "$status" -eq 0 ] && [ -n "$comment" ]; then
  body=$(printf '%s\nNo findings on the latest commit.' "$marker")
fi

if [ -n "$body" ] && [ -n "$comment" ]; then
  gh api -X PATCH "repos/$GITHUB_REPOSITORY/issues/comments/$comment" -f body="$body" > /dev/null
elif [ -n "$body" ]; then
  gh api "repos/$GITHUB_REPOSITORY/issues/$PR/comments" -f body="$body" > /dev/null
fi
cat "$report" >> "$GITHUB_STEP_SUMMARY"

if [ "$status" -ne 0 ] && [ "$OVERRIDDEN" = true ]; then
  exit 0
fi
exit "$status"
