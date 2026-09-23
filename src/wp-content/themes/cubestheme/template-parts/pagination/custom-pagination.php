<nav class="pagination">
    <div class="nav-links">
        <?php
        $big = 999999999; // need an unlikely integer
        echo paginate_links(array(
            'base' => str_replace($big, '%#%', get_pagenum_link($big)),
            'format' => '?paged=%#%',
            'current' => max(1, get_query_var('paged')),
            'total' => $queryResults->max_num_pages,
            'prev_text' => '',
            'next_text' => '',
            'screen_reader_text' => ' ',
            'mid_size' => 2
        ));
        ?>
    </div>
</nav>

