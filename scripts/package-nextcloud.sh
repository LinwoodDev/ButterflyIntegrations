#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
integration_dir="$repo_root/integrations/nextcloud"
output_dir="${1:-$repo_root/dist/nextcloud}"
stage_dir="$(mktemp -d)"

(
	cd "$repo_root"
	node scripts/integration-version.mjs nextcloud
)

cleanup() {
	rm -rf "$stage_dir"
}
trap cleanup EXIT

for required_dir in appinfo css img js lib templates; do
	if [[ ! -d "$integration_dir/$required_dir" ]]; then
		echo "Missing $integration_dir/$required_dir; build the Nextcloud frontend first." >&2
		exit 1
	fi
done

app_dir="$stage_dir/butterfly"
mkdir -p "$app_dir" "$output_dir"
output_dir="$(cd "$output_dir" && pwd)"

for app_component in appinfo css img js lib templates; do
	cp -R "$integration_dir/$app_component" "$app_dir/"
done

cp "$repo_root/LICENSE" "$app_dir/LICENSE"
cp "$integration_dir/CHANGELOG.md" "$app_dir/CHANGELOG.md"
cp "$integration_dir/README.md" "$app_dir/README.md"

tar -C "$stage_dir" -czf "$output_dir/butterfly-nextcloud.tar.gz" butterfly
(
	cd "$stage_dir"
	zip -qr "$output_dir/butterfly-nextcloud.zip" butterfly
)

(
	cd "$output_dir"
	sha256sum butterfly-nextcloud.tar.gz butterfly-nextcloud.zip > checksums.txt
)

echo "Nextcloud archives written to $output_dir"
