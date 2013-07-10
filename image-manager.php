<?php

/**
 * Plugin Name: Daniy Image Manager
 * Plugin URI:  http://www.murdanieko.com/
 * Description: Save more MySQL queries when displaying post galleries of post thumbnails, by saving the image attachments data in mostly empty table <code>post_content_filtered</code> field in <code>wp_posts</code> table
 * Version:     1.3
 * License:     GPLv3
 * Author:      Murdani Eko
 * Author URI:  http://www.murdanieko.com/
 * Last Change: July 6th, 2013
 */

/**
 * @package Daniy Image Manager
 * @author  Murdani Eko
 */

/**
 * Employs the 'mostly-empty' wp_posts  field to save attached image data
 * to reduce MySQL queries when certain attachments for a post is being requested
 *
 * For example: when a post have 10 attachments, using the old code the_image()
 * will run more than 10 additional queries to the post which generated from
 * - get_posts()
 * - get_post_meta()
 */


// Initial settings, defining constants 
define('DNY_FORCE_FLUSH', TRUE);
define('THEME_IMAGE_FALLBACK', plugins_url( 'image_default_fallback.jpg' , __FILE__ ));



class DNY_Image_Manager {

  private $size;      //
  private $images;    //
  private $htmls;     // array of ready to output values like <p><img src="" class="" title="" /></p>
  private $html;      // final form of ready to output images
  private $init;      // initialization values
  

  

  /**
   * Class constructor. Setup default initialization values
   * 
   * @param   array   $init
   */
  function  __construct( $init = array() ) {
    
    $defaults = array(
        'images_per_row'        => NULL,                    // when in thumbnail gallery, how much images do you put in a row?
        'last_row_class'        => NULL,                    // class name to reset the layout, usually reset the right margin
        'insert_image_link'     => TRUE,                    // do each image links to its own attachment page?
        'insert_image_title'    => TRUE,                    // when an image is wrapped within <a> tag, this should be set to FALSE
        'before_wrapper'        => NULL,                    // do you want to add something before the gallery?
        'after_wrapper'         => NULL,                    // do you want to add something after the gallery?
        'image_size'            => 'thumbnail',             // the safest value is 'thumbnail' and 'original'
        'image_id'              => 'attachment-image-',
        'image_class'           => 'attachment-image',
        'image_container_tag'   => NULL,
        'image_container_id'    => NULL,
        'image_container_class' => NULL,
        'image_wrapper_tag'     => 'div',
        'image_wrapper_id'      => 'attachment-container',
        'image_wrapper_class'   => NULL,
    );
    $this->init = array_merge( $defaults, $init );
    $this->size = $this->init['image_size'];

  }


  /**
   * Check the value of a data placeholder
   * 
   * @param string  $var
   */
  function debug( $var ) {
    if( function_exists('tree_dump') ) 
      tree_dump( $this->$var );
    
    else 
      var_dump( $this->$var );
  }
  

  /**
   * Fill up the $images property either from wp_posts.emptytable or postmeta
   *
   * Do the procedural task. If the data is already in wp_posts.emptytable,
   * and is_unserializeable
   *
   * @global object $post
   * @return array
   */
  function populate_images() {
    global $post;

    // get the data from wp_posts.emptytable
    $images = $this->get_data($post->ID);

    // does the wp_posts.emptytable data exists?
    if( $images ) { 

      // yes it exists! format first then return it immediately
      return $this->images = $this->format_image_data($images); 

    } else { 

      // nope, there's no data in it or it's not unserializeable. get from postmeta first
      $images_postmeta = $this->get_image_postmeta($post->ID);

      // ask again.. does this post has any image attachments?
      // if not, return to FALSE
      if( !$images_postmeta ) return FALSE;

      // save the data to wp_posts.emptytable
      $this->save_data($images_postmeta); 

      // format first then return it immediately
      return $this->images = $this->format_image_data($images_postmeta);

    }
  }


  /**
   * Check the data in target wp_posts field
   *
   * If data exists, return the unserialized data, else return FALSE flag
   *
   * @global object $post
   * @return mixed
   */
  function get_data() {
    global $post;

    // if data is not empty or FALSE, try to unserialize
    if( $post->post_content_filtered ):
      $tmp = @unserialize($post->post_content_filtered);

      // yes, it's unserializeable, return array immediately
      if( $tmp ) return $tmp;
    endif;

    // nope, it's a common string, not a serialized array, return FALSE flag
    return FALSE;
  }
  
  
  /**
   * Save the returned postmeta data into wp_posts empty table
   * 
   * @global  object  $post
   * @global  object  $wpdb
   * @param   array   $array
   * @return  mixed
   */
  function save_data( $array ) {
    global $post, $wpdb;

    // if target field is not empty (maybe it's a valid wp_posts.emptytable value),
    // and you don't want to force replace it with the serialized data
    // stop, and fire up FALSE flag
    if( !empty ($post->post_content_filtered) && !DNY_FORCE_FLUSH ) return FALSE;

    // serialize data so we can save it into database
    $save_data = serialize( $array );

    // run the wp update
    $wpdb->update(
            $wpdb->prefix . 'posts',
            array(
                'post_content_filtered' => $save_data,
            ),
            array( 'ID' => $post->ID )
            );

    return;

  }


  /**
   * Format the given raw data to a ready to use array
   *
   * It adds the appropriate baseurl (http://www.example.com/wp-content/uploads)
   * and the basedir (/home/myaccount/public_html/wp-content/uploads) to each
   * of the file values. Also fixes the different directory separator between
   * UNIX and WINDOWS environment
   *
   * @param   array $images
   * @return  array
   */
  function format_image_data( $images ) {

    $updirs = wp_upload_dir();

    // list of available image sizes in WordPress
    $maybe_sizes = array('thumbnail', 'medium', 'large', 'original');

    // set FALSE if images input is empty
    if( !$images ) return FALSE;

    foreach( $images as $n => $img ):
    foreach($maybe_sizes as $size_name):
      if( isset( $img['sizes'][$size_name] ) ):

        $file = '/' . $img['sizes'][$size_name]['file'];

        $images[$n]['sizes'][$size_name]['filepath'] = $this->fix_image_path( trim($updirs['basedir'], '/') . $file );
        $images[$n]['sizes'][$size_name]['fileurl']  = $this->fix_image_url( trim($updirs['baseurl'], '/') . $file );

      endif;
    endforeach;
    endforeach;

    return $images;
  }


  /**
   * Get all children from a post and formats its title and path data
   * with all of its available sizes
   *
   * @param   int   $post_id
   * @return  array
   */
  function get_image_postmeta( $post_id ) {

    $im_metadata = NULL;

    // setup the attachment query arguments
    $att_array = array(
      'post_parent'     => $post_id,
      'post_type'       => 'attachment',
      'numberposts'     => -1,
      'post_mime_type'  => 'image',
      'order_by'        => 'menu_order'
    );
    $attachments = get_children($att_array);

    if( $attachments ) {

      // we need another counter because $num is undependable
      $i = 0;
      foreach($attachments as $att) {

          $updirs = wp_upload_dir();
          $file = get_post_meta( $att->ID, '_wp_attachment_metadata', TRUE);
          $filepath = $updirs['basedir'] . DIRECTORY_SEPARATOR . $file['file'];

          // does the file REALLY EXISTS?? check first!
          if( file_exists($filepath) ) {

            // setup the main data: ID and title
            $im_metadata[$i] = array(
                'att_ID'   => $att->ID,
                'title' => $att->post_title,
                'link'  => get_attachment_link($att->ID),
                );

            // setup the original image data: path and sizes
            $im_metadata[$i]['sizes']['original'] = array(
                'file'    => $file['file'],
                'width'   => $file['width'],
                'height'  => $file['height'],
                );

            // extract the $subdir value from original size
            $basename = basename($file['file']);
            $subdir = str_replace($basename, "", $file['file']);

            // list of available image sizes in WordPress
            $maybe_sizes = array('thumbnail', 'medium', 'large');

            // setup the additional other image data if exists
            foreach($maybe_sizes as $size_name):
              if( isset($file['sizes'][$size_name]) ):
                $im_metadata[$i]['sizes'][$size_name]['file']   = trim( $subdir ) . $file['sizes'][$size_name]['file'];
                $im_metadata[$i]['sizes'][$size_name]['width']  = $file['sizes'][$size_name]['width'];
                $im_metadata[$i]['sizes'][$size_name]['height'] = $file['sizes'][$size_name]['height'];
              endif;
            endforeach;
          }
          
          // increment our counter by one step
          $i++;

      }

      return $im_metadata;

    }

    return FALSE;

  }


  /**
   * Populate the $htmls property with formatted <img> tags
   *
   * @global object $post
   * @return <type> 
   */
  function generate_htmls() {
    global $post;

    $html = NULL;
    $images = $this->populate_images();

    // only process if $images is not empty or not FALSE
    if( $images ):
    foreach( $images as $n => $img ):

      // is this size exists?
      if( !isset($img['sizes'][$this->size]) ) {

        // this size doesn't exists, put NULL value to htmls array
        $this->htmls[] = NULL;

      } else {

        //
        if( $this->init['images_per_row'] && $this->init['last_row_class'] ) {

          $add_class = ( $this->is_multiples_of($n, $this->init['images_per_row']) ) ? ' ' . $this->init['last_row_class'] : '';
          $image_class = $this->init['image_class'] . $add_class;

        } else {

          $image_class = $this->init['image_class'];

        }       
        

        // yep, it exists. let's build the <img> tag
        $imginit = array(
            'src'     => $img['sizes'][$this->size]['fileurl'],
            'alt'     => $img['title'],
            'class'   => $image_class,
            'id'      => $this->init['image_id'] ? $this->init['image_id'] . $img['att_ID'] : '',
            'width'   => $img['sizes'][$this->size]['width'],
            'height'  => $img['sizes'][$this->size]['height'],
        );

        if( $this->init['insert_image_title'] ) $imginit['title'] = $img['title'];
        $element = $this->construct_img_tag( $imginit );


        // should it links to its own attachment page? wrap each <img> with <a> tag
        if( $this->init['insert_image_link'] ) {
          $link_format = '<a href="%s">%s</a>';
          $element = sprintf(
                  $link_format,
                  $img['link'],
                  $element
                  );
        }

        // does it has the image_container tag? wrap each with its container tag
        if( $this->init['image_container_tag'] ) {
          $cont_tag    = $this->init['image_container_tag'];
          $cont_class  = ( $this->init['image_container_class'] ) ? ' class="' . $this->init['image_container_class'] . '"' : '';
          $cont_id     = ( $this->init['image_container_id'] ) ? ' id="' . $this->init['image_container_id'] . '"' : '';
          $cont_format = '<' . $cont_tag . $cont_class . $cont_id . '>%s</' . $cont_tag . '>';
          $element     = sprintf($cont_format, $element);
        }

        $this->htmls[] = $element;
      }

    endforeach;
    endif;

    return $this->htmls;
  }


  /**
   * Finalize the $html property by glueing the $htmls array into the $html property
   *
   * Add wrapper tag if any
   * Add before_wrapper element if any
   * Add after_wrapper element if any
   *
   * @return string
   */
  function finalize_html() {

    // is $htmls an array? or is it empty? fire up FALSE flag immediately
    if( !is_array($this->htmls) || empty($this->htmls) ) return FALSE;

    // glued the $htmls array into a string
    $html = implode(PHP_EOL, $this->htmls);

    // does it has the image_wrapper tag? wrap this gallery with a wrapper tag
    if( $this->init['image_wrapper_tag'] ) {
      $wrap_tag    = $this->init['image_wrapper_tag'];
      $wrap_class  = ( $this->init['image_wrapper_class'] ) ? ' class="' . $this->init['image_wrapper_class'] . '"' : '';
      $wrap_id     = ( $this->init['image_wrapper_id'] ) ? ' id="' . $this->init['image_wrapper_id'] . '"' : '';
      $wrap_format = '<' . $wrap_tag . $wrap_class . $wrap_id . '>%s</' . $wrap_tag . '>';
      $html        = sprintf($wrap_format, $html);
    }

    // does it has a before_wrapper element? get it prefixed
    if( $this->init['before_wrapper'] ) $html = $this->init['before_wrapper'] . PHP_EOL . $html;

    // does it has an after_wrapper element? get it suffixed
    if( $this->init['after_wrapper'] ) $html = $html . PHP_EOL . $this->init['after_wrapper'];

    // concatenation finished. save it into $html property
    $this->html = $html;
  }


  /* ==========================================================================
     COMMON HELPERS
     ========================================================================== */
  
  /**
   * Output the final html onto the screen
   */
  function output_prop( $prop ) {
    echo $this->$prop;
  }


  /**
   * Return the final html to save into a string
   *
   * @return string
   */
  function return_prop( $prop ) {
    return $this->$prop;
  }


  function construct_img_tag( $array ) {

    // set a data placeholder
    $attributes = array();

    // iterate item's input
    foreach ($array as $key => $val):
      $attributes[] = sprintf('%s="%s"', $key, $val);
    endforeach;

    // unify all values to string
    $attribute = implode(" ", $attributes);

    // return by prepend and append tag element
    return '<img ' . $attribute . ' />';
  }

  
  /**
   * Check if a wp_posts.emptytable has no value
   *
   * @global object $post
   * @return bool
   */
  function is_target_empty() {
    global $post;

    if( empty($post->post_content_filtered) ) 
      return TRUE;
    
    return FALSE;

  }


  /**
   * Change the backslash to forward slash
   *
   * @param string $image_url
   * @return string
   */
  function fix_image_url( $image_url ) {
    return str_replace( DIRECTORY_SEPARATOR, "/", $image_url );
  }


  /**
   * Change the forward slash to backslash
   *
   * @param string $image_url
   * @return string
   */
  function fix_image_path( $image_path ) {
    return str_replace( "/", DIRECTORY_SEPARATOR, $image_path );
  }


  /**
   * Check the $n is the multiples result of $multiplier
   *
   * Useful in a foreach iteration to output thumbnail images.
   * If you have a set of thumbnails inside a container of certain width,
   * each images has 10px margin-right which need to be reset after 4 times in a row
   * To calculate which image needed to be reset, this function
   * is a great help, saves you two lines of code
   *
   *
   * @param int $n
   * @param int $multiplier
   * @return bool
   */
  function is_multiples_of($n, $multiplier) {

    $modulus = ($n + 1) % $multiplier;
    if( $modulus == 0 ) return TRUE;
    return FALSE;

  }

}
// end of class DNY_Image_Manager



/**
 * Function to be hooked after plugin deactivated
 * http://wordpress.stackexchange.com/questions/25910/uninstall-activate-deactivate-a-plugin-typical-features-how-to/25979#25979
 */
function imwp_deactivate() {
  global $wpdb, $table_prefix;
  
  if ( ! current_user_can( 'activate_plugins' ) )
      return;
  $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
  check_admin_referer( "deactivate-plugin_{$plugin}" );
  
  $sql = "UPDATE {$table_prefix}posts SET post_content_filtered = ''";
  $wpdb->query($sql);
  
  return;

}
//register_deactivation_hook( __FILE__, 'imwp_deactivate' );


/* ==========================================================================
   FRONT END HELPERS
   WordPress template files should use these functions
   rather than instantiating above class directly
   ========================================================================== */


/**
 * Display the image gallery for a post
 *
 * @param   $array  $init
 */

function imwp_view_gallery( $init = array() ) {

  $img = new DNY_Image_Manager( $init );
  $img->generate_htmls();
  $img->finalize_html();
  $img->output_prop('html');
  unset($img);
  
}


/**
 * Display only the first image attached to a post
 *
 * Useful to display beside a content excerpt on an archive page
 * @param   string $image_class
 */
function imwp_get_thumbnail( $image_class = NULL ) {
  
  $post_ID = get_the_ID();
  if( has_post_thumbnail( $post_ID ) )
    return get_the_post_thumbnail ( $post_ID, 'thumbnail', array('class' => $image_class) );

  $init = array(
      'insert_image_link' => FALSE,
      'insert_image_title'=> FALSE,
      'image_class'       => $image_class,
      );
  $img = new DNY_Image_Manager( $init );
  $img->generate_htmls();
  $html = $img->return_prop('htmls');

  $out = isset($html[0]) ? $html[0] : imwp_get_image_fallback( array('class' => $image_class) );

  return $out;
  unset($img);

}


function imwp_view_thumbnail( $image_class = NULL ) {
  echo imwp_get_thumbnail( $image_class );
}



function imwp_get_image_fallback( $init = array() ) {

  $ims = getimagesize( THEME_IMAGE_FALLBACK );
  $defaults = array(
      'class'   => 'attachment-thumbnail',
      'alt'     => 'Thumbnail preview',
      'title'   => NULL,
      'width'   => $ims[0],
      'height'  => $ims[1],
  );
  $init = array_merge($defaults, $init);

  $format = '<img src="%s" class="%s" alt="%s" title="%s" width="%s" height="%s" />';
  $img = sprintf(
          $format,
          THEME_IMAGE_FALLBACK,
          $init['class'],
          $init['alt'],
          $init['title'],
          $init['width'],
          $init['height']
          );

  return $img;
  
}


/**
 * Force to empty wp_posts.emptytable field everytime we update a post
 *
 * @global object $wpdb
 * @global object $post
 * @return void
 */
function force_empty_field() {
  global $wpdb, $post;
  
  if( !$post ) 
    return;

  if( !defined('DNY_FORCE_FLUSH') && !DNY_FORCE_FLUSH ) 
    return FALSE;

  // flush the wp_posts.emptytable
  $wpdb->update(
          $wpdb->prefix . 'posts',
          array(
              'post_content_filtered' => "",
          ),
          array( 'ID' => $post->ID )
          );

  return;

}
add_action('save_post', 'force_empty_field');
