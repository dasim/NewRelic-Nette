<?php

namespace VrtakCZ\NewRelic\Nette;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\Application\UI\Presenter;
use VrtakCZ\NewRelic\Tracy\Bootstrap;

class Extension extends \Nette\DI\CompilerExtension
{
	/** @var bool */
	private $skipIfIsDisabled;
	/** @var bool */
	private $enabled = TRUE;

	/** @var array */
	public $defaults = array(
		'logLevel' => array(
			\Tracy\Logger::CRITICAL,
			\Tracy\Logger::EXCEPTION,
			\Tracy\Logger::ERROR,
		),
		'rum' => array(
			'enabled' => 'auto',
			'ratio' => 1,
		),
		'transactionTracer' => array(
			'enabled' => TRUE,
			'detail' => 1,
			'recordSql' => 'obfuscated',
			'slowSql' => TRUE,
			'threshold' => 'apdex_f',
			'stackTraceThreshold' => 500,
			'explainThreshold' => 500,
		),
		'errorCollector' => array(
			'enabled' => TRUE,
			'recordDatabaseErrors' => TRUE,
		),
		'parameters' => array(
			'capture' => FALSE,
			'ignored' => array(),
		),
	);

	/**
	 * @param bool
	 */
	public function __construct($skipIfIsDisabled = FALSE)
	{
		$this->skipIfIsDisabled = $skipIfIsDisabled;
	}

	public function loadConfiguration()
	{
		$config = $this->getConfig();
		if ($this->skipIfIsDisabled && (!extension_loaded('newrelic') || !Bootstrap::isEnabled())) {
			$this->enabled = FALSE;
		}

		if (isset($config['enabled']) && !$config['enabled']) {
			$this->enabled = FALSE;
		}

		$this->setupRUM();

		if (!$this->enabled) {
			return;
		}

		if (!extension_loaded('newrelic')) {
			throw new \RuntimeException('NewRelic extension is not loaded');
		} elseif (!Bootstrap::isEnabled()) {
			throw new \RuntimeException('NewRelic is not enabled');
		}

		$this->setupApplicationOnRequest();
		$this->setupApplicationOnError();

		if (isset($config['ratio']) && mt_rand(0, 99) > round($config['ratio'] * 100) - 1) {
			newrelic_ignore_transaction();
		}
	}

	public function afterCompile(ClassType $class)
	{
		if (!$this->enabled) {
			return;
		}

		$config = $this->getConfig($this->defaults);
		$initialize = $class->methods['initialize'];

		// AppName and license
		if (isset($config['appName']) && !is_array($config['appName'])) {
			$initialize->addBody('\VrtakCZ\NewRelic\Tracy\Bootstrap::setup(?, ?);', array(
				$config['appName'], isset($config['license']) ? $config['license'] : NULL
			));
		} elseif (isset($config['appName']) && is_array($config['appName'])) {
			if (!isset($config['appName']['*'])) {
				throw new \RuntimeException('Missing default app name as "*"');
			}
			$initialize->addBody('\VrtakCZ\NewRelic\Tracy\Bootstrap::setup(?, ?);', array(
				$config['appName']['*'], isset($config['license']) ? $config['license'] : NULL
			));
		}

		// Logger
		$initialize->addBody('\Tracy\Debugger::setLogger(new \VrtakCZ\NewRelic\Tracy\Logger(?));', array(
			array_unique($config['logLevel'])
		));

		$this->setupCustom($initialize);

		// Options
		if ($config['rum']['enabled'] !== 'auto') {
			$initialize->addBody('newrelic_disable_autorum();');
		}
		$initialize->addBody("ini_set('newrelic.transaction_tracer.enabled', ?);", array(
			(string) $config['transactionTracer']['enabled'],
		));
		$initialize->addBody("ini_set('newrelic.transaction_tracer.detail', ?);", array(
			(string) $config['transactionTracer']['detail'],
		));
		$initialize->addBody("ini_set('newrelic.transaction_tracer.record_sql', ?);", array(
			(string) $config['transactionTracer']['recordSql'],
		));
		$initialize->addBody("ini_set('newrelic.transaction_tracer.slow_sql', ?);", array(
			(string) $config['transactionTracer']['slowSql'],
		));
		$initialize->addBody("ini_set('newrelic.transaction_tracer.threshold', ?);", array(
			(string) $config['transactionTracer']['threshold'],
		));
		$initialize->addBody("ini_set('newrelic.transaction_tracer.stack_trace_thresholdshow', ?);", array(
			(string) $config['transactionTracer']['stackTraceThreshold'],
		));
		$initialize->addBody("ini_set('newrelic.transaction_tracer.explain_threshold', ?);", array(
			(string) $config['transactionTracer']['explainThreshold'],
		));
		$initialize->addBody("ini_set('newrelic.error_collector.enabled', ?);", array(
			(string) $config['errorCollector']['enabled'],
		));
		$initialize->addBody("ini_set('newrelic.error_collector.record_database_errors', ?);", array(
			(string) $config['errorCollector']['recordDatabaseErrors'],
		));
		$initialize->addBody("newrelic_capture_params(?);", array(
			$config['parameters']['capture'],
		));
		$initialize->addBody("ini_set('newrelic.ignored_params', ?);", array(
			implode(',', $config['parameters']['ignored']),
		));
	}

	private function setupApplicationOnRequest()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig();

		$map = (isset($config['appName']) && is_array($config['appName'])) ? $config['appName'] : array();
		$license = isset($config['license']) ? $config['license'] : NULL;

		$builder->addDefinition($this->prefix('onRequestCallback'))
			->setClass('VrtakCZ\NewRelic\Nette\Callbacks\OnRequestCallback', array(
				$map, $license, isset($config['actionKey']) ? $config['actionKey'] : Presenter::ACTION_KEY,
			))
			->addSetup('register', array('@\Nette\Application\Application'))
			->addTag('run', TRUE);
	}

	private function setupApplicationOnError()
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('onErrorCallback'))
			->setClass('VrtakCZ\NewRelic\Nette\Callbacks\OnErrorCallback')
			->addSetup('register', array('@\Nette\Application\Application'))
			->addTag('run', TRUE);
	}

	private function setupCustom(Method $initialize)
	{
		$config = $this->getConfig();

		if (isset($config['custom']['parameters'])) {
			if (!is_array($config['custom']['parameters'])) {
				throw new \RuntimeException('Invalid custom parameters structure');
			}

			foreach ($config['custom']['parameters'] as $name => $value) {
				$initialize->addBody('\VrtakCZ\NewRelic\Tracy\Custom\Parameters::addParameter(?, ?);', array(
					$name, $value
				));
			}
		}

		if (isset($config['custom']['tracers'])) {
			if (!is_array($config['custom']['tracers'])) {
				throw new \RuntimeException('Invalid custom tracers structure');
			}

			foreach ($config['custom']['tracers'] as $function) {
				$initialize->addBody('\VrtakCZ\NewRelic\Tracy\Custom\Tracers::addTracer(?);', array($function));
			}
		}
	}

	private function setupRUM()
	{
		$config = $this->getConfig($this->defaults);
		$builder = $this->getContainerBuilder();

		$rumEnabled = $this->enabled && $config['rum']['enabled'] === TRUE && mt_rand(0, 99) <= round($config['rum']['ratio'] * 100) - 1;

		$builder->addDefinition($this->prefix('rum.user'))
			->setClass('VrtakCZ\NewRelic\Nette\RUM\User', array($rumEnabled));

		$builder->addDefinition($this->prefix('rum.headerControl'))
			->setClass('VrtakCZ\NewRelic\Nette\RUM\HeaderControl', array($rumEnabled));

		$builder->addDefinition($this->prefix('rum.footerControl'))
			->setClass('VrtakCZ\NewRelic\Nette\RUM\FooterControl', array($rumEnabled));
	}
}
