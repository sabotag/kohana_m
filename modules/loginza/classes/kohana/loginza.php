<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_Loginza {

    protected static $profile;

    protected static $error = FALSE;

    /**
     * @return Loginza
     */
    public static function instance()
    {
        static $instance = NULL;
        
        if ($instance == NULL)
        {
            $config = Kohana::$config->load('loginza');

            $instance = new self($config);
        }

        return $instance;
    }
    
    protected function __construct($config = array())
    {
        $this->config = $config;

        $this->session = Session::instance();
    }
    
    public function check($token = NULL)
    {
        if (is_null($token))
        {
            $token = Arr::get($_POST, 'token', FALSE);
        }
        
        if ( ! empty($token)) 
        {
            //безопасный режим
            if ( ! empty($this->config->widget_id) AND ! empty($this->config->widget_key))
            {
                $profile = $this->_get_auth_info($token, $this->config->widget_id, md5($token.$this->config->widget_key));
            }
            else
            {
                $profile = $this->_get_auth_info($token, $this->config->widget_id, $this->config->widget_key);
            }

            if ( ! empty($profile['error_type'])) 
            {
                self::$error = $profile['error_message'];
            }
            elseif (empty($profile)) 
            {
                self::$error = 'Temporary error';
            }
            else 
            {    
                self::$profile = $profile;
                
                self::$error = FALSE;
            }
        }
        else
        {
            self::$error = 'not token';
        }

        return $this;
    }
    
    public function get_profile()
    {
        if (self::$error)
        {
            throw new Exception_Loginza(self::$error);
        }
        
        $data = $this->_parse_profile(self::$profile);
        
        return $data;
    }
    
    public function get_clear_profile()
    {
        $data = $this->get_profile();
        
        $data['profile'] = $this->_get_mappings($data['profile']);
        
        return $data;
    }
    
    protected function _parse_profile($profile)
    {
        $array = array(
            'nickname' => Arr::get($profile, 'nickname', $this->_gen_nickname($profile)),
            'site' => Arr::get($profile, 'site', $this->_gen_site($profile)),
            'full_name' => Arr::get($profile, 'full_name', $this->_gen_full_name($profile)),
        );

        $profile = Arr::merge($profile, $array);
        
        $system = array(
            'identity' => $profile['identity'],
            'provider' => $profile['provider']
        );
        
        unset($profile['identity'], $profile['provider']);
        
        return array('profile' => $profile, 'system' => $system);
    }
    
    protected function _get_mappings($profile)
    {
        $return_array = array();
        
        foreach ($this->config->mapping_paths as $key => $path)
        {
            if (($find = Arr::path($profile, $path)))
            {
                $return_array[$key] = $find;
            }
        }
        
        return $return_array;
    }

    protected function _gen_site($profile)
    {
        if (isset($profile['web']['blog']) AND ! empty($profile['web']['blog'])) 
        {
            return $profile['web']['blog'];
        }
        elseif (isset($profile['web']['default']) AND ! empty($profile['web']['default'])) 
        {
            return $profile['web']['default'];
        }

        return FALSE;
    }

    protected function _gen_nickname($profile)
    {
        if (isset($profile['email']) AND ! empty($profile['email']) AND preg_match('/^(.+)\@/i', $profile['email'], $nickname)) 
        {
            return $nickname[1];
        }

        // шаблоны по которым выцепляем ник из identity
        $patterns = array(
            '([^\.]+)\.ya\.ru',
            'openid\.mail\.ru\/[^\/]+\/([^\/?]+)',
            'openid\.yandex\.ru\/([^\/?]+)',
            '([^\.]+)\.myopenid\.com'
        );

        foreach ($patterns as $pattern) 
        {
            if (preg_match('/^https?\:\/\/'.$pattern.'/i', $profile['identity'], $result)) 
            {
                return $result[1];
            }
        }

        return FALSE;
    }
    
    protected function _gen_full_name($profile) 
    {
        if (isset($profile['name']['full_name'])) 
        {
            return $profile['name']['full_name'];
        }

        $full_name = trim(
                     (isset($profile['name']['first_name']) ? $profile['name']['first_name'] : '') .' '. 
                     (isset($profile['name']['last_name']) ? $profile['name']['last_name'] : '')
                     );

        if ($full_name)
        {
            return $full_name;
        }

        return FALSE;
    }

    protected function _get_auth_info($token, $id, $sig)
    {
        return $this->_api_request('authinfo', array('token' => $token, 'id' => $id, 'sig' => $sig));
    }
    
    protected function _api_request($method, $params) 
    {
        // url запрос
        $url = str_replace('%method%', $method, $this->config->api_url).'?'.http_build_query($params, NULL, '&');

        if ( function_exists('curl_init') ) 
        {
            $curl = curl_init($url);
            $user_agent = 'LoginzaAPI'.$this->config->version.'/php'.phpversion();

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($curl, CURLOPT_HEADER, FALSE);
            curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            $raw_data = curl_exec($curl);
            curl_close($curl);
            $responce = $raw_data;
        } 
        else 
        {
            $responce = file_get_contents($url);
        }

        // обработка JSON ответа API
        return json_decode($responce, TRUE);
    }
    
    public function get_widget_url()
    {
        $params = array();

        if (isset($this->config->provider))
        {
            $params['provider'] = $this->config->provider;
        }

        if (isset($this->config->token_url))
        {
	        $params['token_url'] = $this->config->token_url;
        }
	    else
	    {
		    $params['token_url'] = $this->_current_url();
	    }

        $params['lang'] = Arr::get($this->config, 'lang', 'ru');

        $ret_url = $this->config->widget_url.'?'.http_build_query($params);

        if (isset($this->config['providers']))
        {
            $ret_url .= '&providers_set='.implode(',', array_unique(array_values($this->config['providers'])));
        }

        return $ret_url;
    }
    
    private function _current_url()
    {
        $url = array();
        // проверка https
        if(isset($_SERVER['HTTPS']) AND $_SERVER['HTTPS']=='on') 
        {
            $url['sheme'] = "https";
            $url['port'] = '443';
        } 
        else 
        {
            $url['sheme'] = 'http';
            $url['port'] = '80';
        }
        // хост
        $url['host'] = $_SERVER['HTTP_HOST'];
        // если не стандартный порт
        if (strpos($url['host'], ':') === FALSE AND $_SERVER['SERVER_PORT'] != $url['port']) 
        {
            $url['host'] .= ':'.$_SERVER['SERVER_PORT'];
        }

        // строка запроса
        if (isset($_SERVER['REQUEST_URI'])) 
        {
            $url['request'] = $_SERVER['REQUEST_URI'];
        } 
        else
        {
            $url['request'] = $_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'];
            $query = $_SERVER['QUERY_STRING'];
            if (isset($query)) 
            {
              $url['request'] .= '?'.$query;
            }
        }

        return $url['sheme'].'://'.$url['host'].$url['request'];
    }
    
    public function get_provider_slug($provider)
    {
        // this piece code from http://loginza.ru/wp-plugin
        if (preg_match('/^https?:\/\/([^\.]+\.)?([a-z0-9\-\.]+\.[a-z]{2,5})/i', $provider, $matches))
        {
            if (array_key_exists($matches[2], $this->config->providers))
            {
                return $this->config->providers[$matches[2]];
            }
        }

        return 'openid';
    }

}
