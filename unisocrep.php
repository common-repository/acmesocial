<?php
/*
Plugin Name: Universal Social Reputation
Plugin URI: http://unisocrep.herokuapp.com/
Description: This plugin allows you to integrate Universal Social Reputation (USR) features to your blog. To get started: 1) Click the "Activate" link to the left of this description, 2) Sign up for an USR account to get an API key, and 3) Go to your USR configuration page, and save your API key. <strong>Warning: </strong>This plugin uses USR, a 3rd party service, that receive data from your users' behaviour. This service has their servers in the USA.
Version: 1.0
Author: USR
Author URI: http://unisocrep.herokuapp.com/
Text Domain: ACME-Social
Domain Path: /languages/

*/

$host = "http://unisocrep.herokuapp.com";

// Añade la funcionalidad de los filtros y acciones al WP
add_action( 'wp_ajax_nopriv_uni_soc_rep_commentsvote_ajaxhandler', 'uni_soc_rep_commentsvote_ajaxhandler' );
add_action( 'wp_ajax_uni_soc_rep_commentsvote_ajaxhandler', 'uni_soc_rep_commentsvote_ajaxhandler' );
add_filter('comment_text', uni_soc_rep_commentsvote_comment_text);
//add_filter('get_comment_author', addAuthorScore);
add_action('admin_menu', 'uni_soc_rep_commentvote_create_menu');
add_action('wp_enqueue_scripts', uni_soc_rep_voteme_enqueuescripts);
add_filter( 'pre_comment_approved' , 'uni_soc_rep_filter_handler' , '99', 2 );
add_action( 'plugins_loaded', uni_soc_rep_load_plugins );
add_filter('get_comment_author', 'uni_soc_rep_comment_author_display_name');

// Añade los JS para que puedan ser utilizados en el plugin.
define('uni_soc_rep_VOTECOMMENTSURL', plugin_dir_url( __FILE__ ) );
define('uni_soc_rep_VOTECOMMENTPATH', plugin_dir_path( __FILE__ )  );

function uni_soc_rep_load_plugins() {
	load_plugin_textdomain( 'ACME-Social', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

function uni_soc_rep_voteme_enqueuescripts() {
    wp_enqueue_script('uni_soc_rep_votecomment', uni_soc_rep_VOTECOMMENTSURL.'js/commentsvote.js', array('jquery'));
	wp_localize_script( 'uni_soc_rep_votecomment', 'uni_soc_rep_votecommentajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
}

function uni_soc_rep_hasASAccount($email){
	$app_id = get_option('app_id');
	$url = $GLOBALS["host"]."/security/".$app_id."/user/".$current_user;
	$response = wp_remote_get( $url,  array( 'timeout' => 10 ) );
	//die(print_r($url,true));

	$body = wp_remote_retrieve_body( $response );
	return (json_decode($body)->active==null?false:json_decode($body)->count->active)=="true";
}

function uni_soc_rep_say($this,$print=null){
	
	$translation = __( $this, 'ACME-Social' );
	
	if($print!=null){
		print_r($translation);
	}
	return $translation;
}

function uni_soc_rep_hasBeenVoted($current_commentID){
	$app_id = get_option('app_id');
	$email_of_logged_user = wp_get_current_user()->user_email;
	$url = $GLOBALS["host"]."/rating/".$app_id."/from/".$email_of_logged_user."/reason/".$current_commentID;
	$response = wp_remote_get( $url,  array( 'timeout' => 10 ) );

	$body = wp_remote_retrieve_body( $response );
	//die(print_r(json_decode($body)->rated."AQIO",true));
	return json_decode($body)->rated==null?true:json_decode($body)->rated=="true";
}

function uni_soc_rep_getVotes($current_commentID){
	$app_id = get_option('app_id');
	$url = $GLOBALS["host"]."/rating/".$app_id."/reason/".$current_commentID;
	$response = wp_remote_get( $url,  array( 'timeout' => 10 ) );
	//die(print_r($url,true));
	$body = wp_remote_retrieve_body( $response );
	return json_decode($body)->count==null?"?":json_decode($body)->count;
}

function uni_soc_rep_getUserScore($current_user = ""){
	if($current_user == ""){
		$current_user = wp_get_current_user()->user_email;
	}
	$app_id = get_option('app_id');
	$url = $GLOBALS["host"]."/rating/".$app_id."/user/".$current_user;
	$response = wp_remote_get( $url,  array( 'timeout' => 10 ) );

	//die(print_r($url,true));
	$body = wp_remote_retrieve_body( $response );
	return json_decode($body)->score==null?"?":json_decode($body)->score;
	
}

function uni_soc_rep_postAction($content,$email="_" ){
		$app_id = get_option('app_id');
		if($email=="_"){
			$email=wp_get_current_user()->user_email;
		}
		$url = $GLOBALS["host"]."/user/".$email."/from/".$app_id."";
		wp_remote_post( $url, array('body' => array( 'mainData' => $content ) ));
}

function uni_soc_rep_rateComment($current_commentID,$alignment,$comment_author_email){
	$app_id = get_option('app_id');
	$email_of_logged_user = wp_get_current_user()->user_email;
	
	$response= wp_remote_post( $GLOBALS["host"]."/rating", array(
						'body' => array( 'action' => $alignment, 'starValue' => '1', 'target' =>$comment_author_email, 'nonce'=>$current_commentID, 'app'=>$app_id, 'origin'=>$email_of_logged_user )));

						}

// Modify apparience of comment in order to add Rate buttons
function uni_soc_rep_commentsvote_comment_text($content) {
	update_option("require_name_email",1,true);
    return $content.uni_soc_rep_commentsvote_showlink();
}

function uni_soc_rep_commentsvote_showlink() {
    $nonce = wp_create_nonce("commentsvote_nonce");
    $current_commentID =  get_comment_ID();
	
	if(is_user_logged_in()){
		$app_id = get_option('app_id');
		uni_soc_rep_postAction("VIEWED ".$current_commentID." @ ".$app_id);
	}
	
	if(uni_soc_rep_hasBeenVoted($current_commentID)){	
        $completelink = '<div class="commentlink" >'.uni_soc_rep_getVotes($current_commentID).' '.uni_soc_rep_say("Votes").' <a href="#"></a></div>';
	}elseif(( is_user_logged_in())) {
	
        $arguments_up = $current_commentID.",'".$nonce."','up'";
		$upButton='<img src="'.uni_soc_rep_VOTECOMMENTSURL.'images/up-arrow-circle-hi.png" >';
        $upVote = ' <a onclick="uni_soc_rep_commentsvote_add('.$arguments_up.');">'.$upButton.'</a>';
		
		$downButton='<img src="'.uni_soc_rep_VOTECOMMENTSURL.'images/up-arrow-circle-lo.png" >';
		$arguments_down = $current_commentID.",'".$nonce."','down'";
		$downVote = ' <a onclick="uni_soc_rep_commentsvote_add('.$arguments_down.');">'.$downButton.'</a>';
		
        $completelink = '<div id="commentsvote-'.$current_commentID.'">';
        $completelink .= '<span>'.uni_soc_rep_getVotes($current_commentID).' '.uni_soc_rep_say("Votes").' </span><br><span>'.$upVote.'  '.$downVote.'</span>';
        $completelink .= '</div>';
	}else {
		$register_link = site_url('wp-login.php', 'login') ;
		$completelink = '<div class="commentlink" >'." <a href=".$register_link.">".uni_soc_rep_getVotes($current_commentID)." ".uni_soc_rep_say("Votes")."</a>".'</div>';
	}
    return $completelink;
}


function uni_soc_rep_comment_author_display_name($author) {
    global $comment;
    if (!empty($comment->user_id)){

		$user=get_userdata($comment->user_id);
		$author='['.uni_soc_rep_getUserScore($user->user_email).'] '.$user->display_name;    

	}else if(!empty($comment->comment_author_email)){
		$author='['.uni_soc_rep_getUserScore($comment->comment_author_email).'] '.$comment->comment_author_email;    
	}

    return $author;
}
/*
function uni_soc_rep_addAuthorScore(){
	if (strlen(comment_author())>0){
		return "g!".;
	}else{
		return "ANNO";
	}
}*/

// This functions is called through AJAX to post comment data.
function uni_soc_rep_commentsvote_ajaxhandler() {
	
    if ( !wp_verify_nonce( sanitize_text_field(esc_html($_POST['nonce'])), "commentsvote_nonce")) {
        exit("Something Wrong");
    }
 
    $results = '';
    
    if( get_option('commentvotelogin') != 'yes' || is_user_logged_in() ) {
  
        $commentid = sanitize_text_field(esc_html($_POST['commentid']));
		$alignment = sanitize_text_field(esc_html($_POST['alignment']));
		$current_comment_email = get_comment_author_email($commentid);
		
		uni_soc_rep_rateComment($commentid,$alignment,$current_comment_email);

        $results .= uni_soc_rep_say("Thanks for voting!");
	
    }

    die($results);
}

// A esta función se le llama cuando se va a insertar un comentario nuevo.
function uni_soc_rep_filter_handler( $approved , $commentdata ){

	$span_rate = "0";
	$publish_rate = "0";
	if(get_option('span_rate')!=""){
		$span_rate = get_option('span_rate');
	};
	if(get_option('publish_rate')!=""){
		$publish_rate = get_option('publish_rate');
	};
	
	uni_soc_rep_postAction("COMMENTED: ".$commentdata["comment_content"],$commentdata["comment_author_email"]);
	
	if(!uni_soc_rep_hasASAccount($commentdata["comment_author_email"])){
		return 0;
	}

	//postAction($commentdata["comment_content"]);
	if( get_option('autopublish') == 'yes' ){
		
		
		if(uni_soc_rep_getUserScore()<$span_rate){
			//die(print_r("A",true));
			return "spam";
		}else if(uni_soc_rep_getUserScore()>=$publish_rate){
			//die(print_r("B".$span_rate." ".$publish_rate." ".getUserScore(),true));
			return $approved;
		}else{
			//die(print_r("C".$span_rate." ".$publish_rate." ".getUserScore(),true));
			return 0;
		}
	}else{
		//die(print_r("D",true));
		return $approved;
	}

}


// Settings

function uni_soc_rep_commentvote_create_menu() {
    add_submenu_page('options-general.php',uni_soc_rep_say('USR'),uni_soc_rep_say('USR'),'manage_options', __FILE__.'uni_soc_rep_comments_settings_page','uni_soc_rep_comments_settings_page');
}
function uni_soc_rep_comments_settings_page() {
?>
    <div class="wrap">
    <?php
	if(!current_user_can("administrator")){
			echo "<p>Sorry, you shall not being in here.</p>";
			die();
	}
    if( isset( $_POST['commentvotesubmit'] ) ) {
        //update_option( 'commentvotelogin' , sanitize_text_field($_POST[ 'commentvotelogin' ]) );
		update_option( 'app_id' , sanitize_text_field(esc_html($_POST[ 'app_id' ] )));
		update_option( 'autopublish' , sanitize_text_field(esc_html($_POST[ 'autopublish' ] )));
		update_option( 'publish_rate' , sanitize_text_field(esc_html($_POST[ 'publish_rate' ] )) );
		update_option( 'span_rate' , sanitize_text_field(esc_html($_POST[ 'span_rate' ] ))  );
		
		update_option( 'show_poweredBy' , sanitize_text_field(esc_html($_POST[ 'show_poweredBy' ] )));
    }
    ?>
        <div id="commentvotesetting">
            <form id='commentvotesettingform' method="post" action="">
                <h1><?php echo uni_soc_rep_say('Settings'); ?></h1>
				<?php uni_soc_rep_say("Place here your USR identifier:",1);?> <input type='text' size="38" maxlength="32" name='app_id' value='<?php if( get_option('app_id') != '' ) echo esc_html(get_option('app_id'));?>'>
				<p><input type = 'checkbox' Name ='show_poweredBy' value= 'yes' <?php if( get_option('show_poweredBy') == 'yes' ) echo 'checked';?> ><?php echo uni_soc_rep_say('Warn users about USR usage by displaying a "Powered By" text near comment box'); ?></p>
				<br>
				<br/>
				<h1><?php echo uni_soc_rep_say('Privacy'); ?></h1>
				<p><?php echo uni_soc_rep_say("text1"); ?></p>
				<p><?php echo uni_soc_rep_say("text2"); ?></p>

				<br>
				<h1><input type = 'checkbox' Name ='autopublish' value= 'yes' <?php if( get_option('autopublish') == 'yes' ) echo 'checked';?> ><?php echo uni_soc_rep_say('Comments filter'); ?></h1>
				<?php uni_soc_rep_say("If enabled, comments will be filtered by using user score.",1);?>
                <br/>
				<br/>
				<?php uni_soc_rep_say("Spam comment if user score is LOWER THAN:",1); ?> <input size="6" maxlength="4" type="number" step="1" name='span_rate' value='<?php if( get_option('span_rate') != '' ){ echo esc_html(get_option('span_rate'));}else{echo '0';}?>'> <?php uni_soc_rep_say("(Default: 0)(This rule is checked first)",1); ?><br>
				<?php uni_soc_rep_say("Autopublish comment if user has a score GREATER OR EQUALS TO:",1); ?> <input type="number" size="6" step="1" maxlength="4" name='publish_rate' value='<?php if( get_option('publish_rate') != '' ) {echo esc_html(get_option('publish_rate'));}else{echo '0';}?>'><?php uni_soc_rep_say("(Default: 0)",1); ?> <br>
				
				<p class="submit">
                <input type="submit" id="commentvotesubmit" name="commentvotesubmit" class="button-primary" value="<?php uni_soc_rep_say("Save",1); ?>" />
                </p>
            </form>
        </div>
    </div>
<?php }


// Add fields after default fields above the comment box, always visible

add_action( 'comment_form_logged_in_after', 'uni_soc_rep_additional_fields' );
add_action( 'comment_form_after_fields', 'uni_soc_rep_additional_fields' );

function uni_soc_rep_additional_fields () {

$disclaimer = '<p>'.uni_soc_rep_say("Submitting this comment you accept the Terms and Conditions").'</p>';
if(get_option("show_poweredBy")=='yes'){
	$string = '<p>'.uni_soc_rep_say("Powered by").'<a href="http://unisocrep.herokuapp.com/"> USR*</a></p>';
	$disclaimer = '<p><a href="http://unisocrep.herokuapp.com/legal">'.uni_soc_rep_say("Submitting this comment you accept the Terms and Conditions").'</a></p>';
}

	echo $disclaimer.$string;

}

?>