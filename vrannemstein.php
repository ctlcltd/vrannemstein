<?php
/**
 * @package vrannemstein
 * @author Leonardo Laureti
 * @license GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: Vrannemstein
 * Plugin URI: https://github.com/ctlcltd/vrannemstein
 * Description: Image thumbnails using wasm-vips via the client side.
 * Version: 0.0.1
 * Author: Leonardo Laureti
 * Author URI: https://github.com/ctlcltd
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
*/

defined( 'ABSPATH' ) || die();

class Vrannemstein {
	public $version = '0.0.1';
	public $wasm_vips_version = '0.0.16'; // reflects package.json version

	public $queue_priority = 9999; // higher scripts enqueue priority

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api' ) );

		// conditionally loads vrannemstein, wasm-vips scripts
		add_action( 'load-post.php', array( $this, 'backend' ) );
		add_action( 'load-post-new.php', array( $this, 'backend' ) );
		add_action( 'load-upload.php', array( $this, 'backend' ) );

		/**
		 * Filter to allow bulk actions generate thumbnails
		 *
		 * @param bool $allow default true
		 */
		if ( apply_filters( 'vrannemstein_bulk_actions', '__return_true' ) ) {
			add_action( 'load-upload.php', array( $this, 'bulk_actions' ) );
		}
	}

	static function init() {
		static $init = 0 ?: new Vrannemstein();
	}

	public function thumbnailer_config() {
		/**
		 * Filters thumbnailer configuration
		 *
		 * Useful to extend thumbnailer options from theme or plugin.
		 *
		 * @see wp_get_registered_image_subsizes()
		 *
		 * @param array $config Javascript options
		 */
		return apply_filters( 'vrannemstein_config', array(
			'verbosity' => 7, // flags (0 none, 1 log, 2 info, 4 error, 7 all)
			'debug' => false, // debug wasm-vips
			'dynamicLibraries' => array(), // dynamic wasm-vips libraries
			'noImageDecoding' => false, // browser decoding image wasm-vips default true
			'image_sizes' => wp_get_registered_image_subsizes(),
			'readXmp' => false,
			'readExif' => false,
			'readIptc' => false,
			'reduce' => array(
				'kernel' => 5 // resample kernel default 5, VipsKernel(0 nearest, 1 linear, 2 cubic, 3 mitchell, 4 lanczos2, 5 lanczos3, 6 mks2013, 7 mks2021)
			),
			'smartcrop' => array(
				'interesting' => 1 // default 3, VipsInteresting(0 none, 1 centre, 2 entropy, 3 attention, 4 low, 5 high, 6 all)
			),
			'jpegsave' => array(
				'Q' => 82, // quality defaults wp 82, php 75, gd 75, vips 75
				// 'interlace' => false, // progressive jpeg default false
				'optimize_coding' => false, // defaults gd false, vips false, sharp true
				'quant_table' => 0, // defaults gd 0, mozjpeg 3, vips 0
				// 'trellis_quant' => false, // default false
				// 'overshoot-deringing' => false, // default false
				// 'optimize_scans' => false, // default false, note: forces to progressive
				// 'subsample_mode' => 0, // jpeg chroma subsample default 0, VipsForeignSubsample(0 auto, 1 on, 2 off) //todo
				// 'background' => '#ffffff',
				// 'page_height' => 0, // min 0, max 100000000
				'keep' => 0 // keep metadata flags VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
			),
			'gifsave' => array(
				'dither' => 0, // default 1, min 0, max 1
				// 'bitdepth' => 8, // default 8, min 1, max 8
				// 'interlace' => false, // progressive gif default false	
				// 'reuse' => true, // reuse palette from input default false
				// 'interpalette_maxerror' => 3, // max inter-palette for reuse palette, default 3, max 256
				// 'effort' => 7, // cpu effort quantization default 7, min 1, max 10
				// 'keep_duplicate_frames' => false, // default true
				// 'background' => '#ffffff',
				// 'page_height' => 0, // min 0, max 100000000
				'keep' => 0 // keep metadata flags VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
			),
			'pngsave' => array(
				'Q' => 50, // quantization default 100, min 0, max 100
				// 'compression' => 6, // default 6, min 1, max 10
				'dither' => 0, // default 100, min 0, max 100
				// 'bitdepth' => 8, // default 8, min 1, max 8
				// 'interlace' => false,
				// 'palette' => true,
				// 'effort' => 7, // cpu effort quantization default 7, min 1, max 10
				// 'filter' => 8, // libpng filter flags default 8, VipsForeignPngFilter(8 none, 16 sub, 32 up, 64 avg, 128 paeth, 248 all)
				// 'background' => '#ffffff',
				// 'page_height' => 0, // min 0, max 100000000
				'keep' => 0 // keep metadata flags VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
			),
			'webpsave' => array(
				'Q' => 86, // defaults wp 86, php 80, gd 75, vips 75
				// 'lossless' => false, // default false
				// 'smart_deblock' => false, // default false
				// 'smart_subsample' =>  false, // default false
				// 'effort' => 4, // cpu effort on file size, default 4, min 0, max 6
				// 'passes' => 1, // default 1, min 1, max 10
				// 'alpha_q' => 100, // alpha quality in lossy, default 100, min 0, max 100
				// 'exact' => false, // preserve color alpha default false
				// 'kmin' => 2147483646, // min frames between key frames, default 2147483646, min 0, max 2147483647
				// 'kmax' => 2147483647, // max frames between key frames, default 2147483647, min 0, max 2147483647
				// 'min-size' => false, // optimize on min file size, default false
				// 'target_size' => 0, // desired target size in bytes, default 0, min 0, max 2147483647
				// 'mixed' => false, // allow mixed encoding, default: false
				// 'preset' => 0, // lossy presets default 0, ForeignWebpPreset(0 default, 1 picture, 2 photo, 3 drawing, 4 icon, 5 text)
				// 'near_lossless' => false, // preprocessing lossless using Q value, default false
				// 'background' => '#ffffff',
				// 'page_height' => 0, // min 0, max 100000000
				'keep' => 0 // keep metadata flags VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
			),
			'avifsave' => array( // avifsave is heifsave in vips
				'Q' => 52, // quality defaults wp 52, php 52, vips 50
				'bitdepth' => 10, // default 12, min 8, max 12
   				// 'lossless' => false, // default false
				// 'effort' => 4, // cpu effort default 4, min 0, max 9 //todo php gd imageavif parameter speed 0-10
				// 'subsample_mode': 0, // av1 chroma subsample default 0, VipsForeignSubsample(0 auto, 1 on, 2 off) //todo
				// 'background' => '#ffffff',
				// 'page_height' => 0, // min 0, max 100000000
				'keep' => 0 // keep metadata flags VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
			)
		) );
	}

	public function backend() {
		// wasm-vips specific, SharedArrayBuffer require-corp same-origin cross-origin policies
		$this->crossorigin_policies();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), $this->queue_priority );
		add_action( 'admin_print_footer_scripts', array( $this, 'thumbnailer_script' ), $this->queue_priority );
		add_action( 'admin_print_footer_scripts', array( $this, 'api_middleware_script' ), $this->queue_priority );
	}

	public function bulk_actions() {
		if ( current_user_can( 'upload_files' ) && current_user_can( 'edit_posts' ) ) {
			add_filter( 'bulk_actions-upload', array( $this, 'bulk_actions_generate_thumbnails' ) );
			add_action( 'admin_print_footer_scripts', array( $this, 'bulk_actions_script' ), $this->queue_priority );
		}
	}

	/**
	 * Filters bulk actions in upload list table (media view)
	 *
	 * @see WP_List_Table
	 *
	 * @param array $actions
	 * @return array $actions
	 */
	public function bulk_actions_generate_thumbnails( $actions ) {
		$actions = array( 'bulk-thumbnails' => __( 'Update File' ), ...$actions );
		return $actions;
	}

	public function thumbnailer_script() {
		include_once __DIR__ . '/inc/thumbnailer-script.php';
	}

	public function api_middleware_script() {
		include_once __DIR__ . '/inc/api-middleware-script.php';
	}

	public function bulk_actions_script() {
		include_once __DIR__ . '/inc/bulk-actions-script.php';
	}

	protected function crossorigin_policies() {
		header( 'Cross-Origin-Embedder-Policy: require-corp' );
		header( 'Cross-Origin-Opener-Policy: same-origin' );
	}

	public function rest_api() {
		require_once __DIR__ . '/inc/class-vrannemstein-rest-controller.php';

		$controller = new Vrannemstein_REST_Controller();
		$controller->register_routes();
	}

	public function enqueue_scripts() {
		wp_register_script( 'vrannemstein', false, false, false, true );
		wp_add_inline_script( 'vrannemstein', 'var vrannemstein_config = ' . wp_json_encode( $this->thumbnailer_config() ), 'before' );
		wp_enqueue_script( 'vrannemstein' );

		wp_enqueue_script( 'wasm-vips', plugins_url( 'node_modules/wasm-vips/lib/vips.js', __FILE__ ), false, $this->wasm_vips_version, true );
	}
}

add_action( 'init', '\Vrannemstein::init' );


//TODO filterable ( $new_sizes, $image_meta, $attachment_id )
add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array', 9999, 3 );


