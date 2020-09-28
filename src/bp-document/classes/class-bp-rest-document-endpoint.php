<?php
/**
 * BP REST: BP_REST_Document_Endpoint class
 *
 * @package BuddyBoss
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * BuddyPress Document endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Document_Endpoint extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base = 'document';

		$this->bp_rest_document_support();
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/upload',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'upload_item' ),
					'permission_callback' => array( $this, 'upload_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'A unique numeric ID for the document.', 'buddyboss' ),
						'type'        => 'integer',
						'required'    => true,
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Upload Document.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response | WP_Error
	 * @since 0.1.0
	 *
	 * @api            {POST} /wp-json/buddyboss/v1/document/upload Upload Document
	 * @apiName        UploadBBDocument
	 * @apiGroup       Document
	 * @apiDescription Upload Document.
	 * @apiVersion     1.0.0
	 * @apiPermission  LoggedInUser
	 * @apiParam {String} file File object which is going to upload.
	 */
	public function upload_item( $request ) {

		$file = $request->get_file_params();

		if ( empty( $file ) ) {
			return new WP_Error(
				'bp_rest_document_file_required',
				__( 'Sorry, you have not uploaded any document.', 'buddyboss' ),
				array(
					'status' => 400,
				)
			);
		}

		add_filter( 'upload_dir', 'bp_document_upload_dir_script' );

		/**
		 * Create and upload the document file.
		 */
		$upload = bp_document_upload();

		remove_filter( 'upload_dir', 'bp_document_upload_dir_script' );

		if ( is_wp_error( $upload ) ) {
			return new WP_Error(
				'bp_rest_document_upload_error',
				$upload->get_error_message(),
				array(
					'status' => 400,
				)
			);
		}

		$retval = array(
			'id'   => $upload['id'],
			'url'  => $upload['url'],
			'name' => $upload['name'],
			'type' => $upload['type'],
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a document is uploaded via the REST API.
		 *
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 *
		 * @since 0.1.0
		 */
		do_action( 'bp_rest_document_upload_item', $response, $request );

		return $response;

	}

	/**
	 * Checks if a given request has access to get all users.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool
	 * @since 0.1.0
	 */
	public function upload_item_permissions_check( $request ) {

		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to upload document.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the members `upload_item` permissions check.
		 *
		 * @param bool            $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 0.1.0
		 */
		return apply_filters( 'bp_rest_document_upload_item_permissions_check', $retval, $request );
	}

	/**
	 * Retrieve documents.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response | WP_Error
	 * @since 0.1.0
	 *
	 * @api            {GET} /wp-json/buddyboss/v1/document Get Documents
	 * @apiName        GetBBDocuments
	 * @apiGroup       Document
	 * @apiDescription Retrieve Documents.
	 * @apiVersion     1.0.0
	 * @apiPermission  LoggedInUser if the site is in Private Network.
	 * @apiParam {Number} [page] Current page of the collection.
	 * @apiParam {Number} [per_page=10] Maximum number of items to be returned in result set.
	 * @apiParam {String} [search] Limit results to those matching a string.
	 * @apiParam {String=asc,desc} [order=asc] Order sort attribute ascending or descending.
	 * @apiParam {String=title,date_created,date_modified,group_id,privacy} [orderby=title] Order by a specific parameter.
	 * @apiParam {Number} [user_id] Limit result set to items created by a specific user (ID).
	 * @apiParam {Number} [max] Maximum number of results to return.
	 * @apiParam {Number} [folder_id] A unique numeric ID for the Folder.
	 * @apiParam {Number} [group_id] A unique numeric ID for the Group.
	 * @apiParam {Number} [activity_id] A unique numeric ID for the Document's Activity.
	 * @apiParam {Array=public,loggedin,friends,onlyme,grouponly,message,forums} [privacy=public] Privacy of the Document.
	 * @apiParam {Array=public,friends,groups,personal} [scope] Scope of the Document.
	 * @apiParam {Array} [exclude] Ensure result set excludes specific IDs.
	 * @apiParam {Array} [include] Ensure result set includes specific IDs.
	 * @apiParam {Boolean} [count_total=true] Show total count or not.
	 */
	public function get_items( $request ) {
		$args = array(
			'page'        => $request['page'],
			'per_page'    => $request['per_page'],
			'sort'        => strtoupper( $request['order'] ),
			'order_by'    => $request['orderby'],
			'count_total' => $request['count_total'],
			'scope'       => '',
		);

		if ( ! empty( $request['search'] ) ) {
			$args['search_terms'] = $request['search'];
		}

		if ( ! empty( $request['max'] ) ) {
			$args['max'] = $request['max'];
		}

		if ( ! empty( $request['scope'] ) ) {
			$args['scope'] = $request['scope'];
		}

		if ( ! empty( $request['user_id'] ) ) {
			$args['user_id'] = $request['user_id'];
		}

		if ( ! empty( $request['folder_id'] ) ) {
			$args['folder_id'] = $request['folder_id'];
		}

		if ( ! empty( $request['group_id'] ) ) {
			$args['group_id'] = $request['group_id'];
		}

		if ( ! empty( $request['activity_id'] ) ) {
			$args['activity_id'] = $request['activity_id'];
		}

		if ( ! empty( $request['privacy'] ) ) {
			$args['privacy'] = $request['privacy'];
		}

		if ( ! empty( $request['exclude'] ) ) {
			$args['exclude'] = $request['exclude'];
		}

		if ( ! empty( $request['include'] ) ) {
			$args['include'] = $request['include'];
		}

		$args['scope'] = $this->bp_rest_document_default_scope( $args['scope'], $args );

		/**
		 * Filter the query arguments for the request.
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 0.1.0
		 */
		$args = apply_filters( 'bp_rest_document_get_items_query_args', $args, $request );

		$documents = $this->assemble_response_data( $args );

		$retval = array();
		foreach ( $documents['documents'] as $document ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $document, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, $documents['total'], $args['per_page'] );

		/**
		 * Fires after a list of documents is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response $response  The response data.
		 * @param WP_REST_Request  $request   The request sent to the API.
		 *
		 * @param array            $documents Fetched documents.
		 */
		do_action( 'bp_rest_document_get_items', $documents, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to get all users.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool
	 * @since 0.1.0
	 */
	public function get_items_permissions_check( $request ) {
		$retval = true;

		if ( function_exists( 'bp_enable_private_network' ) && true !== bp_enable_private_network() && ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, Restrict access to only logged-in members.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the members `get_items` permissions check.
		 *
		 * @param bool            $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 0.1.0
		 */
		return apply_filters( 'bp_rest_document_get_items_permissions_check', $retval, $request );
	}

	/**
	 * Retrieve a single document.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response | WP_Error
	 * @since 0.1.0
	 * @api            {GET} /wp-json/buddyboss/v1/document/:id Get Document
	 * @apiName        GetBBDocument
	 * @apiGroup       Document
	 * @apiDescription Retrieve a single document.
	 * @apiVersion     1.0.0
	 * @apiPermission  LoggedInUser if the site is in Private Network.
	 * @apiParam {Number} id A unique numeric ID for the document.
	 */
	public function get_item( $request ) {

		$id = $request['id'];

		$documents = $this->assemble_response_data( array( 'document_ids' => array( $id ) ) );

		if ( empty( $documents['documents'] ) ) {
			return new WP_Error(
				'bp_rest_document_invalid_id',
				__( 'Invalid document ID.', 'buddyboss' ),
				array(
					'status' => 404,
				)
			);
		}

		$retval = '';
		foreach ( $documents['documents'] as $document ) {
			$retval = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $document, $request )
			);
		}

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a document is fetched via the REST API.
		 *
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 *
		 * @since 0.1.0
		 */
		do_action( 'bp_rest_document_get_item', $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to get all users.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool
	 * @since 0.1.0
	 */
	public function get_item_permissions_check( $request ) {
		$retval = true;

		if ( function_exists( 'bp_enable_private_network' ) && true !== bp_enable_private_network() && ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, Restrict access to only logged-in members.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$document = new BP_Document( $request['id'] );

		if ( true === $retval && empty( $document->id ) ) {
			$retval = new WP_Error(
				'bp_rest_document_invalid_id',
				__( 'Invalid document ID.', 'buddyboss' ),
				array(
					'status' => 404,
				)
			);
		}

		if (
			true === $retval
			&& 'public' !== $document->privacy
			&& true === $this->bp_rest_check_privacy_restriction( $document )
		) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, Restrict access to view this document.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the document `get_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Request $request The request sent to the API.
		 * @param bool            $retval  Returned value.
		 */
		return apply_filters( 'bp_rest_document_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Create documents.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response | WP_Error
	 * @since 0.1.0
	 *
	 * @api            {POST} /wp-json/buddyboss/v1/document Create Document
	 * @apiName        CreateBBDocument
	 * @apiGroup       Document
	 * @apiDescription Create Document.
	 * @apiVersion     1.0.0
	 * @apiPermission  LoggedInUser
	 * @apiParam {Array} document_ids Document specific IDs.
	 * @apiParam {Number} [activity_id] A unique numeric ID for the activity.
	 * @apiParam {Number} [group_id] A unique numeric ID for the Group.
	 * @apiParam {Number} [folder_id] A unique numeric ID for the Document Folder.
	 * @apiParam {string} [content] Document Content.
	 * @apiParam {string=public,loggedin,friends,onlyme,grouponly,message} [privacy=public] Privacy of the Document.
	 */
	public function create_item( $request ) {

		$args = array(
			'document_ids' => $request['document_ids'],
			'privacy'      => $request['privacy'],
		);

		if ( empty( $request['document_ids'] ) ) {
			return new WP_Error(
				'bp_rest_no_document_found',
				__( 'Sorry, you are not allowed to create a Document item.', 'buddyboss' ),
				array(
					'status' => 400,
				)
			);
		}

		if ( isset( $request['group_id'] ) && ! empty( $request['group_id'] ) ) {
			$args['group_id'] = $request['group_id'];
		}

		if ( isset( $request['folder_id'] ) && ! empty( $request['folder_id'] ) ) {
			$args['folder_id'] = $request['folder_id'];
		}

		if ( isset( $request['activity_id'] ) && ! empty( $request['activity_id'] ) ) {
			$args['activity_id'] = $request['activity_id'];
		}

		if ( isset( $request['content'] ) && ! empty( $request['content'] ) ) {
			$args['content'] = $request['content'];
		}

		/**
		 * Filter the query arguments for the request.
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 0.1.0
		 */
		$args = apply_filters( 'bp_rest_document_create_items_query_args', $args, $request );

		$document_ids = $this->bp_rest_create_document( $args );

		if ( is_wp_error( $document_ids ) ) {
			return $document_ids;
		}

		$documents = $this->assemble_response_data( array( 'document_ids' => $document_ids ) );
		$document  = current( $documents['documents'] );

		$fields_update = $this->update_additional_fields_for_object( $document, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$retval = $this->prepare_response_for_collection(
			$this->prepare_item_for_response( $document, $request )
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a Document is created via the REST API.
		 *
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 *
		 * @since 0.1.0
		 */
		do_action( 'bp_rest_document_create_item', $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to create a document.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 * @since 0.1.0
	 */
	public function create_item_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to create a document.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to create a folder.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( true === $retval && isset( $request['group_id'] ) && ! empty( $request['group_id'] ) ) {
			if (
				! bp_is_active( 'groups' )
				|| groups_can_user_manage_document( bp_loggedin_user_id(), (int) $request['group_id'] )
				|| ! function_exists( 'bp_is_group_document_support_enabled' )
				|| ( function_exists( 'bp_is_group_document_support_enabled' ) && false === bp_is_group_document_support_enabled() )
			) {
				$retval = new WP_Error(
					'bp_rest_invalid_permission',
					__( 'You don\'t have a permission to create a document inside this group.', 'buddyboss' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}
		}

		if ( true === $retval && isset( $request['folder_id'] ) && ! empty( $request['folder_id'] ) ) {
			$parent_folder = new BP_Document_Folder( $request['folder_id'] );
			if ( empty( $parent_folder->id ) ) {
				$retval = new WP_Error(
					'bp_rest_invalid_parent_folder_id',
					__( 'Invalid Parent Folder ID.', 'buddyboss' ),
					array(
						'status' => 400,
					)
				);
			} elseif ( ! bp_folder_user_can_edit( $parent_folder->id ) ) {
				$retval = new WP_Error(
					'bp_rest_invalid_permission',
					__( 'You don\'t have a permission to create a document inside this folder.', 'buddyboss' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}
		}

		/**
		 * Filter the document `create_item` permissions check.
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 0.1.0
		 */
		return apply_filters( 'bp_rest_document_create_items_permissions_check', $retval, $request );
	}

	/**
	 * Update a document.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response | WP_Error
	 * @since 0.1.0
	 *
	 * @api            {PATCH} /wp-json/buddyboss/v1/document/:id Update Document
	 * @apiName        UpdateBBDocument
	 * @apiGroup       Document
	 * @apiDescription Update a single Document.
	 * @apiVersion     1.0.0
	 * @apiPermission  LoggedInUser
	 * @apiParam {Number} id A unique numeric ID for the document.
	 * @apiParam {Number} [folder_id] A unique numeric ID for the folder.
	 * @apiParam {Number} [group_id] A unique numeric ID for the Group.
	 * @apiParam {string} [title] Document title.
	 * @apiParam {string=public,loggedin,onlyme,friends,grouponly,message} [privacy] Privacy of the document.
	 */
	public function update_item( $request ) {
		$id = $request['id'];

		$documents = $this->assemble_response_data( array( 'document_ids' => array( $id ) ) );

		if ( empty( $documents['documents'] ) ) {
			return new WP_Error(
				'bp_rest_document_invalid_id',
				__( 'Invalid document ID.', 'buddyboss' ),
				array(
					'status' => 404,
				)
			);
		}

		$document = end( $documents['documents'] );

		$args = array(
			'id'            => $document->id,
			'privacy'       => $document->privacy,
			'attachment_id' => $document->attachment_id,
			'group_id'      => $document->group_id,
			'activity_id'   => $document->activity_id,
			'folder_id'     => $document->folder_id,
			'title'         => $document->title,
		);

		if ( isset( $request['group_id'] ) && ! empty( $request['group_id'] ) ) {
			$args['group_id'] = $request['group_id'];
			$args['privacy']  = 'grouponly';
		}

		if ( isset( $request['folder_id'] ) && ! empty( $request['folder_id'] ) ) {
			$args['folder_id'] = $request['folder_id'];
			$parent_folder     = new BP_Document_Folder( $args['folder_id'] );
			$args['privacy']   = $parent_folder->privacy;
		}

		/**
		 * Filter the query arguments for the request.
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 0.1.0
		 */
		$args = apply_filters( 'bp_rest_document_update_items_query_args', $args, $request );

		if ( ! empty( $request['title'] ) ) {
			$document_rename = bp_document_rename_file( $document->id, $document->attachment_id, $request['title'] );
			if ( ! isset( $document_rename['document_id'] ) || $document_rename['document_id'] < 1 ) {
				return new WP_Error(
					'bp_rest_document_rename',
					$document_rename,
					array(
						'status' => 404,
					)
				);
			}
		}

		if (
			empty( $document->folder_id )
			&& ( ! isset( $request['folder_id'] ) || empty( $request['folder_id'] ) )
			&& isset( $request['privacy'] )
			&& ! empty( $request['privacy'] )
		) {
			bp_document_update_privacy( $document->id, $request['privacy'], 'document' );
		}

		$id     = $this->bp_rest_create_document( $args );
		$status = true;

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		if ( empty( $id ) ) {
			$status = false;
		}

		$documents = $this->assemble_response_data( array( 'document_ids' => array( $request['id'] ) ) );
		$document  = current( $documents['documents'] );

		$fields_update = $this->update_additional_fields_for_object( $document, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$retval = $this->prepare_response_for_collection(
			$this->prepare_item_for_response( $document, $request )
		);

		$response = new WP_REST_Response();
		$response->set_data(
			array(
				'updated' => $status,
				'data'    => $retval,
			)
		);

		/**
		 * Fires after an document is updated via the REST API.
		 *
		 * @param WP_REST_Response     $response The response data.
		 * @param WP_REST_Request      $request  The request sent to the API.
		 *
		 * @since 0.1.0
		 */
		do_action( 'bp_rest_document_update_item', $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to update a document.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool|WP_Error
	 * @since 0.1.0
	 */
	public function update_item_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to update this document.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$document = new BP_Document( $request['id'] );

		if ( true === $retval && empty( $document->id ) ) {
			$retval = new WP_Error(
				'bp_rest_document_invalid_id',
				__( 'Invalid document ID.', 'buddyboss' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( true === $retval && ! bp_document_user_can_edit( $document ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to update this document.', 'buddyboss' ),
				array(
					'status' => 500,
				)
			);
		}

		if ( true === $retval && isset( $request['group_id'] ) && ! empty( $request['group_id'] ) ) {
			if (
				! bp_is_active( 'groups' )
				|| groups_can_user_manage_document( bp_loggedin_user_id(), (int) $request['group_id'] )
			) {
				$retval = new WP_Error(
					'bp_rest_invalid_permission',
					__( 'You don\'t have a permission to edit a document inside this group.', 'buddyboss' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}
		}

		/**
		 * Filter the document to `update_item` permissions check.
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 0.1.0
		 */
		return apply_filters( 'bp_rest_document_update_item_permissions_check', $retval, $request );
	}

	/**
	 * Delete a single document.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response | WP_Error
	 * @since 0.1.0
	 *
	 * @api            {DELETE} /wp-json/buddyboss/v1/document/:id Delete Document
	 * @apiName        DeleteBBDocument
	 * @apiGroup       Document
	 * @apiDescription Delete a single Document.
	 * @apiVersion     1.0.0
	 * @apiPermission  LoggedInUser
	 * @apiParam {Number} id A unique numeric ID for the document.
	 */
	public function delete_item( $request ) {

		$id = $request['id'];

		$documents = $this->assemble_response_data( array( 'document_ids' => array( $id ) ) );

		if ( empty( $documents['documents'] ) ) {
			return new WP_Error(
				'bp_rest_document_invalid_id',
				__( 'Invalid document ID.', 'buddyboss' ),
				array(
					'status' => 404,
				)
			);
		}

		$previous = '';
		foreach ( $documents['documents'] as $document ) {
			$previous = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $document, $request )
			);
		}

		if ( ! bp_document_user_can_delete( $id ) ) {
			return WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to delete this document.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$status = bp_document_delete( array( 'id' => $id ), true );

		// Build the response.
		$response = new WP_REST_Response();
		$response->set_data(
			array(
				'deleted'  => $status,
				'previous' => $previous,
			)
		);

		/**
		 * Fires after a document is deleted via the REST API.
		 *
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 *
		 * @since 0.1.0
		 */
		do_action( 'bp_rest_document_delete_item', $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to for the user.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool
	 * @since 0.1.0
	 */
	public function delete_item_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to delete this document.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$document = new BP_Document( $request['id'] );

		if ( true === $retval && empty( $document->id ) ) {
			$retval = new WP_Error(
				'bp_rest_document_invalid_id',
				__( 'Invalid document ID.', 'buddyboss' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( true === $retval && ! bp_document_user_can_delete( $document ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to delete this document.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the document `delete_item` permissions check.
		 *
		 * @param bool            $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 0.1.0
		 */
		return apply_filters( 'bp_rest_document_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Select the item schema arguments needed for the CREATABLE methods.
	 *
	 * @param string $method Optional. HTTP method of the request.
	 *
	 * @return array Endpoint arguments.
	 * @since 0.1.0
	 */
	public function get_endpoint_args_for_item_schema( $method = WP_REST_Server::CREATABLE ) {
		$args = array();
		$key  = 'create';

		if ( WP_REST_Server::CREATABLE === $method ) {
			$args['document_ids'] = array(
				'description'       => __( 'Document specific IDs.', 'buddyboss' ),
				'default'           => array(),
				'type'              => 'array',
				'required'          => true,
				'items'             => array( 'type' => 'integer' ),
				'sanitize_callback' => 'wp_parse_id_list',
				'validate_callback' => 'rest_validate_request_arg',
			);

			$args['activity_id'] = array(
				'description'       => __( 'A unique numeric ID for the activity.', 'buddyboss' ),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			);

			$args['content'] = array(
				'description'       => __( 'Document Content.', 'buddyboss' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			);
		}

		if ( WP_REST_Server::EDITABLE === $method ) {
			$key        = 'edit';
			$args['id'] = array(
				'description'       => __( 'A unique numeric ID for the document.', 'buddyboss' ),
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			);
		}

		$args['group_id'] = array(
			'description'       => __( 'A unique numeric ID for the Group.', 'buddyboss' ),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$args['folder_id'] = array(
			'description'       => __( 'A unique numeric ID for the Document Folder.', 'buddyboss' ),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$args['privacy'] = array(
			'description'       => __( 'Privacy of the document.', 'buddyboss' ),
			'type'              => 'string',
			'enum'              => array( 'public', 'loggedin', 'friends', 'onlyme', 'grouponly' ),
			'default'           => 'public',
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		/**
		 * Filters the method query arguments.
		 *
		 * @param array  $args   Query arguments.
		 * @param string $method HTTP method of the request.
		 *
		 * @since 0.1.0
		 */
		return apply_filters( "bp_rest_document_{$key}_query_arguments", $args, $method );
	}

	/**
	 * Get documents.
	 *
	 * @param array|string $args All arguments and defaults are shared with BP_Document::get(),
	 *                           except for the following.
	 *
	 * @return array
	 */
	public function assemble_response_data( $args ) {

		// Fetch specific document items based on ID's.
		if ( isset( $args['document_ids'] ) && ! empty( $args['document_ids'] ) ) {
			return bp_document_get_specific( $args );

			// Fetch all activity items.
		} else {
			return bp_document_get( $args );
		}
	}

	/**
	 * Prepares document data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_Document     $document Document data.
	 * @param WP_REST_Request $request  Full details about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $document, $request ) {
		$data = $this->document_get_prepare_response( $document, $request );

		$data = $this->add_additional_fields_to_object( $data, $request );

		$response = rest_ensure_response( $data );

		/**
		 * Filter an document value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Request  $request  Request used to generate the response.
		 * @param BP_Document      $document The Document object.
		 *
		 * @param WP_REST_Response $response The response data.
		 */
		return apply_filters( 'bp_rest_document_prepare_value', $response, $request, $document );
	}

	/**
	 * Prepare object response for the document/folder.
	 *
	 * @param BP_Document $document Document object.
	 * @param array       $request  Request paramaters.
	 *
	 * @return array
	 */
	public function document_get_prepare_response( $document, $request ) {
		$data = array(
			'id'                    => $document->id,
			'blog_id'               => $document->blog_id,
			'attachment_id'         => ( isset( $document->attachment_id ) ? $document->attachment_id : 0 ),
			'user_id'               => $document->user_id,
			'title'                 => $document->title,
			'type'                  => ( empty( $document->attachment_id ) ? 'folder' : 'document' ),
			'folder_id'             => $document->parent,
			'group_id'              => $document->group_id,
			'activity_id'           => ( isset( $document->activity_id ) ? $document->activity_id : 0 ),
			'privacy'               => $document->privacy,
			'menu_order'            => ( isset( $document->menu_order ) ? $document->menu_order : 0 ),
			'date_created'          => $document->date_created,
			'date_modified'         => $document->date_modified,
			'group_name'            => $document->group_name,
			'group_status'          => ( bp_is_active( 'groups' ) && ! empty( $document->group_id ) ? bp_get_group_status( groups_get_group( $document->group_id ) ) : '' ),
			'visibility'            => $document->visibility,
			'download_url'          => '',
			'extension'             => '',
			'extension_description' => '',
			'svg_icon'              => '',
			'filename'              => '',
			'size'                  => '',
			'msg_preview'           => '',
			'attachment_data'       => ( isset( $document->attachment_data ) ? $document->attachment_data : array() ),
			'user_nicename'         => $document->user_nicename,
			'user_login'            => $document->user_login,
			'display_name'          => $document->display_name,
			'user_permissions'      => $this->get_document_current_user_permissions( $document, $request ),
		);

		if ( ! empty( $document->attachment_id ) ) {
			$data['download_url'] = bp_document_download_link( $document->attachment_id, $document->id );
			$data['extension']    = bp_document_extension( $document->attachment_id );
			$data['svg_icon']     = bp_document_svg_icon( $data['extension'], $document->attachment_id, 'svg' );
			$data['filename']     = basename( get_attached_file( $document->attachment_id ) );
			$data['size']         = bp_document_size_format( filesize( get_attached_file( $document->attachment_id ) ) );

			$extension_lists = bp_document_extensions_list();
			if ( ! empty( $extension_lists ) && ! empty( $data['extension'] ) ) {
				$extension_lists = array_column( $extension_lists, 'description', 'extension' );
				$extension_name  = '.' . $data['extension'];
				if ( ! empty( $extension_lists ) && ! empty( $data['extension'] ) && array_key_exists( $extension_name, $extension_lists ) ) {
					$data['extension_description'] = esc_html( $extension_lists[ $extension_name ] );
				}
			}

			$output = '';
			ob_start();

			if ( in_array( $data['extension'], bp_get_document_preview_music_extensions(), true ) ) {
				$audio_url = bp_document_get_preview_audio_url( $document->id, $data['extension'], $document->attachment_id );

				echo '<div class="document-audio-wrap">' .
					'<audio controls controlsList="nodownload">' .
						'<source src="' . esc_url_raw( $audio_url ) . '" type="audio/mpeg">' .
						esc_html__( 'Your browser does not support the audio element.', 'buddyboss' ) .
					'</audio>' .
				'</div>';

			}
			$attachment_url = bp_document_get_preview_image_url( $document->id, $data['extension'], $document->attachment_id );
			if ( $attachment_url ) {
				echo '<div class="document-preview-wrap">' .
					'<img src="' . esc_url_raw( $attachment_url ) . '" alt="" />' .
				'</div>';
			}
			$sizes = is_file( get_attached_file( $document->attachment_id ) ) ? get_attached_file( $document->attachment_id ) : 0;
			if ( $sizes && filesize( $sizes ) / 1e+6 < 2 ) {
				if ( in_array( $data['extension'], bp_get_document_preview_code_extensions(), true ) ) {
					$data_temp = bp_document_get_preview_text_from_attachment( $document->attachment_id );
					$file_data = $data_temp['text'];
					$more_text = $data_temp['more_text'];

					echo '<div class="document-text-wrap">' .
						'<div class="document-text" data-extension="' . esc_attr( $data['extension'] ) . '">' .
							'<textarea class="document-text-file-data-hidden" style="display: none;">' . wp_kses_post( $file_data ) . '</textarea>' .
						'</div>' .
						'<div class="document-expand">' .
							'<a href="#" class="document-expand-anchor"><i class="bb-icon-plus document-icon-plus"></i> ' . esc_html__( 'Click to expand', 'buddyboss' ) . '</a>' .
						'</div>' .
					'</div>';

					if ( true === $more_text ) {
						printf(
						/* translators: %s: download string */
							'<div class="more_text_view">%s</div>',
							sprintf(
							/* translators: %s: download url */
								wp_kses_post( 'This file was truncated for preview. Please <a href="%s">download</a> to view the full file.', 'buddyboss' ),
								esc_url( $data['download_url'] )
							)
						);
					}
				}
			}

			$output .= ob_get_clean();

			$data['msg_preview'] = $output;
		} else {
			$data['svg_icon']     = bp_document_svg_icon( 'folder', '', 'svg' );
			$data['download_url'] = bp_document_folder_download_link( $document->id );
		}

		return $data;
	}

	/**
	 * Get the document schema, conforming to JSON Schema.
	 *
	 * @return array
	 * @since 0.1.0
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bp_document',
			'type'       => 'object',
			'properties' => array(
				'id'                    => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'A unique numeric ID for the Document.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'blog_id'               => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Current Site ID.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'attachment_id'         => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Unique identifier for the document object.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'user_id'               => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'The ID for the author of the document.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'title'                 => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'The Document title.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'type'                  => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Whether it is a document or folder.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'folder_id'             => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'A unique numeric ID for the parent Folder.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'group_id'              => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'A unique numeric ID for the Group.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'activity_id'           => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'A unique numeric ID for the activity.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'privacy'               => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Privacy of the document.', 'buddyboss' ),
					'enum'        => array( 'public', 'loggedin', 'onlyme', 'friends', 'grouponly' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'menu_order'            => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Order of the item.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'date_created'          => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'The date the document was created, in the site\'s timezone.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
					'format'      => 'date-time',
				),
				'date_modified'         => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'The date the document was modified, in the site\'s timezone.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
					'format'      => 'date-time',
				),
				'group_name'            => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Group name associate with the document.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'group_status'          => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Group status associate with the document.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'visibility'            => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Visibility of the document.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'download_url'          => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Download URL for the document.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'extension'             => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Document file extension.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'extension_description' => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Document file description.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'svg_icon'              => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Document Icon based on the extension.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'filename'              => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Full name of the document file with extension.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'size'                  => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Size of the uploaded document.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'msg_preview'           => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Message preview for the document.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'attachment_data'       => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Wordpress Document Data.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'object',
					'properties'  => array(
						'full'           => array(
							'context'     => array( 'embed', 'view', 'edit' ),
							'description' => __( 'Document URL with full image size.', 'buddyboss' ),
							'readonly'    => true,
							'type'        => 'string',
						),
						'thumb'          => array(
							'context'     => array( 'embed', 'view', 'edit' ),
							'description' => __( 'Document URL with thumbnail image size.', 'buddyboss' ),
							'readonly'    => true,
							'type'        => 'string',
						),
						'activity_thumb' => array(
							'context'     => array( 'embed', 'view', 'edit' ),
							'description' => __( 'Document URL for the activity image size.', 'buddyboss' ),
							'readonly'    => true,
							'type'        => 'string',
						),
						'meta'           => array(
							'context'     => array( 'embed', 'view', 'edit' ),
							'description' => __( 'Meta items for the document.', 'buddyboss' ),
							'readonly'    => true,
							'type'        => 'object',
						),
					),
				),
				'user_nicename'         => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'The User\'s nice name to create a document.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'user_login'            => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'The User\'s login name to create a document.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
				),
				'display_name'          => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'The User\'s display name to create a document.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'string',
				),
			),
		);

		/**
		 * Filters the document schema.
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_document_schema', $this->add_additional_fields_schema( $schema ) );
	}

	/**
	 * Get the query params for collections.
	 *
	 * @return array
	 * @since 0.1.0
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['order'] = array(
			'description'       => __( 'Order sort attribute ascending or descending.', 'buddyboss' ),
			'default'           => 'asc',
			'type'              => 'string',
			'enum'              => array( 'asc', 'desc' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['orderby'] = array(
			'description'       => __( 'Order documents by which attribute.', 'buddyboss' ),
			'default'           => 'title',
			'type'              => 'string',
			'enum'              => array( 'title', 'date_created', 'date_modified', 'group_id', 'privacy' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['max'] = array(
			'description'       => __( 'Maximum number of results to return', 'buddyboss' ),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['scope'] = array(
			'description'       => __( 'Scope of the Document.', 'buddyboss' ),
			'type'              => 'array',
			'items'             => array(
				'enum' => array( 'public', 'friends', 'groups', 'personal' ),
				'type' => 'string',
			),
			'sanitize_callback' => 'bp_rest_sanitize_string_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_id'] = array(
			'description'       => __( 'Limit results to a specific user.', 'buddyboss' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['folder_id'] = array(
			'description'       => __( 'A unique numeric ID for the Folder.', 'buddyboss' ),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['group_id'] = array(
			'description'       => __( 'A unique numeric ID for the Group.', 'buddyboss' ),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['activity_id'] = array(
			'description'       => __( 'A unique numeric ID for the Document\'s Activity.', 'buddyboss' ),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['privacy'] = array(
			'description'       => __( 'Privacy of the Document.', 'buddyboss' ),
			'type'              => 'array',
			'items'             => array(
				'enum' => array( 'public', 'loggedin', 'friends', 'onlyme', 'grouponly', 'message', 'forums' ),
				'type' => 'string',
			),
			'sanitize_callback' => 'bp_rest_sanitize_string_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['include'] = array(
			'description'       => __( 'Ensure result set includes specific document IDs.', 'buddyboss' ),
			'default'           => array(),
			'type'              => 'array',
			'items'             => array( 'type' => 'integer' ),
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['exclude'] = array(
			'description'       => __( 'Ensure result set excludes specific document IDs.', 'buddyboss' ),
			'default'           => array(),
			'type'              => 'array',
			'items'             => array( 'type' => 'integer' ),
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['count_total'] = array(
			'description' => __( 'Show total count or not.', 'buddyboss' ),
			'default'     => true,
			'type'        => 'boolean',
		);

		/**
		 * Filters the collection query params.
		 *
		 * @param array $params Query params.
		 */
		return apply_filters( 'bp_rest_document_collection_params', $params );
	}

	/**
	 * Get document permissions based on current user.
	 *
	 * @param BP_Document $document The Document object.
	 * @param array       $request  Request parameter as array.
	 *
	 * @return array
	 */
	protected function get_document_current_user_permissions( $document, $request ) {
		$retval = array(
			'download'           => 0,
			'copy_download_link' => 0,
			'edit_privacy'       => 0,
			'edit_post_privacy'  => 0,
			'rename'             => 0,
			'move'               => 0,
			'delete'             => 0,
		);

		$document_privacy = array();

		if ( ! empty( $document->attachment_id ) ) {
			$document_privacy = bp_document_user_can_manage_document( $document->id, bp_loggedin_user_id() );
		} else {
			$document_privacy = bp_document_user_can_manage_folder( $document->id, bp_loggedin_user_id() );
		}

		if ( ! empty( $document_privacy ) ) {
			if ( isset( $document_privacy['can_download'] ) && true === (bool) $document_privacy['can_download'] ) {
				$retval['download']           = 1;
				$retval['copy_download_link'] = 1;
			}

			if ( isset( $document_privacy['can_manage'] ) && true === (bool) $document_privacy['can_manage'] ) {
				$retval['rename'] = 1;
				$retval['delete'] = 1;

				if ( 0 === (int) $document->group_id && 0 === (int) $document->parent ) {
					if ( ! empty( $document->attachment_id ) ) {
						$child_activity = get_post_meta( $document->attachment_id, 'bp_document_activity_id', true );
						if ( $child_activity ) {
							$parent_activity_id = get_post_meta( $document->attachment_id, 'bp_document_parent_activity_id', true );
							if ( bp_is_active( 'activity' ) ) {
								$retval['edit_post_privacy'] = $parent_activity_id;
							} else {
								$retval['edit_post_privacy'] = $document->activity_id;
							}
						}
					} else {
						$retval['edit_privacy'] = 1;
					}
				}
			}

			if ( isset( $document_privacy['can_add'] ) && true === (bool) $document_privacy['can_add'] ) {
				$retval['move'] = 1;
			}
		}

		if (
			isset( $request['support'] )
			&& (
				'activity' === $request['support']
				|| 'forums' === $request['support']
				|| 'message' === $request['support']
			)
		) {
			unset( $retval['rename'] );

			if (
				'activity' === $request['support']
				|| 'message' === $request['support']
				|| 'forums' === $request['support']
			) {
				unset( $retval['edit_privacy'] );
				unset( $retval['edit_post_privacy'] );
			}

			if ( 'message' === $request['support'] ) {
				unset( $retval['move'] );
				unset( $retval['delete'] );
			}

			if ( 'forums' === $request['support'] ) {
				unset( $retval['move'] );
			}
		}

		return $retval;
	}

	/**
	 * Create the Document IDs from Upload IDs.
	 *
	 * @param array $args Key value array of query var to query value.
	 *
	 * @return array|WP_Error
	 * @since 0.1.0
	 */
	public function bp_rest_create_document( $args ) {

		$document_privacy    = ( ! empty( $args['privacy'] ) ? $args['privacy'] : 'public' );
		$document_upload_ids = ( ! empty( $args['document_ids'] ) ? $args['document_ids'] : '' );
		$activity_id         = ( ! empty( $args['activity_id'] ) ? $args['activity_id'] : false );
		$content             = ( ! empty( $args['content'] ) ? $args['content'] : false );
		$user_id             = ( ! empty( $args['user_id'] ) ? $args['user_id'] : get_current_user_id() );
		$id                  = ( ! empty( $args['id'] ) ? $args['id'] : '' );

		$group_id  = ( ! empty( $args['group_id'] ) ? $args['group_id'] : false );
		$folder_id = ( ! empty( $args['folder_id'] ) ? $args['folder_id'] : false );

		// Override the privacy if Folder ID is given.
		if ( ! empty( $folder_id ) ) {
			$folders = bp_folder_get_specific( array( 'folder_ids' => array( $folder_id ) ) );
			if ( ! empty( $folders['folders'] ) ) {
				$folder           = array_pop( $folders['folders'] );
				$document_privacy = $folder->privacy;
			}
		}

		// Update Document.
		if ( ! empty( $id ) ) {

			$wp_attachment_id  = $args['attachment_id'];
			$wp_attachment_url = wp_get_attachment_url( $wp_attachment_id );

			// when the file found to be empty it's means it's not a valid attachment.
			if ( empty( $wp_attachment_url ) ) {
				return;
			}

			$document_activity_id = $activity_id;

			// extract the nice title name.
			$title = get_the_title( $wp_attachment_id );

			$document_id = bp_document_add(
				array(
					'id'            => $id,
					'attachment_id' => $wp_attachment_id,
					'title'         => $title,
					'activity_id'   => $document_activity_id,
					'folder_id'     => ( ! empty( $args['folder_id'] ) ? $args['folder_id'] : false ),
					'group_id'      => ( ! empty( $args['group_id'] ) ? $args['group_id'] : false ),
					'privacy'       => $document_privacy,
					'user_id'       => $user_id,
					'error_type'    => 'wp_error',
				)
			);

			if ( is_int( $document_id ) ) {

				// save document is saved in attachment.
				update_post_meta( $wp_attachment_id, 'bp_document_saved', true );

				// save document meta for activity.
				if ( ! empty( $document_activity_id ) ) {
					update_post_meta( $wp_attachment_id, 'bp_document_activity_id', $document_activity_id );
				}

				$created_document_ids[] = $document_id;

			}
		}

		// created Documents.
		if ( ! empty( $document_upload_ids ) ) {
			$valid_upload_ids = array();

			foreach ( $document_upload_ids as $wp_attachment_id ) {
				$wp_attachment_url = wp_get_attachment_url( $wp_attachment_id );

				// when the file found to be empty it's means it's not a valid attachment.
				if ( empty( $wp_attachment_url ) ) {
					continue;
				}

				$valid_upload_ids[] = $wp_attachment_id;
			}

			$documents = array();
			if ( ! empty( $valid_upload_ids ) ) {
				foreach ( $valid_upload_ids as $wp_attachment_id ) {

					// extract the nice title name.
					$title = get_the_title( $wp_attachment_id );

					$documents[] = array(
						'id'   => $wp_attachment_id,
						'name' => $title,
					);
				}
			}

			if ( ! empty( $documents ) ) {
				$created_document_ids = bp_document_add_handler( $documents, $document_privacy, $content, $group_id, $folder_id );
			}
		}

		if ( empty( $created_document_ids ) ) {
			return new WP_Error(
				'bp_rest_document_creation_error',
				__( 'Error creating document, please try again.', 'buddyboss' ),
				array(
					'status' => 400,
				)
			);
		}

		// Link all uploaded document to main activity.
		if ( ! empty( $activity_id ) && empty( $id ) ) {
			$created_document_ids_joined = implode( ',', $created_document_ids );
			bp_activity_update_meta( $activity_id, 'bp_document_ids', $created_document_ids_joined );

			$main_activity = new BP_Activity_Activity( $activity_id );
			if ( ! empty( $main_activity ) && empty( $group_id ) ) {
				$main_activity->privacy = $document_privacy;
				$main_activity->save();
			}
		}

		return $created_document_ids;
	}

	/**
	 * Get default scope for the document.
	 * - from bp_document_default_scope();
	 *
	 * @param string $scope Default scope.
	 * @param array  $args  Array of document argument.
	 *
	 * @return string
	 */
	public function bp_rest_document_default_scope( $scope, $args ) {
		$new_scope = array();

		if ( ( 'all' === $scope || empty( $scope ) ) && ( empty( $args['group_id'] ) && empty( $args['user_id'] ) ) ) {
			$new_scope[] = 'public';

			if ( is_user_logged_in() ) {
				$new_scope[] = 'personal';

				if ( bp_is_active( 'friends' ) ) {
					$new_scope[] = 'friends';
				}
			}

			if ( bp_is_active( 'groups' ) ) {
				$new_scope[] = 'groups';
			}
		} elseif ( ! empty( $args['user_id'] ) && ( 'all' === $scope || empty( $scope ) ) ) {
			$new_scope[] = 'personal';
		} elseif ( bp_is_active( 'groups' ) && ! empty( $args['group_id'] ) && ( 'all' === $scope || empty( $scope ) ) ) {
			$new_scope[] = 'groups';
		}

		$new_scope = array_unique( $new_scope );

		if ( empty( $new_scope ) ) {
			$new_scope = (array) $scope;
		}

		/**
		 * Filter to update default scope.
		 */
		$new_scope = apply_filters( 'bp_rest_document_default_scope', $new_scope );

		return implode( ',', $new_scope );
	}

	/**
	 * Check user access based on the privacy for the single document.
	 *
	 * @param BP_Document $document Document object.
	 *
	 * @return bool
	 */
	protected function bp_rest_check_privacy_restriction( $document ) {
		return (
					'onlyme' === $document->privacy
					&& bp_loggedin_user_id() !== $document->user_id
				)
				|| (
					'loggedin' === $document->privacy
					&& empty( bp_loggedin_user_id() )
				)
				|| (
					bp_is_active( 'groups' )
					&& 'grouponly' === $document->privacy
					&& ! empty( $document->group_id )
					&& 'public' !== bp_get_group_status( groups_get_group( $document->group_id ) )
					&& empty( groups_is_user_admin( bp_loggedin_user_id(), $document->group_id ) )
					&& empty( groups_is_user_mod( bp_loggedin_user_id(), $document->group_id ) )
					&& empty( groups_is_user_member( bp_loggedin_user_id(), $document->group_id ) )
				)
				|| (
					bp_is_active( 'friends' )
					&& 'friends' === $document->privacy
					&& ! empty( $document->user_id )
					&& bp_loggedin_user_id() !== $document->user_id
					&& 'is_friend' !== friends_check_friendship_status( $document->user_id, bp_loggedin_user_id() )
				);
	}

	/**
	 * Added document support for activity, forum and messages.
	 */
	public function bp_rest_document_support() {

		if ( function_exists( 'bp_is_profile_document_support_enabled' ) && bp_is_profile_document_support_enabled() ) {
			bp_rest_register_field(
				'activity',      // Id of the BuddyPress component the REST field is about.
				'bp_documents', // Used into the REST response/request.
				array(
					'get_callback'    => array( $this, 'bp_documents_get_rest_field_callback' ),
					// The function to use to get the value of the REST Field.
					'update_callback' => array( $this, 'bp_documents_update_rest_field_callback' ),
					// The function to use to update the value of the REST Field.
					'schema'          => array(                                // The example_field REST schema.
						'description' => 'Activity Documents.',
						'type'        => 'object',
						'context'     => array( 'embed', 'view', 'edit' ),
					),
				)
			);

			register_rest_field(
				'activity_comments',      // Id of the BuddyPress component the REST field is about.
				'bp_documents', // Used into the REST response/request.
				array(
					'get_callback'    => array( $this, 'bp_documents_get_rest_field_callback' ),    // The function to use to get the value of the REST Field.
					'update_callback' => array( $this, 'bp_documents_update_rest_field_callback' ), // The function to use to update the value of the REST Field.
					'schema'          => array(                                // The example_field REST schema.
						'description' => 'Activity Documents.',
						'type'        => 'object',
						'context'     => array( 'embed', 'view', 'edit' ),
					),
				)
			);
		}

		if ( function_exists( 'bp_is_messages_document_support_enabled' ) && bp_is_messages_document_support_enabled() ) {
			// Messages Document Support.
			bp_rest_register_field(
				'messages',      // Id of the BuddyPress component the REST field is about.
				'bp_documents', // Used into the REST response/request.
				array(
					'get_callback'    => array( $this, 'bp_documents_get_rest_field_callback_messages' ),
					// The function to use to get the value of the REST Field.
					'update_callback' => array( $this, 'bp_documents_update_rest_field_callback_messages' ),
					// The function to use to update the value of the REST Field.
					'schema'          => array(                                // The example_field REST schema.
						'description' => 'Messages Medias.',
						'type'        => 'object',
						'context'     => array( 'view', 'edit' ),
					),
				)
			);
		}

		if ( function_exists( 'bp_is_forums_document_support_enabled' ) && true === bp_is_forums_document_support_enabled() ) {
			// Topic Document Support.
			register_rest_field(
				'topics',
				'bbp_documents',
				array(
					'get_callback'    => array( $this, 'bbp_document_get_rest_field_callback' ),
					'update_callback' => array( $this, 'bbp_document_update_rest_field_callback' ),
					'schema'          => array(
						'description' => 'Topic Documentss.',
						'type'        => 'object',
						'context'     => array( 'embed', 'view', 'edit' ),
					),
				)
			);

			// Reply Document Support.
			register_rest_field(
				'reply',
				'bbp_documents',
				array(
					'get_callback'    => array( $this, 'bbp_document_get_rest_field_callback' ),
					'update_callback' => array( $this, 'bbp_document_update_rest_field_callback' ),
					'schema'          => array(
						'description' => 'Reply Documents.',
						'type'        => 'object',
						'context'     => array( 'embed', 'view', 'edit' ),
					),
				)
			);
		}

	}

	/**
	 * The function to use to get documents of the activity REST Field.
	 *
	 * @param BP_Activity_Activity $activity  Activity Array.
	 * @param string               $attribute The REST Field key used into the REST response.
	 *
	 * @return string            The value of the REST Field to include into the REST response.
	 */
	protected function bp_documents_get_rest_field_callback( $activity, $attribute ) {
		$activity_id = $activity['id'];

		if ( empty( $activity_id ) ) {
			return;
		}

		$document_ids = bp_activity_get_meta( $activity_id, 'bp_document_ids', true );
		$document_ids = trim( $document_ids );
		$document_ids = explode( ',', $document_ids );

		if ( empty( $document_ids ) ) {
			return;
		}

		$documents = $this->assemble_response_data( array( 'document_ids' => $document_ids ) );

		if ( empty( $documents['documents'] ) ) {
			return;
		}

		$retval = array();
		foreach ( $documents['documents'] as $document ) {
			$retval[] = $this->document_get_prepare_response( $document, array( 'support' => 'activity' ) );
		}

		return $retval;
	}

	/**
	 * The function to use to update the document's value of the activity REST Field.
	 * - from bp_document_update_activity_document_meta();
	 *
	 * @param object $object     The BuddyPress component's object that was just created/updated during the request.
	 *                           (in this case the BP_Activity_Activity object).
	 * @param object $value      The value of the REST Field to save.
	 * @param string $attribute  The REST Field key used into the REST response.
	 *
	 * @return object
	 */
	protected function bp_documents_update_rest_field_callback( $object, $value, $attribute ) {

		global $bp_activity_edit, $bp_activity_post_update_id, $bp_activity_post_update;

		if ( 'bp_documents' !== $attribute ) {
			$value->bp_documents = null;

			return $value;
		}

		$bp_activity_edit = ( isset( $value->edit ) ? true : false );
		// phpcs:ignore
		$_POST['edit'] = $bp_activity_edit;

		if ( false === $bp_activity_edit && empty( $object ) ) {
			return $value;
		}

		$activity_id = $value->id;
		$privacy     = $value->privacy;
		$group_id    = 0;

		$documents = wp_parse_id_list( $object );

		$old_document_ids      = bp_activity_get_meta( $activity_id, 'bp_document_ids', true );
		$old_document_ids      = ( ! empty( $old_document_ids ) ? explode( ',', $old_document_ids ) : array() );
		$new_documents         = array();
		$old_documents         = array();
		$old_documents_objects = array();

		if ( ! empty( $old_document_ids ) ) {
			foreach ( $old_document_ids as $id ) {
				$document_object = new BP_Document( $id );
				$old_documents_objects[ $document_object->attachment_id ] = $document_object;
				$old_documents[ $id ]                                     = $document_object->attachment_id;
			}
		}

		$bp_activity_post_update    = true;
		$bp_activity_post_update_id = $activity_id;

		if ( ! empty( $value->component ) && 'groups' === $value->component ) {
			$group_id = $value->item_id;
			$privacy  = 'grouponly';
		}

		if ( ! isset( $documents ) || empty( $documents ) ) {

			// delete document ids and meta for activity if empty document in request.
			// delete media ids and meta for activity if empty media in request.
			if ( ! empty( $activity_id ) && ! empty( $old_document_ids ) ) {
				foreach ( $old_document_ids as $document_id ) {
					bp_document_delete( array( 'id' => $document_id ), 'activity' );
				}
				bp_activity_delete_meta( $activity_id, 'bp_document_ids' );
			}

			return $value;
		} else {

			$order_count = 0;
			foreach ( $documents as $id ) {

				$wp_attachment_url = wp_get_attachment_url( $id );

				// when the file found to be empty it's means it's not a valid attachment.
				if ( empty( $wp_attachment_url ) ) {
					continue;
				}

				$order_count ++;

				if ( in_array( $id, $old_documents, true ) ) {
					$new_documents[] = array(
						'document_id' => $old_documents_objects[ $id ]->id,
					);
				} else {
					$new_documents[] = array(
						'id'         => $id,
						'name'       => get_the_title( $id ),
						'folder_id'  => 0,
						'group_id'   => $group_id,
						'menu_order' => $order_count,
						'privacy'    => $privacy,
						'error_type' => 'wp_error',
					);
				}
			}
		}

		remove_action( 'bp_activity_posted_update', 'bp_document_update_activity_document_meta', 10, 3 );
		remove_action( 'bp_groups_posted_update', 'bp_document_groups_activity_update_document_meta', 10, 4 );
		remove_action( 'bp_activity_comment_posted', 'bp_document_activity_comments_update_document_meta', 10, 3 );
		remove_action( 'bp_activity_comment_posted_notification_skipped', 'bp_document_activity_comments_update_document_meta', 10, 3 );

		$document_ids = bp_document_add_handler( $new_documents, $privacy, '', $group_id );

		add_action( 'bp_activity_posted_update', 'bp_document_update_activity_document_meta', 10, 3 );
		add_action( 'bp_groups_posted_update', 'bp_document_groups_activity_update_document_meta', 10, 4 );
		add_action( 'bp_activity_comment_posted', 'bp_document_activity_comments_update_document_meta', 10, 3 );
		add_action( 'bp_activity_comment_posted_notification_skipped', 'bp_document_activity_comments_update_document_meta', 10, 3 );

		// save document meta for activity.
		if ( ! empty( $activity_id ) ) {
			// Delete document if not exists in current document ids.

			if ( true === $bp_activity_edit ) {
				if ( ! empty( $old_document_ids ) ) {
					foreach ( $old_document_ids as $document_id ) {
						if ( ! in_array( (int) $document_id, $document_ids, true ) ) {
							bp_document_delete( array( 'id' => $document_id ) );
						}
					}
				}
			}
			bp_activity_update_meta( $activity_id, 'bp_document_ids', implode( ',', $document_ids ) );
		}
	}

	/**
	 * The function to use to get documents of the messages REST Field.
	 *
	 * @param array  $data      The message value for the REST response.
	 * @param string $attribute The REST Field key used into the REST response.
	 *
	 * @return string            The value of the REST Field to include into the REST response.
	 */
	protected function bp_documents_get_rest_field_callback_messages( $data, $attribute ) {
		$message_id = $data['id'];

		if ( empty( $message_id ) ) {
			return;
		}

		$document_ids = bp_messages_get_meta( $message_id, 'bp_document_ids', true );
		$document_ids = trim( $document_ids );
		$document_ids = explode( ',', $document_ids );

		if ( empty( $document_ids ) ) {
			return;
		}

		$documents = $this->assemble_response_data( array( 'document_ids' => $document_ids ) );

		if ( empty( $documents['documents'] ) ) {
			return;
		}

		$retval = array();
		foreach ( $documents['documents'] as $document ) {
			$retval[] = $this->document_get_prepare_response( $document, array( 'support' => 'message' ) );
		}

		return $retval;
	}

	/**
	 * The function to use to update the documents value of the messages REST Field.
	 *
	 * @param object $object     The BuddyPress component's object that was just created/updated during the request.
	 *                           (in this case the BP_Messages_Message object).
	 * @param object $value      The value of the REST Field to save.
	 * @param string $attribute  The REST Field key used into the REST response.
	 *
	 * @return object
	 */
	protected function bp_documents_update_rest_field_callback_messages( $object, $value, $attribute ) {

		if ( 'bp_documents' !== $attribute || empty( $object ) ) {
			$value->bp_documents = null;
			return $value;
		}

		$message_id = $value->id;

		$thread_id = $value->thread_id;

		$documents = wp_parse_id_list( $object );

		if ( empty( $documents ) ) {
			$value->bp_documents = null;
			return $value;
		}

		$args = array(
			'document_ids' => $documents,
			'privacy'      => 'message',
		);

		$document_ids = $this->bp_rest_create_document( $args );

		if ( is_wp_error( $document_ids ) ) {
			$value->bp_documents = $document_ids;
			return $value;
		}

		if ( ! empty( $document_ids ) ) {
			foreach ( $document_ids as $id ) {
				bp_document_add_meta( $id, 'thread_id', $thread_id );
			}
		}

		bp_messages_update_meta( $message_id, 'bp_document_ids', implode( ',', $document_ids ) );
	}

	/**
	 * The function to use to get documents of the topic/reply REST Field.
	 *
	 * @param array  $post      WP_Post object as array.
	 * @param string $attribute The REST Field key used into the REST response.
	 *
	 * @return string            The value of the REST Field to include into the REST response.
	 */
	protected function bbp_document_get_rest_field_callback( $post, $attribute ) {

		$p_id = $post['id'];

		if ( empty( $p_id ) ) {
			return;
		}

		$document_ids = get_post_meta( $p_id, 'bp_document_ids', true );
		$document_ids = trim( $document_ids );
		$document_ids = explode( ',', $document_ids );

		if ( empty( $document_ids ) ) {
			return;
		}

		$documents = $this->assemble_response_data( array( 'document_ids' => $document_ids ) );

		if ( empty( $documents['documents'] ) ) {
			return;
		}

		$retval = array();
		foreach ( $documents['documents'] as $document ) {
			$retval[] = $this->document_get_prepare_response( $document, array( 'support' => 'forums' ) );
		}

		return $retval;
	}

	/**
	 * The function to use to update the document's value of the topic REST Field.
	 * - from bp_document_forums_new_post_document_save();
	 *
	 * @param object $object     Value for the schema.
	 * @param object $value      The value of the REST Field to save.
	 *
	 * @return object
	 */
	protected function bbp_document_update_rest_field_callback( $object, $value ) {

		$documents = wp_parse_id_list( $object );
		if ( empty( $documents ) ) {
			$value->bbp_documents = null;

			return $value;
		}

		$post_id = $value->ID;

		$reply_id = 0;
		$topic_id = 0;
		$forum_id = 0;

		// save activity id if it is saved in forums and enabled in platform settings.
		$main_activity_id = get_post_meta( $post_id, '_bbp_activity_id', true );

		// fetch currently uploaded document ids.
		$existing_document_ids            = get_post_meta( $post_id, 'bp_document_ids', true );
		$existing_document_attachment_ids = array();
		$existing_document_attachments    = array();

		if ( ! empty( $existing_document_ids ) ) {
			$existing_document_ids = explode( ',', $existing_document_ids );

			foreach ( $existing_document_ids as $existing_document_id ) {
				$existing_document_object = new BP_Document( $existing_document_id );

				if ( ! empty( $existing_document_object->attachment_id ) ) {
					$existing_document_attachment_ids[]                     = $existing_document_object->attachment_id;
					$existing_document_attachments[ $existing_document_id ] = $existing_document_object->attachment_id;
				}
			}
		}

		$document_ids     = array();
		$menu_order_count = 0;

		if ( ! empty( $documents ) ) {
			foreach ( $documents as $document ) {

				$wp_attachment_url = wp_get_attachment_url( $document );

				// when the file found to be empty it's means it's not a valid attachment.
				if ( empty( $wp_attachment_url ) ) {
					continue;
				}

				$menu_order_count ++;

				$attachment_id = ! empty( $document ) ? $document : 0;
				$menu_order    = $menu_order_count;

				if ( ! empty( $existing_document_attachment_ids ) ) {
					$index = array_search( $attachment_id, $existing_document_attachment_ids, true );
					if ( ! empty( $attachment_id ) && false !== $index ) {
						$exisiting_document_id    = array_search( $attachment_id, $existing_document_attachments, true );
						$existing_document_update = new BP_Document( $exisiting_document_id );

						$existing_document_update->menu_order = $menu_order;
						$existing_document_update->save();

						unset( $existing_document_ids[ $index ] );
						$document_ids[] = $exisiting_document_id;
						continue;
					}
				}

				if ( 0 === $reply_id && bbp_get_reply_post_type() === get_post_type( $post_id ) ) {
					$reply_id = $post_id;
					$topic_id = bbp_get_reply_topic_id( $reply_id );
					$forum_id = bbp_get_topic_forum_id( $topic_id );
				} elseif ( 0 === $topic_id && bbp_get_topic_post_type() === get_post_type( $post_id ) ) {
					$topic_id = $post_id;
					$forum_id = bbp_get_topic_forum_id( $topic_id );
				} elseif ( 0 === $forum_id && bbp_get_forum_post_type() === get_post_type( $post_id ) ) {
					$forum_id = $post_id;
				}

				// extract the nice title name.
				$title     = get_the_title( $attachment_id );
				$file      = get_attached_file( $attachment_id );
				$file_type = wp_check_filetype( $file );
				$file_name = basename( $file );

				$document_id = bp_document_add(
					array(
						'attachment_id' => $attachment_id,
						'title'         => $title,
						'folder_id'     => 0,
						'group_id'      => 0,
						'privacy'       => 'forums',
						'error_type'    => 'wp_error',
						'menu_order'    => $menu_order,
					)
				);

				if ( ! is_wp_error( $document_id ) && ! empty( $document_id ) ) {
					$document_ids[] = $document_id;

					// save document meta.
					bp_document_update_meta( $document_id, 'forum_id', $forum_id );
					bp_document_update_meta( $document_id, 'topic_id', $topic_id );
					bp_document_update_meta( $document_id, 'reply_id', $reply_id );
					bp_document_update_meta( $document_id, 'file_name', $file_name );
					bp_document_update_meta( $document_id, 'extension', '.' . $file_type['ext'] );

					// save document is saved in attachment.
					update_post_meta( $attachment_id, 'bp_document_saved', true );
				}
			}
		}

		$document_ids = implode( ',', $document_ids );

		// Save all attachment ids in forums post meta.
		update_post_meta( $post_id, 'bp_document_ids', $document_ids );

		// save document meta for activity.
		if ( ! empty( $main_activity_id ) && bp_is_active( 'activity' ) ) {
			bp_activity_update_meta( $main_activity_id, 'bp_document_ids', $document_ids );
		}

		// delete documents which were not saved or removed from form.
		if ( ! empty( $existing_document_ids ) ) {
			foreach ( $existing_document_ids as $document_id ) {
				bp_document_delete( array( 'id' => $document_id ) );
			}
		}

	}

}