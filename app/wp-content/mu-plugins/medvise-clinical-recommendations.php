<?php
/**
 * Plugin Name: Medvise Clinical Recommendations Frontend
 * Description: Фронтовый раздел клинических рекомендаций.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MEDVISE_CLINICAL_RECOMMENDATIONS_PATH', WPMU_PLUGIN_DIR . '/medvise-clinical-recommendations' );
define( 'MEDVISE_CLINICAL_RECOMMENDATIONS_URL', WPMU_PLUGIN_URL . '/medvise-clinical-recommendations' );

require_once MEDVISE_CLINICAL_RECOMMENDATIONS_PATH . '/class-medvise-clinical-recommendations.php';

function medvise_clinical_recommendations_frontend() {
	return Medvise_Clinical_Recommendations::boot();
}

medvise_clinical_recommendations_frontend();
