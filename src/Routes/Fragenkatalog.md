

## Fragenkatalog zur Bestätigung der Rechnungsdaten

**Betreff: Bitte um Bestätigung der Rechnungsangaben und Abrechnungsregeln**

Guten Tag,

damit wir die Rechnungsdaten korrekt verarbeiten und elektronische Rechnungen ordnungsgemäß erstellen können, bitten wir Sie, die folgenden Angaben zu prüfen und zu bestätigen. Bitte ergänzen oder korrigieren Sie die Antworten bei Bedarf.

### 1. Rechnungsformat und Übermittlung
1. Welches Rechnungsformat benötigen Sie: **XRechnung**, **Factur-X/ZUGFeRD** oder ein anderes Format?
2. Gibt es eine verbindliche Version oder ein bestimmtes Profil, das wir verwenden sollen?
3. Auf welchem Weg sollen Rechnungen eingereicht werden – etwa per E-Mail, über ein Portal oder per Peppol?
4. Benötigen Sie eine Leitweg-ID, Bestellnummer oder ein anderes Pflichtmerkmal für die Zuordnung?

### 2. Rechnungs- und Referenzdaten
5. Sind Rechnungsnummer und Rechnungsdatum die maßgeblichen Angaben für Ihre Buchhaltung?
6. Welche Kundenreferenz, Bestellnummer, Auftragsnummer oder Kostenstelle muss auf der Rechnung stehen?
7. Muss die Rechnung zusätzlich auf eine bestimmte Person, Abteilung oder Organisationseinheit ausgestellt werden?
8. Gibt es Vorgaben zur Angabe von Leistungszeitraum oder Leistungsdatum?

### 3. Rechnungssteller und Rechnungsempfänger
9. Sind Name, Anschrift, Land und Umsatzsteuer-ID des Rechnungsstellers korrekt hinterlegt?
10. Welche vollständige Firmierung und Rechnungsanschrift des Rechnungsempfängers sollen verwendet werden?
11. Soll die Umsatzsteuer-ID des Rechnungsempfängers auf der Rechnung angegeben werden?
12. Gibt es eine abweichende Liefer-, Leistungs- oder Rechnungsadresse?

### 4. Rechnungspositionen
13. Welche Bezeichnung soll für jede Position auf der Rechnung erscheinen?
14. Sind die Artikel- oder Leistungsnummern für Ihre Buchhaltung ausreichend, oder ist eine zusätzliche Beschreibung erforderlich?
15. Welche Mengeneinheiten sollen verwendet werden – zum Beispiel Stück, Stunden oder Kilometer?
16. Sind Menge und Einzelpreis jeweils **netto** angegeben?
17. Wie sollen kostenfreie Positionen oder Positionen mit Menge, aber ohne Berechnungsbetrag dargestellt werden?
18. Benötigen Sie Positionsreferenzen oder eindeutige Positionsnummern, die zu Bestellung oder Lieferschein passen?

### 5. Steuer und Beträge
19. Welcher Umsatzsteuersatz gilt für die einzelnen Positionen?
20. Gibt es Positionen, die steuerfrei, ermäßigt besteuert oder nach dem Reverse-Charge-Verfahren abzurechnen sind?
21. Sind Rabatte, Zuschläge oder sonstige Nachlässe zu berücksichtigen?
22. Sollen wir Rechnungen grundsätzlich mit Nettobeträgen zuzüglich Umsatzsteuer oder mit Bruttobeträgen ausweisen?
23. Welcher Gesamtbetrag ist für die Zahlung maßgeblich: der Nettobetrag oder der Bruttobetrag einschließlich Umsatzsteuer?

### 6. Zahlung und Kontakt
24. Welche Zahlungsfrist gilt?
25. Sollen Zahlungsbedingungen, Skonto oder ein konkretes Fälligkeitsdatum angegeben werden?
26. Welche Bankverbindung und welcher Zahlungsempfänger sollen auf der Rechnung stehen?
27. An welche E-Mail-Adresse sollen Rechnungen und Rückfragen zur Rechnung gesendet werden?

### 7. Prüfung der Beispieldaten
Bitte prüfen Sie außerdem die Beispieldaten in der `XML-Beispieldatei`, insbesondere:

- Die Positionsbezeichnungen sind leer. Welche Bezeichnungen sollen stattdessen verwendet werden?
- Bei allen Positionen ist dieselbe Positionsnummer eingetragen. Sollen die Positionen unterschiedliche, fortlaufende Nummern erhalten?
- Für eine Position sind **15.000 Stück** mit einem Einzelpreis von **0,00 EUR** angegeben. Ist das korrekt, und soll diese Position überhaupt auf der Rechnung erscheinen?
- Die ausgewiesene Umsatzsteuer beträgt rechnerisch **269,65 EUR** bei **1.419,20 EUR netto** und 19 % Umsatzsteuer. Im Gesamtbetrag wird die Umsatzsteuer jedoch mit **0,00 EUR** ausgewiesen und der Zahlbetrag entspricht dem Nettobetrag. Welcher Steuer- und Zahlbetrag ist korrekt?
- Bitte bestätigen Sie, ob die angegebene Kundenreferenz ausreichend ist und ob zusätzlich eine Bestellnummer oder Leitweg-ID erforderlich ist.
- Bitte bestätigen Sie die vollständige Anschrift des Rechnungsempfängers; in der Beispieldatei sind Ort und Anschrift nicht eindeutig auf getrennte Felder verteilt.

### 8. Bisheriger technischer und organisatorischer Stand
28. Welche Buchhaltungs- oder ERP-Software verwenden Sie derzeit (Hersteller und Produktname)?
29. Welche Version bzw. welcher Release-Stand der Software ist bei Ihnen installiert?
30. Wird die Software lokal, in einer Cloud oder über einen externen Dienstleister betrieben?
31. Welche Versionen und Profile von **XRechnung** bzw. **ZUGFeRD/Factur-X** können Sie derzeit empfangen und verarbeiten?
32. Seit wann ist der elektronische Rechnungseingang bei Ihnen produktiv im Einsatz, und gibt es einen geplanten Umstellungs- oder Aktualisierungstermin?
33. Wie werden Rechnungen aktuell entgegengenommen und in die Buchhaltung übernommen (z. B. E-Mail, Peppol, Portal, automatisierter Import oder manuelle Erfassung)?
34. Nutzen Sie für die Verarbeitung eine Schnittstelle oder einen Dienstleister? Falls ja, welche Lösung und welche Version?
35. Gibt es bereits definierte Prüfungen oder Validierungsregeln für eingehende E-Rechnungen? Falls ja, welche Validatoren bzw. Regelversionen verwenden Sie?
36. Gibt es technische oder organisatorische Einschränkungen, bekannte Fehler oder Pflichtangaben, die in der aktuellen Lösung besonders zu beachten sind?
37. Können Sie uns eine anonymisierte, bei Ihnen erfolgreich verarbeitete Beispielrechnung sowie gegebenenfalls eine aktuelle Format- oder Schnittstellenspezifikation zur Verfügung stellen?
38. Wer ist bei Ihnen fachlicher und technischer Ansprechpartner für Rückfragen zur Rechnungsverarbeitung?

Vielen Dank für Ihre Prüfung. Wir bitten Sie, die zutreffenden Angaben zu bestätigen und notwendige Korrekturen oder Ergänzungen mitzuteilen.