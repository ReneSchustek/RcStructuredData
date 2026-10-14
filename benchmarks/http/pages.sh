#!/bin/bash
# Antwortzeiten der Seiten, auf denen die Erweiterung ihren Graphen baut: Startseite, eine Kategorie
# mit vielen Artikeln und eine Landingpage. Je 12 warme Aufrufe, Median in Millisekunden. Läuft auf dem
# Testsystem gegen eine ddev-Instanz.
#
# Ausgabe je Seite eine Zeile: Seite;Median_ms;HTTP-Status
# Aufruf: pages.sh <instanz>
set -u
INSTANCE=${1:?Aufruf: pages.sh <instanz>}
RUNS=12
BASE=https://$INSTANCE.ddev.site
JAR=$(mktemp)
trap 'rm -f "$JAR"' EXIT

cd "/workspace/shopware/instances/$INSTANCE" || exit 1
LANDING=$(ddev mysql -N -e "SELECT seo_path_info FROM seo_url WHERE route_name = 'frontend.landing.page' AND is_canonical = 1 AND is_deleted = 0 LIMIT 1" 2>/dev/null)

median_ms() { sort -n | awk '{v[NR]=$1} END {printf "%d", (v[int((NR+1)/2)] + v[int((NR+2)/2)]) / 2 * 1000}'; }

measure() {
  local page=$1 url=$2 samples
  curl -sk -c "$JAR" -b "$JAR" -o /dev/null "$url"
  samples=$(for _ in $(seq $RUNS); do curl -sk -c "$JAR" -b "$JAR" -o /dev/null -w "%{time_total} %{http_code}\n" "$url"; done)
  printf "%s;%s;%s\n" "$page" "$(echo "$samples" | awk '{print $1}' | median_ms)" "$(echo "$samples" | awk '{print $2}' | sort -u | paste -sd/)"
}

measure Startseite "$BASE/"
measure "Kategorie Bausätze" "$BASE/bausaetze/"
[ -n "$LANDING" ] && measure Landingpage "$BASE/$LANDING"
