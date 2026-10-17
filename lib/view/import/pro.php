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
?>

<p class="max-upload-size">
	<?php printf( __( 'Maximum upload file size: <strong>%s</strong>.', AI1WM_PLUGIN_NAME ), esc_html( ai1wm_size_format( wp_max_upload_size() ) ) ); ?>
</p>
<p>

	<?php _e( 'or', AI1WM_PLUGIN_NAME ); ?>

</p>
