[NewRelic](http://newrelic.com) PHP agent integration for [Nette Framework](http://nette.org)
=============================================================================================

[![Latest Stable Version](https://poser.pugx.org/Vrtak-CZ/NewRelic-Nette/version.png)](https://packagist.org/packages/vrtak-cz/newrelic-nette)
[![Composer Downloads](https://poser.pugx.org/Vrtak-CZ/NewRelic-Nette/d/total.png)](https://packagist.org/packages/vrtak-cz/newrelic-nette)
[![Dependency Status](https://www.versioneye.com/user/projects/534bc43bfe0d0784f300004a/badge.svg?style=flat)](https://www.versioneye.com/user/projects/534bc43bfe0d0784f300004a)

Installation
------------

```
composer require vrtak-cz/newrelic-nette
```

edit `app/config/config.neon`

```yaml
extensions:
    newrelic: VrtakCZ\NewRelic\Nette\Extension
```

Config
------

```yaml
newrelic:
	enabled: Yes #default
	ratio: 1
	appName: YourApplicationName #optional
	license: yourLicenseCode #optional
	actionKey: action # default - optional - action parameter name
	logLevel: #defaults
		- critical
		- exception
		- error

	# optional options with default values
	rum:
		enabled: auto # other options are Yes/No
		ratio: 1
	transactionTracer:
		enabled: Yes
		detail: 1
		recordSql: obfuscated
		slowSql: Yes
		threshold: apdex_f
		stackTraceThreshold: 500
		explainThreshold: 500
	errorCollector:
		enabled: Yes
		recordDatabaseErrors: Yes
	parameters:
		capture: No
		ignored: []
	customParameters:
		paramName: paramValue
```

Realtime User Monitoring
------------------------

add this component factory to your base presenter

```php
protected function createComponentNewRelicHeader()
{
	$control = $this->context->getService('newrelic.rum')->headerControl;
	$control->disableScriptTag(); // optionall
	return $control;
}

protected function createComponentNewRelicFooter()
{
	$control = $this->context->getService('newrelic.rum')->footerControl;
	$control->disableScriptTag(); // optionall
	return $control;
}
```

and add this to your `@layout` header (before `</head>`)

```smarty
{control newRelicHeader}
```

and add this to your `@layout` footer (before `</body>`)

```smarty
{control newRelicFooter}
```

License
-------
NewRelic Nette is licensed under the MIT License - see the LICENSE file for details
