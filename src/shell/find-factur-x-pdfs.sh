#!/bin/sh

set -eu

if ! command -v qpdf >/dev/null 2>&1; then
    printf '%s\n' 'Fehler: qpdf wurde nicht gefunden.' >&2
    exit 1
fi

search_dir=${1:-.}

if [ ! -d "$search_dir" ]; then
    printf 'Fehler: Ordner nicht gefunden: %s\n' "$search_dir" >&2
    exit 1
fi

matches=$(mktemp "${TMPDIR:-/tmp}/factur-x-pdfs.XXXXXX")
trap 'rm -f "$matches"' EXIT HUP INT TERM

find "$search_dir" -type f -iname '*.pdf' -exec sh -c '
    for pdf_file do
        if qpdf --list-attachments "$pdf_file" 2>/dev/null | grep -Fq "factur-x.xml"; then
            printf "%s\n" "$pdf_file" >> "$1"
        fi
    done
' sh "$matches" {} +

found=0
if [ -s "$matches" ]; then
    cat "$matches"
    found=1
fi

if [ "$found" -eq 0 ]; then
    printf '%s\n' 'Keine PDFs mit eingebettetem factur-x.xml gefunden.' >&2
fi
