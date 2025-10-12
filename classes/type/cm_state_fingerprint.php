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
 * A fingerprint for the state of a course module.
 *
 * @package     local_archiving
 * @copyright   2025 Niels Gandraß <niels@gandrass.de>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_archiving\type;

// phpcs:ignore
defined('MOODLE_INTERNAL') || die(); // @codeCoverageIgnore


/**
 * A fingerprint for the state of a course module.
 *
 * This class is used to fingerprint the current state of a course module (cm),
 * i.e. an activity. It is used to determine whether new data has been added to
 * an activity or if it was changed in any other way.
 *
 * CM fingerprints are based on a set of metadata the is provided by the
 * activity archiving drivers. Internally, this data is serialized and hashed
 * to allow for quick comparisons.
 */
final class cm_state_fingerprint {
    /**
     * @var string Fingerprint of the current course module state.
     */
    protected readonly string $fingerprint;

    /**
     * Internal constructor to create a new cm_state_fingerprint instance.
     *
     * @param string $fingerprint The validated raw fingerprint value.
     */
    protected function __construct(string $fingerprint) {
        $this->fingerprint = $fingerprint;
    }

    /**
     * Generates a new course module / activity fingerprint based on the given
     * course module metadata.
     *
     * The provided data should be minimal but MUST change whenever the activity
     * is changed in any way (e.g., new student data is recorded, questions got
     * updated, ...). The resulting fingerprint is then used to determine if an
     * activity has changed since the last time it was archived.
     *
     * @param array $cmdata Array of cm metadata that changes whenever a course
     * module has changed or new student data is added to it.
     *
     * @throws \JsonException If serialization of the given cmdata failed.
     * @throws \coding_exception If the given cmdata array is invalid.
     */
    public static function generate(array $cmdata): self {
        if (empty($cmdata)) {
            throw new \coding_exception('cmdata must not be empty');
        }

        $serializeddata = json_encode($cmdata, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR, 32);
        return self::from_raw_value(hash('sha256', $serializeddata));
    }

    /**
     * Loads an existing raw fingerprint value into a new cm_state_fingerprint
     * instance.
     *
     * @param string $rawfingerprint The raw fingerprint value to load.
     * @return self A new cm_state_fingerprint instance with the given raw fingerprint.
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
     * @return string The raw fingerprint of the current course module state.
     */
    public function get_raw_value(): string {
        return $this->fingerprint;
    }
}
