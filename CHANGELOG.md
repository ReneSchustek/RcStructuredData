# Changelog

## [2.24.0] - 2026-10-14: Bilder in der Sitemap

> **Ausrollen:** `plugin:update RcStructuredData`, `cache:clear`, danach `sitemap:generate`.

### Neu

- **Bilder in der Sitemap:** Jeder Eintrag für Produkt, Kategorie, Landingpage und Startseite trägt die Bilder der
  Seite als `<image:image>`, der Namensraum `xmlns:image` steht im Wurzelelement. Produkte: die Galerie, Titelbild
  zuerst. Kategorien, Startseite, Landingpages: das Kategoriebild und die sichtbaren Bilder der Erlebniswelt aus
  derselben Sammelstelle wie `image` im Graphen, mit den Überschreibungen je Baustein. Höchstens 60 je Seite. Seiten und
  Reihenfolge bleiben, wie der Kern sie liefert; keine zweite Sitemap-Datei.

### Behoben

- Ein Baustein mit unvollständiger Einstellung (ohne `source`) ließ die Bildersuche scheitern; er zählt jetzt einfach
  nicht.

## [2.23.0] - 2026-10-14: Alle sichtbaren Bilder im Graphen

> **Ausrollen:** `plugin:update RcStructuredData`, `cache:clear`.

### Neu

- **`image` an Seitenknoten** (CollectionPage, WebPage): alle Bilder, die auf der Erlebniswelt sichtbar sind, als
  `ImageObject` mit Bildtext, in Seitenreihenfolge, jedes einmal, höchstens 60, Adressen sauber kodiert. Quellen: Bildbausteine, Galerien,
  Schieber, Kacheln und Karten (alles unter `media`/`mediaId` in der Konfiguration), Hintergrundbilder von Abschnitten
  und Blöcken, die Titelbilder der gezeigten Produkte (Listing, Schieber, Querverkauf) und Bilder im HTML von Text- und
  Twig-Bausteinen. Was auf keinem Gerät sichtbar ist, zählt nicht. Bisher stand auf Kategorieseiten kein Bild im
  Graphen.

## [2.22.1] - 2026-10-14: Teamseite und „Über uns" sind Inhaltsseiten

> **Ausrollen:** `plugin:update RcStructuredData`, `cache:clear`.

### Behoben

- **Landingpage-Layouts ohne Liste sind `WebPage`**: „Über uns", „Unser Team" und andere Seiten im Design 2027 sind als
  Landingpage angelegt und waren als `CollectionPage` ausgezeichnet. Führt die Seite keine Liste (Listing,
  Produktschieber, Cross-Selling, Kategoriekacheln, Unterkategorien, Beiträge), ist sie jetzt eine `WebPage`.

## [2.22.0] - 2026-10-14: Mitarbeiter und Kontaktpunkt

> **Ausrollen:** `plugin:update RcStructuredData`, `cache:clear`. Keine Migration.

### Neu

- **Mitarbeiter als `Person`** mit Name und Funktion, verknüpft per `worksFor` mit der Organisation. Quelle sind die
  Personenkarten des Themes (Baustein „Service-Leiste", Karten mit „Bilder als Porträt"): Titel = Name, Text bis zum
  ersten „·" = Funktion. Bewusst ohne E-Mail, Telefon und Bild. Ausgegeben nur auf der Seite mit den Karten.
- **`contactPoint` an der Organisation**: Kundendienst mit dem Telefon aus der Karte LocalBusiness und der Shop-E-Mail
  aus den Grundeinstellungen, Sprache Deutsch, Gebiet DE/AT/CH. Ersetzt den handgeschriebenen Block, der bisher in den
  Layouts stand.

## [2.21.1] - 2026-10-14: Die Startseite heißt wie der Shop

> **Ausrollen:** `plugin:update RcStructuredData`, `cache:clear`.

### Behoben

- **Startseite**: Name aus dem Meta-Titel der Startseite, sonst dem Startseitennamen oder dem Namen des Verkaufskanals,
  statt der Wurzelkategorie („Hauptkategorien"); Beschreibung aus der Meta-Beschreibung der Startseite, sonst der
  Wurzelkategorie.

## [2.21.0] - 2026-10-14: Firmenangaben vollständiger, eine Beschreibung je Seite

> **Ausrollen:** `plugin:update RcStructuredData`, `cache:clear`. Keine Migration. Danach in der Plugin-Konfiguration
> Bild des Geschäfts, Breiten- und Längengrad und Preisspanne pflegen (alle optional).

### Geändert

- **LocalBusiness heißt jetzt `HomeAndConstructionBusiness`**, ein Untertyp für Baubedarf und Handwerk, und trägt ein
  `image` (neue Einstellung „Bild des Geschäfts", sonst das Logo der Organisation).
- **Öffnungszeiten als `openingHoursSpecification`** mit Wochentagen, Öffnen und Schließen, wie Google es empfiehlt.
  Zeilen wie „Mo-Th 08:00-17:00" oder „Mo,We 08:00-12:00"; was sich nicht lesen lässt, bleibt als Text erhalten.
- **Eine Beschreibung je Seite**: CollectionPage, WebPage und Landingpage nehmen die Meta-Beschreibung; die eigene
  Fassung im Zusatzfeld gilt nur noch, wenn keine Meta-Beschreibung gepflegt ist.
- **Namen ohne Leerzeichen am Rand** in Seiten und Brotkrume (etwa „Glasgeländer " aus der Kategoriepflege).

## [2.20.1] - 2026-10-05: Zusatzfelder heißen wie die Erweiterung, Wartegrenze für YouTube

> **Deployment:** `php bin/console plugin:update RcStructuredData && php bin/console cache:clear`.

### Behoben

- **Der Abgleich mit YouTube hält die Seite höchstens drei Sekunden auf.** Die Wartegrenze galt nur
  für die Pause zwischen zwei Datenpaketen; eine langsam tröpfelnde Antwort konnte den Aufbau der
  Start-, Kategorie- oder Landingpage länger aufhalten. Jetzt begrenzt sie den ganzen Aufruf.
- **Ablaufdatum eines Nachweises ohne Zonenangabe:** Es gilt in der Zeit der Redaktion. Auf einem
  Server in UTC wurde aus „3. Juli, 23:30“ der 4. Juli.

### Intern

- **Messprojekt `benchmarks/`** mit PHPBench und einer Seitenmessung über HTTP, Ablage je Lauf in
  `benchmarks/results.csv`; kommt in kein Ausrollpaket. Ausgangswerte vom 05.10. auf einer Kopie des Shops: Der
  Graph kostet auf Start-, Kategorie- und Landingpage weniger als die Streuung der Messung (rund
  10 bis 20 ms); ein Abgleich mit YouTube bei leerem Speicher 25 ms je Video, aus dem Speicher
  praktisch nichts.
- Wächter im Testlauf: Jeder Aufruf der YouTube-Schnittstelle trägt eine Wartegrenze von höchstens
  drei Sekunden.

### Geändert

- Unter „Zusatzfelder“ an Kategorie und Landingpage stand „Schema.org strukturierte Daten“. Dort steht jetzt „Strukturierte Daten“, derselbe Name wie unter Erweiterungen.

## [2.20.0] - 2026-09-21: Nachweise als eigener Baustein, Videos auf der Startseite

> **Deployment:** `php bin/console plugin:update RcStructuredData && php bin/console cache:clear`,
> danach `bin/build-administration.sh` für den neuen Baustein im Backoffice.

### Neu

- **CMS-Baustein „Nachweise" (`rc-documents`).** Er zeigt Dateien aus der Medienverwaltung
  (Prüfzeugnisse, Bescheide) als Liste mit Verweis, freiwilliger Beschreibung, Ausstellungs- und
  Ablaufdatum, und erzeugt daraus je Eintrag einen `DigitalDocument`-Knoten. Die Seite hängt die
  Knoten über `hasPart` an, neben die Videos.

  **Warum das gebraucht wird:** Auf `/bauteile/glashalter/` stehen das bauaufsichtliche
  Prüfzeugnis P-169908-LGA und sein Verlängerungsbescheid als Verweise in einem freien Textblock,
  die Daten dazu in einer Tabelle daneben. Daraus lässt sich kein Nachweis ableiten, ohne Fließtext
  zu deuten; ausgezeichnet waren sie deshalb nur im handgeschriebenen JSON-LD. Fiele der Handblock,
  wüsste Google nicht mehr, was die beiden PDF sind.

- **Ein Eintrag braucht Namen und Datei, sonst erscheint er nicht**, weder auf der Seite noch im
  Graphen. Wird die Datei später gelöscht, verschwindet der Eintrag mit ihr, statt als toter
  Verweis stehen zu bleiben.

- **Kein `publisher`.** Ein Prüfzeugnis stellt die Prüfstelle aus, nicht der Shop.

### Behoben

- **Auf der Startseite entstand aus keinem Baustein ein Knoten.** Der Startseiten-Kontext wurde
  seit 2.6.0 ohne Erlebniswelt gebaut. Gedacht war das, um Breadcrumb und CollectionPage fernzuhalten.
  Mitgenommen hat das auch Videos, FAQ und alles Weitere, was als Baustein auf der Seite steht: Die
  beiden Videos der Startseite standen deshalb nur im handgeschriebenen JSON-LD. Die Erlebniswelt
  reist jetzt mit; Breadcrumb und CollectionPage bleiben aus, weil sie an der Kategorie hängen, die
  weiterhin fehlt. Auf einer Kopie des Shops gegen 2.19.0 verglichen: Startseite und Glashalter gewinnen die
  erwarteten Knoten, die Seiten für Handläufe und Treppengeländer bleiben unverändert.

- **Voraussetzung bleibt das Datum.** Ein Video ohne `uploadDate` wird weiterhin ausgelassen; die
  beiden Startseiten-Videos brauchen die Angaben am Baustein.

### Abgesichert

- **Ein Datum bleibt der Tag, der gepflegt wurde.** Ein Datumsfeld kann „4. Juli, 0 Uhr" als
  `2026-07-03T22:00:00.000Z` speichern; abgeschnitten wäre das der 3. Juli. Zeitpunkte werden in
  der Zone der Redaktion (Europe/Berlin) gelesen. Am Prüfblock auf einer Kopie des Shops nachgewiesen.

- **Die Diagnose-Karte sieht die Nachweise auch.** Sie lädt die Seite ohne Storefront und hätte
  die Dateien sonst nicht aufgelöst. Die Karte hätte eine Lücke gemeldet, die es auf der Seite
  nicht gibt. Aufgelöst wird an einer gemeinsamen Stelle (`DocumentsStructBuilder`).

- **Dateikennungen in Großbuchstaben werden angenommen.** `Uuid::isValid()` lehnt sie ab; ohne
  Umwandlung wäre ein solcher Eintrag still verschwunden. Aufgefallen im Test.

## [2.19.0] - 2026-09-08: Inhaltsseiten sind keine Sammlungen

### Behoben

- **Impressum, AGB, Kontakt und die übrigen Inhaltsseiten wurden als `CollectionPage`
  ausgezeichnet.** Der Typ heißt „Seite mit einer Liste von Einträgen", und auf einer
  Widerrufsbelehrung ist das schlicht falsch. Sie sind jetzt `WebPage`. Betroffen sind im Shop
  **21 Kategorien**, davon 11 sichtbar.

- **Der Unterscheider ist der Typ der Erlebniswelt, nicht der der Kategorie.** `category.type` ist
  bei diesen Seiten `page`, bei Produktkategorien aber ebenfalls; er taugt also nicht. Erst
  `cms_page.type` trennt die Fälle: `product_list` und `landingpage` führen eine Liste, `page`
  nicht.

- **Die Blog-Übersicht bleibt eine Sammlung.** Sie läuft ebenfalls über ein `page`-Layout, führt
  aber sehr wohl eine Liste. Erkannt wird sie am Listen-Baustein, nicht am Namen der Kategorie:
  Ein Name lässt sich ändern, ein Baustein ist das, was die Seite tut.

### Nebenbei abgesichert

- **`CmsPageEntity::$type` ist eine typisierte Eigenschaft ohne Vorgabewert.** Wird die
  Erlebniswelt ohne dieses Feld geladen, wirft schon der Zugriff, und zwar mitten in der
  Auszeichnung jeder Seite. Gelesen wird deshalb über `getVars()`, das nicht initialisierte
  Eigenschaften überspringt. Aufgefallen im Test, nicht im Betrieb.

## [2.18.0] - 2026-08-26: Begriffssatz als eigener Baustein

> **Deployment:** `php bin/console plugin:update RcStructuredData && php bin/console cache:clear`.

### Neu

- **CMS-Baustein „Begriffssatz" (`rc-defined-terms`).** Er zeigt eine Liste von Fachbegriffen
  sichtbar auf der Seite und erzeugt daraus den `DefinedTermSet`-Knoten: Name, freiwillige
  Erläuterung, Begriffe mit freiwilliger Erklärung.

  **Warum das gebraucht wird:** Sieben Kategorieseiten tragen dieses Wissen heute nur als
  handgeschriebenes JSON-LD (Normen, Vorgaben, Fachbegriffe, also fachliches Wissen des Betriebs, das
  sich aus keinem Produktdatensatz ableiten lässt). Fielen die Handblöcke ersatzlos, ginge diese
  Auszeichnung verloren. Jetzt gibt es einen Platz dafür, der nach derselben Regel arbeitet wie
  alles andere in diesem Plugin: **Ausgezeichnet wird, was auf der Seite steht.**

### Entscheidungen im Baustein, die nicht offensichtlich sind

- **Je Baustein ein eigener Satz, anders als bei der FAQ.** Fragen einer Seite werden zu *einer*
  `FAQPage` zusammengeführt; Begriffssätze nicht. „Normen für Geländer" und „Werkstoffe" sind
  zwei Sammlungen mit je eigenem Namen. Sie zu verschmelzen hieße, einen Namen zu verlieren, und
  der Name ist bei einem Begriffssatz die halbe Aussage.
- **Ein Satz ohne Namen oder ohne Begriffe wird nicht ausgegeben**. Es gilt dieselbe Regel wie beim
  `VideoObject` ohne Datum: lieber kein Knoten als ein unvollständiger. Die Nummerierung
  (`#begriffe-1`, `#begriffe-2` …) zählt nur ausgegebene Sätze, damit keine Verknüpfung ins Leere
  zeigt.
- **Verknüpft über `about`, nicht über `hasPart`.** Ein Begriffssatz ist kein Teil der Seite,
  sondern das, wovon sie handelt.
- **Die Erklärung geht durch den Sanitizer, der Begriff nicht.** Die Erklärung kommt aus dem
  Textbearbeiter und wird mit `|raw` ausgegeben — ohne Sanitizer würde aus einem Redakteursrecht
  ein Vollzugriff. Der Begriff wird escaped ausgegeben und stünde, durch den Sanitizer geschickt,
  mit sichtbaren Auszeichnungen auf der Seite.

### Was damit **nicht** entschieden ist

`DigitalDocument` (4 Vorkommen) und `OfferCatalog` (2 Slots) stehen weiterhin nur handgeschrieben
da. Ob sich ein eigener Baustein dafür lohnt, ist eine Frage an die Search Console. Sie gehört
beantwortet, **bevor** die Handblöcke entfernt werden, denn danach ist die Vorlage weg.

## [2.17.1] - 2026-08-24: Ein ausgelassenes Video steht in der Diagnose

> **Deployment:** `php bin/console plugin:update RcStructuredData && php bin/console cache:clear`.

### Neu

- **Wird ein Video ohne Veröffentlichungsdatum ausgelassen, meldet es die Diagnose, mit Videokennung.** Seit 2.17.0 entsteht für ein solches Video gar kein `VideoObject` mehr; ohne diese Meldung wäre das ein stiller Verlust, denn im Backoffice sähe niemand, **welches** Video ein Datum braucht. Mehrere ausgelassene Videos stehen in **einer** Meldung, nicht in einer Wand aus Hinweisen.


## [2.17.0] - 2026-08-24: Search-Console-Befunde: Videos und Versanddetails

> **Deployment:** `php bin/console plugin:update RcStructuredData && php bin/console cache:clear`.
> Kein Storefront-Bau nötig.

### Behoben

- **Ein Video ohne Veröffentlichungsdatum wird gar nicht mehr ausgezeichnet.** Google wertet ein `VideoObject` ohne `uploadDate` als **kritischen Fehler**. Das Video erscheint dann nicht in der Suche, und die Meldung steht in der Search Console. Ein fehlendes `VideoObject` ist dagegen neutral. Am 24.08.2026 waren 13 solcher Knoten auf 7 Seiten betroffen. Wer Datum und Beschreibung am Video pflegt (Zusatzfeld am Block oder an der Seite, wahlweise über die YouTube-Schnittstelle), bekommt die Auszeichnung zurück. Die Auslassung ist also kein Verzicht, sondern ein Hinweis mit Wirkung.

### Neu

- **`offers.shippingDetails` trägt jetzt Ziel, Laufzeit und Preis.** Shopware gibt dort nur die Bearbeitungszeit aus; die Search Console vermisste `shippingDestination`, `transitTime` und `shippingRate`. Neu dabei: die Lieferländer des Verkaufskanals, die Laufzeit der Versandart — fachlich etwas anderes als die Bearbeitungszeit im Haus — und der vorberechnete Versandpreis.
- **Der Preis kommt aus `RcProductFeedShippingExtension`**, das ihn für den Warenstrom ohnehin je Produkt und Land vorhält. Die Anbindung ist weich: Ohne dieses Plugin läuft alles unverändert weiter, nur ohne `shippingRate`. Ein geschätzter Ersatzwert wird bewusst **nicht** übernommen, denn er wäre eine Zusage über einen Preis, den der Shop nicht ermittelt hat.
- Produkte **ohne** Lieferzeit bekommen die Angabe jetzt ebenfalls (vorher ließ Shopware sie ganz weg): Ziel und Laufzeit stehen unabhängig vom Produkt fest.


## [2.16.0] - 2026-08-19: Blogbeiträge tragen ihr Änderungsdatum

### Neu

- **`dateModified` steht jetzt am BlogPosting.** Bisher lieferte die Auszeichnung nur das Datum der
  Veröffentlichung. Das Feld fehlte damit als einziges gegenüber dem Block, den WerklOpenBlogware
  danebenschreibt, und ohne es hätte der Wechsel auf die eigene Auszeichnung etwas gekostet.
- Bei einem nie bearbeiteten Beitrag gilt das Datum der Veröffentlichung. Das ist kein Detail:
  Wer `null` durch eine Datumsformatierung schickt, bekommt das **heutige** Datum. Genau dieser
  Fehler steckt im Block des Fremdplugins, wo jeder Beitrag seit Monaten „heute bearbeitet" meldet.

## [2.15.0] - 2026-08-19: Die Diagnose meldet jetzt zwei stille Fehler

### Neu

- **„Auf dieser Seite steht handgeschriebenes JSON-LD".** Trägt ein Baustein der Seite einen
  eigenen `ld+json`-Block, hat die Seite zwei Auszeichnungen. Sie können auseinanderlaufen, ohne
  dass es jemandem auffällt. Genau das ist auf den Blogbeiträgen passiert: zwanzig Fragen und
  fünf Anleitungsschritte im Schema, von denen kein einziger auf der Seite sichtbar war. Der
  Hinweis nennt den Baustein, in dem es steckt, und erscheint auch dann, wenn für die Seite gar
  kein Graph entsteht.
- **„Diese Seite meldet FAQPage, ohne einen rc-faq-Baustein zu haben".** Die Auszeichnung entsteht
  aus dem sichtbaren Seiteninhalt. Meldet sie trotzdem eine FAQ, stammt diese aus einer anderen
  Quelle, und das weckt den Verdacht auf Fragen, die kein Besucher zu sehen bekommt.

Beide Hinweise stehen in der Diagnose-Karte der Kategorie- und Landingpage-Ansicht, im
Konsolenbefehl und in der Antwort der Diagnose-Schnittstelle. Sie kosten nichts: geprüft wird an
den Bausteinen, die für die Diagnose ohnehin geladen werden.

### Aufgeräumt

- Ein ungenutzter Import im Installer der Zusatzfelder ist entfallen.

## [2.14.1] - 2026-08-17: Baustein, Eigenschaft und Diagnose-Karte sind wieder da

### Behoben

- **Im Backoffice fehlte alles, was die Erweiterung dort beisteuert**: der Baustein für die
  häufigen Fragen, die zusätzlichen Eigenschaften und die Diagnose-Karte, an der sich ablesen
  lässt, ob die Auszeichnung greift. Ursache waren die fehlenden gebauten Dateien des
  Backoffice-Teils: Ohne sie kündigt Shopware das Bündel nicht an und lädt es nie, und
  zwar ohne Fehlermeldung. Die Auszeichnung selbst lief in der Storefront die ganze Zeit weiter, weshalb der
  Ausfall von außen nicht zu sehen war.

## [2.14.0] - 2026-08-12: Zwei Lücken aus dem Sicherheits-Durchgang geschlossen

### Geändert

- **Die Diagnose-Schnittstelle verlangt jetzt ein Recht.** Beide Adressen gaben ihre Auskunft
  bisher an jeden Zugang heraus, der im Backoffice angemeldet war, auch an eine Integration,
  die für einen einzigen, eng geschnittenen Zweck angelegt wurde. Verlangt wird jetzt das
  Leserecht auf das, wonach gefragt wird: `category:read` für Kategorien, `landing_page:read`
  für Landingpages.

  **Vor dem Aktualisieren prüfen:** Greift eine eigene Integration auf die Diagnose zu, braucht
  sie künftig dieses Recht. Für angemeldete Personen mit vollem Zugang ändert sich nichts.

### Behoben

- **Die Antwort einer Frage-Antwort-Liste wird vor der Ausgabe bereinigt.** Sie trägt gewollt
  Auszeichnungen aus dem Textbearbeiter und wird deshalb ungeprüft ausgegeben, damit Absätze und
  Listen erhalten bleiben. Dazwischen fehlte die Bereinigung: Wer im Backoffice Inhalte pflegen
  darf, konnte damit Skripte einschleusen, die im Browser jedes Besuchers liefen, auch bei einer
  Person mit vollem Zugang, die die Seite nur ansieht. Formatierung bleibt erhalten, Skripte
  fallen weg.

## [2.13.2] - 2026-08-10: Strukturierte Daten bleiben auch nach dem Versionswechsel eindeutig

### Geändert

- **WebSite und Organization erscheinen weiterhin genau einmal.** Ob das Plugin sie ergänzen muss, hing bisher an einem Schalter, den Shopware mit der nächsten Hauptversion entfernt. Danach hätte die Abfrage das Gegenteil bedeutet und beide Angaben wären doppelt im Kopfbereich gelandet. Die Entscheidung trifft jetzt das Plugin selbst und bleibt über den Versionswechsel hinweg richtig. Sichtbar ändert sich nichts.

## [2.13.1] - 2026-08-10: Vorbereitet auf die nächste Shopware-Hauptversion

### Geändert

- **Vorbereitung auf die nächste Shopware-Hauptversion.** Der Zugriff auf Suchergebnisse folgt der Schreibweise, die Shopware 6.8 verlangt. Am Verhalten ändert sich nichts.

## [2.13.0] - 2026-07-21

> **Deployment:** `php bin/console cache:clear`.

### Behoben

- **FAQ **und** Blog-Artikel auf derselben Seite verlieren ihre Verknüpfung nicht mehr:** Enthält die einem Blog-Artikel zugeordnete CMS-Seite einen FAQ-Block, zeigten FAQPage und BlogPosting beide auf `mainEntity`. Der zuletzt verdrahtete Typ überschrieb den anderen, und dessen Knoten blieb unverbunden im Graph. Beide werden jetzt gemeinsam als `mainEntity` verlinkt.
- **Keine toten `@id`-Referenzen mehr im Graph:** Zeigte eine `publisher`-Referenz auf eine Organization, die (mangels gepflegtem Shop-/Organisationsnamen) gar nicht erzeugt wurde, stand eine ins Leere laufende Referenz im JSON-LD (von Google als „referenced @id not found" beanstandet). Solche toten Referenzen werden jetzt vor der Ausgabe entfernt.

### Geändert

- **YouTube-API-Ausfall belastet die Seite nicht mehr wiederholt:** Ein API-Fehler (z. B. 403 bei erschöpftem Quota) wurde nicht negativ gecacht, und jeder Cache-Miss-Render löste erneut einen bis zu 3 s blockierenden, scheiternden Aufruf aus. Fehler werden jetzt kurz negativ gecacht (Latenz- und Quota-Schutz).
- **Regressions-Sperre:** Neuer Twig-Contract-Test pinnt die drei JSON-LD-Meta-Overrides (`layout_head_json_ld`/`layout_head_json_ld_global`) gegen einen stillen Core-Rename (Phantom-Block-Klasse).

## [2.12.1] - 2026-07-20

> **Deployment:** `php bin/console cache:clear`.

### Behoben

- **Video-Thumbnail im VideoObject-Schema:** Der Fallback-Vorschaubild-Link (wenn am YouTube-Block kein Bild gepflegt ist) nutzte `maxresdefault.jpg`, das nur für Videos mit HD-Thumbnail existiert. Bei SD-/älteren Videos lieferte er 404, und `thumbnailUrl` ist ein von Google gefordertes Feld (tote URL → Rich-Result-Warnung). Umgestellt auf `hqdefault.jpg` (existiert für jedes Video).

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
  Drag&Drop platzierbar. Man wählt selbst, wo die FAQs entstehen, und nutzt das Shopware-CMS normal
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
  mangels Flag nicht liefert. Bei aktivem Flag gibt es keine Dopplung.

## [2.2.0] - 2026-07-01

> **Deployment:** `bin/console plugin:update RcStructuredData` (neues Konfigurationsfeld) und `cache:clear`. Optionaler API-Key unter **Einstellungen → Plugins → RcStructuredData → YouTube-Data-API**.

### Hinzugefügt
- **YouTube-Data-API-Anreicherung** für VideoObject-Metadaten (`YoutubeApiMetadataEnricher`):
  Ist ein API-Key gesetzt, werden fehlende Felder (name, description, uploadDate, duration)
  automatisch per `videos.list` (snippet + contentDetails) geholt. Eingehängt als Stufe in die
  Feld-Kaskade des VideoObject: **Block → Category → API → Auto** (Abruf nur, wenn nach Block +
  Category noch ein Feld fehlt).
- Antworten werden gecacht (Quota-Schonung); Netzwerk-/Quota-Fehler und ein fehlender Key führen
  zum stillen Fallback auf die manuelle Pflege, nie zu einem Seitenfehler. Der API-Key stammt aus
  `system_config` und erscheint weder im Output noch im Log.
- Plugin-Konfiguration: Feld **YouTube-Data-API-Schlüssel** (Typ `password`), DE/EN.

### Behoben / Qualität
- Englische Hilfetexte für `organizationName` und `websiteName` enthielten deutschen Text; das ist korrigiert.
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
  eingebunden) als bearbeitbare Name/Wert-Liste gerendert, an Kategorie und Landingpage.
  Speichert exakt `[{name, value}, …]` (kompatibel zum bestehenden Auslesen im
  `AbstractPageNodeProvider`). Snippets DE/EN.

## [2.0.0] - 2026-06-30

> **Deployment:** `bin/build-storefront.sh` erforderlich (Erstinstallation v2: Storefront-Twig + Admin geändert), anschließend `bin/console plugin:update RcStructuredData` (Custom-Field-Set) und `cache:clear`.

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
- Getaggte `SchemaNodeProvider`-Architektur (`rc_schema.node_provider`). Neue Schema-Typen
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
