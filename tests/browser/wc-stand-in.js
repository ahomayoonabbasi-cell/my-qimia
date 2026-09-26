/* Stand-in for WooCommerce's add-to-cart.js and cart-fragments.js: the same
   DOM contract (.ajax_add_to_cart, loading/added classes, "View cart" link,
   fragments replaced, then added_to_cart / wc_fragments_refreshed events). */
jQuery(function ($) {
	const endpoint = name => `/?wc-ajax=${name}`;
	const apply = fragments => $.each(fragments || {}, (key, value) => $(key).replaceWith(value));
	$(document.body).on('click', '.add_to_cart_button.ajax_add_to_cart', function (event) {
		const $button = $(this);
		if (!$button.attr('data-product_id')) return true;
		event.preventDefault();
		$button.removeClass('added').addClass('loading');
		$.post(endpoint('add_to_cart'), {product_id: $button.attr('data-product_id'), quantity: $button.attr('data-quantity') || 1})
			.done(response => {
				apply(response.fragments);
				$button.removeClass('loading').addClass('added');
				$button.after(' <a href="/cart/" class="added_to_cart wc-forward">View cart</a>');
				$(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, $button]);
			});
	});
	$(document.body).on('wc_fragment_refresh', () => {
		$.post(endpoint('get_refreshed_fragments')).done(response => { apply(response.fragments); $(document.body).trigger('wc_fragments_refreshed'); });
	});
	$(document.body).on('added_to_cart', (event, fragments) => { if (fragments) apply(fragments); });
});
