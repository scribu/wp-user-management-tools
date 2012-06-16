<?php
/*
Plugin Name: User Management Tools
Version: 1.1
Description: Allows you to bulk-add users to a blog in a multisite install
Author: AppThemes
Author URI: http://appthemes.com
Plugin URI: http://wordpress.org/extend/plugins/user-management-tools/
Text Domain: user-management-tools
Domain Path: /lang


This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

if ( is_admin() && is_multisite() ) {
	add_action( 'load-users.php', array( 'User_Management_Tools', 'init' ) );
}

class User_Management_Tools {

	function init() {
		if ( !is_super_admin() )
			return;

		self::handle_promotion();

		if ( isset( $_GET['umt_network'] ) ) {
			add_action( 'pre_user_query', array( __CLASS__, 'pre_user_query' ), 20 );
			add_action( 'admin_head', array( __CLASS__, 'fix_role' ) );
		}

		add_action( 'admin_print_footer_scripts', array( __CLASS__, 'script' ), 20 );
	}

	function pre_user_query( $user_query ) {
		global $wpdb;

		$user_query->query_from = "FROM $wpdb->users ";
		$user_query->query_where = str_replace( "AND (wp_usermeta.meta_key = '{$wpdb->prefix}capabilities' )", '', $user_query->query_where );
	}

	// http://core.trac.wordpress.org/ticket/18995
	function fix_role() {
		foreach ( $GLOBALS['wp_list_table']->items as $item ) {
			if ( empty( $item->roles ) )
				$item->roles[] = 'none';
		}
	}

	// Need to bypass is_multisite() && !is_user_member_of_blog() check in wp-admin/users.php
	function handle_promotion() {
		if ( !(isset($_REQUEST['changeit']) && !empty($_REQUEST['new_role']) && !empty($_REQUEST['users'])) )
			return;

		check_admin_referer('bulk-users');

		$editable_roles = get_editable_roles();
		if ( empty( $editable_roles[$_REQUEST['new_role']] ) )
			wp_die(__('You can&#8217;t give users that role.'));

		$update = 'promote';
		foreach ( $_REQUEST['users'] as $id ) {
			$id = (int) $id;

			if ( !$id )
				continue;

			$user = new WP_User($id);
			$user->set_role($_REQUEST['new_role']);
		}

		wp_redirect(add_query_arg('update', $update, 'users.php'));
		exit();
	}

	function script() {
		$current = isset( $_GET['umt_network'] ) ? "class='current' " : '';

		$link_html = "<li class='umt-network'><a $current href='" . admin_url( add_query_arg( 'umt_network', true, 'users.php' ) ) . "'>" . __( 'Entire Network', 'user-management-tools' ) . "</a> |</li>";
?>
<script type="text/javascript">
jQuery(function($){
	$('.subsubsub').prepend("<?php echo $link_html; ?>");

	if ( $('.umt-network .current').length ) {
		$('.subsubsub :not(.umt-network) .current').removeClass('current');
		$('#search-submit').before('<input type="hidden" name="umt_network" value="1" />');
	}
});
</script>
<?php
	}
}

