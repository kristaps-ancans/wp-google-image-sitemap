<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Google XML Sitemap for Images
 * Plugin URI:        https://github.com/kristaps-ancans/wp-google-image-sitemap
 * Description:       This plugin will generate a XML Image Sitemap for your WordPress blog. Open the <a href="tools.php?page=image-sitemap-generate-page">settings page</a> to create your image sitemap.
 * Version:           1.1.2
 * Author:            Kristaps Ancāns
 * Author URI:        https://t3hwin.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       plugin-name
 */

if (!defined('WPINC')) {
    die();
}

define('PLUGIN_NAME_VERSION', '1.1.2');

// Add Image Sitemap link in Tools list
function image_sitemap_generate_page()
{
    if (function_exists('add_submenu_page')) {
        add_submenu_page('tools.php', __('Image Sitemap'), __('Image Sitemap'), 'manage_options', 'image-sitemap-generate-page', 'image_sitemap_generate');
    }
}

add_action('admin_menu', 'image_sitemap_generate_page');

// Add Generate Sitemap link in Plugins list
function image_sitemap_action_links($links)
{
    $links = array_merge(['<a href="' . esc_url('tools.php?page=image-sitemap-generate-page') . '">' . __('Generate Sitemap', 'textdomain') . '</a>'], $links);

    return $links;
}

add_action('plugin_action_links_' . plugin_basename(__FILE__), 'image_sitemap_action_links');

// Update image sitemap on post change
add_filter('post_updated_messages', 'image_sitemap_loop');

// Replace `post_updated_messages` with `cron crap`
register_activation_hook(__FILE__, 'my_activation');

function my_activation()
{
    if (!wp_next_scheduled('my_hourly_event')) {
        wp_schedule_event(time(), 'hourly', 'my_hourly_event');
    }
}

add_action('my_hourly_event', 'do_this_hourly');

function do_this_hourly()
{
    // do something every hour
}

/* @author  VJTD3 <http://www.VJTD3.com> */
function IsImageSitemapWritable($filename)
{
    if (!is_writable($filename)) {
        if (!@chmod($filename, 0666)) {
            $pathtofilename = dirname($filename);

            if (!is_writable($pathtofilename)) {
                if (!@chmod($pathtofilename, 0666)) {
                    return false;
                }
            }
        }
    }
    return true;
}

function EscapeXMLEntities($xml)
{
    return str_replace(['&', '<', '>', '\'', '"'], ['&amp;', '&lt;', '&gt;', '&apos;', '&quot;'], $xml);
}

function image_sitemap_generate()
{
    if (isset($_POST['submit'])) {

        $st = image_sitemap_loop();

        if (!$st) {
            echo '<div class="notice notice-error"><h2>Oops!</h2><p>The XML sitemap was generated successfully but the  plugin was unable to save the xml to your WordPress root folder at <strong>' .
                $_SERVER['DOCUMENT_ROOT'] .
                '</strong>.</p><p>Please ensure that the folder has appropriate <a href="http://codex.wordpress.org/Changing_File_Permissions" target="_blank">write permissions</a>.</p><p> You can either use the chmod command in Unix or use your FTP Manager to change the permission of the folder to 0666 and then try generating the sitemap again.</p><p>If the issue remains unresolved, please post the error message in this <a target="_blank" href="http://wordpress.org/tags/google-image-sitemap?forum_id=10#postform">WordPress forum</a>.</p></div>';

            exit();
        }

        $sitemapurl = get_bloginfo('url') . '/sitemap-image.xml';
        ?>


        <div class="wrap">
            <h1>XML Sitemap for Images</h1>
            <p>Sitemaps are a way to tell Google and other search engines about web pages, images and video content on your site that they may otherwise not discover.</p>
            <div class="notice notice-success">
                <p><strong>The <a target="_blank" href="<?php echo $sitemapurl; ?>">XML Sitemap</a> was generated successfully and you can <a target="_blank" href="http://www.google.com/webmasters/sitemaps/ping?sitemap=<?php echo $sitemapurl; ?>">ping Google</a> to inform them about your updated sitemap.</strong></p>
            </div>
            <form id="options_form" method="post" action="">
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" id="sb_submit" value="Generate Image Sitemap">
                </p>
            </form>
        </div>
    <?php
    } else {
         ?>

        <div class="wrap">
            <h1>XML Sitemap for Images</h1>
            <p>Sitemaps are a way to tell Google and other search engines about web pages, images and video content on your site that they may otherwise not discover.</p>
            <form id="options_form" method="post" action="">
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" id="sb_submit" value="Generate Image Sitemap">
                </p>
            </form>
        </div>

<?php
    }
}

function image_sitemap_loop()
{
    global $wpdb;

    $posts = $wpdb->get_results(
        "SELECT id, post_parent, post_content, guid, post_type FROM $wpdb->posts wposts WHERE ((wposts.post_type = 'post') AND (wposts.post_status='publish')) OR ((wposts.post_type = 'page') AND (wposts.post_status='publish')) OR ((wposts.post_type = 'attachment') AND (wposts.post_status='inherit') AND ((wposts.post_mime_type = 'image/jpg') OR (wposts.post_mime_type = 'image/gif') OR (wposts.post_mime_type = 'image/jpeg') OR (wposts.post_mime_type = 'image/png')))"
    );

    if (empty($posts)) {
        return false;
    } else {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<!-- Created by (http://wordpress.org/extend/plugins/google-image-sitemap/) extended version (https://t3hwin.com/) -->' . "\n";
        $xml .= '<!-- Generated-on="' . date('F j, Y, g:i a') . '" -->' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ($posts as $post) {
            if ($post->post_type == 'attachment') {
                if ($post->post_parent != 0) {
                    $post_images = wp_get_attachment_image_src($post->id, 'full');
                    $post_image = $post_images[0];
                    $post_title = get_the_title($post->id);

                    $images[$post->post_parent][] = [$post_title, $post_image];
                }
            }
        }

        foreach ($images as $k => $image_list) {
            $permalink = get_permalink($k);
            $img = '';

            if (!empty($permalink)) {
                foreach ($image_list as $key => $value) {
                    $img .= '<image:image><image:loc>' . $value[1] . '</image:loc><image:title>' . $value[0] . '</image:title></image:image>';
                }

                $xml .= '<url><loc>' . EscapeXMLEntities($permalink) . '</loc>' . $img . '</url>';
            }
        }

        $xml .= "\n</urlset>";
    }

    $image_sitemap_url = $_SERVER['DOCUMENT_ROOT'] . '/sitemap-image.xml';

    if (IsImageSitemapWritable($_SERVER['DOCUMENT_ROOT']) || IsImageSitemapWritable($image_sitemap_url)) {
        if (file_put_contents($image_sitemap_url, $xml)) {
            return true;
        }
    }

    return false;
}
?>
