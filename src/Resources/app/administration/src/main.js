/**
 * Administrations-Einstiegspunkt des Plugins.
 *
 * Seit v2.0.0 erzeugt das Plugin die strukturierten Daten zentral im Storefront-Graph-Builder
 * (PHP). Hier werden die CMS-Elemente `rc-faq` (Anzeige + Datenquelle für den FAQPage-Knoten) und
 * `rc-defined-terms` (Begriffssatz + `DefinedTermSet`) und `rc-documents` (Nachweise +
 * `DigitalDocument`) samt ihren frei platzierbaren Blöcken
 * registriert.
 */
import './module/sw-cms/element/rc-faq';
import './module/sw-cms/block/rc-faq';
import './module/sw-cms/element/rc-defined-terms';
import './module/sw-cms/block/rc-defined-terms';
import './module/sw-cms/element/rc-documents';
import './module/sw-cms/block/rc-documents';
import './module/sw-cms/element/youtube-video';
import './component/rc-schema-additional-property';
import './service/rc-schema-diagnose.api.service';
import './component/sw-rc-schema-diagnose-card';
import './extension/sw-category-detail-base';
import './extension/sw-landing-page-detail-base';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

/*
 * Übersetzungen werden über `Locale.extend` angemeldet. Den früheren Umweg über einen
 * Initializer-Dekorator für `locale` gibt es in Shopware 6.7 nicht mehr — der Aufruf warf bei
 * jedem Laden der Administration eine Ausnahme. Folgenlos war er trotzdem, weil die
 * Übersetzungen über die serverseitige Auslieferung ankommen; ein roter Eintrag in der Konsole
 * macht aber jede echte Meldung schwerer auffindbar.
 */
const { Locale } = Shopware;

Locale.extend('de-DE', deDE);
Locale.extend('en-GB', enGB);
