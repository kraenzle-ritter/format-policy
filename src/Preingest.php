<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy;

use BackedEnum;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use KraenzleRitter\FormatPolicy\Preingest\PlannedRule;
use KraenzleRitter\FormatPolicy\Preingest\PreingestResult;
use KraenzleRitter\FormatPolicy\Preingest\Probe;
use KraenzleRitter\FormatPolicy\Preingest\RuleOutcome;
use KraenzleRitter\FormatPolicy\Preingest\RuleVerdict;
use Throwable;

/**
 * The preingest: what the format
 * policy does to a file before it becomes a medium.
 *
 * The calling program sends every incoming file through run(), naming the
 * route it came by (in Anton: AntonObject::addAntonMedium()). With no rule in force for the tenant it returns at once, without even
 * identifying the file. Otherwise it identifies the file, applies the
 * matching rules in order, verifies each result and identifies it again.
 *
 * It never fails the ingest. A step that fails, a verification that does not
 * pass, a result that is not smaller: the file before that rule is kept and
 * the outcome recorded. The delivered file is only read, never changed; all
 * writing happens in a working directory that the caller removes after
 * ingesting (PreingestResult::cleanup()).
 *
 * Which rules are in force comes from the switches it is given: the
 * container's for a program with one tenant per process (Anton), one set per
 * tenant through withSwitches() for a program serving several (agate).
 */
final class Preingest
{
    public const PHASE = 'preingest';

    public function __construct(
        private readonly FormatPolicyRepository $policy,
        private readonly CommandRunner $runner,
        private readonly RuleSwitches $switches,
        private readonly ?string $workRoot = null,
    ) {}

    /**
     * The same preingest with another tenant's switches.
     */
    public function withSwitches(SwitchStore $store): self
    {
        return new self($this->policy, $this->runner, new RuleSwitches($this->policy, $store), $this->workRoot);
    }

    /**
     * @param  array{skip_rules?: list<string>, delivered?: array{algorithm: string, value: string}}  $options
     */
    public function run(string $path, string|BackedEnum $source, array $options = []): PreingestResult
    {
        $rules = $this->rulesFor($source, $options);
        if ($rules === []) {
            return PreingestResult::untouched($path);
        }

        $workDir = rtrim($this->workRoot ?? sys_get_temp_dir().'/preingest', '/').'/'.Str::uuid();
        File::ensureDirectoryExists($workDir);

        $current = $path;
        $outcomes = [];
        $keepOriginal = false;
        $characterization = null;
        $identification = $this->identify($current);

        foreach ($rules as $rule) {
            if (! $this->applies($rule, $current, $identification, $characterization)) {
                continue;
            }

            $outcome = $this->apply($rule, $current, $identification, $source, $workDir, $options);
            $outcomes[] = $outcome;

            if ($outcome->status === RuleOutcome::OK) {
                $current = (string) $outcome->details['after']['path'];
                $identification = $this->identify($current);
                $keepOriginal = $keepOriginal || $rule->original === 'keep_original';
                $characterization = $characterization === null ? null : $this->characterize($characterization['command'], $current);
            }
        }

        $outcomes = array_map(fn (RuleOutcome $outcome): RuleOutcome => $this->withoutPaths($outcome), $outcomes);

        return new PreingestResult($current, $path, $outcomes, $identification, $keepOriginal, $workDir, $characterization);
    }

    /**
     * The rules that would act on a file — conditions and precheck only,
     * nothing is converted.
     *
     * @param  array{skip_rules?: list<string>}  $options
     * @return list<PlannedRule>
     */
    public function plan(string $path, string|BackedEnum $source, array $options = []): array
    {
        $rules = $this->rulesFor($source, $options);
        if ($rules === []) {
            return [];
        }

        $identification = $this->identify($path);

        return array_values(array_map(
            fn (Rule $rule): PlannedRule => new PlannedRule($rule->key, $rule->label->in(), $rule->description->in(), $rule->optional),
            array_filter($rules, fn (Rule $rule): bool => $this->applies($rule, $path, $identification)),
        ));
    }

    /**
     * What the preingest would do to a file, and why — a dry run that
     * converts nothing and leaves no working directory.
     *
     * Every rule in force on the route gets a verdict, including those this
     * server lacks the tools for, which run() leaves out silently. What the
     * prechecks measured comes along, by command. Optional rules are judged
     * as well: deselecting one is a decision of the run, not of the file.
     */
    public function probe(string $path, string|BackedEnum $source): Probe
    {
        $admitted = $this->admitted($source);
        if ($admitted === []) {
            return new Probe($path, @filesize($path), Identification::unknown(), [], []);
        }

        $identification = $this->identify($path);
        $characteristics = [];
        $verdicts = [];

        foreach ($admitted as [$rule, $missing]) {
            $verdict = fn (string $verdict, array $condition = []): RuleVerdict => new RuleVerdict(
                $rule->key, $rule->label->in(), $rule->description->in(), $rule->optional, $verdict, $condition, $missing,
            );

            if (! $rule->matchesFormat($identification->mimeType, $identification->pronomId, pathinfo($path, PATHINFO_EXTENSION))) {
                $verdicts[] = $verdict(RuleVerdict::OTHER_FORMAT);

                continue;
            }
            if ($missing !== []) {
                $verdicts[] = $verdict(RuleVerdict::TOOLS_MISSING);

                continue;
            }

            $characterization = null;
            [$judgement, $condition] = $this->judge($rule, $path, $identification, $characterization);
            if ($characterization !== null) {
                $characteristics[$characterization['command']] = $characterization;
            }
            $verdicts[] = $verdict($judgement, $condition);
        }

        return new Probe($path, @filesize($path), $identification, $characteristics, $verdicts);
    }

    /**
     * The rules in force for this tenant and this route whose tools this
     * server has — what an upload form announces.
     *
     * @return list<Rule>
     */
    public function rulesInForce(string|BackedEnum $source): array
    {
        $rules = [];

        foreach ($this->admitted($source) as [$rule, $missing]) {
            if ($missing !== []) {
                logger()->warning('Preingest: «'.$rule->key.'» not applied, missing tools: '.implode(', ', $missing));

                continue;
            }
            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * The rules switched on for this tenant that admit the route, each with
     * the tools this server lacks for it.
     *
     * @return list<array{Rule, list<string>}>
     */
    private function admitted(string|BackedEnum $source): array
    {
        $admitted = [];

        foreach ($this->switches->inForce(self::PHASE) as $rule) {
            if ($rule->admits($source)) {
                $admitted[] = [$rule, $this->runner->missingTools($rule)];
            }
        }

        return $admitted;
    }

    /**
     * The rules in force for this run: those of the route, without the
     * optional ones the run deselected. A mandatory rule cannot be
     * deselected.
     *
     * @param  array{skip_rules?: list<string>}  $options
     * @return list<Rule>
     */
    private function rulesFor(string|BackedEnum $source, array $options): array
    {
        $skip = (array) ($options['skip_rules'] ?? []);
        $rules = [];

        foreach ($this->rulesInForce($source) as $rule) {
            if (in_array($rule->key, $skip, true)) {
                if ($rule->optional) {
                    continue;
                }
                logger()->warning('Preingest: the mandatory rule «'.$rule->key.'» cannot be deselected; applied');
            }
            $rules[] = $rule;
        }

        return $rules;
    }

    private function identify(string $file): Identification
    {
        try {
            return $this->runner->identify('siegfried', $file);
        } catch (Throwable $e) {
            logger()->warning('Preingest: identification failed: '.$e->getMessage());

            return Identification::unknown();
        }
    }

    /**
     * Whether a rule acts on a file. A precheck that ran leaves the facts it
     * found in `$characterization` — they describe the file as it stands.
     *
     * @param  array{command: string, tool: string|null, version: string|null, facts: array<string, scalar|null>}|null  $characterization
     */
    private function applies(Rule $rule, string $file, Identification $identification, ?array &$characterization = null): bool
    {
        return $this->judge($rule, $file, $identification, $characterization)[0] === RuleVerdict::APPLIES;
    }

    /**
     * The verdict on a rule for a file, with the `skip_when` entry that
     * vetoed it. The tools are not looked at here: run() and plan() only
     * judge rules whose tools are present, probe() names the missing ones
     * before it gets here.
     *
     * @param  array{command: string, tool: string|null, version: string|null, facts: array<string, scalar|null>}|null  $characterization
     * @return array{string, array<string, mixed>}
     */
    private function judge(Rule $rule, string $file, Identification $identification, ?array &$characterization = null): array
    {
        if (! $rule->matchesFormat($identification->mimeType, $identification->pronomId, pathinfo($file, PATHINFO_EXTENSION))) {
            return [RuleVerdict::OTHER_FORMAT, []];
        }

        if ($rule->precheck === null) {
            return [RuleVerdict::APPLIES, []];
        }

        $characterized = $this->characterize($rule->precheck, $file);
        if ($characterized === null) {
            logger()->warning('Preingest: precheck of «'.$rule->key.'» failed, rule not applied');

            return [RuleVerdict::PRECHECK_FAILED, []];
        }
        $characterization = $characterized;
        $facts = $characterized['facts'];

        foreach ($rule->skipWhen as $condition) {
            if ($this->allMatch((array) $condition, $facts)) {
                logger()->debug('Preingest: «'.$rule->key.'» left out by its precheck', ['file' => basename($file), 'facts' => $facts]);

                return [RuleVerdict::PRECHECK, (array) $condition];
            }
        }

        return [RuleVerdict::APPLIES, []];
    }

    /**
     * The facts a `characterize` command finds, with the tool that found
     * them; null when the command fails.
     *
     * @return array{command: string, tool: string|null, version: string|null, facts: array<string, scalar|null>}|null
     */
    private function characterize(string $commandKey, string $file): ?array
    {
        try {
            $facts = $this->runner->characterize($commandKey, $file);
        } catch (Throwable $e) {
            logger()->warning('Preingest: «'.$commandKey.'» failed on '.basename($file).': '.$e->getMessage());

            return null;
        }
        [$tool, $version] = $this->runner->toolOf($commandKey) ?? [null, null];

        return ['command' => $commandKey, 'tool' => $tool, 'version' => $version, 'facts' => $facts];
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, scalar|null>  $facts
     */
    private function allMatch(array $condition, array $facts): bool
    {
        foreach ($condition as $fact => $expected) {
            $actual = $facts[$fact] ?? null;
            $allowed = is_array($expected) ? $expected : [$expected];
            if (! in_array($actual, $allowed, false)) {
                return false;
            }
        }

        return $condition !== [];
    }

    /**
     * @param  array{delivered?: array{algorithm: string, value: string}}  $options
     */
    private function apply(Rule $rule, string $input, Identification $identification, string|BackedEnum $source, string $workDir, array $options): RuleOutcome
    {
        $details = [
            'rule' => $rule->key,
            'source' => Rule::sourceKey($source),
            'tools' => $this->tools($rule),
            'before' => $this->describe($input, $identification),
        ];
        if (isset($options['delivered'])) {
            $details['delivered'] = $options['delivered'];
        }

        try {
            $extension = pathinfo($input, PATHINFO_EXTENSION);
            $current = $input;
            foreach ($rule->transforms as $step => $commandKey) {
                $target = $workDir.'/'.$rule->key.'-'.($step + 1).($extension !== '' ? '.'.$extension : '');
                $current = $this->runner->transform($commandKey, $input, $current, $target);
            }

            if ($rule->verify !== null) {
                $verification = $this->runner->verify($rule->verify, $input, $current);
                $details['verification'] = ['command' => $rule->verify, 'passed' => $verification->passed, 'detail' => $verification->detail];
                if (! $verification->passed) {
                    return RuleOutcome::failed($rule->key, $details + ['original' => 'ingested', 'message' => 'verification failed: '.$verification->detail]);
                }
            }

            $after = $this->identify($current);
            $details['after'] = $this->describe($current, $after) + ['path' => $current];

            if ($identification->pronomId !== null && $after->pronomId !== $identification->pronomId) {
                return RuleOutcome::failed($rule->key, $details + ['original' => 'ingested', 'message' => "the format changed from {$identification->pronomId} to {$after->pronomId}"]);
            }

            if ($rule->requireSmaller && filesize($current) >= filesize($input)) {
                return RuleOutcome::info($rule->key, $details + ['original' => 'ingested', 'message' => 'the result is not smaller']);
            }

            return RuleOutcome::ok($rule->key, $details + ['original' => $rule->original === 'keep_original' ? 'kept' : 'discarded']);
        } catch (Throwable $e) {
            logger()->warning('Preingest: «'.$rule->key.'» failed, original ingested: '.$e->getMessage());

            return RuleOutcome::failed($rule->key, $details + ['original' => 'ingested', 'message' => $e->getMessage()]);
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function tools(Rule $rule): array
    {
        $tools = [];
        foreach ($rule->commandKeys() as $commandKey) {
            $tool = $this->runner->toolOf($commandKey);
            if ($tool !== null) {
                $tools[$tool[0]] = $tool[1];
            }
        }

        return $tools;
    }

    /**
     * @return array{md5: string|false, sha512: string|false, size: int|false, pronom: string|null, mime: string|null}
     */
    private function describe(string $file, Identification $identification): array
    {
        return [
            'md5' => md5_file($file),
            'sha512' => hash_file('sha512', $file),
            'size' => filesize($file),
            'pronom' => $identification->pronomId,
            'mime' => $identification->mimeType,
        ];
    }

    /**
     * Working-directory paths are internal; they do not go into the event.
     */
    private function withoutPaths(RuleOutcome $outcome): RuleOutcome
    {
        $details = $outcome->details;
        unset($details['after']['path']);

        return new RuleOutcome($outcome->rule, $outcome->status, $details);
    }
}
