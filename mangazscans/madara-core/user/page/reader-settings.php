<?php
	/**
	 * The Template for Reader Settings section in User Settings page
	 *
	 * This template can be overridden by copying it to your-child-theme/madara-core/user/reader-settings.php
	 *
	 * HOWEVER, on occasion Madara will need to update template files and you
	 * (the theme developer) will need to copy the new files to your theme to
	 * maintain compatibility. We try to do this as little as possible, but it does
	 * happen. When this occurs the version of the template file will be bumped and
	 * we will list any important changes on our theme release logs.
	 * @package Madara
	 * @version 1.7.2.2
	 */
	 
	 if ( ! is_user_logged_in() ) {
		return;
	}

	$user_id = get_current_user_id();
	//update reading settings
	$reading_style = isset( $_POST['_manga_reading_style'] ) ? $_POST['_manga_reading_style'] : 'default';
	
	$user_img_per_page = get_user_meta( $user_id, '_manga_img_per_page', true );

	if( isset( $_POST['_manga_img_per_page'] ) ){
		$img_per_page  = $_POST['_manga_img_per_page'];
	}elseif( $user_img_per_page !== 'default' ){
		$img_per_page = madara_get_img_per_page();
	}else{
		$img_per_page = $user_img_per_page;
	}

	if ( isset( $_POST['reader-settings-submit'] ) ) {
		update_user_meta( $user_id, '_manga_reading_style', $reading_style );
		update_user_meta( $user_id, '_manga_img_per_page', $img_per_page );
		update_user_meta( $user_id, '_manga_user_site_schema', $_POST['_manga_site_schema'] );
		$is_update = true;
	}
	
	$site_schema = get_user_meta( $user_id, '_manga_user_site_schema', true);
	$reading_style = get_user_meta( $user_id, '_manga_reading_style', true);
	$img_per_page = get_user_meta( $user_id, '_manga_img_per_page', true);
?>

<?php if ( !empty( $is_update )) { ?>
    <div class="alert alert-success alert-dismissable">
        <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
        <strong><?php esc_html_e( 'Success!', 'mangazscans' ); ?></strong> <?php esc_html_e( ' Update successfully', 'mangazscans' ); ?>
    </div>
<?php } ?>

<div class="tab-group-item image_setting">
    <form method="post">
        <div class="settings-heading">
            <h3><?php esc_html_e( 'Reading Settings', 'mangazscans' ); ?></h3>
        </div>
		<div class="tab-item">
            <div class="settings-title">
                <h3><?php esc_html_e( 'Site Schema', 'mangazscans' ); ?></h3>
            </div>
            <div class="checkbox">
                <input id="manga_site_schema_default" type="radio" name="_manga_site_schema" value="" <?php checked( $site_schema, '' ); ?>>
                <label for="manga_site_schema_default"><?php esc_html_e( 'Default', 'mangazscans' ); ?></label>
            </div>
			<div class="checkbox">
                <input id="manga_site_schema_light" type="radio" name="_manga_site_schema" value="dark" <?php checked( $site_schema, 'dark' ); ?>>
                <label for="manga_site_schema_light"><?php esc_html_e( 'Dark', 'mangazscans' ); ?></label>
            </div>
            <div class="checkbox">
                <input id="manga_site_schema_dark" type="radio" name="_manga_site_schema" value="light" <?php checked( $site_schema, 'light' ); ?>>
                <label for="manga_site_schema_dark"><?php esc_html_e( 'Light', 'mangazscans' ); ?></label>
            </div>
        </div>
        <div class="tab-item">
            <div class="settings-title">
                <h3><?php esc_html_e( 'Reading Style', 'mangazscans' ); ?></h3>
            </div>
            <div class="checkbox">
                <input id="manga_reading_default" type="radio" name="_manga_reading_style" value="default" <?php checked( $reading_style, 'default' ); ?>>
                <label for="manga_reading_default"><?php esc_html_e( 'Default', 'mangazscans' ); ?></label>
            </div>
			<div class="checkbox">
                <input id="manga_reading_page" type="radio" name="_manga_reading_style" value="paged" <?php checked( $reading_style, 'paged' ); ?>>
                <label for="manga_reading_page"><?php esc_html_e( 'Paged', 'mangazscans' ); ?></label>
            </div>
            <div class="checkbox">
                <input id="manga_reading_list" type="radio" name="_manga_reading_style" value="list" <?php checked( $reading_style, 'list' ); ?>>
                <label for="manga_reading_list"><?php esc_html_e( 'List', 'mangazscans' ); ?></label>
            </div>
        </div>
        <div class="tab-item">
            <div class="settings-title">
                <h3><?php esc_html_e( 'Images Per Page', 'mangazscans' ); ?></h3>
                <span class="description"><?php esc_html_e( 'Only works with paged reading style', 'mangazscans' ); ?></span>
            </div>
			<div class="checkbox">
                <input id="per_page_default" type="radio" name="_manga_img_per_page" value="default" <?php checked( $img_per_page, 'default' ); ?>>
                <label for="per_page_default"><?php esc_html_e( 'Default', 'mangazscans' ); ?></label>
            </div>
            <div class="checkbox">
                <input id="1_per_page" type="radio" name="_manga_img_per_page" value="1" <?php checked( $img_per_page, '1' ); ?>>
                <label for="1_per_page"><?php esc_html_e( 'Load 1 image per page (default)', 'mangazscans' ); ?></label>
            </div>
            <div class="checkbox">
                <input id="3_per_page" type="radio" name="_manga_img_per_page" value="3" <?php checked( $img_per_page, '3' ); ?>>
                <label for="3_per_page"><?php esc_html_e( 'Load 3 images per page', 'mangazscans' ); ?></label>
            </div>
            <div class="checkbox">
                <input id="6_per_page" type="radio" name="_manga_img_per_page" value="6" <?php checked( $img_per_page, '6' ); ?>>
                <label for="6_per_page"><?php esc_html_e( 'Load 6 images per page', 'mangazscans' ); ?></label>
            </div>
            <div class="checkbox">
                <input id="10_per_page" type="radio" name="_manga_img_per_page" value="10" <?php checked( $img_per_page, '10' ); ?>>
                <label for="10_per_page"><?php esc_html_e( 'Load 10 images per page', 'mangazscans' ); ?></label>
            </div>
        </div>
        <br/>
        <input class="form-control" type="submit" value="<?php esc_html_e( 'Submit', 'mangazscans' ); ?>" id="reading-input-submit" name="reader-settings-submit">
    </form>
</div>
