<?php
/*
Plugin Name: Formidable User Tracking
Description: Track the steps a user takes before submitting a form
Version: 1.0 AJK mod
Plugin URI: http://formidablepro.com/
Author URI: http://strategy11.com
Author: Strategy11
*/

class Frm_User_Tracking {
	// keep the page history below 100
	protected static $page_max = 100;

	protected static $referrer_info = "\r\n";

	public function __construct() {
		// add_action( 'template_redirect', 'Frm_User_Tracking::compile_referer_session', 1 );
		add_action( 'template_include', 'Frm_User_Tracking::compile_referer_session', 1,1 );
		add_action( 'frm_after_create_entry', 'Frm_User_Tracking::insert_tracking_into_entry', 15 );
		if ( is_admin() ) {
			add_action( 'frm_entry_shared_sidebar', 'Frm_User_Tracking::styling' );
		}
	}

	public static function styling() {
		?><style>.misc-pub-section.frm_force_wrap{font-size:12px;word-break: break-all;line-height:1.8;}</style><?php
	}


	public static function compile_referer_session( $template=null ) {
		// if ( defined( 'WP_IMPORTING' ) || defined( 'DOING_CRON' ) ) return;

		if ( ! isset( $_SESSION ) ) session_start();

		if ( empty( $_SESSION['frm_http_referer'] ) ) self::add_referer_to_session();

		self::add_current_page_to_session();

		return $template;
	}

	private static function add_referer_to_session() {

		if ( ! isset( $_SERVER['HTTP_REFERER'] ) ) {
			$_SESSION['frm_http_referer'] = 'Direct';
		} else {// if ( false === strpos( $_SERVER['HTTP_REFERER'], FrmAppHelper::site_url() ) ) {
			$_SESSION['frm_http_referer'] = FrmAppHelper::get_server_value( 'HTTP_REFERER' );
		}
	}

	private static function add_current_page_to_session() {
		$current_url = FrmAppHelper::get_server_value( 'REQUEST_URI' );
		error_log($current_url);
		if ( strpos( $current_url, 'admin-ajax.php' ) ) {
			error_log('!!! Admin Ajax in frm user tracking! ' . $current_url);
			return;
		}

		if ( empty( $_SESSION['frm_http_pages'] ) || ! is_array( $_SESSION['frm_http_pages'] ) ) {
			$_SESSION['frm_http_pages'] = [];
		}
		// error_log(var_export($_SESSION['frm_http_pages'],1));
		if ( $current_url == end( $_SESSION['frm_http_pages'] ) ) return;

		if ( strpos( $current_url, '.' ) ) {
			$ext = substr( strrchr( parse_url($current_url, PHP_URL_PATH), '.' ), 1 );
			if ( in_array( $ext, ['css','js','ico','jpg'] ) ) {
				error_log("frm tracking would have added a '{$ext}' page visit: " . var_export( $_SERVER, 1 ) );
				return;
			}
		}

		$_SESSION['frm_http_pages'][] = $current_url;
		// error_log(var_export($_SESSION['frm_http_pages'],1));

		if ( count( $_SESSION['frm_http_pages'] ) > self::$page_max ) {
			$_SESSION['frm_http_pages'] = array_slice( $_SESSION['frm_http_pages'], ( 0 - self::$page_max ) );
		}
	}

	public static function insert_tracking_into_entry( $entry_id ) {
		if ( ! isset( $_SESSION ) ) session_start();

		self::add_referer_to_string();
		self::add_pages_to_string();
		$entry = FrmEntry::getOne( $entry_id );
		$entry_description = maybe_unserialize( $entry->description );
		$entry_description['referrer'] = strip_tags( self::$referrer_info );

		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'frm_items', ['description' => serialize( $entry_description ) ],[ 'id' => $entry_id ] );
	}

	private static function add_referer_to_string() {

		if ( ! empty( $_SESSION['frm_http_referer'] ) ) {
			if ( $_SESSION['frm_http_referer'] === 'Direct' ) return;// who cares anyway
			if ( is_array( $_SESSION['frm_http_referer'] ) ) $_SESSION['frm_http_referer'] = current($_SESSION['frm_http_referer']);
			self::$referrer_info .= "Referer: " . $_SESSION['frm_http_referer'] . "\r\n\r\n";
			$keywords_used = self::get_referer_query( $_SESSION['frm_http_referer'] );
			if ( $keywords_used ) {
				self::$referrer_info .= "Keyword: " . $keywords_used . "\r\n\r\n";
			}
		}
		// else {
		// 	self::$referrer_info = FrmAppHelper::get_server_value( 'HTTP_REFERER' );
		// 	error_log("formidable tracking adding http referrer this fallback way: " . var_export( self::$referrer_info, 1 ) );
		// }
	}

	private static function add_pages_to_string() {
		if ( empty( $_SESSION['frm_http_pages'] ) || ! is_array( $_SESSION['frm_http_pages'] ) ) return;

		self::$referrer_info .= "Pages visited:" . "\r\n";
		foreach ( $_SESSION['frm_http_pages'] as $page ) {
			self::$referrer_info .= "~" . trim( $page, '/' ) . "\r\n";
		}
		self::$referrer_info .= "\r\n";
	}

	private static function get_referer_query( $query ) {
		$keyword = false;
		$query = parse_url($query, PHP_URL_QUERY);
		if ( $query ) {
			parse_str( $query, $params );
			$keyword = $params['q'] ?? $params['p'] ?? false;// yahoo uses p... do ppl use yahoo?
		}
		return $keyword;
	}
}

if ( PHP_SESSION_DISABLED !== session_status() ) {
	new Frm_User_Tracking();
}