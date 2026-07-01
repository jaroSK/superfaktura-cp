/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/


const btn = document.querySelector('.stiahnut-cp-button');
const cartItems = document.querySelectorAll('.woocommerce-cart-form__cart-item');
const cartItemsMeta = [];
cartItems.forEach(item => {
  const key = item.querySelector('.qty').getAttribute('name').replace('cart[', '').replace('][qty]', '');
  const wck_meta = item.querySelector('.wck-cart')?.textContent.replace(/:\s+/g, ': ').replace(/\s{2,}/g, '\n').trim() ?? '';
  const woo_meta = item.querySelector('.variation')?.textContent.replace(/:\s+/g, ': ').trim() ?? '';
  cartItemsMeta.push({
    key,
    wck_meta,
    woo_meta
  });
});
btn.addEventListener('click', sfapiCreateCP);
async function sfapiCreateCP() {
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
      body: JSON.stringify({
        cartItemsData: cartItemsData,
        discountData: discountData
      })
    });
    if (!sfapiCreateCPResponse.ok) {
      throw new Error(`Error creating SuperFaktúra CP - (${sfapiCreateCPResponse.status})!`);
    }
    const sfapiCPPdf = await sfapiCreateCPResponse.json();
    if (!sfapiCPPdf.success) {
      throw new Error(`Error downloading SuperFaktúra CP pdf!`);
    } else {
      window.location.href = sfapiCPPdf.data.url;
    }
  } catch (err) {
    console.error(`${err}`);
  }
}
/******/ })()
;
//# sourceMappingURL=index.js.map