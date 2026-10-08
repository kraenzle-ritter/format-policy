<?php

/*
|--------------------------------------------------------------------------
| Format policy — this program's part
|--------------------------------------------------------------------------
|
| The tools, commands and rules themselves are the package's catalogue
| (resources/catalogue.php). Here a program adds entries of its own under
| new keys — never one the catalogue has — and sets where the preingest works
| and, for a program with one tenant per process, which rules it switches.
|
*/

return [

    // Where the preingest writes intermediate files; each run gets its own
    // directory below, removed once the caller has ingested the file.
    // null: storage/framework/preingest.
    'work_dir' => null,

    // Rule key => true|false, overriding a rule's `enabled_by_default` when
    // no other SwitchStore is bound (ArraySwitchStore).
    'switches' => [],

    'tools' => [],

    'commands' => [],

    'rules' => [],

];
