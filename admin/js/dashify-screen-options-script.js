document.addEventListener('DOMContentLoaded', () => {
	const queryParams = new URLSearchParams(window.location.search);
	const isOrderEdit = queryParams.get('action') === 'edit' || queryParams.get('action') === 'new';
	const showLineItemMenuOrderColumnOptions = dashifyScreenOptions.dashifyProEnabled && isOrderEdit;

	const $form = document.querySelector('#adv-settings');
	const $fieldset = document.createElement('fieldset');
	$fieldset.className = 'dashify-screen-options';
	$fieldset.innerHTML = `
		<legend>Dashify</legend>
		<div style="display: flex;">
			<label>
				<input
					name="dashify_on"
					type="checkbox"
					id="dashify_on"
					${dashifyScreenOptions.dashifyOn ? 'checked="checked"' : ''}
			>Use Dashify</label>
			<div class="${showLineItemMenuOrderColumnOptions ? '' : 'hidden'}">
				<label>
					<input
						name="dashify_line_item_menu_order_column_enabled"
						type="checkbox"
						class="dashify-screen-option"
						${dashifyScreenOptions.lineItemMenuOrderColumnEnabled ? 'checked="checked"' : ''}
				>Line item menu order column</label>
				<fieldset class="${dashifyScreenOptions.lineItemMenuOrderColumnEnabled ? '' : 'hidden'}">
					<legend style="color: #686869;">Default sort order</legend>
					<input
						id="ascending"
						name="dashify_line_item_menu_order_column_default_sort"
						value="asc"
						type="radio"
						${dashifyScreenOptions.lineItemMenuOrderColumnDefaultSort === 'asc' ? 'checked="checked"' : ''}
					>
					<label for="ascending">Lowest first</label>
					<input
						id="descending"
						name="dashify_line_item_menu_order_column_default_sort"
						value="desc"
						type="radio"
						${dashifyScreenOptions.lineItemMenuOrderColumnDefaultSort === 'desc' ? 'checked="checked"' : ''}
					>
					<label for="descending">Highest first</label>
					<input
						id="none"
						name="dashify_line_item_menu_order_column_default_sort"
						value="none"
						type="radio"
						${dashifyScreenOptions.lineItemMenuOrderColumnDefaultSort === 'none' ? 'checked="checked"' : ''}
					>
					<label for="none">None</label>
				</fieldset>
			</div>
		</div>
	`;
	$form.insertAdjacentElement('afterbegin', $fieldset);

	// There are two different paths because the order edit screen’s Screen Options
	// do not have a save button—they save automatically, so we are following suit.
	if (isOrderEdit) {
		document.querySelector('#dashify_on').addEventListener('input', async (event) => {
			const formData = new FormData();
			formData.append('_ajax_nonce', dashifyScreenOptions.nonce);
			formData.append('action', 'save_dashify_on');
			formData.append('dashify_on', event.currentTarget.checked ? 'true' : 'false');
			await fetch(ajaxurl, {
				method: 'POST',
				body: formData
			});
			location.reload();
		});

		// Screen options, not including sub-options. We can later extract this to be used for any option.
		const options = document.querySelectorAll('.dashify-screen-option');
		for (const option of options) {
			option.addEventListener(
				'input',
				dashifyCreateSaveOptionHandler_ScreenOptions(option.name, element => element.checked)
			);
		}
	} else {
		$form.addEventListener('submit', async (event) => {
			event.preventDefault();

			const formData = new FormData();
			formData.append('_ajax_nonce', dashifyScreenOptions.nonce);
			formData.append('action', 'save_dashify_on');
			formData.append('dashify_on', document.querySelector('input#dashify_on').checked ? 'true' : 'false');
			await fetch(ajaxurl, {
				method: 'POST',
				body: formData
			});

			$form.submit();
		});
	}

	// There is a duplicate of this in sortable-menu-order.js — eventually, we
	// can find a way to reuse this reliably across files.
	function dashifyCreateSaveOptionHandler_ScreenOptions(optionName, getValue) {
		return async (event) => {
			const formData = new FormData();
			formData.append('_ajax_nonce', dashifyScreenOptions.nonce);
			formData.append('action', 'save_dashify_option');
			formData.append('option_name', optionName);
			formData.append('option_value', getValue(event.target));

			await fetch(ajaxurl, {
				method: 'POST',
				body: formData
			});

			location.reload();
		};
	}
});
