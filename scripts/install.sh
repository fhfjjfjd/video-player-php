#!/usr/bin/env bash
#
# videohub - one-command installer for the video-player-php app.
#
# Detects your OS, clones the source code at the latest GitHub release,
# installs PHP (the runtime), and creates a global `videohub` command so you
# can just type
#   videohub
# to start the app. The app is 100% PHP (no compilation, no build step).
#
# Usage:
#   bash scripts/install.sh            # install (or update if already installed)
#   bash scripts/install.sh update     # update the app in place
#   bash scripts/install.sh reinstall  # remove everything, then install fresh
#   bash scripts/install.sh uninstall  # remove launcher, PATH entries, and app data
#
# After install, `videohub update|reinstall|uninstall` run the same flows.
#
# Install/update pins the app to the LATEST GitHub release: the source is
# checked out at the release tag.
#
# The app lives in ~/videohub (override with VIDEOHUB_DIR).
#
set -euo pipefail

REPO="fhfjfjd/video-player-php"
APP_NAME="videohub"
INSTALL_DIR="${VIDEOHUB_DIR:-$HOME/videohub}"

info() { printf '\033[1;34m[videohub]\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31m[videohub ERROR]\033[0m %s\n' "$*" >&2; exit 1; }

MODE="${1:-install}"
case "$MODE" in
    install|update|reinstall|uninstall) ;;
    *) die "Unknown argument: $MODE. Usage: bash scripts/install.sh [install|update|reinstall|uninstall]" ;;
esac

# --- 1. Detect OS -------------------------------------------------------------
detect_os() {
    case "$(uname -s)" in
        Darwin)
            echo darwin
            ;;
        MINGW*|MSYS*|CYGWIN*)
            echo windows
            ;;
        *)
            if [ -n "${TERMUX_VERSION:-}" ] || command -v termux-info >/dev/null 2>&1; then
                echo android
            else
                echo linux
            fi
            ;;
    esac
}

# --- 2. Detect CPU arch -------------------------------------------------------
detect_arch() {
    case "$(uname -m)" in
        x86_64|amd64|AMD64) echo x64 ;;
        aarch64|arm64|ARM64) echo arm64 ;;
        *) echo unknown ;;
    esac
}

OS="$(detect_os)"

# Global command dir for the `videohub` launcher
if [ "$OS" = "android" ]; then
    CMD_DIR="${PREFIX:-/data/data/com.termux/files/usr}/bin"
else
    CMD_DIR="$HOME/.local/bin"
fi

# --- Helpers ----------------------------------------------------------------
# Ask whether to keep uploaded videos. Non-interactive runs default to KEEP.
keep_data() {
    local ans
    printf '\033[1;33m[videohub]\033[0m Keep uploaded videos (uploads/ + data.db)? [y/N] '
    if ! read -r ans </dev/tty 2>/dev/null; then
        printf '\n'
        return 0
    fi
    case "$ans" in
        y|Y|yes|YES|Yes) return 0 ;;
        *) return 1 ;;
    esac
}

# Remove everything inside INSTALL_DIR except user data (uploads, database, secret).
wipe_except_data() {
    local entry
    for entry in "$INSTALL_DIR"/* "$INSTALL_DIR"/.[!.]*; do
        [ -e "$entry" ] || continue
        case "$(basename "$entry")" in
            uploads|data.db|data.db-wal|data.db-shm|.media-secret) continue ;;
        esac
        rm -rf "$entry"
    done
}

# Remove the PATH entries added by the installer from shell profiles.
remove_path_entries() {
    local profile
    for profile in "$HOME/.profile" "$HOME/.bashrc" "$HOME/.zshrc"; do
        [ -f "$profile" ] || continue
        grep -q '# videohub' "$profile" || continue
        grep -v '# videohub' "$profile" | grep -v "export PATH=.*$CMD_DIR" > "${profile}.vh.tmp" \
            && mv "${profile}.vh.tmp" "$profile"
        info "  Cleaned PATH entry in $profile"
    done
}

# --- Uninstall --------------------------------------------------------------
if [ "$MODE" = "uninstall" ]; then
    info "Uninstalling $APP_NAME ..."
    rm -f "$CMD_DIR/$APP_NAME"
    info "  Removed launcher: $CMD_DIR/$APP_NAME"
    [ "$OS" != "android" ] && remove_path_entries
    if [ -d "$INSTALL_DIR" ]; then
        if keep_data; then
            wipe_except_data
            info "  Kept uploads/ and data.db in $INSTALL_DIR"
        else
            rm -rf "$INSTALL_DIR"
            info "  Removed app directory: $INSTALL_DIR"
        fi
    fi
    info "$APP_NAME has been uninstalled."
    exit 0
fi

ARCH="$(detect_arch)"

[ "$ARCH" = "unknown" ] && die "Unsupported CPU architecture: $(uname -m)"
info "Detected platform: $OS-$ARCH"

# --- 3. Prerequisites ---------------------------------------------------------
for tool in git curl; do
    command -v "$tool" >/dev/null 2>&1 || die "Missing required tool: $tool"
done

# PHP is the runtime. Install it (and ffmpeg for thumbnails) when missing.
if ! command -v php >/dev/null 2>&1; then
    info "PHP not found - installing it..."
    case "$OS" in
        android)
            pkg install -y php ffmpeg
            ;;
        darwin)
            command -v brew >/dev/null 2>&1 || die "Homebrew not found. Install Homebrew, then re-run."
            brew install php ffmpeg
            ;;
        linux)
            if command -v apt-get >/dev/null 2>&1; then
                if [ "$(id -u)" = "0" ]; then
                    apt-get update -y
                    apt-get install -y php-cli php-sqlite3 ffmpeg
                else
                    sudo apt-get update -y
                    sudo apt-get install -y php-cli php-sqlite3 ffmpeg
                fi
            elif command -v dnf >/dev/null 2>&1; then
                if [ "$(id -u)" = "0" ]; then
                    dnf install -y php-cli php-pdo ffmpeg
                else
                    sudo dnf install -y php-cli php-pdo ffmpeg
                fi
            else
                die "PHP not found and no supported package manager. Install PHP (>= 8.1) manually and re-run."
            fi
            ;;
    esac
    command -v php >/dev/null 2>&1 || die "PHP install failed. Install PHP (>= 8.1) manually and re-run."
fi

php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);' 2>/dev/null \
    || die "PHP is missing the pdo_sqlite extension (php-sqlite3). Install it and re-run."

if ! command -v ffmpeg >/dev/null 2>&1; then
    info "ffmpeg not found - automatic thumbnails will be skipped. Install ffmpeg to enable them."
fi

# --- Reinstall --------------------------------------------------------------
if [ "$MODE" = "reinstall" ]; then
    info "Reinstalling $APP_NAME (fresh)..."
    if [ -d "$INSTALL_DIR" ]; then
        if keep_data; then
            wipe_except_data
        else
            rm -rf "$INSTALL_DIR"
        fi
    fi
fi

# --- 4. Latest release ---------------------------------------------------------
info "Fetching the latest release tag..."
LATEST_TAG="$(curl -fsSL "https://api.github.com/repos/$REPO/releases/latest" 2>/dev/null \
    | sed -n 's/.*"tag_name"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -n 1)"
[ -n "$LATEST_TAG" ] || die "Failed to fetch the latest release tag from GitHub API"
info "Latest release: $LATEST_TAG"

# --- 5. Source code (pinned to the latest release tag) -------------------------
if [ -d "$INSTALL_DIR/.git" ]; then
    info "Updating source to $LATEST_TAG ..."
    git -C "$INSTALL_DIR" fetch --depth 1 origin tag "$LATEST_TAG" \
        || die "Failed to fetch tag $LATEST_TAG"
    git -C "$INSTALL_DIR" checkout --detach "$LATEST_TAG" \
        || die "Failed to checkout $LATEST_TAG"
else
    [ "$MODE" = "update" ] && die "App is not installed yet. Run: bash scripts/install.sh"
    info "Cloning source at $LATEST_TAG ..."
    git clone --depth 1 --branch "$LATEST_TAG" "https://github.com/$REPO.git" "$INSTALL_DIR" \
        || die "Failed to clone $REPO"
fi

cd "$INSTALL_DIR"

# --- 6. Backend (PHP) ----------------------------------------------------------
# The app is pure PHP (router index.php + src/). There is nothing to compile
# and no dependencies to install — it runs straight from the source via
# `php -S` (launched through scripts/start.sh).

# --- 7. Create the global `videohub` command ----------------------------------
mkdir -p "$CMD_DIR"

LAUNCHER="$CMD_DIR/$APP_NAME"
cat > "$LAUNCHER" <<EOF
#!/bin/sh
# $APP_NAME launcher (generated by install.sh)
case "\$1" in
    update|reinstall|uninstall)
        exec bash "$INSTALL_DIR/scripts/install.sh" "\$1"
        ;;
esac
cd "$INSTALL_DIR" || exit 1
exec bash "$INSTALL_DIR/scripts/start.sh" "\$@"
EOF
chmod +x "$LAUNCHER"

# Add CMD_DIR to PATH if it is not already there (non-Termux shells)
if [ "$OS" != "android" ]; then
    case ":$PATH:" in
        *":$CMD_DIR:"*) ;;
        *)
            profile="$HOME/.profile"
            if [ -n "${BASH_VERSION:-}" ]; then profile="$HOME/.bashrc"; fi
            if [ -n "${ZSH_VERSION:-}" ]; then profile="$HOME/.zshrc"; fi
            printf '\n# videohub\nexport PATH="%s:$PATH"\n' "$CMD_DIR" >> "$profile"
            info "Added $CMD_DIR to PATH in $profile"
            ;;
    esac
fi

# --- 8. Done ------------------------------------------------------------------
info ""
info "Install complete!"
info "  App directory : $INSTALL_DIR"
info "  Backend       : PHP (router index.php + src/)"
info "  Command       : $APP_NAME"
info ""
info "Open a NEW terminal, then run:  $APP_NAME"
