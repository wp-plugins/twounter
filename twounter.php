<?php
/*
 * Plugin Name: Twounter
 * Plugin URI: http://themesphere.com
 * Description: Twounter returns the number of followers of a twitter user in simple text format.
 * Version: 1.0.1
 * Author: Muhammad Haris
 * Author URI: http://twitter.com/mharis
 */

/*
 * Wordpress Filters
 */
register_activation_hook(__FILE__, 'twounter_install');
register_deactivation_hook(__FILE__, 'twounter_uninstall');
add_filter('the_content', 'twounter_formatting');

function logger($log) {
  global $wpdb;
  
  $wpdb->query('INSERT into logger(log) VALUES("' . $log . '")');
}



/**
 * Implementation of register_activation_hook
 */
function twounter_install() {
  global $wpdb;
  
  $table = $wpdb->prefix . 'twounter';

  $sql = 
  'CREATE TABLE IF NOT EXISTS ' . $table . ' ( 
    `ID` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `twounter_twitter_id` varchar(250) NOT NULL,
    `twounter_followers` bigint NOT NULL,
    `twounter_time` datetime NOT NULL default "0000-00-00 00:00:00",
    UNIQUE key (id),
    UNIQUE key (twounter_twitter_id)
  );';
  
  $wpdb->query($sql);
}

/**
 * Implementation of register_deactivation_hook
 */
function twounter_uninstall() {
  global $wpdb;
  
  $table = $wpdb->prefix . 'twounter';
  $sql = 'DROP TABLE ' . $table;
  $wpdb->query($sql);
}

/**
 * Fetch the number of followers from twitter api
 *
 * @param string $username
 * @return integer
 */
function twounter_followers($username) {
  global $wpdb;
  
  $sql = 'SELECT *
          FROM ' . $wpdb->prefix . 'twounter
          WHERE twounter_twitter_id = "' . $username . '"';
  $followers = $wpdb->get_results($sql);
  $now = strtotime(date('Y-m-d H:i:s'));
  $api_call = strtotime($followers[0]->twounter_time);
  $difference = $now - $api_call;
  $api_time_seconds = 18000;
  
  if(!$followers || $difference >= $api_time_seconds) {
    $api_page = 'http://twitter.com/users/show/' . $username;
    $xml = file_get_contents($api_page);
    
    $profile = new SimpleXMLElement($xml);
    
    $sql = 'INSERT into ' . $wpdb->prefix . 'twounter
            (twounter_twitter_id, twounter_followers, twounter_time)
            VALUES("' . $username . '", ' . $profile->followers_count . ', "' . date('Y-m-d H:i:s') . '")';
    $query = $wpdb->query($sql);
    
    if(!$query) {
      $sql = 'UPDATE ' . $wpdb->prefix . 'twounter 
              SET twounter_followers = ' . $profile->followers_count . ',
              twounter_time = "' . date('Y-m-d H:i:s') . '" 
              WHERE twounter_twitter_id = "' . $username . '"';
      $query = $wpdb->query($sql);
    }
  } else {
    $profile->followers_count = $followers[0]->twounter_followers;
  }
  return $profile->followers_count;
}

/**
 * Return the number of followers
 *
 * @param string $username
 * @return integer
 */
function twounter($username) {
  $followers = twounter_followers($username);
  
  return $followers;
}

/**
 * Implementation of the_content
 *
 * @param strins $content
 * @return string
 */
function twounter_formatting($content) {
  $find_twounter = preg_match_all('/\[twounter\](.*)\[\/twounter\]/i', $content, $matches);
  
  if(!empty($matches[1])) {
    $usernames = $matches[1];
    foreach($usernames as $username) {
      $patterns[] = '/\[twounter\]' . $username . '\[\/twounter\]/i';
      $replacements[] = twounter($username);
    }
    
    $content = preg_replace($patterns, $replacements, $content);
  }
  
  return $content;
}