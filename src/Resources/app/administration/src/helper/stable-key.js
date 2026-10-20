/**
 * Ein Schlüssel je Listeneintrag, der beim Entfernen oder Verschieben anderer Einträge gleich bleibt. Mit dem Index als
 * Schlüssel nähme Vue nach dem Entfernen eines Eintrags die Eingabefelder des nächsten für den falschen Eintrag.
 *
 * Der Schlüssel hängt am Objekt selbst (WeakMap) und landet nicht in der gespeicherten Konfiguration.
 */
const keys = new WeakMap();
let counter = 0;

export function stableKey(item) {
    if (item === null || typeof item !== 'object') {
        return String(item);
    }
    if (!keys.has(item)) {
        counter += 1;
        keys.set(item, `rc-item-${counter}`);
    }

    return keys.get(item);
}
