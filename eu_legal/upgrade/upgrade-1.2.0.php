<?php

function upgrade_module_1_2_0($eu_legal) {
	
	$result = true;
	
	if(is_file(_PS_MODULE_DIR_.$eu_legal->name.'/modules/cashondelivery.zip'))
		$result &= unlink(_PS_MODULE_DIR_.$eu_legal->name.'/modules/cashondelivery.zip');
	if(is_file(_PS_MODULE_DIR_.$eu_legal->name.'/modules/gc_ganalytics.zip'))
		$result &= unlink(_PS_MODULE_DIR_.$eu_legal->name.'/modules/gc_ganalytics.zip');
	if(is_file(_PS_MODULE_DIR_.$eu_legal->name.'/modules/moneybookers.zip'))
		$result &= unlink(_PS_MODULE_DIR_.$eu_legal->name.'/modules/moneybookers.zip');
	if(is_file(_PS_MODULE_DIR_.$eu_legal->name.'/modules/paypal.zip'))
		$result &= unlink(_PS_MODULE_DIR_.$eu_legal->name.'/modules/paypal.zip');
	if(is_file(_PS_MODULE_DIR_.$eu_legal->name.'/modules/sofortbanking.zip'))
		$result &= unlink(_PS_MODULE_DIR_.$eu_legal->name.'/modules/sofortbanking.zip');
	if(is_file(_PS_MODULE_DIR_.$eu_legal->name.'/modules/trustedshops.zip'))
		$result &= unlink(_PS_MODULE_DIR_.$eu_legal->name.'/modules/trustedshops.zip');
	
	Autoload::getInstance()->generateIndex();
	
	$result &= $eu_legal->deleteOverrides('CMSController');
	
	Autoload::getInstance()->generateIndex();
	
	return (bool)$result;
	
}
