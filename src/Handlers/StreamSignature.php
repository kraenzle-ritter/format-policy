<?php

declare(strict_types=1);

namespace KraenzleRitter\FormatPolicy\Handlers;

use Illuminate\Support\Facades\Process;
use KraenzleRitter\FormatPolicy\Command;
use KraenzleRitter\FormatPolicy\CommandFailed;
use KraenzleRitter\FormatPolicy\Verification;

/**
 * Proves a lossless conversion of audio and video (anton#717): an MD5 of the
 * decoded frames of every stream (`ffmpeg -f streamhash`), original against
 * result. A container change, FFV1 or FLAC keeps every sample and every
 * pixel, so each stream's hash stays the same.
 *
 * Sound is hashed as 64-bit float: every integer and float sample format up
 * to 32 bits converts to it exactly, so a 24-bit WAV and the FLAC made of it
 * hash alike, and a float WAV squeezed into integers does not. Pictures are
 * hashed in the pixel format they decode to; frames are neither dropped nor
 * repeated (`-fps_mode passthrough`).
 *
 * Before the hashes, ffprobe compares what the hashes do not cover: the
 * number and kind of streams (subtitles and data included), channels,
 * sample rate, width, height, frame rate and the duration (within 0.1 s, as
 * containers round it differently). ffprobe is looked for beside ffmpeg.
 */
final class StreamSignature implements VerifiesResult
{
    /** How far the durations may differ, in seconds. */
    private const DurationTolerance = 0.1;

    public function verify(Command $command, string $binary, string $original, string $result): Verification
    {
        $expected = $this->probe($command, $binary, $original);
        $actual = $this->probe($command, $binary, $result);

        $difference = $this->difference($expected, $actual);
        if ($difference !== null) {
            return new Verification(false, $difference);
        }

        $hashes = $this->hashes($command, $binary, $original);
        $resultHashes = $this->hashes($command, $binary, $result);

        if (count($hashes) !== count($resultHashes)) {
            return new Verification(false, sprintf('%d streams hashed in the delivery, %d in the result', count($hashes), count($resultHashes)));
        }

        foreach ($hashes as $index => $hash) {
            if ($resultHashes[$index] !== $hash) {
                return new Verification(false, sprintf('the decoded content of stream %d differs', $index));
            }
        }

        return new Verification(true, sprintf('%d %s identical', count($hashes), count($hashes) === 1 ? 'stream' : 'streams'));
    }

    /**
     * @param  array{duration: float|null, streams: list<array<string, scalar|null>>}  $expected
     * @param  array{duration: float|null, streams: list<array<string, scalar|null>>}  $actual
     */
    private function difference(array $expected, array $actual): ?string
    {
        $kinds = fn (array $probe): string => implode(', ', array_map(fn (array $stream): string => (string) $stream['type'], $probe['streams']));
        if ($kinds($expected) !== $kinds($actual)) {
            return sprintf('the streams differ: %s delivered, %s in the result', $kinds($expected) ?: 'none', $kinds($actual) ?: 'none');
        }

        foreach ($expected['streams'] as $index => $stream) {
            foreach ($stream as $property => $value) {
                if ($actual['streams'][$index][$property] !== $value) {
                    return sprintf('stream %d: %s %s delivered, %s in the result', $index, str_replace('_', ' ', $property), $value ?? '–', $actual['streams'][$index][$property] ?? '–');
                }
            }
        }

        if ($expected['duration'] !== null && $actual['duration'] !== null && abs($expected['duration'] - $actual['duration']) > self::DurationTolerance) {
            return sprintf('the duration differs: %.3f s delivered, %.3f s in the result', $expected['duration'], $actual['duration']);
        }

        return null;
    }

    /**
     * @return array{duration: float|null, streams: list<array<string, scalar|null>>}
     */
    private function probe(Command $command, string $ffmpeg, string $file): array
    {
        $result = Process::timeout($command->timeout)->run([$this->ffprobe($ffmpeg), '-v', 'error', '-show_format', '-show_streams', '-of', 'json', $file]);
        $data = json_decode($result->output(), true);

        if (! $result->successful() || ! is_array($data)) {
            throw new CommandFailed("«{$command->key}» could not read the streams of ".basename($file).': '.trim($result->errorOutput()));
        }

        $streams = [];
        foreach ((array) ($data['streams'] ?? []) as $stream) {
            if (! is_array($stream)) {
                continue;
            }
            $type = (string) ($stream['codec_type'] ?? 'unknown');
            $streams[] = ['type' => $type] + match ($type) {
                'audio' => [
                    'channels' => isset($stream['channels']) ? (int) $stream['channels'] : null,
                    'sample_rate' => isset($stream['sample_rate']) ? (int) $stream['sample_rate'] : null,
                ],
                'video' => [
                    'width' => isset($stream['width']) ? (int) $stream['width'] : null,
                    'height' => isset($stream['height']) ? (int) $stream['height'] : null,
                    'frame_rate' => $this->rate($stream['avg_frame_rate'] ?? null) ?? $this->rate($stream['r_frame_rate'] ?? null),
                ],
                default => [],
            };
        }

        $duration = $data['format']['duration'] ?? null;

        return ['duration' => is_numeric($duration) ? (float) $duration : null, 'streams' => $streams];
    }

    /**
     * The MD5 of each audio and video stream, by its position in the output.
     *
     * @return list<string>
     */
    private function hashes(Command $command, string $ffmpeg, string $file): array
    {
        $result = Process::timeout($command->timeout)->run([
            $ffmpeg, '-v', 'error', '-nostdin', '-i', $file,
            '-map', '0:v?', '-map', '0:a?', '-fps_mode', 'passthrough', '-c:a', 'pcm_f64le',
            '-f', 'streamhash', '-hash', 'md5', '-',
        ]);

        preg_match_all('/^\d+,[a-z],MD5=([0-9a-f]{32})$/m', $result->output(), $matches);

        if (! $result->successful()) {
            throw new CommandFailed("«{$command->key}» could not decode ".basename($file).': '.trim($result->errorOutput()));
        }

        return $matches[1];
    }

    private function rate(mixed $rate): ?string
    {
        if (! is_string($rate) || preg_match('#^(\d+)/(\d+)$#', $rate, $match) !== 1 || (int) $match[1] === 0 || (int) $match[2] === 0) {
            return null;
        }

        return number_format((int) $match[1] / (int) $match[2], 3, '.', '');
    }

    private function ffprobe(string $ffmpeg): string
    {
        $beside = dirname($ffmpeg).'/'.str_replace('ffmpeg', 'ffprobe', basename($ffmpeg));

        return $beside !== $ffmpeg && is_executable($beside) ? $beside : 'ffprobe';
    }
}
