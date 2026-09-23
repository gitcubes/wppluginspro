<?php
$suite_comparison_label = get_field('suite_comparison_label');
$suite_comparison_title = get_field('suite_comparison_title');
$suite_comparison_description = get_field('suite_comparison_description');
?>

<section class="suite-comparison" id="compare-suits">
    <div class="container">
        <div class="top-section animation" data-animation="slideUp" data-delay="0.1s">
            <?php if ($suite_comparison_label) : ?>
                <span class="label"><?php echo esc_html($suite_comparison_label); ?></span>
            <?php endif; ?>

            <?php if ($suite_comparison_title) : ?>
                <h2><?php echo esc_html($suite_comparison_title); ?></h2>
            <?php endif; ?>

            <?php if ($suite_comparison_description) : ?>
                <p><?php echo esc_html($suite_comparison_description); ?></p>
            <?php endif; ?>
        </div>

        <div class="plan-table compare-table box animation" data-animation="slideUp" data-delay="0.16s">
            <div class="plan-table-scroll content">
                <table>
                    <thead>
                        <tr>
                            <th scope="col" aria-label="Plan feature"></th>

                            <?php if (have_rows('suite_comparison_columns')) : ?>
                                <?php while (have_rows('suite_comparison_columns')) : the_row(); ?>
                                    <?php $column_title = get_sub_field('title'); ?>

                                    <th scope="col">
                                        <?php echo esc_html($column_title); ?>
                                    </th>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (have_rows('suite_comparison_rows')) : ?>
                            <?php while (have_rows('suite_comparison_rows')) : the_row(); ?>
                                <?php $row_label = get_sub_field('row_label'); ?>

                                <tr>
                                    <th scope="row"><?php echo esc_html($row_label); ?></th>

                                    <?php if (have_rows('cells')) : ?>
                                        <?php while (have_rows('cells')) : the_row(); ?>
                                            <?php $cell_text = get_sub_field('text'); ?>

                                            <td><?php echo esc_html($cell_text); ?></td>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>