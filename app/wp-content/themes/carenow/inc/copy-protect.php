<?php

function copypaste_protection() {
	global $current_user;

	$user_roles = $current_user->roles;
	$user_role  = array_shift( $user_roles );

	$post_type = '';
	if ( isset( get_queried_object()->post_type ) ) {
		$post_type = get_queried_object()->post_type;
	}

	// Копирайт при копировании
	// Клинические рекомендации + препараты
	if ( $post_type === 'substance'
         || ( $post_type === 'disease' && has_term( 'clinical-guidelines', 'article-type' ) )
            && $user_role !== 'administrator' ):
	?>
        <script>
            const copyListener = (event) => {
                const range = window.getSelection().getRangeAt(0),
                    rangeContents = range.cloneContents(),
                    pageLink = `Источник: медицинский портал medvisement.com`,
                    helper = document.createElement("div");

                helper.appendChild(rangeContents);

                event.clipboardData.setData("text/plain", `${helper.innerText}\n${pageLink}`);
                event.clipboardData.setData("text/html", `${helper.innerHTML}<br>${pageLink}`);
                event.preventDefault();
            };
            document.addEventListener("copy", copyListener);
        </script>
		<?php
		return;
	endif;

	if ( $post_type === 'disease' && $user_role !== 'administrator' ): ?>
        <script>
            jQuery(document).ready(function () {
                function disableselect(e) {
                    return false;
                }

                function reEnable() {
                    return true;
                }

                if (window.sidebar) {
                    document.onmousedown = disableselect;
                    document.onclick = reEnable;
                }

                const post_content_el = jQuery('#main');

                post_content_el.bind("contextmenu", function (e) {
                    return false;
                });
                post_content_el.bind("copy", function (e) {
                    return false;
                });
                post_content_el.bind("paste", function (e) {
                    return false;
                });
                post_content_el.bind("select", function (e) {
                    return false;
                });
            });
        </script>
	<?php endif;

}

add_action( 'wp_footer', 'copypaste_protection' );