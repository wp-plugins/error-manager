<?php
/**
Plugin Name: Error Manager
Plugin URI: http://wordpress.org/plugins/error-manager/
Description: Logging and alerts for a variety of site errors
Version: 1.0
Author: DLS Software Studios
Author URI: http://www.dlssoftwarestudios.com/
*/

/*  Copyright 2015  DLS Software Studios

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!class_exists('DLS_Error_Data')) require_once dirname(__FILE__).'/data.php';
if (!class_exists('DLS_Error_Admin')) require_once dirname(__FILE__).'/admin.php';

class DLS_Error
{
    
    public $data;
    private $admin;
    
    private $plugin_path;
    private $request_uri;
    public $plugin_version = '1.0';
    public $db_version = '1.0';
    public $detailed_errors = false;
    
    public function __construct()
    {
        $this->data = new DLS_Error_Data();
        $this->admin = new DLS_Error_Admin();
        
        if (get_option($this->data->prefix().'detailed_errors') === 'true') {
            $this->detailed_errors = true;
        }
        
        // Set vars
        $plugin = plugin_basename(__FILE__);
        $this->plugin_path = dirname(__FILE__).'/';
        $this->request_uri = $_SERVER['REQUEST_URI'] . ((strstr($_SERVER['REQUEST_URI'], '?') === false) ? '?' : '&amp;');

        register_activation_hook(__FILE__, array(&$this, 'activate'));
        register_deactivation_hook( __FILE__, array(&$this, 'deactivate'));

        
        add_action('admin_menu', array(&$this->admin, 'menu'));
        
        add_filter("plugin_action_links_$plugin", array(&$this->admin, 'settings_link')); // Settings link on Plugins page
        add_action( 'plugins_loaded', array(&$this, 'after_plugins_loaded') );
    }

    /**
     * Don't start listeners until after all plugins are loaded.
     */
    public function after_plugins_loaded(){
        register_shutdown_function(array($this, 'fatal_error_listener'));
        add_action('wp', array(&$this, 'error_listener'));
        add_action($this->data->prefix().'php_recent_check', array(&$this, 'perform_php_recent_check'));
        $this->perform_php_recent_check();
    }


    /**
     * Listen for errors
     */
    public function error_listener()
    {
        global $wp;
        // 404
        if (function_exists('is_404') && is_404()) {

            // Log
            if (get_option($this->data->prefix().'log_404') === 'true') {
                $this->data->log('404');
            }
            
            // Alert
            if (get_option($this->data->prefix().'alert_404') === 'true') {
                $this->alert('404');
            }
        }
    }

    /**
     * Fatal error handler
     */
    public function fatal_error_listener()
    {
        $error = error_get_last();

        if ($error != null) {


         // Ignore non-fatal errors
            if (!isset($error['type']) || !($error['type'] === E_ERROR || $error['type'] === E_USER_ERROR)) return;

        // Set friendly error name
        $error['constant'] = (isset($this->data->php_errors[$error['type']])) ? $this->data->php_errors[$error['type']] : null;

        // Log
        if (get_option($this->data->prefix() . 'log_php_fatal') === 'true') {
            $this->data->log('php_error', $error);
        }

        // Alert
        if (get_option($this->data->prefix() . 'alert_php_fatal') === 'true') {
            $this->alert('php_error', $error);
        }
    }
    }
    
    /**
     * Send alert
     * 
     * @param string $code
     * @param string|array $notes
     * @return int|WP_Error
     */
    public function alert($code, $notes=null)
    {
        $message = "Error Code: $code\n".
            "Timestamp: ".current_time('mysql', 1)." GMT\n" .
            "URL: ".$this->data->get_current_url()."\n" .
            "Referrer: ".((!empty($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : null)."\n" .
            "IP: ".$this->data->get_client_ip()."\n\n" .
            ((!empty($notes)) ? "Notes: ".print_r($notes, true) : null);
        wp_mail($this->data->get_alert_recipients(), get_bloginfo('name')." - Error Alert: $code", $message.$this->data->footer);
        return;
    }
    
    /**
     * Perform Recent PHP Errors Log Check
     */
    public function perform_php_recent_check()
    {
        $file = ini_get('error_log');
        if (!file_exists($file)) return;
        $prev_last_modified = get_transient($this->data->prefix().'php_log_last_modified');
        $prev_last_line_time = get_transient($this->data->prefix().'php_log_last_line_time');
        $last_modified = filemtime($file);
        
        if ($last_modified <> $prev_last_modified) {
            set_transient($this->data->prefix().'php_log_last_modified', $last_modified);
            $i = 0;
            $file_lines = file($file);
            $file_lines = array_reverse($file_lines);
            $message_header = "Your PHP error log file has recently changed.  Included below are the most recent lines since the last file change was detected...\n\n";
            $message = null;
            $non_fatal = false;

            foreach ($file_lines as $line) {
                $i++;
                preg_match('/\[(.*?)\]/', $line, $line_time);
                if (!$non_fatal) {
                    preg_match('/\](.*?)\:/', $line, $error_type);
                    if (trim($error_type[1]) <> 'PHP Fatal error') $non_fatal = true;
                }
                if ($i === 1) set_transient($this->data->prefix().'php_log_last_line_time', $line_time[1]);
                if (isset($line_time[1]) && $line_time[1] == $prev_last_line_time) break;
                $modified_line = (isset($line_time[0])) ? str_replace($line_time[0], '', $line) : $line;

                $message .= "$line\n";
            }
            if (empty($message)) return;
            
            $message = $message_header.$message.$this->data->footer;

            if ($non_fatal) wp_mail($this->data->get_alert_recipients(), get_bloginfo('name')." - Error Log Changed", $message);
        }
    }
    
    /**
     * Activate the plugin
     */
    public function activate()
    {
        // Database Tables
        $sql = "CREATE TABLE {$this->data->tables['log']['name']} (
            id INT NOT NULL AUTO_INCREMENT,
            date DATETIME,
            type VARCHAR(50) NOT NULL,
            url TEXT NOT NULL,
            referrer TEXT NOT NULL,
            ip VARCHAR(50) NOT NULL,
            note LONGTEXT NOT NULL,
            UNIQUE KEY id (id)
        );";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        add_option($this->data->prefix().'db_version', $this->db_version);
        
        // Set default option values
        if (get_option($this->data->prefix().'alert_404') === false) {
            add_option($this->data->prefix().'alert_404', 'true', null, 'yes');
        }
        if (get_option($this->data->prefix().'alert_php_fatal') === false) {
            add_option($this->data->prefix().'alert_php_fatal', 'true', null, 'yes');
        }
        if (get_option($this->data->prefix().'log_404') === false) {
            add_option($this->data->prefix().'log_404', 'true', null, 'yes');
        }
        if (get_option($this->data->prefix().'log_php_fatal') === false) {
            add_option($this->data->prefix().'log_php_fatal', 'true', null, 'yes');
        }
        if (get_option($this->data->prefix().'alert_php_log_change') === false) {
            add_option($this->data->prefix().'alert_php_log_change', 'true', null, 'yes');
        }


        if (get_option($this->data->prefix().'alert_php_log_change_frequency') === false) {
            add_option($this->data->prefix().'alert_php_log_change_frequency', 'hourly', null, 'yes');
        }
        
        // Crons
        $alert_php_log_change = get_option($this->data->prefix().'alert_php_log_change');
        if (!empty($alert_php_log_change) && wp_get_schedule($this->data->prefix().'php_recent_check') === false) wp_schedule_event(time(), get_option($this->data->prefix().'alert_php_log_change_frequency'), $this->data->prefix().'php_recent_check');
    }
    
    /**
     * Deactivate the plugin
     */
    public function deactivate()
    {
        // Crons
        wp_clear_scheduled_hook($this->data->prefix().'php_recent_check');
    }
    
}

$dls_error = new DLS_Error();