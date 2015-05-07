<?php
/**
 * EU Legal - Better security for German and EU merchants.
 *
 * @version   : 1.4.1
 * @date      : 2015 05 07
 * @author    : Markus Engel/Chris Gurk @ Onlineshop-Module.de | George June/Alexey Dermenzhy @ Silbersaiten.de
 * @copyright : 2015 Onlineshop-Module.de | 2015 Silbersaiten.de
 * @contact   : info@onlineshop-module.de | info@silbersaiten.de
 * @homepage  : www.onlineshop-module.de | www.silbersaiten.de
 * @license   : http://opensource.org/licenses/osl-3.0.php
 * @changelog : see changelog.txt
 * @compatibility : PS == 1.6.0.14
 */

/* no direct access to this module */
if (!defined('_PS_VERSION_'))
	exit;

/* main class */

class EU_Legal extends Module
{
	/*******************************************************************************************************************
	 *
	 * Module vars and constants
	 * (for source comments see __construct()
	 *
	 *******************************************************************************************************************/
	public $languages = array();
	public $default_language_id = 1;
	public $theme = array();
	public $deliveryNowDefault = '';
	public $deliveryLaterDefault = '';
	public $hooks = array();
	public $cms_pages = array();
	public $config_prefix = '';

	/* Cache */
	private static $_cms_pages = array();

	/*******************************************************************************************************************
	 *
	 * Construct // Module configuration
	 *
	 *******************************************************************************************************************/

	public function __construct()
	{
		$this->module_key = '7a6ec9f904879208107bb61b739e577f';

		// module name, must be same as class name and modul directory name
		$this->name = 'eu_legal';

		// module backoffice tab, maybe an other one?
		$this->tab = 'administration';

		// version: major, minor, bugfix
		$this->version = '1.4.1';

		// author
		$this->author = 'EU Legal Team';

		// instance? No
		$this->need_instance = 0;

		// module compliancy: only for exactly one PS version
		$this->ps_versions_compliancy = array(
			'min' => '1.6.0.13',
			'max' => '1.6.0.14'
		);

		// bootstrap baqckoffice functionality
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('EU Legal');
		$this->description = $this->l('Better security for european merchants.');
		$this->confirmUninstall = $this->l('Are you sure you want to delete your details?');

		// collect all available languages and default language
		$this->languages = Language::getLanguages(false, false);
		$this->default_language_id = Configuration::get('PS_LANG_DEFAULT');

		foreach ($this->languages as $key => $language)
			if ($language['id_lang'] == $this->default_language_id)
				$this->languages[$key]['is_default'] = true;
			else
				$this->languages[$key]['is_default'] = false;

		// supportet theme
		// [theme name => theme title]
		$this->themes = array(
			'bootstrap-legal' => $this->l('Bootstrap Legal'),
		);

		// default values for delivery informations
		$this->deliveryNowDefault = $this->l('1-3 Workdays');
		$this->deliveryLaterDefault = $this->l('7-10 Workdays');

		// new hooks to install
		$this->hooks = array(
			// product delivery hook
			'displayProductDeliveryTime' => array(
				'name' => 'Product Delivery Time',
				'templates' => array(
					'product.tpl',
					'product-list.tpl',
					'products-comparison.tpl',
					'shopping-cart-product-line.tpl'
				),
			),
			// product price hook
			'displayProductPriceBlock' => array(
				'name' => 'Product Price Display',
				'templates' => array(
					'product.tpl',
					'product-list.tpl',
					'products-comparison.tpl'
				),
			),
			'displayHeader' => array(
				'name' => 'display Header',
				'templates' => array(),
			),
			'displayBeforeShoppingCartBlock' => array(
				'name' => 'display before Shopping cart block',
				'templates' => array()
			),
			'displayAfterShoppingCartBlock' => array(
				'name' => 'display after Shopping cart block',
				'templates' => array()
			),

			'displayShippingPrice' => array(
				'name' => 'display shipping price in cart',
				'templates' => array()
			),
			'displayTop' => array(
				'name' => 'hook top',
				'templates' => array()
			),
		);

		// modules not compatible with EU Legal
		$this->modules_not_compatible = array(
			'ganalytics',
			'avalaratax',
			'attributewizardpro',
			'crossselling',
			'carriercompare',
		);

		foreach ($this->modules_not_compatible as $module) {
			if (!Module::isEnabled($module))
				continue;
			else {
				$this->warning .= $this->l('Please disable').': '.$module . ', ';
			}

		}


		// available cms pages
		// [filename => configuration]
		$this->cms_pages = array(
			array('name' => 'legalnotice', 'config' => 'LEGAL_CMS_ID_LEGAL', 'title' => $this->l('Legal Notice')),
			array('name' => 'conditions', 'config' => 'PS_CONDITIONS_CMS_ID', 'title' => $this->l('Conditions')),
			array('name' => 'revocation', 'config' => 'LEGAL_CMS_ID_REVOCATION', 'title' => $this->l('Revocation')),
			array('name' => 'revocationform', 'config' => 'LEGAL_CMS_ID_REVOCATIONFORM', 'title' => $this->l('Revocation Form')),
			array('name' => 'privacy', 'config' => 'LEGAL_CMS_ID_PRIVACY', 'title' => $this->l('Privacy')),
			array('name' => 'environmental', 'config' => 'LEGAL_CMS_ID_ENVIRONMENTAL', 'title' => $this->l('Envorimental')),
			array('name' => 'shipping', 'config' => 'LEGAL_CMS_ID_SHIPPING', 'title' => $this->l('Shipping and Payment')),
		);

		// prefix for config vars
		$this->config_prefix = 'LEGAL_';

	}

	/*******************************************************************************************************************
	 *
	 * Install / Uninstall / Update
	 *
	 *******************************************************************************************************************/

	/* install module */
	public function install()
	{
		$return = true;

		// prescan overrides for existing functions. if some classes and functions already exists -> return error
		$this->_errors = array_merge($this->_errors, $this->checkOverrides());

		if (!empty($this->_errors))
			return false;

		// parent install (overrides, module itself, ...)
		if (!parent::install())
			return false;

		// install admin override templates
		$return &= $this->installAdminTemplates();

		// install and register hooks
		$return &= $this->installHooks();
		$return &= $this->installRegisterHooks();

		// register cache hooks
		$return &= $this->registerHook('actionObjectProductAddAfter');
		$return &= $this->registerHook('actionObjectProductUpdateAfter');
		$return &= $this->registerHook('actionObjectProductDeleteAfter');
		$return &= $this->registerHook('actionObjectCategoryUpdateAfter');

		// global configuration values

		// shop specific configuration values
		if ($return && !Configuration::updateGlobalValue('PS_EU_PAYMENT_API', true))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not update config value:').' PS_EU_PAYMENT_API';
		}

		$values = array();
		foreach ($this->languages as $language)
			$values[$language['id_lang']] = '';
		if ($return && !Configuration::updateValue('SHOPPING_CART_TEXT_BEFORE', $values))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not update config value:').' SHOPPING_CART_TEXT_BEFORE';
		}

		$values = array();
		foreach ($this->languages as $language)
			$values[$language['id_lang']] = '';
		if ($return && !Configuration::updateValue('SHOPPING_CART_TEXT_AFTER', $values))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not update config value:').' SHOPPING_CART_TEXT_AFTER';
		}

		if ($return && !Configuration::updateValue('LEGAL_SHIPTAXMETH', true))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not update config value:').' LEGAL_SHIPTAXMETH';
		}

		$values = array();
		foreach ($this->languages as $language)
			$values[$language['id_lang']] = $this->deliveryNowDefault;
		if ($return && !Configuration::updateValue('LEGAL_DELIVERY_NOW', $values))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not update config value:').' LEGAL_DELIVERY_NOW';
		}


		$values = array();
		foreach ($this->languages as $language)
			$values[$language['id_lang']] = $this->deliveryLaterDefault;
		if ($return && !Configuration::updateValue('LEGAL_DELIVERY_LATER', $values))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not update config value:').' LEGAL_DELIVERY_LATER';
		}

		if ($return && !Configuration::updateValue('LEGAL_SHOW_WEIGHTS', 1))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not update config value:').' LEGAL_SHOW_WEIGHTS';
		}

		if ($return && !Configuration::updateValue('LEGAL_SHOW_FROM', 0))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not update config value:').' LEGAL_SHOW_FROM';
		}

		if ($return && !Configuration::updateValue('LEGAL_SHOW_FANCY', 1))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not update config value:').' LEGAL_SHOW_FANCY';
		}

		if ($return && !Configuration::updateValue('LEGAL_CSS', 1))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not update config value:').' LEGAL_CSS';
		}

		if ($return && !Configuration::updateValue('LEGAL_SHOW_FULLTAXINFO', 1))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not update config value:').' LEGAL_SHOW_FULLTAXINFO';
		}

		if ($return && !Configuration::updateValue('LEGAL_SHOW_FULLUNITPRICE', 1))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not update config value:').' LEGAL_SHOW_FULLUNITPRICE';
		}

		if ($return && !Configuration::updateValue('LEGAL_SHOW_DESCINCART', 1))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not update config value:').' LEGAL_SHOW_DESCINCART';
		}

		if ($return && !Configuration::updateValue('LEGAL_SHOW_DELIVERYINFO', 1))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not update config value:').' LEGAL_SHOW_DELIVERYINFO';
		}

		// set config vars for cms pages
		foreach ($this->cms_pages as $cms_page)
			if (strpos($cms_page['config'], $this->config_prefix) === 0)
				if ($return && !Configuration::updateValue($cms_page['config'], 0))
				{
					$return &= false;
					$this->_errors[] = $this->l('Could not update config value:').' '.$cms_page['config'];
				}

		// add error translations
		if (is_file(_PS_TRANSLATIONS_DIR_.'de/errors.php'))
		{

			$rows_original = file(_PS_TRANSLATIONS_DIR_.'de/errors.php', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

			$rows_new = file(_PS_MODULE_DIR_.$this->name.'/translations/de/errors.php', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			unset($rows_new[0]);

			$rows = array_merge($rows_original, $rows_new);

			if ($return && !(bool)file_put_contents(_PS_TRANSLATIONS_DIR_.'de/errors.php', implode("\n\r", $rows)))
			{
				$return &= false;
				$this->_errors[] = $this->l('Could not update errors file');
			}

		}

		// alter database for price precitions
		if ($return && !DB::getInstance()->execute('
			ALTER TABLE `'._DB_PREFIX_.'orders`
			CHANGE `total_discounts` `total_discounts` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_discounts_tax_incl` `total_discounts_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_discounts_tax_excl` `total_discounts_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_paid` `total_paid` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_paid_tax_incl` `total_paid_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_paid_tax_excl` `total_paid_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_paid_real` `total_paid_real` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_products` `total_products` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_products_wt` `total_products_wt` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_shipping` `total_shipping` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_shipping_tax_incl` `total_shipping_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_shipping_tax_excl` `total_shipping_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_wrapping` `total_wrapping` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_wrapping_tax_incl` `total_wrapping_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_wrapping_tax_excl` `total_wrapping_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\'
		'))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not modify db table:').' '._DB_PREFIX_.'orders';
		}

		if ($return && !DB::getInstance()->execute('
			ALTER TABLE `'._DB_PREFIX_.'order_invoice`
			CHANGE `total_discount_tax_excl` `total_discount_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_discount_tax_incl` `total_discount_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_paid_tax_excl` `total_paid_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_paid_tax_incl` `total_paid_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_products` `total_products` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_products_wt` `total_products_wt` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_shipping_tax_excl` `total_shipping_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_shipping_tax_incl` `total_shipping_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_wrapping_tax_excl` `total_wrapping_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\',
			CHANGE `total_wrapping_tax_incl` `total_wrapping_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT \'0.00\'
		'))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not modify db table:').' '._DB_PREFIX_.'order_invoice';
		}

		// alter database for delivery time
		if ($return && !$this->dbColumnExists('product_lang', 'delivery_now') && !$this->dbColumnExists
				('product_lang', 'delivery_later') && !DB::getInstance()->execute('
			ALTER TABLE `'._DB_PREFIX_.'product_lang`
			ADD `delivery_now` VARCHAR(255) NULL DEFAULT NULL AFTER `available_later`, 
			ADD `delivery_later` VARCHAR(255) NULL DEFAULT NULL AFTER `available_now`;
		'))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not modify db table:').' '._DB_PREFIX_.'product_lang';
		}

        /*
        // add product_attribute_lang table
        if ($return && !$this->dbColumnExists('product_attribute_lang', 'delivery_now') && !$this->dbColumnExists
                ('product_attribute_lang', 'delivery_later') && !DB::getInstance()->execute('
			CREATE TABLE `'._DB_PREFIX_.'product_attribute_lang`
		    (`id_product_attribute` int(10) unsigned NOT NULL,
             `id_shop` int(10) unsigned NOT NULL,
             `id_lang` int(10) unsigned NOT NULL,
		     `delivery_now` VARCHAR(255) NULL DEFAULT NULL,
			 `delivery_later` VARCHAR(255) NULL DEFAULT NULL);
		'))
        {
            $return &= false;
            $this->_errors[] = $this->l('Could not modify db table:').' '._DB_PREFIX_.'product_attribute_lang';
        }
        */

		// regenerate class index
		Autoload::getInstance()->generateIndex();


		return (bool)$return;

	}

	/* install admin override templates */
	protected function installAdminTemplates()
	{
		$return = true;

		if ($return && !is_dir(_PS_OVERRIDE_DIR_.'controllers/admin/templates/products') && !@mkdir(_PS_OVERRIDE_DIR_.'controllers/admin/templates/products', 0755, true))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not create admin template dir.');
		}

		if ($return && !@copy($this->local_path.'override/controllers/admin/templates/products/quantities.tpl', _PS_OVERRIDE_DIR_.'controllers/admin/templates/products/quantities.tpl'))
		{
			$this->_errors[] = $this->l('Could not copy admin templates.');
			$return &= false;
		}

        /*
        if ($return && !@copy($this->local_path.'override/controllers/admin/templates/products/combinations.tpl', _PS_OVERRIDE_DIR_.'controllers/admin/templates/products/combinations.tpl'))
        {
            $this->_errors[] = $this->l('Could not copy admin templates.');
            $return &= false;
        }
        */

		if ($return && !is_dir(_PS_OVERRIDE_DIR_.'controllers/admin/templates/customers/helpers/view') && !@mkdir(_PS_OVERRIDE_DIR_.'controllers/admin/templates/customers/helpers/view', 0755, true))
		{
			$return &= false;
			$this->_errors[] = $this->l('Could not create admin template dir.');
		}

		if ($return && !@copy($this->local_path.'override/controllers/admin/templates/customers/helpers/view/view.tpl', _PS_OVERRIDE_DIR_.'controllers/admin/templates/customers/helpers/view/view.tpl'))
		{
			$this->_errors[] = $this->l('Could not copy admin templates.');
			$return &= false;
		}

		return $return;

	}

	/* uninstall admin override templates */
	protected function uninstallAdminTemplates()
	{
		$return = true;

		$return &= @unlink(_PS_OVERRIDE_DIR_.'controllers/admin/templates/products/quantities.tpl');

        /*
        $return &= @unlink(_PS_OVERRIDE_DIR_.'controllers/admin/templates/products/combinations.tpl');
        */

		$return &= @unlink(_PS_OVERRIDE_DIR_.'controllers/admin/templates/customers/helpers/view/view.tpl');

		return $return;
	}

	/* Install all hooks from $this->hooks */
	protected function installHooks()
	{
		$return = true;

		foreach ($this->hooks as $hook_name => $hook)
		{

			if (Hook::getIdByName($hook_name))
				continue;

			$new_hook = new Hook();
			$new_hook->name = $hook_name;
			$new_hook->title = $hook['name'];
			$new_hook->position = true;
			$new_hook->live_edit = false;

			if (!$new_hook->add())
			{
				$return &= false;
				$this->_errors[] = $this->l('Could not install new hook').': '.$hook_name;
			}

		}

		return $return;
	}

	/* register this module to all hooks from $this->hooks */
	protected function installRegisterHooks()
	{
		$return = true;

		foreach ($this->hooks as $hook_name => $hook)
		{
			if (!$this->registerHook($hook_name))
			{
				$return &= false;
				$this->_errors[] = $this->l('Could not register hook').': '.$hook_name;
			}
		}

		return $return;
	}

	/* uninstall this module */
	public function uninstall()
	{
		$return = true;

		// uninstall parent
		$return &= parent::uninstall();

		// global configuration

		// shop specific configuration
		$return &= Configuration::deleteByName('PS_EU_PAYMENT_API');
		$return &= Configuration::deleteByName('SHOPPING_CART_TEXT_BEFORE');
		$return &= Configuration::deleteByName('SHOPPING_CART_TEXT_AFTER');
		$return &= Configuration::deleteByName('LEGAL_SHIPTAXMETH');
		$return &= Configuration::deleteByName('LEGAL_DELIVERY_NOW');
		$return &= Configuration::deleteByName('LEGAL_DELIVERY_LATER');
		$return &= Configuration::deleteByName('LEGAL_SHOW_WEIGHTS');
		$return &= Configuration::deleteByName('LEGAL_SHOW_FANCY');
		$return &= Configuration::deleteByName('LEGAL_SHOW_FROM');
		$return &= Configuration::deleteByName('LEGAL_SHOW_FULLTAXINFO');
		$return &= Configuration::deleteByName('LEGAL_SHOW_FULLUNITPRICE');
		$return &= Configuration::deleteByName('LEGAL_SHOW_DESCINCART');
		$return &= Configuration::deleteByName('LEGAL_SHOW_DELIVERYINFO');

		foreach ($this->cms_pages as $cms_page)
			if (strpos($cms_page['config'], $this->config_prefix) === 0)
				$return &= Configuration::deleteByName($cms_page['config']);

		// restore database structure
		if ($return && $this->dbColumnExists('product_lang', 'delivery_now') &&
			$this->dbColumnExists('product_lang', 'delivery_later') &&
			!DB::getInstance()->execute('
			ALTER TABLE `'._DB_PREFIX_.'product_lang`
			DROP COLUMN `delivery_now`, 
			DROP COLUMN `delivery_later`;
		'))
			$return &= false;

        /*
        if ($return && $this->dbColumnExists('product_attribute_lang', 'delivery_now') &&
            $this->dbColumnExists('product_attribute_lang', 'delivery_later') &&
            !DB::getInstance()->execute('
			DROP TABLE `'._DB_PREFIX_.'product_attribute_lang`
		'))
            $return &= false;
        */

		$this->uninstallAdminTemplates();

		// regenerate class index
		Autoload::getInstance()->generateIndex();

		return (bool)$return;
	}

	/* reset this module without uninstall and install itself */
	public function reset()
	{
		$return = true;

		// global configuration
		$return &= Configuration::updateGlobalValue('PS_EU_PAYMENT_API', 1);

		// shop specific configuration
		$values = array();
		foreach ($this->languages as $language)
			$values[$language['id_lang']] = '';
		$return &= Configuration::updateValue('SHOPPING_CART_TEXT_BEFORE', $values);

		$values = array();
		foreach ($this->languages as $language)
			$values[$language['id_lang']] = '';
		$return &= Configuration::updateValue('SHOPPING_CART_TEXT_AFTER', $values);

		$return &= Configuration::updateValue('LEGAL_SHIPTAXMETH', true);

		$values = array();
		foreach ($this->languages as $language)
			$values[$language['id_lang']] = $this->deliveryNowDefault;
		$return &= Configuration::updateValue('LEGAL_DELIVERY_NOW', $values);

		$values = array();
		foreach ($this->languages as $language)
			$values[$language['id_lang']] = $this->deliveryLaterDefault;
		$return &= Configuration::updateValue('LEGAL_DELIVERY_LATER', $values);

		$return &= Configuration::updateValue('LEGAL_SHOW_WEIGHTS', 1);

		$return &= Configuration::updateValue('LEGAL_SHOW_FROM', 0);

		$return &= Configuration::updateValue('LEGAL_SHOW_FANCY', false);

		$return &= Configuration::updateValue('LEGAL_SHOW_FULLTAXINFO', 1);

		$return &= Configuration::updateValue('LEGAL_SHOW_FULLUNITPRICE', 1);

		$return &= Configuration::updateValue('LEGAL_SHOW_DESCINCART', 1);

		$return &= Configuration::updateValue('LEGAL_SHOW_DELIVERYINFO', 1);

		foreach ($this->cms_pages as $cms_page)
			if (strpos($cms_page['config'], $this->config_prefix) === 0)
				$return &= Configuration::updateValue($cms_page['config'], 0);

		return (bool)$return;
	}

	/*******************************************************************************************************************
	 *
	 * Module Configuration
	 *
	 *******************************************************************************************************************/

	/* module configuration */
	public function getContent()
	{
		$html = '';

		$this->context->controller->addCSS($this->_path.'views/css/admin/legal.css');

		$html .= $this->displayInfo();
		$html .= $this->postProcess();
		$html .= $this->displayForm();

		return $html;
	}

	/* display module logo and infos */
	public function displayInfo()
	{
		$this->smarty->assign(array(
			'_path' => $this->_path,
			'displayName' => $this->displayName,
			'author' => $this->author,
			'description' => $this->description,
		));

		return $this->display(__FILE__, 'views/templates/module/info.tpl');
	}

	/* display form */
	public function displayForm()
	{
		$html = '';

		$html .= $this->displayFormSettings();
		$html .= $this->displayFormMails();
		$html .= $this->displayFormPdf();
		$html .= $this->displayFormTheme();

		return $html;
	}

	/* general settings form */
	protected function displayFormSettings()
	{
		$helper = new HelperOptions();

		// Helper Options
		$helper->required = false;
		$helper->id = Tab::getCurrentTabId(); //always Tab::getCurrentTabId() at helper option

		// Helper
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		$helper->table = '';
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->module = $this;
		$helper->identifier = null; //alway null at helper option
		$helper->toolbar_btn = null; //alway null at helper option
		$helper->ps_help_context = null; //alway null at helper option
		$helper->title = null; //alway null at helper option
		$helper->show_toolbar = true; //alway true at helper option
		$helper->toolbar_scroll = false; //alway false at helper option
		$helper->bootstrap = false; //alway false at helper option

		$this->getOptionFieldsSettings();
		return $helper->generateOptions($this->option_fields_settings);
	}


	/* mail form */
	protected function displayFormMails()
	{
		$helper = new HelperForm();

		// Helper Form
		$helper->languages = $this->languages;
		$helper->default_form_language = $this->default_language_id;
		$helper->submit_action = 'submitSaveMail';

		// Helper
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		$helper->table = 'configuration';
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->module = $this;
		$helper->title = null;

		$helper->fields_value['theme'] = '';

		$this->getFormFieldsMails();
		return $helper->generateForm($this->form_fields_mails);
	}

	/*
	 * Displays a form with PDF settings in BO
	 *
	 * @access protected
	 *
	 * @return string - form's html
	 */
	protected function displayFormPdf()
	{
		$helper = new HelperForm();

		// Helper Form
		$helper->languages = $this->languages;
		$helper->default_form_language = $this->default_language_id;
		$helper->submit_action = 'submitSavePdf';

		// Helper
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		$helper->table = 'configuration';
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->module = $this;
		$helper->title = null;

		$helper->fields_value['theme'] = '';

		$this->getFormFieldsPdf();
		return $helper->generateForm($this->form_fields_pdf);
	}

	/* theme form */
	protected function displayFormTheme()
	{
		$helper = new HelperForm();

		// Helper Form
		$helper->languages = $this->languages;
		$helper->default_form_language = $this->default_language_id;
		$helper->submit_action = 'submitSaveTheme';

		// Helper
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		$helper->table = 'configuration';
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->module = $this;
		$helper->title = null;

		$helper->fields_value['LEGAL_CSS'] = Configuration::get('LEGAL_CSS');

		$this->getFormFieldsTheme();
		return $helper->generateForm($this->form_fields_theme);
	}

	protected function getOptionFieldsSettings()
	{
		$cms_pages = array();

		foreach ($this->cms_pages as $cms_page)
		{
			$cms_pages[$cms_page['config']] = array(
				'type' => 'select',
				'list' => $this->getCMSPages(),
				'identifier' => 'id_cms',
				'title' => $cms_page['title'],
			);
		}

		$this->option_fields_settings = array(
			array(
				'title' => $this->l('Global Settings'),
				'info' => $this->l('Global Settings for all shops'),
				'icon' => 'icon-globe',
				'fields' => array(
					'PS_EU_PAYMENT_API' => array(
						'type' => 'bool',
						'title' => $this->l('Legal Secure checkout for EU countries'),
						'desc' => $this->l('Enable EU payment mode for payment modules. Note that it requires those modules to be specially designed.'),
						'auto_value' => true,
						'value' => Configuration::getGlobalValue('PS_EU_PAYMENT_API'),
						'no_multishop_checkbox' => true,
					),
				),
				'submit' => array(
					'title' => $this->l('Save global options'),
					'name' => 'submitSaveOptions',
				),
			),
			array(
				'title' => $this->l('General Options'),
				'info' => $this->l('General settings for your shop'),
				'icon' => 'icon-cog',
				'fields' => array(
					'SHOPPING_CART_TEXT_BEFORE' => array(
						'type' => 'textareaLang',
						'title' => $this->l('Shopping cart text 1'),
						'desc' => $this->l('This text is displayed before the shopping cart block.'),
					),
					'SHOPPING_CART_TEXT_AFTER' => array(
						'type' => 'textareaLang',
						'title' => $this->l('Shopping cart text 2'),
						'desc' => $this->l('This text is displayed after the shopping cart block.'),
					),
					'LEGAL_SHIPTAXMETH' => array(
						'type' => 'bool',
						'title' => $this->l('Shipping tax method'),
						'desc' => $this->l('Calculates the average tax of all products for shipping instead of a fixed tax value.')
					),
					'LEGAL_DELIVERY_NOW' => array(
						'type' => 'textLang',
						'title' => $this->l('Availabiliy "in stock"'),
						'desc' => $this->l('Displayed text when in-stock default value. E.g.').' '.$this->deliveryNowDefault,
					),
					'LEGAL_DELIVERY_LATER' => array(
						'type' => 'textLang',
						'title' => $this->l('Availabiliy "back-ordered"'),
						'desc' => $this->l('Displayed text when allowed to be back-ordered default value. E.g.').' '.$this->deliveryLaterDefault,
					),
					'LEGAL_SHOW_WEIGHTS' => array(
						'type' => 'bool',
						'title' => $this->l('Show product weights'),
						'desc' => $this->l('Shows the product weights at your product if weight higher than zero.'),
					),
					'LEGAL_SHOW_FANCY' => array(
						'type' => 'bool',
						'title' => $this->l('Show fancybox excl. shipping'),
						'desc' => $this->l('Shows a fancybox on the excl. shipping links.'),
					),
					'LEGAL_SHOW_FROM' => array(
						'type' => 'bool',
						'title' => $this->l('Show \'From\' before price'),
						'desc' => $this->l('Show \'From\' before price in product list if product has combinations.'),
					),
					'LEGAL_SHOW_FULLTAXINFO' => array(
						'type' => 'bool',
						'title' => $this->l('Show tax info'),
						'desc' => $this->l('Displays the tax info in product listing.'),
					),
					'LEGAL_SHOW_FULLUNITPRICE' => array(
						'type' => 'bool',
						'title' => $this->l('Show unit price'),
						'desc' => $this->l('Displays the unit price in product listing.'),
					),
					'LEGAL_SHOW_DESCINCART' => array(
						'type' => 'bool',
						'title' => $this->l('Show short description'),
						'desc' => $this->l('Displays the products short description in cart.'),
					),
					'LEGAL_SHOW_DELIVERYINFO' => array(
						'type' => 'bool',
						'title' => $this->l('Show delivery info'),
						'desc' => $this->l('Displays a delivery information in cart at the deliverycosts.'),
					),
				),
				'submit' => array(
					'title' => $this->l('Save general options'),
					'name' => 'submitSaveOptions',
				),
			),
			array(
				'title' => $this->l('CMS Pages'),
				'info' => $this->l('Assign your CMS pages. Below you can add cms templates to your shop if they dont exists.'),
				'icon' => 'icon-pencil',
				'fields' => $cms_pages,
				'buttons' => array(
					array(
						'title' => $this->l('Add CMS Pages'),
						'name' => 'submitAddCMSPages',
						'type' => 'submit',
						'icon' => 'process-icon-plus',
					),
				),
				'submit' => array(
					'title' => $this->l('Save CMS assignment'),
					'name' => 'submitSaveOptions',
				),
			),
		);

	}

	protected function getFormFieldsMails()
	{
		$templates = $this->getThemeList();

		$this->form_fields_mails = array(
			array(
				'form' => array(
					'legend' => array(
						'title' => $this->l('Email Settings'),
						'icon' => 'icon-envelope-o'
					),
					'description' => $this->l('You can override your email templates with the email templates suppurtet by eu legal. Please be sure to backup your current email templates!'),
					'input' => array(
						array(
							'type' => 'select',
							'name' => 'theme',
							'label' => $this->l('Theme directory'),
							'desc' => $this->l('Select your theme directory from the list above.'),
							'options' => array(
								'default' => array(
									'value' => '',
									'label' => $this->l('-- Select a Theme --'),
								),
								'query' => $templates,
								'id' => 'id',
								'name' => 'name',
							),
						),
					),
					'submit' => array(
						'title' => $this->l('Add Email Templates'),
						'icon' => 'process-icon-plus',
					),
				),
			),
		);

	}

	/*
	 * Creates a form with PDF settings in BO
	 *
	 * @access protected
	 *
	 * @return void
	 */
	protected function getFormFieldsPdf()
	{
		$templates = $this->getThemeList();

		$this->form_fields_pdf = array(
			array(
				'form' => array(
					'legend' => array(
						'title' => $this->l('PDF Settings'),
						'icon' => 'icon-file-pdf-o'
					),
					'description' => $this->l('You can override your pdf templates with the pdf templates suppurtet by eu legal. Please be sure to backup your current pdf templates!'),
					'input' => array(
						array(
							'type' => 'select',
							'name' => 'theme',
							'label' => $this->l('Theme directory'),
							'desc' => $this->l('Select your theme directory from the list above.'),
							'options' => array(
								'default' => array(
									'value' => '',
									'label' => $this->l('-- Select a Theme --'),
								),
								'query' => $templates,
								'id' => 'id',
								'name' => 'name',
							),
						),
					),
					'submit' => array(
						'title' => $this->l('Add PDF Templates'),
						'icon' => 'process-icon-plus',
					),
				),
			),
		);

	}

	protected function getFormFieldsTheme()
	{
		$this->form_fields_theme = array(
			array(
				'form' => array(
					'legend' => array(
						'title' => $this->l('Template Settings'),
						'icon' => 'icon-picture-o'
					),
					'input' => array(
						array(
							'type' => 'switch',
							'label' => $this->l('EU Legal CSS'),
							'name' => 'LEGAL_CSS',
							'desc' => $this->l('Activate the provides EU Legal CSS file for your theme.'),
							'values' => array(
								array(
									'value' => 1,
								),
								array(
									'value' => 0,
								),
							),
						),
					),
					'submit' => array(
						'title' => $this->l('Save theme settings'),
						'icon' => 'process-icon-plus',
					)
				),
			),
		);

		foreach ($this->searchHooksInThemes() as $theme => $missing_hook)
		{
			$missing_hooks = '';

			$missing_hooks .= 'Theme <i>'.$theme.'</i>: ';
			$missing_hooks .= '<ul>';

			foreach ($missing_hook as $template => $hooks)
			{
				$missing_hooks .= '<li>Template <i>'.$template.'</i>: ';
				$missing_hooks .= '<ul>';

				foreach ($hooks as $hook)
					$missing_hooks .= '<li>Hook <i>'.$hook.'</i></li>';

				$missing_hooks .= '</li></ul>';

			}

			$missing_hooks .= '</ul>';

			$this->form_fields_theme[0]['form']['input'][] = array(
				'type' => 'html',
				'label' => sprintf($this->l('Hooks in theme "%s"'), $theme),
				'id' => 'missing_hooks',
				'name' => (!empty($missing_hooks) ? '<div class="alert alert-warning"><b>'.$this->l('There are some missing hooks!').'</b><br>'.$missing_hooks.'</div>' : '<div class="alert alert-success">'.$this->l('There are all hooks in your themes available.').'</div>'),
				'desc' => $this->l('Are all EU Legal hooks available in your themes?'),
			);

		}

	}

	/*******************************************************************************************************************
	 *
	 * Post Process
	 *
	 *******************************************************************************************************************/

	public function postProcess()
	{
		$this->_errors = array();

		// Generelle Einstellungen
		if (Tools::isSubmit('submitSaveOptions'))
		{
			// Global Settings
			if (!Configuration::updateGlobalValue('PS_EU_PAYMENT_API', (bool)Tools::getValue('PS_EU_PAYMENT_API')))
				$this->_errors[] = $this->l('Could not update').': PS_EU_PAYMENT_API';

			$values = array();

			foreach ($this->languages as $language)
				$values[$language['id_lang']] = Tools::getValue('SHOPPING_CART_TEXT_BEFORE_'.$language['id_lang']);

			if (!Configuration::updateValue('SHOPPING_CART_TEXT_BEFORE', $values))
				$this->_errors[] = $this->l('Could not update').': SHOPPING_CART_TEXT_BEFORE';

			$values = array();

			foreach ($this->languages as $language)
				$values[$language['id_lang']] = Tools::getValue('SHOPPING_CART_TEXT_AFTER_'.$language['id_lang']);

			if (!Configuration::updateValue('SHOPPING_CART_TEXT_AFTER', $values))
				$this->_errors[] = $this->l('Could not update').': SHOPPING_CART_TEXT_AFTER';

			if (!Configuration::updateValue('LEGAL_SHIPTAXMETH', (bool)Tools::getValue('LEGAL_SHIPTAXMETH')))
				$this->_errors[] = $this->l('Could not update').': LEGAL_SHIPTAXMETH';

			// Produktverfügbarkeit
			$values = array();

			foreach ($this->languages as $language)
				$values[$language['id_lang']] = Tools::getValue('LEGAL_DELIVERY_NOW_'.$language['id_lang']);

			if (!Configuration::updateValue('LEGAL_DELIVERY_NOW', $values))
				$this->_errors[] = $this->l('Could not update').': LEGAL_DELIVERY_NOW';

			$values = array();

			foreach ($this->languages as $language)
				$values[$language['id_lang']] = Tools::getValue('LEGAL_DELIVERY_LATER_'.$language['id_lang']);

			if (!Configuration::updateValue('LEGAL_DELIVERY_LATER', $values))
				$this->_errors[] = $this->l('Could not update').': LEGAL_DELIVERY_LATER';

			if (!Configuration::updateValue('LEGAL_SHOW_WEIGHTS', (bool)Tools::getValue('LEGAL_SHOW_WEIGHTS')))
				$this->_errors[] = $this->l('Could not update').': LEGAL_SHOW_WEIGHTS';

			if (!Configuration::updateValue('LEGAL_SHOW_FANCY', (bool)Tools::getValue('LEGAL_SHOW_FANCY')))
				$this->_errors[] = $this->l('Could not update').': LEGAL_SHOW_FANCY';

			if (!Configuration::updateValue('LEGAL_SHOW_FROM', (bool)Tools::getValue('LEGAL_SHOW_FROM')))
				$this->_errors[] = $this->l('Could not update').': LEGAL_SHOW_FROM';

			if (!Configuration::updateValue('LEGAL_SHOW_FULLTAXINFO', (bool)Tools::getValue('LEGAL_SHOW_FULLTAXINFO')))
				$this->_errors[] = $this->l('Could not update').': LEGAL_SHOW_FULLTAXINFO';

			if (!Configuration::updateValue('LEGAL_SHOW_FULLUNITPRICE', (bool)Tools::getValue('LEGAL_SHOW_FULLUNITPRICE')))
				$this->_errors[] = $this->l('Could not update').': LEGAL_SHOW_FULLUNITPRICE';

			if (!Configuration::updateValue('LEGAL_SHOW_DESCINCART', (bool)Tools::getValue('LEGAL_SHOW_DESCINCART')))
				$this->_errors[] = $this->l('Could not update').': LEGAL_SHOW_DESCINCART';

			if (!Configuration::updateValue('LEGAL_SHOW_DELIVERYINFO', (bool)Tools::getValue('LEGAL_SHOW_DELIVERYINFO')))
				$this->_errors[] = $this->l('Could not update').': LEGAL_SHOW_DELIVERYINFO';

			// CMS IDs festlegen
			if (!Configuration::updateValue('LEGAL_CMS_ID_LEGAL', (int)Tools::getValue('LEGAL_CMS_ID_LEGAL')))
				$this->_errors[] = $this->l('Could not update').': LEGAL_CMS_ID_LEGAL';

			if (!Configuration::updateValue('PS_CONDITIONS_CMS_ID', (int)Tools::getValue('PS_CONDITIONS_CMS_ID')))
				$this->_errors[] = $this->l('Could not update').': PS_CONDITIONS_CMS_ID';

			if (!Configuration::updateValue('LEGAL_CMS_ID_REVOCATION', (int)Tools::getValue('LEGAL_CMS_ID_REVOCATION')))
				$this->_errors[] = $this->l('Could not update').': LEGAL_CMS_ID_REVOCATION';

			if (!Configuration::updateValue('LEGAL_CMS_ID_REVOCATIONFORM', (int)Tools::getValue('LEGAL_CMS_ID_REVOCATIONFORM')))
				$this->_errors[] = $this->l('Could not update').': LEGAL_CMS_ID_REVOCATIONFORM';

			if (!Configuration::updateValue('LEGAL_CMS_ID_PRIVACY', (int)Tools::getValue('LEGAL_CMS_ID_PRIVACY')))
				$this->_errors[] = $this->l('Could not update').': LEGAL_CMS_ID_PRIVACY';

			if (!Configuration::updateValue('LEGAL_CMS_ID_ENVIRONMENTAL', (int)Tools::getValue('LEGAL_CMS_ID_ENVIRONMENTAL')))
				$this->_errors[] = $this->l('Could not update').': LEGAL_CMS_ID_ENVIRONMENTAL';

			if (!Configuration::updateValue('LEGAL_CMS_ID_SHIPPING', (int)Tools::getValue('LEGAL_CMS_ID_SHIPPING')))
				$this->_errors[] = $this->l('Could not update').': LEGAL_CMS_ID_SHIPPING';

			if (count($this->_errors) <= 0) {
				$this->_clearCache('*');
				return $this->displayConfirmation($this->l('Settings updated'));
			}

		}
		elseif (Tools::isSubmit('submitAddCMSPages'))
		{
			// install all cms pages
			foreach ($this->cms_pages as $cms_page)
			{
				if ($content = @Tools::file_get_contents($this->local_path.'cms/'.$cms_page['name'].'.txt'))
				{
					$cms = new CMS();
					$cms->active = true;
					$cms->id_cms_category = 1;

					$content = preg_replace('#src="(.*)"#u', 'src="'.Context::getContext()->shop->getBaseURL().'\\1"', $content);

					foreach ($this->languages as $language)
					{
						$cms->meta_title[$language['id_lang']] = $cms_page['title'];
						$cms->meta_description[$language['id_lang']] = $cms_page['title'];
						$cms->meta_keywords[$language['id_lang']] = $cms_page['title'];
						$cms->link_rewrite[$language['id_lang']] = Tools::link_rewrite($cms_page['title']);
						$cms->content[$language['id_lang']] = trim($content);
					}

					if (!$cms->add())
						$this->_errors[] = $this->l('Could not add new cms page').': '.$cms_page['name'];

					Configuration::updateValue($cms_page['config'], $cms->id);

					$_POST[$cms_page['config']] = $cms->id;

				}
				else
					$this->_errors[] = $this->l('Could not open file').': cms/'.$cms_page['name'].'.txt';

			}

			// copy cms images
			try
			{
				$this->rcopy('modules/'.$this->name.'/img/cms/', 'img/cms/', array('root' => _PS_ROOT_DIR_));
			} catch (Exception $e)
			{
				$this->_errors[] = $this->l('Could not copy').': /img/cms/';
			}

			if (count($this->_errors) <= 0)
				return $this->displayConfirmation($this->l('CMS Pages created'));
		}

		elseif (Tools::isSubmit('submitSaveMail'))
		{
			if (!$theme = Tools::getValue('theme'))
				$this->_errors[] = $this->l('Please select a theme.');
			else
			{
				if (!is_dir(_PS_ALL_THEMES_DIR_.$theme.'/mails/') && !mkdir(_PS_ALL_THEMES_DIR_.$theme.'/mails/', 0755, true))
					$this->_errors[] = $this->l('Could not create mail dir.');
				else
				{
					try
					{
						$this->rcopy('modules/'.$this->name.'/mails/', 'themes/'.$theme.'/mails/', array('root' => _PS_ROOT_DIR_));
					} catch (Exception $e)
					{
						$this->_errors[] = $this->l('Could not copy').': modules/'.$this->name.'/mails/';
					}

				}

			}

			if (count($this->_errors) <= 0)
				return $this->displayConfirmation($this->l('Mails saved'));

		}
		elseif (Tools::isSubmit('submitSavePdf'))
		{

			if (!$theme = Tools::getValue('theme'))
				$this->_errors[] = $this->l('Please select a theme.');
			else
			{

				if (!is_dir(_PS_ALL_THEMES_DIR_.$theme.'/pdf/') && !mkdir(_PS_ALL_THEMES_DIR_.$theme.'/pdf/', 0755, true))
					$this->_errors[] = $this->l('Could not create pdf dir.');
				else
				{

					try
					{
						$this->rcopy('modules/'.$this->name.'/pdf/', 'themes/'.$theme.'/pdf/', array('root' => _PS_ROOT_DIR_));
					} catch (Exception $e)
					{
						$this->_errors[] = $this->l('Could not copy').': modules/'.$this->name.'/pdf/';
					}

				}

			}

			if (count($this->_errors) <= 0)
				return $this->displayConfirmation($this->l('PDF files were saved'));
		}
		elseif (Tools::isSubmit('submitSaveTheme'))
		{

			if (!Configuration::updateValue('LEGAL_CSS', (bool)Tools::getValue('LEGAL_CSS')))
				$this->_errors[] = $this->l('Could not update').': LEGAL_CSS';

			if (count($this->_errors) <= 0)
				return $this->displayConfirmation($this->l('Theme settings saved'));

		}
		

		if (!empty($this->_errors))
			return $this->displayError(implode('<br>', $this->_errors));

		return '';

	}

	/*******************************************************************************************************************
	 *
	 * Helper Functions
	 *
	 *******************************************************************************************************************/

	/*
	 * Returns a list of available themes as an array
	 *
	 * @access private
	 *
	 * @return mixed - array of themes or boolean false if no themes were detected
	 */
	private function getThemeList()
	{
		$templates = array();

		$files = scandir(_PS_ALL_THEMES_DIR_);
		foreach ($files as $file)
			if (is_dir(_PS_ALL_THEMES_DIR_.'/'.$file) && !in_array($file, array('.', '..')))
				$templates[] = array('id' => $file, 'name' => $file);

		return count($templates) ? $templates : false;
	}

	private function searchHooksInThemes()
	{
		$themes = Theme::getThemes();

		$not_found_hooks = array();

		foreach ($themes as $theme)
		{

			if (!$theme->isUsed())
				continue;

			$this->searchHooksInTheme($theme->directory, $not_found_hooks);

		}

		return $not_found_hooks;
	}

	private function searchHooksInTheme($theme, &$not_found_hooks)
	{
		foreach ($this->hooks as $hook_name => $hook)
		{

			$search = '{hook h="'.$hook_name.'"';

			foreach ($hook['templates'] as $template)
			{

				$content = '';

				$content = Tools::file_get_contents(_PS_ALL_THEMES_DIR_.$theme.'/'.$template);

				if (!$content || (strstr($content, $search) === false))
					$not_found_hooks[$theme][$template][] = $hook_name;

			}

		}
	}

	private function getCMSPages()
	{
		if (empty(self::$_cms_pages))
		{

			$result = CMS::getCMSPages($this->default_language_id, null, false);

			self::$_cms_pages[] = array('id_cms' => 0, 'name' => $this->l('-- Please select a CMS page --'));

			foreach ($result as $key => $row)
				self::$_cms_pages[] = array('id_cms' => $row['id_cms'], 'name' => $row['id_cms'].'-'.$row['meta_title']);

		}

		return self::$_cms_pages;
	}

	public function rcopy($src, $dest, $options = array())
	{
		/**
		 * function rcopy()
		 * @author: Markus Engel @ Presta-Profi.de
		 * @release: 1.1
		 * @mail: info@presta-profi.de
		 * @www: presta-profi.de
		 *
		 * @return: true if success, error exception if not
		 * @params:
		 *  src: string, source file/dir name, required
		 *  dest: string, destination file/dir name, required
		 *  options: array, optional
		 *   file_permission
		 *   dir_permission
		 *   root
		 *   ds
		 * @license: CC BY-NC-SA 4.0 http://creativecommons.org/licenses/by-nc-sa/4.0/
		 */

		if (empty($src))
			throw new Exception('Source is empty.');

		if (empty($dest))
			throw new Exception('Destination is empty.');

		$options_default = array(
			'file_permission' => 0644,
			'dir_permission' => 0755,
			'root' => '/',
			'ds' => '/'
		);

		$options = array_merge($options_default, $options);

		$is_win = (DIRECTORY_SEPARATOR == '\\');

		if ($is_win)
		{
			$src = strtr($src, '\\', '/');
			$dest = str_replace('\\', '/', $dest);
			$options['root'] = str_replace('\\', '/', $options['root']);

			$src = preg_replace('#[a-z]{1}:#i', '', $src);
			$dest = preg_replace('#[a-z]{1}:#i', '', $dest);
			$options['root'] = preg_replace('#[a-z]{1}:#i', '', $options['root']);
		}

		if (!preg_match('#0[1-7]{3}#', sprintf('%o', $options['file_permission'])))
			$options['file_permission'] = $options_default['file_permission'];

		if (!preg_match('#0[1-7]{3}#', sprintf('%o', $options['dir_permission'])))
			$options['dir_permission'] = $options_default['dir_permission'];

		if (!in_array($options['ds'], array('/', '\\')))
			$options['ds'] = $options_default['ds'];

		// DS vom Ende entfernen
		if (Tools::substr($src, -1) == $options['ds'])
			$src = Tools::substr($src, 0, -1);
		if (Tools::substr($dest, -1) == $options['ds'])
			$dest = Tools::substr($dest, 0, -1);

		// DS vom Anfang entfernen
		if (Tools::substr($src, 0, 1) == $options['ds'])
			$src = Tools::substr($src, 1);
		if (Tools::substr($dest, 0, 1) == $options['ds'])
			$dest = Tools::substr($dest, 1);

		// DS am Ende hinzufügen
		if (Tools::substr($options['root'], -1) != $options['ds'])
			$options['root'] = $options['root'].$options['ds'];

		// DS am Anfang hinzufügen
		if (Tools::substr($options['root'], 0, 1) != $options['ds'])
			$options['root'] = $options['ds'].$options['root'];

		if (is_link($options['root'].$src))
		{
			if (!symlink(readlink($options['root'].$src), $options['root'].$dest))
				throw new Exception('Can not create symlink from source: '.$options['root'].$src);
		}
		elseif (is_file($options['root'].$src))
		{
			if (!copy($options['root'].$src, $options['root'].$dest))
				throw new Exception('Can not copy file from source: '.$options['root'].$src);

			if (!$is_win)
				chmod($options['root'].$dest, $options['file_permission']);
		}
		elseif (is_dir($options['root'].$src))
		{
			if (is_file($options['root'].$dest) || is_link($options['root'].$dest))
				throw new Exception('Destination must be a directory: '.$options['root'].$dest);
			elseif (!is_dir($options['root'].$dest) && !mkdir($options['root'].$dest,
					(!$is_win ? $options['dir_permission'] : null)))
				throw new Exception('Can not create destination directory: '.$options['root'].$dest);

			if (!$dir = dir($options['root'].$src))
				throw new Exception('Can not open directory: '.$options['root'].$src);

			while (false !== ($entry = $dir->read()))
			{
				if ($entry == '.' || $entry == '..')
					continue;

				$this->rcopy($src.$options['ds'].$entry, $dest.$options['ds'].$entry, $options);
			}
			$dir->close();
		}
		else
			throw new Exception('No file or directory: '.$options['root'].$src);

		return true;

	}

	public function checkOverrides()
	{
		$errors = array();

		Autoload::getInstance()->generateIndex();

		foreach (Tools::scandir($this->getLocalPath().'override', 'php', '', true) as $file)
		{
			$class = basename($file, '.php');

			if (Autoload::getInstance()->getClassPath($class.'Core') && $error = $this->checkOverride($class))
				$errors[] = $error;
		}

		return $errors;
	}

	public function checkOverride($classname)
	{
		$path = PrestaShopAutoload::getInstance()->getClassPath($classname.'Core');

		// Check if there is already an override file
		if (!($classpath = PrestaShopAutoload::getInstance()->getClassPath($classname)))
		{

			$override_dest = _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'override'.DIRECTORY_SEPARATOR.$path;

			if (!is_writable(dirname($override_dest)))
				return sprintf(Tools::displayError('directory (%s) not writable'), dirname($override_dest));

			return '';

		}

		// Check if override file is writable
		$override_path = _PS_ROOT_DIR_.'/'.PrestaShopAutoload::getInstance()->getClassPath($classname);

		// Get a uniq id for the class, because you can override a class (or remove the override) twice in the same session and we need to avoid redeclaration
		do $uniq = uniqid();
		while (class_exists($classname.'OverrideOriginal'.$uniq, false));

		if (!is_writable($override_path))
			return sprintf(Tools::displayError('file (%s) not writable'), $override_path);

		// Make a reflection of the override class and the module override class
		$override_file = file($override_path);

		eval(preg_replace(array('#^\s*<\?php#', '#class\s+'.$classname.'\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?#i'), array('', 'class '.$classname.'OverrideOriginal'.$uniq), implode('', $override_file)));
		$override_class = new ReflectionClass($classname.'OverrideOriginal'.$uniq);

		$module_file = file($this->getLocalPath().'override'.DIRECTORY_SEPARATOR.$path);
		eval(preg_replace(array('#^\s*<\?php#', '#class\s+'.$classname.'(\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?)?#i'), array('', 'class '.$classname.'Override'.$uniq), implode('', $module_file)));
		$module_class = new ReflectionClass($classname.'Override'.$uniq);

		// Check if none of the methods already exists in the override class
		foreach ($module_class->getMethods() as $method)
			if ($override_class->hasMethod($method->getName()))
				return sprintf(Tools::displayError('The method %1$s in the class %2$s is already overriden.'), $method->getName(), $classname);

		// Check if none of the properties already exists in the override class
		foreach ($module_class->getProperties() as $property)
			if ($override_class->hasProperty($property->getName()))
				return sprintf(Tools::displayError('The property %1$s in the class %2$s is already defined.'), $property->getName(), $classname);

		// Check if none of the constants already exists in the override class
		foreach ($module_class->getConstants() as $constant => $value)
			if ($override_class->hasConstant($constant))
				return sprintf(Tools::displayError('The constant %1$s in the class %2$s is already defined.'), $constant, $classname);

		return '';
	}

	public function deleteOverrides($classname) {

		$path = PrestaShopAutoload::getInstance()->getClassPath($classname.'Core');

		if (!PrestaShopAutoload::getInstance()->getClassPath($classname))
			return true;

		// Get a uniq id for the class, because you can override a class (or remove the override) twice in the same session and we need to avoid redeclaration
		do $uniq = uniqid();
		while (class_exists($classname.'OverrideOriginal_delete'.$uniq, false));

		$module_file = file($this->getLocalPath().'override/'.$path);
		eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$classname.'(\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?)?#i'), array(' ', 'class '.$classname.'Override_remove'.$uniq), implode('', $module_file)));
		$module_class = new ReflectionClass($classname.'Override_remove'.$uniq);

		foreach ($module_class->getMethods() as $method) {
			
			$this->deleteOverrideMethod($classname, $method->getName());

		}

		// Remove properties from override file
		foreach ($module_class->getProperties() as $property) {

			$this->deleteOverrideProperty($classname, $property->getName());

		}

		// Remove constants from override file
		foreach ($module_class->getConstants() as $constant => $value) {

			$this->deleteOverrideConstant($classname, $constant);
			
		}

		return true;

	}

	public function deleteOverrideMethod($classname, $function)
	{
		if (!PrestaShopAutoload::getInstance()->getClassPath($classname))
			return true;

		// Check if override file is writable
		$override_path = _PS_ROOT_DIR_.'/'.PrestaShopAutoload::getInstance()->getClassPath($classname);
		if (!is_writable($override_path))
			return false;

		do $uniq = uniqid();
		while (class_exists($classname.'OverrideOriginal_delete'.$uniq, false));

		// Make a reflection of the override class and the module override class
		$override_file = file($override_path);
		eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$classname.'\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?#i'), array(' ', 'class '.$classname.'OverrideOriginal_delete'.$uniq), implode('', $override_file)));
		$override_class = new ReflectionClass($classname.'OverrideOriginal_delete'.$uniq);

		// Remove methods from override file
		$override_file = file($override_path);

		if (!$override_class->hasMethod($function))
			return false;

		$method = $override_class->getMethod($function);
		$length = $method->getEndLine() - $method->getStartLine() + 1;
		array_splice($override_file, $method->getStartLine() - 1, $length, array_pad(array(), $length, '#--remove--#'));

		if (preg_match('/\* module: ('.$this->name.')/ism', $override_file[$method->getStartLine() - 5]))
			$override_file[$method->getStartLine() - 7] = $override_file[$method->getStartLine() - 6] = $override_file[$method->getStartLine() - 5] = $override_file[$method->getStartLine() - 4] = $override_file[$method->getStartLine() - 3] =  $override_file[$method->getStartLine() - 2] = '#--remove--#';
		
		// Rewrite nice code
		$code = '';
		foreach ($override_file as $line)
		{
			if ($line == '#--remove--#')
				continue;

			$code .= $line;
		}

		$to_delete = preg_match('/<\?(?:php)?\s+class\s+'.$classname.'\s+extends\s+'.$classname.'Core\s*?[{]\s*?[}]/ism', $code);

		if ($to_delete)
			unlink($override_path);
		else
			file_put_contents($override_path, $code);

		// Re-generate the class index
		Tools::generateIndex();

		return true;
	}

	public function deleteOverrideProperty($classname, $property)
	{
		if (!PrestaShopAutoload::getInstance()->getClassPath($classname))
			return true;

		// Check if override file is writable
		$override_path = _PS_ROOT_DIR_.'/'.PrestaShopAutoload::getInstance()->getClassPath($classname);
		if (!is_writable($override_path))
			return false;

		do $uniq = uniqid();
		while (class_exists($classname.'OverrideOriginal_delete'.$uniq, false));

		// Make a reflection of the override class and the module override class
		$override_file = file($override_path);
		eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$classname.'\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?#i'), array(' ', 'class '.$classname.'OverrideOriginal_delete'.$uniq), implode('', $override_file)));
		$override_class = new ReflectionClass($classname.'OverrideOriginal_delete'.$uniq);

		// Remove methods from override file
		$override_file = file($override_path);

		if (!$override_class->hasProperty($property))
			return true;

		// Remplacer la ligne de declaration par "remove"
		foreach ($override_file as $line_number => &$line_content)
			if (preg_match('/(public|private|protected)\s+(static\s+)?(\$)?'.$property.'/i', $line_content))
			{
				if (preg_match('/\* module: ('.$this->name.')/ism', $override_file[$line_number - 5]))
					$override_file[$line_number - 7] = $override_file[$line_number - 6] = $override_file[$line_number - 5] = $override_file[$line_number - 4] = $override_file[$line_number - 3] =  $override_file[$line_number - 2] = '#--remove--#';
				$line_content = '#--remove--#';
				break;
			}

		// Rewrite nice code
		$code = '';
		foreach ($override_file as $line)
		{
			if ($line == '#--remove--#')
				continue;

			$code .= $line;
		}

		$to_delete = preg_match('/<\?(?:php)?\s+class\s+'.$classname.'\s+extends\s+'.$classname.'Core\s*?[{]\s*?[}]/ism', $code);

		if ($to_delete)
			unlink($override_path);
		else
			file_put_contents($override_path, $code);

		// Re-generate the class index
		Tools::generateIndex();

		return true;
	}


	public function deleteOverrideConstant($classname, $constant)
	{
		if (!PrestaShopAutoload::getInstance()->getClassPath($classname))
			return true;

		// Check if override file is writable
		$override_path = _PS_ROOT_DIR_.'/'.PrestaShopAutoload::getInstance()->getClassPath($classname);
		if (!is_writable($override_path))
			return false;

		do $uniq = uniqid();
		while (class_exists($classname.'OverrideOriginal_delete'.$uniq, false));

		// Make a reflection of the override class and the module override class
		$override_file = file($override_path);
		eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$classname.'\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?#i'), array(' ', 'class '.$classname.'OverrideOriginal_delete'.$uniq), implode('', $override_file)));
		$override_class = new ReflectionClass($classname.'OverrideOriginal_delete'.$uniq);

		$override_file = file($override_path);

		if (!$override_class->hasConstant($constant))
			return true;

		// Remplacer la ligne de declaration par "remove"
		foreach ($override_file as $line_number => &$line_content)
			if (preg_match('/(const)\s+'.$constant.'/i', $line_content))
			{
				$line_content = '#--remove--#';
				break;
			}

		// Rewrite nice code
		$code = '';
		foreach ($override_file as $line)
		{
			if ($line == '#--remove--#')
				continue;

			$code .= $line;
		}

		$to_delete = preg_match('/<\?(?:php)?\s+class\s+'.$classname.'\s+extends\s+'.$classname.'Core\s*?[{]\s*?[}]/ism', $code);

		if ($to_delete)
			unlink($override_path);
		else
			file_put_contents($override_path, $code);

		// Re-generate the class index
		Tools::generateIndex();

		return true;
	}

	/* Nur temporär, kann in zukünftigen Versionen entfernt werden. Problem mit Upgrade und Overrides */
	public function addOverride($classname)
	{
		/*
		* Legal 0.0.1 | 20140324
		* Die Do-While schleife benötigt uniqid als Argument
		*/
		$path = PrestaShopAutoload::getInstance()->getClassPath($classname.'Core');

		// Check if there is already an override file, if not, we just need to copy the file
		if (PrestaShopAutoload::getInstance()->getClassPath($classname))
		{
			// Check if override file is writable
			$override_path = _PS_ROOT_DIR_.'/'.PrestaShopAutoload::getInstance()->getClassPath($classname);
			if ((!file_exists($override_path) && !is_writable(dirname($override_path))) || (file_exists($override_path) && !is_writable($override_path)))
				throw new Exception(sprintf(Tools::displayError('file (%s) not writable'), $override_path));

			// Get a uniq id for the class, because you can override a class (or remove the override) twice in the same session and we need to avoid redeclaration
			do $uniq = uniqid();
			while (class_exists($classname.'OverrideOriginal'.$uniq, false));

			// Make a reflection of the override class and the module override class
			$override_file = file($override_path);
			eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$classname.'\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?#i'), array(' ', 'class '.$classname.'OverrideOriginal'.$uniq), implode('', $override_file)));
			$override_class = new ReflectionClass($classname.'OverrideOriginal'.$uniq);

			$module_file = file($this->getLocalPath().'override'.DIRECTORY_SEPARATOR.$path);
			eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$classname.'(\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?)?#i'), array(' ', 'class '.$classname.'Override'.$uniq), implode('', $module_file)));
			$module_class = new ReflectionClass($classname.'Override'.$uniq);

			// Check if none of the methods already exists in the override class
			foreach ($module_class->getMethods() as $method)
			{
				if ($override_class->hasMethod($method->getName()))
				{
					$method_override = $override_class->getMethod($method->getName());
					if (preg_match('/module: (.*)/ism', $override_file[$method_override->getStartLine() - 5], $name) && preg_match('/date: (.*)/ism', $override_file[$method_override->getStartLine() - 4], $date) && preg_match('/version: ([0-9.]+)/ism', $override_file[$method_override->getStartLine() - 3], $version))
						throw new Exception(sprintf(Tools::displayError('The method %1$s in the class %2$s is already overridden by the module %3$s version %4$s at %5$s.'), $method->getName(), $classname, $name[1], $version[1], $date[1]));
					throw new Exception(sprintf(Tools::displayError('The method %1$s in the class %2$s is already overridden.'), $method->getName(), $classname));
				}
				else
					$module_file = preg_replace('/(^.*?function\s+'.$method->getName().')/ism', "\n\t/*\n\t* module: ".$this->name."\n\t* date: ".date('Y-m-d H:i:s')."\n\t* version: ".$this->version."\n\t*/\n$1", $module_file);
			}

			// Check if none of the properties already exists in the override class
			foreach ($module_class->getProperties() as $property)
			{
				if ($override_class->hasProperty($property->getName()))
					throw new Exception(sprintf(Tools::displayError('The property %1$s in the class %2$s is already defined.'), $property->getName(), $classname));
				else
					$module_file = preg_replace('/(public|private|protected|const)\s+(static\s+)?(\$?'.$property->getName().')/ism', "\n\t/*\n\t* module: ".$this->name."\n\t* date: ".date('Y-m-d H:i:s')."\n\t* version: ".$this->version."\n\t*/\n$1 $2 $3" , $module_file);
			}
			
			// Check if none of the constants already exists in the override class
			foreach ($module_class->getConstants() as $constant => $value)
				if ($override_class->hasConstant($constant))
					throw new Exception(sprintf(Tools::displayError('The constant %1$s in the class %2$s is already defined.'), $constant, $classname));

			// Insert the methods from module override in override
			$copy_from = array_slice($module_file, $module_class->getStartLine() + 1, $module_class->getEndLine() - $module_class->getStartLine() - 2);
			array_splice($override_file, $override_class->getEndLine() - 1, 0, $copy_from);
			$code = implode('', $override_file);
			file_put_contents($override_path, $code);
		}
		else
		{
			$override_src = $this->getLocalPath().'override'.DIRECTORY_SEPARATOR.$path;
			$override_dest = _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'override'.DIRECTORY_SEPARATOR.$path;
			if (!is_writable(dirname($override_dest)))
				throw new Exception(sprintf(Tools::displayError('directory (%s) not writable'), dirname($override_dest)));
			$module_file = file($override_src);
			do $uniq = uniqid();
			while (class_exists($classname.'OverrideOriginal_remove', false));

			eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$classname.'(\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?)?#i'), array(' ', 'class '.$classname.'Override'.$uniq), implode('', $module_file)));
			$module_class = new ReflectionClass($classname.'Override'.$uniq);

			// Add foreach function a comment with the module name and the module version like it permit us to know wich module do the override and permit an update
			foreach ($module_class->getMethods() as $method)
				$module_file = preg_replace('/(^.*?function\s+'.$method->getName().')/ism', "\n\t/*\n\t* module: ".$this->name."\n\t* date: ".date('Y-m-d H:i:s')."\n\t* version: ".$this->version."\n\t*/\n$1", $module_file);

			// same as precedent but for variable
			foreach ($module_class->getProperties() as $property)
				$module_file = preg_replace('/(public|private|protected|const)\s+(static\s+)?(\$?'.$property->getName().')/ism', "\n\t/*\n\t* module: ".$this->name."\n\t* date: ".date('Y-m-d H:i:s')."\n\t* version: ".$this->version."\n\t*/\n$1 $2 $3" , $module_file);

			file_put_contents($override_dest, $module_file);
			// Re-generate the class index
			Tools::generateIndex();
		}
		return true;
	}

	/* Nur temporär, kann in zukünftigen Versionen entfernt werden. Problem mit Upgrade und Overrides */
	public function removeOverride($classname)
	{
		/*
		* Legal 0.0.1 | 20140324
		* Die Do-While schleife benötigt uniqid als Argument
		* Bei löschen von overrides müssen auch Klassenkostanten 'const' entfernt werden
		*/
		$path = PrestaShopAutoload::getInstance()->getClassPath($classname.'Core');

		if (!PrestaShopAutoload::getInstance()->getClassPath($classname))
			return true;
		// Check if override file is writable
		$override_path = _PS_ROOT_DIR_.'/'.PrestaShopAutoload::getInstance()->getClassPath($classname);
		if (!is_writable($override_path))
			return false;

		// Get a uniq id for the class, because you can override a class (or remove the override) twice in the same session and we need to avoid redeclaration
		do $uniq = uniqid();
		while (class_exists($classname.'OverrideOriginal_remove'.$uniq, false));

		// Make a reflection of the override class and the module override class
		$override_file = file($override_path);
		eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$classname.'\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?#i'), array(' ', 'class '.$classname.'OverrideOriginal_remove'.$uniq), implode('', $override_file)));
		$override_class = new ReflectionClass($classname.'OverrideOriginal_remove'.$uniq);

		$module_file = file($this->getLocalPath().'override/'.$path);
		eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$classname.'(\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?)?#i'), array(' ', 'class '.$classname.'Override_remove'.$uniq), implode('', $module_file)));
		$module_class = new ReflectionClass($classname.'Override_remove'.$uniq);

		// Remove methods from override file
		$override_file = file($override_path);
		foreach ($module_class->getMethods() as $method)
		{
			if (!$override_class->hasMethod($method->getName()))
				continue;

			$method = $override_class->getMethod($method->getName());
			$length = $method->getEndLine() - $method->getStartLine() + 1;

			$module_method = $module_class->getMethod($method->getName());
			$module_length = $module_method->getEndLine() - $module_method->getStartLine() + 1;

			$override_file_orig = $override_file;

			$orig_content   = preg_replace("/\s/", '', implode('', array_splice($override_file, $method->getStartLine() - 1,        $length,        array_pad(array(), $length, '#--remove--#')        )));
			$module_content = preg_replace("/\s/", '', implode('', array_splice($module_file,   $module_method->getStartLine() - 1, $module_length, array_pad(array(), $module_length, '#--remove--#') )));

			$replace = true;
			if (preg_match('/\* module: ('.$this->name.')/ism', $override_file[$method->getStartLine() - 5]))
			{
				$override_file[$method->getStartLine() - 7] = $override_file[$method->getStartLine() - 6] = $override_file[$method->getStartLine() - 5] = $override_file[$method->getStartLine() - 4] = $override_file[$method->getStartLine() - 3] =  $override_file[$method->getStartLine() - 2] = '#--remove--#';
				$replace = false;
			}

			if (md5($module_content) != md5($orig_content) && $replace)
				$override_file = $override_file_orig;
		}

		// Remove properties from override file
		foreach ($module_class->getProperties() as $property)
		{
			if (!$override_class->hasProperty($property->getName()))
				continue;

			// Remplacer la ligne de declaration par "remove"
			foreach ($override_file as $line_number => &$line_content)
				if (preg_match('/(public|private|protected)\s+(static\s+)?(\$)?'.$property->getName().'/i', $line_content))
				{
					if (preg_match('/\* module: ('.$this->name.')/ism', $override_file[$line_number - 5]))
						$override_file[$line_number - 7] = $override_file[$line_number - 6] = $override_file[$line_number - 5] = $override_file[$line_number - 4] = $override_file[$line_number - 3] =  $override_file[$line_number - 2] = '#--remove--#';
					$line_content = '#--remove--#';
					break;
				}
		}

		// Remove constants from override file
		foreach ($module_class->getConstants() as $constant => $value)
		{
			if (!$override_class->hasConstant($constant))
				continue;

			// Remplacer la ligne de declaration par "remove"
			foreach ($override_file as $line_number => &$line_content)
				if (preg_match('/(const)\s+'.$constant.'/i', $line_content))
				{
					$line_content = '#--remove--#';
					break;
				}
		}

		// Rewrite nice code
		$code = '';
		foreach ($override_file as $line)
		{
			if ($line == '#--remove--#')
				continue;

			$code .= $line;
		}

		$to_delete = preg_match('/<\?(?:php)?\s+class\s+'.$classname.'\s+extends\s+'.$classname.'Core\s*?[{]\s*?[}]/ism', $code);

		if ($to_delete)
			unlink($override_path);
		else
			file_put_contents($override_path, $code);

		// Re-generate the class index
		Tools::generateIndex();

		return true;
	}

	public function assignCMSPages()
	{
		foreach ($this->cms_pages as $cms_page)
		{
			$link = Context::getContext()->link->getCMSLink(Configuration::get($cms_page['config']), null, true);

			if (strpos($link, '?'))
				$link = $link.'&content_only=1';
			else
				$link = $link.'?content_only=1';

			Context::getContext()->smarty->assign(array(
				$cms_page['name'] => Configuration::get($cms_page['config']),
				'link_'.$cms_page['name'] => $link
			));
		}
	}

	protected function dbColumnExists($table_name, $column_name)
	{
		$result = DB::getInstance()->executeS("
			SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
			FROM INFORMATION_SCHEMA.COLUMNS
			WHERE TABLE_NAME = '"._DB_PREFIX_.pSQL($table_name)."'
			AND TABLE_SCHEMA = '"._DB_NAME_."'
		");

		if (!$result)
			return null;

		foreach ($result as $row)
		{
			if ($row['COLUMN_NAME'] == $column_name)
				return true;
		}

		return false;

	}

	/*******************************************************************************************************************
	 *
	 * Hooks
	 *
	 *******************************************************************************************************************/

	public function hookDisplayHeader($params)
	{
		if (Configuration::get('LEGAL_CSS'))
			$this->context->controller->addCSS($this->_path.'views/css/front/legal.css');

		if (in_array($this->context->controller->php_self, array('index', 'product', 'category')))
			$this->context->controller->addJS($this->_path.'views/js/legal.js');

		$this->assignCMSPages();

		$this->context->smarty->assign(array(
			'legal' => 1,
		));
	}

	public function hookDisplayProductDeliveryTime($params)
	{
		if (!isset($params['product']))
			return;

		if ($params['product'] instanceof Product)
			$id_product = (int)$params['product']->id;
		else
			$id_product = (int)$params['product']['id_product'];

		$cache_key = $this->name.'|delivery_time|'.$id_product;

		if (!$this->isCached('displayProductDeliveryTime.tpl', $this->getCacheId($cache_key)))
		{
			$this->smarty->assign(array(
				'is_object' => (bool)($params['product'] instanceof Product),
				'product' => $params['product'],
				'priceDisplay' => Product::getTaxCalculationMethod((int)$this->context->cookie->id_customer),
				'priceDisplayPrecision' => _PS_PRICE_DISPLAY_PRECISION_,
			));
		}

		return $this->display(__FILE__, 'displayProductDeliveryTime.tpl', $this->getCacheId($cache_key));
	}

	public function hookDisplayTop($params)
	{
		if (Configuration::get('LEGAL_SHOW_FANCY') == true)
		{
			$this->context->controller->addJS(_PS_JS_DIR_.'jquery/plugins/fancybox/jquery.fancybox.js');
			$this->context->controller->addCSS(_PS_JS_DIR_.'jquery/plugins/fancybox/jquery.fancybox.css', 'all');

			$this->smarty->assign(array(
				'show_fancy' => Configuration::get('LEGAL_SHOW_FANCY')
			));
			return $this->display(__FILE__, 'displayTop.tpl');
		}
	}

	public function hookDisplayProductPriceBlock($params)
	{
		if (!isset($params['product']))
			return;

		$is_object = '0';

		if ($params['product'] instanceof Product)
		{
			$id_product = (int)$params['product']->id;
			if (isset($params['product']->cache_default_attribute))
				$current_id_product_attribute = (int)$params['product']->cache_default_attribute;
			else
				$current_id_product_attribute = 0;

			$is_object = '1';

		}
		else
		{
			$id_product = (int)$params['product']['id_product'];
			if (isset($params['product']['id_product_attribute']))
				$current_id_product_attribute = (int)$params['product']['id_product_attribute'];
			else
				$current_id_product_attribute = 0;
		}

		$cache_key = $this->name.'|'.$params['type'].'|'.$id_product.'|'.$current_id_product_attribute.'|'.$is_object;

		if (!$this->isCached('displayProductPriceBlock.tpl', $this->getCacheId($cache_key)))
		{
			$weight = 0;
			$combination_weight = 0;
			$id_product = 0;
			$allow_oosp = 0;

			if ($params['product'] instanceof Product)
			{
				$id_product_attribute = Product::getDefaultAttribute($id_product);
				$weight = (float)$params['product']->weight;
				$allow_oosp = $params['product']->isAvailableWhenOutOfStock((int)$params['product']->out_of_stock);
			}
			else
			{
				$id_product_attribute = Product::getDefaultAttribute($id_product);
				if (!isset($params['product']['weight']))
				{
					$product = new Product((int)$id_product);
					$params['product']['weight'] = $product->weight;
				}
				$weight = (float)$params['product']['weight'];
				$allow_oosp = $params['product']['allow_oosp'];
			}

			if ($id_product_attribute)
			{
				$combination = new Combination($id_product_attribute);
				$combination_weight = $combination->weight;
			}

			$this->smarty->assign(array(
				'is_object' => (bool)($params['product'] instanceof Product),
				'product' => $params['product'],
				'weight' => $weight,
				'allow_oosp' => $allow_oosp,
				'combination_weight' => $combination_weight,
				'priceDisplay' => Product::getTaxCalculationMethod((int)$this->context->cookie->id_customer),
				'priceDisplayPrecision' => _PS_PRICE_DISPLAY_PRECISION_,
				'php_self' => $this->context->controller->php_self,
				'tax_enabled' => Configuration::get('PS_TAX'),
				'cms_id_shipping' => Configuration::get('LEGAL_CMS_ID_SHIPPING'),
				'template_type' => $params['type'],
				'weight_unit' => Configuration::get('PS_WEIGHT_UNIT'),
				'show_weights' => Configuration::get('LEGAL_SHOW_WEIGHTS'),
				'show_fancy' => Configuration::get('LEGAL_SHOW_FANCY'),
				'seo_active' => Configuration::get('PS_REWRITING_SETTINGS'),
				'show_from' => (bool)Configuration::get('LEGAL_SHOW_FROM') && (bool)$current_id_product_attribute,
				'show_fulltaxinfo' => (bool)Configuration::get('LEGAL_SHOW_FULLTAXINFO'),
				'show_fullunitprice' => (bool)Configuration::get('LEGAL_SHOW_FULLUNITPRICE'),
			));
		}
		return $this->display(__FILE__, 'displayProductPriceBlock.tpl', $this->getCacheId($cache_key));
	}

	public function hookDisplayBeforeShoppingCartBlock($params)
	{
		if ($this->context->controller instanceof OrderOpcController || property_exists($this->context->controller, 'step') && $this->context->controller->step == 3)
		{
			$cart_text = Configuration::get('SHOPPING_CART_TEXT_BEFORE', $this->context->language->id);

			if ($cart_text && Configuration::get('PS_EU_PAYMENT_API'))
			{
				$this->context->smarty->assign('cart_text', $cart_text);

				return $this->display(__FILE__, 'displayShoppingCartBeforeBlock.tpl');
			}
		}
	}

	public function hookDisplayAfterShoppingCartBlock($params)
	{
		$cart_text = Configuration::get('SHOPPING_CART_TEXT_AFTER', Context::getContext()->language->id);

		if ($cart_text && Configuration::get('PS_EU_PAYMENT_API'))
		{
			$this->context->smarty->assign('cart_text', $cart_text);

			return $this->display(__FILE__, 'displayShoppingCartAfterBlock.tpl');
		}
	}

	public function hookDisplayShippingPrice($params)
	{
		$with_tax = Product::getTaxCalculationMethod((int)$this->context->cookie->id_customer) == 0;

		$shipping_price = $this->context->cart->getOrderTotal($with_tax, Cart::ONLY_SHIPPING);
		$no_address_selected = !$this->context->cart->id_address_delivery;
		$default_country = false;
		$shipping_link = false;

		if ($no_address_selected)
		{
			$country = new Country(Configuration::get('PS_COUNTRY_DEFAULT'), $this->context->language->id);

			$default_country = $country->name;

			$cms = new CMS(Configuration::get('LEGAL_CMS_ID_SHIPPING'));

			if (Validate::isLoadedObject($cms))
			{
				$shipping_link = $this->context->link->getCMSLink($cms, null, Configuration::get('PS_SSL_ENABLED'));

				if (!strpos($shipping_link, '?'))
					$shipping_link .= '?content_only=1';
				else
					$shipping_link .= '&content_only=1';
			}
		}

		$this->context->smarty->assign(array(
			'shipping_price' => $shipping_price,
			'no_address_selected' => $no_address_selected,
			'default_country' => $default_country,
			'shipping_link' => $shipping_link,
			'show_fancy' => Configuration::get('LEGAL_SHOW_FANCY'),
			'seo_active' => Configuration::get('PS_REWRITING_SETTINGS'),
			'legal_show_deliveryinfo' => Configuration::get('LEGAL_SHOW_DELIVERYINFO'),
		));

		return $this->display(__FILE__, 'displayShippingPrice.tpl');
	}


	/* Empty cache hooks */
	public function hookActionObjectProductAddAfter($params)
	{
		$this->_clearCache('*');
	}

	public function hookActionObjectProductUpdateAfter($params)
	{
		$this->_clearCache('*');
	}

	public function hookActionObjectProductDeleteAfter($params)
	{
		$this->_clearCache('*');
	}

	public function hookActionObjectCategoryUpdateAfter($params)
	{
		$this->_clearCache('*');
	}

	public function _clearCache($template, $cache_id = null, $compile_id = null)
	{
		parent::_clearCache('displayProductPriceBlock.tpl');
		parent::_clearCache('displayProductDeliveryTime.tpl');
	}

	/*******************************************************************************************************************
	 *
	 * Theme helper methods
	 *
	 *******************************************************************************************************************/

	public function getCurrentThemeDir($theme_name = false)
	{
		if (!$theme_name)
			$theme_name = Context::getContext()->theme->directory;

		$default_theme = 'default-bootstrap';

		// first look in theme directory
		$path = _PS_ALL_THEMES_DIR_.$theme_name.'/modules/'.$this->name.'/views/templates/themes/';

		if (is_dir($path))
			return $path;

		// then look in module directory
		$path = _PS_MODULE_DIR_.$this->name.'/views/templates/themes/'.$theme_name.'/';

		if (is_dir($path))
			return $path;
		else if ($theme_name != $default_theme)
			return $this->getCurrentThemeDir($default_theme);

		// maybe should return the path to native theme, tests needed
		return false;
	}

	public function getThemeOverride($template_file)
	{
		if (!Validate::isTplName($template_file))
			throw new Exception(Tools::displayError('Invalid template name'));

		$path = $this->getCurrentThemeDir();

		if ($path && file_exists($path.$template_file.'.tpl'))
			return $path.$template_file.'.tpl';

		return false;
	}
}
