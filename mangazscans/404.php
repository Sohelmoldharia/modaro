<?php

	use App\MangazScans;

	/**
	 * The Template for displaying all 404 pages.
	 *
	 * @package mangazscans
	 */

	get_header();

?>

    <!-- #site-navigation -->
    <div class="container">
        <section class="c-page-content c-page-404 ">

            <div id="primary" class="content-area">
                <main id="main" class="site-main main-content">

                    <div id="main-content" class="row c-row">

                        <div class="col-12 c-column">

                            <section class="error-404 not-found">
                                <div class="error-404_content">
                                    <header class="entry-header">
                                        <div class="entry-featured-image">
											<?php
												$mz_featured_image = MangazScans::getOption( 'page404_featured_image' );

												if ( $mz_featured_image != '' ) {
													echo '<figure class="c-thumbnail"><img src="' . esc_url( $mz_featured_image ) . '" alt="' . esc_attr__( '404', 'mangazscans' ) . '"/></figure>';
												} else {
													echo '<figure class="c-thumbnail"><img src="' . esc_url( get_template_directory_uri() . '/images/404.png' ) . '" alt="' . esc_attr__( '404', 'mangazscans' ) . '"/></figure>';
												}
											?>
                                        </div>
                                        <div class="entry-title">
                                            <h3 class="heading">
												<?php
													$mz_heading = MangazScans::getOption( 'page404_title' );

													if ( $mz_heading != '' ) {
														echo esc_html( $mz_heading );
													} else {
														esc_html_e( 'Oops! page not found.', 'mangazscans' );
													}
												?>
                                            </h3>
                                        </div>
                                    </header>

                                    <!-- .entry-header -->
									<?php $mz_content = MangazScans::getOption( 'page404_content' );

										if ( $mz_content != '' ) {
											echo '<div class="entry-content">' . wp_kses_post( $mz_content ) . '</div>';
										}
									?>
                                    <!-- .entry-content -->

                                    <div class="entry-footer">
                                        <a class="c-btn c-btn_style-3" href="<?php echo esc_url( home_url( '/' ) ); ?>"> <?php esc_html_e( 'Go home', 'mangazscans' ) ?></a>
                                    </div>
                                    <!-- .entry-footer -->
                                </div>
                            </section>

                        </div>

                    </div>
                    <!-- row -->

                </main>
                <!-- main -->
            </div>
            <!-- primary -->

        </section>
        <!-- #c-page-content -->
    </div>

<?php
	get_footer();
