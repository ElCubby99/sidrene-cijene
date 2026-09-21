#!/usr/bin/env bash
# Pakira dodatak u ZIP spreman za WordPress.
# Uporaba: bash build.sh
set -euo pipefail

DODATAK="sidrene-cijene"
IZLAZ="${DODATAK}.zip"

if [ ! -d "$DODATAK" ]; then
  echo "Greška: mapa '$DODATAK' ne postoji." >&2
  exit 1
fi

# Sintaksa svih PHP datoteka, ako je php dostupan.
if command -v php >/dev/null 2>&1; then
  echo "Provjera sintakse…"
  find "$DODATAK" -name '*.php' -print0 | while IFS= read -r -d '' f; do
    php -l "$f" >/dev/null || { echo "Greška u: $f" >&2; exit 1; }
  done
  echo "  sintaksa OK"
else
  echo "Upozorenje: php nije dostupan, preskačem provjeru sintakse." >&2
fi

rm -f "$IZLAZ"
zip -r -q "$IZLAZ" "$DODATAK" \
  -x "*/.git/*" "*/.DS_Store" "*/node_modules/*" "*.map"

echo "Gotovo: $IZLAZ ($(du -h "$IZLAZ" | cut -f1))"
