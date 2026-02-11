document.addEventListener('DOMContentLoaded', () => {
	dashifyOrderTableFilters();
});

function dashifyOrderTableFilters() {
	// Date filter
	dashifyFilterCustomDropdown('date', 'date', '#filter-by-date', '[selected="selected"]:not([value="0"])');

	// Order Tags by 99w
	dashifyFilterCustomDropdown('order-tags-99w', 'order tag', 'select[name="wcot_order_tags_filter"]', '[selected=""]:not([value=""])');

	// dashifyHasWooCommerceSubscriptions is a global added from Dashify_Base::dashify_enqueue_order_table_files.
	if (dashifyHasWooCommerceSubscriptions) {
		// Subscription order type filter
		dashifyFilterCustomDropdown('order-type', 'order type', '#dropdown_shop_order_subtype', '[selected="selected"]:not([value=""])');
	}

	// Customer filter
	dashifyFilterSelect2SubmitEvent('.wc-customer-search');

	// Position custom dropdowns correctly
	dashifyAdjustCustomDropdownPosition('date');
	dashifyAdjustCustomDropdownPosition('order-tags-99w');

	// Clear filters button
	dashifyClearFilters(['#filter-by-date', 'select[name="wcot_order_tags_filter"]', '.wc-customer-search', '#dropdown_shop_order_subtype']);
}
