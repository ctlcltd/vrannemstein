<?php
/**
 * Examples from Vrannemstein examples theme
 *
 * @package theme-examples
 * @version 0.1
 * @author Leonardo Laureti
 * @license GPL-2.0-or-laters
 */


/**
 * Example definitions
 *
 * See the example comment description for file requirements.
 * 
 * 
 * Usage example:
 * 
 * Resample by format (".webp" or ".avif" file requirement), place in theme "functions.php" or "wp-config.php" file:
 * 
 *   define( 'EXAMPLES__hooks_resample_by_format', true );
 * 
 */
defined( 'EXAMPLES__deactivate_bulk_resizer' ) || define( 'EXAMPLES__deactivate_bulk_resizer', false );
defined( 'EXAMPLES__hooks_replace_image_path' ) || define( 'EXAMPLES__hooks_replace_image_path', false );
defined( 'EXAMPLES__hooks_quality_per_size' ) || define( 'EXAMPLES__hooks_quality_per_size', true );
defined( 'EXAMPLES__hooks_resample_by_format' ) || define( 'EXAMPLES__hooks_resample_by_format', true );
defined( 'EXAMPLES__hooks_exclude_image_resize' ) || define( 'EXAMPLES__hooks_exclude_image_resize', false );
defined( 'EXAMPLES__hooks_preprocessor_grayscale_image' ) || define( 'EXAMPLES__hooks_preprocessor_grayscale_image', false );


/**
 * Overrides default image sizes on theme activation
 *
 * @see wp_get_registered_image_subsizes()
 */
function examples_image_sizes() {
	$registered_sizes = wp_get_registered_image_subsizes();

	foreach ( $registered_sizes as $size_name => $size_data ) {
		$hw = 0;
		switch ( $size_name ) {
			case 'thumbnail':
				$hw = ( $size_data['width'] == 150 && $size_data['width'] === $size_data['height'] ) ? 200 : 0;
			break;
			case 'medium':
				$hw = ( $size_data['width'] == 300 && $size_data['width'] === $size_data['height'] ) ? 850 : 0;
			break;
			case 'medium_large':
				$hw = ( $size_data['width'] == 768 && $size_data['height'] == 0 ) ? 600 : 0;
			break;
			case 'large':
				$hw = ( $size_data['width'] == 1024 && $size_data['width'] === $size_data['height'] ) ? 1200 : 0;
			break;
		}
		if ($hw) {
			update_option( "{$size_name}_size_w", $hw );
			( $size_name !== 'medium_large' ) && update_option( "{$size_name}_size_h", $hw );
		}
	}
}

add_action( 'after_switch_theme', 'examples_image_sizes' );


/**
 * Theme setup
 */
function examples_setup() {
	if ( current_theme_supports( 'post-thumbnails' ) )
		set_post_thumbnail_size( 300, 300, false );
}

add_action( 'after_setup_theme', 'examples_setup' );


/**
 * Image darkroom backend
 */
function image_darkroom_backend() {
	add_action( 'admin_print_footer_scripts', 'image_darkroom_script', 99999 );
}

add_action( 'load-upload.php', 'image_darkroom_backend' );


/**
 * Image darkroom script
 */
function image_darkroom_script() {
	include_once get_theme_file_path( 'image-darkroom-script.php' );
}


/**
 * Conditionally load example
 *
 * @param string $example
 */
function examples_conditional_load_example( $example ) {
	switch ( $example ) {
		case 'examples__hooks_replace_image_path':
			add_action( 'load-upload.php', fn() => add_action( 'admin_print_footer_scripts', $example, 99999 ) );
		break;
		case 'examples__hooks_quality_per_size':
		case 'examples__hooks_resample_by_format':
		case 'examples__hooks_exclude_image_resize':
		case 'examples__hooks_preprocessor_grayscale_image':
			add_action( 'load-post.php', fn() => add_action( 'admin_print_footer_scripts', $example, 99999 ) );
			add_action( 'load-post-new.php', fn() => add_action( 'admin_print_footer_scripts', $example, 99999 ) );
			add_action( 'load-upload.php', fn() => add_action( 'admin_print_footer_scripts', $example, 99999 ) );
			add_action( 'load-media-new.php', fn() => add_action( 'admin_print_footer_scripts', $example, 99999 ) );
		break;
	}
}


/**
 * Example: Turn off the Bulk Resizer
 */
if ( EXAMPLES__deactivate_bulk_resizer ) :
	add_filter( 'vrannemstein_bulk_resizer', '__return_false' );
endif;


/**
 * Example: Replace the image source path
 *
 * - a folder named "images-hd-src" in wp-content is required
 */
if ( EXAMPLES__hooks_replace_image_path ) :
	function examples__hooks_replace_image_path() {
?>
<script id="examples--hooks-replace-image-path">
var vrannemstein_hooks = window.vrannemstein_hooks || {};

vrannemstein_hooks.bulkImageSourceUrl = (source_url, src) => {
  return source_url.replace('/wp-content/uploads', '/wp-content/images-hd-src');
};
</script>
<?php
	}

	examples_conditional_load_example( 'examples__hooks_replace_image_path' );
endif;


/**
 * Example: Change the quality factor on a per-size basis
 *
 * - a ".jpg" file to upload or resize is required
 */
if ( EXAMPLES__hooks_quality_per_size ) :
	function examples__hooks_quality_per_size() {
?>
<script id="examples--hooks-quality-per-size">
var vrannemstein_hooks = window.vrannemstein_hooks || {};

var quality_factors = window.quality_factors || {
  'thumbnail': 78,
  'medium': 76,
  'medium_large': 76,
  'large': 78,
  'full': 92,
  'post-thumbnail': 77,
  '1536x1536': 69,
  '2048x2048': 68
};

vrannemstein_hooks.imageWriteOpts = (opts, writeOpts, sizes, source_url, extname) => {
  const {size} = sizes;

  if (/\.(jpg|jpeg|jpe)$/i.test(extname)) {
	opts.Q = quality_factors[size];
  }

  return opts;
};
</script>
<?php
	}

	examples_conditional_load_example( 'examples__hooks_quality_per_size' );
endif;


/**
 * Example: Change the resample kernel per image format
 *
 * - a ".webp" or ".avif" file to upload or resize is required
 */
if ( EXAMPLES__hooks_resample_by_format ) :
	function examples__hooks_resample_by_format() {
?>
<script id="examples--hooks-resample-by-format">
var vrannemstein_hooks = window.vrannemstein_hooks || {};

vrannemstein_hooks.imageThumbOpts = (opts, thumbOpts, sizes, source_url, extname) => {
  if (/\.(webp|avif)$/i.test(extname)) {
	thumbOpts.reduce = {kernel: 1}; // VipsKernel(1 linear)
  }

  return opts;
}
</script>
<?php
	}

	examples_conditional_load_example( 'examples__hooks_resample_by_format' );
endif;


/**
 * Example: Exclude an image from resize
 *
 * - a file named "animated.gif" to upload or resize is required
 */
if ( EXAMPLES__hooks_exclude_image_resize ) :
	function examples__hooks_exclude_image_resize() {
?>
<script id="examples--hooks-exclude-image-resize">
var vrannemstein_hooks = window.vrannemstein_hooks || {};

vrannemstein_hooks.imageThumbOpts = (opts, thumbOpts, sizes, source_url, extname) => {
  const filename = source_url.replace(/.+\/([^/]+)/, '$1');

  if (filename === 'animated.gif') {
	return null;
  }

  return opts;
}
</script>
<?php
	}

	examples_conditional_load_example( 'examples__hooks_exclude_image_resize' );
endif;

/**
 * Example: Grayscale an image using the pre-processor hook
 * 
 * - a file named "colors.jpg" to upload or resize is required
 */
if ( EXAMPLES__hooks_preprocessor_grayscale_image ) :
	function examples__hooks_preprocessor_grayscale_image() {
?>
<script id="examples--hooks-preprocessor-grayscale-image">
var vrannemstein_hooks = window.vrannemstein_hooks || {};

vrannemstein_hooks.imagePreprocessor = (vips, image, source_url, readOpts, writeOpts) => {
  const filename = source_url.replace(/.+\/([^/]+)/, '$1');

  if (filename === 'colors.jpg') {
	const grayscale = image.colourspace('b-w');

	return grayscale.copy({'interpretation': 'srgb'});
  }

  return image;
}
</script>
<?php
	}

	examples_conditional_load_example( 'examples__hooks_preprocessor_grayscale_image' );
endif;

