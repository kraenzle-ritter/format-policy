<?php

/*
 * The settings page of the format rules (format-policy::rules).
 */

return [
    'in_force' => 'in vigore',
    'off' => 'disattivata',
    'follows_default' => 'segue il valore predefinito',
    'own_setting' => 'impostazione propria',
    'default_on' => 'predefinito: attiva',
    'default_off' => 'predefinito: disattivata',
    'routes' => 'Vie d’ingresso',
    'routes_all' => 'tutte',
    'routes_only' => 'solo :list',
    'routes_except' => 'tutte tranne :list',
    'not_runnable' => 'Non eseguibile su questo server: manca uno strumento richiesto dalla regola.',
    'save' => 'Salva',
    'none' => 'Non ci sono regole.',
    'order_note' => 'Se più regole si applicano a un file, vengono eseguite una dopo l’altra in questo ordine; ognuna lavora sul risultato della precedente.',
    'if' => 'Se',
    'if_format' => 'il file ha uno di questi formati (basta una corrispondenza):',
    'mime_types' => 'Tipo MIME',
    'pronom_ids' => 'PRONOM',
    'extensions' => 'Estensione',
    'except' => 'Tranne',
    'except_note' => 'se «:check» trova una di queste caratteristiche:',
    'then' => 'Allora',
    'verify' => 'Verifica: :check. Se fallisce, il file resta come consegnato.',
    'require_smaller' => 'Se il risultato non è più piccolo, il file resta come consegnato.',
    'original_replace' => 'Il risultato sostituisce il file consegnato; le sue somme di controllo restano nell’evento del media.',
    'original_keep' => 'Il file consegnato viene conservato come versione del media.',
    'technical' => 'Tecnica: comandi e strumenti',
    'command' => 'Comando',
    'tool' => 'Strumento',
    'call' => 'Chiamata',
    'tool_missing' => 'mancante su questo server',
];
