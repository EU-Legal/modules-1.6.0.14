<?php

function upgrade_module_1_2_0($eu_legal) {
	
	$result = true;
	
	if(is_file(_PS_MODULE_DIR_.$eu_legal->name.'/modules/paypal.zip'))
		$result &= unlink(_PS_MODULE_DIR_.$eu_legal->name.'/modules/paypal.zip');
	if(is_file(_PS_MODULE_DIR_.$eu_legal->name.'/modules/gc_ganalytics.zip'))
		$result &= unlink(_PS_MODULE_DIR_.$eu_legal->name.'/modules/gc_ganalytics.zip');
	
	Autoload::getInstance()->generateIndex();
	
	$result &= $eu_legal->deleteOverrides('CMSController');
	
	Autoload::getInstance()->generateIndex();
	
	return (bool)$result;
	
}
