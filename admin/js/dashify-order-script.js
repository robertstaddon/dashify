document.addEventListener('DOMContentLoaded', dashifyOrderView);

/**
 * Move and transform elements to give the order detail page a Shopify-like design.
 *
 * Variables starting with $ reference an Element.
 * Variables referencing HTML strings to be inserted all end with "Template".
 *
 * Newly inserted HTML will have inline styles if those styles only apply to
 * that element, to keep it scoped. If not possible, like for hover state, those
 * will be in dashify-order-styles.css.
 */
function dashifyOrderView() {
	try {
		dashifyTop();
		dashifySidebar();
		dashifyTimeline();
		dashifyCustomFields();
		dashifyFilterParams();
		dashifyAddNewOrder();
		dashifySubscriptions();
	} finally {
		document.dispatchEvent(new Event('DashifyBaseLoaded'));
		showDashify();
	}
}

function dashifyTop() {
	const $number =
		document.querySelector('h2.woocommerce-order-data__heading')
		|| document.querySelector('#order_data h2');

	// Remove it from the original location because we're going to insert it into our own template
	$number.remove();

	// Rename "General" to "Customer"
	document.querySelector('#order_data > .order_data_column_container .order_data_column:first-child h3').textContent = 'Customer';

	const statusText = dashifyGetOrderStatusText();

	let background = '#e5e5e5';
	switch (statusText) {
		case 'Processing':
			background = '#c6e1c6';
			break;
		case 'On hold':
			background = '#f8dda7';
			break;
		case 'Completed':
			background = '#c8d7e1';
			break;
		case 'Failed':
			background = '#eba3a3';
			break;
	}
	const statusTemplate = `
		<span style="
			background: ${background};
			border-radius: 0.5rem;
			margin-left: 0.65rem;
			padding: 0.125rem 0.5rem;
		">
			${statusText}
		</span>
	`;

	// Might not exist for a Subscription.
	const dateExists = document.querySelector('input[name="order_date"]');
	const formattedDate = dateExists ? dashifyGetFormattedDate() : '';

	const $updateButton = document.querySelector('button[name="save"]');
	$updateButton.remove();

	const $deleteButton = document.querySelector('div#delete-action');
	$deleteButton.querySelector('a').textContent = 'Delete';
	$deleteButton.remove();

	const $orderActions = document.querySelector('select[name="wc_order_action"]');
	$orderActions.querySelector('option').textContent = 'More actions';
	$orderActions.remove();

	// Calculate the previous and next order IDs for the to be added order
	// pagination buttons.
	const URLParams = new URLSearchParams(window.location.search);
	// We on purpose do not fall back to 'post' as in post.php?post=38&action=edit
	// if 'id' doesn't exist since the post ids refer to any post on the WordPress
	// site, including coupons, etc., and we'd need to do further checks to make
	// sure we paginate to the next post that is an order. This only affects
	// stores using the legacy "WordPress posts storage" option.
	const paginationTemplate = (!dashify.orders.prev && !dashify.orders.next) ? '' : `
		<a
			${dashify.orders.prev === -1 ? '' : `href="${dashify.previousOrderURL}"`}
			class="pagination"
			style="${dashify.orders.prev === -1 ? 'filter: opacity(0.4); pointer-events: none;' : ''}"
		>
			<svg width="18" height="18" viewBox="0 0 20 20">
				<path fill-rule="evenodd" d="M11.78 5.47a.75.75 0 0 1 0 1.06l-3.47 3.47 3.47 3.47a.75.75 0 1 1-1.06 1.06l-4-4a.75.75 0 0 1 0-1.06l4-4a.75.75 0 0 1 1.06 0Z"></path>
			</svg>
			${dashify.orders.prev === -1 ? '' : `<div class="dashify-tooltip bottom">Previous (J)</div>`}
		</a>
		<a
			${dashify.orders.next === -1 ? '' : `href="${dashify.nextOrderURL}"`}
			class="pagination"
			style="${dashify.orders.next === -1 ? 'filter: opacity(0.4); pointer-events: none;' : ''}"
		>
			<svg width="18" height="18" viewBox="0 0 20 20">
				<path fill-rule="evenodd" d="M7.72 14.53a.75.75 0 0 1 0-1.06l3.47-3.47-3.47-3.47a.75.75 0 0 1 1.06-1.06l4 4a.75.75 0 0 1 0 1.06l-4 4a.75.75 0 0 1-1.06 0Z"></path>
			</svg>
			${dashify.orders.next === -1 ? '' : `<div class="dashify-tooltip bottom">Next (K)</div>`}
		</a>
	`;

	let orderListPage = URLParams.has('id')
		? 'admin.php?page=wc-orders' // HPOS
		: 'edit.php?post_type=shop_order'; // Legacy order storage

	if (dashifyIsSubscriptionEdit) {
		orderListPage = URLParams.has('id')
			? 'admin.php?page=wc-orders--shop_subscription' // HPOS
			: 'edit.php?post_type=shop_subscription' // Legacy order storage
	}

	const dashifyTopTemplate = `
		<div
			id="dashify-order-top"
			style="
				display: flex;
				justify-content: space-between;
				align-items: flex-start;
				margin-top: 1rem;
			"
		>
			<div
				style="
					display: flex;
					flex-direction: column;
				"
			>
				<div style="display: flex; align-items: center;">
					<a
						class="back-button"
						href="${dashify.adminURL}${orderListPage}"
						style="
							border-radius: 0.5rem;
							padding: 0.4rem;
							margin-right: 0.25rem;
						">
						<svg style="width: 1.25rem; height: 1rem;">
							<path fill-rule="evenodd" d="M16.75 10a.75.75 0 0 1-.75.75h-9.69l2.72 2.72a.75.75 0 0 1-1.06 1.06l-4-4a.75.75 0 0 1 0-1.06l4-4a.75.75 0 0 1 1.06 1.06l-2.72 2.72h9.69a.75.75 0 0 1 .75.75Z"></path>
						</svg>
					</a>
					${$number.outerHTML}
					${statusTemplate}
				</div>
				<div style="margin-left: 2.25rem; margin-top: 0.125rem;">
					${formattedDate}
				</div>
			</div>
			<div
				id="order-action-buttons"
				style="
					display: flex;
					align-items: center;
					margin-right: 0.1rem;
				"
			>
				${$deleteButton.outerHTML}
				${$orderActions.outerHTML}
				${$updateButton.outerHTML}
				${paginationTemplate}
			</div>
		</div>
	`;

	const $container = document.querySelector('form#order') ?? document.querySelector('form#post');
	$container.insertAdjacentHTML('afterbegin', dashifyTopTemplate);

	document.addEventListener('keydown', (event) => {
		if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) || !['j', 'k'].includes(event.key.toLowerCase()) || document.querySelector('.select2-container--open')) {
			return;
		}
		event.preventDefault();
		if (event.key.toLowerCase() === 'j' && dashify.orders.prev !== -1) {
			document.querySelector('#order-action-buttons .pagination:first-of-type').click();
		} else if (event.key.toLowerCase() === 'k' && dashify.orders.next !== -1 && !(event.metaKey || event.ctrlKey)) {
			document.querySelector('#order-action-buttons .pagination:last-of-type').click();
		}
	});
}

function dashifyGetFormattedDate() {
	// Take the date from the input fields and prepare it for display in a more
	// readable, region-specific string.
	const date = document.querySelector('input[name="order_date"]').value;
	const hour = document.querySelector('input[name="order_date_hour"').value;
	const minute = document.querySelector('input[name="order_date_minute"').value;
	const second = document.querySelector('input[name="order_date_second"').value;
	const dateTimeString = `${date}T${hour}:${minute}:${second}Z`;
	// This will be incorrect if the time zone of the WordPress site is set to
	// anything other than UTC. We'll correct it in the next step.
	const incorrectDate = new Date(dateTimeString);
	// Suppose the actual local time is 11:00, so in UTC time it will be 16:00.
	// We only have the local time to pass to Date() above. Anything passed to
	// Date() is assumed to be UTC, so it will subtract 5 more hours, causing
	// the time to be shown as 6:00. Below, we add those subtracted hours back.
	//
	// For the case of a positive offset of GMT+5:30, let's suppose the actual
	// local time is 10:00, so in actual UTC time it's 4:30. When we pass 10:00
	// to Date(), it's assuming UTC so it will add 5:30, making the time shown
	// be 15:30, which is incorrect. We take the UNIX timestamp, get the
	// timezone offset which will be a negative value, then add those negative
	// milliseconds to the UNIX timestamp of the date which is incorrectly
	// ahead, bringing it back to where it should be.
	//
	// If the person viewing the time is not in the same time zone as what the
	// WordPress settings is set to, then it may appear off.
	const correctDate = new Date(incorrectDate.getTime() + (new Date(incorrectDate).getTimezoneOffset() * 60000));
	return correctDate.toLocaleString(navigator.language, {
		dateStyle: 'full',
		timeStyle: 'short',
		timeZone: dashify.timeZone,
	});
}

function dashifyGetOrderStatusText() {
	const $status = document.querySelector('select#order_status');
	return $status.options[$status.selectedIndex].text;
}

/**
 * Moves elements into the sidebar and styles them to fit.
 */
function dashifySidebar() {
	// Move customer info to sidebar
	$customer =
		document.querySelector('#woocommerce-order-data')
		|| document.querySelector('#order_data'); // WooCommerce Subscriptions

	// Move “Order attribution” and “Customer history” below the customer info
	// box, if they are present and above the customer info.
	$attribution = document.querySelector('#woocommerce-order-source-data');
	$is_subscription = document.querySelector('#woocommerce-subscription-data');
	if ($attribution && comesBefore($attribution, $customer) && !$is_subscription) {
		$customer.after($attribution);
	}
	$history = document.querySelector('#woocommerce-customer-history');
	if ($history && comesBefore($history, $customer) && !$is_subscription) {
		$customer.after($history);
	}

	function comesBefore(node1, node2) {
		return !!(node1.compareDocumentPosition(node2) & Node.DOCUMENT_POSITION_FOLLOWING);
	}

	// Move order status out of under the customer heading
	$status =
		document.querySelector('.wc-order-status')
		|| document.querySelector('select#order_status').parentElement;
	document.querySelector('.order_data_column_container').insertAdjacentElement('afterbegin', $status);

	// Update label text while preserving html that is also in the label
	$statusLabel = document.querySelector('label[for="order_status"]');
	$statusLabel.innerHTML = $statusLabel.innerHTML.replace('Status:', 'Status');
	$customerLabel = document.querySelector('label[for="customer_user"]');
	$customerLabel.innerHTML = $customerLabel.innerHTML.replace('Customer:', '').replace(' Profile', 'Profile');

	// Move "View other orders", "Profile", and other customer links below the customer select box
	waitForElement('.wc-customer-user .select2-container').then(() => {
		document.querySelector('.wc-customer-user .select2-container').insertAdjacentElement('afterend', $customerLabel)
	});
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

function dashifyTimeline() {
	const $header = document.querySelector('#woocommerce-order-notes h2');
	$header.textContent = 'Timeline';
	// Prevent the timeline from being collapsed since it has no background color
	// and if accidentally clicked on, the timeline seems to disappear altogether
	$header.classList.remove('hndle');

	const $screenOption = document.querySelector('label[for="woocommerce-order-notes-hide"');
	$screenOption.childNodes[$screenOption.childNodes.length - 1].nodeValue = 'Timeline';

	// Move new note box to top of timeline
	$addNote = document.querySelector('#woocommerce-order-notes .add_note');
	$notes = document.querySelector('#woocommerce-order-notes .order_notes');
	$notes.before($addNote);

	// Allow the textarea to auto expand
	const $addNoteTextarea = document.querySelector('textarea#add_order_note');
	$addNoteTextarea.parentNode.classList = 'grow-wrap';
	$addNoteTextarea.removeAttribute('rows');
	$addNoteTextarea.removeAttribute('cols');
	const growers = document.querySelectorAll(".grow-wrap");
	growers.forEach((grower) => {
		const textarea = grower.querySelector("textarea");
		textarea.addEventListener("input", () => {
			grower.dataset.replicatedValue = textarea.value;
		});
	});

	$addNoteTextarea.setAttribute('placeholder', 'Leave a note…');
}

function dashifyCustomFields() {
	$header = document.querySelector('#order_custom h2') ?? document.querySelector('#postcustom h2');
	// Make sentence case for consistency
	$header.textContent = 'Custom fields';

	$addLabel = document.querySelector('#postcustomstuff p strong');
	$addLabel.textContent = 'Add new custom field';

	$addCustomFieldButton = document.querySelector('#newmeta-submit');
	$addCustomFieldButton.value = 'Add custom field';
}

/**
 * If someone entered the order view with a filter applied, we want to carry
 * that filter as they navigate to a next or previous order, or if they exit
 * back to the order list.
 */
function dashifyFilterParams() {
	const params = new URLSearchParams(window.location.search);
	const status = params.get('status') ?? params.get('post_status');
	if (!status || status === 'all') {
		return;
	}
	const orderNavigationLinks = document.querySelectorAll('a.back-button, a.pagination');
	for (const link of orderNavigationLinks) {
		// We carry over the two different query params for HPOS and non-HPOS
		// because if someone navigates using the back button from inside the
		// order view, we'll need the original query param for the filtering to
		// work.
		link.href = `${link.href}&${params.has('status') ? 'status' : 'post_status'}=${status}`;
	}

	dashifyShowActiveFilterMessage(dashifyGetOrderStatusText());
}

function dashifyShowActiveFilterMessage(status) {
	const template = `
	<div class="dashify-banner-informational">
		<h2>Order status filter applied</h2>
		<p>Since you viewed this order after setting the status filter to <b>${status}</b>, the <b>next</b> and <b>previous</b> order buttons will go to the next order of the same status.</p>
		<button class="button" id="clear-status-filter-button">Clear filter</button>
	<div>
	`;
	const $form = document.querySelector('form#order') ?? document.querySelector('form#post');
	$form.insertAdjacentHTML('beforebegin', template);
	document.querySelector('#clear-status-filter-button').addEventListener(
		'click',
		() => {
			const url = new URL(location.href);
			url.searchParams.delete('status');
			url.searchParams.delete('post_status');
			window.location.href = url;
		});
}

/**
 * Adjustments for the "Add new order" page.
 */
function dashifyAddNewOrder() {
	// If the table is empty it creates empty space at the top of the space, so
	// upon first load, we hide it if there are no products. After adding a
	// product, WooCommerce replaces the table and so it will become visible.
	const hasProducts = document.querySelector('#order_line_items').children.length > 0;
	if (hasProducts) {
		return;
	}
	const productTable = document.querySelector('.woocommerce_order_items');
	productTable.style.display = 'none';
}

/**
 * Adjustments for elements that WooCommerce Subscriptions adds.
 */
function dashifySubscriptions() {
	const $relatedOrders = document.querySelector('#subscription_renewal_orders');
	if (!$relatedOrders) {
		// If the related orders is not shown (either no WooCommerce Subscrpitions
		// or it's been turned off from the Screen Options, then we don't need to
		// do anything since there are no other Subscriptions elements on the order
		// edit page.
		return;
	}
	const $orderItems = document.querySelector('#woocommerce-order-items');
	$orderItems.after($relatedOrders);

	// Sentence case title.
	$relatedOrders.querySelector('h2').textContent = 'Related orders';
}

function showDashify() {
	document.querySelector('#wpbody-content').classList.add('show');
}
