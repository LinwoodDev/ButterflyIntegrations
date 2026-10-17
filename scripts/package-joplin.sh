#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
integration_dir="$repo_root/integrations/joplin"
output_dir="${1:-$repo_root/dist/joplin}"

(
	cd "$repo_root"
	node scripts/integration-version.mjs joplin
)

plugin_id="dev.linwood.butterfly-joplin"
jpl_file="$integration_dir/publish/${plugin_id}.jpl"
json_file="$integration_dir/publish/${plugin_id}.json"

if [[ ! -f "$jpl_file" ]]; then
	echo "Missing $jpl_file; build the Joplin plugin first (pnpm --filter joplin-plugin-butterfly dist)." >&2
	exit 1
fi

mkdir -p "$output_dir"
output_dir="$(cd "$output_dir" && pwd)"

cp "$jpl_file" "$output_dir/"
cp "$json_file" "$output_dir/"

(
	cd "$output_dir"
	sha256sum "${plugin_id}.jpl" "${plugin_id}.json" > checksums.txt
)

echo "Joplin plugin written to $output_dir"
