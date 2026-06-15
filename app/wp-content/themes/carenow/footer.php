<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the #content div and all content after
 *
 * @package carenow
 */
?>        
        </div><!-- #content -->
    </div><!-- #main-content -->
    
    <?php get_template_part( 'tpl/partner'); ?>
    <?php get_template_part( 'tpl/action-box'); ?>    

    <!-- Start Footer -->   
    <div class="footer_background">
        <div class="overlay-footer"></div>       

        <!-- Footer Widget -->
        <?php get_template_part( 'tpl/footer/footer-widgets'); ?>

        <!-- Info Footer -->
        <?php get_template_part( 'tpl/footer/info-footer'); ?>
       
        <!-- Bottom -->
        <?php get_template_part( 'tpl/footer/bottom'); ?>
        
    </div> <!-- Footer Background Image --> 
    <!-- End Footer -->  
</div><!-- /#boxed -->
<?php wp_footer(); ?>

<div class="app-overlay">
    <div class="app-overlay__inner">
        <div class="app-overlay__content"><span class="spinner"></span></div>
    </div>
</div>

<!-- {literal} -->
<script type='text/javascript'>
    window['l'+'i'+'ve'+'Te'+'x'] = true,
        window['liv'+'eTex'+'ID'] = 177954,
        window['li'+'veTex_'+'obje'+'ct'] = true;
    (function() {
        var t = document['creat'+'e'+'E'+'lemen'+'t']('script');
        t.type ='text/javascript';
        t.async = true;
        t.src = '//cs15'+'.li'+'vetex.ru/js/cli'+'ent.'+'js';
        var c = document['getElemen'+'tsBy'+'TagName']('script')[0];
        if ( c ) c['paren'+'t'+'No'+'d'+'e']['inser'+'tBef'+'ore'](t, c);
        else document['doc'+'um'+'entElem'+'ent']['first'+'Child']['appe'+'ndC'+'hild'](t);
    })();
</script>
<!-- {/literal} -->


</body>
</html>