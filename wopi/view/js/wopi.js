window.addEventListener('message', function(event) {
	// Make sure the message is coming from the expected origin
	if (event.origin === wopiBaseURL) {
		data = JSON.parse(event.data);
		if (data.MessageId === 'UI_Close') {
			wopiCloseIframe();
		}
	}
});

document.addEventListener("DOMContentLoaded", function() {
	let fileLinks = document.querySelectorAll('.file_link');

	fileLinks.forEach(function (element) {
		element.addEventListener('click', function(event) {
			let id = event.srcElement.dataset.id;
			let type = event.srcElement.dataset.type;

			if (wopiSupportedTypes.includes(type)) {
				event.preventDefault();
				wopiOpenIframe(baseurl + '/wopi/' + id);
			}
		});
	});

});

function wopiOpenIframe(src) {
	const iframe = document.createElement('iframe');
	iframe.id = 'wopi_iframe';
	iframe.src = src;
	iframe.style.width = '100%';
	iframe.style.height = '100%';
	iframe.style.position = 'fixed';
	iframe.style.top = 0;
	iframe.style.left = 0;
	iframe.style.zIndex = 2000;

	document.body.style.overflow = 'hidden';
	document.body.appendChild(iframe);
}

function wopiCloseIframe() {
	document.getElementById('wopi_iframe').remove();
	document.body.style.overflow = '';
}
