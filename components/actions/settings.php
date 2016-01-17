<?php
/**
 * General Settings Tab
 *
 * @author  awesome.ug, Author <support@awesome.ug>
 * @package TorroForms/Actions
 * @version 1.0.0
 * @since   1.0.0
 * @license GPL 2
 *
 * Copyright 2015 awesome.ug (support@awesome.ug)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Torro_Actions_Settings extends Torro_Settings {
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initializing.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		parent::__construct();
	}

	public function init() {
		$this->title = __( 'Actions', 'torro-forms' );
		$this->name = 'actions';
	}

	/**
	 * Adding Settings to Settings Page dynamical
	 *
	 * @param $settings_name
	 * @param $settings_title
	 * @param $settings_arr
	 */
	public function add_settings_field( $settings_name, $settings_title, $settings_arr ) {
		$this->add_subsettings_field_arr( $settings_name, $settings_title, $settings_arr );
	}
}

torro()->settings()->add( 'Torro_Actions_Settings' );
