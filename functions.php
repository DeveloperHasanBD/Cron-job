<?php 
// This plugins also need "WP Crontrol"


function twohr_custom_cron_schedule($schedules)
{
	$schedules['every_two_hours'] = array(
		'interval' => 7200, // Every 2 hours
		'display'  => __('Every 2 hours'),
	);
	return $schedules;
}
add_filter('cron_schedules', 'twohr_custom_cron_schedule');

//Schedule an action if it's not already scheduled
if (!wp_next_scheduled('get_data_from_csv')) {
	wp_schedule_event(time(), 'every_two_hours', 'get_data_from_csv');
}

///Hook into that action that'll fire every two hours
add_action('get_data_from_csv', 'import_posts_from_csv');

function wpwd_log($message)
{
	if (is_array($message)) {
		$message = json_encode($message);
	}

	error_log(PHP_EOL . date('Y-m-d h:i:s') . ' :: ' . $message, 3, __DIR__ . '/wpwd_log.log');
}


function import_posts_from_csv()
{

	wpwd_log('<<<--- Last RUN!');
	$csv_art_url = get_site_url() . '/sap/final/arts.csv';
	$csv_image_url = get_site_url() . '/sap/final/images.csv';

	// Fetch CSV data from the provided URLs using wp_remote_get
	$csv_art_response = wp_remote_get($csv_art_url);
	$csv_image_response = wp_remote_get($csv_image_url);

	if (is_wp_error($csv_art_response) || is_wp_error($csv_image_response)) {
		// Handle error - Failed to fetch CSV data
		echo "Failed to fetch CSV data. Check the URLs.";
		return;
	}

	// Get the response body as text
	$csv_art_data = wp_remote_retrieve_body($csv_art_response);
	$csv_image_data = wp_remote_retrieve_body($csv_image_response);

	// Convert CSV data to arrays
	$art_rows = explode("\n", $csv_art_data);
	$image_rows = explode("\n", $csv_image_data);

	// Remove the header row
	array_shift($art_rows);
	array_shift($image_rows);

	global $wpdb;
	$posts_table = $wpdb->prefix . 'posts';




	// Loop through art data and insert posts
	foreach ($art_rows as $art_row) {
		$art_fields = str_getcsv($art_row, ';');

		// Ensure that there are enough elements in the array
		if (count($art_fields) >= 7) {
			// Extract data from CSV fields
			$item_id = $art_fields[0] ?? "";
			$post_title = $art_fields[1] ?? "";
			$post_content = $art_fields[2] ?? "";
			$post_content = str_replace('\n', '%0D%0A', $post_content);

			$category = $art_fields[3] ?? "";
			$sub_category = $art_fields[4] ?? "";
			$sub_sub_category = $art_fields[5] ?? "";



			$args = array(
				'post_type' => 'prodotti',
				'meta_query' => array(
					array(
						'key' => 'csv_item_number',
						'value' => $item_id,
						'compare' => '=',
					)
				)
			);
			require_once(ABSPATH . '/wp-admin/includes/taxonomy.php');
			$cat_id = 0;
			$sub_cat_id = 0;
			$sub_sub_cat_id = 0;

			if (strlen($category) > 1) {
				$cat_result = get_terms([
					'taxonomy' => 'prodotti-cat',
					'name' => $category,
					'hide_empty' => false,
				]);
				if (isset($cat_result[0]->term_id)) {
					$cat_id = $cat_result[0]->term_id;
				} else {
					// $insert_cat = array(
					// 	'taxonomy' => 'prodotti-cat',
					// 	'cat_name' => $category,
					// 	'category_description' => '',
					// 	'category_nicename' => '',
					// 	'category_parent' => '',
					// );
					// $cat_id = wp_insert_category($insert_cat);
				}
			}

			if (strlen($sub_category) > 1) {
				$sub_cat_result = get_terms([
					'taxonomy' => 'prodotti-cat',
					'name' => $sub_category,
					'hide_empty' => false,
				]);

				if (isset($sub_cat_result[0]->term_id)) {
					$sub_cat_id = $sub_cat_result[0]->term_id;
				} else {
					// $insert_sub_cat = array(
					// 	'taxonomy' => 'prodotti-cat',
					// 	'cat_name' => $sub_category,
					// 	'category_description' => '',
					// 	'category_nicename' => '',
					// 	'category_parent' => $cat_id,
					// );
					// $sub_cat_id = wp_insert_category($insert_sub_cat);
				}
			}

			if (strlen($sub_sub_category) > 1) {
				$sub_sub_cat_result = get_terms([
					'taxonomy' => 'prodotti-cat',
					'name' => $sub_sub_category,
					'hide_empty' => false,
				]);
				if (isset($sub_sub_cat_result[0]->term_id)) {
					$sub_sub_cat_id = $sub_sub_cat_result[0]->term_id;
				} else {
					// $insert_cat = array(
					// 	'taxonomy' => 'prodotti-cat',
					// 	'cat_name' => $sub_sub_category,
					// 	'category_description' => '',
					// 	'category_nicename' => '',
					// 	'category_parent' => $sub_cat_id,
					// );
					// $sub_sub_cat_id = wp_insert_category($insert_cat);
				}
			}


			$cat_ids = array();

			if ($cat_id > 0) {
				$cat_ids[0] = $cat_id;
			}

			if ($sub_cat_id > 0) {
				$cat_ids[1] = $sub_cat_id;
			}

			if ($sub_sub_cat_id > 0) {
				$cat_ids[2] = $sub_sub_cat_id;
			}
			$post_ID = "";
			$query = new WP_Query($args);
			//check if product exists
			if ($query->have_posts()) {
				//  update existing products...


				while ($query->have_posts()) {
					$query->the_post();
					$post_ID = get_the_ID();


					wpwd_log('Product Updated: ' . $item_id . ' : ' . $post_title);

					$updated_post = array(
						'ID' => $post_ID,
						'post_title' => $post_title,
					);

					wp_update_post($updated_post);


					// Assign categories to the post
					wp_set_post_categories($post_ID, $cat_ids);

					update_post_meta($post_ID, 'spro_desc_top', $post_content);

					$featured_img = true;
					$images = array();

					require_once(ABSPATH . 'wp-admin/includes/media.php');
					require_once(ABSPATH . 'wp-admin/includes/file.php');
					require_once(ABSPATH . 'wp-admin/includes/image.php');


					// Loop through image data to find the matching image
					foreach ($image_rows as $image_row) {
						$image_fields = str_getcsv($image_row, ';');

						// Ensure that there are enough elements in the array
						if ($image_fields[0] == $art_fields[0]) {
							// Construct the image URL
							$image_name = $image_fields[2] . '.' . $image_fields[3];

							$image_url = get_site_url() . '/sap/AllegatiArticoli/' . $image_name;

							// Check if an image with the same name already exists in the media library
							$existing_attachment = get_posts(array(
								'post_type'      => 'attachment',
								'meta_query'     => array(
									array(
										'key'     => 'org_file_name', // Replace with the actual meta key
										'value'   => $image_name . $post_ID,
										'compare' => '=',
									),
								),
								'numberposts'    => 1
							));

							if (empty($existing_attachment)) {
								// Image doesn't exist, sideload it
								$img_id = media_sideload_image($image_url, $post_ID, $post_title, 'id');



								// Check if sideloading was successful
								if (!is_wp_error($img_id)) {
									if ($featured_img) {
										set_post_thumbnail($post_ID, $img_id);
										$featured_img = false;
									} else {
										$images[] = $img_id;
									}
									wpwd_log('Image Added: ' . $image_name . $post_ID);
									update_post_meta($img_id, 'org_file_name', $image_name . $post_ID);
								}
							} else {
								$attachment_id = $existing_attachment[0]->ID;

								if (get_post_thumbnail_id($existing_attachment[0]->post_parent) !== $attachment_id) {


									$images[] = $attachment_id;
								}
							}
						}
					}

					update_field('spro_gallary', $images, $post_ID);
				}
			} else {



				$my_post = array(
					'post_title' => $post_title,
					'post_content' => $post_content,
					'post_status' => 'publish',
					'post_author' => 1,
					'post_type' => 'prodotti',
				);

				$post_ID = wp_insert_post($my_post);

				wpwd_log('Product Added: ' . $post_title);
				// Assign categories to the post
				wp_set_post_terms($post_ID, $cat_ids, 'prodotti-cat');

				add_post_meta($post_ID, 'csv_item_number', $item_id);
				add_post_meta($post_ID, 'spro_desc_top', $post_content);

				$featured_img = true;
				$images = array();

				require_once(ABSPATH . 'wp-admin/includes/media.php');
				require_once(ABSPATH . 'wp-admin/includes/file.php');
				require_once(ABSPATH . 'wp-admin/includes/image.php');

				// Loop through image data to find the matching image
				foreach ($image_rows as $image_row) {
					$image_fields = str_getcsv($image_row, ';');

					// Ensure that there are enough elements in the array
					if ($image_fields[0] == $art_fields[0]) {
						// Construct the image URL
						$image_name = $image_fields[2] . '.' . $image_fields[3];

						$image_url = get_site_url() . '/sap/AllegatiArticoli/' . $image_name;

						// Sideload the image
						$img_id = media_sideload_image($image_url, $post_ID, $post_title, 'id');


						// Check if sideloading was successful
						if (!is_wp_error($img_id)) {
							if ($featured_img) {
								set_post_thumbnail($post_ID, $img_id);
								$featured_img = false;
							} else {
								$images[] = $img_id;
							}
							update_post_meta($img_id, 'org_file_name', $image_name . $post_ID);
						}
					}
				}

				update_field('spro_gallary', $images, $post_ID);
			}
		}
	}


	$references = array();

	// Loop through art data and insert posts
	foreach ($art_rows as $item) {
		$art_fields = str_getcsv($item, ';');

		if (isset($art_fields[0])) {
			$references[] = $art_fields[0];
		}
	}


	// Query posts with the 'csv_item_number' custom field
	$args = array(
		'post_type' => 'prodotti', // Change to your custom post type if needed
		'posts_per_page' => -1,
		'meta_key' => 'csv_item_number',
		'meta_compare' => 'EXISTS',
	);

	$query = new WP_Query($args);

	if ($query->have_posts()) {
		while ($query->have_posts()) {
			$query->the_post();
			$post_reference = get_post_meta(get_the_ID(), 'csv_item_number', true);

			// Check if the post's 'csv_item_number' exists in the $references array
			if (!in_array($post_reference, $references)) {
				// Delete attached media
				$media_attachments = get_posts(array(
					'post_type' => 'attachment',
					'posts_per_page' => -1,
					'post_parent' => get_the_ID(),
				));

				foreach ($media_attachments as $media_attachment) {
					wp_delete_post($media_attachment->ID, true);
				}

				// Delete the post
				wp_delete_post(get_the_ID(), true);

				wpwd_log('Product deleted: ' . get_the_ID());
			}
		}

		wp_reset_postdata();
	}
}

?>
