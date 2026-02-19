<?php
/**
 * REST API: Vrannemstein_REST_Controller
 *
 * @package vrannemstein
 * @author Leonardo Laureti
 * @license GPL-2.0-or-later
 * @api
 */

defined( 'ABSPATH' ) || die();

/**
 * Attachment thumbnails post-processing extending WP_REST_Attachments_Controller REST API
 *
 * @see WP_REST_Attachments_Controller
 */
class Vrannemstein_REST_Controller extends WP_REST_Attachments_Controller {

	/** @var string $rest_base */
	/** @var string $namespace */

	public array $registered_sizes;
	public string $filename;
	public int $subsizes_filter_priority = 9999; // reflects Vrannemstein class property

	/**
	 * Extends WP_REST_Attachments_Controller
	 */
	public function __construct() {
		parent::__construct( 'attachment' );
		
		$this->rest_base = 'attachment';
		$this->namespace = 'vrannemstein/v2';

		add_filter( 'wp_prevent_unsupported_mime_type_uploads', array( $this, 'rest_api_image_editor_supports_compat' ), 10, 2 );
	}

	/**
	 * Register routes
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/post-process',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'post_process_item' ),
				'permission_callback' => array( $this, 'post_process_item_permissions_check' ),
				'args' => array(
					'id'=> array(
						'description' => __( 'Unique identifier for the attachment.' ),
						'type' => 'integer'
					),
					'action' => array(
						'type' => 'string',
						'enum' => array( 'image-subsizes' ),
						'required' => true
					)
				)
			)
		);
	}

	/**
	 * Post process attachment item
	 *
	 * @see WP_REST_Attachments_Controller::post_process_item()
	 *
	 * @param WP_REST_Request $request The request
	 * @return WP_REST_Response|WP_Error $response The response
	 */
	public function post_process_item( $request ) {
		switch ( $request['action'] ) {
			case 'image-subsizes':
				$response = $this->image_subsizes( $request );
				break;
		}

		$request['context'] = 'edit';

		return $response;
	}

	/**
	 * Uploads attachment thumbnails and updates metadata
	 *
	 * @see WP_REST_Attachments_Controller::upload_from_file()
	 * @see WP_REST_Attachments_Controller::prepare_item_for_response()
	 *
	 * @param WP_REST_Request $request The request
	 * @return WP_REST_Response|WP_Error
	 */
	protected function image_subsizes( $request ) {
		if ( empty( $request['id'] ) ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'Invalid parent type.' ),
				array( 'status' => 400 )
			);
		}

		$files = $request->get_file_params();
		$headers = $request->get_headers();
		$post_data = $this->get_post_data( $request );
		$attachment = get_post( $request['id'] );
		$time = $attachment->post_date; // upload_dir/year/month
		$image_file = wp_get_original_image_path( $request['id'] );
		$image_meta = wp_get_attachment_metadata( $request['id'] );
		$this->registered_sizes = wp_get_registered_image_subsizes();
		$this->filename = $image_meta['file'];

		if ( empty( $image_file ) || empty( $image_meta ) || ! is_array( $image_meta ) ) {
			return new WP_Error(
				'rest_unknown_attachment',
				__( 'Unable to get meta information for file.' ),
				array( 'status' => 404 )
			);
		}

		if ( is_wp_error( $error = $this->validate_files( $files) ) )
			return $error;
		if ( is_wp_error( $error = $this->validate_post_data( $post_data ) ) )
			return $error;

		add_filter( 'wp_unique_filename', array( $this, 'handle_filename' ), 10, 3 );
		add_filter( 'pre_move_uploaded_file', array( $this, 'handle_upload' ), 10, 4 );

		$files_data = [];
		$sizes_data = [];
		$error = NULL;

		foreach ( $files as $size_name => $file ) {
			$file = $this->upload_from_file( array( 'file' => $file ), $headers, $time );

			if ( is_wp_error( $file ) ) {
				$error = $file;
				break;
			}
			$files_data[ $size_name ] = $file;
			$sizes_data[ $size_name ] = $post_data[ $size_name ];
		}

		remove_filter( 'wp_unique_filename', array( $this, 'handle_filename' ), 10 );
		remove_filter( 'pre_move_uploaded_file', array( $this, 'handle_upload', ), 10 );

		if ( $error )
			return $error;

		// Require image functions from wp-admin
		require_once ABSPATH . 'wp-admin/includes/image.php';

		remove_filter( 'intermediate_image_sizes_advanced', '__return_empty_array', $this->subsizes_filter_priority );
		/** This filter is documented in wp-admin/includes/image.php */
		$new_sizes = apply_filters( 'intermediate_image_sizes_advanced', $this->registered_sizes, $image_meta, $request['id'] );
		add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array', $this->subsizes_filter_priority );

		if ( isset( $image_meta['sizes'] ) && is_array( $image_meta['sizes'] ) ) {
			foreach ( $image_meta['sizes'] as $size_name => $size_meta ) {
				if ( array_key_exists( $size_name, $new_sizes ) ) {
					unset( $new_sizes[ $size_name ] );
				}
			}
		} else {
			$image_meta['sizes'] = array();
		}

		foreach ( $new_sizes as $size_name => $size_data ) {
			if ( ! isset( $size_data['width'] ) && ! isset( $size_data['height'] ) )
				continue;

			if ( isset( $files_data[ $size_name ] ) && file_exists( $files_data[ $size_name ]['file'] ) ) {
				$size_meta = array(
					'path' => $files_data[ $size_name ]['file'],
					/** This filter is documented in wp-includes/class-wp-image-editor-gd.php */
					'file' => wp_basename( apply_filters( 'image_make_intermediate_size', $files_data[ $size_name ]['file'] ) ),
					'width' => $sizes_data[ $size_name ]['dst_w'],
					'height' => $sizes_data[ $size_name ]['dst_h'],
					'mime-type' => $files_data[ $size_name ]['type'],
					'filesize' => wp_filesize( $files_data[ $size_name ]['file'] )
				);

				unset( $size_meta['path'] );
				$image_meta['sizes'][ $size_name ] = $size_meta;

				wp_update_attachment_metadata( $request['id'], $image_meta );
			}
		}

		if ( wp_is_serving_rest_request() )
			header( 'X-WP-Upload-Attachment-ID: ' . $request['id'] );

		/** This filter is documented in wp-admin/includes/image.php */
		$image_meta = apply_filters( 'wp_generate_attachment_metadata', $image_meta, $request['id'], 'update' );

		wp_update_attachment_metadata( $attachment_id, $image_meta );

		$attachment = get_post( $request['id'] );
		$response = $this->prepare_item_for_response( $attachment, $request );
		$response = rest_ensure_response( $response );
		//
		// $response->set_status( 502 ); // testing

		return $response;
	}

	/**
	 * Handles unique filename
	 *
	 * @see wp_unique_filename()
	 *
	 * @param string $filename
	 * @param string $ext
	 * @param string $dir
	 * @return string $filename 
	 */
	public function handle_filename( $filename, $ext, $dir ) {
		if ( file_exists( $dir . '/' . basename( $this->filename ) ) )
			$filename = preg_replace( '/-\d+(\.[^\.]+)$/', '$1', $filename, 1 );

		return $filename;
	}

	/**
	 * Handles uploaded file
	 *
	 * @see wp_handle_upload()
	 *
	 * @param null|bool $move_new_file
	 * @param array $file {
	 *     @type string $name
	 *     @type string $type
	 *     @type string $tmp_name
	 *     @type int $size
	 *     @type int $error
	 * }
	 * @param string $new_file
	 * @param string $type
	 * @return null|bool
	 */
	public function handle_upload( $move_new_file, $file, $new_file, $type ) {
		switch ( $type ) {
			case 'image/jpeg':
			case 'image/gif':
			case 'image/png':
			case 'image/webp':
			case 'image/avif':
				break;
			default:
				return false;
		}

		$new_file = preg_replace( '/-\d+x\d+(\.[^\.]+)$/', '$1', $new_file, 1 );

		return strrpos( $new_file, $this->filename ) !== false ? $move_new_file : false;
	}

	/**
	 * Post process item permissions check
	 *
	 * @see WP_REST_Attachments_Controller::post_process_item_permissions_check()
	 * @see WP_REST_Attachments_Controller::edit_media_item_permissions_check()
	 *
	 * @param WP_REST_Request $request The request
	 * @return true|WP_Error
	 */
	public function post_process_item_permissions_check( $request ) {
		return $this->edit_media_item_permissions_check( $request );
	}

	/**
	 * REST API image editor supports mime-type compatibility
	 *
	 * @see WP_REST_Attachments_Controller::create_item_permissions_check()
	 *
	 * @param bool $check_mime
	 * @param string|null $mime_type Image mime type
	 * @return bool Prevent uploads of unsupported images type
	 */
	public function rest_api_image_editor_supports_compat( $check_mime, $mime_type ) {
		if ( $mime_type === 'image/webp' || $mime_type === 'image/avif' )
			return false;
		return $check_mime;
	}

	/**
	 * Gets post data
	 *
	 * @param WP_REST_Request $request The request
	 * @return array Post data array
	 */
	protected function get_post_data( $request ) {
		return json_decode( $request->get_param( 'data' ), true );
	}

	/**
	 * Validates files
	 *
	 * @param array $value Files array
	 * @return true|WP_Error
	 */
	protected function validate_files( $value ) {
		foreach ( $value as $size_name => $file ) {
			$size_name = sanitize_key( $size_name );

			if ( ! array_key_exists( $size_name, $this->registered_sizes ) ) {
				return new WP_Error(
					'rest_invalid_param',
					sprintf( __( 'Invalid parameter(s): %s' ), 'size_name' ),
					array( 'status' => 400 )
				);
			}
		}

		return true;
	}	

	/**
	 * Validates post data
	 *
	 * @param array|null $value Post data array
	 * @return true|WP_Error
	 */
	protected function validate_post_data( $value ) {
		if ( $value === NULL ) {
			return new WP_Error(
				'rest_invalid_param',
				sprintf( __( '%s parameter must be a valid JSON string.' ), 'data' ),
				array( 'status' => 400 )
			);
		}

		$invalid_params = array();

		foreach ( $value as $size_name => $size_data ) {
			$size_name = sanitize_key( $size_name );

			if ( ! array_key_exists( $size_name, $this->registered_sizes ) ) {
				$invalid_params[] = 'size_name';
				continue;
			}

			list( $width, $height, $crop ) = $this->registered_sizes[ $size_name ];

			if ( is_int( $size_data['dst_w'] ) && $width != 0 && $size_data['dst_w'] === $width )
				$invalid_params[ $size_name ][] = 'dst_w';
			if ( is_int( $size_data['dst_h'] ) && $height != 0 && $size_data['dst_h'] === $height )
				$invalid_params[ $size_name ][] = 'dst_h';
			if ( is_bool( $size_data['crop'] ) && $size_data['crop'] === $crop )
				$invalid_params[ $size_name ][] = 'crop';
			if ( is_int( $size_data['src_w'] ) && ! $size_data['src_w'] )
				$invalid_params[ $size_name ][] = 'src_w';
			if ( is_int( $size_data['src_h'] ) && ! $size_data['src_h'] )
				$invalid_params[ $size_name ][] = 'src_h';
		}

		if ( ! empty( $invalid_params ) ) {
			return new WP_Error(
				'rest_invalid_param',
				sprintf( __( 'Invalid parameter(s): %s' ), implode( ', ', array_keys( $invalid_params ) ) ),
				array( 'status' => 400, 'params'  => $invalid_params )
			);
		}

		return true;
	}
}

