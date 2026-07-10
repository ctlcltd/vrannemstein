<?php
/**
 * Vrannemstein examples theme [Twenty Twenty-Five Child Theme]
 *
 * For testing purpose only.
 *
 * @package theme-examples
 * @version 0.1
 * @author Leonardo Laureti
 * @license GPL-2.0-or-later
 */

/**
 * Examples include
 */
if ( is_plugin_active( 'vrannemstein/vrannemstein.php' ) ) :
	require_once get_theme_file_path( 'examples.php' );
endif;


/**
 * Setup Vrannemstein example
 *
 * @param array $config
 */
function config_example( $config ) {
	// manipulate the options array

	return array(
		'verbosity' => 7, // flags (0 none, 1 log, 2 info, 4 error, 7 all)
		'debug' => false, // debug wasm-vips, default false
		'image_sizes' => $config['image_sizes'], // default wp_get_registered_image_subsizes()
		'noImageDecoding' => true, // disallow browser image decoding on wasm-vips load, default true
		'dynamicLibraries' => array('vips-heif.wasm'), // dynamic wasm-vips libraries [vips-jxl.wasm, vips-heif.wasm, vips-resvg.wasm]
		'checkMimeType' => true, // check for input file MIME type, default true
		// 'density' => 72, // metadata resolution in dpi, default 72 (false = use source resolution from exif data)
		// 'readXmp' => true, // allow vrannemstein_hooks.readXmp hook, default false
		// 'readExif' => true, // allow vrannemstein_hooks.readExif hook, default false
		// 'readIptc' => true, // allow vrannemstein_hooks.readIptc hook, default false
		'reduce' => array(
			'centre' => true, // sampling offset, default true, (deprecated parameter)
			'kernel' => 5 // resample kernel, default 5, VipsKernel(0 nearest, 1 linear, 2 cubic, 3 mitchell, 4 lanczos2, 5 lanczos3, 6 mks2013, 7 mks2021)
		),
		'smartcrop' => array(
			'interesting' => 1 // crop area, default 3, VipsInteresting(0 none, 1 centre, 2 entropy, 3 attention, 4 low, 5 high, 6 all)
		),
		'jpegsave' => array(
			'Q' => 86, // quality factor, defaults wp 82, php 75, gd 75, vips 75
			'interlace' => false, // progressive jpeg, default false
			'optimize_coding' => true, // optimize huffman tables, defaults gd false, vips false, sharp-js true
			'quant_table' => 3, // quantization table, default 0, defaults gd 0, mozjpeg 3, vips 0, enum (0 JPEG Annex K, 1 flat, 2 MSSIM tuned, 3 mozjpeg default, 4 PSNR-HVS-M tuned, 5, 6, 7, 8)
			'trellis_quant' => true, // trellis code quantization, default false
			'subsample_mode' => 0, // jpeg chroma subsampling, default 0, VipsForeignSubsample(0 auto, 1 YUV420, 2 YUV444)
			'keep' => 3 // keep metadata flags, VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
		),
		'pngsave' => array(
			'Q' => 100, // quantization value, default 100, min 0, max 100
			'compression' => 9, // compression ratio, default 6, min 1, max 10
			'dither' => 0, // dithering value, default 100, min 0, max 100
			'interlace' => false, // progressive png, default false
			'palette' => true, // PNG-8 256 colors palette, default false
			'bitdepth' => 8, // png image bit-depth, default 8, min 1, max 16, enum (1 mono, 2 mono+alpha, 4 PNG-8, 8 PNG-24, 16 PNG-48)
			'effort' => 7, // cpu effort on quantization, default 7, min 1, max 10
			'keep' => 3 // keep metadata flags, VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
		),
		'gifsave' => array( // gifsave is cgifsave in vips
			'dither' => 1, // dithering value, default 1, min 0, max 1
			'interlace' => false, // progressive gif, default false
			'bitdepth' => 8, // gif palette bit-depth, default 8, min 1, max 8
			'effort' => 7, // cpu effort on quantization, default 7, min 1, max 10
			'keep' => 3 // keep metadata flags, VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
		),
		'webpsave' => array(
			'Q' => 88, // quality factor, defaults wp 86, php 80, gd 75, vips 75
			'smart_deblock' => true, // webp smart deblocking filter, default false
			'smart_subsample' => true, // webp smart chroma subsample, default false
			'exact' => true, // preserve color alpha, default false
			'effort' => 4, // cpu effort on file size, default 4, min 0, max 6
			'preset' => 0, // lossy presets, default 0, VipsForeignWebpPreset(0 default, 1 picture, 2 photo, 3 drawing, 4 icon, 5 text)
			'keep' => 3 // keep metadata flags, VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
		),
		'avifsave' => array( // avifsave is heifsave in vips
			'Q' => 55, // quality factor, defaults wp 50, php 52, vips 50
			'bitdepth' => 10, // av1 image bit-depth, default 12, min 8, max 12
			'effort' => 4, // av1 cpu effort value, default 4, min 0, max 9
			'subsample_mode' => 0, // av1 chroma subsampling, default 0, VipsForeignSubsample(0 auto, 1 YUV420, 2 YUV444)
			'keep' => 3 // keep metadata flags, VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
		)
	);
}

add_filter( 'vrannemstein_config', 'config_example' );


/**
 * Fallback image compression quality
 *
 * @see WP_Image_Editor::set_quality()
 *
 * @param int $quality
 * @param string $mime_type
 * @return int
 */
function image_editor_quality_fallback( $quality, $mime_type ) {
	switch ( $mime_type ) {
		case 'image/jpeg': return 86;
		case 'image/webp': return 88;
		case 'image/avif': return 55;
		default: return $quality;
	}
}

add_filter( 'wp_editor_set_quality', 'image_editor_quality_fallback', 10, 2 );

