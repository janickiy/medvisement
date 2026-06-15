<h3>Настройки учетных специальностей</h3>
<form method="POST" action="options.php">
	<?php
	settings_fields('med_specialties');
	do_settings_sections('med-specialties-page');
	submit_button();
	?>
</form>
