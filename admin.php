<?php
/**
 * Admin Class
 */

if (!class_exists('DLS_Error_Data')) require_once dirname(__FILE__).'/data.php';

class DLS_Error_Admin
{
    
    public $data;
    public $detailed_errors = false;
    private $admin_settings_slug;
    
    public function __construct()
    {
        $this->data = new DLS_Error_Data();
        
        if (get_option($this->data->prefix().'detailed_errors') === 'true') {
            $this->detailed_errors = true;
        }
        
        $this->admin_settings_slug = $this->data->prefix(true, '-').'settings';
    }
        
    /**
     * Admin Menu
     */
    public function menu()
    {
        add_options_page('Error Manager Settings', 'Error Manager', 'manage_options', $this->admin_settings_slug, array(&$this, 'options'));
    }

    /**
     * Page: Options/Settings
     */
    function options()
    {
        if (!current_user_can('manage_options'))  {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $cron_frequency = array(
            'hourly' => 'Hourly',
            'twicedaily' => 'Twice Daily',
            'daily' => 'Daily',
        );
        
        // Define Options
        $options = array(
            'Notification Settings',
            array('Alert Admin Email', $this->data->prefix().'alert_admin', 'checkbox', '(send alerts to admin email set in Settings > General)'),
            array('Email(s) to Alert', $this->data->prefix().'alert_emails', 'text', '(comma separate multiple emails)'),
            'Instant Alerts',
            array('404 Errors', $this->data->prefix().'alert_404', 'checkbox', null),
            array('PHP Fatal Error', $this->data->prefix().'alert_php_fatal', 'checkbox', null),
            'Logs (in database)',
            array('404 Errors', $this->data->prefix().'log_404', 'checkbox', null),
            array('PHP Fatal Error', $this->data->prefix().'log_php_fatal', 'checkbox', null),
            'Scheduled Alerts',
            array('PHP Error Log Changes', $this->data->prefix().'alert_php_log_change', 'checkbox', 'Sends an email if there has been a change to the PHP error log'),
            array('PHP Error Log Changes (frequency)', $this->data->prefix().'alert_php_log_change_frequency', 'radio', null, $cron_frequency),

        );
        
        // Display Options
        $hidden_field_name = 'submit_hidden';
        echo '
            <div class="wrap '.$this->data->prefix(false).'">

                <div style="float: right; padding: .6em; text-align: center;" id="dls-em-go-pro">
                    <a class="button-primary" href="https://www.dlssoftwarestudios.com/downloads/error-manager-pro-wordpress-plugin/" target="_blank">
                        Upgrade to <strong>Error Manager Pro</strong>
                    </a>
                   
               </div>

                <div id="icon-options-general" class="icon32"><br /></div>
                <h2>' . __('Error Manager', $this->data->prefix(false)) . ' ' . __('Settings', $this->data->prefix(false)). '</h2>



                <form name="form1" method="post" action="">
                    ';
                    
                    $num_saved = 0;
                    foreach ($options AS $key=>$o) {
                        if (!is_array($o)) {
                            if ($key !== 0) echo '</table>';
                            echo '<h3>'.$o.'</h3>';
                            echo '<table class="form-table">';
                            continue;
                        }
                        if ($key === 0) echo '<table class="form-table">';
                        $opt_label = (isset($o[0])) ? $o[0] : null;
                        $opt_name = (isset($o[1])) ? $o[1] : null;
                        $opt_type = (isset($o[2])) ? $o[2] : null;
                        $opt_note = (isset($o[3])) ? $o[3] : null;
                        $opt_options = (isset($o[4])) ? $o[4] : null;
                        $opt_val = get_option($opt_name);
                            
                        if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
                            $opt_val = (isset($_POST[$opt_name])) ? $_POST[$opt_name] : null;
                            update_option($opt_name, $opt_val);
                            $num_saved++;
                            if ($num_saved === 1) {
                                $frequency_changed = (get_option($this->data->prefix().'alert_php_log_change_frequency') <> $_POST[$this->data->prefix().'alert_php_log_change_frequency']) ? true : false;
                                if ($frequency_changed) wp_clear_scheduled_hook($this->data->prefix().'php_recent_check');
                                if (!empty($_POST[$this->data->prefix().'alert_php_log_change']) || $frequency_changed) {
                                    $alert_php_log_change = get_option($this->data->prefix().'alert_php_log_change');
                                    if (empty($alert_php_log_change) || $frequency_changed) {
                                        wp_schedule_event(time(), $_POST[$this->data->prefix().'alert_php_log_change_frequency'], $this->data->prefix().'php_recent_check');
                                    }
                                } else {
                                    wp_clear_scheduled_hook($this->data->prefix().'php_recent_check');
                                }
                                echo '<div class="updated"><p><strong>'.__('Settings saved.', $this->data->prefix(false)).'</strong></p></div>';
                            }
                        }
                        
                        echo '
                            <tr valign="top">
                                <th scope="row">'.__($opt_label.":", $this->data->prefix(false)).'</th>
                                <td>
                                    ';
                                    
                                    switch ($opt_type) {
                                        case 'text':
                                            echo '<input type="text" name="'.$opt_name.'" value="'.esc_attr($opt_val).'" size="20">';
                                            break;
                                        case 'password':
                                            echo '<input type="password" name="'.$opt_name.'" value="'.esc_attr($opt_val).'" size="20">';
                                            break;
                                        case 'textarea':
                                            echo '<textarea name="'.$opt_name.'" rows="5" style="width: 100%;">'.stripslashes(esc_html($opt_val)).'</textarea>';
                                            break;
                                        case 'checkbox':
                                            echo '<input type="checkbox" name="'.$opt_name.'" value="true"'.(($opt_val === 'true') ? ' checked="checked"' : '').'>';
                                            break;
                                        case 'radio':
                                            $i=0;
                                            foreach($opt_options AS $k=>$v) {
                                                $i++;
                                                echo '<input type="radio" name="'.$opt_name.'" value="'.esc_attr($k).'"'.(($k === $opt_val) ? ' checked="checked"' : '').'> <label>'.esc_attr($v).'</label><br />';
                                            }
                                            break;
                                        case 'dropdown':
                                            echo '<select name="'.$opt_name.'">';
                                            foreach($opt_options AS $k=>$v) {
                                                $selected = ($opt_val == $k) ? ' selected="selected"' : '';
                                                echo '<option value="'.$k.'"'.$selected.'>'.$v.'</option>';
                                            }
                                            echo '</select>';
                                            break;
                                    }
                                    
                                    echo '
                                    <span class="description">'.$opt_note.'</span>
                                </tr>
                            </tr>
                        ';

                    }
                    
                    echo '
                    </table>
                    <hr />
                    <p class="submit">
                        <input type="hidden" name="'.$hidden_field_name.'" value="Y">
                        <input type="submit" name="Submit" class="button-primary" value="'.esc_attr('Save Changes').'" />
                    </p>

                </form>
            </div><!-- .wrap -->
        ';
    }
    
    /**
     * Add settings link on plugin page
     * 
     * @param   mixed   links
     * @param   mixed   links
     */
    function settings_link($links)
    { 
        $settings_link = '<a href="options-general.php?page='.$this->admin_settings_slug.'">Settings</a>'; 
        array_unshift($links, $settings_link); 
        return $links;
    }
    
}