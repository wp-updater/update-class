<?php

require_once(ABSPATH . 'wp-admin/includes/plugin.php');
require_once(ABSPATH . 'wp-admin/includes/update.php');

class WP_Updater
{
    private $packageId;
    private $directory;
    private $file;
    private $currentVersion;
    private $slug;

    private $endpointPackage = 'http://wordpresss-updater.local/api/package/';
    private $endpointDetails = 'http://wordpresss-updater.local/api/detail/';

    public function __construct($packageId, $directory, $file)
    {
        // none of this needs to be run if not in the admin area
        if(!is_admin()) {
            return;
        }
        $this->packageId = $packageId;
        $this->directory = $directory;
        $this->file = $file;
        $this->currentVersion = $this->current_version();
        if ($this->is_plugin()) {
            $this->register_hooks_for_plugin();
        } elseif ($this->is_theme()) {
            $this->register_hooks_for_theme();
        }
    }

    /**
     * delete transients to debug
     */
    public function debug()
    {
//        delete_transient('update_themes');
//        delete_transient('update_plugins');
    }

    private function current_version(): ?string
    {
        if ($this->is_plugin()) {
            return $this->currentVersion = get_plugin_data($this->file)['Version'];
        }
        if ($this->is_theme()) {
            return $this->currentVersion = wp_get_theme($this->get_theme_transient_slug())->get('Version');
        }
        return null;
    }

    private function is_plugin(): bool
    {
        return strpos($this->directory, 'wp-content/plugins') !== false;
    }

    private function is_theme(): bool
    {
        return strpos($this->directory, 'wp-content/themes') !== false;
    }

    private function register_hooks_for_theme(): void
    {
        // check for theme updates
        add_filter('site_transient_update_themes', [$this, 'update_theme_transients'], 20, 1);
    }

    private function register_hooks_for_plugin(): void
    {
        // check for plugin updates
        add_filter('site_transient_update_plugins', [$this, 'update_plugin_transients'], 20, 1);

        // display plugin details when view more details is pressed
        add_filter('plugins_api', [$this, 'plugins_api'], 20, 3);
    }

    public function plugins_api($res, $action, $args)
    {
        if ($action === 'plugin_information') {
            if ($args->slug == $this->slug) {
                return $this->get_remote_plugin_details_information();
            }
        }
        return $res;
    }

    public function update_theme_transients($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }

        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }

        if (!array_key_exists($this->get_theme_transient_slug(), $transient->response)) {
            $response = $this->get_remote_theme_version_information();

            if(version_compare($this->currentVersion, $response->version, '<')) {
                $transient->response[$this->get_theme_transient_slug()] = [
                    'theme' => $response->slug, // Whatever you want, as long as it's not on WordPress.org
                    'new_version' => $response->version, // The newest version
                    'url' => $response->url, // Informational
                    'package' => $response->package // Where WordPress should pull the ZIP from.
                ];
            }
        }

        return $transient;
    }

    public function update_plugin_transients($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }

        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }

        if (!array_key_exists($this->get_plugin_transient_slug(), $transient->response)) {
            $response = $this->get_remote_plugin_version_information();
            $this->slug = $response->slug;

            $transient->response[$this->get_plugin_transient_slug()] = (object)[
                'slug' => $response->slug,
                'new_version' => $response->version,
                'url' => $response->url,
                'package' => $response->package
            ];
        } else {
            $this->slug = $transient->response[$this->get_plugin_transient_slug()]->slug;
        }
        return $transient;
    }

    /**
     * Retrieve information for when the user clicks view more details
     *
     * @return array|mixed|object
     */
    private function get_remote_plugin_details_information(): object
    {
        $body = $this->remote_post($this->endpointDetails . $this->packageId);
        // convert sections and banners into arrays
        $body->sections = (array)$body->sections;
        $body->banners = (array)$body->banners;

        return $body;
    }

    /**
     * Retrieve version information for this plugin
     *
     * @return array|mixed|object
     */
    private function get_remote_plugin_version_information(): object
    {
        return $this->remote_post($this->endpointPackage . $this->packageId);
    }


    /**
     * Retrieve theme version information
     *
     * @return object
     */
    private function get_remote_theme_version_information(): object
    {
        return $this->remote_post($this->endpointPackage . $this->packageId);
    }

    /**
     * POST wrapper for wp_remote_post
     * @param $uri
     * @return array|mixed|object
     */
    private function remote_post($uri) {
        global $wp_version;
        if ( false === ( $response = get_transient( md5($uri) ) ) ) {
            $response = wp_remote_post($uri, [
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url('/')
                ]
            ]);
            set_transient(md5($uri), $response, 5 * 60);
        }



        return json_decode($response['body']);
    }

    private function get_theme_transient_slug(): string
    {
        $file = str_replace(
            ABSPATH . 'wp-content/themes/',
            '',
            $this->file
        );

        return explode('/', $file)[0];
    }


    private function get_plugin_transient_slug(): string
    {
        return str_replace(
            ABSPATH . 'wp-content/plugins/',
            '',
            $this->file
        );
    }
}
