document.addEventListener('DOMContentLoaded', function() {
	var priceWrappers = document.querySelectorAll('.lgp-live-price-wrapper');
	if (priceWrappers.length === 0) {
		return;
	}

	var productIds = [];
	priceWrappers.forEach(function(wrapper) {
		var id = wrapper.getAttribute('data-product-id');
		if (id && productIds.indexOf(id) === -1) {
			productIds.push(id);
		}
	});

	if (productIds.length > 0) {
		var url = lgp_data.rest_url + '?ids=' + productIds.join(',');

		fetch(url, {
			method: 'GET',
			headers: {
				'X-WP-Nonce': lgp_data.nonce,
				'Accept': 'application/json'
			}
		})
		.then(function(response) {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(function(data) {
			priceWrappers.forEach(function(wrapper) {
				var id = wrapper.getAttribute('data-product-id');
				if (data[id]) {
					// Replace the inner HTML with the freshly calculated live price HTML
					wrapper.innerHTML = data[id];
				}
			});
		})
		.catch(function(error) {
			console.error('LGP Error fetching live prices:', error);
		});
	}
});

// Also hook into jQuery AJAX events to re-apply if new products are loaded dynamically (e.g. infinite scroll, AJAX filters)
if (typeof jQuery !== 'undefined') {
	function fetchAndReplaceLivePrices() {
		var newWrappers = document.querySelectorAll('.lgp-live-price-wrapper:not(.lgp-updated)');
		if (newWrappers.length > 0) {
			var newIds = [];
			newWrappers.forEach(function(wrapper) {
				var id = wrapper.getAttribute('data-product-id');
				if (id && newIds.indexOf(id) === -1) {
					newIds.push(id);
				}
				wrapper.classList.add('lgp-updated'); // Mark as updating to prevent loops
			});

			if (newIds.length > 0) {
				fetch(lgp_data.rest_url + '?ids=' + newIds.join(','), {
					headers: {
						'X-WP-Nonce': lgp_data.nonce,
						'Accept': 'application/json'
					}
				})
				.then(r => r.json())
				.then(data => {
					newWrappers.forEach(function(wrapper) {
						var id = wrapper.getAttribute('data-product-id');
						if (data[id]) {
							wrapper.innerHTML = data[id];
						}
					});
				});
			}
		}
	}

	jQuery(document).on('ajaxComplete', function(event, xhr, settings) {
		// Only trigger if the ajax response likely contains new products
		if (settings.url && (settings.url.indexOf('admin-ajax.php') !== -1 || settings.url.indexOf('wc-ajax') !== -1)) {
			setTimeout(fetchAndReplaceLivePrices, 200);
		}
	});

	var originalTopPrice = null;

	// Handle WooCommerce variable product variations
	jQuery(document).on('show_variation', function(event, variation) {
		if (variation && variation.variation_id) {
			var vid = variation.variation_id;
			var container = document.querySelector('.woocommerce-variation-price');
			var topPriceContainer = document.querySelector('div.summary p.price');
			
			if (topPriceContainer && !originalTopPrice) {
				originalTopPrice = topPriceContainer.innerHTML;
			}
			
			// Show loading skeleton immediately
			var skeletonHTML = '<div class="lgp-loading-skeleton"></div>';
			if (container) {
				container.innerHTML = skeletonHTML;
				container.style.display = 'block';
			}
			if (topPriceContainer) {
				topPriceContainer.innerHTML = skeletonHTML;
			}

			fetch(lgp_data.rest_url + '?ids=' + vid, {
				headers: {
					'X-WP-Nonce': lgp_data.nonce,
					'Accept': 'application/json'
				}
			})
			.then(r => r.json())
			.then(data => {
				if (data[vid]) {
					// We MUST wrap the response so that the 1-minute auto-fetcher can find it later!
					var livePriceHTML = '<span class="lgp-live-price-wrapper lgp-updated" data-product-id="' + vid + '">' + data[vid] + '</span>';
					
					if (container) {
						container.innerHTML = livePriceHTML;
					}
					if (topPriceContainer) {
						topPriceContainer.innerHTML = livePriceHTML;
					}
				}
			});
		}
	});

	// Restore top price when variation is cleared
	jQuery(document).on('hide_variation', function(event) {
		if (originalTopPrice) {
			var topPriceContainer = document.querySelector('div.summary p.price');
			if (topPriceContainer) {
				topPriceContainer.innerHTML = originalTopPrice;
			}
		}
	});

	// Automatically poll every 60 seconds (60000ms) to update live prices on the page without refresh
	setInterval(function() {
		// Remove 'lgp-updated' from all current wrappers so they can be fetched again
		var allWrappers = document.querySelectorAll('.lgp-live-price-wrapper');
		if (allWrappers.length > 0) {
			allWrappers.forEach(function(wrapper) {
				wrapper.classList.remove('lgp-updated');
			});
			fetchAndReplaceLivePrices();
		}

		// Also update Cart and Checkout dynamically if the user is on those pages
		if (document.querySelector('.woocommerce-cart-form')) {
			// Trigger WooCommerce native cart update AJAX
			jQuery(document.body).trigger('wc_update_cart');
		}
		if (document.querySelector('form.checkout')) {
			// Trigger WooCommerce native checkout update AJAX
			jQuery(document.body).trigger('update_checkout');
		}
	}, 60000);
}
