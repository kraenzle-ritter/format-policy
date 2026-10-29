<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Tests;

use KraenzleRitter\FormatPolicy\CommandRunner;
use KraenzleRitter\FormatPolicy\ConfigFormatPolicyRepository;
use KraenzleRitter\FormatPolicy\FormatPolicyRepository;
use KraenzleRitter\FormatPolicy\Preingest;
use KraenzleRitter\FormatPolicy\Preingest\RuleOutcome;
use KraenzleRitter\FormatPolicy\RuleSwitches;
use KraenzleRitter\FormatPolicy\Verification;

/**
 * The verify command stream_signature with the real ffmpeg (anton#717). The
 * samples are made by ffmpeg for each run.
 */
final class StreamSignatureTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        exec('command -v ffmpeg', $output, $status);
        if ($status !== 0) {
            $this->markTestSkipped('ffmpeg is not installed.');
        }
        $this->dir = sys_get_temp_dir().'/qj-stream-signature-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            exec('rm -rf '.escapeshellarg($this->dir));
        }

        parent::tearDown();
    }

    public function test_a_container_change_and_lossless_codecs_keep_every_stream(): void
    {
        $original = $this->sample('a.mov', ['-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-c:a', 'pcm_s24le']);

        $verification = $this->verify($original, $this->convert($original, 'b.mkv', ['-c', 'copy']));
        $this->assertTrue($verification->passed, $verification->detail);
        $this->assertSame('2 streams identical', $verification->detail);

        $verification = $this->verify($original, $this->convert($original, 'c.mkv', ['-c:v', 'ffv1', '-level', '3', '-c:a', 'flac']));
        $this->assertTrue($verification->passed, $verification->detail);
    }

    public function test_a_24_bit_wav_and_its_flac_are_the_same_sound(): void
    {
        $wav = $this->sample('a.wav', ['-c:a', 'pcm_s24le'], video: false);

        $verification = $this->verify($wav, $this->convert($wav, 'a.flac', ['-c:a', 'flac']));

        $this->assertTrue($verification->passed, $verification->detail);
        $this->assertSame('1 stream identical', $verification->detail);
    }

    public function test_a_float_wav_squeezed_into_flac_is_not_the_same_sound(): void
    {
        $wav = $this->sample('a.wav', ['-af', 'volume=0.3', '-c:a', 'pcm_f32le'], video: false);

        $verification = $this->verify($wav, $this->convert($wav, 'a.flac', ['-c:a', 'flac']));

        $this->assertFalse($verification->passed);
        $this->assertSame('the decoded content of stream 0 differs', $verification->detail);
    }

    public function test_a_lossy_conversion_fails(): void
    {
        $original = $this->sample('a.mkv', ['-c:v', 'ffv1', '-c:a', 'flac']);

        $verification = $this->verify($original, $this->convert($original, 'b.mkv', ['-c:v', 'libx264', '-crf', '35', '-c:a', 'copy']));
        $this->assertFalse($verification->passed);
        $this->assertSame('the decoded content of stream 0 differs', $verification->detail);

        $verification = $this->verify($original, $this->convert($original, 'c.mkv', ['-c:v', 'copy', '-c:a', 'aac']));
        $this->assertSame('the decoded content of stream 1 differs', $verification->detail);
    }

    public function test_what_the_hashes_do_not_cover_is_compared_too(): void
    {
        $original = $this->sample('a.mkv', ['-c:v', 'ffv1', '-c:a', 'flac']);

        $silent = $this->convert($original, 'silent.mkv', ['-an', '-c:v', 'copy']);
        $this->assertSame('the streams differ: video, audio delivered, video in the result', $this->verify($original, $silent)->detail);

        $mono = $this->convert($original, 'mono.mkv', ['-c:v', 'copy', '-c:a', 'flac', '-ar', '44100']);
        $this->assertSame('stream 1: sample rate 48000 delivered, 44100 in the result', $this->verify($original, $mono)->detail);

        $short = $this->convert($original, 'short.mkv', ['-c', 'copy', '-t', '1']);
        $this->assertStringStartsWith('the duration differs: 2.0', $this->verify($original, $short)->detail);
    }

    public function test_a_rule_of_the_program_uses_it_in_the_preingest(): void
    {
        config(['format-policy.rules.qj_mkv_ffv1' => [
            'label' => ['en' => 'MKV as FFV1 version 3'],
            'description' => ['en' => 'Test rule.'],
            'phase' => 'preingest',
            'match' => ['extensions' => ['mkv']],
            'transform' => ['qj_ffv1_level3'],
            'verify' => 'stream_signature',
            'require_smaller' => false,
            'original' => 'replace',
            'priority' => 100,
            'enabled_by_default' => true,
        ], 'format-policy.commands.qj_ffv1_level3' => [
            'label' => ['en' => 'FFV1 version 3'],
            'description' => ['en' => 'Test command.'],
            'kind' => 'transform',
            'tool' => 'ffmpeg',
            'template' => '{binary} -v error -nostdin -y -i {source} -map 0 -c copy -c:v ffv1 -level 3 -slicecrc 1 -f matroska {target}',
            'timeout' => 600,
        ]]);
        $this->app->instance(FormatPolicyRepository::class, $policy = new ConfigFormatPolicyRepository);
        $this->assertSame([], $policy->problems());
        app(RuleSwitches::class)->set($policy->rule('qj_mkv_ffv1'), true);

        $result = app(Preingest::class)->run($this->sample('a.mkv', ['-c:v', 'ffv1', '-level', '1', '-c:a', 'flac']), 'upload');

        try {
            $outcome = $result->outcomes[0];
            $this->assertSame(RuleOutcome::OK, $outcome->status, (string) json_encode($outcome->details));
            $this->assertTrue($outcome->details['verification']['passed']);
            $this->assertSame('2 streams identical', $outcome->details['verification']['detail']);
            $this->assertNotNull($outcome->details['tools']['ffmpeg']);
        } finally {
            $result->cleanup();
        }
    }

    private function verify(string $original, string $result): Verification
    {
        return app(CommandRunner::class)->verify('stream_signature', $original, $result);
    }

    /**
     * @param  list<string>  $options
     */
    private function sample(string $name, array $options, bool $video = true): string
    {
        $input = $video ? ['-f', 'lavfi', '-i', 'testsrc=size=160x120:rate=25:duration=2'] : [];

        return $this->ffmpeg([...$input, '-f', 'lavfi', '-i', 'sine=duration=2:sample_rate=48000', ...$options], $name);
    }

    /**
     * @param  list<string>  $options
     */
    private function convert(string $source, string $name, array $options): string
    {
        return $this->ffmpeg(['-i', $source, '-map', '0', ...$options], $name);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function ffmpeg(array $arguments, string $name): string
    {
        $path = $this->dir.'/'.$name;
        exec(implode(' ', array_map('escapeshellarg', ['ffmpeg', '-v', 'error', '-nostdin', '-y', ...$arguments, $path])).' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));

        return $path;
    }
}
