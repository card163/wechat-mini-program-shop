#!/usr/bin/env bash
# 管理后台（web-admin）线上发布脚本
#
# 部署方式：本地构建 -> 打包上传 -> 服务器解压到独立版本目录 -> 原子切换软链接
# 原子性保证：nginx 站点目录本身是一个软链接，指向 releases/<版本号>；
#   切换版本时先建一个新软链接再 mv -T 覆盖旧软链接，mv 在同一文件系统内
#   是 rename(2) 系统调用，具有原子性，不会出现"半个新版本"被访问到的情况。
#
# 用法：
#   sh/deploy-admin.sh              构建并发布最新代码
#   sh/deploy-admin.sh rollback     回滚到上一个已发布版本（不重新构建）
#
# 可通过环境变量覆盖默认配置，例如：
#   SSH_USER=deploy sh/deploy-admin.sh
set -euo pipefail

# ------------------------- 配置 -------------------------
SERVER_IP="${SERVER_IP:-59.110.52.164}"
SSH_USER="${SSH_USER:-root}"
SSH_PORT="${SSH_PORT:-22}"
REMOTE_ROOT="${REMOTE_ROOT:-/www/wwwroot/admin-nf.dwj.la}"
REMOTE_RELEASES="${REMOTE_RELEASES:-/www/wwwroot/admin-nf.dwj.la_releases}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
WEB_ADMIN_DIR="$REPO_ROOT/web-admin"

SSH_OPTS=(-p "$SSH_PORT" -o StrictHostKeyChecking=accept-new)
SSH_TARGET="$SSH_USER@$SERVER_IP"

log() { echo "[deploy-admin] $*"; }

# ------------------------- 回滚 -------------------------
rollback() {
  log "开始回滚 $REMOTE_ROOT 到上一个版本 ..."
  ssh "${SSH_OPTS[@]}" "$SSH_TARGET" bash -s -- "$REMOTE_ROOT" "$REMOTE_RELEASES" <<'REMOTE_EOF'
set -euo pipefail
REMOTE_ROOT="$1"
REMOTE_RELEASES="$2"

if [ ! -L "$REMOTE_ROOT" ]; then
  echo "错误：$REMOTE_ROOT 当前不是软链接结构（可能从未通过发布脚本部署过），无法回滚" >&2
  exit 1
fi

current_target="$(readlink -f "$REMOTE_ROOT")"
current_name="$(basename "$current_target")"

# 只在符合时间戳命名（严格14位数字）的版本目录中挑选，排除 000-bootstrap-* 备份目录，按名称排序即按时间排序
TS_GLOB='[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]'
mapfile -t releases < <(find "$REMOTE_RELEASES" -maxdepth 1 -mindepth 1 -type d -name "$TS_GLOB" -printf '%f\n' | sort)

prev_name=""
for i in "${!releases[@]}"; do
  if [ "${releases[$i]}" = "$current_name" ] && [ "$i" -gt 0 ]; then
    prev_name="${releases[$((i-1))]}"
  fi
done

if [ -z "$prev_name" ]; then
  echo "错误：找不到当前版本 $current_name 之前的可回滚版本" >&2
  exit 1
fi

echo "当前版本：$current_name  ->  回滚到：$prev_name"
ln -sfn "$REMOTE_RELEASES/$prev_name" "${REMOTE_ROOT}.new"
mv -Tf "${REMOTE_ROOT}.new" "$REMOTE_ROOT"
echo "回滚完成"
REMOTE_EOF
  log "回滚完成"
}

if [ "${1:-}" = "rollback" ]; then
  rollback
  exit 0
fi

# ------------------------- 构建 -------------------------
log "本地构建 web-admin ..."
cd "$WEB_ADMIN_DIR"
if [ -f package-lock.json ]; then
  npm ci
else
  npm install
fi
npm run build

if [ ! -f "$WEB_ADMIN_DIR/dist/index.html" ]; then
  echo "错误：构建产物 dist/index.html 不存在，构建可能失败" >&2
  exit 1
fi

# ------------------------- 打包 -------------------------
TS="$(date +%Y%m%d%H%M%S)"
TAR_NAME="admin-nf-${TS}.tar.gz"
TMP_TAR="/tmp/${TAR_NAME}"

log "打包构建产物 -> ${TMP_TAR}"
# macOS tar 默认会把扩展属性/资源分叉（AppleDouble、com.apple.provenance 等）写进 tar 包，须禁用并排除
COPYFILE_DISABLE=1 tar -czf "$TMP_TAR" -C "$WEB_ADMIN_DIR/dist" --exclude='.DS_Store' --exclude='._*' .

# ------------------------- 上传 -------------------------
log "上传到服务器 ${SSH_TARGET}:/tmp/${TAR_NAME}"
scp -P "$SSH_PORT" -o StrictHostKeyChecking=accept-new "$TMP_TAR" "$SSH_TARGET:/tmp/${TAR_NAME}"
rm -f "$TMP_TAR"

# ------------------------- 远程发布（原子切换）-------------------------
log "服务器端解压并原子切换版本 ..."
ssh "${SSH_OPTS[@]}" "$SSH_TARGET" bash -s -- "$REMOTE_ROOT" "$REMOTE_RELEASES" "$KEEP_RELEASES" "$TS" "$TAR_NAME" <<'REMOTE_EOF'
set -euo pipefail
REMOTE_ROOT="$1"
REMOTE_RELEASES="$2"
KEEP_RELEASES="$3"
TS="$4"
TAR_NAME="$5"

mkdir -p "$REMOTE_RELEASES"

RELEASE_DIR="$REMOTE_RELEASES/$TS"
mkdir -p "$RELEASE_DIR"
tar -xzf "/tmp/$TAR_NAME" -C "$RELEASE_DIR"
rm -f "/tmp/$TAR_NAME"

# 首次发布时，若站点目录是宝塔创建的普通目录（非软链接），先整体挪走保留，
# 避免 mv -T 覆盖非空目录失败，之后每次发布 $REMOTE_ROOT 都只会是软链接
if [ -e "$REMOTE_ROOT" ] && [ ! -L "$REMOTE_ROOT" ]; then
  backup="$REMOTE_RELEASES/000-bootstrap-$(date +%Y%m%d%H%M%S)"
  echo "首次发布：$REMOTE_ROOT 是普通目录，备份到 $backup"
  mv "$REMOTE_ROOT" "$backup"
fi

# 原子切换：先建临时软链接，再用 mv -T（rename 系统调用）整体替换
ln -sfn "$RELEASE_DIR" "${REMOTE_ROOT}.new"
mv -Tf "${REMOTE_ROOT}.new" "$REMOTE_ROOT"
echo "已切换到版本 $TS"

# 清理旧版本，只保留最近 KEEP_RELEASES 个时间戳版本目录（bootstrap 备份不清理）
TS_GLOB='[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]'
mapfile -t releases < <(find "$REMOTE_RELEASES" -maxdepth 1 -mindepth 1 -type d -name "$TS_GLOB" -printf '%f\n' | sort)
total="${#releases[@]}"
if [ "$total" -gt "$KEEP_RELEASES" ]; then
  to_remove=$(( total - KEEP_RELEASES ))
  for i in $(seq 0 $((to_remove - 1))); do
    old="${releases[$i]}"
    echo "清理旧版本：$old"
    rm -rf "${REMOTE_RELEASES:?}/$old"
  done
fi
REMOTE_EOF

log "发布完成：$REMOTE_ROOT -> $REMOTE_RELEASES/$TS"
