<?php
/*
Plugin Name: My Tickets: Donations
Plugin URI: http://www.joedolson.com/
Description: Invite ticket purchasers to make a voluntary donation at the time of purchase.
Author: Joseph C Dolson
Author URI: http://www.joedolson.com/product/my-tickets-donations/
Version: 1.0.1
*/
/*  Copyright 2015  Joe Dolson (email : joe@joedolson.com)

    This program is open source software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
global $mtd_version;
$mtd_version = '1.0.1';

load_plugin_textdomain( 'my-tickets-donations', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

/*
 * Create custom field added to My Tickets shopping cart where user can add a custom donation amount.
 *
 * @param $custom_fields array Any other existing fields.
 * @param $cart array Data currently existing in cart.
 * @param $gateway string Current gateway identifier.
 *
 * @return array New array of custom fields.
**/

add_filter( 'mt_cart_custom_fields', 'mtd_custom_fields', 10, 3 );
function mtd_custom_fields( $custom_fields, $cart, $gateway ) {
	if ( $gateway == 'offline' ) {
		// disable for offline payments?
	} else {
		$custom_fields['donations'] = '<p class="mtd_donation"><label for="mtd_donation">' . apply_filters( 'mtd_donation_label', __( 'Add a Donation to your Purchase', 'my-tickets-donations' ) ) . '<input type="text" name="mtd_donation" id="mtd_donation" /></p>';
	}
	return $custom_fields;
}


add_filter( 'mtd_donation_label', 'mtd_donation_label_value' );
function mtd_donation_label_value( $label ) {
	$custom = get_option( 'mtd_cta' );
	if ( $custom ) {
		$label = esc_html( $custom );
	}
	return $label;
}

/*
 * Display donation value on purchase confirmation form.
 *
 * @param $form string Purchase form (generated by gateway.)
 * 
 * @return string Purchase form with confirmation appended.
 */
add_filter( 'mt_custom_notices', 'mtd_confirm_donation' );
function mtd_confirm_donation( $notices ) {
	$donation = '';
	if ( isset( $_POST['mtd_donation'] ) && is_numeric( $_POST['mtd_donation'] ) ) {
		$donation = '<span class="mt_donation_number mt_cart_value">' . apply_filters( 'mt_money_format', $_POST['mtd_donation'] ) . '</span>';
		$donation = '<div class="mt_cart_donation mt_cart_label">' . sprintf( apply_filters( 'mtd_donation_confirmation', __( 'Donation: %s', 'my-tickets-donations' ) ), $donation ) . "</div>";
	}
	
	return $notices . $donation;
}

/* 
 * Add donation amount to cart total.
 *
 * @param $charges integer Base custom charges added through plug-ins.
 * @return integer New charges
 */
add_filter( 'mt_custom_charges', 'mtd_donation_charge' );
function mtd_donation_charge( $charges ) {
	if ( isset( $_POST['mtd_donation'] ) && is_numeric( $_POST['mtd_donation'] ) ) {
		$donation = $_POST['mtd_donation'];
		$charges  = $donation + $charges;
	}
	
	return $charges;	
}

/*
 * Add donation value to payment to be processed by gateway.
 *
 * @param $args Arguments being sent to payment form.
 * 
 * @return $args New arguments
 */
add_filter( 'mt_payment_form_args', 'mtd_add_donation' );
function mtd_add_donation( $args ) {
	if ( isset( $_POST['mtd_donation'] ) && is_numeric( $_POST['mtd_donation'] ) ) {
		$total = $args['total'];
		$total = $total + $_POST['mtd_donation'];
		$args['total'] = $total;
	}
	
	return $args;
}


/*
 * Save donation value into payment record.
 *
 * @param $payment integer post ID for payment
 * @param $post $_POST 
 *
 * @return null
 */
add_filter( 'mt_handle_custom_cart_data', 'mtd_save_donation', 10, 2 );
function mtd_save_donation( $payment, $post ) {
	if ( isset( $_POST['mtd_donation'] ) && is_numeric( $_POST['mtd_donation'] ) ) {
		update_post_meta( $payment, '_donation', $_POST['mtd_donation'] );
		// Action for other plug-ins to use to register donations elsewhere or send secondary notifications.
		do_action( 'mtd_ticket_donation', $_POST, $payment );
	}
}


/*
 * Display donation value on payments page.
 *
 * @param $output string. Any other custom output.
 * @param $post_ID Payment ID
 *
 * @return string Output string plus new output.
 */
add_filter( 'mt_show_in_payment_fields', 'mtd_show_donation', 10, 2 );
function mtd_show_donation( $output, $post_ID ) {
	$donation = get_post_meta( $post_ID, '_donation', true );
	if ( $donation ) {
		$donation = sprintf( __( 'Includes a donation of <strong>%s</strong>', 'my-tickets-donations' ), apply_filters( 'mt_money_format', $donation ) );
	} else {
		$donation = __( 'No Donation included', 'my-tickets-donations' );
	}
	return $output . $donation;
}

add_filter( 'mt_generate_cart_total', 'mtd_include_donation_in_total', 10, 1 );
function mtd_include_donation_in_total( $total ) {
	$donation = ( isset( $_POST['mtd_donation'] ) ) ? $_POST['mtd_donation'] : 0;
	$total = $total + $donation;
	
	return $total;
}

/*
 * Add donation value to notifications output.
 */
add_filter( 'mt_notifications_data', 'mtd_donation_field', 10, 2 );
function mtd_donation_field( $data, $details ) {
	$donation = get_post_meta( $details['id'], '_donation', true );
	if ( $donation ) {
		$data['donation'] = apply_filters( 'mt_money_format', $donation );
	} else {
		$data['donation'] = __( 'No Donation included', 'my-tickets-donations' );
	}
	return $data;
}

/*
 * Add donation tag to available notification tags list.
 */
add_filter( 'mt_display_tags', 'mtd_custom_tag' );
function mtd_custom_tag( $tags ) {
	$tags[] = 'donation';
	return $tags;
}

/*
 * Show donation info on receipt
 */
add_filter( 'mt_custom_receipt', 'mtd_custom_receipt' );
function mtd_custom_receipt( $content ) {
	$donation = mt_get_donation_details();
	return $donation;
}


/**
 * Get donation data from payment.
 *
 * @return string|void
 */
function mt_get_donation_details() {
	$receipt = mt_get_receipt();
	if ( $receipt ) {
		$donation = get_post_meta( $receipt->ID, '_donation', true );
		$donation = sprintf( __( 'Includes a donation of <strong>%s</strong>', 'my-tickets-donations' ), apply_filters( 'mt_money_format', $donation ) );
		return $donation;
	}

	return '';
}

/*
 * Get total donations submitted on event.
 */
add_filter( 'mt_custom_total_line_time', 'mtd_total_donations_line', 10, 3 );
function mtd_total_donations_line( $output, $start, $end ) {
	$donations = mtd_donations( $start, $end );
	if ( $donations ) {
		$count = "<strong>$donations[count]</strong>";
		$total = "<strong>" . apply_filters( 'mt_money_format', $donations['total'] ) . "</strong>";
		return "<p>" . sprintf( __( '%1$s donations, totaling %2$s', 'my-tickets-donations' ), $count, $total ) . "</p>";
	} else {
		return "<p>" . __( "No Donations", 'my-tickets-donations' ) . "</p>";
	}
}


/**
 * Get all donations total by event.
 *
 * @param $event_id
 *
 * @return array
 */
function mtd_donations( $start, $end ) {
	$args    =
		array(
			'post_type'   => 'mt-payments',
			'post_status' => array( 'publish' ),
			'meta_query'  => array(
				'relation' => 'AND',
				'queries'  => array(
					'key'     => '_donation',
					'compare' => 'EXISTS'
				)
			),
			'date_query'     => array(
				'after'     => $start,
				'before'    => $end,
				'inclusive' => true
			)
		);
	$query   = new WP_Query( $args );	
	$total = 0;
	$count = 0;
	if ( !empty( $query->posts ) ) {
		$count = count( $query->posts );
		foreach ( $query->posts as $payment ) {
			$purchase_id = $payment->ID;
			$donation = get_post_meta( $purchase_id, '_donation', true );
			$total    = $total + $donation;
		}

		return array( 'total' => $total, 'count' => $count );
	}
}


/*
 * Add donation value to reports. Uses @mt_custom_fields filter, but only in reports.
 */
add_filter( 'mt_custom_fields', 'mtd_report_fields', 10, 2 );
function mtd_report_fields( $fields, $context ) {
	if ( $context == 'reports' ) {
		$fields['_donation'] = array( 'title'=> __( 'Donation', 'my-tickets-donations' ) );
	}
	return $fields;
}

// Actions/Filters for various tables and the css output
add_action( 'admin_init', 'mtd_add' );
/**
 * Add custom columns to payments post type page.
 */
function mtd_add() {
	add_action( 'admin_head', 'mtd_css' );
	add_filter( "manage_mt-payments_posts_columns", 'mtd_column' );
	add_action( "manage_mt-payments_posts_custom_column", 'mtd_custom_column', 10, 2 );
}

// Output CSS for width of new column
function mtd_css() {
	global $current_screen;
	if ( $current_screen->id == 'mt-payments' || $current_screen->id == 'edit-mt-payments' ) {
		wp_enqueue_style( 'mtd.posts', plugins_url( 'css/mtd-post.css', __FILE__ ) );
	}
}
/**
 * Add status/total and receipt ID fields to Payments post type.
 *
 * @param $cols
 *
 * @return mixed
 */
function mtd_column( $cols ) {
	$cols['mt_donation']  = __( 'Donation', 'my-tickets-donations' );

	return $cols;
}


// Echo the ID for the new column
/**
 * In Payment post type, get status paid and receipt data.
 * @param $column_name
 * @param $id
 */
function mtd_custom_column( $column_name, $id ) {
	switch ( $column_name ) {
		case 'mt_donation' :
			$donation = get_post_meta( $id, '_donation', true );
			if ( $donation ) {
				$donation = apply_filters( 'mt_money_format', $donation );
			} else {
				$donation = __( 'None', 'my-tickets-donations' );
			}
			echo $donation;
			break;
	}
}


add_action( 'admin_menu', 'mtd_menu_item', 11 );
/**
 * Add submenus item to show donations page.
 */
function mtd_menu_item() {
	$permission = apply_filters( 'mt_donations_permissions', 'manage_options' );
	add_submenu_page( 'my-tickets', __( 'My Tickets: Donations', 'my-tickets' ), __( 'Donations', 'my-tickets' ), $permission, 'my-tickets-donations', 'mtd_list' );
}

function mtd_list() {
	?>
	<?php $response = mtd_update_settings( $_POST ); ?>
	<div class="wrap my-tickets" id="mt_donations">
		<div id="icon-options-general" class="icon32"><br/></div>
		<h2><?php _e( 'Donations', 'my-tickets-donations' ); ?></h2>
		<div class="postbox-container jcd-wide">
			<div class="metabox-holder">

				<div class="ui-sortable meta-box-sortables">
					<div class="postbox">
						<h3><?php _e( 'Contributions made at Ticket Purchase', 'my-tickets-donations' ); ?></h3>

						<div class="inside">
							<?php mtd_donations_list(); ?>
						</div>
					</div>
				</div>
			</div>
			
			<div class="metabox-holder">

				<div class="ui-sortable meta-box-sortables">
					<div class="postbox">
						<h3><?php _e( 'Donations Settings', 'my-tickets-donations' ); ?></h3>
						<div class="inside">
							<?php echo $response; ?>
							<form method="post" action="<?php echo admin_url( "admin.php?page=my-tickets-donations" ); ?>">
								<div><input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'my-tickets-donations' ); ?>"/></div>
								<p>
									<label for="mtd_cta"><?php _e( 'Donation label:', 'my-tickets-donations' ); ?></label>
									<input type="text" class="widefat" name="mtd_cta" id="mtd_cta" value="<?php esc_attr_e( stripslashes( get_option( 'mtd_cta' ) ) ); ?>" />
								</p>
								<p><input type="submit" name="mtd-settings" class="button-primary" value="<?php _e( 'Save Settings', 'my-tickets-donations' ); ?>" /></p>
							</form>											
						</div>
					</div>
				</div>
			</div>			
		</div>
		<?php mt_show_support_box(); ?>
	</div>
	<?php
}

function mtd_update_settings( $post ) {
	if ( isset( $post['mtd-settings'] ) ) {
		$nonce = isset( $_POST['_wpnonce'] ) ? $_POST['_wpnonce'] : false;
		if ( !$nonce ) {
			return;
		}
		if ( ! wp_verify_nonce( $nonce, 'my-tickets-donations' ) ) {
			return false;
		}
		if ( isset( $_POST['mtd_cta'] ) ) {
			$mtd_cta = $_POST['mtd_cta'];
			update_option( 'mtd_cta', $mtd_cta );
		}

		return "<div class=\"updated\"><p><strong>" . __( 'Donations Settings saved', 'my-tickets-donations' ) . "</strong></p></div>";
	}

	return false;
}

/**
 * List all donations limited by time. Default 4 weeks.
 *
 * @param $start
 * @param $end
 *
 * @return array
 */
function mtd_donations_list( $start=false, $end=false, $return=false ) {
	$data = mtd_donations_data( $start, $end );
	$start = $data['start'];
	$end   = $data['end'];
	$posts = $data['posts'];
	$total = 0;
	$count = 0;
	$selected = ( isset( $_GET['format'] ) && $_GET['format'] == 'csv' ) ? " selected='selected'" : '';	
	$form     = "
			<div class='donations-by-date'>
				<h4>" . __( 'Donations Report by Date', 'my-tickets-donations' ) . "</h4>
				<form method='GET' action='" . admin_url( "admin.php?page=my-tickets-donations" ) . "'>
					<div>
						<input type='hidden' name='page' value='my-tickets-donations' />
					</div>
					<p>
						<label for='mt_start'>" . __( 'Report Start Date', 'my-tickets-donations' ) . "</label>
						<input type='date' name='mt_start' id='mt_start' value='$start' />
					</p>
					<p>
						<label for='mt_end'>" . __( 'Report End Date', 'my-tickets-donations' ) . "</label>
						<input type='date' name='mt_end' id='mt_end' value='$end' />
					</p>
					<p>
					<label for='mt_select_format'>" . __( 'Report Format', 'my-tickets-donations' ) . "</label>
					<select name='format' id='mt_select_format'>
						<option value='view'>" . __( 'View Report', 'my-tickets-donations' ) . "</option>
						<option value='csv'$selected>" . __( 'Download CSV', 'my-tickets-donations' ) . "</option>
					</select>
					</p>					
					<p><input type='submit' name='mt-display-report' class='button-primary' value='" . __( 'Get Donations by Date', 'my-tickets-donations' ) . "' /></p>
				</form>
			</div>";	
	if ( !empty( $posts ) ) {
		$table = '<table class="widefat">
					<thead>
						<tr>
							<th scope="col">' . __( 'Donor Name', 'my-tickets-donations' ) . '</th>
							<th scope="col">' . __( 'Donation', 'my-tickets-donations' ) . '</th>
							<th scope="col">' . __( 'Date', 'my-tickets-donations' ) . '</th>
							<th scope="col">' . __( 'Payment', 'my-tickets-donations' ) . '</th>
						</tr>
					</thead>
					<tbody>';	
		$count = count( $posts );
		foreach ( $posts as $payment ) {
			$purchase_id = $payment->ID;
			$donation = get_post_meta( $purchase_id, '_donation', true );
			$total    = $total + $donation;
			$row = "
			<tr>
			<th scope='row'>$payment->post_title</th>
			<td>" . apply_filters( 'mt_money_format', $donation ) . "</td>
			<td>" . date_i18n( get_option( 'date_format' ), strtotime( $payment->post_date ) ) . "</td>
			<td><a href='" . get_edit_post_link( $purchase_id ) . "'>" . get_post_meta( $purchase_id, '_receipt', true ) . "</a></td>
			</tr>";
			$table .= $row;
		}
		$count = "<strong>$count</strong>";
		$total = "<strong>" . apply_filters( 'mt_money_format', $total ) . "</strong>";
		$totals = "<p>" . sprintf( __( '%1$s donations totaling %2$s', 'my-tickets-donations' ), $count, $total ) . "</p>";
		$table .= '</tbody></table>';
		$print_report = "<p><a class='button' href='" . admin_url( 'admin.php?page=mt-reports&mt-event-report=donations&format=view&mt_print=true' ). "'>" . __( 'Print Report', 'my-tickets-donations' ) . "</a></p>";
		if ( $return ) {
			return $totals . $table;
		} else {
			echo $totals . $table . $print_report . $form;
		}
	} else {
		$none = __( 'There were no donations during this period.', 'my-tickets-donations' );
		if ( $return ) {
			return $none;
		} else {
			echo $none . $form;
		}
	}
}


/*
 * Get donation data for time period
 */
function mtd_donations_data( $start=false, $end=false ) {
	if ( isset( $_GET['mt_start'] ) && isset( $_GET['mt_end'] ) ) {
		$start = date( 'Y-m-d', strtotime( $_GET['mt_start'] ) );
		$end   = date( 'Y-m-d', strtotime( $_GET['mt_end'] ) );
	} else {
		$start   = ( !$start ) ? date( 'Y-m-d', ( current_time( 'timestamp' ) - 60*60*24*7*4 ) ) : $start;
		$end     = ( !$end ) ? date( 'Y-m-d', ( current_time( 'timestamp' ) ) ) : $end;
	}
	$args    =
		array(
			'post_type'   => 'mt-payments',
			'post_status' => array( 'publish' ),
			'meta_query'  => array(
				'relation' => 'AND',
				'queries'  => array(
					'key'     => '_donation',
					'compare' => 'EXISTS'
				)
			),
			'date_query'     => array(
				'after'     => $start,
				'before'    => $end,
				'inclusive' => true
			)
		);
	$query   = new WP_Query( $args );
	return array( 'posts'=>$query->posts, 'start'=>$start, 'end'=>$end );
}


add_action( 'admin_init', 'mtd_donations_csv' );
/**
 * Download donations by sales period as CSV.
 */
function mtd_donations_csv() {
	$csv = '';
	if ( isset( $_GET['format'] ) && $_GET['format'] == 'csv' 
		&& isset( $_GET['page'] ) && $_GET['page'] == 'my-tickets-donations' 
		&& isset( $_GET['mt_start'] ) ) {
		$data = mtd_donations_data();
		$posts = $data['posts'];
		$start = $data['start'];
		$end   = $data['end'];
		$csv = __( 'First Name', 'my-tickets-donations' ) . "," . __( 'Last name', 'my-tickets-donations' ) . "," . __( 'Donation Amount', 'my-tickets-donations' ) . "," . __( 'Date', 'my-tickets-donations' ) . "," . __( 'Receipt ID', 'my-tickets-donations' ) . PHP_EOL;
		foreach ( $posts as $payment ) {
			$purchase_id = $payment->ID;
			$purchaser    = get_the_title( $payment->ID );
			$first_name    = get_post_meta( $payment->ID, '_first_name', true );
			$last_name     = get_post_meta( $payment->ID, '_last_name', true );
			if ( !$first_name && !$last_name ) {
				$name = explode( ' ', $purchaser );
				$first_name = $name[0];
				$last_name = end( $name );
			}
			$receipt = get_post_meta( $purchase_id, '_receipt', true );
			$date = date_i18n( get_option( 'date_format' ), strtotime( $payment->post_date ) );
			$donation = get_post_meta( $purchase_id, '_donation', true );
			$row = "\"$first_name\",\"$last_name\",\"$donation\",\"$date\",\"$receipt\"" . PHP_EOL;
			$csv .= $row;
		}
		$title = sanitize_title( 'donations_'.$start . '_' . $end ) . '-' . date( 'Y-m-d' );
		header( 'Content-Type: application/csv' );
		header( "Content-Disposition: attachment; filename=$title.csv" );
		header( 'Pragma: no-cache' );
		echo $csv;
		exit;
	}
}

/* 
 * Make report printable.
 */
add_filter( 'mt_printable_report', 'mtd_printable_report' );
function mtd_printable_report( $report ) {
	$context = ( isset( $_GET['mt-event-report'] ) ) ? $_GET['mt-event-report'] : false;
	if ( $context == 'donations' ) {
		
		$report = mtd_donations_list( false, false, true );
	}
	return $report;
}

/* 
 * Make report back link return to donations.
 */
add_filter( 'mt_printable_report_back', 'mtd_printable_report_back' );
function mtd_printable_report_back( $back ) {
	$context = ( isset( $_GET['mt-event-report'] ) ) ? $_GET['mt-event-report'] : false;
	if ( $context == 'donations' ) {
		$back = 'admin.php?page=my-tickets-donations';
	}
	return $back;
}

/* Common fields to all My Tickets add-ons */
add_action( 'init', 'mtd_check_for_upgrades' );
/**
 * Check for automatic upgrade availability.
 */
function mtd_check_for_upgrades() {
	if ( is_admin() ) {
		global $mtd_version;
		$hash = get_option( 'mtd_license_key' );
		if ( ! $hash ) {
			return;
		}
		$mt_plugin_current_version = $mtd_version;
		$mt_plugin_remote_path     = "http://www.joedolson.com/wp-content/plugins/files/updates-v2.php?key=$hash";
		$mt_plugin_slug            = plugin_basename( __FILE__ );
		if ( class_exists( 'mt_auto_update' ) ) {
			new mt_auto_update( $mt_plugin_current_version, $mt_plugin_remote_path, $mt_plugin_slug );
		}
	}
	return;
}

/**
 * Insert license key field onto license keys page.
 *
 * @param $fields string Existing fields.
 * @return string
 */
add_action( 'mt_license_fields', 'mtd_license_field' );
function mtd_license_field( $fields ) {
	$field = 'mtd_license_key';
	$name =  __( 'My Tickets: Donations', 'my-tickets-donations' );
	return $fields . "
	<p class='license'>
		<label for='$field'>$name</label><br/>
		<input type='text' name='$field' id='$field' size='60' value='".esc_attr( trim( get_option( $field ) ) )."' />
	</p>";
}

add_action( 'mt_save_license', 'mtd_save_license', 10, 2 );
function mtd_save_license( $response, $post ) {
	$field = 'mtd_license_key';
	$name =  __( 'My Tickets: Donations', 'my-tickets-donations' );	
	if ( $post[$field] != get_option( $field ) ) {
		$verify = mt_verify_key( $field, $name );
	} else {
		$verify = '';
	}
	$verify = "<li>$verify</li>";
	return $response . $verify;
}

// these are existence checkers. Exist if licensed.
if ( get_option( 'mtd_license_key_valid' ) == 'true' ) {
	function mtd_valid() {
		return true;
	}
} else {
	$message = sprintf( __( "Please <a href='%s'>enter your My Tickets: Donations license key</a> to be eligible for support.", 'my-tickets-donations' ), admin_url( 'admin.php?page=my-tickets' ) );
	add_action( 'admin_notices', create_function( '', "if ( ! current_user_can( 'manage_options' ) ) { return; } else { echo \"<div class='error'><p>$message</p></div>\";}" ) );
}	