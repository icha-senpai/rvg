#!/usr/bin/env bash
set -euo pipefail

home=/srv/rvg
repo=$home/repo
branch=release
pending=$home/.horizon-update.pending
queue=horizon-rvg-queue.service
timer=horizon-rvg-schedule.timer
schedule=horizon-rvg-schedule.service
bot_unit=horizon-rvg-bot.service
export PATH="/opt/rvg-node-v24.22.3/bin:$PATH"
export GIT_TERMINAL_PROMPT=0

fail() { printf '%s\n' "$*" >&2; exit 1; }
as_app() { runuser -u horizon_rvg -- "$@"; }
git_app() { as_app git -C "$repo" "$@" </dev/null; }
sha() { [[ $1 =~ ^[a-f0-9]{40}$ ]]; }
active() { if systemctl is-active --quiet "$1"; then printf 1; else printf 0; fi; }
read_pending() {
    read -r from next queue_on timer_on bot_on < "$pending"
    sha "$from" && sha "$next" && [[ $queue_on$timer_on$bot_on =~ ^[01]{3}$ ]] ||
        fail 'Invalid unfinished deployment marker.'
}

[[ $# -le 1 ]] || fail 'Usage: horizon-update [--plan|--apply]'
case ${1:---apply} in
    --plan) mode=plan ;;
    --apply) mode=apply ;;
    *) fail 'Usage: horizon-update [--plan|--apply]' ;;
esac
[[ $EUID == 0 ]] || fail 'Run horizon-update as root.'

exec 9>"$home/.horizon-update.lock"
flock -n 9 || fail 'Another Horizon deployment is in progress.'
[[ -z $(git_app status --porcelain) ]] || fail 'Horizon checkout is dirty; no files changed.'
current_branch=$(git_app symbolic-ref --short HEAD)
[[ $current_branch == release || $current_branch == dev ]] ||
    fail 'Horizon must be on release (or dev for the initial transition).'
expected_head=$(git_app rev-parse HEAD)
git_app -c credential.interactive=false fetch --no-tags origin \
    "+refs/heads/$branch:refs/remotes/origin/$branch"
release_head=$(git_app rev-parse "refs/remotes/origin/$branch")
base=$expected_head
target=$release_head

if [[ -f $pending ]]; then
    read_pending
    base=$from
    target=$next
    git_app merge-base --is-ancestor "$target" "$release_head" ||
        fail 'The unfinished deployment is no longer on origin/release; inspect it before retrying.'
    [[ $expected_head == "$base" || $expected_head == "$target" ]] || fail 'Unexpected HEAD during retry.'
else
    queue_on=$(active "$queue")
    timer_on=$(active "$timer")
    bot_on=$(active "$bot_unit")
fi

git_app merge-base --is-ancestor "$expected_head" "$target" || fail 'Not a fast-forward.'
if [[ $current_branch != "$branch" ]] && git_app show-ref --verify --quiet "refs/heads/$branch"; then
    [[ $(git_app rev-parse "refs/heads/$branch") == "$expected_head" ]] ||
        fail 'The existing local release branch differs from the active checkout.'
fi

git_app diff --quiet "$base" "$target" -- backend/database/migrations backend/database/schema ||
    fail 'Schema changes require a separately reviewed migration; nothing was activated.'
git_app diff --quiet "$base" "$target" -- backend/storage ||
    fail 'Laravel storage changes require a separate release.'
[[ -z $(git_app ls-tree -r --name-only "$target" -- backend/.env backend/vendor backend/public/build \
    backend/public/hot backend/public/storage bots/horizon-bot/.env bots/horizon-bot/node_modules) ]] ||
    fail 'The candidate tracks a server environment file or a generated runtime path.'

backend=0
bot=0
git_app diff --quiet "$base" "$target" -- backend || backend=1
git_app diff --quiet "$base" "$target" -- bots/horizon-bot || bot=1
printf 'Horizon release: %.8s -> %.8s\n' "$expected_head" "$target"
printf 'Rebuild Laravel: %s; rebuild Discord bot: %s\n' "$backend" "$bot"
git_app diff --stat "$base" "$target"
[[ ! -f $pending ]] || printf 'Retrying unfinished deployment %.8s before the latest release.\n' "$target"
[[ $mode != plan ]] || exit 0

switch_release() {
    git_app config remote.origin.fetch "+refs/heads/$branch:refs/remotes/origin/$branch"
    if [[ $current_branch != "$branch" ]]; then
        if git_app show-ref --verify --quiet "refs/heads/$branch"; then
            git_app switch "$branch"
        else
            git_app switch -c "$branch"
        fi
    fi
    git_app branch --set-upstream-to="origin/$branch" "$branch"
}

if [[ $expected_head == "$target" && ! -f $pending ]]; then
    switch_release
    printf 'Horizon is already on the current release.\n'
    exit 0
fi

stage=$(mktemp -d "$home/.horizon-build.XXXXXXXX")
trap 'find -P "$stage" -depth -delete' EXIT
chown horizon_rvg:horizon_rvg "$stage"
app=$repo/backend
bot_path=bots/horizon-bot

stage_source() {
    git_app archive "$target" "$1" | as_app tar -x -C "$stage"
}
npm_app() { as_app env "PATH=$PATH" npm --prefix "$1" "${@:2}" </dev/null; }
artisan() { as_app php "$app/artisan" "$@" --no-ansi --no-interaction </dev/null; }
check_migrations() {
    local status
    status=$(artisan migrate:status)
    [[ $status == *Ran* && $status != *Pending* ]] || fail 'Laravel DB migration status is not current.'
}

if (( backend )); then
    if [[ ! -f $pending ]]; then check_migrations; fi
    stage_source backend
    as_app ln -sT "$app/.env" "$stage/backend/.env"
    as_app composer --working-dir="$stage/backend" install --no-dev --no-scripts --no-interaction --no-progress --prefer-dist </dev/null
    as_app composer --working-dir="$stage/backend" audit --locked --no-dev --no-interaction --format=summary </dev/null
    npm_app "$stage/backend" ci --ignore-scripts --no-audit --no-fund
    npm_app "$stage/backend" audit --package-lock-only
    npm_app "$stage/backend" run build
    [[ -s $stage/backend/public/build/manifest.json ]] || fail 'Laravel build manifest missing.'
fi
if (( bot )); then
    stage_source "$bot_path"
    npm_app "$stage/$bot_path" ci --omit=dev --ignore-scripts --no-audit --no-fund
    npm_app "$stage/$bot_path" audit --omit=dev --package-lock-only
    as_app /opt/rvg-node-v24.22.3/bin/node --check "$stage/$bot_path/index.js"
fi

for destination in "$app/vendor" "$app/public/build" "$repo/$bot_path/node_modules"; do
    [[ ! -L $destination ]] || fail "Refusing to replace a linked generated directory: $destination"
done
if (( backend )) && [[ ! -f $pending && -e $app/storage/framework/down ]]; then
    fail 'Laravel is already in maintenance mode; leave it intact.'
fi
if [[ ! -f $pending ]]; then
    (umask 077; set -o noclobber; printf '%s %s %s %s %s\n' "$base" "$target" "$queue_on" "$timer_on" "$bot_on" > "$pending")
fi

if (( backend )); then
    artisan down --retry=60
    systemctl stop "$timer"
    for (( attempt=0; attempt<30; attempt++ )); do
        [[ $(active "$schedule") == 1 ]] || break
        sleep 2
    done
    [[ $(active "$schedule") == 0 ]] || fail 'An existing scheduler run did not finish; retry the same commit.'
    systemctl stop "$queue"
fi
if (( bot )); then systemctl stop "$bot_unit"; fi

switch_release
git_app merge --ff-only --no-overwrite-ignore "$target"
sync_generated() { as_app rsync -a --checksum --delay-updates --delete-delay -- "$1/" "$2/"; }
if (( backend )); then
    sync_generated "$stage/backend/vendor" "$app/vendor"
    sync_generated "$stage/backend/public/build" "$app/public/build"
    artisan package:discover
    check_migrations
    systemctl reload php8.5-fpm.service
    artisan up
    for path in /up /verify; do
        if ! code=$(curl --fail --silent --show-error --retry 2 --max-time 8 \
            -H 'Host: horizoninterstellar.com' -o /dev/null -w '%{http_code}' "http://127.0.0.1:8080$path") ||
            [[ $code != 200 ]]; then
            artisan down --retry=60
            fail "Laravel smoke check failed ($path); retry the same deployment after diagnosis."
        fi
    done
    if (( queue_on )); then systemctl start "$queue"; fi
    if (( timer_on )); then systemctl start "$timer"; fi
fi
if (( bot )); then
    sync_generated "$stage/$bot_path/node_modules" "$repo/$bot_path/node_modules"
    if (( bot_on )); then
        systemctl start "$bot_unit"
        invocation=$(systemctl show -p InvocationID --value "$bot_unit")
        sleep 10
        systemctl is-active --quiet "$bot_unit" &&
            [[ -n $invocation && $(systemctl show -p InvocationID --value "$bot_unit") == "$invocation" ]] ||
            fail 'Discord bot did not stay active.'
    fi
fi

rm -- "$pending"
git_app log -1 --format='Horizon deployed %h %s'
