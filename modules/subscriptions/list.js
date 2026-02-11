document.addEventListener('DOMContentLoaded', () => {
	dashifySubscriptionTableFilters();
});

function dashifySubscriptionTableFilters() {
	dashifyFilterCustomDropdown('date', 'date', '#filter-by-date', '[selected="selected"]:not([value="0"])');

	dashifyFilterCustomDropdown('payment-method', 'payment method', '#_payment_method', '[selected=""]:not([value=""])');

	dashifyFilterSelect2SubmitEvent('.wc-customer-search');

	dashifyFilterSelect2SubmitEvent('.wc-product-search');
	// Remove the ellipses so it doesn’t appear as if the full label is cut off.
	document.querySelector('select.wc-product-search').setAttribute('data-placeholder', 'Search for a product');

	// Position custom dropdowns correctly
	dashifyAdjustCustomDropdownPosition('date');

	dashifyClearFilters(['#filter-by-date', '.wc-customer-search', '.wc-product-search', '#_payment_method']);
}
