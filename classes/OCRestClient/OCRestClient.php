<?php
/**
 * OCRestClient.php - The administarion of the opencast player
 */

define(DEBUG_CURL, FALSE);

class OCRestClient
{
    static $me;

    protected $base_url,
        $username,
        $password,
        $oc_version,
        $config_id,
        $cookie;

    public $serviceName = 'ParentRestClientClass';

    static function getInstance($config_id = null)
    {
        // use default config if nothing else is given
        if (is_null($config_id) || $config_id === false) {
            $config_id = OCConfig::getConfigIdForCourse(Context::getId()) ?: 1;
        }

        if (!property_exists(get_called_class(), 'me')) {
            throw new Exception('Every child of ' . get_class() . ' needs to implement static property "$me"');
        }

        if (!is_object(static::$me[$config_id])) {
            static::$me[$config_id] = new static($config_id);
        }

        return static::$me[$config_id];
    }

    function __construct($config)
    {
        $this->base_url   = $config['service_url'];
        $this->username   = $config['service_user'];
        $this->password   = $config['service_password'];
        $this->oc_version = $config['service_version'];

        if ($config['config_id'] == null){
            $config['config_id'] = -1;
        }

        $this->config_id = $config['config_id'];

        $precise_config = Configuration::instance($config['config_id']);

        // setting up a curl-handler
        $this->ochandler = curl_init();
        curl_setopt($this->ochandler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ochandler, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($this->ochandler, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($this->ochandler, CURLOPT_ENCODING, $precise_config['upload_encoding']);
        curl_setopt($this->ochandler, CURLOPT_HTTPHEADER, ["X-Requested-Auth: Digest"]);

        curl_setopt($this->ochandler, CURLOPT_FOLLOWLOCATION, 1);

        //ssl
        curl_setopt($this->ochandler, CURLOPT_SSL_VERIFYPEER, $precise_config['ssl_verify_peer']);
        curl_setopt($this->ochandler, CURLOPT_SSL_VERIFYHOST, $precise_config['ssl_verify_host']);
        if ($precise_config['ssl_cipher_list'] != 'none') {
            curl_setopt($this->ochandler, CURLOPT_SSL_CIPHER_LIST, $precise_config['ssl_cipher_list']);
        }

        // debugging
        if (DEBUG_CURL) {
            curl_setopt($this->ochandler, CURLOPT_VERBOSE, true);
            echo '<pre>';
        }
    }

    public function has_config_error()
    {
        return
            ($this->username == 'error' || $this->username == null) &&
            ($this->password == 'error' || $this->password == null) &&
            ($this->oc_version == 'error' || $this->oc_version == null) &&
            ($this->base_url == 'error' || $this->base_url == null);
    }

    function setCookie($name, $value)
    {
        $this->cookie = $name .'='. $value;
    }

    function getCookie()
    {
        return $this->cookie;
    }

    /**
     *  function getJSON - performs a REST-Call and retrieves response in JSON
     */
    function getJSON($service_url, $data = [], $is_get = true, $with_res_code = false)
    {
        if (isset($service_url)) {
            if (DEBUG_CURL) {
                $this->debug = fopen('php://output', 'w');
                curl_setopt($this->ochandler, CURLOPT_STDERR, $this->debug);
            }

            $options = [
                CURLOPT_URL           => $this->base_url . $service_url,
                CURLOPT_FRESH_CONNECT => 1
            ];

            if (!$is_get) {
                $options[CURLOPT_POST] = 1;
                if (!empty($data)) {
                    $options[CURLOPT_POSTFIELDS] = $data;
                }
            } else {
                $options[CURLOPT_HTTPGET] = 1;
            }


            if (!DEBUG_CURL) {  // CURLINFO_HEADER_OUT ist not worjing in conjunction with CURLOPT_VERBOSE
                // see: https://bugs.php.net/bug.php?id=65348
                curl_setopt($this->ochandler, CURLINFO_HEADER_OUT, true);
            }
            curl_setopt_array($this->ochandler, $options);


            if ($this->getCookie()) {
                curl_setopt($this->ochandler, CURLOPT_HTTPHEADER, ['Cookie: '. $this->getCookie()]);
            }

            $response = curl_exec($this->ochandler);
            $httpCode = curl_getinfo($this->ochandler, CURLINFO_HTTP_CODE);

            if (DEBUG_CURL) {
                fclose($this->debug);
            }

            if ($with_res_code) {
                return [json_decode($response) ? : $response, $httpCode];
            } else {
                // throw exception if the endpoint is missing
                if ($httpCode == 404) {
                    if (DEBUG_CURL) {
                        error_log('[Opencast-Plugin] Error calling "'
                            . $this->base_url . $service_url
                            . '" ' . strip_tags($response)
                        );
                    }

                    return false;
                } else if ($httpCode == 401) {
                    throw new AccessDeniedException('Wrong username/password for Opencast server!');
                } else {
                    return json_decode($response);
                }
            }
        } else {
            throw new Exception(_("Es wurde keine Service URL angegeben"));
        }

    }

    /**
     * function getXML - performs a REST-Call and retrieves response in XML
     */
    function getXML($service_url, $data = [], $is_get = true, $with_res_code = false)
    {
        if (isset($service_url)) {
            if (DEBUG_CURL) {
                $this->debug = fopen('php://output', 'w');
                curl_setopt($this->ochandler, CURLOPT_STDERR, $this->debug);
            }

            $options = [
                CURLOPT_URL           => $this->base_url . $service_url,
                CURLOPT_FRESH_CONNECT => 1
            ];

            if (!$is_get) {
                $options[CURLOPT_POST] = 1;
                if (!empty($data)) {
                    $options[CURLOPT_POSTFIELDS] = $data;
                }
            } else {
                $options[CURLOPT_HTTPGET] = 1;
            }

            if ($this->getCookie()) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Cookie: '. $this->getCookie()]);
            }

            curl_setopt_array($this->ochandler, $options);
            $response = curl_exec($this->ochandler);
            $httpCode = curl_getinfo($this->ochandler, CURLINFO_HTTP_CODE);

            if (DEBUG_CURL) {
                fclose($this->debug);
            }

            if ($with_res_code) {
                return [$response, $httpCode];
            } else {
                // throw exception if the endpoint is missing
                if ($httpCode == 404) {
                    if (DEBUG_CURL) {
                        error_log('[Opencast-Plugin] Error calling "'
                            . $this->base_url . $service_url
                            . '" ' . strip_tags($response)
                        );
                    }

                    return false;
                } else {
                    return $response;
                }
            }
        } else {
            throw new Exception(_("Es wurde keine Service URL angegeben"));
        }
    }
}
