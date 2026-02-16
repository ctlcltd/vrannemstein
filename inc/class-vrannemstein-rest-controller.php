<?php
/**
 * REST API: Vrannemstein_REST_Controller
 *
 * @package vrannemstein
 * @author Leonardo Laureti
 * @license GPL-2.0-or-later
 *
 * @api
 * @todo
 */

defined( 'ABSPATH' ) || die();

/**
 * Attachment thumbnails post-processing extending WP_REST_Attachments_Controller REST API.
 *
 * @see WP_REST_Attachments_Controller
 */
class Vrannemstein_REST_Controller extends WP_REST_Attachments_Controller {

	/** @var string $rest_base */
	/** @var string $namespace */

	/** @var string */
	public $filename;

	/**
	 * Extends attachments controller
	 */
	public function __construct() {
		parent::__construct( 'attachment' );
		
		$this->rest_base = 'attachment';
		$this->namespace = 'vrannemstein/v2';
	}

	/**
	 * Register routes
	 *
	 * @see WP_REST_Attachments_Controller::post_process_item()
	 * @see WP_REST_Attachments_Controller::post_process_item_permissions_check()
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/post-process',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'post_process_item' ),
				//
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
	 * @see WP_REST_Attachments_Controller::prepare_item_for_response()
	 *
	 * @param WP_REST_Request $request The request
	 * @return WP_REST_Response|WP_Error
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
	 *
	 * @param WP_REST_Request $request The request
	 * @return WP_REST_Response|WP_Error
	 */
	public function image_subsizes( $request ) {
		if ( empty( $request['id'] ) ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'Invalid parent type.' ),
				array( 'status' => 400 )
			);
		}

		$files = $request->get_file_params();
		$headers = $request->get_headers();
		$attachment = get_post( $request['id'] );
		$time = $attachment->post_date; // upload_dir/year/month
		$image_file = wp_get_original_image_path( $request['id'] );
		$image_meta = wp_get_attachment_metadata( $request['id'] );
		$this->filename = $image_meta['file'];

		if ( empty( $image_file ) || empty( $image_meta ) || ! is_array( $image_meta ) ) {
			return new WP_Error(
				'rest_unknown_attachment',
				__( 'Unable to get meta information for file.' ),
				array( 'status' => 404 )
			);
		}

		// return print_r([$headers, $files], true);

		add_filter( 'wp_unique_filename', array( $this, 'handle_filename' ), 10, 3 );
		add_filter( 'pre_move_uploaded_file', array( $this, 'handle_upload' ), 10, 4 );

		$error = null;

		foreach ( $files as $i => $file ) {
			//
			//TODO pre check ?
			$data[ $i ] = $this->upload_from_file( array( 'file' => $file ), $headers, $time );

			if ( is_wp_error( $data[ $i ] ) ) {
				$error = $data[ $i ];
				break;
			}
		}

		remove_filter( 'wp_unique_filename', array( $this, 'handle_filename' ), 10 );
		remove_filter( 'pre_move_uploaded_file', array( $this, 'handle_upload', ), 10 );

		if ( is_wp_error( $error ) ) {
			foreach ( $data as $file ) {
				if ( ! empty( $file['file'] ) )
					@unlink( $file['file'] );
			}

			return $error;
		}

		// Require image functions from wp-admin
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$new_sizes = wp_get_registered_image_subsizes();

		// remove_filter( 'intermediate_image_sizes_advanced', '__return_empty_array', 9999 );
		/** This filter is documented in wp-admin/includes/image.php */
		// $new_sizes = apply_filters( 'intermediate_image_sizes_advanced', $new_sizes, $image_meta, $request['id'] );
		// add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array', 9999 );

		if ( isset( $image_meta['sizes'] ) && is_array( $image_meta['sizes'] ) ) {
			foreach ( $image_meta['sizes'] as $size_name => $size_meta ) {
				if ( array_key_exists( $size_name, $new_sizes ) ) {
					unset( $new_sizes[ $size_name ] );
				}
			}
		} else {
			$image_meta['sizes'] = array();
		}

		if ( empty( $new_sizes ) ) {
			//
			return false;
		}

		foreach ( $new_sizes as $size_name => $size_data ) {
			if ( ! isset( $size_data['width'] ) && ! isset( $size_data['height'] ) )
				continue;

			if (0) {
				// error
				return false;
			} else {
				$size_meta = array(
					//
					'path' => 'todo-' . $size_name,
					/** This filter is documented in wp-includes/class-wp-image-editor-gd.php */
					'file' => wp_basename( apply_filters( 'image_make_intermediate_size', 'todo' ) ),
					'width' => $size_data['width'], // dst_w
					'height' => $size_data['height'], // dst_h
					'mime-type' => '',
					'filesize' => 0
					//
				);
			}

			if (0) {
				unset( $size_meta['path'] );
				$image_meta['sizes'][ $size_name ] = $size_meta;

				// wp_update_attachment_metadata( $request['id'], $image_meta );
			} else {
				$image_meta['missing_image_sizes'][ $size_name ] = $size_meta;

				if ( wp_is_serving_rest_request() ) {
					header( 'X-WP-Upload-Attachment-ID: ' . $attachment_id );
				}
			}
		}

		/** This filter is documented in wp-admin/includes/image.php */
		$image_meta = apply_filters( 'wp_generate_attachment_metadata', $image_meta, $request['id'], 'update' );

		// wp_update_attachment_metadata( $attachment_id, $image_meta );

		$attachment = get_post( $request['id'] );
		$response = $this->prepare_item_for_response( $attachment, $request );
		$response = rest_ensure_response( $response );

		return $response;
	}

	/**
	 * Handles unique filename
	 *
	 * @see wp_unique_filename()
	 *
	 * @param string $ext
	 * @param string $dir
	 * @return string $filename 
	 */
	public function handle_filename( $filename, $ext, $dir ) {
		//
		return $filename;
		$origin_filename = basename( $this->filename );

		return file_exists( $dir . $origin_filename ) ? $filename : $origin_filename;
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
	 */
	public function handle_upload( $move_new_file, $file, $new_file, $type ) {
		//
		// return $move_new_file;
		return false; //todo testing
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

		return ( $new_file === $this->filename ) ? $move_new_file : false;
	}
}

