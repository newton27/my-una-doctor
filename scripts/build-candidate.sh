#!/usr/bin/env bash
# Reproducible inputs: exact clean Git HEAD, including upgrade templates.
set -euo pipefail
cd -- "$(dirname -- "$0")/.."
[[ -z $(git status --porcelain) ]] || { echo 'STOP: Worktree must be clean.'; exit 1; }
commit=$(git rev-parse HEAD)
output=$(mktemp -d "${TMPDIR:-/tmp}/my-una-doctor-064-candidate-XXXXXXXX")
stage="$output/stage"
mkdir -p "$stage"
git archive "$commit" | tar -x -C "$stage"
git archive --format=zip --prefix=my-una-doctor-source/ --output="$output/my-una-doctor-0.6.4-candidate-source.zip" "$commit"
mkdir "$output/my_una_doctor"
# Runtime payload comes only from the same commit; no tests/build templates.
for item in classes install template request.php cover.jpg README.md INSTALLATION.txt MARKETPLACE.txt MARKETPLACE-DESCRIPTION.html CHANGELOG.md docs; do
    cp -a -- "$stage/$item" "$output/my_una_doctor/"
done
(cd "$output" && zip -q -r my-una-doctor-0.6.4-candidate-full.zip my_una_doctor)
for from in 0.6.2 0.6.3; do
    wrapper="update_${from}_0.6.4"
    mkdir -p "$output/$wrapper/source"
    cp -a -- "$stage/upgrade-templates/${from}_0.6.4/install" "$output/$wrapper/"
    cp -a -- "$output/my_una_doctor/." "$output/$wrapper/source/"
    (cd "$output" && zip -q -r "my-una-doctor-${from}-to-0.6.4-candidate.zip" "$wrapper")
done
printf 'Source commit: %s\nStatus: CANDIDATE ONLY — not certified for installation\n' "$commit" > "$output/SOURCE.txt"
(cd "$output" && sha256sum ./*.zip > SHA256SUMS)
echo "Candidate archives and checksums: $output"
echo 'No upload, installation, tag or publication performed.'
