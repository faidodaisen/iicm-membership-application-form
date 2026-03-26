<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * GitHub Auto-Updater for IICM Membership plugin.
 *
 * How it works:
 *  1. Checks the GitHub Releases API for the latest release tag.
 *  2. Compares tag version against the installed plugin version.
 *  3. If a newer version exists, injects it into WordPress's update transient.
 *  4. On install, renames the extracted folder to match the plugin slug.
 *
 * To release an update:
 *  1. Bump Version in iicm-membership.php (header + constant).
 *  2. Zip the iicm-membership/ folder → produces iicm-membership.zip
 *     (zip must contain iicm-membership/ as its root folder).
 *  3. On GitHub: Releases → New Release → tag e.g. v1.0.1
 *  4. Upload iicm-membership.zip as a release asset → Publish.
 *  5. WordPress will show the update in WP Admin → Plugins.
 */
class IICM_GitHub_Updater {

    private $plugin_file;
    private $plugin_slug;   // iicm-membership/iicm-membership.php
    private $plugin_folder; // iicm-membership
    private $github_user  = 'faidodaisen';
    private $github_repo  = 'iicm-membership-application-form';
    private $cache_key;

    public function __construct( $plugin_file ) {
        $this->plugin_file   = $plugin_file;
        $this->plugin_slug   = plugin_basename( $plugin_file );
        $this->plugin_folder = dirname( $this->plugin_slug );
        $this->cache_key     = 'iicm_gh_release_' . md5( $this->plugin_slug );

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
        add_filter( 'plugins_api',                           array( $this, 'plugin_info' ), 20, 3 );
        add_filter( 'upgrader_post_install',                 array( $this, 'post_install' ), 10, 3 );
        add_action( 'upgrader_process_complete',             array( $this, 'clear_cache' ), 10, 2 );
    }

    // -------------------------------------------------------------------------
    // Fetch latest release from GitHub API (cached 12 h)
    // -------------------------------------------------------------------------
    private function get_release() {
        $cached = get_transient( $this->cache_key );
        if ( false !== $cached ) return $cached;

        $url      = "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest";
        $response = wp_remote_get( $url, array(
            'headers' => array( 'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );
        if ( empty( $release->tag_name ) ) return false;

        // Prefer an uploaded .zip asset; fall back to GitHub's auto-generated zipball.
        $release->download_url = $release->zipball_url;
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( substr( $asset->name, -4 ) === '.zip' ) {
                    $release->download_url = $asset->browser_download_url;
                    break;
                }
            }
        }

        set_transient( $this->cache_key, $release, 12 * HOUR_IN_SECONDS );
        return $release;
    }

    // -------------------------------------------------------------------------
    // Inject update into WP transient when a newer version exists
    // -------------------------------------------------------------------------
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $release = $this->get_release();
        if ( ! $release ) return $transient;

        $current = isset( $transient->checked[ $this->plugin_slug ] )
                   ? $transient->checked[ $this->plugin_slug ] : '';
        $latest  = ltrim( $release->tag_name, 'v' );

        if ( version_compare( $latest, $current, '>' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) array(
                'id'           => $this->plugin_slug,
                'slug'         => $this->plugin_folder,
                'plugin'       => $this->plugin_slug,
                'new_version'  => $latest,
                'url'          => "https://github.com/{$this->github_user}/{$this->github_repo}",
                'package'      => $release->download_url,
                'icons'        => array(),
                'banners'      => array(),
                'tested'       => '',
                'requires'     => '',
                'requires_php' => '',
            );
        }

        return $transient;
    }

    // -------------------------------------------------------------------------
    // Populate the "View details" popup in WP Admin
    // -------------------------------------------------------------------------
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_folder ) return $result;

        $release = $this->get_release();
        if ( ! $release ) return $result;

        $plugin_data = get_plugin_data( $this->plugin_file );

        return (object) array(
            'name'          => $plugin_data['Name'],
            'slug'          => $this->plugin_folder,
            'version'       => ltrim( $release->tag_name, 'v' ),
            'author'        => $plugin_data['AuthorName'],
            'homepage'      => "https://github.com/{$this->github_user}/{$this->github_repo}",
            'download_link' => $release->download_url,
            'last_updated'  => isset( $release->published_at ) ? $release->published_at : '',
            'sections'      => array(
                'description' => $plugin_data['Description'],
                'changelog'   => nl2br( isset( $release->body ) ? esc_html( $release->body ) : 'See GitHub releases for changelog.' ),
            ),
        );
    }

    // -------------------------------------------------------------------------
    // After install: rename the extracted folder to the correct plugin folder
    // -------------------------------------------------------------------------
    public function post_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
            return $result;
        }

        $dest = WP_PLUGIN_DIR . '/' . $this->plugin_folder;
        $wp_filesystem->move( $result['destination'], $dest, true );
        $result['destination'] = $dest;

        if ( is_plugin_active( $this->plugin_slug ) ) {
            activate_plugin( $this->plugin_slug );
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Clear cached release data after a plugin update completes
    // -------------------------------------------------------------------------
    public function clear_cache( $upgrader, $options ) {
        if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
            delete_transient( $this->cache_key );
        }
    }
}
