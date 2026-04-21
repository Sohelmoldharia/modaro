<?php
use App\Madara;

add_action('madara_chapter_reading_actions_list_items', 'madara_chapter_reading_actions_add_darkmode_button');

function madara_chapter_reading_actions_add_darkmode_button(){
	?>
	<li><a href="#" class="wp-manga-action-button" data-action="toggle-contrast" title="<?php esc_html_e('Toggle Dark/Light Mode', 'madara');?>"><i class="icon ion-md-contrast"></i></a></li>
	<?php
}


add_filter( 'madara_meta_query_args', 'madara_adult_content_filter_metaquery');
	
function madara_adult_content_filter_metaquery($meta_query){
	if(Madara::getOption('manga_adult_content','off') == 'on'){
		if(isset($_COOKIE['wpmanga-adault']) && $_COOKIE['wpmanga-adault']) {
			// I'm an adult, family-mode is off, then show all content as default
		} else {
		
			$meta_query[] = array(
						'key'     => 'manga_adult_content',
						'value'   => ''
					);
		}
	}
	return $meta_query;
}