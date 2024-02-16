<?php
/**
 * WP Custom GeoIP Class
 *
 * This class handles GeoIP functionality including fetching location data based on IP address.
 */
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/* Check if Class Exists. */
if (! class_exists('GeoIP')) {
    class GeoIP
    {
        /**
         * @var string the name of the GeoIP plugin
         */
        public $name;

        /**
         * @var string the prefix used for GeoIP options
         */
        public $prefix;

        /**
         * @var string the title of the GeoIP settings page
         */
        public $title;

        /**
         * @var string the slug of the GeoIP settings page
         */
        public $slug;

        /**
         * @var string FIELDS The API fields to retrieve from the GeoIP API
         */
        const FIELDS = '?fields=status,message,countryCode,regionName,city';

        /**
         * @var string GEOIP_URL The base URL for the GeoIP API
         */
        const GEOIP_URL = 'http://pro.ip-api.com/json/';

        /**
         * @var string GEOIP_STAGE_URL The base URL for the GeoIP staging API
         */
        const GEOIP_STAGE_URL = 'http://ip-api.com/json/';

        /**
         * Constructor function to initialize the GeoIP plugin.
         */
        public function __construct()
        {
            $this->name   = 'geoip';
            $this->prefix = 'geo_';
            $this->title  = 'Geo IP Settings';
            $this->slug   = str_replace('_', '-', $this->name);
            if (is_admin()) { // Admin actions.
                add_action('admin_menu', [$this, 'geo_add_admin_page']);
                add_action('admin_init', [$this, 'geo_add_custom_settings']);
            }
        }

        /**
         * Adds the GeoIP settings page to the WordPress admin menu.
         */
        public function geo_add_admin_page()
        {
            add_options_page(
                __('Geo IP Settings', 'textdomain'),
                __('Geo IP Settings', 'textdomain'),
                'manage_options',
                $this->slug,
                [$this, 'geo_render_settings_page']
            );
        }

        /**
         * Adds custom settings for the GeoIP plugin.
         */
        public function geo_add_custom_settings()
        {
            add_settings_section(
                $this->prefix . 'geoip_settings',
                __('API Settings', 'textdomain'),
                [$this, 'geo_geoip_settings_callback'],
                $this->slug . '-api-settings'
            );
            register_setting(
                $this->prefix . 'geoip_settings',
                $this->prefix . 'api_keys'
            );
        }

        /**
         * Builds the API URL for fetching GeoIP data based on the given IP address.
         *
         * @param string $ip the IP address
         *
         * @return string the constructed API URL
         */
        private function build_api_url($ip)
        {
            $api_options = get_option($this->prefix . 'api_keys');
            $apikey      = (! empty($api_options['api'])) ? $api_options['api'] : '';

            return (($apikey) ? self::GEOIP_URL : self::GEOIP_STAGE_URL) . $ip . self::FIELDS . (($apikey) ? '&key=' . $api_options['api'] : '');
        }

        /**
         * Fetches location data based on the given IP address.
         *
         * @param string $ip the IP address
         *
         * @return object|null the location data
         */
        private function get_location($ip)
        {
            $url  = $this->build_api_url($ip);
            $curl = curl_init();
            curl_setopt_array(
                $curl,
                [
                    CURLOPT_URL            => $url,
                    CURLOPT_HEADER         => 0,
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_FRESH_CONNECT  => 1,
                    CURLOPT_TIMEOUT        => 5,
                ]
            );
            $result = curl_exec($curl);
            curl_close($curl);

            return json_decode($result);
        }

        /**
         * Callback function for displaying API settings fields.
         */
        public function geo_geoip_settings_callback()
        {
            settings_fields($this->prefix . 'geoip_settings');
            $this->geo_api_settings_callback();
        }

        /**
         * Displays API settings fields.
         */
        public function geo_api_settings_callback()
        {
            $api_options = get_option($this->prefix . 'api_keys');
            ?>
<p><?php _e('GEO IP is from IP-API', 'textdomain'); ?> <a href="http://ip-api.com/">http://ip-api.com/</a></p>
<label for="<?php echo esc_attr($this->prefix); ?>api_keys[api]"><?php _e('API Key', 'textdomain'); ?></label>
<input name="<?php echo esc_attr($this->prefix); ?>api_keys[api]" type="text" value="<?php echo esc_attr($api_options['api']); ?>" />
<br>
<?php
        }

        /**
         * Renders the GeoIP settings page in the WordPress admin.
         */
        public function geo_render_settings_page()
        {
            ?>

<div class="wrap">

    <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
    <form method="post" action="options.php">

        <?php
                    do_settings_sections($this->slug . '-api-settings');
            submit_button(__('Save Geo Settings', 'textdomain'), 'primary', 'submit');
            ?>

    </form>
</div>

<?php
        }

        /**
         * Retrieves the location data for the given IP address.
         *
         * @param string $ip the IP address
         *
         * @return object the location data
         */
        public function get_location_by_ip($ip = '')
        {
            $ip = $this->get_ip_address();
            if (empty($_COOKIE['geo_location'])) {
                try {
                    $location = $this->get_location($ip);
                    if (! empty($location->status) && 'success' == $location->status && 'US' == $location->countryCode) {
                        if ('California' != $location->regionName && 'Texas' != $location->regionName) {
                            $location->regionName = 'California';
                            $location->city       = 'Los Angeles';
                        }
                    } else {
                        $location              = new stdClass();
                        $location->status      = 'success';
                        $location->countryCode = 'US';
                        $location->regionName  = 'California';
                        $location->city        = 'Los Angeles';
                    }
                    setcookie('geo_location', json_encode($location), 0, '/');

                    return $location;
                } catch (Exception $e) {
                    $location              = new stdClass();
                    $location->status      = 'success';
                    $location->countryCode = 'US';
                    $location->regionName  = 'California';
                    $location->city        = 'Los Angeles';
                    setcookie('geo_location', json_encode($location), 0, '/');
                }
            }
        }

        /**
         * Retrieves the user's IP address.
         *
         * @return string the user's IP address
         */
        public function get_ip_address()
        {
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '1.1.1.1';
            // Cloudflare
            $ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $ip;
            // Reblaze
            $ip = isset($_SERVER['X-Real-IP']) ? $_SERVER['X-Real-IP'] : $ip;
            // Sucuri
            $ip = isset($_SERVER['HTTP_X_SUCURI_CLIENTIP']) ? $_SERVER['HTTP_X_SUCURI_CLIENTIP'] : $ip;
            // Ezoic
            $ip = isset($_SERVER['X-FORWARDED-FOR']) ? $_SERVER['X-FORWARDED-FOR'] : $ip;
            // Akamai
            $ip = isset($_SERVER['True-Client-IP']) ? $_SERVER['True-Client-IP'] : $ip;
            // Clouways
            $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $ip;
            // Varnish trash ?
            $ip = str_replace(['::ffff:', ', 127.0.0.1'], '', $ip);
            if (preg_match('/,/', $ip)) {
                $ips = explode(',', $ip);
                $ip  = trim($ips[0]);
            }
            if ('127.0.0.1' == $ip || '::1' == $ip) {
                $ip = '24.176.217.66';
            }

            return $ip;
        }
    }
}
