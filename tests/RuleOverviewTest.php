<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Tests;

use KraenzleRitter\FormatPolicy\ArraySwitchStore;
use KraenzleRitter\FormatPolicy\CommandRunner;
use KraenzleRitter\FormatPolicy\ConfigFormatPolicyRepository;
use KraenzleRitter\FormatPolicy\FormatPolicyRepository;
use KraenzleRitter\FormatPolicy\RuleOverview;
use KraenzleRitter\FormatPolicy\RuleOverviewEntry;
use KraenzleRitter\FormatPolicy\RuleSwitches;

/**
 * The settings page of the format rules (anton#655): the overview it shows,
 * the switches it saves, and the view Anton and agate both include.
 */
final class RuleOverviewTest extends TestCase
{
    private ConfigFormatPolicyRepository $policy;

    private ArraySwitchStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ConfigFormatPolicyRepository($this->policyArray());
        $this->app->instance(FormatPolicyRepository::class, $this->policy);
        $this->store = new ArraySwitchStore(['qj_off_by_default' => true]);
    }

    public function test_the_overview_names_each_rule_with_what_holds_for_the_tenant(): void
    {
        $entries = $this->keyed($this->overview()->entries);

        $this->assertSame(['qj_off_by_default', 'qj_on_by_default', 'qj_needs_a_missing_tool', 'qj_upload_only'], array_keys($entries), 'retired rules are left out');

        $on = $entries['qj_on_by_default'];
        $this->assertSame([null, true, true], [$on->override, $on->enabledByDefault, $on->inForce]);
        $this->assertSame('Halbieren', $on->label->in('de'));
        $this->assertSame([], $on->missingTools);
        $this->assertTrue($on->runsHere());
        $this->assertNull($on->admits, 'no route asked about');

        $off = $entries['qj_off_by_default'];
        $this->assertSame([true, false, true], [$off->override, $off->enabledByDefault, $off->inForce], 'the tenant switched it on');

        $missing = $entries['qj_needs_a_missing_tool'];
        $this->assertSame(['qj_absent'], $missing->missingTools);
        $this->assertFalse($missing->runsHere());

        $this->assertSame(['upload'], $entries['qj_upload_only']->sources);
        $this->assertSame(['sip_ech'], $on->excludeSources);
    }

    public function test_asked_about_a_route_the_overview_says_which_rules_admit_it(): void
    {
        $overview = $this->overview('sip_ech');
        $entries = $this->keyed($overview->entries);

        $this->assertFalse($entries['qj_on_by_default']->admits);
        $this->assertFalse($entries['qj_upload_only']->admits);
        $this->assertTrue($entries['qj_off_by_default']->admits);
        $this->assertSame(['qj_off_by_default', 'qj_needs_a_missing_tool'], array_keys($this->keyed($overview->admitting())));
    }

    public function test_the_form_sets_what_changed_and_says_so(): void
    {
        $switches = new RuleSwitches($this->policy, $this->store);

        $changes = $switches->apply([
            'qj_on_by_default' => '0',
            'qj_off_by_default' => '1',
            'qj_upload_only' => '1',
            'qj_retired' => '1',
            'qj_unknown' => '1',
        ]);

        $this->assertSame([
            ['rule' => 'qj_on_by_default', 'from' => true, 'to' => false],
            ['rule' => 'qj_upload_only', 'from' => false, 'to' => true],
        ], $changes);
        $this->assertSame(['qj_off_by_default' => true, 'qj_on_by_default' => false, 'qj_upload_only' => true], $this->store->overrides(), 'unchanged, retired and unknown keys are not written');
    }

    public function test_the_view_is_a_form_with_one_switch_per_rule(): void
    {
        $html = view('format-policy::rules', [
            'overview' => $this->overview(),
            'editable' => true,
            'action' => 'https://anton.example/admin/format-policy',
            'method' => 'PUT',
            'sourceLabels' => ['sip_ech' => 'SIP eCH-0160'],
        ])->render();

        $this->assertStringContainsString('action="https://anton.example/admin/format-policy"', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('<input type="hidden" name="rules[qj_on_by_default]" value="0">', $html);
        $this->assertMatchesRegularExpression('/name="rules\[qj_on_by_default\]" value="1"\s+aria-label="Halve"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="rules\[qj_upload_only\]" value="1"\s+aria-label="[^"]+"\s+checked/', $html);
        $this->assertStringNotContainsString('qj_retired', $html);
        $this->assertStringContainsString(__('format-policy::rules.routes_except', ['list' => 'SIP eCH-0160']), $html);
        $this->assertStringContainsString(__('format-policy::rules.not_runnable'), $html);
    }

    public function test_read_only_the_view_names_where_the_rules_are_set(): void
    {
        app()->setLocale('de');

        $html = view('format-policy::rules', [
            'overview' => $this->overview(),
            'editable' => false,
            'managedBy' => ['text' => 'Festgelegt in Anton', 'url' => 'https://anton.example/admin/format-policy'],
        ])->render();

        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('name="rules[', $html);
        $this->assertStringContainsString('<a href="https://anton.example/admin/format-policy">Festgelegt in Anton</a>', $html);
        $this->assertStringContainsString('Halbieren', $html);
        $this->assertStringContainsString('in Kraft', $html);
    }

    public function test_the_catalogue_speaks_the_four_languages_of_anton(): void
    {
        $rule = (new ConfigFormatPolicyRepository)->rule('tiff_zip');

        foreach (['en', 'de', 'fr', 'it'] as $locale) {
            $this->assertArrayHasKey($locale, $rule->label->texts, $locale);
            $this->assertArrayHasKey($locale, $rule->description->texts, $locale);
        }
        $this->assertSame('TIFF verlustfrei komprimieren (Deflate, Predictor 2)', $rule->label->in('de'));
    }

    private function overview(?string $source = null): RuleOverview
    {
        return RuleOverview::for($this->policy, $this->store, app(CommandRunner::class), $source);
    }

    /**
     * @param  list<RuleOverviewEntry>  $entries
     * @return array<string, RuleOverviewEntry>
     */
    private function keyed(array $entries): array
    {
        $keyed = [];
        foreach ($entries as $entry) {
            $keyed[$entry->key] = $entry;
        }

        return $keyed;
    }

    /**
     * @return array<string, mixed>
     */
    private function policyArray(): array
    {
        $text = fn (string $en, ?string $de = null): array => array_filter(['en' => $en, 'de' => $de]);
        $command = ['label' => $text('Command'), 'description' => $text('A command.'), 'kind' => 'transform', 'tool' => 'sh', 'template' => '{binary} -c true'];
        $rule = fn (array $entry): array => $entry + [
            'label' => $text('Halve', 'Halbieren'),
            'description' => $text('Halves a text file.', 'Halbiert eine Textdatei.'),
            'phase' => 'preingest',
            'match' => ['extensions' => ['txt']],
            'transform' => ['qj_true'],
            'original' => 'replace',
        ];

        return [
            'tools' => [
                'sh' => ['label' => $text('Shell'), 'description' => $text('The shell.'), 'binaries' => ['sh'], 'version_command' => 'echo 1.0'],
                'qj_absent' => ['label' => $text('Absent'), 'description' => $text('Not installed.'), 'binaries' => ['qj-no-such-program'], 'version_command' => 'echo 1.0'],
            ],
            'commands' => [
                'qj_true' => $command,
                'qj_absent_true' => ['tool' => 'qj_absent'] + $command,
            ],
            'rules' => [
                'qj_on_by_default' => $rule(['enabled_by_default' => true, 'exclude_sources' => ['sip_ech'], 'priority' => 400]),
                'qj_off_by_default' => $rule(['enabled_by_default' => false, 'priority' => 500]),
                'qj_needs_a_missing_tool' => $rule(['transform' => ['qj_absent_true'], 'priority' => 300]),
                'qj_upload_only' => $rule(['sources' => ['upload'], 'priority' => 200]),
                'qj_retired' => $rule(['retired' => true, 'enabled_by_default' => true, 'priority' => 100]),
            ],
        ];
    }
}
