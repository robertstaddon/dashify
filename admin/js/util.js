function makeElement(tagName, attributes = {}, properties = {}, listeners = []) {
	const $element = document.createElement(tagName);
	Object.entries(attributes).forEach(([key, value]) => {
		$element.setAttribute(key, value);
	});
	Object.entries(properties).forEach(([key, value]) => {
		$element[key] = value;
	});
	listeners.forEach(({ event, handler }) => {
		$element.addEventListener(event, handler);
	});
	return $element;
}