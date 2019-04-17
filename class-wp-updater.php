<?php
require_once(ABSPATH . 'wp-admin/includes/plugin.php');
require_once(ABSPATH . 'wp-admin/includes/update.php');

class WP_Updater {
    private $url;
    private $directory;
    private $file;
    private $currentVersion;
    private $slug;

    public function __construct($url, $directory, $file)
    {
        $this->url = $url;
        $this->directory = $directory;
        $this->file = $file;
        $this->currentVersion = $this->current_version();
        $this->register_ui_methods_for_plugin();
    }

    private function current_version()
    {
        if($this->is_plugin()) {
            return $this->currentVersion = get_plugin_data($this->file)['Version'];
        }
        if($this->is_theme()) {
            return 0; //TODO: find how to return theme version number here
        }
        return null;
    }

    private function is_plugin()
    {
        return strpos($this->directory, 'wp-content/plugins') !== false;
    }

    private function is_theme()
    {
        return strpos($this->directory, 'wp-content/themes') !== false;
    }

    private function register_ui_methods_for_plugin()
    {
        add_filter( 'site_transient_update_plugins', [ $this, 'update_transients' ], 20, 1 );
        add_filter('plugins_api_result', [ $this, 'plugins_api_result'], 20, 3);
        add_filter('plugins_api', [ $this, 'plugins_api'], 20, 3);
        wp_plugin_update_row($this->file, []);
//        add_filter('plugin_row_meta', [$this, 'test'], 20, 4);
    }

    public function plugins_api($res, $action, $args) {
        if($action === 'plugin_information'){
            if($args->slug == $this->slug) {
                return $this->get_remote_details_information();
                //var_dump($res, $action, $args); wp_die();

            }
        }
        return $res;

    }

    public function plugins_api_result($res, $action, $args) {
        //return true;
        if($action === 'plugin_information'){
            if($args->slug == $this->slug) {
                return $this->get_remote_details_information();

            }
        }

        return $res;
    }

    public function update_transients($transient)
    {
        if ( ! is_object( $transient ) ) {
            return $transient;
        }

        if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
            $transient->response = array();
        }

        if(!array_key_exists($this->file, $transient->response)) {
            $response = $this->get_remote_version_information();
            $this->slug = $response->slug;
            if(version_compare($this->currentVersion, $response->version, '<')) {
                $transient->response[$this->get_transient_slug()] = (object) [
                    'slug'         => $response->slug, // Whatever you want, as long as it's not on WordPress.org
                    'new_version'  => $response->version, // The newest version
                    'url'          => $response->url, // Informational
                    'package'      => $response->package // Where WordPress should pull the ZIP from.
                ];
            }
        }
        //var_dump($transient); wp_die();
        return $transient;
    }

    public function get_transient_slug()
    {
        $file = str_replace(ABSPATH, '', $this->file);
        $slug = str_replace('wp-content/plugins/', '', $file);
        return $slug;
    }

    public function get_remote_details_information()
    {
        return new stdClass();
    }

    public function get_remote_version_information()
    {
        $response = wp_remote_post($this->url, [
            'headers'     => ['Content-Type' => 'application/json; charset=utf-8'],
        ]);

        return json_decode($response['body']);
    }

}
