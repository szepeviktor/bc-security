<?php
/**
 * @package BC_Security
 */

namespace BlueChip\Security;

/**
 * Main plugin class
 */
class Plugin
{
    /** @var \BlueChip\Security\Admin */
    public $admin;

    /** @var array Array with module objects for all plugin modules */
    private $modules;

    /** @var array Array with setting objects for particular modules */
    private $settings;

    /** @var \wpdb WordPress database access abstraction object */
    private $wpdb;

    /**
     * Construct the plugin instance.
     *
     * @param \wpdb $wpdb WordPress database access abstraction object
     */
    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;

        // Read plugin settings
        $this->settings = [
            'hardening' => new Modules\Hardening\Settings('bc-security-hardening'),
            'login'     => new Modules\Login\Settings('bc-security-login'),
            'setup'     => new Setup\Settings('bc-security-setup'),
        ];

        // Get setup info
        $setup = new Setup\Core($this->settings['setup']);

        // IP address is at core interest within this plugin :)
        $remote_address = $setup->getRemoteAddress();

        // Init admin, if necessary.
        $this->admin = is_admin() ? new Admin() : null;

        // Construct modules...
        $logger     = new Modules\Log\Logger($wpdb, $remote_address);
        $hardening  = new Modules\Hardening\Core($this->settings['hardening']);
        $bl_manager = new Modules\IpBlacklist\Manager($wpdb);
        $bl_bouncer = new Modules\IpBlacklist\Bouncer($remote_address, $bl_manager);
        $bookkeeper = new Modules\Login\Bookkeeper($this->settings['login'], $wpdb);
        $gatekeeper = new Modules\Login\Gatekeeper($this->settings['login'], $remote_address, $bookkeeper, $bl_manager);

        // ... and store them for later.
        $this->modules = [
            'logger'            => $logger,
            'hardening-core'    => $hardening,
            'blacklist-manager' => $bl_manager,
            'blacklist-bouncer' => $bl_bouncer,
            'login-bookkeeper'  => $bookkeeper,
            'login-gatekeeper'  => $gatekeeper,
        ];
    }


    /**
     * Load the plugin by hooking into WordPress actions and filters.
     * Method should be invoked immediately on plugin load.
     */
    public function load()
    {
        // Load all modules that require immediate loading.
        foreach ($this->modules as $module) {
            if ($module instanceof Modules\Loadable) {
                $module->load();
            }
        }

        // Register initialization method.
        add_action('init', [$this, 'init'], 10, 0);
    }


    /**
     * Perform initialization tasks.
     * Method should be run (early) in init hook.
     */
    public function init()
    {
        // Initialize all modules that require initialization.
        foreach ($this->modules as $module) {
            if ($module instanceof Modules\Initializable) {
                $module->init();
            }
        }

        if ($this->admin) {
            // Initialize admin interface.
            $this->admin->init()
                // Setup comes first...
                ->addPage(new Setup\AdminPage($this->settings['setup']))
                // ...then comes modules pages.
                ->addPage(new Modules\Checklist\AdminPage($this->wpdb))
                ->addPage(new Modules\Hardening\AdminPage($this->settings['hardening']))
                ->addPage(new Modules\Login\AdminPage($this->settings['login']))
                ->addPage(new Modules\IpBlacklist\AdminPage($this->modules['blacklist-manager']))
            ;
        }
    }


    /**
     * Perform installation tasks.
     * Method should be run on plugin activation.
     *
     * @link https://developer.wordpress.org/plugins/the-basics/activation-deactivation-hooks/
     */
    public function install()
    {
        // Install every module that requires it.
        foreach ($this->modules as $module) {
            if ($module instanceof Modules\Installable) {
                $module->install();
            }
        }
    }


    /**
     * Perform uninstallation tasks.
     * Method should be run on plugin uninstall.
     *
     * @link https://developer.wordpress.org/plugins/the-basics/uninstall-methods/
     */
    public function uninstall()
    {
        // Remove plugin settings.
        foreach ($this->settings as $settings) {
            delete_option($settings->getOptionName());
        }

        // Uninstall every module that requires it.
        foreach ($this->modules as $module) {
            if ($module instanceof Modules\Installable) {
                $module->uninstall();
            }
        }
    }
}
