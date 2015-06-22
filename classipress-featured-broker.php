<?php
/*
Plugin Name: Classipress Featured Broker
Plugin URI: http://atmoz.org/classipress-featured-broker/
Description: Add a widget to display one (1) random user
Version: 0.0.1
Author: Nathan Johnson
Author URI: http://atmoz.org/
*/

/**
 * Adds Broker_Widget widget.
 */
class Broker_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			'broker_widget', // Base ID
			__( 'Broker Options', 'text_domain' ), // Name
			array( 'description' => __( 'Display Broker Widget', 'text_domain' ), ) // Args
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		// Enqueue Plugin CSS only on pages that display the widget
		wp_enqueue_style( 'featured-broker', plugin_dir_url( __FILE__ ) . 'style.css' );
		
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ). $args['after_title'];
		}
		cpc_get_featured_brokers( $instance );
		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Featured Broker', 'text_domain' );
		$user = ! empty( $instance['user'] ) ? $instance['user'] : __( '', 'text_domain' );
		?>
		<p>
		<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>

<!-- select membership group -->
<?php
	$args = array(
		'post_type' => array( 'package-membership' )
	);
	$testquery = new WP_Query( $args );

	if ( $testquery->have_posts() ) {
		?><p>
      <label for="<?php echo $this->get_field_id('user'); ?>">Membership: 
        <select class='widefat' id="<?php echo $this->get_field_id('user'); ?>"
                name="<?php echo $this->get_field_name('user'); ?>" type="text"> <?php
		foreach( $testquery->posts as $post ){
			?>
          <option value='<?php echo $post->post_title; ?>'<?php echo ($user==$post->post_title)?'selected':'nope'; ?>>
            <?php echo $post->post_title; ?>
          </option>
			<?php
		}
		    ?></select>                
      </label>
     </p> <?php
	}
?>
		<?php 
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['user'] = ( ! empty( $new_instance['user'] ) ) ? strip_tags( $new_instance['user'] ) : '';

		return $instance;
	}

    /**
     * Gets the options for a widget of the specified name.
     *
     * @param string $widget_id Optional. If provided, will only get options for the specified widget.
     * @return array An associative array containing the widget's options and values. False if no opts.
     */
    public static function get_dashboard_widget_options( $widget_id='' )
    {
        //Fetch ALL dashboard widget options from the db...
        $opts = get_option( 'widget_broker_widget' );

        //If no widget is specified, return everything
        if ( empty( $widget_id ) )
            return $opts;

        //If we request a widget and it exists, return it
        if ( isset( $opts[$widget_id] ) )
            return $opts[$widget_id];

        //Something went wrong...
        return false;
    }

    /**
     * Gets one specific option for the specified widget.
     * @param $widget_id
     * @param $option
     * @param null $default
     *
     * @return string
     */
    public static function get_dashboard_widget_option( $widget_id, $option, $default=NULL ) {

        $opts = self::get_dashboard_widget_options($widget_id);

        //If widget opts don't exist, return false
        if ( ! $opts )
            return false;

        //Otherwise fetch the option or use default
        if ( isset( $opts[$option] ) && ! empty($opts[$option]) )
            return $opts[$option];
        else
            return ( isset($default) ) ? $default : false;

    }
} // class Foo_Widget

// register Foo_Widget widget
function register_broker_widget() {
    register_widget( 'Broker_Widget' );
}
add_action( 'widgets_init', 'register_broker_widget' );


/**
 * Simple helper function to grab the number of listings a user has
 */
function cpc_broker_listings( $theuser ){
	$args = array(
		'author' => $theuser,
		'post_type' => array( 'ad_listing' ),
	);
	$author_total = new WP_Query( $args );
	
	if( $author_total->have_posts() ){
		return count( $author_total->posts );
	}
	return false;
}

function cpc_using_classipress() {

	// Test if using the ClassiPress theme or child theme
	$my_theme = wp_get_theme();
	if( !($my_theme->get( 'Name' ) == 'ClassiPress' || $my_theme->get( 'Template' ) == 'classipress' )){
		return false;
	}
	return true;
}

function cpc_list_brokers() {

}

function cpc_broker_list_shortcode() {

}

function cpc_get_featured_brokers( $instance ) {	

	global $wpdb, $current_user;
	$type = $instance['user'];

	// If not using the ClassiPress theme, don't display the Widget
	if( !cpc_using_classipress() ){
		?>
		<style>
		.widget_broker_widget {
			display: none !important;
		}
		</style>
		<?php
		return;
	}
	
	// If ClassiPress More Memberships plugin is used
	if( function_exists('ukljuci_ad_limit_jms') ) {
		// This plugin introduces a different way to handle Memberships
		$sql = "	SELECT  `ID` 
					FROM  `$wpdb->posts` 
					WHERE  `post_title` =  '$type'
					LIMIT 1";
		
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, '' ) );
		foreach ( $rows as $row ) {
			$pack_id = $row->ID;
		}		
	}
	else {
		// Since the plugin isn't installed, just use the default value
		$pack_id = $type;
	}
	
	$args = array(
		'meta_key' => 'active_membership_pack', 
		'meta_value' => $pack_id
	);

	// The Query
	$user_query = new WP_User_Query( $args );
	
	$count = count( $user_query->results);
	$number = rand(0,$count-1);
	
	$n=0;
	// User Loop - display 1 random user
	if ( ! empty( $user_query->results ) ) {
			foreach ( $user_query->results as $user ) { 
				if( $n == $number ){

						/* This function is dependent upon the WP User Avatar plugin! */
						if( function_exists('get_wp_user_avatar_src') ) {
							$imgURL = get_wp_user_avatar_src($user->ID, 250);
						}
						elseif( function_exists('get_avatar_url') ) {
							$imgURL = get_avatar_url($user->ID);
						}
						else{
							$imgURL = '';
						}
					  
					?>
					<div class="broker-wrapper">
						<ul class="slide">
							<li>
								<a class="featured-broker-header" href="<?php echo site_url();?>/author/<?php echo $user->user_nicename;?>/">
									<h3 class="broker-header"><?php echo $user->display_name;?></h3>
									<figure class="broker-content">
										<img width="400" height="244" src="<?php echo $imgURL; ?>" class="attachment-bsc_featured" alt="" />
										<figcaption><p><?php echo strip_tags( get_the_author_meta( 'description', $user->ID ) ); ?></p></figcaption>
									</figure>
								<p class="broker-tag">Listings: <?php echo cpc_broker_listings( $user->ID )?></p>
								</a>
							</li>            
						</ul>
					</div>
			<?php
				}
				$n=$n+1;
			}
	}
}