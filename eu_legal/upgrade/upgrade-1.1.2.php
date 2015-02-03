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
	
	Autoload::getInstance()->generateIndex();
	
	$result &= $eu_legal->deleteOverrides('Module');
	$result &= $eu_legal->addOverride('Module');

	$result &= $eu_legal->deleteOverrides('Order');
	$result &= $eu_legal->addOverride('Order');
	
	$result &= $eu_legal->deleteOverrides('OrderDetail');
	
	$result &= $eu_legal->deleteOverrides('HTMLTemplateInvoice');
	$result &= $eu_legal->addOverride('HTMLTemplateInvoice');
	
	$result &= $eu_legal->deleteOverrides('HTMLTemplateOrderSlip');
	$result &= $eu_legal->addOverride('HTMLTemplateOrderSlip');
	
	$result &= $eu_legal->deleteOverrides('Cart');
	$result &= $eu_legal->addOverride('Cart');

	$result &= $eu_legal->deleteOverrides('Category');
	$result &= $eu_legal->addOverride('Category');
	
	$result &= $eu_legal->deleteOverrides('Manufacturer');
	$result &= $eu_legal->addOverride('Manufacturer');
	
	$result &= $eu_legal->deleteOverrides('PaymentModule');
	$result &= $eu_legal->addOverride('PaymentModule');
	
	$result &= $eu_legal->deleteOverrides('Product');
	$result &= $eu_legal->addOverride('Product');
	
	$result &= $eu_legal->deleteOverrides('ProductSale');
	$result &= $eu_legal->addOverride('ProductSale');
	
	$result &= $eu_legal->deleteOverrides('Supplier');
	$result &= $eu_legal->addOverride('Supplier');
	
	$result &= $eu_legal->deleteOverrides('AdminProductsController');
	$result &= $eu_legal->addOverride('AdminProductsController');
	
	$result &= $eu_legal->deleteOverrides('OrderController');
	$result &= $eu_legal->addOverride('OrderController');

	$result &= $eu_legal->addOverride('OrderDetailController');
	
	$result &= $eu_legal->addOverride('ProductController');
	
	$result &= copy(_PS_MODULE_DIR_.$eu_legal->name.'/override/controllers/admin/templates/products/combinations.tpl', _PS_OVERRIDE_DIR_.'controllers/admin/templates/products/combinations.tpl');
	$result &= copy(_PS_MODULE_DIR_.$eu_legal->name.'/override/controllers/admin/templates/products/quantities.tpl', _PS_OVERRIDE_DIR_.'controllers/admin/templates/products/quantities.tpl');
	
	Autoload::getInstance()->generateIndex();
	
	return (bool)$result;
	
}
