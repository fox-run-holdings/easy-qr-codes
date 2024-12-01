<?php
	/**
	 * Plugin Name: Easy QR Codes
	 * Description: Generates QR codes for any URL and displays a list of previously generated QR codes.
	 * Version: 1.1
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
		
		$sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        qr_code_url TEXT NOT NULL,
        target_url TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
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
			
			$qr_code_image = generate_qr_code($input_url);
			
			// Save the QR code URL and target URL to the database.
			$wpdb->insert($table_name, [
				'qr_code_url' => $qr_code_image,
				'target_url' => $input_url,
			]);
			
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
					<th>Generated At</th>
				</tr>
				</thead>
				<tbody>
				<?php if (!empty($qr_codes)) : ?>
					<?php foreach ($qr_codes as $qr_code) : ?>
						<tr>
							<td><?php echo esc_html($qr_code['id']); ?></td>
							<td>
								<img src="<?php echo esc_url($qr_code['qr_code_url']); ?>" alt="QR Code" width="100">
							</td>
							<td>
								<a href="<?php echo esc_url($qr_code['target_url']); ?>" target="_blank"><?php echo esc_html($qr_code['target_url']); ?></a>
							</td>
							<td><?php echo esc_html($qr_code['created_at']); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="4">No QR codes generated yet.</td>
					</tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

// Generate a QR code and save it as an image in the uploads directory.
	function generate_qr_code($url) {
		include_once(ABSPATH . 'wp-admin/includes/file.php');
		WP_Filesystem();
		
		$upload_dir = wp_upload_dir();
		$qr_code_dir = trailingslashit($upload_dir['basedir']) . 'qr_codes/';
		$qr_code_url = trailingslashit($upload_dir['baseurl']) . 'qr_codes/';
		
		// Ensure directory exists.
		if (!file_exists($qr_code_dir)) {
			wp_mkdir_p($qr_code_dir);
		}
		
		$file_name = 'qr_code_' . time() . '.png';
		$file_path = $qr_code_dir . $file_name;
		
		// Include the PHP QR Code library.
		require_once plugin_dir_path(__FILE__) . 'vendor/phpqrcode/qrlib.php';
		
		// Generate the QR code image.
		QRcode::png($url, $file_path, QR_ECLEVEL_L, 10);
		
		return $qr_code_url . $file_name;
	}
