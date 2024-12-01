<?php
	/**
	 * Plugin Name: Easy QR Codes
	 * Description: Generates QR codes for any URL, tracks usage, and redirects QR code scans through the site.
	 * Version: 1.2
	 * Author: Ken Schnetz, Fox Run Holdings LLC
	 */
	
	if (!defined('ABSPATH')) {
		exit; // Exit if accessed directly.
	}

// Register activation hook to create or update the database table.
	register_activation_hook(__FILE__, 'qr_code_generator_create_table');
	
	function qr_code_generator_create_table() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'qr_codes';
		$charset_collate = $wpdb->get_charset_collate();
		
		// Updated SQL to include 'usage_count' column
		$sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        qr_code_url TEXT NOT NULL,
        target_url TEXT NOT NULL,
        usage_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

// Register the custom rewrite rule
	add_action('init', 'qr_code_generator_rewrite_rule');
	
	function qr_code_generator_rewrite_rule() {
		add_rewrite_rule('^easy-qr-codes/([0-9]+)/?$', 'index.php?qr_code_id=$matches[1]', 'top');
	}

// Register the query variable
	add_filter('query_vars', 'qr_code_generator_query_vars');
	
	function qr_code_generator_query_vars($query_vars) {
		$query_vars[] = 'qr_code_id';
		return $query_vars;
	}

// Handle the template redirect
	add_action('template_redirect', 'qr_code_generator_template_redirect');
	
	function qr_code_generator_template_redirect() {
		global $wpdb;
		
		$qr_code_id = get_query_var('qr_code_id');
		
		if ($qr_code_id) {
			$table_name = $wpdb->prefix . 'qr_codes';
			
			// Fetch the target URL from the database
			$qr_code = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $qr_code_id));
			
			if ($qr_code) {
				// Increment the usage count
				$wpdb->update(
					$table_name,
					array('usage_count' => $qr_code->usage_count + 1),
					array('id' => $qr_code_id),
					array('%d'),
					array('%d')
				);
				
				// Redirect to the target URL
				wp_redirect(esc_url_raw($qr_code->target_url));
				exit;
			} else {
				// If QR code not found, redirect to home page or show 404
				wp_redirect(home_url());
				exit;
			}
		}
	}

// Flush rewrite rules on plugin activation and deactivation
	register_activation_hook(__FILE__, 'qr_code_generator_flush_rewrites');
	register_deactivation_hook(__FILE__, 'qr_code_generator_flush_rewrites');
	
	function qr_code_generator_flush_rewrites() {
		qr_code_generator_rewrite_rule();
		flush_rewrite_rules();
	}

// Create admin menu.
	add_action('admin_menu', 'qr_code_generator_menu');
	
	function qr_code_generator_menu() {
		add_menu_page(
			'QR Code Generator',
			'Easy QR Codes',
			'manage_options',
			'qr-code-generator',
			'qr_code_generator_page',
			'dashicons-screenoptions',
			20
		);
	}

// Generate QR code page.
	function qr_code_generator_page() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'qr_codes';
		$site_url = site_url();
		
		// Handle QR code generation.
		if (isset($_POST['generate_qr_code'])) {
			// Sanitize and retrieve the URL from the form input.
			$input_url = isset($_POST['qr_target_url']) ? esc_url_raw(trim($_POST['qr_target_url'])) : $site_url;
			
			if (empty($input_url)) {
				$input_url = $site_url;
			}
			
			// Insert a new record into the database to get the QR code ID
			$wpdb->insert($table_name, [
				'qr_code_url' => '', // We'll update this after generating the QR code
				'target_url' => $input_url,
			]);
			
			$qr_code_id = $wpdb->insert_id;
			
			// Generate the QR code image
			$qr_code_image = generate_qr_code($qr_code_id);
			
			// Update the record with the QR code URL
			$wpdb->update(
				$table_name,
				array('qr_code_url' => $qr_code_image),
				array('id' => $qr_code_id),
				array('%s'),
				array('%d')
			);
			
			echo '<div class="notice notice-success is-dismissible"><p>QR Code generated successfully!</p></div>';
		}
		
		// Fetch stored QR codes.
		$qr_codes = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A);
		
		?>
		<div class="wrap">
			<h1>QR Code Generator</h1>
			<form method="post">
				<p>
					<label for="qr_target_url">Enter URL to encode:</label>
					<input type="text" id="qr_target_url" name="qr_target_url" value="<?php echo esc_attr($site_url); ?>" size="50" />
				</p>
				<p>
					<button type="submit" name="generate_qr_code" class="button button-primary">Generate QR Code</button>
				</p>
			</form>
			<h2>Generated QR Codes</h2>
			<table class="widefat fixed striped">
				<thead>
				<tr>
					<th>ID</th>
					<th>QR Code</th>
					<th>Target URL</th>
					<th>Usage Count</th>
					<th>Generated At</th>
				</tr>
				</thead>
				<tbody>
				<?php if (!empty($qr_codes)) : ?>
					<?php foreach ($qr_codes as $qr_code) : ?>
						<tr>
							<td><?php echo esc_html($qr_code['id']); ?></td>
							<td>
								<a href="<?php echo esc_url($qr_code['qr_code_url']); ?>" target="_blank">
									<img src="<?php echo esc_url($qr_code['qr_code_url']); ?>" alt="QR Code" width="100">
								</a>
							</td>
							<td>
								<a href="<?php echo esc_url($qr_code['target_url']); ?>" target="_blank"><?php echo esc_html($qr_code['target_url']); ?></a>
							</td>
							<td><?php echo esc_html($qr_code['usage_count']); ?></td>
							<td><?php echo esc_html($qr_code['created_at']); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="5">No QR codes generated yet.</td>
					</tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

// Generate a QR code and save it as an image in the uploads directory.
	function generate_qr_code($qr_code_id) {
		include_once(ABSPATH . 'wp-admin/includes/file.php');
		WP_Filesystem();
		
		$upload_dir = wp_upload_dir();
		$qr_code_dir = trailingslashit($upload_dir['basedir']) . 'qr_codes/';
		$qr_code_url = trailingslashit($upload_dir['baseurl']) . 'qr_codes/';
		
		// Ensure directory exists.
		if (!file_exists($qr_code_dir)) {
			wp_mkdir_p($qr_code_dir);
		}
		
		$file_name = 'qr_code_' . $qr_code_id . '.png';
		$file_path = $qr_code_dir . $file_name;
		
		// Include the PHP QR Code library.
		require_once plugin_dir_path(__FILE__) . 'vendor/phpqrcode/qrlib.php';
		
		// Build the URL to encode in the QR code
		$site_url = site_url();
		$qr_code_link = $site_url . '/easy-qr-codes/' . $qr_code_id;
		
		// Generate the QR code image.
		QRcode::png($qr_code_link, $file_path, QR_ECLEVEL_L, 10);
		
		return $qr_code_url . $file_name;
	}
