<?php
/**
 * @package BC_Security
 */

namespace BlueChip\Security\Modules\Checklist\Checks;

use BlueChip\Security\Modules\Checklist;
use BlueChip\Security\Modules\Cron\Jobs;

class CoreIntegrity extends Checklist\AdvancedCheck
{
    /**
     * @var string
     */
    const CRON_JOB_HOOK = Jobs::CORE_INTEGRITY_CHECK;

    /**
     * @var string URL of checksum API
     */
    const CHECKSUMS_API_URL = 'https://api.wordpress.org/core/checksums/1.0/';


    public function __construct()
    {
        parent::__construct(
            __('WordPress core files are untouched', 'bc-security'),
            sprintf(
                /* translators: 1: link to Wikipedia article about md5sum, 2: link to checksums file at WordPress.org */
                esc_html__('By comparing %1$s of local core files with %2$s it is possible to determine, if any of core files have been modified or if there are any unknown files in core directories.', 'bc-security'),
                '<a href="' . esc_url(__('https://en.wikipedia.org/wiki/Md5sum', 'bc-security')) . '" target="_blank">' . esc_html__('MD5 checksums', 'bc-security') . '</a>',
                '<a href="' . esc_url(self::getChecksumsUrl()) . '" target="_blank">' . esc_html__('checksums downloaded from WordPress.org', 'bc-security') . '</a>'
            )
        );
    }


    public function run(): Checklist\CheckResult
    {
        $url = self::getChecksumsUrl();

        // Get checksums via WordPress.org API.
        if (empty($checksums = self::getChecksums($url))) {
            $message = sprintf(
                esc_html__('Failed to get core file checksums from %1$s.', 'bc-security'),
                '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a>'
            );
            return new Checklist\CheckResult(null, $message);
        }

        // Use checksums to find any modified files.
        $modified_files = self::findModifiedFiles($checksums);
        // Scan WordPress directories to find any files unknown to WordPress.
        $unknown_files = self::findUnknownFiles($checksums);

        // Trigger alert, if any suspicious files have been found.
        if (!empty($modified_files) || !empty($unknown_files)) {
            $message = esc_html__('Some of WordPress core files have been modified and/or there are unknown files present.', 'bc-security');
            return new Checklist\CheckResult(false, $message);
        }

        return new Checklist\CheckResult(true, esc_html__('WordPress core files seem to be genuine.', 'bc-security'));
    }


    /**
     * @return string URL to checksums file at api.wordpress.org for current WordPress version and locale.
     */
    public static function getChecksumsUrl(): string
    {
        // Add necessary arguments to request URL.
        return add_query_arg(
            [
                'version' => get_bloginfo('version'),
                'locale'  => get_locale(), // TODO: What about multilanguage sites?
            ],
            self::CHECKSUMS_API_URL
        );
    }


    /**
     * Get md5 checksums of core WordPress files from WordPress.org API.
     *
     * @param string $url
     * @return \stdClass|null
     */
    private static function getChecksums(string $url)
    {
        $json = Checklist\Helper::getJson($url);

        return $json && !empty($json->checksums) ? $json->checksums : null;
    }


    /**
     * Check md5 hashes of files on local filesystem against $checksums and report any modified files.
     *
     * Files in wp-content directory are automatically excluded, see:
     * https://github.com/pluginkollektiv/checksum-verifier/pull/11
     *
     * @hook \BlueChip\Security\Modules\Checklist\Hooks::IGNORED_CORE_MODIFIED_FILES
     *
     * @param \stdClass $checksums
     * @return array
     */
    private static function findModifiedFiles($checksums): array
    {
        // Get files that should be ignored.
        $ignored_files = apply_filters(
            Checklist\Hooks::IGNORED_CORE_MODIFIED_FILES,
            [
                'wp-config-sample.php',
                'wp-includes/version.php',
            ]
        );

        // Initialize array for files that do not match.
        $modified_files = Checklist\Helper::checkDirectoryForModifiedFiles(ABSPATH, $checksums, $ignored_files);

        // Ignore any modified files in wp-content directory.
        return array_filter(
            $modified_files,
            function ($filename) {
                return strpos($filename, 'wp-content/') !== 0;
            }
        );
    }


    /**
     * Report any unknown files in root directory and in wp-admin and wp-includes directories (including subdirectories).
     *
     * @hook \BlueChip\Security\Modules\Checklist\Hooks::IGNORED_CORE_UNKNOWN_FILES
     *
     * @param \stdClass $checksums
     * @return array
     */
    private static function findUnknownFiles($checksums): array
    {
        // Get files that should be ignored.
        $ignored_files = apply_filters(
            Checklist\Hooks::IGNORED_CORE_UNKNOWN_FILES,
            [
                '.htaccess',
                'wp-config.php',
                'liesmich.html', // German readme (de_DE)
                'olvasdel.html', // Hungarian readme (hu_HU)
                'procitajme.html',  // Croatian readme (hr)
            ]
        );

        return array_filter(
            array_merge(
                // Scan root WordPress directory.
                Checklist\Helper::scanDirectoryForUnknownFiles(ABSPATH, ABSPATH, $checksums, false),
                // Scan wp-admin directory recursively.
                Checklist\Helper::scanDirectoryForUnknownFiles(ABSPATH . 'wp-admin', ABSPATH, $checksums, true),
                // Scan wp-include directory recursively.
                Checklist\Helper::scanDirectoryForUnknownFiles(ABSPATH . WPINC, ABSPATH, $checksums, true)
            ),
            function ($filename) use ($ignored_files) {
                return !in_array($filename, $ignored_files, true);
            }
        );
    }
}
