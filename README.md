# Schema.org CMS Erweiterung (RcStructuredData)

Dieses Plugin sorgt dafür, dass Ihre **Kategorie- und Landingpages** von Google und anderen
Suchmaschinen besser verstanden werden. Es fügt den Seiten unsichtbare, maschinenlesbare
Zusatzinformationen im Format **Schema.org / JSON-LD** hinzu – die Grundlage für die
„aufgehübschten" Suchergebnisse (Rich Results) mit Aufklapp-FAQ, Video-Vorschau,
Breadcrumb-Pfad und Shop-Informationen.

Sie müssen dafür **kein rohes JSON und keinen Code** anfassen: Alle Angaben werden bequem im
Shopware-Administrationsbereich gepflegt.

---

## Inhalt

- [Was bringt mir das? (in einfachen Worten)](#was-bringt-mir-das-in-einfachen-worten)
- [Was macht das Plugin konkret?](#was-macht-das-plugin-konkret)
- [Voraussetzungen](#voraussetzungen)
- [Installation](#installation)
- [Einrichtung (Schritt für Schritt)](#einrichtung-schritt-für-schritt)
- [Inhalte pro Seite pflegen](#inhalte-pro-seite-pflegen)
- [Prüfen, ob alles funktioniert](#prüfen-ob-alles-funktioniert)
- [Häufige Fragen und Fehlerbehebung](#häufige-fragen-und-fehlerbehebung)
- [Hinweise für Updates und Deployment](#hinweise-für-updates-und-deployment)
- [Für Entwickler (technischer Anhang)](#für-entwickler-technischer-anhang)
- [Datenschutz](#datenschutz)
- [Support und Lizenz](#support-und-lizenz)

---

## Was bringt mir das? (in einfachen Worten)

Suchmaschinen lesen Ihre Website nicht wie ein Mensch. Damit Google zum Beispiel weiß, dass ein
bestimmter Textblock eine **Frage-Antwort-Liste (FAQ)** ist oder dass auf der Seite ein **Video**
liegt, braucht es strukturierte Daten – eine Art unsichtbares Etikett im Quelltext der Seite.

Sind diese Etiketten sauber gesetzt, kann Google Ihre Seite in den Suchergebnissen ansprechender
darstellen, etwa mit:

- **Aufklappbaren FAQ** direkt im Suchergebnis,
- einer **Video-Vorschau** mit Laufzeit,
- dem **Navigationspfad** (z. B. „Startseite › Bauteile › Fittings"),
- **Firmen- und Website-Angaben** (Name, Logo, Social-Media-Profile, Suchfeld).

Das kann die Sichtbarkeit erhöhen und mehr Besucher auf Ihre Seiten bringen. Das Plugin nimmt
Ihnen die technische Arbeit ab und erzeugt diese Etiketten automatisch aus den Inhalten, die Sie
ohnehin pflegen.

> **Wichtig:** Strukturierte Daten sind eine Empfehlung an Google, keine Garantie. Ob und wie ein
> Rich Result angezeigt wird, entscheidet Google. Das Plugin stellt die technische Grundlage
> korrekt bereit.

---

## Was macht das Plugin konkret?

Auf jeder Kategorie- und Landingpage erzeugt das Plugin **einen einzigen, zusammenhängenden
Datenblock** (`@graph`) mit folgenden Bausteinen – je nachdem, was auf der Seite vorhanden ist:

| Baustein | Bedeutung in einfachen Worten |
|----------|-------------------------------|
| **CollectionPage** | „Das ist eine Kategorie-/Übersichtsseite" (bewusst kein Produkt-Etikett). |
| **WebPage** | „Das ist eine Landingpage/Inhaltsseite." |
| **BreadcrumbList** | Der Navigationspfad der Seite, automatisch aus der Shop-Navigation. |
| **Organization** | Ihre Firmenangaben (Name, Logo, Social-Media-Profile). |
| **WebSite** | Angaben zur Website inkl. optionalem Suchfeld für Google. |
| **FAQPage** | Frage-Antwort-Listen aus dem FAQ-Block (siehe unten). |
| **VideoObject** | Videoangaben aus einem YouTube-Video-Block der Seite. |

Alle Bausteine sind sauber miteinander verknüpft, und die von Shopware selbst erzeugten,
getrennten Schema-Angaben werden auf diesen Seiten **ersetzt** (kein doppeltes Schema).

---

## Voraussetzungen

- **Shopware 6.7 oder 6.8**
- **PHP 8.2 oder neuer**
- Zugang zum Shopware-Administrationsbereich (Administrator-Rechte)

Optional (nur für die automatische Video-Anreicherung):

- Ein **YouTube-Data-API-Schlüssel** von Google (kostenlos über die
  [Google Cloud Console](https://console.cloud.google.com/) erhältlich).

---

## Installation

### Variante A – über den Administrationsbereich (empfohlen)

1. **Einstellungen → System → Plugins** öffnen.
2. Oben rechts auf **Plugin hochladen** klicken und die ZIP-Datei des Plugins auswählen.
3. Beim Plugin **RcStructuredData** auf **Installieren** und danach auf **Aktivieren** klicken.
4. Wenn Ihr Theme neu gebaut werden muss, meldet Shopware das – folgen Sie dem Hinweis bzw.
   informieren Sie Ihren Techniker (siehe [Updates und Deployment](#hinweise-für-updates-und-deployment)).

### Variante B – über die Kommandozeile (für Techniker)

```bash
bin/console plugin:refresh
bin/console plugin:install --activate RcStructuredData
bin/console cache:clear
```

Nach der Installation stehen die Konfiguration und die neuen Pflegefelder sofort zur Verfügung.

---

## Einrichtung (Schritt für Schritt)

Öffnen Sie **Einstellungen → System → Plugins**, klicken Sie beim Plugin auf die drei Punkte und
dann auf **Konfiguration**. Sie finden dort mehrere Karten:

### 1. Organisation

Angaben zu Ihrem Unternehmen. Werden von Google für die Firmendarstellung genutzt.

- **Name der Organisation** – Firmenname. Bleibt das Feld leer, wird automatisch der Shop-Name
  verwendet.
- **Logo-URL** – vollständige Internetadresse zu Ihrem Logo (z. B.
  `https://ihr-shop.de/media/logo.png`).
- **Social-Profile** – eine Profil-Adresse pro Zeile (z. B. Facebook, Instagram, LinkedIn).

### 2. Website

- **Name der Website** – meist identisch mit dem Shop-Namen. Leer = Shop-Name.
- **Such-URL-Vorlage** – optional. Ermöglicht Google, ein Suchfeld für Ihren Shop anzuzeigen.
  Die Adresse muss den Platzhalter `{search_term_string}` enthalten, z. B.
  `https://ihr-shop.de/search?search={search_term_string}`.

### 3. YouTube-Data-API (optional)

- **YouTube-Data-API-Schlüssel** – wenn Sie hier einen Schlüssel eintragen, holt das Plugin
  fehlende Videoangaben (Titel, Beschreibung, Veröffentlichungsdatum, Laufzeit) automatisch von
  YouTube. Ohne Schlüssel bleibt alles wie gewohnt – die Angaben pflegen Sie dann selbst.
  Der Schlüssel wird sicher gespeichert und niemals im Seitenquelltext oder in Protokollen
  angezeigt.

### 4. Diagnose

- **Vollständigkeits-Diagnose als HTML-Kommentar ausgeben** – ein Hilfsschalter für die
  Fehlersuche. Ist er aktiv, listet das Plugin fehlende empfohlene Felder als (für Besucher
  unsichtbaren) Kommentar im Quelltext auf. **Bitte im Live-Betrieb ausgeschaltet lassen** und nur
  vorübergehend zum Prüfen aktivieren.

Nach Änderungen an der Konfiguration ggf. den Cache leeren (Shopware weist darauf hin).

---

## Inhalte pro Seite pflegen

Zusätzlich zur globalen Konfiguration können Sie **je Kategorie oder Landingpage** Angaben
hinterlegen. Öffnen Sie dazu die Kategorie bzw. Landingpage im Admin.

### Zusätzliche Schema-Felder (Karte „Schema.org strukturierte Daten")

In den Kategorie-/Landingpage-Einstellungen finden Sie eine eigene Karte mit:

- **Schema-Beschreibung (Override)** – eine optionale Klartext-Beschreibung der Seite. Bleibt sie
  leer, wird die normale Kategoriebeschreibung verwendet.
- **Zusätzliche Eigenschaften** – frei definierbare **Name/Wert-Paare** (z. B. „Material" /
  „Edelstahl"). Über **Eigenschaft hinzufügen** legen Sie beliebig viele Zeilen an; mit
  **Entfernen** löschen Sie eine Zeile wieder.
- **Video-Fallback-Felder** (Titel, Beschreibung, Upload-Datum, Dauer) – dienen als Rückfallebene
  für Videos auf dieser Seite, falls direkt am Video-Block nichts gepflegt ist.

### FAQ auf einer Seite anzeigen (Block „FAQ (strukturierte Daten)")

1. Öffnen Sie das Erlebniswelten-/Layout-Design der Seite.
2. Fügen Sie das Element **„FAQ (strukturierte Daten)"** ein (Kategorie „Text").
3. Tragen Sie beliebig viele **Frage/Antwort-Paare** ein.

Das Element zeigt im Shop ein aufklappbares FAQ (barrierearm über `<details>`/`<summary>`) **und**
liefert gleichzeitig die FAQ-Daten für Google – beides aus einer Quelle, sodass Anzeige und
Schema garantiert übereinstimmen.

### Video-Angaben pflegen (Standard-Element „YouTube-Video")

Das Plugin erweitert das normale YouTube-Video-Element um vier Schema-Felder:

- **Video-Titel (Schema)**
- **Video-Beschreibung (Schema)**
- **Upload-Datum** im Format `JJJJ-MM-TT` (z. B. `2024-05-01`)
- **Dauer** im ISO-8601-Format (z. B. `PT5M30S` für 5 Minuten 30 Sekunden)

Fehlende Felder werden – falls konfiguriert – automatisch ergänzt. Die Reihenfolge, in der die
Angaben gezogen werden (die erste ausgefüllte Stufe gewinnt):

1. **direkt am Video-Block** (die vier Felder oben),
2. **Video-Fallback-Felder der Seite** (Kategorie/Landingpage),
3. **YouTube-Data-API** (nur bei hinterlegtem Schlüssel),
4. **automatische Werte** (Seitentitel bzw. Vorschaubild); Upload-Datum und Dauer bleiben sonst leer.

Das Vorschaubild stammt aus dem gepflegten Vorschaumedium des Video-Blocks; ist keines gesetzt,
wird das YouTube-Standardvorschaubild verwendet.

---

## Prüfen, ob alles funktioniert

- **Google Rich Results Test:** Rufen Sie <https://search.google.com/test/rich-results> auf und
  geben Sie die Adresse einer Kategorie-/Landingpage ein. Google zeigt an, welche strukturierten
  Daten erkannt wurden und ob es Fehler oder Warnungen gibt.
- **Diagnose-Schalter:** Aktivieren Sie den Diagnose-Schalter (siehe Einrichtung) vorübergehend.
  Fehlende empfohlene Felder erscheinen dann als Kommentar im Seitenquelltext.
- **Kommandozeile (für Techniker):**

  ```bash
  bin/console rc-schema:diagnose "Name oder ID der Kategorie"
  ```

  Der Befehl baut den Datenblock der Kategorie nach und listet je Baustein die fehlenden Felder auf.

---

## Häufige Fragen und Fehlerbehebung

**Ich sehe im Seitenquelltext kein Schema.**
Das Plugin behandelt nur **Kategorie- und Landingpages**. Auf der Startseite und auf
Produktdetailseiten bleibt das Standardverhalten von Shopware erhalten. Prüfen Sie außerdem, ob
der Cache geleert wurde.

**Google zeigt eine Warnung „fehlende Felder" beim Video.**
Google verlangt für Videos u. a. Titel, Beschreibung, Upload-Datum und (empfohlen) die Laufzeit.
Pflegen Sie diese am Video-Block oder in den Video-Fallback-Feldern der Seite – oder hinterlegen
Sie einen YouTube-API-Schlüssel, damit sie automatisch geholt werden. Der Diagnose-Befehl zeigt
Ihnen genau, was fehlt.

**Wird doppeltes Schema ausgegeben?**
Nein. Auf den behandelten Seiten ersetzt das Plugin die getrennte Standard-Ausgabe von Shopware
durch einen einzigen zusammenhängenden Datenblock.

**Die FAQ werden im Shop angezeigt, aber nicht als Schema erkannt.**
Verwenden Sie das mitgelieferte Element **„FAQ (strukturierte Daten)"**. Beliebige Text-Blöcke
werden bewusst **nicht** ausgelesen, weil sich Schema und sichtbarer Inhalt sonst widersprechen
könnten.

**Muss ich nach Änderungen etwas neu bauen?**
Reine Inhalts- und Konfigurationsänderungen wirken nach dem Leeren des Caches. Nach einem
Plugin-Update kann ein Theme-/Administrations-Build nötig sein (Ihr Techniker weiß, was zu tun
ist; siehe nächster Abschnitt).

---

## Hinweise für Updates und Deployment

Nach einem Update des Plugins auf dem Server:

1. Plugin aktualisieren: **Einstellungen → System → Plugins → Aktualisieren** (oder
   `bin/console plugin:update RcStructuredData`).
2. Cache leeren: `bin/console cache:clear`.
3. Falls Administrations- oder Storefront-Code geändert wurde, den entsprechenden Build ausführen
   (`bin/build-administration.sh` bzw. `bin/build-storefront.sh`). Der jeweils nötige Schritt steht
   im [CHANGELOG](CHANGELOG.md) unter „Deployment".

---

## Für Entwickler (technischer Anhang)

Der Datenblock entsteht ausschließlich in PHP und wird als **eine** Page-Extension ins Template
gereicht:

```
NavigationPageLoadedEvent / LandingPageLoadedEvent
  → SchemaGraphSubscriber (nur Delegation, keine Geschäftslogik)
    → SchemaContextFactory  (löst Name, Beschreibung, URLs, Locale, Custom Fields auf)
    → GraphBuilder          (sammelt getaggte Provider, baut den @graph, verknüpft @id-Bezüge)
      → SchemaNodeProvider[] (ein Provider je Schema-Typ)
  → Page-Extension rcSchemaGraph
    → Twig-Override page/content/meta.html.twig (ein <script type="application/ld+json">)
```

**Erweiterbarkeit (Open-Closed):** Ein neuer Schema-Typ (Article, Product, LocalBusiness, HowTo …)
ist ein zusätzlicher, mit `rc_schema.node_provider` getaggter Service. `GraphBuilder`, `IdFactory`,
die Twig-Ausgabe und die bestehenden Provider bleiben unverändert.

**Datenquellen je CMS-Block** liegen in `src/Schema/Extractor/` (ein Extractor je Block-Typ).
Die Video-Anreicherung über die YouTube-Data-API ist eine optionale, gecachte und fehlertolerante
Stufe der Feld-Kaskade (`src/Schema/Enrichment/`); ohne API-Schlüssel bleibt sie wirkungslos.

**Diagnose:** `CompletenessChecker` kennt je Schema-Typ die von Google geforderten/empfohlenen
Felder; die Ausgabe erfolgt als optionaler HTML-Kommentar (Diagnose-Schalter) und über den
Konsolenbefehl `rc-schema:diagnose`.

**Qualitätssicherung:**

```bash
composer cs-check   # Code-Stil (PSR-12)
composer phpstan    # statische Analyse (Level 8)
composer test       # Unit-Tests (PHPUnit)
composer quality    # alle drei zusammen
```

---

## Datenschutz

Das Plugin verarbeitet ausschließlich Shop- und Seiteninhalte und gibt diese als strukturierte
Daten aus. Es setzt keine Cookies und erhebt keine personenbezogenen Besucherdaten.

Wird optional ein YouTube-Data-API-Schlüssel hinterlegt, ruft der Server (nicht der Besucher)
öffentliche Video-Metadaten bei Google ab. Der Schlüssel wird in der Shopware-Systemkonfiguration
gespeichert und niemals im Seitenquelltext oder in Protokollen ausgegeben.

---

## Support und Lizenz

- **Hersteller:** Ruhrcoder (René Schustek) – <https://ruhrcoder.de>
- **Lizenz:** MIT (siehe [LICENSE](LICENSE))
- **Änderungshistorie:** siehe [CHANGELOG](CHANGELOG.md)
