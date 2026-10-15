<?php

/*
 * The settings page of the format rules (format-policy::rules).
 */

return [
    'in_force' => 'in force',
    'off' => 'off',
    'follows_default' => 'follows the default',
    'own_setting' => 'own setting',
    'default_on' => 'default: on',
    'default_off' => 'default: off',
    'routes' => 'Ingest routes',
    'routes_all' => 'all',
    'routes_only' => 'only :list',
    'routes_except' => 'all except :list',
    'not_runnable' => 'Cannot run on this server: a tool the rule needs is missing.',
    'save' => 'Save',
    'none' => 'There are no rules.',
    'order_note' => 'Where several rules match a file, they run one after the other in this order; each works on the result of the one before.',
    'if' => 'If',
    'if_format' => 'the file has one of these formats (one match suffices):',
    'mime_types' => 'MIME type',
    'pronom_ids' => 'PRONOM',
    'extensions' => 'Extension',
    'except' => 'Except',
    'except_note' => 'where «:check» finds one of these characteristics:',
    'then' => 'Then',
    'verify' => 'Check: :check. If it fails, the file stays as delivered.',
    'require_smaller' => 'If the result is not smaller, the file stays as delivered.',
    'original_replace' => 'The result replaces the delivered file; its checksums stay in the event on the medium.',
    'original_keep' => 'The delivered file is kept as a version of the medium.',
    'technical' => 'Technical: commands and tools',
    'command' => 'Command',
    'tool' => 'Tool',
    'call' => 'Call',
    'tool_missing' => 'missing on this server',
];
