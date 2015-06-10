<?php
/**
 * EU Legal - Better security for German and EU merchants.
 *
 * @version   : 1.0.2
 * @date      : 2014 08 26
 * @author    : Markus Engel/Chris Gurk @ Onlineshop-Module.de | George June/Alexey Dermenzhy @ Silbersaiten.de
 * @copyright : 2014 Onlineshop-Module.de | 2014 Silbersaiten.de
 * @contact   : info@onlineshop-module.de | info@silbersaiten.de
 * @homepage  : www.onlineshop-module.de | www.silbersaiten.de
 * @license   : http://opensource.org/licenses/osl-3.0.php
 * @changelog : see changelog.txt
 * @compatibility : PS == 1.6.0.9
 */

class Cart extends CartCore
{
	protected static $_isPartlyVirtualCart = array();

	public function getProducts($refresh = false, $id_product = false, $id_country = null)
	{
		/* 
		* EU-Legal
		* 1) correct calculation of prices -> Problem with inaccuracy at high number of items 
		* 2) assign standard delivery times to products
		*/
		if (!$this->id)
			return array();
		// Product cache must be strictly compared to NULL, or else an empty cart will add dozens of queries
		if ($this->_products !== null && !$refresh)
		{
			// Return product row with specified ID if it exists
			if (is_int($id_product))
			{
				foreach ($this->_products as $product)
					if ($product['id_product'] == $id_product)
						return array($product);
				return array();
			}
			return $this->_products;
		}

		// Build query
		$sql = new DbQuery();

		// Build SELECT
		$sql->select('cp.`id_product_attribute`, cp.`id_product`, cp.`quantity` AS cart_quantity, cp.id_shop, pl.`name`, p.`is_virtual`,
						pl.`description_short`, pl.`available_now`, pl.`available_later`, pl.`delivery_now`, pl.`delivery_later`, product_shop.`id_category_default`, p.`id_supplier`,
						p.`id_manufacturer`, product_shop.`on_sale`, product_shop.`ecotax`, product_shop.`additional_shipping_cost`,
						product_shop.`available_for_order`, product_shop.`price`, product_shop.`active`, product_shop.`unity`, product_shop.`unit_price_ratio`, 
						stock.`quantity` AS quantity_available, p.`width`, p.`height`, p.`depth`, stock.`out_of_stock`, p.`weight`,
						p.`date_add`, p.`date_upd`, IFNULL(stock.quantity, 0) as quantity, pl.`link_rewrite`, cl.`link_rewrite` AS category,
						CONCAT(LPAD(cp.`id_product`, 10, 0), LPAD(IFNULL(cp.`id_product_attribute`, 0), 10, 0), IFNULL(cp.`id_address_delivery`, 0)) AS unique_id, cp.id_address_delivery,
						product_shop.advanced_stock_management, ps.product_supplier_reference supplier_reference, IFNULL(sp.`reduction_type`, 0) AS reduction_type');

		// Build FROM
		$sql->from('cart_product', 'cp');

		// Build JOIN
		$sql->leftJoin('product', 'p', 'p.`id_product` = cp.`id_product`');
		$sql->innerJoin('product_shop', 'product_shop', '(product_shop.`id_shop` = cp.`id_shop` AND product_shop.`id_product` = p.`id_product`)');
		$sql->leftJoin('product_lang', 'pl', '
			p.`id_product` = pl.`id_product`
			AND pl.`id_lang` = '.(int)$this->id_lang.Shop::addSqlRestrictionOnLang('pl', 'cp.id_shop')
		);

		$sql->leftJoin('category_lang', 'cl', '
			product_shop.`id_category_default` = cl.`id_category`
			AND cl.`id_lang` = '.(int)$this->id_lang.Shop::addSqlRestrictionOnLang('cl', 'cp.id_shop')
		);

		$sql->leftJoin('product_supplier', 'ps', 'ps.`id_product` = cp.`id_product` AND ps.`id_product_attribute` = cp.`id_product_attribute` AND ps.`id_supplier` = p.`id_supplier`');

		$sql->leftJoin('specific_price', 'sp', 'sp.`id_product` = cp.`id_product`');

		// @todo test if everything is ok, then refactorise call of this method
		$sql->join(Product::sqlStock('cp', 'cp'));

		// Build WHERE clauses
		$sql->where('cp.`id_cart` = '.(int)$this->id);
		if ($id_product)
			$sql->where('cp.`id_product` = '.(int)$id_product);
		$sql->where('p.`id_product` IS NOT NULL');

		// Build GROUP BY
		$sql->groupBy('unique_id');

		// Build ORDER BY
		$sql->orderBy('cp.`date_add`, p.`id_product`, cp.`id_product_attribute` ASC');

		if (Customization::isFeatureActive())
		{
			$sql->select('cu.`id_customization`, cu.`quantity` AS customization_quantity');
			$sql->leftJoin('customization', 'cu',
				'p.`id_product` = cu.`id_product` AND cp.`id_product_attribute` = cu.`id_product_attribute` AND cu.`id_cart` = '.(int)$this->id);
		}
		else
			$sql->select('NULL AS customization_quantity, NULL AS id_customization');

		if (Combination::isFeatureActive())
		{
			$sql->select('
				product_attribute_shop.`price` AS price_attribute, product_attribute_shop.`ecotax` AS ecotax_attr,
				IF (IFNULL(pa.`reference`, \'\') = \'\', p.`reference`, pa.`reference`) AS reference,
				(p.`weight`+ pa.`weight`) weight_attribute,
				IF (IFNULL(pa.`ean13`, \'\') = \'\', p.`ean13`, pa.`ean13`) AS ean13,
				IF (IFNULL(pa.`upc`, \'\') = \'\', p.`upc`, pa.`upc`) AS upc,
				pai.`id_image` as pai_id_image, il.`legend` as pai_legend,
				IFNULL(product_attribute_shop.`minimal_quantity`, product_shop.`minimal_quantity`) as minimal_quantity,
				IF(product_attribute_shop.wholesale_price > 0,  product_attribute_shop.wholesale_price, product_shop.`wholesale_price`) wholesale_price
			');

			$sql->leftJoin('product_attribute', 'pa', 'pa.`id_product_attribute` = cp.`id_product_attribute`');
			$sql->leftJoin('product_attribute_shop', 'product_attribute_shop', '(product_attribute_shop.`id_shop` = cp.`id_shop` AND product_attribute_shop.`id_product_attribute` = pa.`id_product_attribute`)');
			$sql->leftJoin('product_attribute_image', 'pai', 'pai.`id_product_attribute` = pa.`id_product_attribute`');
			$sql->leftJoin('image_lang', 'il', 'il.`id_image` = pai.`id_image` AND il.`id_lang` = '.(int)$this->id_lang);
		}
		else
			$sql->select(
				'p.`reference` AS reference, p.`ean13`,
				p.`upc` AS upc, product_shop.`minimal_quantity` AS minimal_quantity, product_shop.`wholesale_price` wholesale_price'
			);
		$result = Db::getInstance()->executeS($sql);

		// Reset the cache before the following return, or else an empty cart will add dozens of queries
		$products_ids = array();
		$pa_ids = array();
		if ($result)
			foreach ($result as $row)
			{
				$products_ids[] = $row['id_product'];
				$pa_ids[] = $row['id_product_attribute'];
			}
		// Thus you can avoid one query per product, because there will be only one query for all the products of the cart
		Product::cacheProductsFeatures($products_ids);
		Cart::cacheSomeAttributesLists($pa_ids, $this->id_lang);

		$this->_products = array();
		if (empty($result))
			return array();

		$cart_shop_context = Context::getContext()->cloneContext();
		foreach ($result as &$row)
		{
			if (isset($row['ecotax_attr']) && $row['ecotax_attr'] > 0)
				$row['ecotax'] = (float)$row['ecotax_attr'];

			$row['stock_quantity'] = (int)$row['quantity'];
			// for compatibility with 1.2 themes
			$row['quantity'] = (int)$row['cart_quantity'];

			if (isset($row['id_product_attribute']) && (int)$row['id_product_attribute'] && isset($row['weight_attribute']))
				$row['weight'] = (float)$row['weight_attribute'];

			if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice')
				$address_id = (int)$this->id_address_invoice;
			else
				$address_id = (int)$row['id_address_delivery'];
			if (!Address::addressExists($address_id))
				$address_id = null;

			if ($cart_shop_context->shop->id != $row['id_shop'])
				$cart_shop_context->shop = new Shop((int)$row['id_shop']);

			$address = Address::initialize($address_id, true);
			$id_tax_rules_group = Product::getIdTaxRulesGroupByIdProduct((int)$row['id_product'], $cart_shop_context);
			$tax_calculator = TaxManagerFactory::getManager($address, $id_tax_rules_group)->getTaxCalculator();

			$row['price'] = Product::getPriceStatic(
				(int)$row['id_product'],
				false,
				isset($row['id_product_attribute']) ? (int)$row['id_product_attribute'] : null,
				6,
				null,
				false,
				true,
				$row['cart_quantity'],
				false,
				(int)$this->id_customer ? (int)$this->id_customer : null,
				(int)$this->id,
				$address_id,
				$specific_price_output,
				false,
				true,
				$cart_shop_context
			);

			switch (Configuration::get('PS_ROUND_TYPE'))
			{
				case Order::ROUND_TOTAL:
					$row['total'] = $row['price'] * (int)$row['cart_quantity'];
					$row['total_wt'] = $tax_calculator->addTaxes($row['price']) * (int)$row['cart_quantity'];
					break;
				case Order::ROUND_LINE:
					$row['total'] = Tools::ps_round($row['price'] * (int)$row['cart_quantity'], _PS_PRICE_COMPUTE_PRECISION_);
					$row['total_wt'] = Tools::ps_round($tax_calculator->addTaxes($row['price']) * (int)$row['cart_quantity'], _PS_PRICE_COMPUTE_PRECISION_);
					break;

				case Order::ROUND_ITEM:
				default:
					$row['total'] = Tools::ps_round($row['price'], _PS_PRICE_COMPUTE_PRECISION_) * (int)$row['cart_quantity'];
					$row['total_wt'] = Tools::ps_round($tax_calculator->addTaxes($row['price']), _PS_PRICE_COMPUTE_PRECISION_) * (int)$row['cart_quantity'];
					break;
			}

			$row['price_wt'] = $tax_calculator->addTaxes($row['price']);
			$row['description_short'] = Tools::nl2br($row['description_short']);

			if (!isset($row['pai_id_image']) || $row['pai_id_image'] == 0)
			{
				$cache_id = 'Cart::getProducts_'.'-pai_id_image-'.(int)$row['id_product'].'-'.(int)$this->id_lang.'-'.(int)$row['id_shop'];
				if (!Cache::isStored($cache_id))
				{
					$row2 = Db::getInstance()->getRow('
						SELECT image_shop.`id_image` id_image, il.`legend`
						FROM `'._DB_PREFIX_.'image` i
						JOIN `'._DB_PREFIX_.'image_shop` image_shop ON (i.id_image = image_shop.id_image AND image_shop.cover=1 AND image_shop.id_shop='.(int)$row['id_shop'].')
						LEFT JOIN `'._DB_PREFIX_.'image_lang` il ON (image_shop.`id_image` = il.`id_image` AND il.`id_lang` = '.(int)$this->id_lang.')
						WHERE i.`id_product` = '.(int)$row['id_product'].' AND image_shop.`cover` = 1'
					);
					Cache::store($cache_id, $row2);
				}
				$row2 = Cache::retrieve($cache_id);
				if (!$row2)
					$row2 = array('id_image' => false, 'legend' => false);
				else
					$row = array_merge($row, $row2);
			}
			else
			{
				$row['id_image'] = $row['pai_id_image'];
				$row['legend'] = $row['pai_legend'];
			}

			$row['reduction_applies'] = ($specific_price_output && (float)$specific_price_output['reduction']);
			$row['quantity_discount_applies'] = ($specific_price_output && $row['cart_quantity'] >= (int)$specific_price_output['from_quantity']);
			$row['id_image'] = Product::defineProductImage($row, $this->id_lang);
			$row['allow_oosp'] = Product::isAvailableWhenOutOfStock($row['out_of_stock']);
			$row['features'] = Product::getFeaturesStatic((int)$row['id_product']);

			if (array_key_exists($row['id_product_attribute'].'-'.$this->id_lang, self::$_attributesLists))
				$row = array_merge($row, self::$_attributesLists[$row['id_product_attribute'].'-'.$this->id_lang]);

			/* 
			* EU-Legal
			* assign standard delivery times to products
			*/
			$row['delivery_now'] = !empty($row['delivery_now']) ? $row['delivery_now'] : Configuration::get('LEGAL_DELIVERY_NOW', $this->id_lang);
			$row['delivery_later'] = !empty($row['delivery_later']) ? $row['delivery_later'] : Configuration::get('LEGAL_DELIVERY_LATER', $this->id_lang);

			$row = Product::getTaxesInformations($row, $cart_shop_context);

			$this->_products[] = $row;
		}

		return $this->_products;

	}

	public function getOrderTotal($with_taxes = true, $type = Cart::BOTH, $products = null, $id_carrier = null, $use_cache = true)
	{
		/* 
		* EU-Legal
		* correct calculation of prices -> Problem with inaccuracy at high number of items
		*/

		static $address = null;

		if (!$this->id)
			return 0;

		$type = (int)$type;
		$array_type = array(
			Cart::ONLY_PRODUCTS,
			Cart::ONLY_DISCOUNTS,
			Cart::BOTH,
			Cart::BOTH_WITHOUT_SHIPPING,
			Cart::ONLY_SHIPPING,
			Cart::ONLY_WRAPPING,
			Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING,
			Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING,
		);

		$taxes = $this->getTaxDetails();
		$order_total_products_taxed = 0;

		// Define virtual context to prevent case where the cart is not the in the global context
		$virtual_context = Context::getContext()->cloneContext();
		$virtual_context->cart = $this;

		if (!in_array($type, $array_type))
			die(Tools::displayError());

		$with_shipping = in_array($type, array(Cart::BOTH, Cart::ONLY_SHIPPING));

		// if cart rules are not used
		if ($type == Cart::ONLY_DISCOUNTS && !CartRule::isFeatureActive())
			return 0;

		// no shipping cost if is a cart with only virtuals products
		$virtual = $this->isVirtualCart();
		if ($virtual && $type == Cart::ONLY_SHIPPING)
			return 0;

		if ($virtual && $type == Cart::BOTH)
			$type = Cart::BOTH_WITHOUT_SHIPPING;

		if (!Configuration::get('LEGAL_SHIPTAXMETH'))
		{
			if ($with_shipping)
			{
				if (is_null($products) && is_null($id_carrier))
					$shipping_fees = $this->getTotalShippingCost(null, (boolean)$with_taxes);
				else
					$shipping_fees = $this->getPackageShippingCost($id_carrier, (bool)$with_taxes, null, $products);
			}
			else
				$shipping_fees = 0;
		}
		else
		{
			if (!in_array($type, array(Cart::BOTH_WITHOUT_SHIPPING, Cart::ONLY_PRODUCTS, Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING, Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING)))
			{
				if (is_null($products) && is_null($id_carrier))
					$shipping_fees_taxed = $this->getTotalShippingCost(null, false);
				else
					$shipping_fees_taxed = $this->getPackageShippingCost($id_carrier, false, null, $products);
			}
			else
				$shipping_fees_taxed = 0;

			$shipping_fees = Order::calculateCompundTaxPrice($shipping_fees_taxed, $taxes);

			if ($with_taxes)
				$shipping_fees = $shipping_fees_taxed;
		}

		if ($type == Cart::ONLY_SHIPPING)
			return $shipping_fees;

		if ($type == Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING)
			$type = Cart::ONLY_PRODUCTS;

		$param_product = true;
		if (is_null($products))
		{
			$param_product = false;
			$products = $this->getProducts();
		}

		if ($type == Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING)
		{
			foreach ($products as $key => $product)
				if ($product['is_virtual'])
					unset($products[$key]);
			$type = Cart::ONLY_PRODUCTS;
		}

		$order_total = 0;
		if (Tax::excludeTaxeOption())
			$with_taxes = false;

		$specific_price_output = false;
		$null = false;
		$products_total = array();
		$ecotax_total = 0;

		foreach ($products as $product)
		{
			if ($virtual_context->shop->id != $product['id_shop'])
				$virtual_context->shop = new Shop((int)$product['id_shop']);

			if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice')
				$id_address = (int)$this->id_address_invoice;
			else
				$id_address = (int)$product['id_address_delivery'];
			if (!Address::addressExists($id_address))
				$id_address = null;

			$price = Product::getPriceStatic(
				(int)$product['id_product'],
				false,
				(int)$product['id_product_attribute'],
				6,
				null,
				false,
				true,
				$product['cart_quantity'],
				false,
				(int)$this->id_customer ? (int)$this->id_customer : null,
				(int)$this->id,
				$id_address,
				$null,
				false,
				true,
				$virtual_context
			);

			if (Configuration::get('PS_USE_ECOTAX'))
			{
				$ecotax = $product['ecotax'];
				if (isset($product['attribute_ecotax']) && $product['attribute_ecotax'] > 0)
					$ecotax = $product['attribute_ecotax'];
			}
			else
				$ecotax = 0;

			$address = Address::initialize($id_address, true);

			if ($with_taxes)
			{
				$id_tax_rules_group = Product::getIdTaxRulesGroupByIdProduct((int)$product['id_product'], $virtual_context);
				$tax_calculator = TaxManagerFactory::getManager($address, $id_tax_rules_group)->getTaxCalculator();

				if ($ecotax)
					$ecotax_tax_calculator = TaxManagerFactory::getManager($address, (int)Configuration::get('PS_ECOTAX_TAX_RULES_GROUP_ID'))->getTaxCalculator();
			}
			else
				$id_tax_rules_group = 0;

			if (in_array(Configuration::get('PS_ROUND_TYPE'), array(Order::ROUND_ITEM, Order::ROUND_LINE)))
			{
				if (!isset($products_total[$id_tax_rules_group]))
					$products_total[$id_tax_rules_group] = 0;
			}
			else
				if (!isset($products_total[$id_tax_rules_group.'_'.$id_address]))
					$products_total[$id_tax_rules_group.'_'.$id_address] = 0;

			switch (Configuration::get('PS_ROUND_TYPE'))
			{
				case Order::ROUND_TOTAL:
					$products_total[$id_tax_rules_group.'_'.$id_address] += $price * (int)$product['cart_quantity'];

					if ($ecotax)
						$ecotax_total += $ecotax * (int)$product['cart_quantity'];
					break;
				case Order::ROUND_LINE:
					$product_price = $price * $product['cart_quantity'];

					if ($with_taxes)
						$products_total[$id_tax_rules_group] += Tools::ps_round($product_price + $tax_calculator->getTaxesTotalAmount($product_price), _PS_PRICE_COMPUTE_PRECISION_);
					else
						$products_total[$id_tax_rules_group] += Tools::ps_round($product_price, _PS_PRICE_COMPUTE_PRECISION_);

					if ($ecotax)
					{
						$ecotax_price = $ecotax * (int)$product['cart_quantity'];

						if ($with_taxes)
							$ecotax_total += Tools::ps_round($ecotax_price + $ecotax_tax_calculator->getTaxesTotalAmount($ecotax_price), _PS_PRICE_COMPUTE_PRECISION_);
						else
							$ecotax_total += Tools::ps_round($ecotax_price, _PS_PRICE_COMPUTE_PRECISION_);
					}
					break;
				case Order::ROUND_ITEM:
				default:
					$product_price = $with_taxes ? $tax_calculator->addTaxes($price) : $price;
					$products_total[$id_tax_rules_group] += Tools::ps_round($product_price, _PS_PRICE_COMPUTE_PRECISION_) * (int)$product['cart_quantity'];

					if ($ecotax)
					{
						$ecotax_price = $with_taxes ? $ecotax_tax_calculator->addTaxes($ecotax) : $ecotax;
						$ecotax_total += Tools::ps_round($ecotax_price, _PS_PRICE_COMPUTE_PRECISION_) * (int)$product['cart_quantity'];
					}
					break;
			}
		}

		foreach ($products_total as $key => $price)
		{
			if ($with_taxes && Configuration::get('PS_ROUND_TYPE') == Order::ROUND_TOTAL)
			{
				$tmp = explode('_', $key);
				$address = Address::initialize((int)$tmp[1], true);
				$tax_calculator = TaxManagerFactory::getManager($address, $tmp[0])->getTaxCalculator();
				$order_total += Tools::ps_round($price + $tax_calculator->getTaxesTotalAmount($price), _PS_PRICE_COMPUTE_PRECISION_);
			}
			else
				$order_total += $price;
		}

		if ($ecotax_total && $with_taxes && Configuration::get('PS_ROUND_TYPE') == Order::ROUND_TOTAL)
			$ecotax_total = Tools::ps_round($ecotax_total, _PS_PRICE_COMPUTE_PRECISION_) + Tools::ps_round($ecotax_tax_calculator->getTaxesTotalAmount($ecotax_total), _PS_PRICE_COMPUTE_PRECISION_);

		$order_total += $ecotax_total;
		$order_total_products = $order_total;

		if ($type == Cart::ONLY_DISCOUNTS)
			$order_total = 0;

		// Wrapping Fees
		$wrapping_fees = 0;
		if ($this->gift)
			$wrapping_fees = Tools::convertPrice(Tools::ps_round($this->getGiftWrappingPrice($with_taxes), _PS_PRICE_COMPUTE_PRECISION_), Currency::getCurrencyInstance((int)$this->id_currency));
		if ($type == Cart::ONLY_WRAPPING)
			return $wrapping_fees;

		$order_total_discount = 0;
		$order_shipping_discount = 0;
		if (!in_array($type, array(Cart::ONLY_SHIPPING, Cart::ONLY_PRODUCTS)) && CartRule::isFeatureActive())
		{
			// First, retrieve the cart rules associated to this "getOrderTotal"
			if ($with_shipping || $type == Cart::ONLY_DISCOUNTS)
				$cart_rules = $this->getCartRules(CartRule::FILTER_ACTION_ALL);
			else
			{
				$cart_rules = $this->getCartRules(CartRule::FILTER_ACTION_REDUCTION);
				// Cart Rules array are merged manually in order to avoid doubles
				foreach ($this->getCartRules(CartRule::FILTER_ACTION_GIFT) as $tmp_cart_rule)
				{
					$flag = false;
					foreach ($cart_rules as $cart_rule)
						if ($tmp_cart_rule['id_cart_rule'] == $cart_rule['id_cart_rule'])
							$flag = true;
					if (!$flag)
						$cart_rules[] = $tmp_cart_rule;
				}
			}

			$id_address_delivery = 0;
			if (isset($products[0]))
				$id_address_delivery = (is_null($products) ? $this->id_address_delivery : $products[0]['id_address_delivery']);
			$package = array('id_carrier' => $id_carrier, 'id_address' => $id_address_delivery, 'products' => $products);

			// Then, calculate the contextual value for each one
			$flag = false;
			foreach ($cart_rules as $cart_rule)
			{
				// If the cart rule offers free shipping, add the shipping cost
				if (($with_shipping || $type == Cart::ONLY_DISCOUNTS) && $cart_rule['obj']->free_shipping && !$flag)
				{
					$order_shipping_discount = (float)Tools::ps_round($cart_rule['obj']->getContextualValue($with_taxes, $virtual_context, CartRule::FILTER_ACTION_SHIPPING, ($param_product ? $package : null), $use_cache), _PS_PRICE_COMPUTE_PRECISION_);
					$flag = true;
				}

				// If the cart rule is a free gift, then add the free gift value only if the gift is in this package
				if ((int)$cart_rule['obj']->gift_product)
				{
					$in_order = false;
					if (is_null($products))
						$in_order = true;
					else
						foreach ($products as $product)
							if ($cart_rule['obj']->gift_product == $product['id_product'] && $cart_rule['obj']->gift_product_attribute == $product['id_product_attribute'])
								$in_order = true;

					if ($in_order)
						$order_total_discount += $cart_rule['obj']->getContextualValue($with_taxes, $virtual_context, CartRule::FILTER_ACTION_GIFT, $package, $use_cache);
				}

				// If the cart rule offers a reduction, the amount is prorated (with the products in the package)
				if ($cart_rule['obj']->reduction_percent != 0 || $cart_rule['obj']->reduction_amount != 0)
					$order_total_discount += Tools::ps_round($cart_rule['obj']->getContextualValue($with_taxes, $virtual_context, CartRule::FILTER_ACTION_REDUCTION, $package, $use_cache), _PS_PRICE_COMPUTE_PRECISION_);
			}
			$order_total_discount = min(Tools::ps_round($order_total_discount, 2), (float)$order_total_products) + (float)$order_shipping_discount;
			$order_total -= $order_total_discount;
		}

		if ($type == Cart::BOTH)
			$order_total += $shipping_fees + $wrapping_fees;

		if ($order_total < 0 && $type != Cart::ONLY_DISCOUNTS)
			return 0;

		if ($type == Cart::ONLY_DISCOUNTS)
			return $order_total_discount;

		return Tools::ps_round((float)$order_total, _PS_PRICE_COMPUTE_PRECISION_);
	}

	public function getGiftWrappingPrice($with_taxes = true, $id_address = null)
	{
		static $address = array();
		/*
		* EU-Legal
		* alternative method for tax calculation (LEGAL_SHIPTAXMETH)
		*/
		if (!Configuration::get('LEGAL_SHIPTAXMETH'))
			parent::getGiftWrappingPrice($with_taxes, $id_address);

		$wrapping_fees = (float)Configuration::get('PS_GIFT_WRAPPING_PRICE');
		if ($with_taxes && $wrapping_fees > 0)
		{
			$tax_rate = Cart::getTaxesAverageUsed((int)($this->id));
			$wrapping_fees = $wrapping_fees * (1 + ($tax_rate / 100));
		}

		return $wrapping_fees;
	}

	/*
	 * Check if cart contains virtual products. Can't use "isVirtual" method, because it tests if ALL
	 * the products in cart are virtual. This method will return boolean true if at least one virtual
	 * product is contained.
	 * 
	 * @access public
	 *
	 * @return boolean - whether or not the cart contains virtual products
	 */
	public function containsVirtualProducts()
	{
		/*
		* EU-Legal
		* is at least one virtual product in cart?
		*/
		if (!ProductDownload::isFeatureActive())
			return false;

		if (!isset(self::$_isPartlyVirtualCart[$this->id]))
		{

			$products = $this->getProducts();

			if (!count($products))
				return false;

			$is_partly_virtual = 0;

			foreach ($products as $product)
			{
				if ($product['is_virtual'])
					$is_partly_virtual = 1;
			}

			self::$_isPartlyVirtualCart[$this->id] = (int)$is_partly_virtual;

		}

		return self::$_isPartlyVirtualCart[$this->id];
	}

	/*
	 * Returns a list of all the taxes represented in the cart and the percentage of each tax, e.g,
	 * an order with two products taxed with 9% and 17% accordingly would have 50% of 9% tax and
	 * 50% of 19% tax.

	 * @access public
	 *
	 * @param mixed $products - an array of procuts (optional, will be fetched from the current cart instance if not provided)
	 *
	 * @return mixed - an array of taxes or boolean false
	 */
	public function getTaxDetails($products = false)
	{
		if (!is_array($products) || !count($products))
			$products = $this->getProducts();

		$context = Context::getContext();

		if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice')
			$id_address = (int)$this->id_address_invoice;
		else
			$id_address = (int)$this->id_address_delivery;

        if (Address::addressExists($id_address))
            $address = Address::initialize($id_address);
        else
            return false;

		if (!count($products))
			return false;

		$prepared_taxes = array();
		$total_products_price = 0;

		foreach ($products as $product)
		{
			$id_tax_rules = (int)Product::getIdTaxRulesGroupByIdProduct((int)$product['id_product'], $context);
			$tax_manager = TaxManagerFactory::getManager($address, $id_tax_rules);
			$tax_calculator = $tax_manager->getTaxCalculator();

			$product_taxes = $tax_calculator->getTaxData($product['price']);
			$total_products_price += (float)$product['total_wt'];

			foreach ($product_taxes as $tax_id => $tax_data)
			{
				if (!array_key_exists($tax_id, $prepared_taxes))
				{
					$prepared_taxes[$tax_id] = $tax_data + array(
							'total' => (float)$product['total_wt'] - (float)$product['total'],
							'total_net' => (float)$product['total'],
							'total_vat' => (float)$product['total_wt'],
							'percentage' => 0
						);
				}
				else
				{
					$prepared_taxes[$tax_id]['total'] += ((float)$product['total_wt'] - (float)$product['total']);
					$prepared_taxes[$tax_id]['total_net'] += (float)$product['total'];
					$prepared_taxes[$tax_id]['total_vat'] += (float)$product['total_wt'];
				}
			}
		}

		foreach ($prepared_taxes as &$tax)
		{
			if ($total_products_price > 0 && $tax['total_vat'] > 0)
				$tax['percentage'] = 100 / ($total_products_price / $tax['total_vat']);
		}

		return count($prepared_taxes) ? $prepared_taxes : false;
	}

	/*
	 * Get package shipping cost. If LEGAL_SHIPTAXMETH is disabled, the default way is used, otherwise
	 * it is more or less the same to the default, except that it subtracts the carrier tax as opposed
	 * to the default way, where the carrier tax is added.
	 * The basic logic is this:
	 * LEGAL_SHIPTAXMETH disabled, $use_tax = true
	 * get shipping cost, get tax, add the tax to the cost
	 *
	 * LEGAL_SHIPTAXMETH enabled, $use_tax = false
	 * get shipping cost, get tax, subtract the tax from the cost
	 *
	 * @access public
	 *
	 * @return float - shipping price
	 */
	public function getPackageShippingCost($id_carrier = null, $use_tax = true, Country $default_country = null, $product_list = null, $id_zone = null)
	{
		if (!Configuration::get('LEGAL_SHIPTAXMETH'))
			return parent::getPackageShippingCost($id_carrier, $use_tax, $default_country, $product_list, $id_zone);

		if ($this->isVirtualCart())
			return 0;

		if (!$default_country)
			$default_country = Context::getContext()->country;

		if (!is_null($product_list))
			foreach ($product_list as $key => $value)
				if ($value['is_virtual'] == 1)
					unset($product_list[$key]);

		$complete_product_list = $this->getProducts();
		if (is_null($product_list))
			$products = $complete_product_list;
		else
			$products = $product_list;

		if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice')
			$address_id = (int)$this->id_address_invoice;
		elseif (count($product_list))
		{
			$prod = current($product_list);
			$address_id = (int)$prod['id_address_delivery'];
		}
		else
			$address_id = null;
		if (!Address::addressExists($address_id))
			$address_id = null;

		$cache_id = 'getPackageShippingCost_'.(int)$this->id.'_'.(int)$address_id.'_'.(int)$id_carrier.'_'.(int)$use_tax.'_'.(int)$default_country->id;
		if ($products)
			foreach ($products as $product)
				$cache_id .= '_'.(int)$product['id_product'].'_'.(int)$product['id_product_attribute'];

		if (Cache::isStored($cache_id))
			return Cache::retrieve($cache_id);

		// Order total in default currency without fees
		$order_total = $this->getOrderTotal(true, Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING, $product_list);

		// Start with shipping cost at 0
		$shipping_cost = 0;
		// If no product added, return 0
		if (!count($products))
		{
			Cache::store($cache_id, $shipping_cost);
			return $shipping_cost;
		}

		if (!isset($id_zone))
		{
			// Get id zone
			if (!$this->isMultiAddressDelivery()
				&& isset($this->id_address_delivery)
				&& $this->id_address_delivery
				&& Customer::customerHasAddress($this->id_customer, $this->id_address_delivery
			))
				$id_zone = Address::getZoneById((int)$this->id_address_delivery);
			else
			{
				if (!Validate::isLoadedObject($default_country))
					$default_country = new Country(Configuration::get('PS_COUNTRY_DEFAULT'), Configuration::get('PS_LANG_DEFAULT'));

				$id_zone = (int)$default_country->id_zone;
			}
		}

		if ($id_carrier && !$this->isCarrierInRange((int)$id_carrier, (int)$id_zone))
			$id_carrier = '';

		if (empty($id_carrier) && $this->isCarrierInRange((int)Configuration::get('PS_CARRIER_DEFAULT'), (int)$id_zone))
			$id_carrier = (int)Configuration::get('PS_CARRIER_DEFAULT');

		$total_package_without_shipping_tax_inc = $this->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING, $product_list);
		if (empty($id_carrier))
		{
			if ((int)$this->id_customer)
			{
				$customer = new Customer((int)$this->id_customer);
				$result = Carrier::getCarriers((int)Configuration::get('PS_LANG_DEFAULT'), true, false, (int)$id_zone, $customer->getGroups());
				unset($customer);
			}
			else
				$result = Carrier::getCarriers((int)Configuration::get('PS_LANG_DEFAULT'), true, false, (int)$id_zone);

			foreach ($result as $k => $row)
			{
				if ($row['id_carrier'] == Configuration::get('PS_CARRIER_DEFAULT'))
					continue;

				if (!isset(self::$_carriers[$row['id_carrier']]))
					self::$_carriers[$row['id_carrier']] = new Carrier((int)$row['id_carrier']);

				$carrier = self::$_carriers[$row['id_carrier']];

				// Get only carriers that are compliant with shipping method
				if (($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT && $carrier->getMaxDeliveryPriceByWeight((int)$id_zone) === false)
				|| ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_PRICE && $carrier->getMaxDeliveryPriceByPrice((int)$id_zone) === false))
				{
					unset($result[$k]);
					continue;
				}

				// If out-of-range behavior carrier is set on "Desactivate carrier"
				if ($row['range_behavior'])
				{
					$check_delivery_price_by_weight = Carrier::checkDeliveryPriceByWeight($row['id_carrier'], $this->getTotalWeight(), (int)$id_zone);

					$total_order = $total_package_without_shipping_tax_inc;
					$check_delivery_price_by_price = Carrier::checkDeliveryPriceByPrice($row['id_carrier'], $total_order, (int)$id_zone, (int)$this->id_currency);

					// Get only carriers that have a range compatible with cart
					if (($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT && !$check_delivery_price_by_weight)
					|| ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_PRICE && !$check_delivery_price_by_price))
					{
						unset($result[$k]);
						continue;
					}
				}

				if ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT)
					$shipping = $carrier->getDeliveryPriceByWeight($this->getTotalWeight($product_list), (int)$id_zone);
				else
					$shipping = $carrier->getDeliveryPriceByPrice($order_total, (int)$id_zone, (int)$this->id_currency);

				if (!isset($min_shipping_price))
					$min_shipping_price = $shipping;

				if ($shipping <= $min_shipping_price)
				{
					$id_carrier = (int)$row['id_carrier'];
					$min_shipping_price = $shipping;
				}
			}
		}

		if (empty($id_carrier))
			$id_carrier = Configuration::get('PS_CARRIER_DEFAULT');

		if (!isset(self::$_carriers[$id_carrier]))
			self::$_carriers[$id_carrier] = new Carrier((int)$id_carrier, Configuration::get('PS_LANG_DEFAULT'));

		$carrier = self::$_carriers[$id_carrier];

		// No valid Carrier or $id_carrier <= 0 ?
		if (!Validate::isLoadedObject($carrier))
		{
			Cache::store($cache_id, 0);
			return 0;
		}

		if (!$carrier->active)
		{
			Cache::store($cache_id, $shipping_cost);
			return $shipping_cost;
		}

		// Free fees if free carrier
		if ($carrier->is_free == 1)
		{
			Cache::store($cache_id, 0);
			return 0;
		}

		// Select carrier tax
		if ($use_tax && !Tax::excludeTaxeOption())
		{
			$address = Address::initialize((int)$address_id);
			$carrier_tax = $carrier->getTaxesRate($address);
		}

		$configuration = Configuration::getMultiple(array(
			'PS_SHIPPING_FREE_PRICE',
			'PS_SHIPPING_HANDLING',
			'PS_SHIPPING_METHOD',
			'PS_SHIPPING_FREE_WEIGHT'
		));

		// Free fees
		$free_fees_price = 0;
		if (isset($configuration['PS_SHIPPING_FREE_PRICE']))
			$free_fees_price = Tools::convertPrice((float)$configuration['PS_SHIPPING_FREE_PRICE'], Currency::getCurrencyInstance((int)$this->id_currency));
		$orderTotalwithDiscounts = $this->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING, null, null, false);
		if ($orderTotalwithDiscounts >= (float)($free_fees_price) && (float)($free_fees_price) > 0)
		{
			Cache::store($cache_id, $shipping_cost);
			return $shipping_cost;
		}

		if (isset($configuration['PS_SHIPPING_FREE_WEIGHT'])
			&& $this->getTotalWeight() >= (float)$configuration['PS_SHIPPING_FREE_WEIGHT']
			&& (float)$configuration['PS_SHIPPING_FREE_WEIGHT'] > 0)
		{
			Cache::store($cache_id, $shipping_cost);
			return $shipping_cost;
		}

		// Get shipping cost using correct method
		if ($carrier->range_behavior)
		{
			if(!isset($id_zone))
			{
				// Get id zone
				if (isset($this->id_address_delivery)
					&& $this->id_address_delivery
					&& Customer::customerHasAddress($this->id_customer, $this->id_address_delivery))
					$id_zone = Address::getZoneById((int)$this->id_address_delivery);
				else
					$id_zone = (int)$default_country->id_zone;
			}

			if (($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT && !Carrier::checkDeliveryPriceByWeight($carrier->id, $this->getTotalWeight(), (int)$id_zone))
			|| ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_PRICE && !Carrier::checkDeliveryPriceByPrice($carrier->id, $total_package_without_shipping_tax_inc, $id_zone, (int)$this->id_currency)
			))
				$shipping_cost += 0;
			else
			{
				if ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT)
					$shipping_cost += $carrier->getDeliveryPriceByWeight($this->getTotalWeight($product_list), $id_zone);
				else
					$shipping_cost += $carrier->getDeliveryPriceByPrice($order_total, $id_zone, (int)$this->id_currency);
			}
		}
		else
		{
			if ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT)
				$shipping_cost += $carrier->getDeliveryPriceByWeight($this->getTotalWeight($product_list), $id_zone);
			else
				$shipping_cost += $carrier->getDeliveryPriceByPrice($order_total, $id_zone, (int)$this->id_currency);

		}
		// Adding handling charges
		if (isset($configuration['PS_SHIPPING_HANDLING']) && $carrier->shipping_handling)
			$shipping_cost += (float)$configuration['PS_SHIPPING_HANDLING'];

		// Additional Shipping Cost per product
		foreach ($products as $product)
			if (!$product['is_virtual'])
				$shipping_cost += $product['additional_shipping_cost'] * $product['cart_quantity'];

		$shipping_cost = Tools::convertPrice($shipping_cost, Currency::getCurrencyInstance((int)$this->id_currency));

		//get external shipping cost from module
		if ($carrier->shipping_external)
		{
			$module_name = $carrier->external_module_name;
			$module = Module::getInstanceByName($module_name);

			if (Validate::isLoadedObject($module))
			{
				if (array_key_exists('id_carrier', $module))
					$module->id_carrier = $carrier->id;
				if ($carrier->need_range)
					if (method_exists($module, 'getPackageShippingCost'))
						$shipping_cost = $module->getPackageShippingCost($this, $shipping_cost, $products);
					else
						$shipping_cost = $module->getOrderShippingCost($this, $shipping_cost);
				else
					$shipping_cost = $module->getOrderShippingCostExternal($this);

				// Check if carrier is available
				if ($shipping_cost === false)
				{
					Cache::store($cache_id, false);
					return false;
				}
			}
			else
			{
				Cache::store($cache_id, false);
				return false;
			}
		}

		$shipping_cost = (float)Tools::ps_round((float)$shipping_cost, 2);
		Cache::store($cache_id, $shipping_cost);

		return $shipping_cost;
	}

}
