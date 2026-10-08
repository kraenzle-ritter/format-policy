<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Tests;

use KraenzleRitter\FormatPolicy\ArraySwitchStore;
use KraenzleRitter\FormatPolicy\ConfigFormatPolicyRepository;
use KraenzleRitter\FormatPolicy\FormatPolicyRepository;
use KraenzleRitter\FormatPolicy\Preingest;
use KraenzleRitter\FormatPolicy\Rule;
use KraenzleRitter\FormatPolicy\RuleSwitches;
use KraenzleRitter\FormatPolicy\Tool;
use KraenzleRitter\FormatPolicy\ToolLocator;

/**
 * The format policy: tools,
 * commands and rules, their references, their descriptions, and the
 * switches per tenant.
 */
final class FormatPolicyRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

    }

    public function test_the_shipped_policy_has_no_problems(): void
    {
        $policy = new ConfigFormatPolicyRepository;

        $this->assertSame([], $policy->problems());
        $this->assertNotNull($policy->rule('tiff_zip'));
    }

    public function test_a_rule_naming_a_missing_command_is_reported_and_left_out(): void
    {
        $policy = new ConfigFormatPolicyRepository($this->policy(['transform' => ['qj_missing']]));

        $this->assertSame([], $policy->rules());
        $this->assertStringContainsString('«qj_missing»', implode("\n", $policy->problems()));
    }

    public function test_a_command_naming_a_missing_tool_takes_its_rules_out(): void
    {
        $policy = $this->policy([]);
        $policy['commands']['copy']['tool'] = 'qj_missing_tool';

        $repository = new ConfigFormatPolicyRepository($policy);

        $this->assertSame([], $repository->rules());
        $this->assertStringContainsString('«qj_missing_tool»', implode("\n", $repository->problems()));
    }

    public function test_an_entry_without_english_text_is_a_problem(): void
    {
        $policy = $this->policy([]);
        $policy['tools']['cp']['description'] = ['de' => 'Kopiert Dateien'];

        $this->assertStringContainsString('«cp»', implode("\n", (new ConfigFormatPolicyRepository($policy))->problems()));
    }

    public function test_a_description_falls_back_to_english(): void
    {
        $rule = (new ConfigFormatPolicyRepository($this->policy([])))->rule('qj_rule');

        $this->assertSame('Copies a file.', $rule?->description->in('de'));
    }

    public function test_switches_override_the_default_either_way(): void
    {
        $policy = new ConfigFormatPolicyRepository($this->policy([], ['qj_on' => true, 'qj_off' => false]));
        $switches = new RuleSwitches($policy, new ArraySwitchStore);
        $on = $policy->rule('qj_on');
        $off = $policy->rule('qj_off');

        $this->assertTrue($switches->isInForce($on));
        $this->assertFalse($switches->isInForce($off));

        $switches->set($on, false);
        $switches->set($off, true);

        $this->assertFalse($switches->isInForce($on), 'a default-on rule switched off');
        $this->assertTrue($switches->isInForce($off), 'a default-off rule switched on');
    }

    public function test_a_retired_rule_is_never_in_force(): void
    {
        $policy = new ConfigFormatPolicyRepository($this->policy(['retired' => true]));
        $switches = new RuleSwitches($policy, new ArraySwitchStore);
        $switches->set($policy->rule('qj_rule'), true);

        $this->assertFalse($switches->isInForce($policy->rule('qj_rule')));
    }

    public function test_routes_include_and_exclude(): void
    {
        $excluding = (new ConfigFormatPolicyRepository($this->policy(['exclude_sources' => ['sip_ech']])))->rule('qj_rule');
        $listing = (new ConfigFormatPolicyRepository($this->policy(['sources' => ['media_add']])))->rule('qj_rule');

        $this->assertFalse($excluding->admits('sip_ech'));
        $this->assertTrue($excluding->admits('unknown'));
        $this->assertTrue($listing->admits('media_add'));
        $this->assertFalse($listing->admits('unknown'), 'unknown does not match a source list');
    }

    /**
     * A route is whatever string the calling program names; a backed enum
     * counts by its value.
     */
    public function test_a_route_is_any_string(): void
    {
        $rule = (new ConfigFormatPolicyRepository($this->policy(['exclude_sources' => ['agate']])))->rule('qj_rule');

        $this->assertFalse($rule->admits('agate'));
        $this->assertTrue($rule->admits('upload'));
        $this->assertFalse($rule->admits(Route::Agate));
        $this->assertTrue($rule->admits(Route::Upload));
    }

    /**
     * A program serving several tenants in one process gives each its own
     * switches; the container's stay those of the config.
     */
    public function test_each_tenant_gets_its_own_switches(): void
    {
        $this->app->instance(FormatPolicyRepository::class, new ConfigFormatPolicyRepository($this->policy([])));
        $preingest = app(Preingest::class);

        $keys = fn (Preingest $preingest): array => array_map(fn (Rule $rule): string => $rule->key, $preingest->rulesInForce('upload'));

        $this->assertSame(['qj_rule'], $keys($preingest->withSwitches(new ArraySwitchStore(['qj_rule' => true]))));
        $this->assertSame([], $keys($preingest->withSwitches(new ArraySwitchStore)));
        $this->assertSame([], $keys($preingest), 'the config is untouched and the rule off by default');
    }

    public function test_a_program_adds_rules_but_does_not_redefine_the_catalogue(): void
    {
        $catalogue = $this->policy([]);
        $additions = $this->policy([]);
        $additions['rules']['qj_own'] = $additions['rules']['qj_rule'];

        [$merged, $problems] = ConfigFormatPolicyRepository::merge($catalogue, $additions);

        $this->assertSame(['qj_rule', 'qj_own'], array_keys($merged['rules']));
        $this->assertSame($catalogue['rules']['qj_rule'], $merged['rules']['qj_rule']);
        $this->assertStringContainsString('«qj_rule»', implode("\n", $problems));
        $this->assertStringContainsString('«cp»', implode("\n", $problems), 'tools and commands as well');
    }

    public function test_the_repository_reads_the_catalogue_and_the_additions_of_the_config(): void
    {
        $own = $this->policy([])['rules']['qj_rule'];
        $own['transform'] = ['tiffcp_zip'];
        config(['format-policy.rules' => ['qj_own' => $own, 'tiff_zip' => $own]]);

        $policy = new ConfigFormatPolicyRepository;

        $this->assertSame(['tiffcp_zip'], $policy->rule('qj_own')?->transforms);
        $this->assertNotSame(['tiffcp_zip'], $policy->rule('tiff_zip')?->transforms, 'the catalogue entry stays');
        $this->assertCount(1, $policy->problems());
    }

    public function test_only_the_repository_and_the_provider_read_the_config(): void
    {
        $readers = array_filter(
            $this->phpFiles(dirname(__DIR__).'/src'),
            fn (string $file): bool => str_contains((string) file_get_contents($file), "config('format-policy"),
        );
        sort($readers);

        $this->assertSame([
            realpath(dirname(__DIR__).'/src/ConfigFormatPolicyRepository.php'),
            realpath(dirname(__DIR__).'/src/FormatPolicyServiceProvider.php'),
        ], array_map('realpath', $readers));
    }

    public function test_a_missing_program_makes_a_tool_unavailable(): void
    {
        $locator = new ToolLocator;

        $this->assertFalse($locator->isAvailable(Tool::fromArray('qj', ['binaries' => ['qj-no-such-program']])));
        $this->assertTrue($locator->isAvailable(Tool::fromArray('sh', ['binaries' => ['sh']])));
    }

    /**
     * A small policy: one tool, one command, rules built from `$rule`.
     *
     * @param  array<string, mixed>  $rule
     * @param  array<string, bool>  $defaults  extra rules by key => enabled_by_default
     * @return array<string, mixed>
     */
    private function policy(array $rule, array $defaults = []): array
    {
        $text = fn (string $en): array => ['en' => $en];
        $base = ['label' => $text('Copy'), 'description' => $text('Copies a file.'), 'phase' => 'preingest', 'match' => ['extensions' => ['txt']], 'transform' => ['copy']];
        $rules = ['qj_rule' => array_merge($base, $rule)];
        foreach ($defaults as $key => $enabled) {
            $rules[$key] = $base + ['enabled_by_default' => $enabled];
        }

        return [
            'tools' => ['cp' => ['label' => $text('cp'), 'description' => $text('Copies files.'), 'binaries' => ['cp']]],
            'commands' => ['copy' => ['label' => $text('Copy'), 'description' => $text('Copies.'), 'kind' => 'transform', 'tool' => 'cp', 'template' => '{binary} {source} {target}']],
            'rules' => $rules,
        ];
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $dir): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
