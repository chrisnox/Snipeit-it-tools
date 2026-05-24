#!/bin/bash
# ============================================================
#  Author   :  Chris M.
#  IT-Tools — Container Entrypoint
#  Starts Apache and regenerates all runner files on every
#  container start to ensure bookmarks reflect latest config.
# ============================================================

apache2-foreground &
APACHE_PID=$!

echo "[IT-Tools] Waiting for Apache..."
for i in $(seq 1 15); do
    if curl -sf http://localhost/api/status > /dev/null 2>&1; then
        echo "[IT-Tools] Apache ready — generating runner files..."
        RESULT=$(curl -sf -X POST http://localhost/api/generate \
            -H "Content-Type: application/json" \
            -d '{"type":"all"}' 2>&1)
        echo "[IT-Tools] Generate: $RESULT"
        break
    fi
    sleep 1
done

wait $APACHE_PID
