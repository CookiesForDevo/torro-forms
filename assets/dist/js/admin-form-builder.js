/*!
 * Torro Forms Version 1.0.0-beta.8 (http://torro-forms.com)
 * Licensed under GNU General Public License v3 (http://www.gnu.org/licenses/gpl-3.0.html)
 */
window.torro = window.torro || {};

( function( torro, $, _, i18n ) {
	'use strict';

	var instanceCount = 0,
		initialized = [],
		callbacks = {},
		builder;

	/**
	 * A form builder instance.
	 *
	 * @class
	 *
	 * @param {string} selector DOM selector for the wrapping element for the UI.
	 */
	function Builder( selector ) {
		instanceCount++;
		callbacks[ 'builder' + instanceCount ] = [];

		this.instanceNumber = instanceCount;

		this.$el = $( selector );
	}

	_.extend( Builder.prototype, {

		/**
		 * Available element types.
		 *
		 * @since 1.0.0
		 * @access public
		 * @type {torro.Builder.ElementTypes}
		 */
		elementTypes: undefined,

		/**
		 * Current form model.
		 *
		 * @since 1.0.0
		 * @access public
		 * @type {torro.Builder.FormModel}
		 */
		form: undefined,

		/**
		 * View object.
		 *
		 * @since 1.0.0
		 * @access public
		 * @type {torro.Builder.View}
		 */
		view: undefined,

		/**
		 * Initializes the form builder.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		init: function() {
			if ( ! this.$el.length ) {
				console.error( i18n.couldNotInitCanvas );
				return;
			}

			torro.api.init()
				.done( _.bind( function() {
					( new torro.api.collections.ElementTypes() ).fetch({
						data: {
							context: 'edit'
						},
						context: this,
						success: function( elementTypes ) {
							this.elementTypes = torro.Builder.ElementTypes.fromApiCollection( elementTypes );

							if ( 'auto-draft' !== $( '#original_post_status' ).val() ) {
								( new torro.api.models.Form({
									id: parseInt( $( '#post_ID' ).val(), 10 )
								}) ).fetch({
									data: {
										context: 'edit',
										_embed: true
									},
									context: this,
									success: function( form ) {
										$( document ).ready( _.bind( function() {
											var i;

											initialized.push( this.instanceCount );

											this.setupInitialData( form.attributes );
											this.setupViews();

											for ( i in callbacks[ 'builder' + this.instanceCount ] ) {
												callbacks[ 'builder' + this.instanceCount ][ i ]( this );
											}

											delete callbacks[ 'builder' + this.instanceCount ];
										}, this ) );
									},
									error: function() {
										$( document ).ready( _.bind( function() {
											this.fail( i18n.couldNotLoadData );
										}, this ) );
									}
								});
							} else {
								$( document ).ready( _.bind( function() {
									var i;

									initialized.push( this.instanceCount );

									this.setupInitialData();
									this.setupViews();

									for ( i in callbacks[ 'builder' + this.instanceCount ] ) {
										callbacks[ 'builder' + this.instanceCount ][ i ]( this );
									}

									delete callbacks[ 'builder' + this.instanceCount ];
								}, this ) );
							}
						},
						error: function() {
							$( document ).ready( _.bind( function() {
								this.fail( i18n.couldNotLoadData );
							}, this ) );
						}
					});
				}, this ) )
				.fail( _.bind( function() {
					$( document ).ready( _.bind( function() {
						this.fail( i18n.couldNotLoadData );
					}, this ) );
				}, this ) );
		},

		/**
		 * Sets up initial data for the form builder.
		 *
		 * This method only works if the form builder has been initialized.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param {object|undefined} form REST API form response including embedded data, or
		 *                                undefined if this is a new form.
		 */
		setupInitialData: function( form ) {
			var container, element, elementChoice, elementSetting, elementParents, i;

			if ( ! _.contains( initialized, this.instanceCount ) ) {
				return;
			}

			if ( form ) {
				this.form = new torro.Builder.FormModel( form, {
					container_label_placeholder: i18n.defaultContainerLabel
				});

				if ( form._embedded.containers && form._embedded.containers[0] ) {
					this.form.containers.add( form._embedded.containers[0] );

					if ( form._embedded.elements && form._embedded.elements[0] ) {
						elementParents = {};

						for ( i = 0; i < form._embedded.elements[0].length; i++ ) {
							element = form._embedded.elements[0][ i ];

							container = this.form.containers.get( element.container_id );
							if ( container ) {
								container.elements.add( element );

								elementParents[ element.id ] = element.container_id;
							}
						}

						if ( form._embedded.element_choices && form._embedded.element_choices[0] ) {
							for ( i = 0; i < form._embedded.element_choices[0].length; i++ ) {
								elementChoice = form._embedded.element_choices[0][ i ];

								if ( elementParents[ elementChoice.element_id ] ) {
									container = this.form.containers.get( elementParents[ elementChoice.element_id ] );
									if ( container ) {
										element = container.elements.get( elementChoice.element_id );
										if ( element ) {
											element.element_choices.add( elementChoice );
										}
									}
								}
							}
						}

						if ( form._embedded.element_settings && form._embedded.element_settings[0] ) {
							for ( i = 0; i < form._embedded.element_settings[0].length; i++ ) {
								elementSetting = form._embedded.element_settings[0][ i ];

								if ( elementParents[ elementSetting.element_id ] ) {
									container = this.form.containers.get( elementParents[ elementSetting.element_id ] );
									if ( container ) {
										element = container.elements.get( elementSetting.element_id );
										if ( element ) {
											element.element_settings.add( elementSetting );
										}
									}
								}
							}
						}
					}
				}
			} else {
				this.form = new torro.Builder.FormModel({}, {
					container_label_placeholder: i18n.defaultContainerLabel
				});

				this.form.containers.add({});
			}
		},

		/**
		 * Sets up form builder views.
		 *
		 * This method only works if the form builder has been initialized.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		setupViews: function() {
			if ( ! _.contains( initialized, this.instanceCount ) ) {
				return;
			}

			this.view = new torro.Builder.View( this.$el, this.form, {
				i18n: i18n
			});

			this.view.initialize();
		},

		/**
		 * Adds a callback that will be executed once the form builder has been initialized.
		 *
		 * If the form builder has already been initialized, the callback will be executed
		 * immediately.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param {function} callback Callback to execute. Should accept the form builder instance
		 *                            as parameter.
		 */
		onLoad: function( callback ) {
			if ( _.isUndefined( callbacks[ 'builder' + this.instanceCount ] ) ) {
				callback( this );
				return;
			}

			callbacks[ 'builder' + this.instanceCount ].push( callback );
		},

		/**
		 * Shows a failure message for the form builder in the UI.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param {string} message Failure message to display.
		 */
		fail: function( message ) {
			var compiled = torro.template( 'failure' );

			this.$el.find( '.drag-drop-area' ).addClass( 'is-empty' ).html( compiled({ message: message }) );
		}
	});

	torro.Builder = Builder;

	/**
	 * Returns the main form builder instance.
	 *
	 * It will be instantiated and initialized if it does not exist yet.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	torro.Builder.getInstance = function() {
		if ( ! builder ) {
			builder = new Builder( '#torro-form-canvas' );
			builder.init();
		}

		return builder;
	};

}( window.torro, window.jQuery, window._, window.torroBuilderI18n ) );

( function( torroBuilder, _ ) {
	'use strict';

	/**
	 * An element type.
	 *
	 * @class
	 *
	 * @param {object} attributes Element type attributes.
	 */
	function ElementType( attributes ) {
		this.attributes = attributes;
	}

	_.extend( ElementType.prototype, {

		/**
		 * Returns the element type slug.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {string} Element type slug.
		 */
		getSlug: function() {
			return this.attributes.slug;
		},

		/**
		 * Returns the element type title.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {string} Element type title.
		 */
		getTitle: function() {
			return this.attributes.title;
		},

		/**
		 * Returns the element type description.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {string} Element type description.
		 */
		getDescription: function() {
			return this.attributes.description;
		},

		/**
		 * Returns the element type icon URL.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {string} Element type icon URL.
		 */
		getIconUrl: function() {
			return this.attributes.icon_url;
		},

		/**
		 * Checks whether the element type is a non input element type.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {string} True if the element type is a non input element type, false otherwise.
		 */
		isNonInput: function() {
			return this.attributes.non_input;
		},

		/**
		 * Checks whether the element type is evaluable.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {string} True if the element type is evaluable, false otherwise.
		 */
		isEvaluable: function() {
			return this.attributes.evaluable;
		},

		/**
		 * Checks whether the element type contains multiple fields.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {string} True if the element type contains multiple fields, false otherwise.
		 */
		isMultiField: function() {
			return this.attributes.multifield;
		},

		/**
		 * Returns the settings sections that belong to the element type.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {object[]} Element type sections.
		 */
		getSections: function() {
			return this.attributes.sections;
		},

		/**
		 * Returns the settings fields that belong to the element type.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {object[]} Element type fields.
		 */
		getFields: function() {
			return this.attributes.fields;
		}
	});

	torroBuilder.ElementType = ElementType;

})( window.torro.Builder, window._ );

( function( torroBuilder, _ ) {
	'use strict';

	/**
	 * A list of available element types.
	 *
	 * @class
	 *
	 * @param {torro.Builder.ElementType[]} elementTypes Registered element type objects.
	 */
	function ElementTypes( elementTypes ) {
		var i;

		this.types = {};

		for ( i in elementTypes ) {
			this.types[ elementTypes[ i ].getSlug() ] = elementTypes[ i ];
		}
	}

	_.extend( ElementTypes.prototype, {

		/**
		 * Returns a specific element type.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {torro.Builder.ElementType|undefined} Element type object, or undefined if not available.
		 */
		get: function( slug ) {
			if ( _.isUndefined( this.types[ slug ] ) ) {
				return undefined;
			}

			return this.types[ slug ];
		},

		/**
		 * Returns all element types.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {torro.Builder.ElementType[]} All element type objects.
		 */
		getAll: function() {
			return this.types;
		}
	});

	/**
	 * Generates an element types list instance from a REST API response.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @returns {torro.Builder.ElementTypes} Element types object.
	 */
	ElementTypes.fromApiCollection = function( collection ) {
		var elementTypes = [];

		collection.each( function( model ) {
			var attributes = _.extend({}, model.attributes );
			if ( attributes._links ) {
				delete attributes._links;
			}
			if ( attributes._embedded ) {
				delete attributes._embedded;
			}

			elementTypes.push( new torroBuilder.ElementType( attributes ) );
		});

		return new ElementTypes( elementTypes );
	};

	torroBuilder.ElementTypes = ElementTypes;

})( window.torro.Builder, window._ );

( function( torroBuilder, torro, _, Backbone ) {
	'use strict';

	/**
	 * Base for a form builder model.
	 *
	 * This model has no persistence with the server.
	 *
	 * @class
	 * @augments Backbone.Model
	 */
	torroBuilder.BaseModel = Backbone.Model.extend({

		/**
		 * Related REST links.
		 *
		 * @since 1.0.0
		 * @access public
		 * @type {object}
		 */
		links: {},

		/**
		 * Instantiates a new model.
		 *
		 * Overrides constructor in order to strip out unnecessary attributes.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param {object} [attributes] Model attributes.
		 * @param {object} [options]    Options for the model behavior.
		 */
		constructor: function( attributes, options ) {
			var attrs = attributes || {};
			var idAttribute = this.idAttribute || Backbone.Model.prototype.idAttribute || 'id';

			if ( attrs._links ) {
				this.links = attrs._links;
			}

			attrs = _.omit( attrs, [ '_links', '_embedded' ] );

			if ( ! attrs[ idAttribute ] ) {
				attrs[ idAttribute ] = torro.generateTempId();
			}

			Backbone.Model.apply( this, [ attrs, options ] );
		},

		/**
		 * Synchronizes the model with the server.
		 *
		 * Overrides synchronization in order to disable synchronization.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {boolean} True on success, false on failure.
		 */
		sync: function( method, model, options ) {
			if ( 'create' === method && model.has( model.idAttribute ) ) {
				if ( ! options.attrs ) {
					options.attrs = model.toJSON( options );
				}

				options.attrs = _.omit( options.attrs, model.idAttribute );
			}

			return false;
		},

		/**
		 * Checks whether this model is new.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return {boolean} True if the model is new, false otherwise.
		 */
		isNew: function() {
			return ! this.has( this.idAttribute ) || torro.isTempId( this.get( this.idAttribute ) );
		}
	});

})( window.torro.Builder, window.torro, window._, window.Backbone );

( function( torroBuilder, torro, _, Backbone ) {
	'use strict';

	/**
	 * Base for a form builder collection.
	 *
	 * This collection has no persistence with the server.
	 *
	 * @class
	 * @augments Backbone.Collection
	 */
	torroBuilder.BaseCollection = Backbone.Collection.extend({

		/**
		 * Model class for the collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {function}
		 */
		model: torroBuilder.BaseModel,

		/**
		 * Default properties for the collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {object}
		 */
		defaultProps: {},

		/**
		 * Instantiates a new collection.
		 *
		 * Sets up collection properties.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param {object[]} [models]  Models for the collection.
		 * @param {object}   [options] Options for the model behavior.
		 */
		constructor: function( models, options ) {
			var props = _.defaults( options && options.props || {}, this.defaultProps );

			this.props = new Backbone.Model( props );

			if ( this.urlEndpoint ) {
				this.url = torro.api.root + torro.api.versionString + this.urlEndpoint;
			}

			Backbone.Collection.apply( this, arguments );
		},

		/**
		 * Synchronizes the collection with the server.
		 *
		 * Overrides synchronization in order to disable synchronization.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {boolean} True on success, false on failure.
		 */
		sync: function() {
			return false;
		}
	});

})( window.torro.Builder, window.torro, window._, window.Backbone );

( function( torroBuilder, _ ) {
	'use strict';

	/**
	 * A single container.
	 *
	 * @class
	 * @augments torro.Builder.BaseModel
	 */
	torroBuilder.ContainerModel = torroBuilder.BaseModel.extend({

		/**
		 * Returns container defaults.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {object} Container defaults.
		 */
		defaults: function() {
			return _.extend( _.clone({
				id: 0,
				form_id: 0,
				label: '',
				sort: 0
			}), this.collection.getDefaultAttributes() );
		},

		/**
		 * Element collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {object}
		 */
		elements: undefined,

		/**
		 * Instantiates a new model.
		 *
		 * Overrides constructor in order to strip out unnecessary attributes.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param {object} [attributes] Model attributes.
		 * @param {object} [options]    Options for the model behavior.
		 */
		constructor: function( attributes, options ) {
			torroBuilder.BaseModel.apply( this, [ attributes, options ] );

			this.elements = new torroBuilder.ElementCollection([], {
				props: {
					container_id: this.get( 'id' )
				}
			});
		}
	});

})( window.torro.Builder, window._ );

( function( torroBuilder, _ ) {
	'use strict';

	/**
	 * A single element choice.
	 *
	 * @class
	 * @augments torro.Builder.BaseModel
	 */
	torroBuilder.ElementChoiceModel = torroBuilder.BaseModel.extend({

		/**
		 * Returns element choice defaults.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {object} Element choice defaults.
		 */
		defaults: function() {
			return _.extend( _.clone({
				id: 0,
				element_id: 0,
				field: '',
				value: '',
				sort: 0
			}), this.collection.getDefaultAttributes() );
		}
	});

})( window.torro.Builder, window._ );

( function( torroBuilder, _ ) {
	'use strict';

	/**
	 * A single element.
	 *
	 * @class
	 * @augments torro.Builder.BaseModel
	 */
	torroBuilder.ElementModel = torroBuilder.BaseModel.extend({

		/**
		 * Returns element defaults.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {object} Element defaults.
		 */
		defaults: function() {
			return _.extend( _.clone({
				id: 0,
				container_id: 0,
				label: '',
				sort: 0,
				type: 'textfield'
			}), this.collection.getDefaultAttributes() );
		},

		/**
		 * Element choice collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {object}
		 */
		element_choices: null,

		/**
		 * Element setting collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {object}
		 */
		element_settings: undefined,

		/**
		 * Instantiates a new model.
		 *
		 * Overrides constructor in order to strip out unnecessary attributes.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param {object} [attributes] Model attributes.
		 * @param {object} [options]    Options for the model behavior.
		 */
		constructor: function( attributes, options ) {
			torroBuilder.BaseModel.apply( this, [ attributes, options ] );

			this.element_choices = new torroBuilder.ElementChoiceCollection([], {
				props: {
					element_id: this.get( 'id' )
				}
			});

			this.element_settings = new torroBuilder.ElementSettingCollection([], {
				props: {
					element_id: this.get( 'id' )
				}
			});
		}
	});

})( window.torro.Builder, window._ );

( function( torroBuilder, _ ) {
	'use strict';

	/**
	 * A single element setting.
	 *
	 * @class
	 * @augments torro.Builder.BaseModel
	 */
	torroBuilder.ElementSettingModel = torroBuilder.BaseModel.extend({

		/**
		 * Returns element choice defaults.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {object} Element choice defaults.
		 */
		defaults: function() {
			return _.extend( _.clone({
				id: 0,
				element_id: 0,
				name: '',
				value: ''
			}), this.collection.getDefaultAttributes() );
		}
	});

})( window.torro.Builder, window._ );

( function( torroBuilder ) {
	'use strict';

	/**
	 * A single form.
	 *
	 * @class
	 * @augments torro.Builder.BaseModel
	 */
	torroBuilder.FormModel = torroBuilder.BaseModel.extend({

		/**
		 * Form defaults.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {object}
		 */
		defaults: {
			id: 0,
			title: '',
			slug: '',
			author: 0,
			status: 'draft',
			timestamp: 0,
			timestamp_modified: 0
		},

		/**
		 * Container collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {object}
		 */
		containers: undefined,

		/**
		 * Instantiates a new model.
		 *
		 * Overrides constructor in order to strip out unnecessary attributes.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param {object} [attributes] Model attributes.
		 * @param {object} [options]    Options for the model behavior.
		 */
		constructor: function( attributes, options ) {
			var containerProps;

			torroBuilder.BaseModel.apply( this, [ attributes, options ] );

			containerProps = {
				form_id: this.get( 'id' )
			};

			if ( 'object' === typeof options && options.container_label_placeholder ) {
				containerProps.label_placeholder = options.container_label_placeholder;
			}

			this.containers = new torroBuilder.ContainerCollection([], {
				props: containerProps
			});
		}
	});

})( window.torro.Builder );

( function( torroBuilder ) {
	'use strict';

	/**
	 * A collection of containers.
	 *
	 * @class
	 * @augments torro.Builder.BaseCollection
	 */
	torroBuilder.ContainerCollection = torroBuilder.BaseCollection.extend({

		/**
		 * Model class for the container collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {function}
		 */
		model: torroBuilder.ContainerModel,

		/**
		 * REST endpoint URL part for accessing containers.
		 *
		 * @since 1.0.0
		 * @access public
		 * @type {string}
		 */
		urlEndpoint: 'containers',

		/**
		 * Default properties for the collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {object}
		 */
		defaultProps: {
			selected:          false,
			form_id:           0,
			label_placeholder: 'Page %s'
		},

		/**
		 * Returns container defaults.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {object} Container defaults.
		 */
		getDefaultAttributes: function() {
			return {
				form_id: this.props.get( 'form_id' ),
				label:   this.props.get( 'label_placeholder' ).replace( '%s', this.length + 1 ),
				sort:    this.length
			};
		}
	});

})( window.torro.Builder );

( function( torroBuilder ) {
	'use strict';

	/**
	 * A collection of element choices.
	 *
	 * @class
	 * @augments torro.Builder.BaseCollection
	 */
	torroBuilder.ElementChoiceCollection = torroBuilder.BaseCollection.extend({

		/**
		 * Model class for the element choice collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {function}
		 */
		model: torroBuilder.ElementChoiceModel,

		/**
		 * REST endpoint URL part for accessing element choices.
		 *
		 * @since 1.0.0
		 * @access public
		 * @type {string}
		 */
		urlEndpoint: 'element_choices',

		/**
		 * Default properties for the collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {object}
		 */
		defaultProps: {
			element_id: 0
		},

		/**
		 * Returns element choice defaults.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {object} Element choice defaults.
		 */
		getDefaultAttributes: function() {
			return {
				element_id: this.props.get( 'element_id' ),
				sort:       this.length
			};
		}
	});

})( window.torro.Builder );

( function( torroBuilder ) {
	'use strict';

	/**
	 * A collection of elements.
	 *
	 * @class
	 * @augments torro.Builder.BaseCollection
	 */
	torroBuilder.ElementCollection = torroBuilder.BaseCollection.extend({

		/**
		 * Model class for the element collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {function}
		 */
		model: torroBuilder.ElementModel,

		/**
		 * REST endpoint URL part for accessing elements.
		 *
		 * @since 1.0.0
		 * @access public
		 * @type {string}
		 */
		urlEndpoint: 'elements',

		/**
		 * Default properties for the collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {object}
		 */
		defaultProps: {
			active:       false,
			container_id: 0
		},

		/**
		 * Returns element defaults.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {object} Element defaults.
		 */
		getDefaultAttributes: function() {
			return {
				container_id: this.props.get( 'container_id' ),
				sort:         this.length
			};
		}
	});

})( window.torro.Builder );

( function( torroBuilder ) {
	'use strict';

	/**
	 * A collection of element settings.
	 *
	 * @class
	 * @augments torro.Builder.BaseCollection
	 */
	torroBuilder.ElementSettingCollection = torroBuilder.BaseCollection.extend({

		/**
		 * Model class for the element setting collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {function}
		 */
		model: torroBuilder.ElementSettingModel,

		/**
		 * REST endpoint URL part for accessing element settings.
		 *
		 * @since 1.0.0
		 * @access public
		 * @type {string}
		 */
		urlEndpoint: 'element_settings',

		/**
		 * Default properties for the collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {object}
		 */
		defaultProps: {
			element_id: 0
		},

		/**
		 * Returns element setting defaults.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @returns {object} Element setting defaults.
		 */
		getDefaultAttributes: function() {
			return {
				element_id: this.props.get( 'element_id' ),
				sort:       this.length
			};
		}
	});

})( window.torro.Builder );

( function( torroBuilder ) {
	'use strict';

	/**
	 * A collection of forms.
	 *
	 * @class
	 * @augments torro.Builder.BaseCollection
	 */
	torroBuilder.FormCollection = torroBuilder.BaseCollection.extend({

		/**
		 * Model class for the form collection.
		 *
		 * @since 1.0.0
		 * @access public
		 * @property {function}
		 */
		model: torroBuilder.FormModel,

		/**
		 * REST endpoint URL part for accessing forms.
		 *
		 * @since 1.0.0
		 * @access public
		 * @type {string}
		 */
		urlEndpoint: 'forms'
	});

})( window.torro.Builder );

( function( torroBuilder, $, _ ) {
	'use strict';

	/**
	 * The form builder view.
	 *
	 * @class
	 *
	 * @param {object} attributes Element type attributes.
	 */
	function View( $el, form, options ) {
		this.$el = $el;

		this.form = form;
		this.options = options || {};
	}

	_.extend( View.prototype, {
		initialize: function() {
			console.log( this.form );

			// TODO.
		}

		// TODO: functions here.
	});

	torroBuilder.View = View;

})( window.torro.Builder, window.jQuery, window._ );

( function( $ ) {
	'use strict';

	$( '.torro-metabox-tab' ).on( 'click', function( e ) {
		var $this = $( this );
		var $all  = $this.parent().children( '.torro-metabox-tab' );

		e.preventDefault();

		if ( 'true' === $this.attr( 'aria-selected' ) ) {
			return;
		}

		$all.each( function() {
			$( this ).attr( 'aria-selected', 'false' );
			$( $( this ).attr( 'href' ) ).attr( 'aria-hidden', 'true' );
		});

		$this.attr( 'aria-selected', 'true' );
		$( $this.attr( 'href' ) ).attr( 'aria-hidden', 'false' ).find( '.plugin-lib-map-control' ).each( function() {
			$( this ).wpMapPicker( 'refresh' );
		});
	});

})( window.jQuery );

( function( torroBuilder ) {
	'use strict';

	torroBuilder.getInstance();

})( window.torro.Builder );
