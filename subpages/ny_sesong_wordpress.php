<?php

$newSeason = (int)date('Y')+1;


$TWIG['season'] = $newSeason;
update_site_option('season', $newSeason );
update_blog_option(1, 'season', $newSeason);

echo TWIG('ny_sesong/steg2_wordpress.twig.html', $TWIG, dirname( dirname( __FILE__ ) ) );