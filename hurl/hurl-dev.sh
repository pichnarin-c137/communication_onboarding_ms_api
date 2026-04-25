#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VARS_FILE="$ROOT_DIR/hurl/env/dev_env.hurl"

if [[ $# -lt 1 ]]; then
  echo "Usage: $(basename "$0") <hurl-file> [extra hurl args]" >&2
  echo "Example: $(basename "$0") hurl/smoke/auth/healthcheck.hurl" >&2
  exit 1
fi

INPUT_FILE="$1"
shift

if [[ -f "$INPUT_FILE" ]]; then
  exec hurl --variables-file "$VARS_FILE" "$INPUT_FILE" "$@"
fi

if [[ -f "$ROOT_DIR/hurl/$INPUT_FILE" ]]; then
  exec hurl --variables-file "$VARS_FILE" "$ROOT_DIR/hurl/$INPUT_FILE" "$@"
fi

if [[ -f "$ROOT_DIR/hurl/smoke/$INPUT_FILE" ]]; then
  exec hurl --variables-file "$VARS_FILE" "$ROOT_DIR/hurl/smoke/$INPUT_FILE" "$@"
fi

echo "error: Cannot access '$INPUT_FILE': No such file or directory" >&2
exit 1
