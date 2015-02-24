<?php

class CmsController extends CmsControllerCore
{
	
	public function init()
	{
		if ($id_cms = (int)Tools::getValue('id_cms'))
			$this->cms = new CMS($id_cms, $this->context->language->id);
		
		if (Configuration::get('PS_SSL_ENABLED') && Tools::getValue('content_only') && Validate::isLoadedObject($this->cms))
			$this->ssl = true;

		parent::init();

	}

}
