
<br/>

<img src="../modules/payme/payme.png" style="float:left; margin-right:15px;">

<b>{$this->l('MODUL_DESCRIPTION')}</b>
<br/><br/>

<form action="{$action}" method="post">

	<fieldset>

		<legend><img src="../img/admin/edit.gif"/>{$this->l('SETTINGS')}</legend>
		<table border="0" width="550" cellpadding="0" cellspacing="0" id="form">

			<tr>
				<td width="250" style="height: 35px;">{$this->l('PAYME_MERCHANT')}</td>
				<td><input type="text" name="PAYME_MERCHANT_ID" value="{$PAYME_MERCHANT_ID}" style="width: 300px;"/></td>
			</tr>

			<tr>
				<td width="250" style="height: 35px;">{$this->l('SECURE_KEY')}</td>
				<td><input type="text" name="PAYME_SECRET_KEY" value="{$PAYME_SECRET_KEY}" style="width: 300px;"/></td>
			</tr>

			<tr>
				<td width="250" style="height: 35px;">{$this->l('SECURE_KEY_TEST')}</td>
				<td><input type="text" name="PAYME_SECRET_KEY_TEST" value="{$PAYME_SECRET_KEY_TEST}" style="width: 300px;"/></td>
			</tr>

			<tr>
				<td width="250" style="height: 35px;">{$this->l('ENDPOINT_URL')}</td>
				<td><input type="text" name="PAYME_ENDPOINT_URL" value="{$PAYME_ENDPOINT_URL}" style="width: 300px;"/></td>
			</tr>

			<tr>
				<td width="250" style="height: 35px;">{$this->l('TEST_MODE')}</td>
				<td><input type="checkbox" name="PAYME_TEST_MODE" {if ($PAYME_TEST_MODE) } checked="checked" {/if}></td>
			</tr>

			<tr>
				<td width="250" style="height: 35px;">{$this->l('CHECKOUT_URL')}</td>
				<td><input type="text" name="PAYME_CHECKOUT_URL" value="{$PAYME_CHECKOUT_URL}" style="width: 300px;"/></td>
			</tr>

			<tr>
				<td width="250" style="height: 35px;">{$this->l('CHECKOUT_URL_TEST')}</td>
				<td><input type="text" name="PAYME_CHECKOUT_URL_TEST" value="{$PAYME_CHECKOUT_URL_TEST}" style="width: 300px;"/></td>
			</tr>

			<tr>
				<td width="250" style="height: 35px;">{$this->l('RETURN_URL')}</td>
				<td><input type="text" name="PAYME_RETURN_URL" value="{$PAYME_RETURN_URL}" style="width: 300px;"/></td>
			</tr>

			<tr>
				<td width="250" style="height: 35px;">{$this->l('RETURN_AFTER')}</td>
				<td>
					<select name="PAYME_RETURN_AFTER" style="width: 300px;">
						{html_options options=$returnAfterList selected=$PAYME_RETURN_AFTER}
					</select>
				</td>
			</tr>

			<tr>
				<td width="250" style="height: 35px;">{$this->l('ADD_PRODUCT_INFORMATION')}</td>
				<td>
					<select name="PAYME_ADD_PRODUCT_INFORMATION" style="width: 300px;">
						{html_options options=$productInformationList selected=$PAYME_ADD_PRODUCT_INFORMATION}
					</select>
				</td>
			</tr>

			<tr>
				<td> </td>
				<td align="center"><input class="button" name="btnSubmit" value="{$this->l('SAVE')}" type="submit"/></td>
			</tr>

		</table>

	</fieldset>

</form>
 