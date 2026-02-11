document.addEventListener('DOMContentLoaded', () => {
	try {
		dashifyTableScroll();
		dashifyPageHeader();
		dashifyTableHeader();
		dashifyOrderSearch();
		dashifyFilters();
		dashifyBulkActions(getBulkActions());
		dashifyPagination();
		dashifyOrderNumberNamePreview();
		dashifyOrderStatus();
		dashifyFooter();
		dashifyAnalytics();
		dashifyEmptyTrashButton();
		dashifyAddFiltersToOrderLinks();
		dashifyListTableSearchResultCount();
	} finally {
		showDashify();
	}
});

/**
 * Makes adjustments to the orders table to make it scroll horizontally
 */
function dashifyTableScroll() {
	const $tableContainer = makeElement('div', { class: 'wp-list-table-container', ['scroll-x']: '0' }, {}, [{
		event: 'scroll',
		handler: (event) => {
			event.currentTarget.setAttribute('scroll-x', `${event.currentTarget.scrollLeft}`);
		}
	}]);
	document.querySelector('.wp-list-table').insertAdjacentElement('beforebegin', $tableContainer);
	$tableContainer.append(document.querySelector('.wp-list-table'));
}

function dashifyPageHeader() {
	const $heading = document.querySelector('.wp-heading-inline');
	const $header = makeElement('div', { class: 'dashify-page-top' });
	$heading.insertAdjacentElement('beforebegin', $header);
	$header.insertAdjacentElement('afterbegin', $heading);
	const $pageActions = makeElement('div', { class: 'dashify-page-actions' });
	document.querySelectorAll('.page-title-action').forEach($pageAction => {
		$pageActions.insertAdjacentElement('beforeend', $pageAction);
	});
	$header.insertAdjacentElement('beforeend', $pageActions);
}

/**
 * Makes the table top header with 'All', 'Processing', 'Drafts' filters
 */
function dashifyTableHeader() {
	// Make table top header
	const $header1 = makeElement('div', { class: 'dashify-orders-table-header-1' });
	(document.querySelector('#posts-filter') ?? document.querySelector('#wc-orders-filter')).insertAdjacentElement('afterbegin', $header1);

	// Move 'All', 'Processing', 'Drafts' header inside the form
	const $subsubsub = document.querySelector('.subsubsub');
	$header1.append($subsubsub);

	// Remove pipe characters between 'All', 'Processing', 'Drafts'
	$subsubsub.querySelectorAll('li:not(:last-child)').forEach($li => {
		$li.innerHTML = $li.innerHTML.slice(0, -1);
	});

	// Highlight 'All' tab if no other is selected
	if (!document.querySelector('.subsubsub .current')) {
		document.querySelector('.subsubsub .all a').classList.add('current');
	}
}

/**
 * Enhances the search UI and moves it to the table top header
 */
function dashifyOrderSearch() {
	// If filters are selected such that there are no filtered results, the
	// original WooCommerce search disappears, so here we create an empty div
	// as a backup.
	const $search = document.querySelector('.search-box') || document.createElement('div');

	const $searchContainer = makeElement('div', { class: 'dashify-search' }, {}, [{
		event: 'click', handler: (event) => {
			if (!event.currentTarget.classList.contains('expanded')) {
				event.currentTarget.classList.add('expanded');
				(document.querySelector('#post-search-input') ?? document.querySelector('#orders-search-input-search-input')).focus();
				dashifyHideEmptyTrashButton();
			}
		}
	}]);

	if (typeof openSearchAndFilterByDefault !== 'undefined' && openSearchAndFilterByDefault) {
		$searchContainer.classList.add('expanded');
	}

	// Wrap the original search bar in the button we just made.
	$searchContainer.append($search);

	// Move the search bar inside the top header
	document.querySelector('.dashify-orders-table-header-1').insertAdjacentElement('beforeend', $searchContainer);

	if (document.querySelector('#order-search-filter')) {
		// Search bar placeholder initial text if there's search a search filter dropdown for HPOS setting
		const setSearchFilter = (val) => {
			let searchMessage = '';
			if (val === 'all') {
				searchMessage = 'Search';
			} else {
				const prettySearchFilter = document.querySelector(`#order-search-filter option[value="${val}"]`).innerHTML;
				searchMessage = `Searching by ${prettySearchFilter}`;
			}
			document.querySelector('#orders-search-input-search-input').setAttribute('placeholder', searchMessage);
		};
		setSearchFilter(document.querySelector('#order-search-filter').value);
		document.querySelector('#order-search-filter').addEventListener('change', (event) => {
			setSearchFilter(event.target.value);
		});
	} else {
		// Search bar placeholder if there's no search filter dropdown
		(document.querySelector('#post-search-input') ?? document.querySelector('#orders-search-input-search-input'))?.setAttribute('placeholder', 'Search');
	}

	// Remove search submit button
	document.querySelector('#search-submit')?.remove();

	// If the search box is rendered, add search-related elements
	if (document.querySelector('.search-box')) {
		// Make divs for the search and filter icons
		const $searchIcon = makeElement('div', { class: 'search-icon' });
		const $filterIcon = makeElement('div', { class: 'filter-icon' });
		const $searchAndFilterIcons = makeElement('div', { class: 'icons' });
		$searchAndFilterIcons.append($searchIcon);
		$searchAndFilterIcons.append($filterIcon);
		$searchContainer.insertAdjacentElement('afterbegin', $searchAndFilterIcons);

		// Add tooltip to search button
		let $tooltip = makeElement('div', { class: 'dashify-tooltip top' }, { innerHTML: 'Search and filter (F)' });
		$searchContainer.append($tooltip);

		// Without this, the tooltip makes the page wider and the right part of
		// the toolip is then hidden behind scrolling.
		$tooltip.style.width = '6rem';

		// Cancel search button
		const $cancel = makeElement('div', { class: 'dashify-search-cancel' }, { innerHTML: 'Cancel' }, [{
			event: 'click',
			handler: (event) => {
				event.stopPropagation();

				const searchInput = document.querySelector('#orders-search-input-search-input') ?? document.querySelector('#post-search-input');
				if (searchInput) {
					if (searchInput.value === '') {
						event.currentTarget.closest('.dashify-search').classList.remove('expanded');
					} else {
						dashifyClearSearch();
					}
					dashifyShowEmptyTrashButton();
				} else {
					event.currentTarget.closest('.dashify-search').classList.remove('expanded');
				}
			}
		}]);
		document.querySelector('.search-box').insertAdjacentElement('beforeend', $cancel);
	}

	// If a search query is active when the page is reloaded, which happens
	// when first entering a search, expand the search area.
	const params = new URLSearchParams(window.location.search);
	if (params.get('filter_action') && params.get('filter_action') === 'Filter') {
		document.querySelector('.dashify-search').classList.add('expanded');
	}

	// Add event listener for expanding search on 'F' key press
	document.addEventListener('keydown', (event) => {
		if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) || event.key.toLowerCase() !== 'f' || document.querySelector('.select2-container--open')) {
			return;
		}
		event.preventDefault();
		document.querySelector('.dashify-search').classList.add('expanded');
		(document.querySelector('#post-search-input') ?? document.querySelector('#orders-search-input-search-input')).focus();
		dashifyHideEmptyTrashButton();
	});
}

function dashifyHideEmptyTrashButton() {
	dashifySetEmptyTrashDisplay('none');
}

function dashifyShowEmptyTrashButton() {
	dashifySetEmptyTrashDisplay('inline');
}

function dashifySetEmptyTrashDisplay(value) {
	$emptyTrash = document.querySelector('input#delete_all');
	if ($emptyTrash) {
		$emptyTrash.style.display = value;
	}
}

function dashifyClearSearch() {
	const input = document.querySelector('#orders-search-input-search-input') ?? document.querySelector('#post-search-input');
	const submit = document.querySelector('#order-query-submit') ?? document.querySelector('#post-query-submit');
	input.value = '';
	submit.click();
}

function dashifyAdjustCustomDropdownPosition(key) {
	waitForElement('.dashify-filters').then(() => {
		const $filterButton = document.querySelector(`.dashify-${key}-filter`);
		let $previousElement = $filterButton.previousElementSibling;
		let shift = 0;
		while ($previousElement) {
			shift += $previousElement.clientWidth + 4;
			$previousElement = $previousElement.previousElementSibling;
		}
		document.querySelector(`.dashify-${key}-dropdown`).setAttribute(
			'style',
			`transform: translateX(${shift + 2}px);`
		);
	});
}

function dashifyFilterCustomDropdown(key, prettyText, selectSelector, selectedOptionSelector) {
	// Filter button
	const $filterButton = makeElement('div', { class: `dashify-${key}-filter` }, { innerHTML: `Filter by ${prettyText}` }, [{
		event: 'click',
		handler: () => {
			const $dropdown = document.querySelector(`.dashify-${key}-dropdown`);
			$dropdown.classList.toggle('expanded');
			if ($dropdown.classList.contains('expanded')) {
				const closeDropdownListener = (event) => {
					if (!event.target.closest(`.dashify-${key}-dropdown`) && !event.target.closest(`.dashify-${key}-filter`)) {
						document.querySelector(`.dashify-${key}-dropdown`).classList.remove('expanded');
						window.removeEventListener('click', closeDropdownListener);
					}
				};
				window.addEventListener('click', closeDropdownListener);
			}
		}
	}]);
	waitForElement('.dashify-filters').then(() => {
		document.querySelector('.dashify-filters').prepend($filterButton);
	});

	// Filter dropdown
	const $dropdown = makeElement('div', { class: `dashify-filter-dropdown dashify-${key}-dropdown` });
	document.querySelectorAll(`${selectSelector} option:not([value=""])`).forEach($option => {
		const $dropdownLabel = makeElement('label', { for: `dashify-${key}-` + $option.value }, { innerHTML: $option.innerHTML });
		const $dropdownItem = makeElement('input', {
			nodeType: 'INPUT',
			type: 'radio',
			value: $option.value,
			id: `dashify-${key}-${$option.value}`
		}, {}, [{
			event: 'input',
			handler: () => {
				document.querySelector(`${selectSelector}`).value = $option.value;
				(document.querySelector('#order-query-submit') ?? document.querySelector('#post-query-submit')).click();
			}
		}]);
		$dropdownLabel.append($dropdownItem);
		$dropdown.append($dropdownLabel);
	});
	const $form = (document.querySelector('#posts-filter') ?? document.querySelector('#wc-orders-filter'));
	$form.append($dropdown);

	// Filter initial state
	const $selectedOption = document.querySelector(`${selectSelector} ${selectedOptionSelector}`);
	if ($selectedOption) {
		$filterButton.innerHTML = `${$selectedOption.innerHTML}<span class="dashify-filter-remove"></span>`;
		$filterButton.querySelector('.dashify-filter-remove').addEventListener('click', (event) => {
			event.stopPropagation();
			document.querySelector(`${selectSelector}`).value = '0';
			(document.querySelector('#order-query-submit') ?? document.querySelector('#post-query-submit')).click();
		});
		if (!document.querySelector('.no-items')) {
			waitForElement('.dashify-search').then(() => {
				document.querySelector('.dashify-search').classList.add('expanded');
			});
		}
		document.querySelector(`#dashify-${key}-${$selectedOption.value}`).checked = true;
	}
}

function dashifySelect2FilterRemovers() {
	// Add a custom clear X to the Select2 filters.
	waitForElement('.select2-selection__clear').then(addCustomFilterRemovers);

	function addCustomFilterRemovers() {
		const originalRemovers = document.querySelectorAll('.dashify-filters .select2-selection__clear');
		if (originalRemovers.length > 0) {
			document.querySelector('.dashify-search')?.classList.add('expanded');
			originalRemovers.forEach(original => {
				const customRemover = createCustomRemover();
				original.insertAdjacentElement('afterend', customRemover);
				original.remove();
			});
		}
	}

	function createCustomRemover() {
		return makeElement('span', { class: 'dashify-filter-remove' }, {}, [{
			event: 'click',
			handler: event => handleCustomRemoverClick(event)
		}]);
	}

	function handleCustomRemoverClick(event) {
		const dropdown = document.querySelector('.select2-dropdown');
		// Product filter gets added with WooCommerce Subscriptions.
		const searchInput =
			event.target.parentElement.id.includes('product')
				? document.querySelector('.wc-product-search')
				: document.querySelector('.wc-customer-search');
		const submitButton = document.querySelector('#order-query-submit') ?? document.querySelector('#post-query-submit');

		// If there is a way to do this without having the dropdown flash for a
		// second before this gets applied…
		dropdown.style.display = 'none';
		searchInput.value = '';
		submitButton.click();
	}
}

function dashifyFilterSelect2SubmitEvent(selectSelector) {
	const $selectElement = document.querySelector(selectSelector);
	if ($selectElement) {
		const customerFilterObserver = new MutationObserver(() => {
			(document.querySelector('#order-query-submit') ?? document.querySelector('#post-query-submit')).click();
		});
		customerFilterObserver.observe($selectElement, {
			attributeFilter: ['selected'],
			childList: true
		});
	}
}

function dashifyClearFilters(selectSelectors) {
	const $clearFilters = makeElement('button', { class: 'dashify-clear-filter' }, { innerHTML: 'Clear filters' }, [{
		event: 'click',
		handler: () => {
			selectSelectors.forEach(selector => {
				const $select = document.querySelector(selector);
				if ($select) {
					$select.value = '';
				}
			});

			(document.querySelector('#order-query-submit') ?? document.querySelector('#post-query-submit')).click();
		}
	}]);
	waitForElement('.dashify-filters').then(() => {
		document.querySelector('.dashify-filters').append($clearFilters);
	});
}

/**
 * Creates a Shopify-style filters tool and places it in the table top header (collapsed under 'Search and 'Filter' button)
 */
function dashifyFilters() {
	// Move filters to below the search
	const $filters = document.querySelector('.tablenav.top .actions:not(.bulkactions)');
	$filters.classList.remove('alignleft');
	$filters.classList.add('dashify-filters');
	document.querySelector('.dashify-orders-table-header-1').insertAdjacentElement('afterend', $filters);

	// Hide filter form elements
	for (const $filter of $filters.children) {
		if (!$filter.classList.contains('select2')) {
			$filter.style.display = 'none';
		}
	}

	dashifySelect2FilterRemovers();
}

/**
 * Get the bulk actions from the native WooCommerce dropdown for use in ours.
 * By grabbing them from here, we ensure compatibility with any plugin that adds
 * bulk actions as well as users of WooCommerce in all languages.
 * @returns {Array<Object>}
 */
function getBulkActions() {
	const options = document.querySelectorAll('#bulk-action-selector-top option');
	if (!options) {
		return;
	}
	const keysToSkip = new Set([
		'-1',
	]);
	const actions = [];
	for (const option of options) {
		if (keysToSkip.has(option.value)) {
			continue;
		}
		actions.push(
			{ value: option.value, innerHTML: option.innerHTML }
		);
	}
	return actions;
}

/**
 * Creates Shopify-style bulk actions that shows only when items are selected
 * @param {Array<Object>} actions - The array of actions from getBulkActions().
 */
function dashifyBulkActions(actions) {
	// Move bulk actions to after .wrap
	const $bulkActions = document.querySelector('.tablenav.bottom .bulkactions');
	if (!$bulkActions) return;
	document.querySelector('.wrap').insertAdjacentElement('afterend', $bulkActions);

	// Give bulk actions the dashify classname and reorder stuff within
	$bulkActions.classList.add('dashify-bulk-actions');
	$bulkActions.querySelector('#bulk-action-selector-bottom').style.display = "none";
	$bulkActions.querySelector('#doaction2').style.display = "none";

	actions.forEach(({ value, innerHTML }) => {
		const $bulkAction = makeElement('button', { value: value }, { innerHTML: innerHTML }, [{
			event: 'click', handler: (event) => {
				document.querySelector('#bulk-action-selector-bottom').value = event.currentTarget.value;
				document.querySelector('#bulk-action-selector-top').value = event.currentTarget.value;
				document.querySelector('#doaction2').click();
			}
		}]);
		$bulkActions.append($bulkAction);
	});

	// Move hidden form elements to bulk actions space (so it's inside form)
	const $bulkActionsBg = makeElement('div', { class: 'bulkactions-bg' });
	document.querySelector('#the-list').insertAdjacentElement('afterend', $bulkActionsBg);
	$bulkActions.querySelectorAll('select, input').forEach($el => {
		$bulkActionsBg.append($el);
	});

	// Add event listener to checkboxes
	let checked = [];
	document.querySelectorAll('[name="id[]"], [name="post[]"]').forEach($checkbox => {
		$checkbox.addEventListener('change', (event) => {
			const $checkbox = event.currentTarget;
			if (checked.includes($checkbox.id) && !$checkbox.checked) {
				checked.splice(checked.indexOf($checkbox.id), 1);
			} else if (!checked.includes($checkbox.id) && $checkbox.checked) {
				checked.push($checkbox.id);
			}
			if (checked.length > 0) {
				document.querySelector('.dashify-bulk-actions')?.classList.add('show');
				document.querySelector('.bulkactions-bg')?.classList.add('show');
			} else {
				document.querySelector('.dashify-bulk-actions')?.classList.remove('show');
				document.querySelector('.bulkactions-bg')?.classList.remove('show');
			}
		});
	});
	document.querySelector('#cb-select-all-1').addEventListener('change', (event) => {
		const $checkbox = event.currentTarget;
		if ($checkbox.checked) {
			document.querySelectorAll('.check-column input[name="post[]"], .check-column input[name="id[]"]').forEach($cb => {
				$cb.checked = true;
			});
			checked = Array.from(document.querySelectorAll('.check-column input[name="post[]"], .check-column input[name="id[]"]')).map(($cb) => {
				return $cb.id;
			});
			document.querySelector('.dashify-bulk-actions')?.classList.add('show');
			document.querySelector('.bulkactions-bg')?.classList.add('show');
		} else {
			document.querySelectorAll('.check-column input[name="post[]"], .check-column input[name="id[]"]').forEach($cb => {
				$cb.checked = false;
			});
			checked = [];
			document.querySelector('.dashify-bulk-actions')?.classList.remove('show');
			document.querySelector('.bulkactions-bg')?.classList.remove('show');
		}
	});
}

/**
 * Hides top pagination and adjusts bottom pagination
 */
function dashifyPagination() {
	// Hide top pagination
	document.querySelector('.tablenav.top').style.display = 'none';

	// Give pagination the dashify classname and reorder stuff within
	const $bottomPagination = document.querySelector('.tablenav.bottom');
	$bottomPagination.classList.add('dashify-pagination');

	document.querySelector('.dashify-pagination .pagination-links')
		.querySelectorAll('.button span[aria-hidden], .button:not(:has(.screen-reader-text))')
		.forEach(($chevron, index) => {
			$chevron.innerHTML = '';
			$chevron.classList.add('chevron');
			switch (index) {
				case 0:
					$chevron.classList.add('double-left');
					break;
				case 1:
					$chevron.classList.add('left');
					break;
				case 2:
					$chevron.classList.add('right');
					break;
				case 3:
					$chevron.classList.add('double-right');
					break;
				default:
					break;
			}
		});
}

/**
 * Adjusts details for the table cell with order number, order name, and preview button
 */
function dashifyOrderNumberNamePreview() {
	// Remove 'Preview' text from the preview buttons
	document.querySelectorAll('.order-preview').forEach($preview => {
		$preview.innerHTML = '';
		$preview.closest('.order_number').querySelector('.order-view')?.insertAdjacentElement('afterend', $preview);
	});

	// Rearrange order number table cell
	document.querySelectorAll('.wp-list-table tbody .column-order_number').forEach($cell => {
		const $cellItems = makeElement('div', { class: 'dashify-order-number' }, { innerHTML: $cell.innerHTML });
		$cell.innerHTML = '';
		$cell.append($cellItems);
	});
}

/**
 * Makes adjustments to the order status table cell
 */
function dashifyOrderStatus() {
	// Change order status mark
	document.querySelectorAll('mark.order-status').forEach($mark => {
		const $new = makeElement('div', { class: $mark.className }, { innerHTML: $mark.innerHTML });
		$mark.innerHTML = '';
		$mark.insertAdjacentElement('afterend', $new);
		$mark.remove();
	});
}

/**
 * Removes the table footer
 */
function dashifyFooter() {
	// Remove table footer
	document.querySelector('.wp-list-table tfoot').remove();
}

/**
 * Makes the analytics section
 */
function dashifyAnalytics() {
	if (!dashifyAnalyticsData) return;
	let rangeText = '';

	if (dashifyAnalyticsData.analytics_range === 0) {
		rangeText = 'Today';
	} else if (dashifyAnalyticsData.analytics_range === 1) {
		rangeText = '24 hours'
	} else {
		rangeText = `${dashifyAnalyticsData.analytics_range} days`;
	}

	document.querySelector('.wp-header-end').insertAdjacentHTML('afterend', `
		<div class="dashify-analytics">
			<div class="dashify-analytics-date-dropdown">
				<span class="calendar-icon"></span>${rangeText}
				<div class="dashify-analytics-date-dropdown-content">
					<fieldset>
						<div>
							<input
								type="radio"
								id="today"
								name="days"
								value="0"
								${dashifyAnalyticsData.analytics_range === 0 ? 'checked' : ''}
							>
							<label for="today">Today</label>
						</div>
						<div>
							<input
								type="radio"
								id="last-24-hours"
								name="days"
								value="1"
								${dashifyAnalyticsData.analytics_range === 1 ? 'checked' : ''}
							>
							<label for="last-24-hours">Last 24 hours</label>
						</div>
						<div>
							<input
								type="radio"
								id="last-7-days"
								name="days"
								value="7"
								${dashifyAnalyticsData.analytics_range === 7 ? 'checked' : ''}
							>
							<label for="last-7-days">Last 7 days</label>
						</div>
						<div>
							<input
								type="radio"
								id="last-30-days"
								name="days"
								value="30"
								${dashifyAnalyticsData.analytics_range === 30 ? 'checked' : ''}
							>
							<label for="last-30-days">Last 30 days</label>
						</div>
					</fieldset>
				</div>
			</div>
			<div class="dashify-analytics-data-group" scroll-x="0">
				<span class="fade-left"></span>
				<div class="dashify-analytics-data">
					<div class="label">${dashifyAnalyticsData.sections[0].label}</div>
					<div class="data">
						<div class="number">${dashifyAnalyticsData.sections[0].value}</div>
						<div class="arrow"></div>
						<canvas id="dashify-${dashifyAnalyticsData.sections[0].key}">
					</div>
				</div>
				<div class="dashify-analytics-data">
					<div class="label">${dashifyAnalyticsData.sections[1].label}</div>
					<div class="data">
						<div class="number">${dashifyAnalyticsData.sections[1].value}</div>
						<div class="arrow"></div>
						<canvas id="dashify-${dashifyAnalyticsData.sections[1].key}">
					</div>
				</div>
				<div class="dashify-analytics-data">
					<div class="label">${dashifyAnalyticsData.sections[2].label}</div>
					<div class="data">
						<div class="number">${dashifyAnalyticsData.sections[2].value}</div>
						<div class="arrow"></div>
						<canvas id="dashify-${dashifyAnalyticsData.sections[2].key}">
					</div>
				</div>
			</div>
		</div>
	`);
	makeGraph(dashifyAnalyticsData.sections[0]);
	makeGraph(dashifyAnalyticsData.sections[1]);
	makeGraph(dashifyAnalyticsData.sections[2]);

	document.querySelector('.dashify-analytics-date-dropdown').addEventListener(
		'click',
		event => toggleRangeSelection(event.target)
	);

	function toggleRangeSelection(target) {
		target.classList.toggle('expanded');
		if (target.classList.contains('expanded')) {
			const clickOutsideHandler = (event) => {
				if (!event.target.closest('.dashify-analytics-date-dropdown')) {
					document.querySelector('.dashify-analytics-date-dropdown').classList.remove('expanded');
					document.removeEventListener('click', clickOutsideHandler);
				}
			};
			document.addEventListener('click', clickOutsideHandler);
		}
	}

	document.querySelector('.dashify-analytics-data-group').addEventListener('scroll', (event) => {
		event.currentTarget.setAttribute('scroll-x', event.currentTarget.scrollLeft);
	});

	addEventListenersForAnalyticsRangeSelection();
}

function dashifyEmptyTrashButton() {
	const params = new URLSearchParams(window.location.search);
	const status = params.get('status') ?? params.get('post_status');
	if (status !== 'trash') {
		return;
	}

	// We clone it here to keep the filter hiding code in filters() clean.
	const $button = document.querySelector('input#delete_all').cloneNode();
	$button.style.display = 'inline';

	$searchContainer = document.querySelector('.dashify-search');
	$searchContainer.insertAdjacentElement('beforebegin', $button);
	// We never want to show the Empty Trash button when the search is
	// expanded.
	if ($searchContainer.classList.contains('expanded')) {
		$button.style.display = 'none';
	}
}

/**
 * If an order status filter is applied, we add the query param to each link
 * to an individual order, allowing us to maintain that filtering for the next
 * and previous buttons inside the single order view.
 */
function dashifyAddFiltersToOrderLinks() {
	const params = new URLSearchParams(window.location.search);
	const status = params.get('status') ?? params.get('post_status');
	if (!status) {
		return;
	}
	const orderLinks = document.querySelectorAll('a.order-view');
	for (const link of orderLinks) {
		// We carry over the two different query params for HPOS and non-HPOS
		// because if someone navigates using the back button from inside the
		// order view, we'll need the original query param for the filtering to
		// work.
		link.href = `${link.href}&${params.has('status') ? 'status' : 'post_status'}=${status}`;
	}
}

/**
 * Helper function for analytics. Draws the graph in the corresponding canvas element.
 * @param {object} section - The section for which we’re making the graph.
 */
function makeGraph(section) {
	const $canvas = document.querySelector(`canvas#dashify-${section.key}`);
	const ctx = $canvas.getContext('2d');
	const canvasPadding = 4;
	const graphFill = (canvasWidth, canvasHeight, colorStop1 = 0.2, colorStop2 = 1) => {
		const fill = ctx.createLinearGradient(canvasWidth / 2, 0, canvasWidth / 2, canvasHeight);
		fill.addColorStop(colorStop1, '#51b0FF88');
		fill.addColorStop(colorStop2, '#ffffff00');
		return fill;
	}
	if (section.value === 0 || section.value === '$0.00') {
		const flatGraphInnerWidth = 100;
		const flatGraphInnerHeight = 36;
		const flatGraphCanvasWidth = flatGraphInnerWidth + canvasPadding * 2;
		const flatGraphCanvasHeight = flatGraphInnerHeight + canvasPadding * 2;
		$canvas.setAttribute('width', `${flatGraphCanvasWidth}`);
		$canvas.setAttribute('height', `${flatGraphCanvasHeight}`);
		ctx.beginPath();
		ctx.moveTo(canvasPadding, flatGraphCanvasHeight * 2 / 3);
		ctx.lineTo(canvasPadding + flatGraphInnerWidth, flatGraphCanvasHeight * 2 / 3);
		ctx.lineWidth = 2.5;
		ctx.lineCap = 'round';
		ctx.strokeStyle = '#3AA3FF';
		ctx.stroke();
		ctx.beginPath();
		ctx.moveTo(canvasPadding, flatGraphCanvasHeight * 2 / 3);
		ctx.lineTo(canvasPadding + flatGraphInnerWidth, flatGraphCanvasHeight * 2 / 3);
		ctx.lineTo(canvasPadding + flatGraphInnerWidth, canvasPadding + flatGraphInnerHeight);
		ctx.lineTo(canvasPadding, canvasPadding + flatGraphInnerHeight);
		ctx.closePath();
		ctx.fillStyle = graphFill(flatGraphCanvasWidth, flatGraphCanvasHeight, 0.6);
		ctx.fill();
		return;
	}
	const graphDivisions = dashifyAnalyticsData.graphDivisions;
	const innerWidth = graphDivisions > 12 ? 200 : 148;
	const innerHeight = 36;
	const unit = (innerWidth / graphDivisions).toFixed(2);
	const canvasWidth = innerWidth + canvasPadding * 2;
	const canvasHeight = innerHeight + canvasPadding * 2;
	$canvas.setAttribute('width', `${canvasWidth}`);
	$canvas.setAttribute('height', `${canvasHeight}`);
	const heightAtDivision = (i) => {
		const data = dashifyAnalyticsData.intervalData[i] ? dashifyAnalyticsData.intervalData[i][section.key] : 0;
		const ratio = data / dashifyAnalyticsData.divisionMaxima[section.key];
		return (1 - ratio.toFixed(2)) * innerHeight;
	}
	ctx.beginPath();
	const graphXCoord = (graphDivision) => graphDivision * unit + canvasPadding;
	const graphYCoord = (graphDivision) => heightAtDivision(graphDivision) + canvasPadding;
	for (let i = 0; i < graphDivisions; i++) {
		if (graphYCoord(i) < innerHeight + canvasPadding) {
			ctx.moveTo(graphXCoord(i) - 0.5, innerHeight + canvasPadding - 1);
			ctx.lineTo(graphXCoord(i) + 0.5, innerHeight + canvasPadding - 1);
			ctx.stroke();
			ctx.moveTo(graphXCoord(i - 1), graphYCoord(i - 1));
		}
		if (i === 0) {
			ctx.moveTo(graphXCoord(i), graphYCoord(i));
		} else {
			ctx.bezierCurveTo(graphXCoord(i - 1) + 2.5, graphYCoord(i - 1), graphXCoord(i) - 2.5, graphYCoord(i), graphXCoord(i), graphYCoord(i));
		}
	}
	const strokeGradient = ctx.createLinearGradient(canvasWidth / 2, 0, canvasWidth / 2, canvasHeight);
	strokeGradient.addColorStop(.3, '#44B6FF');
	strokeGradient.addColorStop(.9, '#3896F0');
	ctx.strokeStyle = strokeGradient;
	ctx.lineWidth = 3;
	ctx.lineCap = 'round';
	ctx.lineJoin = 'round';
	ctx.stroke();
	ctx.beginPath();
	for (let i = 0; i < graphDivisions; i++) {
		if (i === 0) {
			ctx.moveTo(graphXCoord(i), graphYCoord(i));
		} else {
			ctx.bezierCurveTo(graphXCoord(i - 1) + 2.5, graphYCoord(i - 1), graphXCoord(i) - 2.5, graphYCoord(i), graphXCoord(i), graphYCoord(i));
		}
	}
	ctx.lineTo(graphXCoord(graphDivisions - 1), innerHeight + canvasPadding);
	ctx.lineTo(graphXCoord(0), innerHeight + canvasPadding);
	ctx.lineTo(graphXCoord(0), graphYCoord(0));
	ctx.closePath();
	ctx.lineWidth = 1;
	ctx.fillStyle = graphFill(canvasWidth, canvasHeight);
	ctx.fill();
}

function addEventListenersForAnalyticsRangeSelection() {
	for (const radio of document.querySelectorAll('input[name="days"]')) {
		radio.addEventListener('click', async () => {
			dashifyAnalyticsData = await fetchGraphData(radio.value);

			// wp_send_json_success() puts these into strings, so we have to turn
			// them back to JSON here. This could be refactored so it's not necessary.
			dashifyAnalyticsData.intervalData = JSON.parse(dashifyAnalyticsData.intervalData);
			dashifyAnalyticsData.divisionMaxima = JSON.parse(dashifyAnalyticsData.divisionMaxima);

			document.querySelector('.dashify-analytics').remove();
			dashifyAnalytics();
		});
	}
}

async function fetchGraphData(days) {
	const body = new FormData();
	body.append('_ajax_nonce', dashifyAnalyticsAJAX.nonce);
	body.append('action', 'dashify_order_list_analytics');
	body.append('days', days);
	body.append('is_subscriptions', isDashifySubscriptionList);

	// ajaxurl is globally available in the admin: https://developer.wordpress.org/plugins/javascript/ajax/#url
	const response = await fetch(ajaxurl, {
		method: 'POST',
		body,
	});

	return (await response.json()).data;
}

/**
 * Unhides the page content
 */
function showDashify() {
	// Unhide page elements
	document.querySelector('#wpbody-content')?.classList.add('show');
}

function waitForElement(selector) {
	return new Promise((resolve) => {
		const checkElement = () => {
			const element = document.querySelector(selector);
			if (element !== null) {
				resolve(element);
			} else {
				requestAnimationFrame(checkElement);
			}
		};

		checkElement();
	});
}

/**
 * Add a result count near the search bar in the order list table.
 *
 * This was introduced because Dashify removes the top pagination. The item
 * count, if pagination shows, is only at the bottom and can be easily missed.
 *
 * @returns void
 */
function dashifyListTableSearchResultCount() {
	const params = new URLSearchParams(window.location.search);
	// Don’t show result count unless a search query has been entered.
	if (!params.has('s')) {
		return;
	}

	const total = document.querySelector('.displaying-num')?.textContent.split(' ')[0];
	// Exit if pagination isn’t rendered, since then we wouldn’t have any number to grab.
	// It may not be visible, but it’s usually in the DOM.
	if (!total) {
		return;
	}

	const searchInput =
		document.querySelector('#orders-search-input-search-input')
		|| document.querySelector('#post-search-input');
	if (!searchInput) {
		return;
	}

	searchInput.insertAdjacentHTML('afterend', `
		<span style="white-space: nowrap; color: #707070;">
			${total} result${parseInt(total) === 1 ? '' : 's'}
		</span>
	`);
}
