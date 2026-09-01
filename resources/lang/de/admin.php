<?php

return [
    'title' => 'Sitemap',
    'permission' => 'Sitemap verwalten',

    'url' => 'Deine Sitemap',
    'url-hint' => 'Diese Adresse in der Google Search Console und den Bing Webmaster Tools einreichen und in public/robots.txt als „Sitemap: :url" eintragen.',
    'count' => '{0}Noch keine URL|{1}1 URL|[2,*]:count URLs',
    'cached' => 'Zwischengespeichert, wird nach :minutes Minuten neu aufgebaut.',
    'not-cached' => 'Nicht zwischengespeichert, der nächste Aufruf baut sie neu auf.',

    'settings' => 'Einstellungen',
    'cache-minutes' => 'Haltbarkeit des Zwischenspeichers (Minuten)',
    'cache-minutes-hint' => 'Wie lange die URL-Liste gehalten wird, bevor sie neu gebaut wird. Suchmaschinen rufen die Sitemap deutlich seltener ab.',
    'exclude' => 'Ausgeschlossene Pfade',
    'exclude-hint' => 'Ein Muster pro Zeile, ohne Domain, z.B. „shop/*". Seiten hinter einer Anmeldung fallen automatisch heraus – diese Liste ist für Seiten, deren Plugin Gäste erst im eigenen Code wegleitet.',

    'save' => 'Speichern',
    'saved' => 'Einstellungen gespeichert, die Sitemap wurde neu aufgebaut.',
    'refresh' => 'Jetzt neu aufbauen',
    'refreshed' => 'Sitemap neu aufgebaut, :count URLs.',

    'check' => 'URLs prüfen',
    'check-hint' => 'Ruft jede URL so ab, wie ein nicht angemeldeter Besucher sie sieht. In eine Sitemap gehören nur Seiten, die wirklich mit 200 antworten – Weiterleitungen und Anmeldeschranken melden Suchmaschinen als Fehler.',
    'check-ok' => 'Alle :count URLs antworten mit 200.',
    'check-bad' => ':count von :total URLs antworten nicht mit 200. Trage ihren Pfad oben bei den ausgeschlossenen Pfaden ein.',
    'check-capped' => 'Es wurden nur die ersten :limit URLs geprüft. Für alle: „php artisan sitemap:check".',

    'issues-title' => 'Auffälligkeiten auf der Seite',
    'issues-hint' => 'Diese Seiten gehören in die Sitemap, aber eine Suchmaschine rechnet ihnen Folgendes an.',
    'check-all-ok' => 'Alle :count URLs antworten mit 200 und bestehen die Seitenprüfung.',

    'issue' => [
        'h1-missing' => 'Keine h1-Überschrift',
        'h1-multiple' => ':count h1-Überschriften – eine Seite sollte genau eine haben',
        'description-missing' => 'Keine Meta-Beschreibung',
        'description-short' => 'Meta-Beschreibung sehr kurz (:count Zeichen)',
        'description-long' => 'Meta-Beschreibung zu lang (:count Zeichen, abgeschnitten wird bei etwa 160)',
        'title-missing' => 'Kein Seitentitel',
        'images-without-alt' => ':count Bild(er) ohne brauchbares alt-Attribut',
    ],
];
