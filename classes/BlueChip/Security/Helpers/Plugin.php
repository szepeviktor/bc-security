<?php
/**
 * @package BC_Security
 */
namespace BlueChip\Security\Helpers;

/**
 * Helper methods to deal with installed plugins.
 */
abstract class Plugin
{
    /**
     * @var string
     */
    const PLUGINS_DIRECTORY_URL = 'https://wordpress.org/plugins/';


    /**
     * @param string $plugin_basename
     * @return string Presumable URL of the plugin in WordPress.org Plugins Directory.
     */
    public static function getDirectoryUrl(string $plugin_basename): string
    {
        return trailingslashit(self::PLUGINS_DIRECTORY_URL . self::getSlug($plugin_basename));
    }


    /**
     * Get slug (ie. bc-security) for plugin with given basename (ie. bc-security/bc-security.php).
     *
     * @param string $plugin_basename
     * @return string Plugin slug or empty string, if plugin does not seem to be installed in its own directory.
     */
    public static function getSlug(string $plugin_basename): string
    {
        // This is fine most of the time and WPCentral/WP-CLI-Security gets the slug the same way,
        // but it does not seem to be guaranteed that slug is always equal to directory name...
        $slug = dirname($plugin_basename);
        // For single-file plugins, return empty string.
        return $slug === '.' ? '' : $slug;
    }


    /**
     * @param string $plugin_basename
     * @return bool True, if there is readme.txt file present in plugin directory, false otherwise.
     */
    public static function hasReadmeTxt(string $plugin_basename): bool
    {
        return is_file(self::getPluginDirPath($plugin_basename) . '/readme.txt');
    }


    /**
     * Get all installed plugins that seems to be hosted at WordPress.org repository (= have readme.txt file).
     * Method effectively discards any plugins that are not in their own directory (like Hello Dolly) from output.
     *
     * @return array
     */
    public static function getPluginsInstalledFromWordPressOrg(): array
    {
        // We're using some wp-admin stuff here, so make sure it's available.
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // There seem to be no easy way to find out if plugin is hosted at WordPress.org repository or not, see:
        // https://core.trac.wordpress.org/ticket/32101

        return array_filter(
            get_plugins(),
            [self::class, 'hasReadmeTxt'],
            ARRAY_FILTER_USE_KEY
        );
    }


    /**
     * Get absolute path to plugin directory for given $plugin_basename (ie. "bc-security/bc-security.php").
     *
     * @see get_plugins()
     *
     * @param string $plugin_basename Basename of plugin installed in its own directory.
     * @return string Absolute path to directory where plugin is installed.
     */
    public static function getPluginDirPath(string $plugin_basename): string
    {
        return wp_normalize_path(WP_PLUGIN_DIR . '/' . dirname($plugin_basename));
    }


    /**
     * Create comma separated list of plugin names optionally with a link to plugin page.
     *
     * @param array $plugins
     * @param bool $linkToPage
     * @return string
     */
    public static function implodeList(array $plugins, bool $linkToPage = false): string
    {
        return implode(
            ', ',
            array_map(
                function (array $plugin_data, string $plugin_basename) use ($linkToPage): string {
                    $plugin_name = '<em>' . esc_html($plugin_data['Name']) . '</em>';
                    return $linkToPage
                        ? '<a href="' . esc_url(self::getDirectoryUrl($plugin_basename)) . '" target="_blank">' . $plugin_name . '</a>'
                        : $plugin_name
                    ;
                },
                $plugins,
                array_keys($plugins)
            )
        );
    }
}
