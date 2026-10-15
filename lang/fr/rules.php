<?php

/*
 * The settings page of the format rules (format-policy::rules).
 */

return [
    'in_force' => 'en vigueur',
    'off' => 'désactivée',
    'follows_default' => 'suit la valeur par défaut',
    'own_setting' => 'réglage propre',
    'default_on' => 'par défaut : activée',
    'default_off' => 'par défaut : désactivée',
    'routes' => 'Voies d’entrée',
    'routes_all' => 'toutes',
    'routes_only' => 'seulement :list',
    'routes_except' => 'toutes sauf :list',
    'not_runnable' => 'Ne peut pas s’exécuter sur ce serveur : un outil requis par la règle manque.',
    'save' => 'Enregistrer',
    'none' => 'Il n’y a aucune règle.',
    'order_note' => 'Si plusieurs règles s’appliquent à un fichier, elles s’exécutent l’une après l’autre dans cet ordre ; chacune travaille sur le résultat de la précédente.',
    'if' => 'Si',
    'if_format' => 'le fichier a l’un de ces formats (une correspondance suffit) :',
    'mime_types' => 'Type MIME',
    'pronom_ids' => 'PRONOM',
    'extensions' => 'Extension',
    'except' => 'Sauf',
    'except_note' => 'si « :check » trouve l’une de ces caractéristiques :',
    'then' => 'Alors',
    'verify' => 'Contrôle : :check. S’il échoue, le fichier reste tel que livré.',
    'require_smaller' => 'Si le résultat n’est pas plus petit, le fichier reste tel que livré.',
    'original_replace' => 'Le résultat remplace le fichier livré ; ses sommes de contrôle restent dans l’événement du média.',
    'original_keep' => 'Le fichier livré est conservé comme version du média.',
    'technical' => 'Technique : commandes et outils',
    'command' => 'Commande',
    'tool' => 'Outil',
    'call' => 'Appel',
    'tool_missing' => 'manquant sur ce serveur',
];
