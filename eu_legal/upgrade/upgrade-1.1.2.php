<?php

function upgrade_module_1_1_2($eu_legal) {
	
	$result = true;
	
	if(is_file(_PS_MODULE_DIR_.$eu_legal->name.'/modules/bankwire.zip'))
		$result &= unlink(_PS_MODULE_DIR_.$eu_legal->name.'/modules/bankwire.zip');
	if(is_file(_PS_MODULE_DIR_.$eu_legal->name.'/modules/cheque.zip'))
		$result &= unlink(_PS_MODULE_DIR_.$eu_legal->name.'/modules/cheque.zip');
	if(is_file(_PS_MODULE_DIR_.$eu_legal->name.'/modules/ogone.zip'))
		$result &= unlink(_PS_MODULE_DIR_.$eu_legal->name.'/modules/ogone.zip');
	if(is_file(_PS_MODULE_DIR_.$eu_legal->name.'/modules/hipay.zip'))
		$result &= unlink(_PS_MODULE_DIR_.$eu_legal->name.'/modules/hipay.zip');
	
	$result &= copy(_PS_MODULE_DIR_.$eu_legal->name.'/override/controllers/admin/templates/products/combinations.tpl', _PS_OVERRIDE_DIR_.'controllers/admin/templates/products/combinations.tpl');
	$result &= copy(_PS_MODULE_DIR_.$eu_legal->name.'/override/controllers/admin/templates/products/quantities.tpl', _PS_OVERRIDE_DIR_.'controllers/admin/templates/products/quantities.tpl');
	
	return (bool)$result;
	
}
