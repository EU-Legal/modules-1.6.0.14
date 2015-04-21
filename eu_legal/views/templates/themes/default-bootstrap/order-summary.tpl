{**
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
*}
<div id="orderSummaryWrapper">
	{hook h="displayBeforeShoppingCartBlock"}
	
	<div id="order-detail-content" class="table_block table-responsive">
		<table id="cart_summary" class="table table-bordered">
			<thead>
				<tr>
					<th class="cart_product first_item">{l s='Product' mod='eu_legal'}</th>
					<th class="cart_description item">{l s='Description' mod='eu_legal'}</th>
					{if $PS_STOCK_MANAGEMENT}
						<th class="cart_availability item">{l s='Avail.' mod='eu_legal'}</th>
					{/if}
					<th class="cart_unit item">{l s='Unit price' mod='eu_legal'}</th>
					<th class="cart_quantity item">{l s='Qty' mod='eu_legal'}</th>
					<th class="cart_total item">{l s='Total' mod='eu_legal'}</th>
					<th class="cart_delete last_item">&nbsp;</th>
				</tr>
			</thead>
			<tfoot>
				{if $use_taxes}
					{if $priceDisplay}
						<tr class="cart_total_price">
							<td colspan="5" class="text-right">{if $display_tax_label}{l s='Total products (tax excl.)' mod='eu_legal'}{else}{l s='Total products' mod='eu_legal'}{/if}</td>
							<td colspan="2" class="price" id="total_product">{displayPrice price=$total_products}</td>
						</tr>
					{else}
						<tr class="cart_total_price">
							<td colspan="5" class="text-right">{if $display_tax_label}{l s='Total products (tax incl.)' mod='eu_legal'}{else}{l s='Total products' mod='eu_legal'}{/if}</td>
							<td colspan="2" class="price" id="total_product">{displayPrice price=$total_products_wt}</td>
						</tr>
					{/if}
				{else}
					<tr class="cart_total_price">
						<td colspan="5" class="text-right">{l s='Total products' mod='eu_legal'}</td>
						<td colspan="2" class="price" id="total_product">{displayPrice price=$total_products}</td>
					</tr>
				{/if}
				<tr class="cart_total_voucher" {if $total_wrapping == 0}style="display:none"{/if}>
					<td colspan="5" class="text-right">
						{if $use_taxes}
							{if $priceDisplay}
								{if $display_tax_label}{l s='Total gift wrapping (tax excl.):' mod='eu_legal'}{else}{l s='Total gift wrapping cost:' mod='eu_legal'}{/if}
							{else}
								{if $display_tax_label}{l s='Total gift wrapping (tax incl.)' mod='eu_legal'}{else}{l s='Total gift wrapping cost:' mod='eu_legal'}{/if}
							{/if}
						{else}
							{l s='Total gift wrapping cost:' mod='eu_legal'}
						{/if}
					</td>
					<td colspan="2" class="price-discount price" id="total_wrapping">
						{if $use_taxes}
							{if $priceDisplay}
								{displayPrice price=$total_wrapping_tax_exc}
							{else}
								{displayPrice price=$total_wrapping}
							{/if}
						{else}
							{displayPrice price=$total_wrapping_tax_exc}
						{/if}
					</td>
				</tr>
				{if ! $PS_EU_PAYMENT_API}
					{if $total_shipping_tax_exc <= 0 && !isset($virtualCart)}
						<tr class="cart_total_delivery">
							<td colspan="5" class="text-right">{l s='Shipping:' mod='eu_legal'}</td>
							<td colspan="2" class="price" id="total_shipping">{l s='Free Shipping!' mod='eu_legal'}</td>
						</tr>
					{else}
						{if $use_taxes && $total_shipping_tax_exc != $total_shipping}
							{if $priceDisplay}
								<tr class="cart_total_delivery" {if $shippingCost <= 0} style="display:none"{/if}>
									<td colspan="5" class="text-right">{if $display_tax_label}{l s='Total shipping (tax excl.)' mod='eu_legal'}{else}{l s='Total shipping' mod='eu_legal'}{/if}</td>
									<td colspan="2" class="price" id="total_shipping">{displayPrice price=$shippingCostTaxExc}</td>
								</tr>
							{else}
								<tr class="cart_total_delivery"{if $shippingCost <= 0} style="display:none"{/if}>
									<td colspan="5" class="text-right">{if $display_tax_label}{l s='Total shipping (tax incl.)' mod='eu_legal'}{else}{l s='Total shipping' mod='eu_legal'}{/if}</td>
									<td colspan="2" class="price" id="total_shipping" >{displayPrice price=$shippingCost}</td>
								</tr>
							{/if}
						{else}
							<tr class="cart_total_delivery"{if $shippingCost <= 0} style="display:none"{/if}>
								<td colspan="5" class="text-right">{l s='Total shipping' mod='eu_legal'}</td>
								<td colspan="2" class="price" id="total_shipping" >{displayPrice price=$shippingCostTaxExc}</td>
							</tr>
						{/if}
					{/if}
				{else}
					<tr class="cart_total_delivery">
						<td colspan="5" class="text-right">{l s='Shipping' mod='eu_legal'}</td>
						<td colspan="2" class="price" id="total_shipping">{hook h="displayShippingPrice"}</td>
					</tr>
				{/if}
				<tr class="cart_total_voucher" {if $total_discounts == 0}style="display:none"{/if}>
					<td colspan="5" class="text-right">
						{if $use_taxes}
							{if $priceDisplay}
								{if $display_tax_label}{l s='Total vouchers (tax excl.)' mod='eu_legal'}{else}{l s='Total vouchers' mod='eu_legal'}{/if}
							{else}
								{if $display_tax_label}{l s='Total vouchers (tax incl.)' mod='eu_legal'}{else}{l s='Total vouchers' mod='eu_legal'}{/if}
							{/if}
						{else}
							{l s='Total vouchers' mod='eu_legal'}
						{/if}
					</td>
					<td colspan="2" class="price-discount price" id="total_discount">
						{if $use_taxes}
							{if $priceDisplay}
								{displayPrice price=$total_discounts_tax_exc*-1}
							{else}
								{displayPrice price=$total_discounts*-1}
							{/if}
						{else}
							{displayPrice price=$total_discounts_tax_exc*-1}
						{/if}
					</td>
				</tr>
				{if $use_taxes}
					{if $priceDisplay && $total_tax != 0}
						<tr class="cart_total_tax">
							<td colspan="5" class="text-right">{l s='Total tax:' mod='eu_legal'}</td>
							<td colspan="2" class="price" id="total_tax" >{displayPrice price=$total_tax}</td>
						</tr>
					{/if}
					<tr class="cart_total_price">
						<td colspan="5" class="total_price_container text-right"><span>{l s='Total' mod='eu_legal'}</span></td>
						<td colspan="2" class="price" id="total_price_container">
							<span id="total_price">{displayPrice price=$total_price}</span>
						</td>
					</tr>
				{/if}
				<tr>
					<td colspan="7">{hook h="displayAfterShoppingCartBlock"}</td>
				</tr>
			</tfoot>
	
			<tbody>
				{foreach from=$products item=product name=productLoop}
					{assign var='productId' value=$product.id_product}
					{assign var='productAttributeId' value=$product.id_product_attribute}
					{assign var='quantityDisplayed' value=0}
					{assign var='cannotModify' value=0}
					{assign var='odd' value=$product@iteration%2}
					{assign var='noDeleteButton' value=0}
	
					{* Display the product line *}
					{include file="$legal_theme_dir./shopping-cart-product-line.tpl"}
	
					{* Then the customized datas ones*}
					{if isset($customizedDatas.$productId.$productAttributeId)}
						{foreach from=$customizedDatas.$productId.$productAttributeId[$product.id_address_delivery] key='id_customization' item='customization'}
							<tr id="product_{$product.id_product|escape:'htmlall'}_{$product.id_product_attribute|escape:'htmlall'}_{$id_customization|escape:'htmlall'}" class="alternate_item cart_item">
								<td colspan="4">
									{foreach from=$customization.datas key='type' item='datas'}
										{if $type == $CUSTOMIZE_FILE}
											<div class="customizationUploaded">
												<ul class="customizationUploaded">
													{foreach from=$datas item='picture'}
														<li>
															<img src="{$pic_dir|escape:'htmlall'}{$picture.value|escape:'htmlall'}_small" alt="" class="customizationUploaded" />
														</li>
													{/foreach}
												</ul>
											</div>
										{elseif $type == $CUSTOMIZE_TEXTFIELD}
											<ul class="typedText">
												{foreach from=$datas item='textField' name='typedText'}
													<li>
														{if $textField.name}
															{l s='%s:' mod='eu_legal' sprintf=$textField.name}
														{else}
															{l s='Text #%s:' mod='eu_legal' sprintf=$smarty.foreach.typedText.index+1}
														{/if}
														{$textField.value|escape:'htmlall'}
													</li>
												{/foreach}
											</ul>
										{/if}
									{/foreach}
								</td>
								<td class="cart_quantity text-center">
									{$customization.quantity|escape:'htmlall'}
								</td>
								<td class="cart_delete">
									<div>
										<a rel="nofollow" class="cart_quantity_delete" id="{$product.id_product}_{$product.id_product_attribute}_{$id_customization}_{$product.id_address_delivery|intval}" href="{$link->getPageLink('cart', true, NULL, "delete=1&amp;id_product={$product.id_product|intval}&amp;ipa={$product.id_product_attribute|intval}&amp;id_customization={$id_customization}&amp;id_address_delivery={$product.id_address_delivery}&amp;token={$token_cart}")|escape:'html'}">{l s='Delete' mod='eu_legal'}</a>
									</div>
								</td>
							</tr>
							{assign var='quantityDisplayed' value=$quantityDisplayed+$customization.quantity}
						{/foreach}
						{* If it exists also some uncustomized products *}
						{if $product.quantity-$quantityDisplayed > 0}{include file="$legal_theme_dir./shopping-cart-product-line.tpl"}{/if}
					{/if}
				{/foreach}
				{assign var='last_was_odd' value=$product@iteration%2}
				{foreach $gift_products as $product}
					{assign var='productId' value=$product.id_product}
					{assign var='productAttributeId' value=$product.id_product_attribute}
					{assign var='quantityDisplayed' value=0}
					{assign var='odd' value=($product@iteration+$last_was_odd)%2}
					{assign var='ignoreProductLast' value=isset($customizedDatas.$productId.$productAttributeId)}
					{assign var='cannotModify' value=0}
					{* Display the gift product line *}
					{include file="$legal_theme_dir./shopping-cart-product-line.tpl" productLast=$product@last productFirst=$product@first}
				{/foreach}
			</tbody>
	
			{if count($discounts)}
				<tbody>
					{foreach from=$discounts item=discount name=discountLoop}
						<tr class="cart_discount {if $smarty.foreach.discountLoop.last}last_item{elseif $smarty.foreach.discountLoop.first}first_item{else}item{/if}" id="cart_discount_{$discount.id_discount}">
							<td class="cart_discount_description" colspan="2">{$discount.description|escape:'htmlall'}</td>
							<td class="cart_discount_name">{$discount.name|escape:'htmlall'}</td>
							<td class="cart_discount_price" colspan="3">
								<span class="price-discount">
									{if $discount.value_real > 0}
										{if !$priceDisplay}
											{displayPrice price=$discount.value_real*-1}
										{else}
											{displayPrice price=$discount.value_tax_exc*-1}
										{/if}
									{/if}
								</span>
							</td>
							<td class="price_discount_del text-center">
								{if strlen($discount.code)}
									<a
											href="{if $opc}{$link->getPageLink('order-opc', true)}{else}{$link->getPageLink('order', true)}{/if}?deleteDiscount={$discount.id_discount}"
											class="price_discount_delete"
											title="{l s='Delete' mod='eu_legal'}">
										<i class="icon-trash"></i>
									</a>
								{/if}
							</td>
						</tr>
					{/foreach}
				</tbody>
			{/if}
		</table>
	</div> <!-- end order-detail-content -->
	<div id="HOOK_SHOPPING_CART">{$HOOK_SHOPPING_CART}</div>
</div>