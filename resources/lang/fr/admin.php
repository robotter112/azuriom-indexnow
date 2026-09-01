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
];
