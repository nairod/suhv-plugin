document.addEventListener('click', function (event) {
	var button = event.target.closest('.suhv-copy-shortcode');
	if (!button) {
		return;
	}

	var container = button.closest('.suhv-webcomponents-code');
	var field = container && container.querySelector('textarea');
	var status = container && container.querySelector('.suhv-copy-status');
	if (!field) {
		return;
	}

	function copied() {
		if (status) {
			status.textContent = 'Kopiert.';
		}
	}

	function fallback() {
		field.focus();
		field.select();
		try {
			document.execCommand('copy');
			copied();
		} catch (error) {
			if (status) {
				status.textContent = 'Bitte den Code manuell kopieren.';
			}
		}
	}

	if (navigator.clipboard && window.isSecureContext) {
		navigator.clipboard.writeText(field.value).then(copied, fallback);
	} else {
		fallback();
	}
});
