#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="${DEPLOY_ENV_FILE:-$ROOT/.deploy-ftp.env}"
LAST_DEPLOY_FILE="$ROOT/.deploy-ftp.last"
UNIQUE_FILES=()

WITH_VENDOR=0
DRY_RUN=0
LIST_REMOTE=0
FULL_MIRROR=0
LAST_COMMIT_ONLY=0
MARK_SYNCED=0
ONLY_FILES=()
ONLY_FORCE=0

usage() {
    cat <<'EOF'
Upotreba: ./deploy-ftp.sh [opcije]

  (default)       Fajlovi od poslednjeg deploy-a + necommitovane izmene u src/
                  (ako nema markera — samo necommitovane izmene)
  --last-commit   Samo fajlovi iz poslednjeg git commit-a
  --mark-synced   Označi da je server sync-ovan sa trenutnim git HEAD (jednom posle full upload-a)
  --full          Pun mirror celog src/ (sporo — retko)
  --list-remote   Prikaži FTP pwd i foldere
  --with-vendor   Uključi vendor/ fajlove
  --dry-run       Prikaži listu, bez slanja
  --reset-last    Resetuj marker poslednjeg deploy-a
  --only PATH     Eksplicitno pošalji fajl(ove), ignoriši git diff (može više puta)
                  vendor/ i wp-config.php se ne preskaču kad su eksplicitno navedeni
  -h, --help      Pomoć

Prvi put (posle ručnog / full FTP upload-a celog projekta):
  ./deploy-ftp.sh --mark-synced

Svaki sledeći deploy:
  git commit ...
  ./deploy-ftp.sh --dry-run
  ./deploy-ftp.sh
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --with-vendor) WITH_VENDOR=1; shift ;;
        --dry-run) DRY_RUN=1; shift ;;
        --list-remote) LIST_REMOTE=1; shift ;;
        --full) FULL_MIRROR=1; shift ;;
        --last-commit) LAST_COMMIT_ONLY=1; shift ;;
        --mark-synced) MARK_SYNCED=1; shift ;;
        --reset-last) rm -f "$LAST_DEPLOY_FILE"; echo "Marker deploy-a obrisan."; exit 0 ;;
        --only)
            ONLY_FORCE=1
            shift
            [[ $# -eq 0 ]] && { echo "Greška: --only zahteva putanju fajla." >&2; exit 1; }
            ONLY_FILES+=("$1")
            shift
            ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Nepoznata opcija: $1" >&2; usage; exit 1 ;;
    esac
done

if ! command -v lftp >/dev/null 2>&1; then
    echo "Greška: lftp nije instaliran. Pokreni: brew install lftp" >&2
    exit 1
fi

if ! git -C "$ROOT" rev-parse --git-dir >/dev/null 2>&1; then
    echo "Greška: $ROOT nije git repozitorijum." >&2
    exit 1
fi

if [[ "$MARK_SYNCED" -eq 1 ]]; then
    git -C "$ROOT" rev-parse HEAD > "$LAST_DEPLOY_FILE"
    echo "Server označen kao sync-ovan sa commit-om: $(cat "$LAST_DEPLOY_FILE")"
    echo "Sledeći ./deploy-ftp.sh šalje samo novije izmene posle ovog commit-a."
    exit 0
fi

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Greška: nema fajla $ENV_FILE" >&2
    echo "Kopiraj: cp .deploy-ftp.env.example .deploy-ftp.env" >&2
    exit 1
fi

# shellcheck disable=SC1090
source "$ENV_FILE"

: "${FTP_HOST:?FTP_HOST nije setovan u .deploy-ftp.env}"
: "${FTP_USER:?FTP_USER nije setovan u .deploy-ftp.env}"
: "${FTP_PASS:?FTP_PASS nije setovan u .deploy-ftp.env}"

LOCAL_PATH="${LOCAL_PATH:-src}"
LOCAL_DIR="$ROOT/$LOCAL_PATH"
FTP_PARALLEL="${FTP_PARALLEL:-4}"
FTP_SSL="${FTP_SSL:-false}"
FTP_REMOTE_PATH="${FTP_REMOTE_PATH:-.}"

LFTP_SSL_SETTINGS=""
if [[ "$FTP_SSL" == "true" ]]; then
    LFTP_SSL_SETTINGS="set ftp:ssl-force true
set ftp:ssl-protect-data true
set ssl:verify-certificate no"
else
    LFTP_SSL_SETTINGS="set ftp:ssl-allow false
set ftp:ssl-force false"
fi

run_lftp() {
    lftp -u "$FTP_USER","$FTP_PASS" "$FTP_HOST" <<EOF
set cmd:fail-exit yes
set net:timeout 30
set net:max-retries 3
set net:reconnect-interval-base 5
$LFTP_SSL_SETTINGS
$1
bye
EOF
}

should_skip_file() {
    local rel="$1"

    case "$rel" in
        .env|.env.*|*/.env|*/.env.*) return 0 ;;
        wp-config.php) return 0 ;;
        wp-content/uploads/*|wp-content/upgrade/*|wp-content/cache/*) return 0 ;;
        debug.log|wp-content/debug.log|*/debug.log) return 0 ;;
        vendor/*|*/vendor/*) [[ "$WITH_VENDOR" -eq 1 ]] && return 1 || return 0 ;;
        node_modules/*|*/node_modules/*) return 0 ;;
        tests/*|*/tests/*) return 0 ;;
        *.mov|*.sql|composer.phar) return 0 ;;
    esac

    return 1
}

add_unique_file() {
    local file="$1"
    local existing
    local rel

    [[ -z "$file" ]] && return 0
    [[ "$file" != "$LOCAL_PATH"/* ]] && return 0
    rel="${file#"$LOCAL_PATH"/}"
    if [[ "$ONLY_FORCE" -eq 0 ]] || { [[ "$rel" != vendor/* ]] && [[ "$rel" != */vendor/* ]] && [[ "$rel" != wp-config.php ]]; }; then
        should_skip_file "$rel" && return 0
    fi
    [[ -f "$ROOT/$file" ]] || return 0

    for existing in "${UNIQUE_FILES[@]:-}"; do
        [[ "$existing" == "$file" ]] && return 0
    done

    UNIQUE_FILES+=("$file")
    return 0
}

collect_changed_files() {
    local -a raw_files=()
    UNIQUE_FILES=()
    DEPLOY_FILES=()
    local last_sha=""
    local pattern="$LOCAL_PATH/"
    local file

    if [[ "$LAST_COMMIT_ONLY" -eq 1 ]]; then
        if git -C "$ROOT" rev-parse HEAD~1 >/dev/null 2>&1; then
            while IFS= read -r line; do raw_files+=("$line"); done < <(git -C "$ROOT" diff --name-only HEAD~1 HEAD -- "$pattern")
        else
            while IFS= read -r line; do raw_files+=("$line"); done < <(git -C "$ROOT" diff-tree --no-commit-id --name-only -r HEAD -- "$pattern")
        fi
    else
        if [[ -f "$LAST_DEPLOY_FILE" ]]; then
            last_sha="$(tr -d '[:space:]' < "$LAST_DEPLOY_FILE")"
            if [[ -n "$last_sha" ]] && git -C "$ROOT" cat-file -e "${last_sha}^{commit}" 2>/dev/null; then
                while IFS= read -r line; do raw_files+=("$line"); done < <(git -C "$ROOT" diff --name-only "$last_sha" HEAD -- "$pattern")
            fi
        fi

        while IFS= read -r line; do raw_files+=("$line"); done < <(git -C "$ROOT" diff --name-only HEAD -- "$pattern")
        while IFS= read -r line; do raw_files+=("$line"); done < <(git -C "$ROOT" diff --name-only --cached -- "$pattern")
        while IFS= read -r line; do raw_files+=("$line"); done < <(git -C "$ROOT" ls-files --others --exclude-standard -- "$pattern")
    fi

    for file in "${raw_files[@]:-}"; do
        add_unique_file "$file"
    done

    if [[ ${#UNIQUE_FILES[@]} -gt 0 ]]; then
        DEPLOY_FILES=("${UNIQUE_FILES[@]}")
    else
        DEPLOY_FILES=()
    fi
}

if [[ "$LIST_REMOTE" -eq 1 ]]; then
    echo "=========================================="
    echo " FTP remote listing — $FTP_HOST"
    echo "=========================================="
    echo " Tražim WordPress root (wp-admin) u . / public / www-root / www-root/public"
    echo " U .deploy-ftp.env stavi FTP_REMOTE_PATH na folder gde vidiš wp-admin."
    echo "=========================================="
    lftp -u "$FTP_USER","$FTP_PASS" "$FTP_HOST" <<EOF
set cmd:fail-exit no
set net:timeout 30
set net:max-retries 2
$LFTP_SSL_SETTINGS
echo '=== pwd ==='
pwd -p
echo ''
echo '=== FTP root (.) ==='
cls -l
echo ''
echo '=== . (ima li wp-admin?) ==='
cls -l wp-admin || echo '(nema wp-admin u .)'
echo ''
echo '=== public/ ==='
cls -l public || echo '(nema folder public)'
echo ''
echo '=== public/wp-admin ==='
cls -l public/wp-admin || echo '(nema wp-admin u public)'
echo ''
echo '=== www-root/ ==='
cls -l www-root || echo '(nema folder www-root)'
echo ''
echo '=== www-root/public/wp-admin ==='
cls -l www-root/public/wp-admin || echo '(nema wp-admin u www-root/public)'
bye
EOF
    exit 0
fi

if [[ ! -d "$LOCAL_DIR" ]]; then
    echo "Greška: lokalni folder ne postoji: $LOCAL_DIR" >&2
    exit 1
fi

if [[ "$FULL_MIRROR" -eq 1 ]]; then
    EXCLUDES=(-X ".git/" -X ".env" -X ".env.*" -X ".DS_Store" -X "node_modules/" -X "wp-config.php" -X "wp-content/uploads/" -X "wp-content/upgrade/" -X "wp-content/cache/" -X "debug.log" -X "wp-content/debug.log")
    [[ "$WITH_VENDOR" -eq 0 ]] && EXCLUDES+=(-X "vendor/")

    echo "=========================================="
    echo " SamoNameštaj FTP deploy — PUN MIRROR"
    echo "=========================================="
    MIRROR_FLAGS=(--reverse --only-newer --parallel="$FTP_PARALLEL" --verbose)
    [[ "$DRY_RUN" -eq 1 ]] && MIRROR_FLAGS+=(--dry-run)

    run_lftp "
lcd $LOCAL_DIR
cd $FTP_REMOTE_PATH
mirror ${MIRROR_FLAGS[*]} ${EXCLUDES[*]}
"

    if [[ "$DRY_RUN" -eq 0 ]]; then
        git -C "$ROOT" rev-parse HEAD > "$LAST_DEPLOY_FILE"
    fi

    echo ""
    [[ "$DRY_RUN" -eq 1 ]] && echo "Dry-run završen." || echo "Pun mirror završen."
    exit 0
fi

DEPLOY_FILES=()
if [[ ${#ONLY_FILES[@]} -gt 0 ]]; then
    UNIQUE_FILES=()
    for file in "${ONLY_FILES[@]}"; do
        if [[ "$file" != "$LOCAL_PATH"/* ]]; then
            file="$LOCAL_PATH/${file#src/}"
            file="${file#./}"
        fi
        add_unique_file "$file"
    done
    if [[ ${#UNIQUE_FILES[@]} -gt 0 ]]; then
        DEPLOY_FILES=("${UNIQUE_FILES[@]}")
    else
        DEPLOY_FILES=()
    fi
else
    collect_changed_files
fi

echo "=========================================="
echo " SamoNameštaj FTP deploy — samo izmene"
echo "=========================================="
echo " Lokalno : $LOCAL_DIR"
echo " Remote  : $FTP_REMOTE_PATH"
echo " Host    : $FTP_HOST"
if [[ -f "$LAST_DEPLOY_FILE" ]]; then
    echo " Od      : $(tr -d '[:space:]' < "$LAST_DEPLOY_FILE")"
else
    echo " Od      : (nema markera — samo necommitovane izmene)"
    echo " Savet   : posle full upload-a pokreni ./deploy-ftp.sh --mark-synced"
fi
echo " Fajlova : ${#DEPLOY_FILES[@]}"
echo "=========================================="

if [[ "${#DEPLOY_FILES[@]}" -eq 0 ]]; then
    echo "Nema fajlova za deploy."
    echo "Commituj izmene pa pokreni ponovo, ili: ./deploy-ftp.sh --last-commit"
    exit 0
fi

printf ' - %s\n' "${DEPLOY_FILES[@]}"

if [[ "$DRY_RUN" -eq 1 ]]; then
    echo ""
    echo "Dry-run: ništa nije poslato."
    exit 0
fi

LFTP_COMMANDS="lcd $LOCAL_DIR
cd $FTP_REMOTE_PATH
"

for file in "${DEPLOY_FILES[@]}"; do
    rel="${file#"$LOCAL_PATH"/}"
    remote_dir="$(dirname "$rel")"
    if [[ "$remote_dir" != "." ]]; then
        LFTP_COMMANDS+="mkdir -f -p $remote_dir
"
    fi
    LFTP_COMMANDS+="put -O $remote_dir $ROOT/$file
"
done

run_lftp "$LFTP_COMMANDS"

git -C "$ROOT" rev-parse HEAD > "$LAST_DEPLOY_FILE"

echo ""
echo "Deploy završen (${#DEPLOY_FILES[@]} fajlova)."
echo "wp-config.php i uploads se ne šalju (ostaju produkcijski)."
