#!/usr/bin/env bash
# 后端（server，webman + docker）线上发布脚本
#
# 部署方式：本地语法自检 -> 打包上传（不含 vendor/runtime/.env）-> 服务器端解压合并
#   -> composer install -> docker compose build/up -> 重启容器使新代码生效 -> 健康检查
#
# 红线（务必遵守，详见 AGENTS.md）：
#   - 绝不 DROP/CREATE DATABASE，绝不导入备份 SQL，绝不 TRUNCATE。
#   - 本脚本完全不触碰数据库、不触碰 .env（线上密钥），数据库结构变更走 sql/version/ 迁移脚本单独执行。
#   - 部署前会自动把线上当前的完整目录（含 vendor，不含 runtime）打包备份，可用 rollback 快速回退。
#
# 用法：
#   sh/deploy-backend.sh              同步代码并重启服务
#   sh/deploy-backend.sh rollback     回滚到上一次部署前的备份（不重新构建）
#
# 可通过环境变量覆盖默认配置，例如：
#   SSH_USER=deploy sh/deploy-backend.sh
set -euo pipefail

# ------------------------- 配置 -------------------------
SERVER_IP="${SERVER_IP:-59.110.52.164}"
SSH_USER="${SSH_USER:-root}"
SSH_PORT="${SSH_PORT:-22}"
REMOTE_DIR="${REMOTE_DIR:-/home/server}"
REMOTE_BACKUPS="${REMOTE_BACKUPS:-/home/server_backups}"
KEEP_BACKUPS="${KEEP_BACKUPS:-5}"
CONTAINER_NAME="${CONTAINER_NAME:-docker-webman}"
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1:8787/health}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SERVER_DIR="$REPO_ROOT/server"

SSH_OPTS=(-p "$SSH_PORT" -o StrictHostKeyChecking=accept-new)
SSH_TARGET="$SSH_USER@$SERVER_IP"

log() { echo "[deploy-backend] $*"; }

# ------------------------- 远程健康检查（本地/远程共用逻辑，内嵌进 heredoc）-------------------------
# 见下方 REMOTE_EOF 内的 health_check 函数

# ------------------------- 回滚 -------------------------
rollback() {
  log "开始回滚 $REMOTE_DIR 到上一次部署前的备份 ..."
  ssh "${SSH_OPTS[@]}" "$SSH_TARGET" bash -s -- "$REMOTE_DIR" "$REMOTE_BACKUPS" "$CONTAINER_NAME" "$HEALTH_URL" <<'REMOTE_EOF'
set -euo pipefail
REMOTE_DIR="$1"
REMOTE_BACKUPS="$2"
CONTAINER_NAME="$3"
HEALTH_URL="$4"

latest="$(find "$REMOTE_BACKUPS" -maxdepth 1 -name '*.tar.gz' -printf '%f\n' 2>/dev/null | sort | tail -n1)"
if [ -z "$latest" ]; then
  echo "错误：$REMOTE_BACKUPS 下没有可用备份，无法回滚" >&2
  exit 1
fi
echo "使用备份：$REMOTE_BACKUPS/$latest"

# 回滚只还原代码与 vendor，不动 runtime（pid/日志）与 .env（线上密钥）
tar --warning=no-unknown-keyword -xzf "$REMOTE_BACKUPS/$latest" -C "$REMOTE_DIR"

cd "$REMOTE_DIR"
docker compose up -d
docker restart "$CONTAINER_NAME"

echo "等待服务重启 ..."
ok=0
for i in $(seq 1 15); do
  sleep 1
  code="$(curl -s -o /dev/null -w '%{http_code}' --noproxy '*' "$HEALTH_URL" || true)"
  if [ "$code" = "200" ]; then
    ok=1
    break
  fi
done
if [ "$ok" != "1" ]; then
  echo "警告：回滚后健康检查未通过（${HEALTH_URL}），请手动登录服务器排查" >&2
  exit 1
fi
echo "回滚完成，健康检查通过"
REMOTE_EOF
  log "回滚完成"
}

if [ "${1:-}" = "rollback" ]; then
  rollback
  exit 0
fi

# ------------------------- 本地自检 -------------------------
log "本地 PHP 语法自检 ..."
if command -v php >/dev/null 2>&1; then
  find "$SERVER_DIR/app" "$SERVER_DIR/config" "$SERVER_DIR/support" "$SERVER_DIR/scripts" \
    -name '*.php' -print0 2>/dev/null | xargs -0 -n1 php -l >/tmp/deploy-backend-lint.log 2>&1 || {
      grep -v '^No syntax errors detected' /tmp/deploy-backend-lint.log || true
      echo "错误：本地 PHP 语法检查未通过，见上方输出" >&2
      exit 1
    }
else
  log "警告：本机没有 php 命令，跳过语法自检"
fi

# ------------------------- 打包 -------------------------
TS="$(date +%Y%m%d%H%M%S)"
TAR_NAME="server-${TS}.tar.gz"
TMP_TAR="/tmp/${TAR_NAME}"

log "打包 server/ 代码（不含 vendor/runtime/.env）-> ${TMP_TAR}"
# macOS tar 默认会把扩展属性/资源分叉（AppleDouble、com.apple.provenance 等）写进 tar 包，
# 这些垃圾数据会污染服务器上的代码文件，必须禁用（COPYFILE_DISABLE）并排除对应文件。
COPYFILE_DISABLE=1 tar -czf "$TMP_TAR" -C "$SERVER_DIR" \
  --exclude='vendor' \
  --exclude='runtime' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='.git' \
  --exclude='*.log' \
  --exclude='tests/tmp' \
  --exclude='.DS_Store' \
  --exclude='._*' \
  .

# ------------------------- 上传 -------------------------
log "上传到服务器 ${SSH_TARGET}:/tmp/${TAR_NAME}"
scp -P "$SSH_PORT" -o StrictHostKeyChecking=accept-new "$TMP_TAR" "$SSH_TARGET:/tmp/${TAR_NAME}"
rm -f "$TMP_TAR"

# ------------------------- 远程发布 -------------------------
log "服务器端备份、合并代码、安装依赖、重启容器 ..."
ssh "${SSH_OPTS[@]}" "$SSH_TARGET" bash -s -- \
  "$REMOTE_DIR" "$REMOTE_BACKUPS" "$KEEP_BACKUPS" "$TS" "$TAR_NAME" "$CONTAINER_NAME" "$HEALTH_URL" <<'REMOTE_EOF'
set -euo pipefail
REMOTE_DIR="$1"
REMOTE_BACKUPS="$2"
KEEP_BACKUPS="$3"
TS="$4"
TAR_NAME="$5"
CONTAINER_NAME="$6"
HEALTH_URL="$7"

if [ ! -d "$REMOTE_DIR" ]; then
  echo "错误：$REMOTE_DIR 不存在，请先手动完成首次部署（含 .env 配置）" >&2
  exit 1
fi

mkdir -p "$REMOTE_BACKUPS"

# 备份当前完整代码（含 vendor 便于原样回滚，不含 runtime 避免带上 pid/日志）
echo "备份当前版本 -> $REMOTE_BACKUPS/${TS}.tar.gz"
tar --warning=no-unknown-keyword -czf "$REMOTE_BACKUPS/${TS}.tar.gz" -C "$REMOTE_DIR" --exclude='runtime' .

# 提前记录 Dockerfile / docker-compose.yml 的哈希，覆盖后对比，只有变化时才需要重新 build 镜像
old_image_hash="$(cat "$REMOTE_DIR/Dockerfile" "$REMOTE_DIR/docker-compose.yml" 2>/dev/null | md5sum | awk '{print $1}')"

# 解压新代码覆盖到现有目录：只新增/覆盖同名文件，不删除多余文件（不加 --delete，规避误删）
mkdir -p "/tmp/server-extract-${TS}"
tar --warning=no-unknown-keyword -xzf "/tmp/${TAR_NAME}" -C "/tmp/server-extract-${TS}"
rm -f "/tmp/${TAR_NAME}"
cp -a "/tmp/server-extract-${TS}/." "$REMOTE_DIR/"
rm -rf "/tmp/server-extract-${TS}"

cd "$REMOTE_DIR"

echo "安装 composer 依赖 ..."
# 宿主机 PHP 是 8.2，但实际运行时是容器内的 PHP 8.4（本项目 composer.json 要求 php >=8.3/8.4），
# 依赖本身是纯 PHP 包，不受宿主机 PHP 版本影响，故加 --ignore-platform-reqs 跳过宿主机平台校验。
composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

new_image_hash="$(cat "$REMOTE_DIR/Dockerfile" "$REMOTE_DIR/docker-compose.yml" 2>/dev/null | md5sum | awk '{print $1}')"
if [ "$old_image_hash" != "$new_image_hash" ]; then
  echo "检测到 Dockerfile / docker-compose.yml 变化，重新构建镜像 ..."
  docker compose build
else
  echo "Dockerfile / docker-compose.yml 未变化，跳过 docker compose build"
fi
docker compose up -d

echo "重启容器使新代码生效：$CONTAINER_NAME"
docker restart "$CONTAINER_NAME"

echo "等待服务启动并做健康检查 ..."
ok=0
for i in $(seq 1 15); do
  sleep 1
  code="$(curl -s -o /dev/null -w '%{http_code}' --noproxy '*' "$HEALTH_URL" || true)"
  if [ "$code" = "200" ]; then
    ok=1
    break
  fi
done
if [ "$ok" != "1" ]; then
  echo "错误：健康检查未通过（${HEALTH_URL}），请检查 docker logs ${CONTAINER_NAME}，必要时执行 rollback" >&2
  exit 1
fi
echo "健康检查通过"

# 清理旧备份，只保留最近 KEEP_BACKUPS 份
mapfile -t backups < <(find "$REMOTE_BACKUPS" -maxdepth 1 -name '*.tar.gz' -printf '%f\n' | sort)
total="${#backups[@]}"
if [ "$total" -gt "$KEEP_BACKUPS" ]; then
  to_remove=$(( total - KEEP_BACKUPS ))
  for i in $(seq 0 $((to_remove - 1))); do
    old="${backups[$i]}"
    echo "清理旧备份：$old"
    rm -f "${REMOTE_BACKUPS:?}/$old"
  done
fi
REMOTE_EOF

log "发布完成：${REMOTE_DIR}（备份：${REMOTE_BACKUPS}/${TS}.tar.gz）"
