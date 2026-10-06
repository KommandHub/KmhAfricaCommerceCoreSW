# 0.1.0

Erstveröffentlichung.

- Referenzdaten-Import, aus der Plugin-Konfiguration („Referenzdaten-Import ausführen") oder mit `bin/console africa:reference:import`: aktiviert 26 afrikanische Länder, legt 443 ISO-3166-2-Regionen/Bundesländer an und setzt die ISO-4217-Nachkommastellen für 18 afrikanische Währungen (XOF, XAF, RWF, UGX u. a. mit 0 Nachkommastellen, TND mit 3).
- Adressregeln je Land als Daten: Ghana verlangt keine Postleitzahl mehr, Nigeria zeigt bei der Registrierung das Bundesland-Feld.
- Der Import kann gefahrlos wiederholt werden: Ein zweiter Lauf ändert nichts. Nicht angelegte Währungen werden als „Fehlend" aufgeführt und nie erstellt, weil eine Währung deinen echten Umrechnungsfaktor braucht; lege sie unter Einstellungen > Währungen an und führe den Import erneut aus.
- Feld „Manuell überschrieben" an Ländern, Währungen, Bundesländern und Bezirken: Ist es gesetzt, ändert der Import diesen Datensatz nie.
- Optionale lokale Bezirke unterhalb des Bundeslands (z. B. nigerianische LGAs und Wards) mit kaskadierender Auswahl „Verwaltungsbezirk" in den Adressformularen der Storefront. Enthält ein Beispiel für Lagos.
- Optionale zusätzliche Adressfelder in Registrierung und Kundenkonto: nächstgelegener Orientierungspunkt, Gebiet/Viertel, Wegbeschreibung, digitaler Adresscode und lokaler Bezirk. Gespeicherte Werte bleiben erhalten, wenn ein Client die Adresse bearbeitet, ohne diese Felder zu senden.
- Optionale Live-Prüfung der Telefonnummer in den Adressformularen: Die Nummer wird im internationalen Format angezeigt, oder es erscheint ein Hinweis, wenn sie ungültig wirkt. Die Bestellung wird nie blockiert.
- Store-API-Endpunkte für Headless-Storefronts: `GET /store-api/kmh-af/divisions/{countryIso}` und `POST /store-api/kmh-af/phone/normalize`.
- Jede Funktion hat in der Plugin-Konfiguration einen eigenen Schalter, je Verkaufskanal.
- Einstellung „Länderregeln" für die erwartete Bezirkstiefe; der Adressvalidator schlägt danach die Auswahl eines Bezirks vor.
- Optionale Debug-Protokollierung, je Verkaufskanal.
- Deinstallation ohne Datenerhalt entfernt die eigenen Tabellen und Felder des Plugins; Länder, Bundesländer und Währungen bleiben bestehen, bestehende Bestellungen und Adressen sind nicht betroffen.
- Storefront auf Englisch, Deutsch und Französisch; Administration auf Englisch und Deutsch. Kompatibel mit Shopware 6.6 und 6.7.
