# ZUGFeRD

Dieses Paket erzeugt ZUGFeRD-/XRechnung-XML im Faktur-X-Format und kann das XML als eingebetteten Anhang in das erzeugte PDF schreiben.

Es nutzt:

- PHP
- Composer
- easybill/zugferd-php
- Spatie/Browsershot für den PDF-Export
- qpdf für das Einbetten der XML-Datei in das PDF

## Voraussetzungen

- PHP 8.x
- Composer
- Chromium/Chrome für Browsershot
- qpdf

## Installation

### macOS

1. Installiere Homebrew, falls noch nicht vorhanden:

   ```bash
   /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
   ```

2. Installiere die benötigten Pakete:

   ```bash
   brew install php composer qpdf
   ```

3. Im Projektverzeichnis die Abhängigkeiten installieren:

   ```bash
   cd /Users/thomashoffmann/Documents/Projects/php/tualo/zugferd
   composer install
   ```

4. Falls Chrome/Chromium fehlt, installiere es ebenfalls:

   ```bash
   brew install --cask google-chrome
   ```

5. Optional: BrowserShot-Chemistry prüfen:

   ```bash
   which google-chrome || which chromium || which chromium-browser
   ```

### Debian / Ubuntu

1. Pakete installieren:

   ```bash
   sudo apt update
   sudo apt install -y php php-cli php-xml composer qpdf
   ```

2. Wenn ein Browser für Browsershot benötigt wird, z. B. Chromium:

   ```bash
   sudo apt install -y chromium
   ```

3. Im Projektverzeichnis die Abhängigkeiten installieren:

   ```bash
   cd /path/to/zugferd
   composer install
   ```

4. Wenn Browsershot den Browser nicht findet, ggf. Pfad setzen, z. B. in der App-Konfiguration:

   ```php
   'chrome_path' => '/usr/bin/chromium'
   ```

## Nutzung

Die Rechnung wird über den XML-Generator erzeugt und kann anschließend als PDF mit eingebetteter XML ausgegeben werden.

Beispiel:

```php
$xml = \Tualo\Office\Zugferd\Report::get('rechnung', 262099);
```

Die PDF-Route liefert eine PDF-Datei mit eingebetteter XML, wenn qpdf vorhanden ist:

```text
/zugferd/pdf/{type}/{tablename}/{template}/{id}
```

## Hinweis zur eingebetteten XML

Die eingebettete XML-Datei wird im PDF als Anhang mit folgendem Namen mitgeliefert:

```text
factur-x.xml
```

Damit entspricht das PDF dem üblichen Factur-X-/ZUGFeRD-Format für digitale Rechnungen.

## Troubleshooting

- qpdf fehlt: PDF wird ohne eingebettete XML erzeugt
- Browsershot findet keinen Browser: `chrome_path` konfigurieren
- XML validiert nicht: auf fehlende Felder oder falsche Datentypen prüfen

## Lizenz

MIT
