<?php
/*
  Plugin Name: Postfinance paiement
  Plugin URI: http://compassion.ch/
  Description:
  Author: Olivier Requet / J. Kläy
  Version: 0.2
  Author URI: http://compassion.ch/
 */
defined('ABSPATH') || die();

global $donation_db_version;
$donation_db_version = '1.25';

function donation_db_install() {

    global $wpdb;
    global $donation_db_version;

    $table_name = $wpdb->prefix . "donation_to_odoo";

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
             id int(1) NOT NULL AUTO_INCREMENT,
             time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
             ip_address tinytext NOT NULL,
             email tinytext NOT NULL,
             orderid tinytext NULL,
             campaign_slug tinytext NULL,
             last_name tinytext NOT NULL,
             first_name tinytext NOT NULL,
             street tinytext NOT NULL,
             zipcode tinytext NOT NULL,
             city tinytext NOT NULL,
             country tinytext NOT NULL,
             language varchar(5) NOT NULL,
             amount decimal(10,2) NULL,
             currency varchar(5) NOT NULL,
             fund tinytext NULL,
             child_id tinytext NULL,
             partner_ref tinytext NULL,
             transaction_id tinytext NOT NULL,
             session_id tinytext NOT NULL,
             pf_pm tinytext NULL,
             pf_payid varchar(16) NULL,
             pf_brand tinytext NULL,
             pf_raw text NULL,
             odoo_status tinytext NOT NULL,
             odoo_complete_time datetime NULL,
             odoo_invoice_id int(1) NULL,
             PRIMARY KEY (id)
            ) $charset_collate; ";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta($sql);

    add_option('donation_db_version', $donation_db_version);

    if (version_compare($donation_db_version, '1.24') < 0) {
        $sql = "CREATE TABLE $table_name (
                 id int(1) NOT NULL AUTO_INCREMENT,
                 time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                 ip_address tinytext NOT NULL,
                 email tinytext NOT NULL,
                 orderid tinytext NULL,
                 campaign_slug tinytext NULL,
                 last_name tinytext NOT NULL,
                 first_name tinytext NOT NULL,
                 street tinytext NOT NULL,
                 zipcode tinytext NOT NULL,
                 city tinytext NOT NULL,
                 country tinytext NOT NULL,
                 language varchar(5) NOT NULL,
                 amount decimal(10,2) NULL,
                 currency varchar(5) NOT NULL,
                 fund tinytext NULL,
                 child_id tinytext NULL,
                 partner_ref tinytext NULL,
                 transaction_id tinytext NOT NULL,
                 session_id tinytext NOT NULL,
                 pf_pm tinytext NULL,
                 pf_payid varchar(16) NULL,
                 pf_brand tinytext NULL,
                 pf_raw text NULL,
                 odoo_status tinytext NOT NULL,
                 odoo_complete_time datetime NULL,
                 odoo_invoice_id int(1) NULL,
                 PRIMARY KEY (id)
                ) $charset_collate; ";
        dbDelta($sql);
        error_log('update to version 1.25');

        update_option('donation_db_version', '1.25');
    }
}

add_action('plugins_loaded', 'donate_load_textdomain');

function donate_load_textdomain() {
    load_plugin_textdomain('donation-form', false, dirname(plugin_basename(__FILE__)) . '/lang/');
}

function guidv4()
{
    if (function_exists('com_create_guid') === true)
        return trim(com_create_guid(), '{}');

    $data = openssl_random_pseudo_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

register_activation_hook(__FILE__, 'donation_db_install');

class donationForm {

    public static $alreadyEnqueued = false;

    private $step;


    public function __construct() {
        add_action('init', [$this, '__init']);
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueueScripts' ) );

    }

    public function enqueueScripts() {
        if ( ! self::$alreadyEnqueued ) {
            wp_enqueue_script( 'wp-color-picker' );
            wp_enqueue_style( 'wp-color-picker' );
        }
        self::$alreadyEnqueued = true;
    }

    public function __init() {

        if (!isset($_SESSION)) {
            session_start();
        }
        @$_SESSION['count_runs']=0;
//         error_log($_SESSION['campaign_slug']);

        add_shortcode('donation-form', array($this, 'shortcode'));


        // load styles
        wp_enqueue_style('donation-form', plugin_dir_url(__FILE__) . '/assets/stylesheets/screen.css', array(), null);

        //load scripts
//        wp_enqueue_script('validation-js', plugin_dir_url(__FILE__) . 'bower_components/jquery-validation/dist/jquery.validate.min.js', array('jquery'));
        wp_enqueue_script('validation-js', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.0/jquery.validate.min.js', array('jquery'));
    }



    /**
     * Process form data
     *
     * Save data to session, destroy session or send data
     *
     * @param $data
     */
    private function process_form_data($data) {
//        $session_data = $_SESSION['donation-form'];

        switch ($this->step) {
            case 2;
                $this->send_data($data);
//                session_destroy();
                break;
        }
    }

    /**
     * Generate shortcode
     *
     * Process form data and load next template
     *
     * @return string
     */
    public function shortcode($atts, $content) {

        $this->step = (isset($_GET['step'])) ? intval($_GET['step']) : 1;
        $atts = shortcode_atts(
                array(
            'form' => '',
                ), $atts);
        /**
         * process form data
         */
        $this->process_form_data($_POST);

        /**
         * load template
         */
        ob_start();
//        $session_data = $_SESSION['donation-form'];

//      include("templates/frontend/header.php");
        if ('donation' == $atts['form']) {
            include("templates/frontend/step-$this->step.php");
        } else if ('csp' == $atts['form']){
             include("templates/csp/step-$this->step.php");
        }
        else {
             include("templates/cadeau/step-$this->step.php");
        }

        $content = ob_get_contents();
        ob_end_clean();

        /**
         * return shortcode
         */
        return $content;
    }

    private function cleanfordb($value) {

        return trim(filter_var($value));

    }

    /**
     * Send form data
     *
     * When user completed form do whatever you want with the data
     *
     * @param $data
     */
    private function send_data($data) {
//        print_r($data);

        global $wpdb;

        $session_data = $data;
        $my_current_lang = apply_filters('wpml_current_language', NULL);
        if ($my_current_lang == 'fr') {
            $lang = 'fr_FR';
        } elseif ($my_current_lang == 'de') {
            $lang = 'de_DE';
        } elseif ($my_current_lang == 'it') {
            $lang = 'it_IT';
        } else {
            $lang = 'de_DE';
        }

        if($_SESSION['count_runs']==0) {
            $transaction = guidv4();
            $_SESSION['transaction'] = $transaction;
        }

        $from_csp = substr($session_data['fonds'], 0, strlen('csp_mensuel')) == 'csp_mensuel';
        $final_amount = ($from_csp ? floatval(substr($session_data['fonds'], -2)) : $session_data['wert']);

        // Form data to send to postfinance (ogone)
        $form = array(
            'PSPID' => 'compassion_yp',
            'ORDERID' => trim($session_data['refenfant'] . '_' . $session_data['fonds']),
            'AMOUNT' => $final_amount * 100,
            'CURRENCY' => 'CHF',
            'LANGUAGE' => $lang,
            'CN' => $session_data['first_name'] . ' ' . $session_data['last_name'],
            'EMAIL' => $session_data['email'],
            'COMPLUS' => $_SESSION['transaction'],
            'PARAMPLUS' => 'campaign_slug='.$_SESSION['campaign_slug'],
'ACCEPTURL' => 'https://' . $_SERVER['HTTP_HOST'] . '/' . $my_current_lang .'/confirmation-don',
           'DECLINEURL' => 'https://' . $_SERVER['HTTP_HOST'] . '/' .$my_current_lang.'/annulation-don',
           'EXCEPTIONURL' => 'https://' . $_SERVER['HTTP_HOST'] . '/' .$my_current_lang.'/annulation-don',
           'CANCELURL' => 'https://' . $_SERVER['HTTP_HOST'] . '/' .$my_current_lang.'/annulation-don',        );

        if($_SESSION['count_runs']==0) {
            $_SESSION['count_runs']++;

            $wpdb->insert(
                    $wpdb->prefix . "donation_to_odoo",
                    array(
                        'time' => date('Y-m-d H:i:s'),
                        'ip_address' => $_SERVER['REMOTE_ADDR'],
                        'email' => $this->cleanfordb($data['email']),
                        'orderid' => $this->cleanfordb($session_data['refenfant'] . '_' . $session_data['fonds']),
                        'utm_source' => $this->cleanfordb($_SESSION['utm_source']),
                        'utm_medium' => $this->cleanfordb($_SESSION['utm_medium']),
                        'utm_campaign' => $this->cleanfordb($_SESSION['utm_campaign']),
                        'first_name' => $this->cleanfordb($data['first_name']),
                        'last_name' => $this->cleanfordb($data['last_name']),
                        'street' => $this->cleanfordb($data['street']),
                        'zipcode' => $this->cleanfordb($data['zipcode']),
                        'city' => $this->cleanfordb($data['city']),
                        'country' => $this->cleanfordb($data['country']),
                        'language' => $lang,
                        'amount' => $final_amount,
                        'currency' => 'CHF',
                        'fund' => $this->cleanfordb($session_data['fonds']),
                        'child_id' => $this->cleanfordb($session_data['refenfant']),
                        'partner_ref' => $this->cleanfordb($session_data['partner_ref']),
                        'transaction_id' => $transaction,
                        'session_id' => session_id(),
                        'odoo_status' => 'submit_to_pf',
                    )
                );
        }

        //generate hash string
        $arrayToHash = array();
        foreach ($form as $key => $value) {
            if ($value != '') {
                $arrayToHash[] = strtoupper($key) . '=' . $value . 'stc0mp45510nypg3in2';
            }
        }
        asort($arrayToHash);
        $stringToHash = implode('', $arrayToHash);
        $hashedString = sha1($stringToHash);

        ?>
        <html>
            <head><title>Redirecting to Postfinance...</title></head>
            <body>
                <form action="<?=POSTFINANCE_URL?>/orderstandard_utf8.asp" method="post" name="pf_for_contact_form">
                    <input type="hidden" name="PSPID" value="<?php echo $form['PSPID']; ?>">
                    <input type="hidden" name="ORDERID" value="<?php echo $form['ORDERID']; ?>">
                    <input type="hidden" name="PARAMPLUS" value="<?php echo $form['PARAMPLUS']; ?>">
                    <input type="hidden" name="AMOUNT" value="<?php echo $form['AMOUNT']; ?>">
                    <input type="hidden" name="CURRENCY" value="<?php echo $form['CURRENCY']; ?>">
                    <input type="hidden" name="LANGUAGE" value="<?php echo $form['LANGUAGE']; ?>">
                    <input type="hidden" name="CN" value="<?php echo $form['CN']; ?>">
                    <input type="hidden" name="EMAIL" value="<?php echo $form['EMAIL']; ?>">
                    <input type="hidden" name="COMPLUS" value="<?php echo $form['COMPLUS']; ?>">
                    <input type="hidden" name="ACCEPTURL" value="<?php echo $form['ACCEPTURL']; ?>">
                    <input type="hidden" name="DECLINEURL" value="<?php echo $form['DECLINEURL']; ?>">
                    <input type="hidden" name="EXCEPTIONURL" value="<?php echo $form['EXCEPTIONURL']; ?>">
                    <input type="hidden" name="CANCELURL" value="<?php echo $form['CANCELURL']; ?>">
                    <input type="hidden" name="SHASIGN" value="<?php echo $hashedString; ?>">
                </form>
                <script type="text/javascript">
                    document.pf_for_contact_form.submit();
                </script>
            </body>
        </html>
        <?php
    }

}

new donationForm();
