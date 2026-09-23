<section class="privacy-content">
    <div class="container">
        <div class="content animation" data-animation="slideUp" data-delay="0.12s">
            <?php the_content(); ?>

            <div class="publish-date">
                <span><?php printf(esc_html__('Last updated:', 'cubestheme')); ?></span>
                <span><?php echo esc_html(get_the_modified_date('M Y')); ?></span>
            </div>
        </div>
    </div>
</section>