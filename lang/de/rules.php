<?php

/*
 * The settings page of the format rules (format-policy::rules).
 */

return [
    'in_force' => 'in Kraft',
    'off' => 'aus',
    'follows_default' => 'folgt der Vorgabe',
    'own_setting' => 'eigene Einstellung',
    'default_on' => 'Vorgabe: an',
    'default_off' => 'Vorgabe: aus',
    'routes' => 'Eingangswege',
    'routes_all' => 'alle',
    'routes_only' => 'nur :list',
    'routes_except' => 'alle ausser :list',
    'not_runnable' => 'Auf diesem Server nicht lauffähig: Ein Werkzeug, das die Regel braucht, fehlt.',
    'save' => 'Speichern',
    'none' => 'Es gibt keine Regeln.',
    'order_note' => 'Passen mehrere Regeln auf eine Datei, laufen sie in dieser Reihenfolge nacheinander; jede arbeitet mit dem Ergebnis der vorherigen.',
    'if' => 'Wenn',
    'if_format' => 'die Datei eines dieser Formate hat (eine Angabe genügt):',
    'mime_types' => 'MIME-Typ',
    'pronom_ids' => 'PRONOM',
    'extensions' => 'Endung',
    'except' => 'Ausser',
    'except_note' => 'wenn «:check» eines dieser Merkmale findet:',
    'then' => 'Dann',
    'verify' => 'Prüfung: :check. Schlägt sie fehl, bleibt die Datei wie geliefert.',
    'require_smaller' => 'Wird das Ergebnis nicht kleiner, bleibt die Datei wie geliefert.',
    'original_replace' => 'Das Ergebnis ersetzt die gelieferte Datei; deren Prüfsummen bleiben im Ereignis am Medium.',
    'original_keep' => 'Die gelieferte Datei bleibt als Fassung des Mediums erhalten.',
    'technical' => 'Technik: Befehle und Werkzeuge',
    'command' => 'Befehl',
    'tool' => 'Werkzeug',
    'call' => 'Aufruf',
    'tool_missing' => 'fehlt auf diesem Server',
];
