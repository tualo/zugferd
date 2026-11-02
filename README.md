# ZUGFeRD

Dieses Paket erzeugt Rechnungs-XML und exportiert Factur-X/ZUGFeRD im
EN16931-Profil als PDF/A-3 mit eingebetteter XML. Der eigenständige XML-Export
behält sein XRechnung-Profil; dessen Geschäftsregelprüfung ist davon getrennt.

Es nutzt:

- PHP
- Composer
- easybill/zugferd-php
- Spatie/Browsershot für den PDF-Export
- Ghostscript für die PDF/A-3-Konvertierung mit sRGB-OutputIntent
- Mustang CLI für Factur-X-Metadaten, XML-Einbettung und Geschäftsregel-/PDF-Prüfung

## Voraussetzungen

- PHP 8.x
- Composer
- Chromium/Chrome für Browsershot
- Ghostscript und ein sRGB-ICC-Profil
- Java und eine lokal installierte Mustang-CLI-JAR (getestet: 2.26.0)
- PHP-Erweiterung DOM und aktiviertes `proc_open`

## Installation

### macOS

1. Installiere Homebrew, falls noch nicht vorhanden:

   ```bash
   /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
   ```

2. Installiere die benötigten Pakete:

   ```bash
   brew install php composer ghostscript
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
   sudo apt install -y php php-cli php-xml composer ghostscript default-jre
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

Die PDF-Route liefert ausschließlich einen erfolgreich geprüften Factur-X-Export:

```text
/zugferd/pdf/{type}/{tablename}/{template}/{id}
```

## Hinweis zur eingebetteten XML

Die eingebettete XML-Datei wird im PDF als Anhang mit folgendem Namen mitgeliefert:

```text
factur-x.xml
```

Ein einfacher XML-Anhang in einer normalen PDF genügt **nicht** für Factur-X.
Der Export prüft zuerst das XML mit Mustang, konvertiert die PDF mit Ghostscript,
fügt XML und Metadaten mit Mustang hinzu und validiert anschließend die gesamte PDF.
Bei fehlenden Werkzeugen, ungültigen Rechnungsdaten oder fehlerhafter PDF/A-Ausgabe
wird eine Exception ausgelöst; die Route meldet `success: false`.

## Konfiguration

In der Tualo-Konfiguration unter `[zugferd]` setzen:

```ini
[zugferd]
mustang_jar=/opt/mustang/Mustang-CLI-2.26.0.jar
icc_profile=/usr/share/color/icc/ghostscript/srgb.icc
java_binary=java
ghostscript_binary=gs
```

Pfade an die Installation anpassen. macOS/Ghostscript stellt das Profil z. B.
unter `/opt/homebrew/Cellar/ghostscript/<version>/share/ghostscript/iccprofiles/srgb.icc`
bereit. Die JAR separat installieren; das Paket lädt nichts herunter und verwendet
keinen Online-Validator. Der Serverprozess braucht Lesezugriff auf JAR und ICC
sowie Ausführungsrechte für Java/Ghostscript.

`Report::validate()` prüft EN16931-XML nun zusätzlich mit Mustang.
Die globale XSD-Prüfung allein erkennt keine fehlenden Geschäftsregel-Pflichtangaben.
Die PDF-Konvertierung ersetzt die Browser-PDF, kann deren Rendering verändern und
ist nicht für die Erhaltung digitaler Signaturen vorgesehen.

## Rechnungsdaten

Die Daten kommen aus `Report::get()` bzw. der Datenbankroutine `getReport`.
Verkäufer-Steuerkennungen werden aus `tax_registration` gelesen
(z. B. `VA` für USt-ID oder `FC` für Steuernummer). Adressen müssen in
`seller_information` und `buyer_information` korrekt geliefert werden.
Fehlende Pflichtwerte werden nicht erfunden. Mustang meldet die konkreten
Geschäftsregeln, die vor einem erfolgreichen Export erfüllt sein müssen.

Die PDF verwendet `urn:cen.eu:en16931:2017`, nicht das XRechnung-Profil.
`TaxCurrencyCode` wird nicht pauschal auf die Rechnungswährung gesetzt, da dies
einen zusätzlichen Steuerbetrag in Abrechnungswährung erfordern würde.

## Lokaler Integrationstest

```bash
php tests/FacturXTest.php /pfad/Mustang-CLI-2.26.0.jar \
    /pfad/srgb.icc /pfad/testquelle.pdf /tmp/factur-x-test.pdf
```

Der Test verwendet eine synthetische Rechnung, prüft XSD und Mustang,
weist den Export ohne Verkäufer-Steuerkennung zurück und validiert die fertige
PDF/A-3 mit eingebetteter XML. Die Quell-PDF bleibt unverändert. Das Ergebnis
ist eine **Testrechnung**, keine korrigierte reale Rechnung.

## Troubleshooting

- JAR/ICC oder Java/Ghostscript fehlen: Konfiguration korrigieren; kein PDF-Fallback
- Browsershot findet keinen Browser: `chrome_path` konfigurieren
- XML validiert nicht: Pflichtangaben und Geschäftsregeln aus der Fehlermeldung prüfen
- `Not a PDF/A-3` / `XMP Metadata not found`: einen alten qpdf-Export nicht als
  Factur-X verwenden; mit der neuen Pipeline erneut erzeugen

## Lizenz

MIT
