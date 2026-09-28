#!/bin/sh

set -eu

if ! command -v qpdf >/dev/null 2>&1; then
    printf '%s\n' 'Fehler: qpdf wurde nicht gefunden.' >&2
    exit 1
fi

if [ "$#" -lt 1 ] || [ "$#" -gt 2 ]; then
    printf 'Verwendung: %s PDF-Datei [XML-Zieldatei]\n' "$0" >&2
    exit 1
fi

pdf_file=$1
output_file=${2:-}

if [ ! -f "$pdf_file" ]; then
    printf 'Fehler: PDF-Datei nicht gefunden: %s\n' "$pdf_file" >&2
    exit 1
fi

if ! qpdf --list-attachments "$pdf_file" 2>/dev/null | grep -Fq 'factur-x.xml'; then
    printf 'Fehler: Kein eingebettetes factur-x.xml gefunden: %s\n' "$pdf_file" >&2
    exit 1
fi

if [ -n "$output_file" ]; then
    qpdf --show-attachment=factur-x.xml "$pdf_file" > "$output_file"
    printf 'XML gespeichert: %s\n' "$output_file" >&2
else
    qpdf --show-attachment=factur-x.xml "$pdf_file"
fi
