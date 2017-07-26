<?php
/**
 * Form edit page handler class
 *
 * @package TorroForms
 * @since 1.0.0
 */

namespace awsmug\Torro_Forms\DB_Objects\Forms;

use awsmug\Torro_Forms\Assets;
use Leaves_And_Love\Plugin_Lib\Fields\Field_Manager;
use WP_Post;
use WP_Error;

/**
 * Class for handling form edit page behavior.
 *
 * @since 1.0.0
 */
class Form_Edit_Page_Handler {

	/**
	 * Form manager instance.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @var Form_Manager
	 */
	protected $form_manager;

	/**
	 * Array of meta boxes as `$id => $args` pairs.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @var array
	 */
	protected $meta_boxes = array();

	/**
	 * Array of tabs as `$id => $args` pairs.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @var array
	 */
	protected $tabs = array();

	/**
	 * Current form storage.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @var Form|null
	 */
	protected $current_form = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param Form_Manager $form_manager Form manager instance.
	 */
	public function __construct( $form_manager ) {
		$this->form_manager = $form_manager;
	}

	/**
	 * Adds a meta box to the edit page.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param string $id   Meta box identifier.
	 * @param array  $args {
	 *     Optional. Meta box arguments.
	 *
	 *     @type string $title       Meta box title.
	 *     @type string $description Meta box description.
	 *     @type string $context     Meta box content. Either 'normal', 'advanced' or 'side'. Default 'advanced'.
	 *     @type string $priority    Meta box priority. Either 'high', 'core', 'default' or 'low'. Default 'default'.
	 * }
	 */
	public function add_meta_box( $id, $args ) {
		$prefix = $this->form_manager->get_prefix();

		if ( 0 !== strpos( $id, $prefix ) ) {
			$id = $prefix . $id;
		}

		$this->meta_boxes[ $id ] = wp_parse_args( $args, array(
			'title'       => '',
			'description' => '',
			'content'     => 'advanced',
			'priority'    => 'default',
		) );

		$services = array(
			'ajax'          => $this->form_manager->ajax(),
			'assets'        => $this->form_manager->assets(),
			'error_handler' => $this->form_manager->error_handler(),
		);

		$this->meta_boxes[ $id ]['field_manager'] = new Field_Manager( $prefix, $services, array(
			'get_value_callback'         => array( $this, 'get_meta_values' ),
			'get_value_callback_args'    => array( $id ),
			'update_value_callback'      => array( $this, 'update_meta_values' ),
			'update_value_callback_args' => array( $id, '{value}' ),
			'name_prefix'                => $id,
			'render_mode'                => 'form-table',
		) );
	}

	/**
	 * Adds a tab to the edit page.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param string $id   Tab identifier.
	 * @param array  $args {
	 *     Optional. Tab arguments.
	 *
	 *     @type string $title       Tab title.
	 *     @type string $description Tab description.
	 *     @type string $meta_box    Identifier of the meta box this tab should belong to.
	 * }
	 */
	public function add_tab( $id, $args ) {
		if ( ! empty( $args['meta_box'] ) ) {
			$prefix = $this->form_manager->get_prefix();

			if ( 0 !== strpos( $args['meta_box'], $prefix ) ) {
				$args['meta_box'] = $prefix . $args['meta_box'];
			}
		}

		$this->tabs[ $id ] = wp_parse_args( $args, array(
			'title'       => '',
			'description' => '',
			'meta_box'    => '',
		) );
	}

	/**
	 * Adds a field to the edit page.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param string $id      Field identifier.
	 * @param string $type    Identifier of the type.
	 * @param array  $args    {
	 *     Optional. Field arguments. See the field class constructor for further arguments.
	 *
	 *     @type string $tab           Tab identifier this field belongs to. Default empty.
	 *     @type string $label         Field label. Default empty.
	 *     @type string $description   Field description. Default empty.
	 *     @type mixed  $default       Default value for the field. Default null.
	 *     @type array  $input_classes Array of CSS classes for the field input. Default empty array.
	 *     @type array  $label_classes Array of CSS classes for the field label. Default empty array.
	 *     @type array  $input_attrs   Array of additional input attributes as `$key => $value` pairs.
	 *                                 Default empty array.
	 * }
	 */
	public function add_field( $id, $type, $args = array() ) {
		if ( isset( $args['tab'] ) ) {
			$args['section'] = $args['tab'];
			unset( $args['tab'] );
		}

		if ( ! isset( $args['section'] ) ) {
			return;
		}

		if ( ! isset( $this->tabs[ $args['section'] ] ) ) {
			return;
		}

		if ( ! isset( $this->meta_boxes[ $this->tabs[ $args['section'] ]['meta_box'] ] ) ) {
			return;
		}

		$meta_box_args = $this->meta_boxes[ $this->tabs[ $args['section'] ]['meta_box'] ];
		$meta_box_args['field_manager']->add( $id, $type, $args );
	}

	/**
	 * Renders form canvas if conditions are met.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param WP_Post $post Current post.
	 */
	public function maybe_render_form_canvas( $post ) {
		$form = $this->form_manager->get( $post->ID );
		if ( ! $form ) {
			return;
		}

		$this->render_form_canvas( $form );
	}

	/**
	 * Adds meta boxes if conditions are met.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param WP_Post $post Current post.
	 */
	public function maybe_add_meta_boxes( $post ) {
		$form = $this->form_manager->get( $post->ID );
		if ( ! $form ) {
			return;
		}

		$this->add_meta_boxes( $form );
	}

	/**
	 * Enqueues assets to load if conditions are met.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param string $hook_suffix Current hook suffix.
	 */
	public function maybe_enqueue_assets( $hook_suffix ) {
		if ( 'post-new.php' !== $hook_suffix && 'post.php' !== $hook_suffix ) {
			return;
		}

		$target_post_type = $this->form_manager->get_prefix() . $this->form_manager->get_singular_slug();

		if ( empty( $_GET['post_type'] ) || $target_post_type !== $_GET['post_type'] ) {
			if ( empty( $_GET['post'] ) || get_post_type( $_GET['post'] ) !== $target_post_type ) {
				return;
			}
		}

		$this->enqueue_assets();
	}

	/**
	 * Prints templates if conditions are met.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function maybe_print_templates() {
		$target_post_type = $this->form_manager->get_prefix() . $this->form_manager->get_singular_slug();

		if ( empty( $_GET['post_type'] ) || $target_post_type !== $_GET['post_type'] ) {
			if ( empty( $_GET['post'] ) || get_post_type( $_GET['post'] ) !== $target_post_type ) {
				return;
			}
		}

		$this->print_templates();
	}

	/**
	 * Handles a save request if conditions are met.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param int $post_id Current post ID.
	 */
	public function maybe_handle_save_request( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( is_multisite() && ms_is_switched() ) {
			return;
		}

		$form = $this->form_manager->get( $post_id );
		if ( ! $form ) {
			return;
		}

		$this->handle_save_request( $form );
	}

	/**
	 * Callback to get meta values for a specific meta box identifier.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param string $meta_box_id Meta box identifier.
	 * @return array Meta values stored for the meta box.
	 */
	public function get_meta_values( $meta_box_id ) {
		if ( ! $this->current_form ) {
			return array();
		}

		return $this->form_manager->get_meta( $this->current_form->id, $meta_box_id, true );
	}

	/**
	 * Callback to update meta values for a specific meta box identifier.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param string $meta_box_id Meta box identifier.
	 * @param array  $values      Meta values to store for the meta box.
	 */
	public function update_meta_values( $meta_box_id, $values ) {
		if ( ! $this->current_form ) {
			return;
		}

		$this->form_manager->update_meta( $this->current_form->id, $meta_box_id, $values );
	}

	/**
	 * Renders form canvas.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param Form $form Current form.
	 */
	private function render_form_canvas( $form ) {
		?>
		<div id="torro-form-canvas" class="torro-form-canvas">
			<div class="torro-form-canvas-header torro-form-canvas-tabs" role="tablist">
				<button type="button" class="torro-form-canvas-tab" aria-selected="true" disabled="disabled">
					<span aria-hidden="true">+</span><span class="screen-reader-text"><?php _e( 'Add New Container', 'torro-forms' ); ?></span>
				</button>
			</div>
			<div class="torro-form-canvas-content">
				<div class="drag-drop-area is-empty">
					<div class="content loader-content hide-if-no-js">
						<?php _e( 'Loading form builder...', 'torro-forms' ); ?>
						<span class="spinner is-active"></span>
					</div>
					<div class="torro-notice notice-warning hide-if-js">
						<p>
							<?php _e( 'It seems you have disabled JavaScript in your browser. Torro Forms requires JavaScript in order to edit your forms.', 'torro-forms' ); ?>
						</p>
					</div>
				</div>
			</div>
			<div class="torro-form-canvas-footer"></div>
		</div>
		<?php
	}

	/**
	 * Adds meta boxes to the page.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param Form $form Current form.
	 */
	private function add_meta_boxes( $form ) {
		$this->current_form = $form;

		if ( ! did_action( "{$this->form_manager->get_prefix()}add_form_meta_content" ) ) {
			/**
			 * Fires when meta boxes for the form edit page should be added.
			 *
			 * @since 1.0.0
			 *
			 * @param Form_Edit_Page_Handler $edit_page Form edit page.
			 */
			do_action( "{$this->form_manager->get_prefix()}add_form_meta_content", $this );
		}

		foreach ( $this->meta_boxes as $id => $args ) {
			add_meta_box( $id, $args['title'], function( $post, $box ) {
				if ( ! empty( $box['args']['description'] ) ) {
					echo '<p class="description">' . $box['args']['description'] . '</p>';
				}

				$tab_id_prefix      = 'metabox-' . $box['id'] . '-tab-';
				$tabpanel_id_prefix = 'metabox-' . $box['id'] . '-tabpanel-';

				$tabs = wp_list_filter( $this->tabs, array( 'meta_box' => $box['id'] ) );

				$first = true;
				?>
				<h3 class="torro-metabox-tab-wrapper" role="tablist">
					<?php foreach ( $tabs as $id => $args ) : ?>
						<a id="<?php echo esc_attr( $tab_id_prefix . $id ); ?>" class="torro-metabox-tab" href="<?php echo esc_attr( '#' . $tabpanel_id_prefix . $id ); ?>" aria-controls="<?php echo esc_attr( $tabpanel_id_prefix . $id ); ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>" role="tab">
							<?php echo $args['title']; ?>
						</a>
						<?php $first = false; ?>
					<?php endforeach; ?>
				</h3>
				<?php $first = true; ?>
				<?php foreach ( $tabs as $id => $args ) : ?>
					<div id="<?php echo esc_attr( $tabpanel_id_prefix . $id ); ?>" class="torro-metabox-tab-panel" aria-labelledby="<?php echo esc_attr( $tab_id_prefix . $id ); ?>" aria-hidden="<?php echo $first ? 'false' : 'true'; ?>" role="tabpanel">
						<?php if ( ! empty( $args['description'] ) ) : ?>
							<p class="description"><?php echo $args['description']; ?></p>
						<?php endif; ?>
						<table class="form-table">
							<?php $box['args']['field_manager']->render( $id ); ?>
						</table>
					</div>
					<?php $first = true; ?>
				<?php endforeach; ?>
			<?php
			}, null, $args['context'], $args['priority'], $args );
		}

		/**
		 * Fires when meta boxes for the form edit page should be added.
		 *
		 * @since 1.0.0
		 *
		 * @param Form $form Form that is being edited.
		 */
		do_action( "{$this->form_manager->get_prefix()}add_form_meta_boxes", $form );
	}

	/**
	 * Enqueues assets to load on the page.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function enqueue_assets() {
		$this->form_manager->assets()->enqueue_script( 'admin-form-builder' );
		$this->form_manager->assets()->enqueue_style( 'admin-form-builder' );

		if ( ! did_action( "{$this->form_manager->get_prefix()}add_form_meta_content" ) ) {
			/** This action is documented in src/db-objects/forms/form-edit-page-handler.php */
			do_action( "{$this->form_manager->get_prefix()}add_form_meta_content", $this );
		}

		foreach ( $this->meta_boxes as $id => $args ) {
			$args['field_manager']->enqueue();
		}

		/**
		 * Fires after scripts and stylesheets for the form builder have been enqueued.
		 *
		 * @since 1.0.0
		 *
		 * @param Assets $assets The Assets API instance.
		 */
		do_action( "{$this->form_manager->get_prefix()}enqueue_form_builder_scripts", $this->form_manager->assets() );
	}

	/**
	 * Prints templates to use in JavaScript.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function print_templates() {
		?>
		<script type="text/html" id="tmpl-torro-failure">
			<div class="torro-notice notice-error">
				<p>
					<strong><?php _e( 'Error:', 'torro-forms' ); ?></strong>
					{{ data.message }}
				</p>
			</div>
		</script>

		<script type="text/html" id="tmpl-torro-form-canvas">
			<div class="torro-form-canvas-header torro-form-canvas-tabs torro-js-container-tabs"></div>
			<div class="torro-form-canvas-content torro-js-container-panels"></div>
			<div class="torro-form-canvas-footer torro-js-container-footer-panels"></div>
		</script>

		<script type="text/html" id="tmpl-torro-container-tab">
			<span>{{ data.label }}</span>
			<input type="hidden" name="<?php echo $this->form_manager->get_prefix(); ?>containers[{{ data.id }}][label]" value="{{ data.label }}" />
		</script>

		<script type="text/html" id="tmpl-torro-container-tab-button">
			<button type="button" class="torro-form-canvas-tab add-button">
				<span aria-hidden="true">+</span><span class="screen-reader-text"><?php _e( 'Add New Container', 'torro-forms' ); ?></span>
			</button>
		</script>

		<script type="text/html" id="tmpl-torro-empty-container-drag-drop">
			<div class="content"><?php _e( 'Click the button above to add your first container', 'torro-forms' ); ?></div>
		</script>

		<script type="text/html" id="tmpl-torro-container-panel">
			<div class="drag-drop-area torro-js-elements"></div>

			<input type="hidden" name="<?php echo $this->form_manager->get_prefix(); ?>containers[{{ data.id }}][form_id]" value="{{ data.form_id }}" />
			<input type="hidden" name="<?php echo $this->form_manager->get_prefix(); ?>containers[{{ data.id }}][sort]" value="{{ data.sort }}" />
		</script>

		<script type="text/html" id="tmpl-torro-container-footer-panel">
			<button type="button" class="button-link button-link-delete delete-container-button"><?php _e( 'Delete Page', 'torro-forms' ); ?></button>
		</script>

		<script type="text/html" id="tmpl-torro-empty-element-drag-drop">
			<div class="content"><?php _e( 'Drop your elements here', 'torro-forms' ); ?></div>
		</script>
		<?php

		/**
		 * Fires after templates for the form builder have been printed.
		 *
		 * @since 1.0.0
		 */
		do_action( "{$this->form_manager->get_prefix()}print_form_builder_templates" );
	}

	/**
	 * Handles a save request for the page.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param Form $form Current form.
	 */
	private function handle_save_request( $form ) {
		$this->current_form = $form;

		$mappings = array(
			'forms'            => array(
				$form->id => $form->id,
			),
			'containers'       => array(),
			'elements'         => array(),
			'element_choices'  => array(),
			'element_settings' => array(),
		);

		$errors = new WP_Error();

		if ( isset( $_POST[ $this->form_manager->get_prefix() . 'containers' ] ) ) {
			$mappings = $this->save_containers( wp_unslash( $_POST[ $this->form_manager->get_prefix() . 'containers' ] ), $mappings, $errors );
		}

		if ( isset( $_POST[ $this->form_manager->get_prefix() . 'elements' ] ) ) {
			$mappings = $this->save_elements( wp_unslash( $_POST[ $this->form_manager->get_prefix() . 'elements' ] ), $mappings, $errors );
		}

		if ( isset( $_POST[ $this->form_manager->get_prefix() . 'element_choices' ] ) ) {
			$mappings = $this->save_element_choices( wp_unslash( $_POST[ $this->form_manager->get_prefix() . 'element_choices' ] ), $mappings, $errors );
		}

		if ( isset( $_POST[ $this->form_manager->get_prefix() . 'element_settings' ] ) ) {
			$mappings = $this->save_element_settings( wp_unslash( $_POST[ $this->form_manager->get_prefix() . 'element_settings' ] ), $mappings, $errors );
		}

		if ( isset( $_POST[ $this->form_manager->get_prefix() . 'deleted_containers' ] ) ) {
			$this->delete_containers( array_map( 'absint', $_POST[ $this->form_manager->get_prefix() . 'deleted_containers' ] ) );
		}

		if ( isset( $_POST[ $this->form_manager->get_prefix() . 'deleted_elements' ] ) ) {
			$this->delete_elements( array_map( 'absint', $_POST[ $this->form_manager->get_prefix() . 'deleted_elements' ] ) );
		}

		if ( isset( $_POST[ $this->form_manager->get_prefix() . 'deleted_element_choices' ] ) ) {
			$this->delete_element_choices( array_map( 'absint', $_POST[ $this->form_manager->get_prefix() . 'deleted_element_choices' ] ) );
		}

		if ( isset( $_POST[ $this->form_manager->get_prefix() . 'deleted_element_settings' ] ) ) {
			$this->delete_element_settings( array_map( 'absint', $_POST[ $this->form_manager->get_prefix() . 'deleted_element_settings' ] ) );
		}

		if ( ! did_action( "{$this->form_manager->get_prefix()}add_form_meta_content" ) ) {
			/** This action is documented in src/db-objects/forms/form-edit-page-handler.php */
			do_action( "{$this->form_manager->get_prefix()}add_form_meta_content", $this );
		}

		foreach ( $this->meta_boxes as $id => $args ) {
			if ( isset( $_POST[ $id ] ) ) {
				// TODO: Figure out how to deal with errors.
				$args['field_manager']->update_values( wp_unslash( $_POST[ $id ] ) );
			}
		}

		/**
		 * Fires after a form has been saved.
		 *
		 * @since 1.0.0
		 *
		 * @param Form $form Form that has been saved.
		 */
		do_action( "{$this->form_manager->get_prefix()}save_form", $form );
	}

	/**
	 * Saves containers.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param array    $containers Array of `$container_id => $container_data` pairs.
	 * @param array    $mappings   Array of mappings to pass-through and modify.
	 * @param WP_Error $errors     Error object to append errors to.
	 * @return array Modified mappings.
	 */
	private function save_containers( $containers, $mappings, $errors ) {
		$container_manager = $this->form_manager->get_child_manager( 'containers' );

		foreach ( $containers as $id => $data ) {
			$data['form_id'] = key( $mappings['forms'] );

			if ( $this->is_temp_id( $id ) ) {
				$container = $container_manager->create();
			} else {
				$container = $container_manager->get( $id );
				if ( ! $container ) {
					$container = $container_manager->create();
				}
			}

			foreach ( $data as $key => $value ) {
				$container->$key = $value;
			}

			$status = $container->sync_upstream();
			if ( is_wp_error( $status ) ) {
				$errors->add( $status->get_error_code(), $status->get_error_message(), array(
					'id'   => $id,
					'data' => $data,
				) );
			} else {
				$mappings['containers'][ $id ] = $container->id;
			}
		}

		return $mappings;
	}

	/**
	 * Saves elements.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param array    $elements Array of `$element_id => $element_data` pairs.
	 * @param array    $mappings Array of mappings to pass-through and modify.
	 * @param WP_Error $errors   Error object to append errors to.
	 * @return array Modified mappings.
	 */
	private function save_elements( $elements, $mappings, $errors ) {
		$element_manager = $this->form_manager->get_child_manager( 'containers' )->get_child_manager( 'elements' );

		foreach ( $elements as $id => $data ) {
			if ( empty( $data['container_id'] ) || ! isset( $mappings['containers'][ $data['container_id'] ] ) ) {
				continue;
			}

			$data['container_id'] = $mappings['containers'][ $data['container_id'] ];

			if ( $this->is_temp_id( $id ) ) {
				$element = $element_manager->create();
			} else {
				$element = $element_manager->get( $id );
				if ( ! $element ) {
					$element = $element_manager->create();
				}
			}

			foreach ( $data as $key => $value ) {
				$element->$key = $value;
			}

			$status = $element->sync_upstream();
			if ( is_wp_error( $status ) ) {
				$errors->add( $status->get_error_code(), $status->get_error_message(), array(
					'id'   => $id,
					'data' => $data,
				) );
			} else {
				$mappings['elements'][ $id ] = $element->id;
			}
		}

		return $mappings;
	}

	/**
	 * Saves element choices.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param array    $element_choices Array of `$element_choice_id => $element_choice_data` pairs.
	 * @param array    $mappings        Array of mappings to pass-through and modify.
	 * @param WP_Error $errors          Error object to append errors to.
	 * @return array Modified mappings.
	 */
	private function save_element_choices( $element_choices, $mappings, $errors ) {
		$element_choice_manager = $this->form_manager->get_child_manager( 'containers' )->get_child_manager( 'elements' )->get_child_manager( 'element_choices' );

		foreach ( $element_choices as $id => $data ) {
			if ( empty( $data['element_id'] ) || ! isset( $mappings['elements'][ $data['element_id'] ] ) ) {
				continue;
			}

			$data['element_id'] = $mappings['elements'][ $data['element_id'] ];

			if ( $this->is_temp_id( $id ) ) {
				$element_choice = $element_choice_manager->create();
			} else {
				$element_choice = $element_choice_manager->get( $id );
				if ( ! $element_choice ) {
					$element_choice = $element_choice_manager->create();
				}
			}

			foreach ( $data as $key => $value ) {
				$element_choice->$key = $value;
			}

			$status = $element_choice->sync_upstream();
			if ( is_wp_error( $status ) ) {
				$errors->add( $status->get_error_code(), $status->get_error_message(), array(
					'id'   => $id,
					'data' => $data,
				) );
			} else {
				$mappings['element_choices'][ $id ] = $element_choice->id;
			}
		}

		return $mappings;
	}

	/**
	 * Saves element settings.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param array    $element_settings Array of `$element_setting_id => $element_setting_data` pairs.
	 * @param array    $mappings        Array of mappings to pass-through and modify.
	 * @param WP_Error $errors          Error object to append errors to.
	 * @return array Modified mappings.
	 */
	private function save_element_settings( $element_settings, $mappings, $errors ) {
		$element_setting_manager = $this->form_manager->get_child_manager( 'containers' )->get_child_manager( 'elements' )->get_child_manager( 'element_settings' );

		foreach ( $element_settings as $id => $data ) {
			if ( empty( $data['element_id'] ) || ! isset( $mappings['elements'][ $data['element_id'] ] ) ) {
				continue;
			}

			$data['element_id'] = $mappings['elements'][ $data['element_id'] ];

			if ( $this->is_temp_id( $id ) ) {
				$element_setting = $element_setting_manager->create();
			} else {
				$element_setting = $element_setting_manager->get( $id );
				if ( ! $element_setting ) {
					$element_setting = $element_setting_manager->create();
				}
			}

			foreach ( $data as $key => $value ) {
				$element_setting->$key = $value;
			}

			$status = $element_setting->sync_upstream();
			if ( is_wp_error( $status ) ) {
				$errors->add( $status->get_error_code(), $status->get_error_message(), array(
					'id'   => $id,
					'data' => $data,
				) );
			} else {
				$mappings['element_settings'][ $id ] = $element_setting->id;
			}
		}

		return $mappings;
	}

	/**
	 * Deletes containers with specific IDs.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param array $container_ids Array of container IDs.
	 */
	private function delete_containers( $container_ids ) {
		$container_manager = $this->form_manager->get_child_manager( 'containers' );

		foreach ( $container_ids as $container_id ) {
			$container = $container_manager->get( $container_id );
			if ( ! $container ) {
				continue;
			}

			$container->delete();
		}
	}

	/**
	 * Deletes elements with specific IDs.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param array $element_ids Array of element IDs.
	 */
	private function delete_elements( $element_ids ) {
		$element_manager = $this->form_manager->get_child_manager( 'containers' )->get_child_manager( 'elements' );

		foreach ( $element_ids as $element_id ) {
			$element = $element_manager->get( $element_id );
			if ( ! $element ) {
				continue;
			}

			$element->delete();
		}
	}

	/**
	 * Deletes element choices with specific IDs.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param array $element_choice_ids Array of element choice IDs.
	 */
	private function delete_element_choices( $element_choice_ids ) {
		$element_choice_manager = $this->form_manager->get_child_manager( 'containers' )->get_child_manager( 'elements' )->get_child_manager( 'element_choices' );

		foreach ( $element_choice_ids as $element_choice_id ) {
			$element_choice = $element_choice_manager->get( $element_choice_id );
			if ( ! $element_choice ) {
				continue;
			}

			$element_choice->delete();
		}
	}

	/**
	 * Deletes element settings with specific IDs.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param array $element_setting_ids Array of element setting IDs.
	 */
	private function delete_element_settings( $element_setting_ids ) {
		$element_setting_manager = $this->form_manager->get_child_manager( 'containers' )->get_child_manager( 'elements' )->get_child_manager( 'element_settings' );

		foreach ( $element_setting_ids as $element_setting_id ) {
			$element_setting = $element_setting_manager->get( $element_setting_id );
			if ( ! $element_setting ) {
				continue;
			}

			$element_setting->delete();
		}
	}

	/**
	 * Checks whether a specific ID is a temporary ID.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param int $id Component ID.
	 * @return bool True if temporary ID, false otherwise.
	 */
	private function is_temp_id( $id ) {
		return is_string( $id ) && 'temp_id_' === substr( $id, 0, 8 );
	}
}
