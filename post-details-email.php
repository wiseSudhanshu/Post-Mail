<?php
/*
Plugin Name:       Post Details Email
Plugin URI:        https://sudhanshu.wisdmlabs.net/
Description:       Sends mail to admin of all the posts published in a day
Version:           1.0.0
Author:            Sudhanshu Rai
Author URI:        https://sudhanshu.wisdmlabs.net/
License:           GPL-2.0+
License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain:       pde
Domain Path:       /languages
*/

if(!defined('WPINC')){
    die;
}
   
   
if(!defined('WPMP_PLUGIN_DIR'))
{
    define('WPMP_PLUGIN_DIR',plugin_dir_url(__FILE__));
}
   
   
add_action( 'wp', 'schedule_daily_posts_email' );
function schedule_daily_posts_email() {
    if ( ! wp_next_scheduled( 'send_daily_posts_email' ) ) {
        wp_schedule_event( strtotime( 'today 11:00pm' ), 'daily', 'send_daily_posts_email' );
    }
}
   
add_action( 'send_daily_posts_email', 'send_daily_posts_email' );
   
   
function send_daily_posts_email() {
    //date
    $today = date('Y-m-d');
    
    //  posts published today
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'date_query' => array(
            array(
                'year' => date( 'Y' ),
                'month' => date( 'm' ),
                'day' => date( 'd' ),
            ),
        ),
    );
    $posts = get_posts( $args );

    
    // Create email message
    $message = "The following posts were published on $today:\r\n\r\n";
    foreach ( $posts as $post ) {
        $message .= $post->post_title . " (ID: " . $post->ID . ")\r\n";
        $url = get_permalink($post);

        $message.="Meta URl ".$url."\r\n";

        $response = wp_remote_retrieve_body(wp_remote_get($url));
        $metaDescriptionMsg = sr_found_meta_description($response);
        $metaTitleMsg = sr_found_meta_title($response);
        
        $message.="Meta Description ".$metaDescriptionMsg."\r\n";
        $message.="Meta Title ".$metaTitleMsg."\r\n";
        $message.="Page Speed Score ".get_page_speed_score($url)."\r\n";
    }
       
    // Send email
    wp_mail( get_option( 'admin_email' ), 'Daily Posts', $message );
}

function sr_found_meta_description($htmlResponse)
{
    $word = '<meta name="description" content="';

    $index = strpos($htmlResponse, $word);
    $metaDescriptionMsg = '';

    if ($index !== false) {

        //Get the end index of the meta tag
        $end = strpos($htmlResponse, '>', $index);
        //Exclude the <meta name="description" Content=" part and get the only content
        $start = $index + 34;
        $length = $end - $start - 3;
        $metaDescriptionMsg = substr($htmlResponse, $start, $length);

    } else {
        $metaDescriptionMsg = "No Meta Description Found";
    }

    return $metaDescriptionMsg;

}
   
function sr_found_meta_title($htmlResponse)
{
    $word = '<title>';
    $index = strpos($htmlResponse, $word);
    $metaTitle = '';
    if ($index !== false) {
        $end = strpos($htmlResponse, '</title>', $index);
        $start = $index + 7;
        $length = $end - $start;
        $metaTitle = substr($htmlResponse, $start, $length);
    } else {
        $metaTitle = "No Title Found";
    }

    return $metaTitle;
}

function get_page_speed_score($url) {

    $api_key = "416ca0ef-63e4-4caa-a047-ead672ecc874"; // your api key
    $new_url = "http://www.webpagetest.org/runtest.php?url=".$url."&runs=1&f=xml&k=".$api_key; 
    $run_result = simplexml_load_file($new_url);
    $test_id = $run_result->data->testId;

    $status_code=100;
    
    while( $status_code != 200){
        sleep(10);
        $xml_result = "http://www.webpagetest.org/xmlResult/".$test_id."/";
        $result = simplexml_load_file($xml_result);
        $status_code = $result->statusCode;
        $time = (float) ($result->data->median->firstView->loadTime)/1000;
    };

    return $time;
}