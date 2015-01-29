<?php

function upgrade_module_1_1_1($eu_legal) {
	
	$result = true;
	
	$result &= unlink(_PS_MODULE_DIR_.$eu_legal->name.'/modules/bankwire.zip');
	$result &= unlink(_PS_MODULE_DIR_.$eu_legal->name.'/modules/cheque.zip');
	$result &= unlink(_PS_MODULE_DIR_.$eu_legal->name.'/modules/ogone.zip');
	
	Autoload::getInstance()->generateIndex();
	
	$result &= $eu_legal->removeOverride('Module');
	$result &= $eu_legal->addOverride('Module');
	
	$result &= $eu_legal->removeOverride('Order');
	$result &= $eu_legal->addOverride('Order');
	
	$result &= $eu_legal->removeOverride('OrderDetail');
	$result &= $eu_legal->addOverride('OrderDetail');
	
	$result &= $eu_legal->removeOverride('HTMLTemplateInvoice');
	$result &= $eu_legal->addOverride('HTMLTemplateInvoice');
	
	$result &= $eu_legal->removeOverride('HTMLTemplateOrderSlip');
	$result &= $eu_legal->addOverride('HTMLTemplateOrderSlip');
	
	$result &= $eu_legal->removeOverride('Cart');
	$result &= $eu_legal->addOverride('Cart');

	$result &= $eu_legal->removeOverride('Category');
	$result &= $eu_legal->addOverride('Category');
	
	$result &= $eu_legal->removeOverride('Manufacturer');
	$result &= $eu_legal->addOverride('Manufacturer');
	
	$result &= $eu_legal->removeOverride('PaymentModule');
	$result &= $eu_legal->addOverride('PaymentModule');
	
	$result &= $eu_legal->removeOverride('Product');
	$result &= $eu_legal->addOverride('Product');
	
	$result &= $eu_legal->removeOverride('ProductSale');
	$result &= $eu_legal->addOverride('ProductSale');
	
	$result &= $eu_legal->removeOverride('Supplier');
	$result &= $eu_legal->addOverride('Supplier');
	
	$result &= $eu_legal->removeOverride('AdminProductsController');
	$result &= $eu_legal->addOverride('AdminProductsController');
	
	$result &= $eu_legal->removeOverride('OrderController');
	$result &= $eu_legal->addOverride('OrderController');

	$result &= $eu_legal->addOverride('ProductController');
	
	$result &= copy(_PS_MODULE_DIR_.$eu_legal->name.'/override/controllers/admin/templates/products/combinations.tpl', _PS_OVERRIDE_DIR_.'controllers/admin/templates/products/combinations.tpl');
	
	Autoload::getInstance()->generateIndex();
	
	return $result;
	
}
