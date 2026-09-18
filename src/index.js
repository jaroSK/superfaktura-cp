'use strict';

const cartItems = document.querySelectorAll('.woocommerce-cart-form__cart-item');
const cartItemsMeta = [];
cartItems.forEach(item => {
	const key = item.querySelector('.qty').getAttribute('name').replace('cart[', '').replace('][qty]', '');
	const wck_meta =
		item
			.querySelector('.wck-cart')
			?.textContent.replace(/:\s+/g, ': ')
			.replace(/\s{2,}/g, '\n')
			.trim() ?? '';
	const woo_meta = item.querySelector('.variation')?.textContent.replace(/:\s+/g, ': ').trim() ?? '';

	cartItemsMeta.push({ key, wck_meta, woo_meta });
});

document.querySelector('body').addEventListener('click', function (e) {
	if (e.target.classList.contains('stiahnut-cp-button')) {
		sfapiCreateCP(e.target);
	}
});

async function sfapiCreateCP(btn) {
	btn.textContent = 'Vytváram CP, počkajte prosím...';
	btn.disabled = true;

	const cartDataUrl = `${sfapi_cp_data.root_url}/wp-json/wc/store/v1/cart`;
	const cartItemsDataUrl = `${sfapi_cp_data.root_url}/wp-json/wc/store/v1/cart/items`;
	const sfapiCreateCPUrl = `${sfapi_cp_data.root_url}/wp-json/superfaktura-cp/v1/create`;

	try {
		const [cartDataResponse, cartItemsDataResponse] = await Promise.all([fetch(cartDataUrl), fetch(cartItemsDataUrl)]);
		if (!cartDataResponse.ok || !cartItemsDataResponse.ok) {
			throw new Error(`Error getting cart data - (${cartDataResponse.status}) or error getting cart items data - (${cartItemsDataResponse.status})!`);
		}
		const [cartData, cartItemsData] = await Promise.all([cartDataResponse.json(), cartItemsDataResponse.json()]);

		const discountData = cartData.fees;

		cartItemsData.forEach(cartItem => {
			cartItemsMeta.forEach(cartItemMeta => {
				if (cartItem.key === cartItemMeta.key) {
					cartItem.wck_meta = cartItemMeta.wck_meta;
					cartItem.woo_meta = cartItemMeta.woo_meta;
				}
			});
		});

		const sfapiCreateCPResponse = await fetch(sfapiCreateCPUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({ cartItemsData: cartItemsData, discountData: discountData })
		});

		if (!sfapiCreateCPResponse.ok) {
			throw new Error(`Error creating SuperFaktúra CP - (${sfapiCreateCPResponse.status})!`);
		}
		const sfapiCPPdf = await sfapiCreateCPResponse.json();

		if (!sfapiCPPdf.success) {
			throw new Error(sfapiCPPdf.error_message.type[0]);
		} else {
			window.location.href = sfapiCPPdf.url;
		}
	} catch (err) {
		console.error(`${err}`);
	}

	btn.textContent = 'Stiahnuť cenovú ponuku';
	btn.disabled = false;
}
