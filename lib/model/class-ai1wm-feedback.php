<?php
/**
 * Copyright (C) 2014-2026 Amarta Dey
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Ai1wm_Feedback {

	/**
	 * Submit customer feedback to servmask.com
	 *
	 * @param  string  $type      Feedback type
	 * @param  string  $email     User e-mail
	 * @param  string  $message   User message
	 * @param  integer $terms     User accept terms
	 * @param  string  $purchases Purchases IDs
	 *
	 * @return array
	 */
	public static function add( $type, $email, $message, $terms, $purchases ) {
		// Feedback submission is disabled — no data is sent to any remote server.
		return array( 'response' => array( 'code' => 200 ) );
	}
}
