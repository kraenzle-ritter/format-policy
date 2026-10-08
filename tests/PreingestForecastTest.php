<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Tests;

use KraenzleRitter\FormatPolicy\ArraySwitchStore;
use KraenzleRitter\FormatPolicy\PreingestForecast;
use KraenzleRitter\FormatPolicy\SwitchStore;

/**
 * What the preingest would at most do to the files of an import: counted by
 * extension and MIME type, without calling a tool.
 */
final class PreingestForecastTest extends TestCase
{
    public function test_the_forecast_counts_the_files_a_rule_may_act_on(): void
    {
        $this->app->instance(SwitchStore::class, new ArraySwitchStore(['tiff_zip' => true]));
        $files = [__DIR__.'/fixtures/lzw.tif', __DIR__.'/fixtures/gray.tif', __DIR__.'/fixtures/blank.pdf'];

        $forecast = app(PreingestForecast::class)->forFiles($files, 'media_add');

        $this->assertCount(1, $forecast);
        $this->assertSame('tiff_zip', $forecast[0]->rule);
        $this->assertSame([2, 3], [$forecast[0]->count, $forecast[0]->total]);
        $this->assertSame([], app(PreingestForecast::class)->forFiles($files, 'sip_ech'), 'a route the rule excludes');
    }

    public function test_a_rule_that_is_off_is_not_forecast(): void
    {
        $this->assertSame([], app(PreingestForecast::class)->forFiles([__DIR__.'/fixtures/lzw.tif'], 'media_add'));
    }
}
