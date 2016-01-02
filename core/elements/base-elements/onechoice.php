<?php
/**
 * One Choice Form Element
 *
 * @author  awesome.ug, Author <support@awesome.ug>
 * @package TorroForms/Core/Elements
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

class Torro_Form_Element_OneChoice extends Torro_Form_Element {
	public function init() {
		$this->name = 'OneChoice';
		$this->title = __( 'One Choice', 'torro-forms' );
		$this->description = __( 'Add an Element which can be answered by selecting one of the given answers.', 'torro-forms' );
		$this->icon_url = TORRO_URLPATH . 'assets/img/icon-onechoice.png';

		$this->has_answers = true;
		$this->answer_is_multiple = false;
		$this->is_analyzable = true;
	}

	public function input_html()
	{
		$html  = '<label for="' . $this->get_input_name() . '">' . esc_html( $this->label ) . '</label>';

		foreach ( $this->answers as $answer ) {
			$checked = '';
			if ( $this->response === $answer['text'] ) {
				$checked = ' checked="checked"';
			}

			$html .= '<div class="torro_element_radio"><input type="radio" name="' . $this->get_input_name() . '" value="' . esc_attr( $answer['text'] ) . '" ' . $checked . '/> ' . esc_html( $answer['text'] ) . '</div>';
		}

		if ( ! empty( $this->settings['description'] ) ) {
			$html .= '<small>';
			$html .= esc_html( $this->settings['description'] );
			$html .= '</small>';
		}

		return $html;
	}

	public function settings_fields() {
		$this->settings_fields = array(
			'description'	=> array(
				'title'			=> __( 'Description', 'torro-forms' ),
				'type'			=> 'textarea',
				'description'	=> __( 'The description will be shown after the field.', 'torro-forms' ),
				'default'		=> ''
			)
		);
	}

	public function validate( $input ) {
		$error = false;

		if ( empty( $input ) ) {
			$this->validate_errors[] = sprintf( __( 'Please select a value.', 'torro-forms' ) );
			$error = true;
		}

		return ! $error;
	}
}

torro_register_form_element( 'Torro_Form_Element_OneChoice' );
