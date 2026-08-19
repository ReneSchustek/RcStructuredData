# Changelog

## [2.15.0] - 2026-08-19 — Die Diagnose meldet jetzt zwei stille Fehler

### Neu

- **„Auf dieser Seite steht handgeschriebenes JSON-LD".** Trägt ein Baustein der Seite einen
  eigenen `ld+json`-Block, hat die Seite zwei Auszeichnungen. Sie können auseinanderlaufen, ohne
  dass es jemandem auffällt — genau das ist auf den Blogbeiträgen passiert: zwanzig Fragen und
  fünf Anleitungsschritte im Schema, von denen kein einziger auf der Seite sichtbar war. Der
  Hinweis nennt den Baustein, in dem es steckt, und erscheint auch dann, wenn für die Seite gar
  kein Graph entsteht.
- **„Diese Seite meldet FAQPage, ohne einen rc-faq-Baustein zu haben".** Die Auszeichnung entsteht
  aus dem sichtbaren Seiteninhalt. Meldet sie trotzdem eine FAQ, stammt diese aus einer anderen
  Quelle — der Verdacht auf Fragen, die kein Besucher zu sehen bekommt.

Beide Hinweise stehen in der Diagnose-Karte der Kategorie- und Landingpage-Ansicht, im
Konsolenbefehl und in der Antwort der Diagnose-Schnittstelle. Sie kosten nichts: geprüft wird an
den Bausteinen, die für die Diagnose ohnehin geladen werden.

### Aufgeräumt

- Ein ungenutzter Import im Installer der Zusatzfelder ist entfallen.

## [2.14.1] - 2026-08-17 — Baustein, Eigenschaft und Diagnose-Karte sind wieder da

### Behoben

- **Im Backoffice fehlte alles, was die Erweiterung dort beisteuert**: der Baustein für die
  häufigen Fragen, die zusätzlichen Eigenschaften und die Diagnose-Karte, an der sich ablesen
  lässt, ob die Auszeichnung greift. Ursache waren die fehlenden gebauten Dateien des
  Backoffice-Teils: Ohne sie kündigt Shopware das Bündel nicht an und lädt es nie — ohne
  Fehlermeldung. Die Auszeichnung selbst lief in der Storefront die ganze Zeit weiter, weshalb der
  Ausfall von außen nicht zu sehen war.

## [2.14.0] - 2026-08-12 — Zwei Lücken aus dem Sicherheits-Durchgang geschlossen

### Geändert

- **Die Diagnose-Schnittstelle verlangt jetzt ein Recht.** Beide Adressen gaben ihre Auskunft
  bisher an jeden Zugang heraus, der im Backoffice angemeldet war — auch an eine Integration,
  die für einen einzigen, eng geschnittenen Zweck angelegt wurde. Verlangt wird jetzt das
  Leserecht auf das, wonach gefragt wird: `category:read` für Kategorien, `landing_page:read`
  für Landingpages.

  **Vor dem Aktualisieren prüfen:** Greift eine eigene Integration auf die Diagnose zu, braucht
  sie künftig dieses Recht. Für angemeldete Personen mit vollem Zugang ändert sich nichts.

### Behoben

- **Die Antwort einer Frage-Antwort-Liste wird vor der Ausgabe bereinigt.** Sie trägt gewollt
  Auszeichnungen aus dem Textbearbeiter und wird deshalb ungeprüft ausgegeben, damit Absätze und
  Listen erhalten bleiben. Dazwischen fehlte die Bereinigung: Wer im Backoffice Inhalte pflegen
  darf, konnte damit Skripte einschleusen, die im Browser jedes Besuchers liefen — auch bei einer
  Person mit vollem Zugang, die die Seite nur ansieht. Formatierung bleibt erhalten, Skripte
  fallen weg.

## [2.13.2] - 2026-08-10 — Strukturierte Daten bleiben auch nach dem Versionswechsel eindeutig

### Geändert

- **WebSite und Organization erscheinen weiterhin genau einmal.** Ob das Plugin sie ergänzen muss, hing bisher an einem Schalter, den Shopware mit der nächsten Hauptversion entfernt — danach hätte die Abfrage das Gegenteil bedeutet und beide Angaben wären doppelt im Kopfbereich gelandet. Die Entscheidung trifft jetzt das Plugin selbst und bleibt über den Versionswechsel hinweg richtig. Sichtbar ändert sich nichts.

## [2.13.1] - 2026-08-10 — Vorbereitet auf die nächste Shopware-Hauptversion

### Geändert

- **Vorbereitung auf die nächste Shopware-Hauptversion.** Der Zugriff auf Suchergebnisse folgt der Schreibweise, die Shopware 6.8 verlangt. Am Verhalten ändert sich nichts.

## [2.13.0] - 2026-07-21

> **Deployment:** `php bin/console cache:clear`.

### Behoben

- **FAQ **und** Blog-Artikel auf derselben Seite verlieren ihre Verknüpfung nicht mehr:** Enthält die einem Blog-Artikel zugeordnete CMS-Seite einen FAQ-Block, zeigten FAQPage und BlogPosting beide auf `mainEntity` — der zuletzt verdrahtete Typ überschrieb den anderen, dessen Knoten blieb unverbunden im Graph. Beide werden jetzt gemeinsam als `mainEntity` verlinkt.
- **Keine toten `@id`-Referenzen mehr im Graph:** Zeigte eine `publisher`-Referenz auf eine Organization, die (mangels gepflegtem Shop-/Organisationsnamen) gar nicht erzeugt wurde, stand eine ins Leere laufende Referenz im JSON-LD (von Google als „referenced @id not found" beanstandet). Solche toten Referenzen werden jetzt vor der Ausgabe entfernt.

### Geändert

- **YouTube-API-Ausfall belastet die Seite nicht mehr wiederholt:** Ein API-Fehler (z. B. 403 bei erschöpftem Quota) wurde nicht negativ gecacht — jeder Cache-Miss-Render löste erneut einen bis zu 3 s blockierenden, scheiternden Aufruf aus. Fehler werden jetzt kurz negativ gecacht (Latenz- und Quota-Schutz).
- **Regressions-Sperre:** Neuer Twig-Contract-Test pinnt die drei JSON-LD-Meta-Overrides (`layout_head_json_ld`/`layout_head_json_ld_global`) gegen einen stillen Core-Rename (Phantom-Block-Klasse).

## [2.12.1] - 2026-07-20

> **Deployment:** `php bin/console cache:clear`.

### Behoben

- **Video-Thumbnail im VideoObject-Schema:** Der Fallback-Vorschaubild-Link (wenn am YouTube-Block kein Bild gepflegt ist) nutzte `maxresdefault.jpg`, das nur für Videos mit HD-Thumbnail existiert — bei SD-/älteren Videos lieferte er 404, und `thumbnailUrl` ist ein von Google gefordertes Feld (tote URL → Rich-Result-Warnung). Umgestellt auf `hqdefault.jpg` (existiert für jedes Video).

## [2.12.0] - 2026-07-02

> **Deployment:** `bin/build-administration.sh` (Admin-JS geändert) und `bin/console cache:clear`.

### Hinzugefügt
- **Schema-Diagnose-Karte auch auf Landingpages:** Die Diagnose-Karte (Knotenzahl + fehlende Felder)
  erscheint jetzt zusätzlich auf der Landingpage-Detailseite. Neuer Admin-API-Endpunkt
  `/api/_action/rc-schema/diagnose/landing-page/{landingPageId}`; der `SchemaDiagnoser` diagnostiziert
  neben Kategorien nun auch Landingpages (`SchemaContextFactory::fromLandingPageEntity`).

## [2.11.0] - 2026-07-02

> **Deployment:** `bin/console cache:clear` (nur PHP-Logik; kein Build nötig).

### Hinzugefügt
- **FAQ (und Video) auch auf Blog-Detailseiten:** Nutzt der Blog-Artikel den `rc-faq`-Block (bzw. einen
  `youtube-video`-Block), erzeugt das Plugin dort jetzt automatisch `FAQPage`- (bzw. `VideoObject`-)
  Schema im Blog-`@graph`. Der Subscriber lädt dazu die dem Blog-Eintrag zugeordnete Artikel-CMS-Seite
  (nicht das globale Blog-Detail-Layout) und reicht sie an die block-basierten Provider durch; ohne
  passende Blöcke entsteht kein zusätzlicher Knoten. Der `BlogPosting`-Knoten bleibt die `mainEntity`.

### Behoben
- **Leerer FAQ-Block gibt kein Markup mehr aus:** Sind keine Frage/Antwort-Paare gepflegt, rendert das
  `rc-faq`-Element gar nichts (kein leerer Wrapper) und erzeugt auch **kein** `FAQPage`-Schema.

## [2.10.0] - 2026-07-02

> **Deployment:** `bin/build-administration.sh` (Admin-JS geändert) und `bin/console cache:clear`.

### Hinzugefügt
- **Sichtbarer Schema-Diagnose-Hinweis im Admin:** Auf der Kategorie-Detailseite zeigt eine neue Karte
  „Schema.org-Diagnose" die Anzahl der erzeugten `@graph`-Knoten und – je Knoten – fehlende Pflicht-
  und Empfehlungsfelder (aus Google-Sicht). Bisher gab es diese Diagnose nur als HTML-Kommentar
  (Debug-Schalter) und über den Konsolenbefehl `rc-schema:diagnose`. Neuer Admin-API-Endpunkt
  `/api/_action/rc-schema/diagnose/category/{categoryId}` (nutzt den vorhandenen `SchemaDiagnoser`).

## [2.9.0] - 2026-07-02

> **Deployment:** `bin/build-administration.sh` (Admin-JS geändert) und `bin/console cache:clear`.

### Geändert
- **FAQ-Antworten unterstützen jetzt Formatierung (Rich-Text):** Das Antwortfeld im FAQ-Element ist
  ein Rich-Text-Editor statt eines reinen Textfelds; die Storefront gibt die Formatierung aus
  (Fett, Listen, Links …). Anzeige und `FAQPage`-Schema stammen weiter aus einer Quelle. Bestehende
  Klartext-Antworten funktionieren unverändert.

## [2.8.0] - 2026-07-02

> **Deployment:** `bin/console plugin:update RcStructuredData` (neue Konfigurationsfelder) und `cache:clear`.

### Hinzugefügt
- **LocalBusiness-Schema** (lokale SEO): Ist eine Adresse (Straße + Ort) in der Plugin-Konfiguration
  gepflegt, ergänzt der `@graph` einen `LocalBusiness`-Knoten mit `PostalAddress` und optional
  `telephone`, `priceRange`, `geo` (GeoCoordinates) und `openingHours`. Neue Konfig-Karte
  „LocalBusiness" (DE/EN).
- **ItemList auch auf Landingpages:** Der `ItemList`-Knoten (gelistete Produkte) entsteht jetzt auch
  auf Landingpages mit Produktliste, nicht mehr nur auf Kategorieseiten.

## [2.7.0] - 2026-07-02

> **Deployment:** `bin/console cache:clear` (nur PHP/Twig-Logik; kein Build nötig).

### Hinzugefügt
- **Blog-Article-Schema** für **WerklOpenBlogware**-Blog-Detailseiten: `@graph` mit `BlogPosting`
  (headline, description, datePublished, author, image, articleBody) + Organization/WebSite/WebPage
  (WebPage `mainEntity` → BlogPosting). Vollautomatisch aus dem Blog-Eintrag.
- **Optionaler Andockpunkt ohne harte Abhängigkeit:** Der Blog-Subscriber wird nur registriert, wenn
  WerklOpenBlogware installiert ist (bedingtes Laden in `build()`). Ohne das Blog-Plugin bleibt alles
  unverändert. Der `BlogPostingNodeProvider` selbst ist frei von Blog-Plugin-Typen; die statische
  Analyse nutzt einen PHPStan-Stub (keine Laufzeit-Abhängigkeit).

## [2.6.0] - 2026-07-02

> **Deployment:** `bin/console cache:clear` (nur PHP/Twig-Logik; kein Build nötig).

### Hinzugefügt
- **Strukturierte Daten auf der Startseite:** Die Startseite erhält jetzt einen `@graph` mit
  `Organization`, `WebSite` (inkl. Sitelinks-Suchfeld, sobald eine Such-URL konfiguriert ist) und
  einem `WebPage`-Knoten (`isPartOf` → WebSite, `publisher` → Organization). Bisher gab das Plugin auf
  der Startseite nichts aus. Umgesetzt über einen neuen Kontext-Typ `home` in der
  `SchemaContextFactory`; die Startseite bekommt bewusst **keine** CollectionPage/BreadcrumbList.

## [2.5.0] - 2026-07-02

> **Deployment:** `bin/build-administration.sh` (Admin-JS geändert) und `bin/console cache:clear`.

### Hinzugefügt
- **Dedizierter FAQ-CMS-Block** (`rc-faq`): erscheint in der Block-Seitenleiste und ist frei per
  Drag&Drop platzierbar — man wählt selbst, wo die FAQs entstehen, und nutzt das Shopware-CMS normal
  weiter. Der Block kapselt das bestehende `rc-faq`-Element (Anzeige und FAQPage-Schema aus einer
  Quelle).
- **Wählbare Darstellung** je FAQ-Element (`displayMode`): **Accordion** (aufklappbar,
  `<details>/<summary>`) oder **Liste** (offen, `<dl>`). Nicht mehr fest auf Accordion verdrahtet.
  Das FAQPage-Schema bleibt unverändert (Google-konform: Anzeige = sichtbarer Inhalt). Unbekannte
  Werte fallen sicher auf Accordion zurück; Bestandselemente ohne `displayMode` ebenso.

## [2.4.0] - 2026-07-02

> **Deployment:** `bin/console cache:clear` (nur PHP/Twig-Logik; kein Build nötig).

### Hinzugefügt
- **`ItemList` auf Kategorieseiten:** Der `@graph` enthält jetzt einen `ItemList`-Knoten mit den auf
  der aktuellen (paginierten) Seite gelisteten Produkten (`ListItem` mit Position und
  Produktdetail-URL). Damit versteht Google die Kategorie als Produktsammlung. Vollautomatisch aus der
  vorhandenen Produktliste (`product-listing`-Element), keine Pflege nötig. Neuer
  `ProductListingExtractor` + `ItemListNodeProvider` (getaggter Node-Provider). Ohne Produktliste oder
  bei leerer Liste entsteht kein Knoten.

## [2.3.0] - 2026-07-02

> **Deployment:** `bin/console cache:clear` (nur Storefront-Twig geändert; kein Build nötig).

### Hinzugefügt
- **Strukturierte Daten auf Produktdetailseiten** (JSON-LD): Produktseiten geben jetzt Product,
  Offer/AggregateOffer, Breadcrumb, WebPage (ItemPage), Organization und WebSite als JSON-LD aus —
  **unabhängig vom Feature-Flag `JSON_LD_DATA`** (ab Shopware 6.8 ist Microdata der Standard, sonst
  bliebe die Produktseite ohne JSON-LD). Es werden die gepflegten Core-Partials wiederverwendet
  (kein PHP-Nachbau), inklusive Varianten (`ProductGroup`), Maße, Videos, Verfügbarkeits-Kaskade und
  `aggregateRating`/`review` aus vorhandenen Bewertungen.
- Umgesetzt als Twig-Override `storefront/page/product-detail/meta.html.twig` (Block
  `layout_head_json_ld`). WebSite/Organization werden nur ergänzt, wenn der globale Core-Block sie
  mangels Flag nicht liefert — bei aktivem Flag keine Dopplung.

## [2.2.0] - 2026-07-01

> **Deployment:** `bin/console plugin:update RcStructuredData` (neues Konfigurationsfeld) und `cache:clear`. Optionaler API-Key unter **Einstellungen → Plugins → RcStructuredData → YouTube-Data-API**.

### Hinzugefügt
- **YouTube-Data-API-Anreicherung** für VideoObject-Metadaten (`YoutubeApiMetadataEnricher`):
  Ist ein API-Key gesetzt, werden fehlende Felder (name, description, uploadDate, duration)
  automatisch per `videos.list` (snippet + contentDetails) geholt. Eingehängt als Stufe in die
  Feld-Kaskade des VideoObject: **Block → Category → API → Auto** (Abruf nur, wenn nach Block +
  Category noch ein Feld fehlt).
- Antworten werden gecacht (Quota-Schonung); Netzwerk-/Quota-Fehler und ein fehlender Key führen
  zum stillen Fallback auf die manuelle Pflege — nie zu einem Seitenfehler. Der API-Key stammt aus
  `system_config` und erscheint weder im Output noch im Log.
- Plugin-Konfiguration: Feld **YouTube-Data-API-Schlüssel** (Typ `password`), DE/EN.

### Behoben / Qualität
- Englische Hilfetexte für `organizationName` und `websiteName` enthielten deutschen Text — korrigiert.
- Video-Cache-Schlüssel nutzt `hash('sha256', …)` statt `sha1` (keine sicherheitsrelevante Hash-Funktion).
- Sicherer Fallback der JSON-Encode-Flags im Storefront-Template (`JSON_HEX_TAG | JSON_HEX_AMP`).
- Endanwenderfreundliche, ausführliche README; erweiterter Unit-Test für die `SchemaContextFactory`.
- CI-Matrix über PHP 8.2/8.3/8.4; einheitliche Zeilenenden (LF) via `.gitattributes`.

## [2.1.0] - 2026-07-01

> **Deployment:** `bin/build-storefront.sh` erforderlich (Admin-JS geändert), anschließend `bin/console plugin:update RcStructuredData` (Custom-Field-Konfiguration) und `cache:clear`.

### Hinzugefügt
- Admin-Repeater für **`additionalProperty`** (Name/Wert): Das JSON-Custom-Field
  `rc_schema_additional_properties` wird jetzt über eine eigene Admin-Komponente
  (`rc-schema-additional-property`, via `config.componentName` in `sw-form-field-renderer`
  eingebunden) als bearbeitbare Name/Wert-Liste gerendert — an Kategorie und Landingpage.
  Speichert exakt `[{name, value}, …]` (kompatibel zum bestehenden Auslesen im
  `AbstractPageNodeProvider`). Snippets DE/EN.

## [2.0.0] - 2026-06-30

> **Deployment:** `bin/build-storefront.sh` erforderlich (Erstinstallation v2 — Storefront-Twig + Admin geändert), anschließend `bin/console plugin:update RcStructuredData` (Custom-Field-Set) und `cache:clear`.

### Geändert (Breaking)
- Architektur-Umbau zum **Schema Graph Builder**: Das Plugin erzeugt die strukturierten
  Daten jetzt zentral in PHP und gibt auf Kategorie- und Landingpages **einen** zusammen-
  hängenden JSON-LD-`@graph` im Storefront-`<head>` aus (Knoten über `@id` verknüpft).
- Der frühere Admin-Override `sw-cms-page-form` und das rohe `schema_json`-Textfeld sind
  **entfallen**. Die bisherige Theme-seitige Ausgabe von `schema_json` wird durch den
  Builder abgelöst.

### Hinzugefügt
- Knoten: `CollectionPage` (Kategorie, niemals Product), `WebPage` (Landingpage),
  `BreadcrumbList` (automatisch aus der Shopware-Navigation), `Organization` und `WebSite`
  (Singletons aus der Plugin-Konfiguration, per `@id` referenziert).
- Getaggte `SchemaNodeProvider`-Architektur (`rc_schema.node_provider`) — neue Schema-Typen
  (FAQPage, VideoObject, Article, Product …) werden durch Hinzufügen eines Providers ergänzt.
- Custom-Field-Set `rc_schema` (Beschreibungs-Override, additionalProperty) für Kategorie
  und Landingpage.
- Plugin-Konfiguration für Organization/WebSite und einen Diagnose-Schalter.
- Unterdrückung der Core-`JSON_LD_DATA`-Ausgabe auf behandelten Seiten (kein Doppel-Schema).
- CMS-Element **`rc-faq`**: pflegbares Frage/Antwort-Accordion (barrierearm via
  `<details>`/`<summary>`), das zugleich Datenquelle für den `FAQPage`-Knoten ist
  (`mainEntity` an der CollectionPage/WebPage). Eine Quelle für sichtbaren Inhalt und Schema.
- **`VideoObject`** aus dem Standard-CMS-Element `youtube-video`: je Slot ein Knoten mit
  eindeutiger `@id` (`#video-1`, `#video-2` …), `embedUrl`/`contentUrl` aus der `videoID`,
  `thumbnailUrl` aus dem Vorschaubild (Fallback: YouTube-Standardbild), `publisher` per `@id`
  an die Organization, automatisch per `hasPart` an die Seite gehängt.
- **Feld-Kaskade** für Video-Metadaten: Block-Felder (Admin-Override des `youtube-video`-
  Formulars: `rcSchemaName`/`-Description`/`-UploadDate`/`-Duration`) → Category-/Landingpage-
  Fallback (`rc_schema_video_*`) → Auto-Wert (Seitentitel/Meta-Description) bzw. Lücke.
- **Vollständigkeits-Diagnose**: `CompletenessChecker` kennt je Schema-Typ die Google-Pflicht-/
  Empfehlungsfelder; Ausgabe als HTML-Kommentar bei aktivem Debug-Schalter **und** über den
  Konsolenbefehl `rc-schema:diagnose <Kategorie>`.
- Unit-Tests für GraphBuilder, IdFactory, alle Knoten-Provider, FAQ- und Youtube-Extractor,
  FAQ-/VideoObject-Provider, CompletenessChecker, DiagnoseCommand, CmsSlotIterator und FaqResolver.

### Migrationshinweis
- Bereits in `cms_page.customFields.schema_json` gepflegtes JSON bleibt in der Datenbank
  erhalten, wird aber nicht mehr ausgegeben. Inhalte ggf. in Kategorie-Felder / FAQ-Block überführen.

## [1.0.0] - 2026-06-28

### Hinzugefügt
- Administrations-Erweiterung: Override von `sw-cms-page-form` blendet am Ende des
  CMS-Seiten-Formulars eine Karte „Strukturierte Daten (JSON-LD)" ein.
- Textfeld `Schema.org JSON` (volle Breite, Platzhalter `{ ... }`), gebunden an das
  bestehende CustomField `page.customFields.schema_json`.
- Getter/Setter-Bindung: leerer String bei nicht gesetztem Wert, automatische
  Initialisierung von `page.customFields` beim ersten Schreiben.
- Inline-JSON-Validierung (try/catch `JSON.parse`) mit Fehlerhinweis unter dem Feld.
- Snippets DE/EN für Kartentitel, Label, Platzhalter und Fehlertext.
- Unit-Tests für die Plugin-Basisklasse.

### Hinweis
- Reines Admin-Plugin: keine Storefront-Änderung, keine neuen CustomFields, keine
  Migration, keine Datenbank-Zugriffe. Die Ausgabe des JSON-LD erfolgt zentral im
  Theme (siehe README).
