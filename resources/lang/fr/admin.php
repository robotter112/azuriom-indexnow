<?php

return [
    'title' => 'Sitemap',
    'permission' => 'Gérer le sitemap',

    'url' => 'Votre sitemap',
    'url-hint' => 'Soumettez cette adresse dans la Google Search Console et dans les outils Bing pour les webmasters, puis ajoutez-la à public/robots.txt sous la forme « Sitemap: :url ».',
    'count' => '{0}Aucune URL pour le moment|{1}1 URL|[2,*]:count URLs',
    'cached' => 'En cache, reconstruit automatiquement après :minutes minutes.',
    'not-cached' => 'Pas en cache, la prochaine visite le reconstruit.',

    'settings' => 'Paramètres',
    'cache-minutes' => 'Durée du cache (minutes)',
    'cache-minutes-hint' => 'Durée pendant laquelle la liste des URLs est conservée avant d\'être reconstruite. Les robots d\'indexation la consultent bien moins souvent.',
    'exclude' => 'Chemins exclus',
    'exclude-hint' => 'Un motif par ligne, sans le domaine, par exemple « shop/* ». Les pages protégées par une connexion sont ignorées automatiquement — cette liste sert aux pages dont le plugin redirige lui-même les visiteurs depuis son propre code.',

    'save' => 'Enregistrer',
    'saved' => 'Paramètres enregistrés, le sitemap a été reconstruit.',
    'refresh' => 'Reconstruire maintenant',
    'refreshed' => 'Sitemap reconstruit, :count URLs.',

    'check' => 'Vérifier les URLs',
    'check-hint' => 'Récupère chaque URL comme le ferait un visiteur non connecté. Un sitemap ne doit contenir que des pages qui répondent réellement en 200 — les moteurs de recherche signalent les redirections et les pages de connexion comme des erreurs.',
    'check-ok' => 'Les :count URLs répondent toutes en 200.',
    'check-bad' => ':count URLs sur :total ne répondent pas en 200. Ajoutez leur chemin aux chemins exclus ci-dessus.',
    'check-capped' => 'Seules les :limit premières URLs ont été vérifiées. Utilisez « php artisan sitemap:check » pour toutes les vérifier.',

    'issues-title' => 'Problèmes sur la page',
    'issues-hint' => 'Ces pages ont leur place dans le sitemap, mais un moteur de recherche leur reprochera ce qui suit.',
    'check-all-ok' => 'Les :count URLs répondent en 200 et passent les vérifications de page.',

    'issue' => [
        'h1-missing' => 'Aucun titre h1',
        'h1-multiple' => ':count titres h1 - une page ne devrait en avoir qu\'un seul',
        'description-missing' => 'Aucune méta-description',
        'description-short' => 'Méta-description très courte (:count caractères)',
        'description-long' => 'Méta-description trop longue (:count caractères, coupée vers 160)',
        'title-missing' => 'Aucun titre de page',
        'images-without-alt' => ':count image(s) sans attribut alt exploitable',
    ],

    'robots-title' => 'robots.txt',
    'robots-ok' => 'Votre robots.txt indique le sitemap aux robots d\'indexation.',
    'robots-missing' => 'Votre robots.txt ne mentionne pas le sitemap. Les moteurs de recherche ne le trouveront alors que si vous l\'avez soumis dans une console pour webmasters.',
    'robots-write' => 'Ajouter la ligne Sitemap',
    'robots-written' => 'La ligne Sitemap a été ajoutée au robots.txt.',
    'robots-not-writable' => 'Le robots.txt n\'est pas accessible en écriture (:path). Ajoutez la ligne manuellement.',
    'canonical-title' => 'URL canonique',
    'canonical-enable' => 'Ajouter une URL canonique aux pages qui n\'en ont pas',
    'canonical-hint' => 'Une même page atteinte avec des paramètres de suivi ou de cache ressemble à une adresse distincte pour un moteur de recherche, ce qui divise son classement. Ceci ajoute l\'adresse propre. Une balise déjà posée par votre thème n\'est jamais remplacée.',
    'canonical-keep' => 'Paramètres de requête à conserver',
    'canonical-keep-hint' => 'Séparés par des virgules. Ils modifient le contenu de la page et doivent être conservés. Sans « page », les moteurs de recherche considéreraient la page 2 comme une copie de la page 1 et elle sortirait de l\'index.',
];
