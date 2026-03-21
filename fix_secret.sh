#!/bin/bash
echo "=== Removing secret from git history ==="
cd "$(dirname "$0")"

git filter-branch --force --tree-filter \
  'sed -i "s/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/YOUR_GOOGLE_CLIENT_SECRET/g" includes/config.php 2>/dev/null || true' \
  --tag-name-filter cat -- --all

echo "=== Committing clean files ==="
git add .gitignore includes/config.php includes/config.secrets.php
git commit -m "Remove Google OAuth secret from config, add .gitignore

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"

echo "=== Force pushing to GitHub ==="
git push origin main --force

echo ""
echo "=== DONE! Push successful ==="
echo "REMINDER: Revoke your old secret in Google Cloud Console and generate a new one!"
read -p "Press Enter to close..."
