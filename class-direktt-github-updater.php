<?php

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

if (!class_exists('Direktt_Github_Updater')) {
    class Direktt_Github_Updater
    {
        private $version;
        private $cache_key;
        private $cache_allowed;
        private $plugin_slug;
        private $info_json_url;
        private $plugin_file_path;
        

        public function __construct($version, $plugin_slug, $info_json_url, $cache_key, $cache_allowed = true)
        {
            $this->version = $version;
            $this->cache_key     = $cache_key;
            $this->cache_allowed = $cache_allowed;
            $this->plugin_slug = $plugin_slug;
            $this->info_json_url = $info_json_url;
            $this->plugin_file_path = WP_PLUGIN_DIR . '/' . $this->plugin_slug;
        }

        public function get_github_update_info()
        {
            $remote_info = get_transient($this->cache_key);

            if (false === $remote_info || ! $this->cache_allowed) {
                $response = wp_remote_get($this->info_json_url);

                if (!is_wp_error($response) && $response['response']['code'] == 200) {
                    $remote_info = json_decode($response['body']);

                    // Look for the asset that is a ZIP file
                    if (!empty($remote_info->assets)) {
                        foreach ($remote_info->assets as $asset) {
                            if (strpos($asset->name, '.zip') !== false) {
                                $remote_info->download_url = $asset->browser_download_url;
                                break;
                            }
                        }
                    }

                    set_transient($this->cache_key, $remote_info, HOUR_IN_SECONDS);
                }
            }

            return $remote_info;
        }

        public function github_info($response, $action, $args)
        {
            if ('plugin_information' !== $action) {
                return $response;
            }

            if (empty($args->slug)) {
                return $response;
            }

            $plugin_slug = $this->plugin_slug;

            if (!empty($args->slug) && $args->slug === $plugin_slug) {

                $update_info = $this->get_github_update_info();
                $response = new \stdClass();

                $response->name           = $update_info->name;
                $response->slug           = $update_info->slug;
                $response->version        = $update_info->version;
                $response->tested         = $update_info->tested;
                $response->requires       = $update_info->requires;
                $response->author         = $update_info->author;
                $response->author_profile = $update_info->author_profile;
                if (! empty($update_info->donate_link)) {
                    $response->donate_link    = $update_info->donate_link;
                }
                $response->homepage       = $update_info->homepage;
                $response->download_link  = $update_info->download_url;
                $response->trunk          = $update_info->download_url;
                $response->requires_php   = $update_info->requires_php;
                if (! empty($update_info->last_updated)) {
                    $response->last_updated    = $update_info->last_updated;
                }
                $response->new_version    = $update_info->version;

                $response->sections = [
                    'description'  => $update_info->sections->description,
                    'installation' => $update_info->sections->installation,
                    'changelog'    => $update_info->sections->changelog
                ];

                if (! empty($update_info->banners)) {
                    $response->banners = [
                        'low'  => $update_info->banners->low,
                        'high' => $update_info->banners->high
                    ];
                }
            }

            return $response;
        }

        public function github_update($transient)
        {
            if (empty($transient->checked)) {
                return $transient;
            }

            $plugin_file_path =  $this->plugin_file_path;

            $plugin_data = get_plugin_data($plugin_file_path);

            $plugin_slug = $this->plugin_slug;
            $plugin_current_version = $plugin_data['Version'];
            $update_info = $this->get_github_update_info();

            if ($update_info && version_compare($plugin_current_version, $update_info->version, '<')) {
                $package = $update_info->download_url;

                $obj = new stdClass();
                $obj->slug = $plugin_slug;
                $obj->new_version = $update_info->version;
                $obj->url = $package;
                $obj->package = $package;

                $transient->response[$plugin_slug] = $obj;
            }

            return $transient;
        }

        public function delete_transient()
        {
            delete_transient($this->cache_key);
        }

        public function purge($upgrader, $options)
        {

            if ($this->cache_allowed && 'update' === $options['action'] && 'plugin' === $options['type']) {
                // just clean the cache when new plugin version is installed
                delete_transient($this->cache_key);
            }
        }
    }
}
