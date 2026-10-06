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
 * A fingerprint for an archive job.
 *
 * @package     local_archiving
 * @copyright   2026 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\local\type;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore

use local_archiving\archive_job;


/**
 * A fingerprint for an archive job.
 *
 * This class is used to create an easily comparable fingerprint for archive
 * jobs. It is based on the course ID, the course module ID and the job settings,
 * thereby allowing to easily answer the question: "Has there been an archive job
 * for this specific activity with this specific settings before?".
 *
 * Job fingerprints do not capture the state of the targeted activity. See
 * cm_state_fingerprint for this purpose.
 */
final class archive_job_fingerprint {
    /**
     * @var string Fingerprint of the archive job.
     */
    protected readonly string $fingerprint;

    /**
     * Internal constructor to create a new archive_job_fingerprint instance.
     *
     * @param string $fingerprint The validated raw fingerprint value.
     */
    protected function __construct(string $fingerprint) {
        $this->fingerprint = $fingerprint;
    }

    /**
     * Generates a new archive job fingerprint.
     *
     * Settings are normalized before hashing, so that neither the order of
     * object keys nor the order of elements in lists affects the resulting
     * fingerprint.
     *
     * @param int $courseid ID of the course the job is run for
     * @param int $cmid ID of the course module the job is run for
     * @param \stdClass $settings Job settings object
     * @return self A new archive_job_fingerprint instance for the given job data.
     * @throws \JsonException If serialization of the given data failed.
     * @throws \coding_exception
     */
    public static function generate(int $courseid, int $cmid, \stdClass $settings): self {
        // Ensure we compare fully preprocessed settings objects.
        $settings = archive_job::preprocess_settings($settings);

        $normalizedsettings = self::normalize(
            // Convert to JSON and back to ensure all objects end up as (associative) arrays for sorting.
            json_decode(
                json_encode($settings, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
                true
            )
        );

        $serializeddata = json_encode([
            'courseid' => $courseid,
            'cmid' => $cmid,
            'settings' => $normalizedsettings,
        ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);

        return self::from_raw_value(hash('sha256', $serializeddata));
    }

    /**
     * Recursively normalizes the given value by sorting associative arrays by
     * their keys and lists of scalar values by their values.
     *
     * @param mixed $value Value to normalize
     * @return mixed Normalized value
     */
    protected static function normalize(mixed $value): mixed {
        // Scalar value.
        if (!is_array($value)) {
            return $value;
        }

        // Recursively normalize all values in the array.
        $value = array_map(fn ($v) => self::normalize($v), $value);

        if (array_is_list($value)) {
            if (count(array_filter($value, fn ($v) => !is_scalar($v))) === 0) {
                sort($value);
            }
        } else {
            ksort($value);
        }

        return $value;
    }

    /**
     * Loads an existing raw fingerprint value into a new archive_job_fingerprint
     * instance.
     *
     * @param string $rawfingerprint The raw fingerprint value to load.
     * @return self A new archive_job_fingerprint instance with the given raw fingerprint.
     * @throws \coding_exception
     */
    public static function from_raw_value(string $rawfingerprint): self {
        if (strlen($rawfingerprint) !== 64) {
            throw new \coding_exception('Invalid fingerprint length, expected 64 characters.');
        }

        return new self($rawfingerprint);
    }

    /**
     * Returns the raw fingerprint value.
     *
     * @return string The raw fingerprint of the archive job.
     */
    public function get_raw_value(): string {
        return $this->fingerprint;
    }

    /**
     * Determines if this fingerprint is identical to the given one.
     *
     * @param archive_job_fingerprint $other Fingerprint to compare against
     * @return bool True if both fingerprints are identical
     */
    public function equals(archive_job_fingerprint $other): bool {
        return $this->fingerprint === $other->get_raw_value();
    }
}
