<?php
/*
Plugin Name: Favicon Generator
Plugin URI: http://www.think-press.com/plugins/favicon-generator
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=3441397
Description: This plugin will allow you to upload an image file of your choosing to be converted to a favicon for your WordPress site.
Author: Brandon Dove, Jeffrey Zinn
Version: 1.3
Author URI: http://www.think-press.com


Copyright 2009  Pixel jar  (email : info@pixeljar.net)

This program is free software; you can redistribute it and/or modify
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

if (!class_exists('pj_favicon_generator')) {
	
	include ('php/Directory.php');
	include ('php/Ico2_1.php');
	include ('php/Image.php');
	
    class pj_favicon_generator	{
		
		/**
		* @var string   The name the options are saved under in the database.
		*/
		var $adminOptionsName = "pj_favicon_generator_options";
		
		/**
		* PHP 4 Compatible Constructor
		*/
		function pj_favicon_generator(){$this->__construct();}
		
		/**
		* PHP 5 Constructor
		*/		
		function __construct(){
			add_action("admin_menu", array(&$this,"add_admin_pages"));
			add_action('wp_head', array(&$this,'wp_head_intercept'));
			$this->adminOptions = $this->getAdminOptions();
			if ( ! defined( 'WP_CONTENT_URL' ) )
			      define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
			if ( ! defined( 'WP_CONTENT_DIR' ) )
			      define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
			if ( ! defined( 'WP_PLUGIN_URL' ) )
			      define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
			if ( ! defined( 'WP_PLUGIN_DIR' ) )
			      define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
		}
		
		/**
		* Retrieves the options from the database.
		* @return array
		*/
		function getAdminOptions() {
			$adminOptions = array(
				"favicon" => "<empty>"
			);
			$savedOptions = get_option($this->adminOptionsName);
			if (!empty($savedOptions)) {
				foreach ($savedOptions as $key => $option) {
					$adminOptions[$key] = $option;
				}
			}
			update_option($this->adminOptionsName, $adminOptions);
			return $adminOptions;
		}
		
		function saveAdminOptions(){
			update_option($this->adminOptionsName, $this->adminOptions);
		}
		
		/**
		* Creates the admin page.
		*/
		function add_admin_pages(){
			add_menu_page("Favicon Generator", "Favicon Generator", 10, "Favicon-Generator", array(&$this,"output_sub_admin_page_0"));
		}
		
		/**
		* Outputs the HTML for the admin sub page.
		*/
		function output_sub_admin_page_0 () {

			// PATHS
			$uploaddir = WP_PLUGIN_DIR.'/favicon-generator/uploads/';
			$uploadurl = WP_PLUGIN_URL.'/favicon-generator/uploads/';
			$favicondir = WP_PLUGIN_DIR.'/favicon-generator/';
			$faviconurl = WP_PLUGIN_URL.'/favicon-generator/';
			$submiturl = preg_replace('/&[du]=[a-z0-9.%()_-]*\.(jpg|jpeg|gif|png)/is', '', $_SERVER['REQUEST_URI']);
			
			$msg = "";

			// USER UPLOADED A NEW IMAGE
			if (!empty($_FILES)) {
				$userfile = preg_replace('/\\\\\'/', '', $_FILES['favicon']['name']);
				$file_size = $_FILES['favicon']['size'];
				$file_temp = $_FILES['favicon']['tmp_name'];
				$file_err = $_FILES['favicon']['error'];
				$file_name = explode('.', $userfile);
				$file_type = strtolower($file_name[count($file_name) - 1]);
				$uploadedfile = $uploaddir.$userfile;
				
				if(!empty($userfile)) {
					$file_type = strtolower($file_type);
					$files = array('jpeg', 'jpg', 'gif', 'png');
					$key = array_search($file_type, $files);
				
					if(!$key) {
						$msg .= "ILLEGAL FILE TYPE. Only JPEG, JPG, GIF or PNG files are allowed.<br />";
					}
				
					// ERROR CHECKING
					$error_count = count($file_error);
					if($error_count > 0) {
						for($i = 0; $i <= $error_count; ++$i) {
							$msg .= $_FILES['favicon']['error'][$i]."<br />";
						}
					} else {
						if (is_file($favicondir.'/favicon.ico')) {
							if (!unlink($favicondir.'/favicon.ico')) {
								$msg .= "There was an error deleting the old favicon.<br />";
							}
						}
					
						if(!move_uploaded_file($file_temp, $uploadedfile)) {
							$msg .= "There was an error when uploading your file.<br />";
						}
						if (!chmod($uploadedfile, 0777)) {
							$msg .= "There was an error when changing your favicon's permissions.<br />";
						}
					
						$img =new Image($uploadedfile);
						$img->resizeImage(16,16);
						$img->saveImage($uploadedfile);
					
						switch ($file_type) {
							case "jpeg":
							case "jpg":
								$im = imagecreatefromjpeg($uploadedfile);
								break;
							case "gif":
								$im = imagecreatefromgif($uploadedfile);
								break;
							case "png":
								$im = imagecreatefrompng($uploadedfile);
								imagealphablending($im, true); // setting alpha blending on
								imagesavealpha($im, true); // save alphablending setting (important)
								break;
						}
						// ImageICO function provided by JPEXS.com <http://www.jpexs.com/php.html>
						ImageIco($im, $favicondir.'/favicon.ico');
						$this->adminOptions['favicon'] = $userfile;
						$this->saveAdminOptions();
						$msg .= "Your favicon has been updated.";
					}

				}
			}

			// USER HAS CHOSEN TO DELETE AN UPLOADED IMAGE
			if (!empty($_GET['d']) && is_file($uploaddir.$_GET['d'])) {
				if (!unlink ($uploaddir.$_GET['d'])) {
					$msg .= "There was a problem deleting the selected image.";
				} else {
					$msg .= "The selected image has been deleted.";
				}
			}
			
			// USER HAS CHOSEN TO CHANGE HIS FAVICON TO A PREVIOUSLY UPLOADED IMAGE
			if (!empty($_GET['u'])) {
				$file_name = explode('.', $_GET['u']);
				$file_type = $file_name[count($file_name) - 1];
				switch ($file_type) {
					case "jpeg":
					case "jpg":
						$im = imagecreatefromjpeg($uploaddir.$_GET['u']);
						break;
					case "gif":
						$im = imagecreatefromgif($uploaddir.$_GET['u']);
						break;
					case "png":
						$im = imagecreatefrompng($uploaddir.$_GET['u']);
						imagealphablending($im, true); // setting alpha blending on
						imagesavealpha($im, true); // save alphablending setting (important)
						break;
				}
				
				// ImageICO function provided by JPEXS.com <http://www.jpexs.com/php.html>
				ImageIco($im, $favicondir.'/favicon.ico');
				$this->adminOptions['favicon'] = $_GET['u'];
				$this->saveAdminOptions();
				$msg .= "Your favicon has been updated.";
			}
			?>
<div class="wrap">
	<div id="icon-options-general" class="icon32"><br /></div>
	<h2>Favicon Generator</h2>

	<form method="post" action="<?php echo $submiturl; ?>" enctype="multipart/form-data">
		<?php wp_nonce_field('update-options'); ?>
		
		
		<h3>Upload a New Image</h3>
		<p>Acceptable file types are JPG, JPEG, GIF and PNG. Note that for this to work,
			you're going to need to have PHP configured with the GD2 library.</p>
			
		<table class="form-table">
		<tr valign="top">
			<th scope="row">Favicon Source</th>
			<td>
				<input type="file" name="favicon" id="favicon" />
				<input type="submit" class="button" name="html-upload" value="Upload" />
			</td>
		</tr>
		</table>
		
		<h3>Select a Previously Uploaded File</h3>
		<p>Since this plugin stores every image you upload, you can upload as many images as you like.
			You can then come back from time to time and change your favicon. Select from the
			choices below.</p>
		<p><em><strong>Note:</strong> Some browsers hang on to old favicon images in their cache. This is
			an unfortunate side effect of caching. If you make a change to your favicon and don't
			immediately see the change, don't start banging your head against the wall. This is not an
			indication that this plugin is not working. Try
			<a href="http://en.wikipedia.org/wiki/Bypass_your_cache" target="_blank">emptying your cache</a>
			and quitting the browser.</em></p>
		<?php
			$files = dirList($uploaddir);
			for ($i = 0; $i < count($files); $i++) :
				$active = ($files[$i] == $this->adminOptions['favicon']) ? true : false;
				echo '<div style="float: left; margin-top: 20px; padding: 10px; text-align: center;'.(($active) ? ' background-color: #dddddd' : '').'">';
				echo '	<div class="choice-block" style="position: relative; width: 36px; height: 36px; border: 1px solid '.(($active) ? '#ff6666' : '#cccccc').';">';
				echo '		<img src="'.$uploadurl.$files[$i].'" title="'.$files[$i].'" alt="'.$files[$i].'" class="favicon-choices" style="position: absolute; top: 10px; left: 10px; width: 16px; height: 16px;" />';
				echo '	</div>';
				echo '	<div>';
				echo ($active) ? 'Active<br />' : '		<a href="'.$submiturl.'&d='.$files[$i].'">Delete</a><br />';
				echo ($active) ? 'Icon' : '		<a href="'.$submiturl.'&u='.$files[$i].'">Use</a>';
				echo '	</div>';
				echo '</div>';
				
			endfor;
			echo '<div class="clear"></div>'
		?>
	</form>
</div>
			<?php
		} 
		
		
		/**
		* Called by the action wp_head
		*/
		function wp_head_intercept() {
			//this is a sample function that includes additional styles within the head of your template.
			echo '<link rel="shortcut icon" href="'.WP_PLUGIN_URL.'/favicon-generator/favicon.ico" />';
		}
    }
}

//instantiate the class
if (class_exists('pj_favicon_generator')) {
	$pj_favicon_generator = new pj_favicon_generator();
}
?>