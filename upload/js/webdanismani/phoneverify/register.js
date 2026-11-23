/*
 * WD Telefon Doğrulama - kayıt formu
 * Web Danışmanı · webdanismani.com · Destek: oblifex.com
 *
 * Olay yetkilendirmesi kullanılır; kayıt formu açılır pencerede (overlay) sonradan
 * yüklendiğinde de çalışır. jQuery gerektirmez (XenForo 2.2 ve 2.3).
 */
(function (window, document)
{
	'use strict';

	if (window.wdpvRegisterInit)
	{
		return;
	}
	window.wdpvRegisterInit = true;

	var timers = {};

	function closest(el, selector)
	{
		while (el && el.nodeType === 1)
		{
			if ((el.matches || el.msMatchesSelector).call(el, selector))
			{
				return el;
			}
			el = el.parentNode;
		}
		return null;
	}

	function setStatus(btn, text, ok)
	{
		var group = closest(btn, '.wdpv-group');
		var status = group ? group.nextElementSibling : null;
		if (!status || !status.hasAttribute('data-wdpv-status'))
		{
			return;
		}
		status.textContent = text || '';
		status.className = 'wdpv-status' + (text ? (ok ? ' is-ok' : ' is-error') : '');
	}

	function setLabel(btn, text)
	{
		var span = btn.querySelector('.button-text');
		(span || btn).textContent = text;
	}

	function countdown(btn, seconds)
	{
		var id = btn.getAttribute('data-wdpv-id');
		if (!id)
		{
			id = 'b' + Math.random().toString(36).slice(2);
			btn.setAttribute('data-wdpv-id', id);
		}
		clearInterval(timers[id]);

		var left = seconds;
		btn.disabled = true;

		var tick = function ()
		{
			if (left <= 0 || !document.body.contains(btn))
			{
				clearInterval(timers[id]);
				btn.disabled = false;
				setLabel(btn, btn.getAttribute('data-label-again') || btn.getAttribute('data-label'));
				return;
			}
			setLabel(btn, (btn.getAttribute('data-label-wait') || '{s}').replace('{s}', left));
			left--;
		};
		tick();
		timers[id] = setInterval(tick, 1000);
	}

	function csrfToken(form)
	{
		if (window.XF && XF.config && XF.config.csrf)
		{
			return XF.config.csrf;
		}
		var input = form ? form.querySelector('input[name="_xfToken"]') : null;
		return input ? input.value : '';
	}

	function send(btn)
	{
		var group = closest(btn, '.wdpv-group');
		var input = group ? group.querySelector('[data-wdpv-phone]') : null;
		var form = closest(btn, 'form');
		if (!input)
		{
			return;
		}

		var phone = input.value.trim();
		if (!phone)
		{
			input.focus();
			return;
		}

		btn.disabled = true;
		setLabel(btn, btn.getAttribute('data-label-sending') || '...');
		setStatus(btn, '', true);

		var body = new FormData();
		body.append('phone', phone);
		body.append('_xfToken', csrfToken(form));
		body.append('_xfResponseType', 'json');

		fetch(btn.getAttribute('data-url'), {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
		})
			.then(function (r) { return r.json(); })
			.then(function (data)
			{
				var res = data && data.wdpv;
				if (!res)
				{
					var err = data && data.errors ? (Array.isArray(data.errors) ? data.errors[0] : data.errors) : null;
					setStatus(btn, err || btn.getAttribute('data-label-error'), false);
					btn.disabled = false;
					setLabel(btn, btn.getAttribute('data-label'));
					return;
				}

				setStatus(btn, res.message, res.ok);

				if (res.wait > 0)
				{
					countdown(btn, res.wait);
				}
				else
				{
					btn.disabled = false;
					setLabel(btn, btn.getAttribute(res.ok ? 'data-label-again' : 'data-label'));
				}

				if (res.ok && form)
				{
					var code = form.querySelector('input[name="wdpv_code"]');
					if (code)
					{
						code.value = '';
						code.focus();
					}
				}
			})
			.catch(function ()
			{
				setStatus(btn, btn.getAttribute('data-label-error'), false);
				btn.disabled = false;
				setLabel(btn, btn.getAttribute('data-label'));
			});
	}

	document.addEventListener('click', function (e)
	{
		var btn = closest(e.target, '[data-wdpv-send]');
		if (!btn || btn.disabled)
		{
			return;
		}
		e.preventDefault();
		send(btn);
	});

	// Telefon kutusunda Enter: formu göndermek yerine kodu gönder.
	document.addEventListener('keydown', function (e)
	{
		if (e.key !== 'Enter' || !e.target || !e.target.hasAttribute || !e.target.hasAttribute('data-wdpv-phone'))
		{
			return;
		}
		e.preventDefault();
		var group = closest(e.target, '.wdpv-group');
		var btn = group ? group.querySelector('[data-wdpv-send]') : null;
		if (btn && !btn.disabled)
		{
			send(btn);
		}
	});

	// Kod kutusuna yalnızca rakam.
	document.addEventListener('input', function (e)
	{
		var t = e.target;
		if (t && t.name === 'wdpv_code')
		{
			var v = t.value.replace(/\D+/g, '');
			if (v !== t.value)
			{
				t.value = v;
			}
		}
	});
})(window, document);
