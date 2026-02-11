document.addEventListener('DOMContentLoaded', () => {
	// dashifyDismissible is the global data object added from the PHP.
	const dd = dashifyDismissible;

	const $dismissible = document.createElement('div');
	$dismissible.className = 'dashify-dismissible';

	const header = `<div class="dashify-dismissible-header">
		<h1>${dd.heading}</h1>
		<div class="description">${dd.description}</div>
	</div>`;

	let mainContent = '';
	if (typeof dd.content === 'string') {
		mainContent = dd.content;
	} else {
		dd.content.forEach(section => {
			let sectionStr = readSection(section);
			mainContent += sectionStr;
		});
	}

	const content = `<div class="dashify-dismissible-content">
		${mainContent}
	</div>`;

	const $dismissButton = document.createElement('button');
	$dismissButton.classList.add('dashify-dismissible-button');
	$dismissButton.addEventListener('click', async () => hideAndSendDismissRequest());
	$dismissible.innerHTML = `${header}${content}`;
	$dismissible.append($dismissButton);

	const $dismissForever = document.createElement('button');
	$dismissForever.innerText = 'Don’t show this again'
	$dismissForever.classList.add('dashify-dismiss-forever');
	$dismissible.append($dismissForever);
	$dismissForever.addEventListener('click', async () => hideAndSendDismissRequest({forever: true}));

	async function hideAndSendDismissRequest(options) {
		const $notice = document.querySelector('.dashify-dismissible');
		$notice.classList.add('fade-out');
		setTimeout(() => {
			$notice.remove();
		}, 300);
		const formData = new FormData();
		formData.append('_ajax_nonce', dashifyDismissible.nonce);
		formData.append('action', 'mark_notice_dismissed');
		if (options && options.forever) {
			formData.append('option', 'forever');
		}
		// ajaxurl is globally available in the admin: https://developer.wordpress.org/plugins/javascript/ajax/#url
		await fetch(ajaxurl, {
			method: 'POST',
			body: formData
		});
	}

	document.querySelector('#wpbody-content').append($dismissible);
});

function readSection(section) {
	let content = '';
	if (typeof section.content === 'string') {
		content = `<div>${section.content}</div>`;
	} else {
		content += '<ul>';
		section.content.forEach(item => {
			content += `<li><span>${item}</span></li>`;
		});
		content += '</ul>';
	}
	return `<div class="dashify-dismissible-section"><h2>${section.heading}</h2>${content}</div>`;
}
