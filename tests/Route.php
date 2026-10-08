<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Tests;

/** Ingest routes as a program might name them. */
enum Route: string
{
    case Upload = 'upload';
    case Agate = 'agate';
}
