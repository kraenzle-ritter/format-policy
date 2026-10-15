<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Tests;

use KraenzleRitter\FormatPolicy\Command;
use KraenzleRitter\FormatPolicy\ConfigFormatPolicyRepository;
use KraenzleRitter\FormatPolicy\FormatPolicyRepository;
use KraenzleRitter\FormatPolicy\Handlers\CharacterizesFile;
use KraenzleRitter\FormatPolicy\Handlers\IdentifiesFormat;
use KraenzleRitter\FormatPolicy\Handlers\VerifiesResult;
use KraenzleRitter\FormatPolicy\Identification;
use KraenzleRitter\FormatPolicy\Preingest;
use KraenzleRitter\FormatPolicy\Preingest\PreingestResult;
use KraenzleRitter\FormatPolicy\Preingest\RuleOutcome;
use KraenzleRitter\FormatPolicy\Preingest\RuleVerdict;
use KraenzleRitter\FormatPolicy\Verification;

/**
 * The preingest with a small policy
 * built on `sh`: a rule that halves a text file, verified by a handler the
 * test controls.
 */
final class PreingestTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    /** @var list<PreingestResult> */
    private array $results = [];

    /** What the handlers saw and what they answer. */
    private \stdClass $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->state = (object) ['identifications' => 0, 'verificationPasses' => true];

        $this->app->instance('qj.identify', new class($this->state) implements IdentifiesFormat
        {
            public function __construct(private \stdClass $state) {}

            public function identify(Command $command, string $binary, string $file): Identification
            {
                $this->state->identifications++;

                return pathinfo($file, PATHINFO_EXTENSION) === 'csv'
                    ? new Identification('x-fmt/18', 'text/csv', 'Comma Separated Values')
                    : new Identification('x-fmt/111', 'text/plain', 'Plain Text File');
            }
        });
        $this->app->instance('qj.verify', new class($this->state) implements VerifiesResult
        {
            public function __construct(private \stdClass $state) {}

            public function verify(Command $command, string $binary, string $original, string $result): Verification
            {
                $passes = (bool) $this->state->verificationPasses;

                return new Verification($passes, $passes ? 'equal' : 'the pixels differ on page 1');
            }
        });
        $this->app->instance('qj.characterize', new class implements CharacterizesFile
        {
            public function characterize(Command $command, string $binary, string $file): array
            {
                return ['packed' => str_starts_with((string) file_get_contents($file), 'PACKED')];
            }
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->results as $result) {
            $result->cleanup();
        }
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_a_tenant_without_a_rule_calls_no_command(): void
    {
        $this->usePolicy(['enabled_by_default' => false]);
        $file = $this->textFile();

        $result = $this->preingest($file, 'upload');

        $this->assertSame($file, $result->path);
        $this->assertFalse($result->changed());
        $this->assertSame([], $result->outcomes);
        $this->assertSame(0, $this->state->identifications, 'not even identified');
        $this->assertNull($result->characteristics);
    }

    public function test_a_converted_file_is_verified_and_described(): void
    {
        $this->usePolicy([]);
        $file = $this->textFile();
        $md5 = md5_file($file);

        $result = $this->preingest($file, 'media_add');

        $this->assertTrue($result->changed());
        $this->assertSame(10, filesize($result->path));
        $this->assertSame($md5, md5_file($file), 'the delivered file is not touched');
        $this->assertCount(1, $result->outcomes);

        $outcome = $result->outcomes[0];
        $this->assertSame(RuleOutcome::OK, $outcome->status);
        $this->assertSame('qj_half', $outcome->details['rule']);
        $this->assertSame('media_add', $outcome->details['source']);
        $this->assertArrayHasKey('sh', $outcome->details['tools']);
        $this->assertSame($md5, $outcome->details['before']['md5']);
        $this->assertSame(md5_file($result->path), $outcome->details['after']['md5']);
        $this->assertSame('x-fmt/111', $outcome->details['after']['pronom']);
        $this->assertArrayNotHasKey('path', $outcome->details['after'], 'working paths stay out of the event');
        $this->assertSame(['command' => 'qj_verify', 'passed' => true, 'detail' => 'equal'], $outcome->details['verification']);
        $this->assertSame('discarded', $outcome->details['original']);

        $this->assertWorkDirIsRemoved($result);
    }

    public function test_a_failing_command_ingests_the_original(): void
    {
        $this->usePolicy(['transform' => ['qj_fail']]);
        $file = $this->textFile();

        $result = $this->preingest($file, 'upload');

        $this->assertSame($file, $result->path);
        $this->assertSame(RuleOutcome::FAILED, $result->outcomes[0]->status);
        $this->assertSame('ingested', $result->outcomes[0]->details['original']);
        $this->assertStringContainsString('ended with 3', $result->outcomes[0]->details['message']);
        $this->assertWorkDirIsRemoved($result);
    }

    public function test_a_command_over_its_time_limit_ingests_the_original(): void
    {
        $this->usePolicy(['transform' => ['qj_slow']]);
        $file = $this->textFile();

        $result = $this->preingest($file, 'upload');

        $this->assertSame($file, $result->path);
        $this->assertSame(RuleOutcome::FAILED, $result->outcomes[0]->status);
        $this->assertWorkDirIsRemoved($result);
    }

    public function test_a_result_that_fails_verification_is_not_ingested(): void
    {
        $this->usePolicy([]);
        $this->state->verificationPasses = false;
        $file = $this->textFile();

        $result = $this->preingest($file, 'upload');

        $this->assertSame($file, $result->path);
        $this->assertSame(RuleOutcome::FAILED, $result->outcomes[0]->status);
        $this->assertStringContainsString('the pixels differ on page 1', $result->outcomes[0]->details['message']);
        $this->assertArrayNotHasKey('after', $result->outcomes[0]->details);
    }

    public function test_a_result_that_is_not_smaller_is_only_noted(): void
    {
        $this->usePolicy(['transform' => ['qj_double']]);
        $file = $this->textFile();

        $result = $this->preingest($file, 'upload');

        $this->assertSame($file, $result->path);
        $this->assertSame(RuleOutcome::INFO, $result->outcomes[0]->status);
        $this->assertSame('ingested', $result->outcomes[0]->details['original']);
    }

    public function test_the_precheck_leaves_a_file_alone(): void
    {
        $this->usePolicy(['precheck' => ['command' => 'qj_check', 'skip_when' => [['packed' => true]]]]);
        $file = $this->textFile('PACKED and some more text');

        $result = $this->preingest($file, 'upload');

        $this->assertFalse($result->changed());
        $this->assertSame([], $result->outcomes, 'no event for a rule that does not apply');
    }

    public function test_a_file_of_another_format_is_left_alone(): void
    {
        $this->usePolicy([]);
        $file = $this->textFile(extension: 'csv');

        $this->assertSame([], $this->preingest($file, 'upload')->outcomes);
    }

    public function test_routes_are_honoured(): void
    {
        $this->usePolicy(['exclude_sources' => ['sip_ech']]);
        $this->assertSame([], $this->preingest($this->textFile(), 'sip_ech')->outcomes);
        $this->assertCount(1, $this->preingest($this->textFile(), 'unknown')->outcomes);

        $this->usePolicy(['sources' => ['media_add']]);
        $this->assertSame([], $this->preingest($this->textFile(), 'unknown')->outcomes, 'unknown does not match a source list');
        $this->assertCount(1, $this->preingest($this->textFile(), 'media_add')->outcomes);
    }

    /**
     * There is no deselection per run any more (anton#655): an archive sets
     * its rules once, a delivery cannot leave one out.
     */
    public function test_a_run_cannot_leave_out_a_rule_in_force(): void
    {
        $this->usePolicy(['optional' => true]);

        $this->assertCount(1, $this->preingest($this->textFile(), 'upload', ['skip_rules' => ['qj_half']])->outcomes);
    }

    public function test_a_rule_whose_tool_is_missing_is_not_applied(): void
    {
        $policy = $this->policy([]);
        $policy['tools']['sh']['binaries'] = ['qj-no-such-program'];
        $this->app->instance(FormatPolicyRepository::class, new ConfigFormatPolicyRepository($policy));

        $this->assertSame([], $this->preingest($this->textFile(), 'upload')->outcomes);
    }

    public function test_the_plan_names_the_rule_and_changes_nothing(): void
    {
        $this->usePolicy([]);
        $file = $this->textFile();
        $md5 = md5_file($file);
        $before = (array) glob(storage_path('framework/preingest/*'));

        $plan = app(Preingest::class)->plan($file, 'upload');

        $this->assertCount(1, $plan);
        $this->assertSame('qj_half', $plan[0]->rule);
        $this->assertSame('Halves a text file.', $plan[0]->description);
        $this->assertSame($md5, md5_file($file));
        $this->assertSame($before, (array) glob(storage_path('framework/preingest/*')), 'no working directory');
    }

    public function test_the_probe_says_a_rule_applies_and_changes_nothing(): void
    {
        $this->usePolicy(['precheck' => ['command' => 'qj_check', 'skip_when' => [['packed' => true]]]]);
        $file = $this->textFile();
        $md5 = md5_file($file);
        $before = (array) glob(storage_path('framework/preingest/*'));

        $probe = app(Preingest::class)->probe($file, 'upload');

        $this->assertSame('x-fmt/111', $probe->identification->pronomId);
        $this->assertSame(filesize($file), $probe->size);
        $this->assertCount(1, $probe->verdicts);
        $this->assertSame(['qj_half', RuleVerdict::APPLIES], [$probe->verdicts[0]->rule, $probe->verdicts[0]->verdict]);
        $this->assertCount(1, $probe->applying());
        $this->assertSame(['packed' => false], $probe->characteristics['qj_check']['facts'], 'what the precheck measured');
        $this->assertSame($md5, md5_file($file));
        $this->assertSame($before, (array) glob(storage_path('framework/preingest/*')), 'no working directory');
    }

    public function test_the_probe_names_the_condition_that_vetoed_a_rule(): void
    {
        $this->usePolicy(['precheck' => ['command' => 'qj_check', 'skip_when' => [['packed' => false], ['packed' => true]]]]);

        $verdict = app(Preingest::class)->probe($this->textFile('PACKED and some more text'), 'upload')->verdicts[0];

        $this->assertSame(RuleVerdict::PRECHECK, $verdict->verdict);
        $this->assertSame(['packed' => true], $verdict->condition);
        $this->assertFalse($verdict->applies());
    }

    public function test_the_probe_names_a_file_of_another_format(): void
    {
        $this->usePolicy([]);

        $probe = app(Preingest::class)->probe($this->textFile(extension: 'csv'), 'upload');

        $this->assertSame(RuleVerdict::OTHER_FORMAT, $probe->verdicts[0]->verdict);
        $this->assertSame([], $probe->applying());
    }

    public function test_the_probe_names_a_failed_precheck(): void
    {
        $this->usePolicy(['precheck' => ['command' => 'qj_check_fails', 'skip_when' => [['packed' => true]]]]);

        $this->assertSame(RuleVerdict::PRECHECK_FAILED, app(Preingest::class)->probe($this->textFile(), 'upload')->verdicts[0]->verdict);
    }

    /**
     * run() leaves such a rule out with a line in the log; the probe says
     * so where the person preparing an import can see it.
     */
    public function test_the_probe_names_the_missing_tools(): void
    {
        $policy = $this->policy([]);
        $policy['tools']['sh']['binaries'] = ['qj-no-such-program'];
        $this->app->instance(FormatPolicyRepository::class, new ConfigFormatPolicyRepository($policy));

        $verdict = app(Preingest::class)->probe($this->textFile(), 'upload')->verdicts[0];

        $this->assertSame(RuleVerdict::TOOLS_MISSING, $verdict->verdict);
        $this->assertSame(['sh'], $verdict->missingTools);
    }

    public function test_the_probe_of_a_route_without_rules_identifies_nothing(): void
    {
        $this->usePolicy(['exclude_sources' => ['sip_ech']]);

        $probe = app(Preingest::class)->probe($this->textFile(), 'sip_ech');

        $this->assertSame([], $probe->verdicts);
        $this->assertSame(0, $this->state->identifications);
    }

    public function test_the_checksum_of_a_delivery_is_kept(): void
    {
        $this->usePolicy([]);

        $result = $this->preingest($this->textFile(), 'sip_ech', ['delivered' => ['algorithm' => 'sha256', 'value' => 'abc']]);

        $this->assertSame(['algorithm' => 'sha256', 'value' => 'abc'], $result->outcomes[0]->details['delivered']);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function usePolicy(array $rule): void
    {
        $this->app->instance(FormatPolicyRepository::class, new ConfigFormatPolicyRepository($this->policy($rule)));
    }

    /**
     * One tool (`sh`) and one rule, `qj_half`, in force by default.
     *
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function policy(array $rule): array
    {
        $text = fn (string $en): array => ['en' => $en];
        $command = fn (string $kind, array $entry): array => ['label' => $text('Command'), 'description' => $text('A command.'), 'kind' => $kind, 'tool' => 'sh'] + $entry;

        return [
            'tools' => ['sh' => ['label' => $text('Shell'), 'description' => $text('The shell.'), 'binaries' => ['sh'], 'version_command' => 'echo 1.0']],
            'commands' => [
                'qj_identify' => $command('identify', ['handler' => 'qj.identify']),
                'qj_check' => $command('characterize', ['handler' => 'qj.characterize']),
                'qj_check_fails' => $command('characterize', ['template' => '{binary} -c \'exit 4\'']),
                'qj_half' => $command('transform', ['template' => '{binary} -c \'head -c 10 "$1" > "$2"\' qj {source} {target}']),
                'qj_double' => $command('transform', ['template' => '{binary} -c \'cat "$1" "$1" > "$2"\' qj {source} {target}']),
                'qj_fail' => $command('transform', ['template' => '{binary} -c \'exit 3\'']),
                'qj_slow' => $command('transform', ['template' => '{binary} -c \'sleep 5\'', 'timeout' => 1]),
                'qj_verify' => $command('verify', ['handler' => 'qj.verify']),
                'siegfried' => $command('identify', ['handler' => 'qj.identify']),
            ],
            'rules' => [
                'qj_half' => array_merge([
                    'label' => $text('Halve'),
                    'description' => $text('Halves a text file.'),
                    'phase' => 'preingest',
                    'match' => ['pronom_ids' => ['x-fmt/111'], 'extensions' => ['txt']],
                    'transform' => ['qj_half'],
                    'verify' => 'qj_verify',
                    'require_smaller' => true,
                    'original' => 'replace',
                    'enabled_by_default' => true,
                ], $rule),
            ],
        ];
    }

    private function textFile(string $content = 'A text of some forty characters, roughly.', string $extension = 'txt'): string
    {
        $file = sys_get_temp_dir().'/qj-preingest-'.uniqid().'.'.$extension;
        file_put_contents($file, $content);
        $this->files[] = $file;

        return $file;
    }

    private function assertWorkDirIsRemoved(PreingestResult $result): void
    {
        $this->assertNotNull($result->workDir);
        $result->cleanup();
        $this->assertDirectoryDoesNotExist($result->workDir);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function preingest(string $file, string $source, array $options = []): PreingestResult
    {
        return $this->results[] = app(Preingest::class)->run($file, $source, $options);
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
