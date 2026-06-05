<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Coverage gate parser for tiny_fastpix.
 *
 * Reads a phpunit clover-format coverage report and enforces the project
 * coverage policy from .claude/CLAUDE.md and the build plan:
 *
 *   overall (all production classes/)   85%
 *   classes/external/get_my_videos.php  90%   (owner-scope / capability path)
 *
 * Only files under the plugin's classes/ directory count; phpunit emits
 * coverage for every file that ran, so we scope by path rather than trust
 * the report's global summary. Exits 0 if both targets are met, 1 with a
 * remediation report otherwise, 2 on a report-parse failure.
 *
 * Invoked by tools/coverage.sh; not meant to be run standalone.
 *
 * @package    tiny_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This is a standalone CLI gate; it deliberately does not bootstrap Moodle, so
// the MOODLE_INTERNAL guard the sniff expects would die() at runtime. Disable
// that one sniff for this file rather than break the script.
// phpcs:disable moodle.Files.MoodleInternal

/** @var float Overall coverage target (percent) across the plugin's classes/ tree. */
const TARGET_OVERALL = 85.0;

/** @var string Path fragment identifying the plugin's production code in the clover report. */
const SCOPE_FRAGMENT = '/lib/editor/tiny/plugins/fastpix/classes/';

/** @var array<string,float> Per-file coverage targets (path suffix => percent). */
const TARGET_FILES = [
    '/classes/external/get_my_videos.php' => 90.0,
];

if ($argc < 2) {
    fwrite(STDERR, "usage: php coverage_gate.php <clover.xml>\n");
    exit(2);
}

$cloverpath = $argv[1];
if (!is_readable($cloverpath)) {
    fwrite(STDERR, "coverage_gate: cannot read {$cloverpath}\n");
    exit(2);
}

$prev = libxml_use_internal_errors(true);
$doc = simplexml_load_file($cloverpath);
$errors = libxml_get_errors();
libxml_clear_errors();
libxml_use_internal_errors($prev);

if ($doc === false || !empty($errors)) {
    fwrite(STDERR, "coverage_gate: clover XML failed to parse\n");
    foreach ($errors as $e) {
        fwrite(STDERR, '  - ' . trim($e->message) . "\n");
    }
    exit(2);
}

$totalstatements = 0;
$coveredstatements = 0;
$perfile = [];

foreach ($doc->xpath('//file') as $file) {
    $name = (string)$file['name'];
    if (strpos($name, SCOPE_FRAGMENT) === false) {
        continue;
    }
    $metrics = $file->metrics;
    $statements = (int)$metrics['statements'];
    $covered = (int)$metrics['coveredstatements'];
    $totalstatements += $statements;
    $coveredstatements += $covered;
    $perfile[$name] = [
        'statements' => $statements,
        'covered' => $covered,
        'percent' => $statements > 0 ? (100.0 * $covered / $statements) : 100.0,
    ];
}

if ($totalstatements === 0) {
    fwrite(STDERR, "coverage_gate: no plugin files found in clover report.\n");
    fwrite(STDERR, "  Expected paths containing '" . SCOPE_FRAGMENT . "'.\n");
    fwrite(STDERR, "  Did phpunit run with --coverage-clover and a coverage driver?\n");
    exit(2);
}

$overall = 100.0 * $coveredstatements / $totalstatements;
$shortfalls = [];

// Per-file targets.
foreach (TARGET_FILES as $suffix => $target) {
    $found = null;
    foreach ($perfile as $name => $data) {
        if (substr($name, -strlen($suffix)) === $suffix) {
            $found = $data;
            break;
        }
    }
    if ($found === null) {
        $shortfalls[] = sprintf('%-46s target %5.1f%%  but file not present in report', $suffix, $target);
        continue;
    }
    if ($found['percent'] + 1e-9 < $target) {
        $shortfalls[] = sprintf(
            '%-46s %5.1f%% < %5.1f%%  (%d/%d statements)',
            $suffix,
            $found['percent'],
            $target,
            $found['covered'],
            $found['statements']
        );
    }
}

// Overall target.
if ($overall + 1e-9 < TARGET_OVERALL) {
    $shortfalls[] = sprintf(
        '%-46s %5.1f%% < %5.1f%%  (%d/%d statements)',
        '(overall classes/)',
        $overall,
        TARGET_OVERALL,
        $coveredstatements,
        $totalstatements
    );
}

// Report.
echo "tiny_fastpix coverage gate\n";
echo str_repeat('-', 60) . "\n";
foreach ($perfile as $name => $data) {
    $short = substr($name, strpos($name, SCOPE_FRAGMENT) + strlen('/lib/editor/tiny/plugins/fastpix/'));
    echo sprintf("  %6.1f%%  %-40s (%d/%d)\n", $data['percent'], $short, $data['covered'], $data['statements']);
}
echo str_repeat('-', 60) . "\n";
echo sprintf("  OVERALL %5.1f%% (target %.0f%%)\n", $overall, TARGET_OVERALL);
echo str_repeat('-', 60) . "\n";

if (!empty($shortfalls)) {
    fwrite(STDERR, "COVERAGE GATE FAILED:\n");
    foreach ($shortfalls as $line) {
        fwrite(STDERR, "  - {$line}\n");
    }
    exit(1);
}

echo "COVERAGE GATE PASSED\n";
exit(0);
