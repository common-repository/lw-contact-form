<?php
/*
Plugin Name: LW Contact Form
Plugin URI: http://www.localweb.it/
Description: Addon per il modulo di contatto.
Author: LocalWeb S.R.L
Version: 1.1.3
Author URI: http://www.localweb.it/chi-siamo/
 */
/**
 * @package local_web
 * @version 1.1.3
 */
defined('ABSPATH') or die('No script kiddies please!');

register_activation_hook(__FILE__, 'lwcf_install_db');
register_activation_hook(__FILE__, 'lwcf_update_db_check');
register_activation_hook(__FILE__, 'lwcf_sent_activation');
register_deactivation_hook(__FILE__, 'lwcf_sent_deactivation');

global $lwcf_db_version;
$lwcf_db_version = '1.1.3';

function lwcf_install_db() {
    global $wpdb;
    global $lwcf_db_version;
    $table_name = $wpdb->prefix . 'inserimenti_cf';
    $charset_collate = $wpdb->get_charset_collate();
    if ($wpdb->get_var("show tables like '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			soggetto text NULL,
			messaggio text NULL,
			nome varchar(150) NULL,
			cognome varchar(150) NULL,
			time varchar(20) NULL,
			email varchar(100) NULL,
			telefono varchar(100) NULL,
			tipo_Contratto varchar(10) NULL,
			id_Contratto varchar(10) NULL,
			submited_page varchar(500) NULL,
			inviato varchar(2) DEFAULT '' NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        add_option('lwcf_db_version', $lwcf_db_version);
    }
}

function lwcf_update_db() {
    global $wpdb;
    global $lwcf_db_version;
    $installed_ver = get_option("lwcf_db_version");
    if ($installed_ver != $lwcf_db_version) {
        $table_name = $wpdb->prefix . 'inserimenti_cf';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			soggetto text NULL,
			messaggio text NULL,
			nome varchar(150) NULL,
			cognome varchar(150) NULL,
			time varchar(20) NULL,
			email varchar(100) NULL,
			telefono varchar(100) NULL,
			tipo_Contratto varchar(10) NULL,
			id_Contratto varchar(10) NULL,
			submited_page varchar(500) NULL,
			inviato varchar(2) DEFAULT '' NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option("lwcf_db_version", $lwcf_db_version);
    }
}

function lwcf_update_db_check() {
    global $wpdb;
    global $lwcf_db_version;
    if (get_option('lwcf_db_version') != $lwcf_db_version) {
        lwcf_update_db();
    }
}
// add_action( 'plugins_loaded', 'lwcf_update_db_check' );
add_action('wpcf7_before_send_mail', 'lwcf_to_db');
function lwcf_to_db($WPCF7_ContactForm) {

    $url_path = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    $domain = htmlspecialchars($url_path, ENT_QUOTES, 'UTF-8');
    $domain = trim($domain, '/');
    if (!preg_match('#^http(s)?://#', $domain)) {
        $domain = 'http://' . $domain;
    }
    $url_parts = parse_url($domain);
    $submited_page = preg_replace('/^www\./', '', $url_parts['host']);

    $submission = WPCF7_Submission::get_instance();
    $posted_data = &$submission->get_posted_data();

    if (isset($posted_data['nome'])) {
        $mapped_field = array();
        $mapped_field['nome'] = $posted_data['nome'];
        $mapped_field['cognome'] = $posted_data['cognome'];
        $mapped_field['email'] = $posted_data['email'];
        $mapped_field['telefono'] = $posted_data['telefono'];
        $mapped_field['soggetto'] = $posted_data['oggetto'];
        $mapped_field['messaggio'] = $posted_data['messaggio'];
        $mapped_field['tipo_Contratto'] = $posted_data['tipo-contratto'];
        $mapped_field['id_Contratto'] = $posted_data['id-contratto'];
        $mapped_field['submited_page'] = $submited_page;

        $json_mapped_fields = json_encode($mapped_field);

        $url = "https://localwebapi.ids.al/contactFormWeb";
        $args = array(
            'body' => $json_mapped_fields,
            'timeout' => '5',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(),
            'cookies' => array(),
        );

        $send = wp_remote_post($url, $args);
        $ret_body = wp_remote_retrieve_body($send);
        $data = json_decode($ret_body);
        if (is_wp_error($ret_body)) {
            $inviato = "No";
            // echo "Something went wrong: $inviato";
        } elseif ($data->response == "OK") {
            $inviato = "Si";
        } else {
            $inviato = "No";
        }

        global $wpdb;
        $nome = $mapped_field['nome'];
        $cognome = $mapped_field['cognome'];
        $email = $mapped_field['email'];
        $telefono = $mapped_field['telefono'];
        $soggetto = $mapped_field['soggetto'];
        $messaggio = $mapped_field['messaggio'];
        $tipo_Contratto = $mapped_field['tipo_Contratto'];
        $id_Contratto = $mapped_field['id_Contratto'];
        $time = time();

        $table_name = $wpdb->prefix . 'inserimenti_cf';
        $wpdb->insert(
            $table_name,
            array(
                'time' => $time,
                'nome' => $nome,
                'cognome' => $cognome,
                'email' => $email,
                'telefono' => $telefono,
                'soggetto' => $soggetto,
                'messaggio' => $messaggio,
                'tipo_Contratto' => $tipo_Contratto,
                'id_Contratto' => $id_Contratto,
                'submited_page' => $submited_page,
                'inviato' => $inviato,
            )
        );
    }
}

function lwcf_add_every_five_minutes($schedules) {
    $schedules['lwcf_every_five_minutes'] = array(
        'interval' => 300,
        'display' => __('Every 5 Minutes', 'localweb'),
    );
    return $schedules;
}
add_filter('cron_schedules', 'lwcf_add_every_five_minutes');

function lwcf_sent_activation() {
    if (!wp_next_scheduled('lwcf_check_sent_data')) {
        wp_schedule_event(time(), 'lwcf_every_five_minutes', 'lwcf_check_sent_data');
    }
}
add_action('lwcf_check_sent_data', 'lwcf_every_5_minutes');

function lwcf_sent_deactivation() {
    wp_clear_scheduled_hook('lwcf_check_sent_data');
}

function lwcf_every_5_minutes() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'inserimenti_cf';
    $select_nn_inviato = $wpdb->get_row("SELECT * FROM " . $table_name . " WHERE inviato !='Si'");
    if ($select_nn_inviato !== null) {
        $re_invia = array();
        $re_invia['nome'] = $select_nn_inviato->nome;
        $re_invia['cognome'] = $select_nn_inviato->cognome;
        $re_invia['email'] = $select_nn_inviato->email;
        $re_invia['telefono'] = $select_nn_inviato->telefono;
        $re_invia['soggetto'] = $select_nn_inviato->soggetto;
        $re_invia['messaggio'] = $select_nn_inviato->messaggio;
        $re_invia['tipo_Contratto'] = $select_nn_inviato->tipo_Contratto;
        $re_invia['id_Contratto'] = $select_nn_inviato->id_Contratto;
        $re_invia['submited_page'] = $select_nn_inviato->submited_page;

        $json_re_invia = json_encode($re_invia);
        $url = "https://localwebapi.ids.al/contactFormWeb";
        $args = array(
            'body' => $json_re_invia,
            'timeout' => '5',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(),
            'cookies' => array(),
        );
        $send = wp_remote_post($url, $args);
        $ret_body = wp_remote_retrieve_body($send);
        $data = json_decode($ret_body);

        if ($data->response == "OK") {
            $inviato = "Si";
            $id = $select_nn_inviato->id;
            $wpdb->update(
                $table_name,
                array(
                    'inviato' => $inviato,
                ), array('id' => $id)
            );
        }
    }
}
?>