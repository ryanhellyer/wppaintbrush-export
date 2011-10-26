<?php
/*
Plugin Name: WP Paintbrush Theme Exporter
Plugin URI: http://pixopoint.com/
Description: Allows users to export their WP Paintbrush theme as a standard WordPress theme zip file
Author: PixoPoint
Version: 0.9
Author URI: http://pixopoint.com/
*/


/**
 * Do not continue processing if WP Paintbrush not already loaded
 * @since 0.1
 */
if ( !defined( 'WPPB_SETTINGS' ) )
	return;

/**
 * wppb_export_zip()
 * @description Zips the templates into a regular WordPress theme
 * @since       0.8.1
 */
function wppb_export_zip() {

	// Theme specs.
	$name = get_bloginfo( 'name' );
	$folder = sanitize_title( get_bloginfo( 'name' ) );
			
	// Set current userinfo (allows us to grab the current author names later on)
	global $current_user;
	get_currentuserinfo();

	// Headers for theme files
	function pixopoint_theme_header( $title, $name ) {
		return '<?php
/**
 * @package WordPress
 * @subpackage ' . $name . '
 *
 * ' . $title . '
 */

?>';
	}
	
	// Removes pointless opening and closing of PHP tags
	function pixopoint_remove_openclose_php( $input ) {
		return str_replace( '?><?php', '', $input );
	}
	
	// Creating data file
	$options = get_option( WPPB_SETTINGS );
	foreach( $options as $name2=>$key ) {
		$data .= WPPB_BLOCK_SPLITTER;
		$data .= WPPB_NAME_SPLIT_START . $name2 . WPPB_NAME_SPLIT_END;
		$data .= $key;
	}
	$options = get_option( WPPB_DESIGNER_SETTINGS );
	$data .= WPPB_BLOCK_SPLITTER;
	$data .= WPPB_NAME_SPLIT_START;
	$data .= 'paintbrush_designer';
	$data .= WPPB_NAME_SPLIT_END;
	foreach( $options as $name2=>$key ) {
		$data .= $name2;
		$data .= '|';
		$data .= $key;
		$data .= '}';
	}

	// Create CSS
	$css = '/*
	Theme Name: ' . $name . '
	Theme URI: ' . home_url() . '
	Description: ' . get_bloginfo( 'description' ) . '
	Author: ' . $current_user->user_firstname . ' ' . $current_user->user_lastname . ' (' . $current_user->display_name . ')
	Version: ' . ( ( 100 + get_wppb_option( 'version' ) ) / 100 ) . '
*/

' . get_wppb_option( 'css' );
	// Creating array of require CSS classes
	$css_requirements = array(
		'.alignleft',
		'.aligncenter',
		'.alignright',
		'.wp-caption',
		'.wp-caption-text',
		'.gallery-caption',
		'.sticky',
		'.bypostauthor'
	);
	// Creating reminder string
	foreach ( $css_requirements as $needle ) {
		if ( strpos( $css, $needle ) === false )
			$css_reminder .= $needle . ', ';
	}

	// Finalizing CSS advice for style.css file
	if ( $css_reminder )
		$css = $css . "\n\n/* You have not included some useful CSS classes. We recommend you include the " . $css_reminder . " CSS classes in your theme */";

	// Setting background image URLs to correct folder
	$css = str_replace( wppb_storage_folder( 'images', 'url' ) . '/', 'images/', $css );

	// Load template files
	$files = array(
		$folder . '/data.tpl'             => $data,
		$folder . '/functions.php'        => pixopoint_remove_openclose_php( pixopoint_theme_header( 'Functions', $name ) . '<?php ' . wppb_functions_dot_php() ),
		$folder . '/license.txt'          => file_get_contents( get_template_directory() . '/license.txt' ),
		$folder . '/style.css'            => $css,
	);
	
	// Function for adding extra templates to the zip file
	function wppb_add_template_to_zip( $template, $title, $name, $files, $folder ) {
		if ( '' != get_wppb_option( $template ) ) {
			$files[$folder . '/' . $template . '.php'] = (
				pixopoint_theme_header( $title, $name ) . // Add in the file header
				pixopoint_remove_openclose_php( /* Remove ?><?php crap */
					do_shortcode( get_wppb_option( $template ) ) // Convert the shortcodes to PHP
				)
			);
		}
		return $files;
	}
	
	// Plowing through and adding each of the optional template files
	$files = wppb_add_template_to_zip( 'footer', 'Footer', $name, $files, $folder );
	$files = wppb_add_template_to_zip( 'index', 'Index', $name, $files, $folder );
	$files = wppb_add_template_to_zip( 'front_page', 'Front Page', $name, $files, $folder );
	$files = wppb_add_template_to_zip( 'home', 'Blog', $name, $files, $folder );
	$files = wppb_add_template_to_zip( 'page', 'Page', $name, $files, $folder );
	$files = wppb_add_template_to_zip( 'page_template_1', 'Page template 1', $name, $files, $folder );
	$files = wppb_add_template_to_zip( 'page_template_2', 'Page template 2', $name, $files, $folder );
	$files = wppb_add_template_to_zip( 'single', 'Single', $name, $files, $folder );
	$files = wppb_add_template_to_zip( 'archive', 'Archive', $name, $files, $folder );
	$files = wppb_add_template_to_zip( 'comments', 'Comments', $name, $files, $folder );
	
	// Add embedded fonts
	/*
	foreach ( wppb_embeddable_fonts() as $font => $details ) {
		if ( 'on' == get_wppb_option( 'fontembed_' . $font ) ) {
			$font_dir = str_replace( 'wppb_INTERNAL_FONT_', get_template_directory() . '/fonts', $details['url'] ); // 
			if ( $details['url'] != $font_dir )
			$files[$folder . '/footer.php'] = $files[$folder . '/footer.php'] . file_get_contents( get_template_directory() . '/footer.php' );
		}
	}
	die;
	*/
			
	// Adding actual header and footers in (header does not use wppb_add_template_to_zip() since it messes up the PHPDoc comment at top of template file
	$files[$folder . '/header.php'] = (
		pixopoint_theme_header( 'header', $name ) .
		file_get_contents( get_template_directory() . '/header.php' ) .
		pixopoint_remove_openclose_php(
			do_shortcode(
				get_wppb_option( 'header' )
			)
		)
	);
	$files[$folder . '/header.php'] = str_replace( "<?php eval( '?>' . do_shortcode( get_wppb_option( 'header' ) ) . '<?php ' ); ?>", '', $files[$folder . '/header.php'] );
	$files[$folder . '/footer.php'] = $files[$folder . '/footer.php'] . file_get_contents( get_template_directory() . '/footer.php' );
	$files[$folder . '/footer.php'] = str_replace( "<?php eval( '?>' . do_shortcode( get_wppb_option( 'footer' ) ) . '<?php ' ); ?>", '', $files[$folder . '/footer.php'] );
	
	// Load image files
	$file_list = wppb_settings_list_files( wppb_storage_folder( 'images' ) . '/' ); // Grab list of  files in folder
	foreach ( $file_list as $file ) {
		$files[$folder . '/images/' . $file] = file_get_contents( wppb_storage_folder( 'images' ) . '/' . $file );
	}
	
	// Load scripts files
	$files[$folder . '/scripts/html5.js'] = file_get_contents( get_template_directory() . '/scripts/html5.js' );
	// Dropdown menus
	if ( 'on' == get_wppb_option( 'script_menu' ) )
		$files[$folder . '/scripts/menu.js'] = file_get_contents( get_template_directory() . '/scripts/menu.js' );
	// Anything slider jQuery plugin
	if ( 'on' == get_wppb_option( 'script_anythingslider' ) ) {
		$files[$folder . '/scripts/jquery.easing.1.2.js'] = file_get_contents( get_template_directory() . '/scripts/jquery.easing.1.2.js' );
		$files[$folder . '/scripts/jquery.anythingslider.js'] = file_get_contents( get_template_directory() . '/scripts/jquery.anythingslider.js' );
		$files[$folder . '/scripts/anythingslider.init.js'] = file_get_contents( get_template_directory() . '/scripts/anythingslider.init.js' );
	}
	
	// Create zip file
	$zip = new ZipArchive();
	$rand = rand();
	$zip->open( 'temp' . $rand . '.tmp', ZIPARCHIVE::CREATE );
	if ( $files ) foreach( $files as $localname => $source ) {
		if ( is_file( $source ) )
			$zip->addFile( $source, $localname );
		else
			$zip->addFromString( $localname, $source );
	}
	$zip->close();
	
	// Downloading zip
	header( 'Content-type: application/zip' ); // File header
	header( 'Content-Disposition: attachment; filename="' . $folder . '.zip"' ); // File header
	readfile( 'temp' . $rand . '.tmp' ); // Read temporary file from disk
	unlink( 'temp' . $rand . '.tmp' ); // Delete temporary file
	die; // Kill execution since all done now
}

