<?php
/**
 * Database queries and actions
 */

class DLS_Error_Data
{
    
    public $wpdb;
    public $err;
    public $detailed_errors = false;
    public $plugin_prefix = 'dls_error';
    public $tables = array();
    public $footer = null;
    public $php_errors = array(
        // value => constant
        1 => 'E_ERROR',
        2 => 'E_WARNING',
        4 => 'E_PARSE',
        8 => 'E_NOTICE',
        16 => 'E_CORE_ERROR',
        32 => 'E_CORE_WARNING',
        64 => 'E_COMPILE_ERROR',
        128 => 'E_COMPILE_WARNING',
        256 => 'E_USER_ERROR',
        512 => 'E_USER_WARNING',
        1024 => 'E_USER_NOTICE',
        2048 => 'E_STRICT',
        4096 => 'E_RECOVERABLE_ERROR',
        8192 => 'E_DEPRECATED',
        16384 => 'E_USER_DEPRECATED',
    );
    
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        if (get_option($this->prefix().'detailed_errors') === 'true') {
            $this->detailed_errors = true;
        }
        
        // Set table names
        $this->tables = array(
            'log' => array(
                'name' => $this->wpdb->prefix.$this->prefix().'log',
                'allowed_fields' => array(
                    'date' => false,
                    'type' => false,
                    'url' => false,
                    'referrer' => false,
                    'ip' => false,
                    'note' => false,
                ),
            ),
        );
        
        $this->footer = "\n\n--\nThis message was sent from ".get_site_url()." by the Error Manager plugin.";
    }
    
    /**
     * Get plugin prefix string
     * 
     * @param mixed $trailing_separator
     * @param mixed $separator_replacement
     * @return string prefix
     */
    public function prefix($trailing_separator=true, $separator_replacement=null)
    {
        $prefix = $this->plugin_prefix;
        if ($trailing_separator === true) $prefix .= '_';
        if (!is_null($separator_replacement)) $prefix = str_replace('_', $separator_replacement, $prefix);
        return $prefix;
    }
    
    /**
     * Add a new log entry
     * 
     * @param string $code
     * @param string|array $notes
     * @return int|WP_Error
     */
    public function log($code, $notes=null)
    {
        $data = array(
            'date' => current_time('mysql', 1),
            'type' => $code,
            'url' => $this->get_current_url(),
            'referrer' => ((!empty($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : null),
            'ip' => $this->get_client_ip(),
            'note' => maybe_serialize($notes),
        );
        
        $result = $this->wpdb->insert($this->tables['log']['name'], $data);
        if ($result === false) return $this->err('add_log_error', 'Error adding log record... '.print_r(mysql_error(), true));
        return $result;
    }
    
    /**
     * Get email alert recipient or default to admin value
     * 
     * @return string comma separated email address
     */
    public function get_alert_recipients()
    {
        $emails = array();
        if (get_option($this->prefix().'alert_admin') === 'true') $emails[] = get_option('admin_email');
        $other = explode(',', get_option($this->prefix().'alert_emails'));
        $emails = array_merge($emails, $other);
        return implode(',', $emails);
    }
    
    /**
     * Get current URL
     * 
     * @return string url
     */
    public function get_current_url()
    {
        if (!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
            return null;
        }
        
        return sprintf(
            "%s://%s%s",
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
            $_SERVER['HTTP_HOST'],
            $_SERVER['REQUEST_URI']
        );
    }
    
    /**
     * Get the client ip address
     * 
     * @return string ip address
     */
    public function get_client_ip()
    {
        $server_vars = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );
        foreach ($server_vars as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }
    }
    
    private function err($code, $message)
    {
        if (is_wp_error($this->err)) {
            $this->err->add($code, $message);
        } else {
            $this->err = new WP_Error($code, $message);
        }
        return $this->err;
    }
    
}