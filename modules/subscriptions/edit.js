document.addEventListener('DOMContentLoaded', () => {
	dashifySentenceCaseLabels();
});

function dashifySentenceCaseLabels() {
	const orderStatusLabel = document.querySelector('label[for="order_status"]');
	orderStatusLabel.textContent = orderStatusLabel.textContent.replace(':', '');

	const startDate = document.querySelector('#subscription-start-date strong');
	startDate.textContent = startDate.textContent.replace('Date:', 'date:');

	const trialEnd = document.querySelector('#subscription-trial_end-date strong');
	trialEnd.textContent = trialEnd.textContent.replace('End:', 'end');

	const nextPayment = document.querySelector('#subscription-next_payment-date strong');
	nextPayment.textContent = nextPayment.textContent.replace('Payment:', 'payment');

	const endDate = document.querySelector('#subscription-end-date strong');
	endDate.textContent = endDate.textContent.replace('Date:', 'date');
}
